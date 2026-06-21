<?php
/**
 * Front controller — final withdrawal submission.
 * POST only: validates CSRF, checks idempotency, rebuilds DTO from session,
 * calls WithdrawalSubmissionService, then redirects to success.
 *
 * Security rules:
 *  - GET requests are redirected to start.
 *  - CSRF must pass before any processing.
 *  - Submission DTO is rebuilt entirely from session; nothing is taken from POST body
 *    except the CSRF token and idempotency token.
 *  - No automatic order cancellation, refund, or status change.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalSubmitModuleFrontController extends ModuleFrontController
{
    /** @var array|null */
    public $submissionResult;

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

        // GET → redirect to start
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
            return;
        }

        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
            return;
        }

        $this->processSubmission();
    }

    /**
     * Main submission flow.
     *
     * @return void
     */
    protected function processSubmission()
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;

        // Step 1: CSRF check
        $csrfToken = Tools::getValue('pcwdl_csrf_token', '');
        if (!$csrfManager->validateToken('submit', $csrfToken, $idShop)) {
            $this->redirectToReviewWithError($this->module->l('Security token invalid. Please go back and try again.', 'submit'));
            return;
        }

        // Step 2: Idempotency — read from POST (it was put in a hidden field on review page)
        $idempotencyToken = Tools::getValue('pcwdl_idempotency_token', '');
        if ('' === $idempotencyToken && isset($_SESSION['pcwdl_idempotency_token'])) {
            $idempotencyToken = $_SESSION['pcwdl_idempotency_token'];
        }

        if ('' === $idempotencyToken) {
            $this->redirectToReviewWithError($this->module->l('Session data missing. Please go back and try again.', 'submit'));
            return;
        }

        // Step 3: Rebuild order from session — never from POST
        $order = $this->getVerifiedOrderData($idShop);
        if (!$order) {
            $this->redirectToStartWithError();
            return;
        }

        // Step 4: Load selection from session
        $scopeCode         = isset($_SESSION['pcwdl_scope_code'])         ? $_SESSION['pcwdl_scope_code']         : '';
        $selectedItems     = isset($_SESSION['pcwdl_selected_items'])     ? $_SESSION['pcwdl_selected_items']     : array();
        $customerStatement = isset($_SESSION['pcwdl_customer_statement']) ? $_SESSION['pcwdl_customer_statement'] : '';
        $identifyMode      = isset($_SESSION['pcwdl_identify_mode'])      ? $_SESSION['pcwdl_identify_mode']      : '';

        if ('' === $scopeCode || empty($selectedItems)) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'select'));
            return;
        }

        // Step 5: Resolve consumer identity
        if ('customer' === $identifyMode && $this->context->customer->isLogged()) {
            $firstname = (string) $this->context->customer->firstname;
            $lastname  = (string) $this->context->customer->lastname;
            $email     = (string) $this->context->customer->email;
            $idCustomer = (int) $this->context->customer->id;
        } else {
            $idCustomer = (int) $order['id_customer'];
            $firstname  = $this->getCustomerField($idCustomer, 'firstname');
            $lastname   = $this->getCustomerField($idCustomer, 'lastname');
            $email      = $this->getCustomerField($idCustomer, 'email');
        }

        // Step 6: Build DTO items
        $dtoItems = array();
        foreach ($selectedItems as $sel) {
            $item = new PerpetualCode\PcWithdrawal\DTO\WithdrawalItem();
            $item->idOrderDetail       = (int) $sel['id_order_detail'];
            $item->idProduct           = (int) $sel['id_product'];
            $item->idProductAttribute  = (int) $sel['id_product_attribute'];
            $item->productName         = (string) $sel['product_name'];
            $item->productReference    = (string) $sel['product_reference'];
            $item->attributeName       = (string) $sel['attribute_name'];
            $item->quantityOrdered     = (int) $sel['quantity_ordered'];
            $item->quantityWithdrawn   = (int) $sel['quantity_withdrawn'];
            $item->unitPriceTaxIncl    = (float) $sel['unit_price_tax_incl'];
            $item->totalPriceTaxIncl   = (float) $sel['unit_price_tax_incl'] * (int) $sel['quantity_withdrawn'];
            $item->currencyIso         = isset($sel['currency_iso']) ? (string) $sel['currency_iso'] : '';
            $item->exceptionCode       = '';
            $dtoItems[] = $item;
        }

        // Step 7: Build submission DTO
        $submission = new PerpetualCode\PcWithdrawal\DTO\WithdrawalSubmission();
        $submission->idShop               = $idShop;
        $submission->idCustomer           = $idCustomer;
        $submission->idLang               = (int) $this->context->language->id;
        $submission->consumerFirstname    = $firstname;
        $submission->consumerLastname     = $lastname;
        $submission->consumerEmail        = $email;
        $submission->orderReference       = (string) $order['reference'];
        $submission->scopeCode            = $scopeCode;
        $submission->sourceCode           = 'customer' === $identifyMode ? 'front_customer' : 'front_guest';
        $submission->customerStatement    = $customerStatement;
        $submission->purchaseDateDeclared = isset($order['date_add']) ? substr((string) $order['date_add'], 0, 10) : '';
        $submission->submittedAtUtc       = gmdate('Y-m-d H:i:s');
        $submission->submittedTimezone    = 'UTC';
        $submission->submittedLocalAt     = date('Y-m-d H:i:s');
        $submission->clientIp             = $this->getClientIp();
        $submission->contractType         = '';
        $submission->items                = $dtoItems;

        // Step 8: Get submission service
        $submissionService = isset($container['submission_service'])
            ? $container['submission_service']
            : null;

        if (!$submissionService) {
            $this->redirectToReviewWithError($this->module->l('Submission service unavailable. Please try again later.', 'submit'));
            return;
        }

        // Step 9: Submit
        try {
            $result = $submissionService->submit($submission, $idempotencyToken, $order);
            $this->submissionResult = $result;
        } catch (Exception $e) {
            $this->redirectToReviewWithError($this->module->l('An error occurred during submission. Please try again.', 'submit'));
            return;
        }

        // Step 10: Clear withdrawal session state
        $this->clearWithdrawalSession();

        // Step 11: Redirect to success
        Tools::redirect(
            $this->context->link->getModuleLink('pcwithdrawal', 'success', array('ref' => $result['public_reference']))
        );
    }

    /**
     * Retrieve and re-verify the order from session.
     *
     * @param int $idShop
     *
     * @return array|null
     */
    protected function getVerifiedOrderData($idShop)
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
             WHERE `id_customer` = ' . $idCustomer . ' LIMIT 1'
        );

        return $val ? (string) $val : '';
    }

    /**
     * Clear all withdrawal-related session keys.
     *
     * @return void
     */
    protected function clearWithdrawalSession()
    {
        $keys = array(
            'pcwdl_verified_order_id',
            'pcwdl_verified_order_signature',
            'pcwdl_identify_mode',
            'pcwdl_scope_code',
            'pcwdl_selected_items',
            'pcwdl_customer_statement',
            'pcwdl_idempotency_token',
            'pcwdl_guest_email_hash',
            'pcwdl_guest_order_ref_hash',
            'pcwdl_guest_order_ref_enc',
        );

        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * @param string $error
     *
     * @return void
     */
    protected function redirectToReviewWithError($error)
    {
        $_SESSION['pcwdl_submit_error'] = $error;
        Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'review'));
    }

    /**
     * @return void
     */
    protected function redirectToStartWithError()
    {
        $this->clearWithdrawalSession();
        Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
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
}
