<?php
/**
 * Front controller — guest verification token validation.
 * Verifies the token sent to the guest email and, on success, stores a
 * signed verified-order reference in session so the select controller can load it.
 *
 * Security rules:
 *  - State changes happen via POST only.
 *  - GET with token param shows an auto-submit page (PR/redirect pattern).
 *  - Token attempts are rate-limited.
 *  - Failed attempts are generic to prevent oracle attacks.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalVerifyModuleFrontController extends ModuleFrontController
{
    /** @var array */
    public $verifyErrors = array();

    /** @var string */
    public $php_self = 'module';

    /** @var bool */
    public $auth = false;

    /** @var bool */
    public $guestAllowed = true;

    /**
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            $this->verifyErrors[] = $this->module->l('The withdrawal form is currently unavailable.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        $getToken = Tools::getValue('token', '');

        if (Tools::isSubmit('pcwdl_verify_submit')) {
            $this->processPost();
        } elseif ('' !== $getToken) {
            // Auto-submit pattern: render a page with a hidden form that self-submits
            $this->renderAutoSubmit((string) $getToken);
        } else {
            $this->renderVerifyForm();
        }
    }

    /**
     * Process the POST token verification.
     *
     * @return void
     */
    protected function processPost()
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;

        $csrfToken = Tools::getValue('pcwdl_csrf_token', '');
        if (!$csrfManager->validateToken('verify', $csrfToken, $idShop)) {
            $this->verifyErrors[] = $this->module->l('Security token invalid. Please refresh the page and try again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        // Rate limiting
        $rateLimiter = isset($container['rate_limiter'])
            ? $container['rate_limiter']
            : new PerpetualCode\PcWithdrawal\Security\RateLimiter($container['rate_limit_repository']);

        $ip = $this->getClientIp();

        if ($rateLimiter->isLimitExceeded($idShop, 'verify_identity', $ip)) {
            // Generic message — do not reveal rate limiting
            $this->verifyErrors[] = $this->module->l('Verification failed. Please check your link and try again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        $submittedToken = Tools::getValue('pcwdl_token', '');
        if ('' === $submittedToken || strlen($submittedToken) > 128) {
            $this->verifyErrors[] = $this->module->l('Verification failed. Please check your link and try again.', 'verify');
            $rateLimiter->hit($idShop, 'verify_identity', $ip);
            $this->renderVerifyForm();
            return;
        }

        // Retrieve session-stored hashes set during guest identify POST
        $emailHash    = isset($_SESSION['pcwdl_guest_email_hash'])     ? $_SESSION['pcwdl_guest_email_hash']     : '';
        $orderRefHash = isset($_SESSION['pcwdl_guest_order_ref_hash']) ? $_SESSION['pcwdl_guest_order_ref_hash'] : '';

        if ('' === $emailHash) {
            $this->verifyErrors[] = $this->module->l('Verification session expired. Please start again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        /** @var PerpetualCode\PcWithdrawal\Repository\VerificationRepository $verificationRepo */
        $verificationRepo = $container['verification_repository'];

        $record = $verificationRepo->findActive($idShop, 'guest_order', $emailHash);

        if (!$record) {
            $rateLimiter->hit($idShop, 'verify_identity', $ip);
            $this->verifyErrors[] = $this->module->l('Verification failed or link has expired. Please start again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        $submittedTokenHash = hash('sha256', $submittedToken);
        $storedTokenHash    = (string) $record['token_hash'];

        if (!hash_equals($storedTokenHash, $submittedTokenHash)) {
            $verificationRepo->incrementAttempt((int) $record['id_pcwithdrawal_verification']);
            $rateLimiter->hit($idShop, 'verify_identity', $ip);
            $this->verifyErrors[] = $this->module->l('Verification failed. Please check your link and try again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        // Token valid — verify order ref hash matches session (prevent token reuse across refs)
        $recordOrderRefHash = (string) $record['order_reference_hash'];
        if ('' !== $recordOrderRefHash && '' !== $orderRefHash) {
            if (!hash_equals($recordOrderRefHash, $orderRefHash)) {
                $verificationRepo->incrementAttempt((int) $record['id_pcwithdrawal_verification']);
                $this->verifyErrors[] = $this->module->l('Verification failed. Please start again.', 'verify');
                $this->renderVerifyForm();
                return;
            }
        }

        // Mark token used
        $verificationRepo->markUsed((int) $record['id_pcwithdrawal_verification']);

        // Now look up the actual order from the stored order reference hash
        // We stored the order ref hash in session but not the clear-text ref.
        // We need to retrieve the clear-text from the verification record or session.
        // Approach: look up all orders for this shop and find the one whose ref hash matches.
        // For performance, the guest must have the ref in the token/session context.
        // Re-fetch via the verification record: we store the order_reference_hash in the DB
        // but not clear-text (by design). Instead we also need to store an encrypted ref.
        // Pragmatic approach: also store the order_reference plain hash allows us to match,
        // but we still need the clear text. Store it encrypted in session at identify time.

        $encOrderRef = isset($_SESSION['pcwdl_guest_order_ref_enc']) ? $_SESSION['pcwdl_guest_order_ref_enc'] : '';

        $verifiedOrderRef = $this->decryptSessionValue($encOrderRef);

        if ('' === $verifiedOrderRef) {
            // Fallback: session lost — cannot proceed without order reference
            $this->verifyErrors[] = $this->module->l('Verification session expired. Please start again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        /** @var PerpetualCode\PcWithdrawal\Service\OrderIdentificationService $orderService */
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        $order = $orderService->findOrderByReference($verifiedOrderRef, $idShop);

        if (!$order) {
            // Should not happen at this point, but handle defensively
            $this->verifyErrors[] = $this->module->l('Order not found. Please start again.', 'verify');
            $this->renderVerifyForm();
            return;
        }

        // Store verified order in session, signed
        $idOrder          = (int) $order['id_order'];
        $sessionSignature = hash_hmac('sha256', (string) $idOrder, session_id() . _COOKIE_KEY_);

        $_SESSION['pcwdl_verified_order_id']        = $idOrder;
        $_SESSION['pcwdl_verified_order_signature'] = $sessionSignature;
        $_SESSION['pcwdl_identify_mode']            = 'guest';

        // Clear verification session data
        unset(
            $_SESSION['pcwdl_guest_email_hash'],
            $_SESSION['pcwdl_guest_order_ref_hash'],
            $_SESSION['pcwdl_guest_order_ref_enc']
        );

        Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'select'));
    }

    /**
     * Render the auto-submit page (GET with token in URL).
     *
     * @param string $token
     *
     * @return void
     */
    protected function renderAutoSubmit($token)
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;
        $csrfToken   = $csrfManager->generateToken('verify', $idShop);

        $this->context->smarty->assign(array(
            'pcwdl_auto_submit' => true,
            'pcwdl_token'       => htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
            'pcwdl_csrf_token'  => $csrfToken,
            'pcwdl_action_url'  => $this->context->link->getModuleLink('pcwithdrawal', 'verify'),
            'pcwdl_errors'      => $this->verifyErrors,
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/verify.tpl');
    }

    /**
     * Render the manual token entry form.
     *
     * @return void
     */
    protected function renderVerifyForm()
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;
        $csrfToken   = $csrfManager->generateToken('verify', $idShop);

        $this->context->smarty->assign(array(
            'pcwdl_auto_submit' => false,
            'pcwdl_token'       => '',
            'pcwdl_csrf_token'  => $csrfToken,
            'pcwdl_action_url'  => $this->context->link->getModuleLink('pcwithdrawal', 'verify'),
            'pcwdl_errors'      => $this->verifyErrors,
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/verify.tpl');
    }

    /**
     * Decrypt a session-stored encrypted order reference.
     * Uses a reversible XOR cipher keyed by session ID + shop cookie key.
     * Not intended for long-term storage — session lifetime only.
     *
     * @param string $encrypted Base64-encoded encrypted value.
     *
     * @return string Plain text or empty string on failure.
     */
    protected function decryptSessionValue($encrypted)
    {
        if ('' === $encrypted) {
            return '';
        }

        $decoded = base64_decode($encrypted, true);
        if (false === $decoded) {
            return '';
        }

        $key    = hash('sha256', session_id() . _COOKIE_KEY_, true);
        $keyLen = strlen($key);
        $result = '';

        for ($i = 0; $i < strlen($decoded); $i++) {
            $result .= chr(ord($decoded[$i]) ^ ord($key[$i % $keyLen]));
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->registerStylesheet(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/css/front.css',
            array('media' => 'all', 'priority' => 200)
        );

        $this->registerJavascript(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/js/front.js',
            array('position' => 'bottom', 'priority' => 200)
        );
    }
}
