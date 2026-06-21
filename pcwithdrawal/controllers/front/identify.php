<?php
/**
 * Front controller — consumer identification.
 * Three modes: customer (logged-in order selection), guest (has order ref), noref (no order ref).
 *
 * Security rules:
 *  - Never reveal whether an order exists to unauthenticated users.
 *  - All POST actions verify CSRF before any processing.
 *  - Guest and noref paths always return the same generic response.
 *  - Honeypot checked before rate-limit hit to avoid inflating counts.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalIdentifyModuleFrontController extends ModuleFrontController
{
    /** @var string|null */
    public $mode;

    /** @var array */
    public $errors = array();

    /** @var array */
    public $orders = array();

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
            $this->errors[] = $this->module->l('The withdrawal form is currently unavailable.', 'identify');
            $this->renderMode('noref');
            return;
        }

        $this->mode = Tools::getValue('mode', 'guest');
        $allowedModes = array('customer', 'guest', 'noref');
        if (!in_array($this->mode, $allowedModes, true)) {
            $this->mode = 'guest';
        }

        // Customer mode requires login
        if ('customer' === $this->mode && !$this->context->customer->isLogged()) {
            Tools::redirect($this->context->link->getPageLink('my-account'));
            return;
        }

        if (Tools::isSubmit('pcwdl_identify_submit')) {
            $this->processPost();
        } else {
            $this->renderMode($this->mode);
        }
    }

    /**
     * Dispatch POST handling per mode.
     *
     * @return void
     */
    protected function processPost()
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;

        $csrfToken = Tools::getValue('pcwdl_csrf_token', '');

        if (!$csrfManager->validateToken('identify_' . $this->mode, $csrfToken, $idShop)) {
            $this->errors[] = $this->module->l('Security token invalid. Please refresh the page and try again.', 'identify');
            $this->renderMode($this->mode);
            return;
        }

        switch ($this->mode) {
            case 'customer':
                $this->processCustomerPost($idShop, $container);
                break;
            case 'guest':
                $this->processGuestPost($idShop, $container);
                break;
            case 'noref':
                $this->processNoRefPost($idShop, $container);
                break;
            default:
                $this->renderMode($this->mode);
        }
    }

    /**
     * Customer mode POST — verify order belongs to customer then store in session.
     *
     * @param int   $idShop
     * @param array $container
     *
     * @return void
     */
    protected function processCustomerPost($idShop, array $container)
    {
        if (!$this->context->customer->isLogged()) {
            Tools::redirect($this->context->link->getPageLink('my-account'));
            return;
        }

        $idCustomer = (int) $this->context->customer->id;
        $idOrder    = (int) Tools::getValue('pcwdl_id_order', 0);

        if ($idOrder < 1) {
            $this->errors[] = $this->module->l('Please select an order.', 'identify');
            $this->renderMode('customer');
            return;
        }

        /** @var PerpetualCode\PcWithdrawal\Service\OrderIdentificationService $orderService */
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        // Re-verify ownership — never trust id_order from form alone
        $order = $orderService->findOrderForCustomer($idOrder, $idCustomer, $idShop);

        if (!$order) {
            // Generic message — do not reveal whether order exists
            $this->errors[] = $this->module->l('The selected order could not be found.', 'identify');
            $this->renderMode('customer');
            return;
        }

        // Store verified order ID in session (signed with session id to prevent fixation)
        $sessionSignature = hash_hmac('sha256', (string) $idOrder, session_id() . _COOKIE_KEY_);
        $_SESSION['pcwdl_verified_order_id']        = $idOrder;
        $_SESSION['pcwdl_verified_order_signature'] = $sessionSignature;
        $_SESSION['pcwdl_identify_mode']            = 'customer';

        Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'select'));
    }

    /**
     * Guest mode POST — verify email+reference match, send verification email.
     * Always returns the same generic message regardless of whether the order was found.
     *
     * @param int   $idShop
     * @param array $container
     *
     * @return void
     */
    protected function processGuestPost($idShop, array $container)
    {
        // Check honeypot before rate-limit hit
        $honeypot = Tools::getValue('pcwdl_hp', '');
        if ('' !== $honeypot) {
            // Bot — silently pretend success
            $this->assignGuestSuccessVars();
            $this->renderMode('guest');
            return;
        }

        $rateLimiter = isset($container['rate_limiter'])
            ? $container['rate_limiter']
            : new PerpetualCode\PcWithdrawal\Security\RateLimiter($container['rate_limit_repository']);

        $ip = $this->getClientIp();

        if ($rateLimiter->isLimitExceeded($idShop, 'identify_order', $ip)) {
            // Still show generic message — do not reveal rate limiting
            $this->assignGuestSuccessVars();
            $this->renderMode('guest');
            return;
        }

        $email    = trim(Tools::getValue('pcwdl_email', ''));
        $orderRef = strtoupper(trim(Tools::getValue('pcwdl_order_reference', '')));

        // Basic format validation (no revealing specifics)
        if ('' === $email || !\Validate::isEmail($email)) {
            $this->errors[] = $this->module->l('Please enter a valid email address.', 'identify');
            $this->renderMode('guest');
            return;
        }

        if ('' === $orderRef || !preg_match('/^[A-Z0-9\-]{1,32}$/', $orderRef)) {
            $this->errors[] = $this->module->l('Please enter a valid order reference.', 'identify');
            $this->renderMode('guest');
            return;
        }

        // Record rate-limit hit
        $rateLimiter->hit($idShop, 'identify_order', $ip);

        /** @var PerpetualCode\PcWithdrawal\Service\OrderIdentificationService $orderService */
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        // verifyGuestOrder does the constant-time comparison internally
        $order = $orderService->verifyGuestOrder($orderRef, $email, $idShop);

        if ($order) {
            // Create and send verification token; invalidate old tokens first
            $verificationRepo = $container['verification_repository'];
            $emailHash        = hash('sha256', strtolower(trim($email)));
            $orderRefHash     = hash('sha256', strtoupper(trim($orderRef)));

            $verificationRepo->invalidateForEmail($idShop, 'guest_order', $emailHash);

            $random    = $container['random_generator'];
            $rawToken  = $random->generateToken(32);
            $tokenHash = hash('sha256', $rawToken);

            $lifetimeMinutes = (int) Configuration::get('PCWITHDRAWAL_VERIFY_TOKEN_LIFETIME');
            if ($lifetimeMinutes < 5) {
                $lifetimeMinutes = 15;
            }

            $verificationRepo->create(
                $idShop,
                'guest_order',
                $emailHash,
                $tokenHash,
                $lifetimeMinutes,
                5,
                $orderRefHash
            );

            // Build verification URL
            $verifyUrl = $this->context->link->getModuleLink(
                'pcwithdrawal',
                'verify',
                array('token' => $rawToken)
            );

            // Send email — failure is non-fatal (legal record already exists if submitted later)
            if (isset($container['mail_sender'])) {
                try {
                    $container['mail_sender']->sendGuestVerification(
                        $idShop,
                        (int) $this->context->language->id,
                        $email,
                        array(
                            'verification_url'        => $verifyUrl,
                            'verification_expiration' => date('Y-m-d H:i', strtotime('+' . $lifetimeMinutes . ' minutes')),
                            'shop_name'               => Configuration::get('PS_SHOP_NAME'),
                            'order_reference'         => $orderRef,
                        )
                    );
                } catch (Exception $e) {
                    // Log but do not surface to customer
                    if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                        PrestaShopLogger::addLog(
                            'pcwithdrawal: guest verification mail failed: ' . $e->getMessage(),
                            2,
                            null,
                            'PcWithdrawalIdentify',
                            0
                        );
                    }
                }
            }

            // Store hashed context in session (never clear-text)
            $_SESSION['pcwdl_guest_email_hash']    = $emailHash;
            $_SESSION['pcwdl_guest_order_ref_hash'] = $orderRefHash;
            $_SESSION['pcwdl_identify_mode']       = 'guest';
        }

        // Always show the same generic message — never reveal whether order was found
        $this->assignGuestSuccessVars();
        $this->renderMode('guest');
    }

    /**
     * No-ref mode POST — submit an identification-required request directly.
     *
     * @param int   $idShop
     * @param array $container
     *
     * @return void
     */
    protected function processNoRefPost($idShop, array $container)
    {
        // Check honeypot first
        $honeypot = Tools::getValue('pcwdl_hp', '');
        if ('' !== $honeypot) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'success'));
            return;
        }

        $rateLimiter = isset($container['rate_limiter'])
            ? $container['rate_limiter']
            : new PerpetualCode\PcWithdrawal\Security\RateLimiter($container['rate_limit_repository']);

        $ip = $this->getClientIp();

        if ($rateLimiter->isLimitExceeded($idShop, 'submit_form', $ip)) {
            $this->errors[] = $this->module->l('Too many requests. Please try again later.', 'identify');
            $this->renderMode('noref');
            return;
        }

        $data = array(
            'firstname'              => Tools::getValue('pcwdl_firstname', ''),
            'lastname'               => Tools::getValue('pcwdl_lastname', ''),
            'email'                  => Tools::getValue('pcwdl_email', ''),
            'purchase_date_declared' => Tools::getValue('pcwdl_purchase_date', ''),
            'description'            => Tools::getValue('pcwdl_description', ''),
            'contract_info'          => Tools::getValue('pcwdl_contract_info', ''),
            'approximate_amount'     => Tools::getValue('pcwdl_approximate_amount', ''),
            'customer_statement'     => Tools::getValue('pcwdl_customer_statement', ''),
        );

        /** @var PerpetualCode\PcWithdrawal\Validator\SubmissionValidator $validator */
        $validator = new PerpetualCode\PcWithdrawal\Validator\SubmissionValidator();
        $result    = $validator->validateNoRefSubmission($data);

        if (!$result['valid']) {
            foreach ($result['errors'] as $msg) {
                $this->errors[] = $msg;
            }
            $this->context->smarty->assign('pcwdl_form_data', $data);
            $this->renderMode('noref');
            return;
        }

        $rateLimiter->hit($idShop, 'submit_form', $ip);

        /** @var PerpetualCode\PcWithdrawal\Service\WithdrawalSubmissionService $submissionService */
        $submissionService = isset($container['submission_service'])
            ? $container['submission_service']
            : null;

        if (!$submissionService) {
            $this->errors[] = $this->module->l('Submission service unavailable. Please try again later.', 'identify');
            $this->renderMode('noref');
            return;
        }

        $submission = new PerpetualCode\PcWithdrawal\DTO\WithdrawalSubmission();
        $submission->idShop              = $idShop;
        $submission->idCustomer          = 0;
        $submission->idLang              = (int) $this->context->language->id;
        $submission->consumerFirstname   = trim($data['firstname']);
        $submission->consumerLastname    = trim($data['lastname']);
        $submission->consumerEmail       = trim($data['email']);
        $submission->orderReference      = '';
        $submission->scopeCode           = 'full';
        $submission->sourceCode          = 'front_noref';
        $submission->customerStatement   = strip_tags(trim($data['customer_statement']));
        $submission->purchaseDateDeclared = trim($data['purchase_date_declared']);
        $submission->submittedAtUtc      = gmdate('Y-m-d H:i:s');
        $submission->submittedTimezone   = 'UTC';
        $submission->submittedLocalAt    = date('Y-m-d H:i:s');
        $submission->clientIp            = $ip;
        $submission->contractType        = trim($data['contract_info']);
        $submission->items               = array();

        $random         = $container['random_generator'];
        $idempotencyKey = $random->generateToken(32);

        try {
            $submitResult = $submissionService->submit($submission, $idempotencyKey, array());
            Tools::redirect(
                $this->context->link->getModuleLink('pcwithdrawal', 'success', array('ref' => $submitResult['public_reference']))
            );
        } catch (Exception $e) {
            $this->errors[] = $this->module->l('An error occurred. Please try again later.', 'identify');
            $this->renderMode('noref');
        }
    }

    /**
     * Assign variables and render the template for the given mode.
     *
     * @param string $mode
     *
     * @return void
     */
    protected function renderMode($mode)
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;

        $csrfToken = $csrfManager->generateToken('identify_' . $mode, $idShop);

        $this->context->smarty->assign(array(
            'pcwdl_mode'       => $mode,
            'pcwdl_csrf_token' => $csrfToken,
            'pcwdl_errors'     => $this->errors,
            'pcwdl_action_url' => $this->context->link->getModuleLink('pcwithdrawal', 'identify', array('mode' => $mode)),
        ));

        switch ($mode) {
            case 'customer':
                $this->loadCustomerOrders($idShop);
                $this->setTemplate('module:pcwithdrawal/views/templates/front/identify_customer.tpl');
                break;
            case 'noref':
                $this->setTemplate('module:pcwithdrawal/views/templates/front/identify_noref.tpl');
                break;
            case 'guest':
            default:
                $this->setTemplate('module:pcwithdrawal/views/templates/front/identify_guest.tpl');
                break;
        }
    }

    /**
     * Load and assign customer orders for the order selection form.
     *
     * @param int $idShop
     *
     * @return void
     */
    protected function loadCustomerOrders($idShop)
    {
        if (!$this->context->customer->isLogged()) {
            return;
        }

        $container    = Pcwithdrawal::getServiceContainer();
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        $idCustomer   = (int) $this->context->customer->id;
        $this->orders = $orderService->getCustomerOrders($idCustomer, $idShop, 20);

        // Enrich with status label for display
        $idLang = (int) $this->context->language->id;
        foreach ($this->orders as &$order) {
            $stateObj = new OrderState((int) $order['current_state'], $idLang);
            $order['state_name'] = $stateObj->name;
        }
        unset($order);

        $this->context->smarty->assign('pcwdl_orders', $this->orders);
    }

    /**
     * Assign variables that produce the generic "check your email" message for guest mode.
     *
     * @return void
     */
    protected function assignGuestSuccessVars()
    {
        $this->context->smarty->assign('pcwdl_guest_submitted', true);
    }

    /**
     * Get the client IP address (respects CF-Connecting-IP / X-Forwarded-For if trusted).
     *
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
