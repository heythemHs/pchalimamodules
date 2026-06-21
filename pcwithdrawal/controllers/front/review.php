<?php
/**
 * Front controller — review and confirmation page.
 * Displays an immutable summary of the withdrawal declaration for the customer
 * to read before final submission. Does NOT handle the final POST — that is
 * the responsibility of the submit controller.
 *
 * Security rules:
 *  - All data displayed is loaded from session, never from URL params.
 *  - CSRF token and idempotency token are embedded as hidden fields.
 *  - GET only on this controller; POST goes to submit controller.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalReviewModuleFrontController extends ModuleFrontController
{
    /** @var array */
    public $reviewData = array();

    /** @var array */
    public $items = array();

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
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
            return;
        }

        // Load verified order from session (same signature check as select controller)
        $order = $this->getVerifiedOrderData();
        if (!$order) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
            return;
        }

        // Load selection from session
        $scopeCode         = isset($_SESSION['pcwdl_scope_code'])         ? $_SESSION['pcwdl_scope_code']         : '';
        $selectedItems     = isset($_SESSION['pcwdl_selected_items'])     ? $_SESSION['pcwdl_selected_items']     : array();
        $customerStatement = isset($_SESSION['pcwdl_customer_statement']) ? $_SESSION['pcwdl_customer_statement'] : '';

        if ('' === $scopeCode || empty($selectedItems)) {
            // Selection step not completed
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'select'));
            return;
        }

        $this->items      = $selectedItems;
        $this->reviewData = array(
            'order'              => $order,
            'scope_code'         => $scopeCode,
            'customer_statement' => $customerStatement,
        );

        // Identify consumer info
        $identifyMode = isset($_SESSION['pcwdl_identify_mode']) ? $_SESSION['pcwdl_identify_mode'] : '';
        if ('customer' === $identifyMode && $this->context->customer->isLogged()) {
            $consumer = array(
                'firstname' => $this->context->customer->firstname,
                'lastname'  => $this->context->customer->lastname,
                'email'     => $this->context->customer->email,
            );
        } else {
            // Guest — derive from session-stored encrypted values or from order row
            $consumer = array(
                'firstname' => isset($order['id_customer']) ? $this->getCustomerField((int) $order['id_customer'], 'firstname') : '',
                'lastname'  => isset($order['id_customer']) ? $this->getCustomerField((int) $order['id_customer'], 'lastname')  : '',
                'email'     => '',
            );
        }

        $this->reviewData['consumer'] = $consumer;

        // Generate CSRF for the submit form
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;
        $csrfToken   = $csrfManager->generateToken('submit', $idShop);

        // Generate or reuse idempotency token
        if (empty($_SESSION['pcwdl_idempotency_token'])) {
            $random  = $container['random_generator'];
            $_SESSION['pcwdl_idempotency_token'] = $random->generateToken(32);
        }
        $idempotencyToken = $_SESSION['pcwdl_idempotency_token'];

        $submitUrl  = $this->context->link->getModuleLink('pcwithdrawal', 'submit');
        $selectUrl  = $this->context->link->getModuleLink('pcwithdrawal', 'select');

        // Final action label from config (e.g. "Submit withdrawal" or "Confirm withdrawal")
        $actionLabel = Configuration::get('PCWITHDRAWAL_CONFIRM_LABEL');
        if (empty($actionLabel)) {
            $actionLabel = $this->module->l('Confirm and Submit Withdrawal', 'review');
        }

        $this->context->smarty->assign(array(
            'pcwdl_review_data'       => $this->reviewData,
            'pcwdl_items'             => $this->items,
            'pcwdl_csrf_token'        => $csrfToken,
            'pcwdl_idempotency_token' => $idempotencyToken,
            'pcwdl_submit_url'        => $submitUrl,
            'pcwdl_select_url'        => $selectUrl,
            'pcwdl_action_label'      => $actionLabel,
            'pcwdl_scope_code'        => $scopeCode,
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/review.tpl');
    }

    /**
     * Retrieve and re-verify the order from session (shared logic with select controller).
     *
     * @return array|null
     */
    protected function getVerifiedOrderData()
    {
        $idOrder   = isset($_SESSION['pcwdl_verified_order_id']) ? (int) $_SESSION['pcwdl_verified_order_id'] : 0;
        $signature = isset($_SESSION['pcwdl_verified_order_signature']) ? $_SESSION['pcwdl_verified_order_signature'] : '';
        $mode      = isset($_SESSION['pcwdl_identify_mode']) ? $_SESSION['pcwdl_identify_mode'] : '';

        if ($idOrder < 1 || '' === $signature) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', (string) $idOrder, session_id() . _COOKIE_KEY_);
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $idShop = (int) $this->context->shop->id;

        $container    = Pcwithdrawal::getServiceContainer();
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        if ('customer' === $mode) {
            if (!$this->context->customer->isLogged()) {
                return null;
            }
            return $orderService->findOrderForCustomer($idOrder, (int) $this->context->customer->id, $idShop);
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `id_order` = ' . $idOrder . ' AND `id_shop` = ' . $idShop . '
             LIMIT 1'
        );
        return $row ? $row : null;
    }

    /**
     * Retrieve a single field from the customer record.
     *
     * @param int    $idCustomer
     * @param string $field
     *
     * @return string
     */
    protected function getCustomerField($idCustomer, $field)
    {
        if ($idCustomer < 1) {
            return '';
        }

        $allowed = array('firstname', 'lastname', 'email');
        if (!in_array($field, $allowed, true)) {
            return '';
        }

        $val = Db::getInstance()->getValue(
            'SELECT `' . bqSQL($field) . '` FROM `' . _DB_PREFIX_ . 'customer`
             WHERE `id_customer` = ' . $idCustomer . '
             LIMIT 1'
        );

        return $val ? (string) $val : '';
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
