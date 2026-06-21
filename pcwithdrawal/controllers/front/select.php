<?php
/**
 * Front controller — product/scope selection.
 * Loads the verified order from session, lets the customer choose which items
 * (or the full order) to withdraw, then stores the selection in session.
 *
 * Security rules:
 *  - Order is always re-fetched and re-verified from DB, never trusted from POST.
 *  - Customer-mode orders are re-verified against logged-in customer.
 *  - Quantities from POST are clamped to ordered quantities server-side.
 *  - CSRF verified on POST.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalSelectModuleFrontController extends ModuleFrontController
{
    /** @var array|null */
    public $order;

    /** @var array */
    public $orderDetails = array();

    /** @var array */
    public $errors = array();

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

        // Load and verify order from session
        $order = $this->getVerifiedOrder();
        if (!$order) {
            Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'start'));
            return;
        }

        $this->order = $order;
        $idShop      = (int) $this->context->shop->id;

        $container    = Pcwithdrawal::getServiceContainer();
        $orderService = isset($container['order_identification_service'])
            ? $container['order_identification_service']
            : new PerpetualCode\PcWithdrawal\Service\OrderIdentificationService();

        $this->orderDetails = $orderService->getOrderDetails((int) $order['id_order'], $idShop);

        if (Tools::isSubmit('pcwdl_select_submit')) {
            $this->processPost($idShop, $container);
        } else {
            $this->renderSelectForm();
        }
    }

    /**
     * Process the scope/item selection POST.
     *
     * @param int   $idShop
     * @param array $container
     *
     * @return void
     */
    protected function processPost($idShop, array $container)
    {
        $csrfManager = $container['csrf_token_manager'];
        $csrfToken   = Tools::getValue('pcwdl_csrf_token', '');

        if (!$csrfManager->validateToken('select', $csrfToken, $idShop)) {
            $this->errors[] = $this->module->l('Security token invalid. Please refresh the page and try again.', 'select');
            $this->renderSelectForm();
            return;
        }

        $scopeCode = Tools::getValue('pcwdl_scope', 'full');
        if (!in_array($scopeCode, array('full', 'partial'), true)) {
            $scopeCode = 'full';
        }

        $partialAllowed = (int) Configuration::get('PCWITHDRAWAL_ALLOW_PARTIAL');
        if (!$partialAllowed && 'partial' === $scopeCode) {
            $scopeCode = 'full';
        }

        $selectedItems = array();

        if ('partial' === $scopeCode) {
            $postedItems = Tools::getValue('pcwdl_items', array());
            if (!is_array($postedItems)) {
                $postedItems = array();
            }

            // Map order detail rows by ID for clamping
            $detailsById = array();
            foreach ($this->orderDetails as $detail) {
                $detailsById[(int) $detail['id_order_detail']] = $detail;
            }

            foreach ($postedItems as $rawId => $rawQty) {
                $idOrderDetail = (int) $rawId;
                $qtyWithdrawn  = (int) $rawQty;

                if ($idOrderDetail < 1 || $qtyWithdrawn < 1) {
                    continue;
                }

                if (!isset($detailsById[$idOrderDetail])) {
                    // Posted item ID not in this order — reject silently
                    continue;
                }

                $detail = $detailsById[$idOrderDetail];
                $maxQty = (int) $detail['product_quantity'];

                // Clamp to ordered quantity
                $qtyWithdrawn = min($qtyWithdrawn, $maxQty);

                if ($qtyWithdrawn > 0) {
                    $selectedItems[] = array(
                        'id_order_detail'      => $idOrderDetail,
                        'id_product'           => (int) $detail['product_id'],
                        'id_product_attribute' => (int) $detail['product_attribute_id'],
                        'product_name'         => (string) $detail['product_name'],
                        'product_reference'    => (string) $detail['product_reference'],
                        'attribute_name'       => (string) $detail['product_attribute_combination'],
                        'quantity_ordered'     => $maxQty,
                        'quantity_withdrawn'   => $qtyWithdrawn,
                        'unit_price_tax_incl'  => (float) $detail['unit_price_tax_incl'],
                        'currency_iso'         => '',
                    );
                }
            }

            if (empty($selectedItems)) {
                $this->errors[] = $this->module->l('Please select at least one item for a partial withdrawal.', 'select');
                $this->renderSelectForm();
                return;
            }
        } else {
            // Full order — build items from all order details
            foreach ($this->orderDetails as $detail) {
                $selectedItems[] = array(
                    'id_order_detail'      => (int) $detail['id_order_detail'],
                    'id_product'           => (int) $detail['product_id'],
                    'id_product_attribute' => (int) $detail['product_attribute_id'],
                    'product_name'         => (string) $detail['product_name'],
                    'product_reference'    => (string) $detail['product_reference'],
                    'attribute_name'       => (string) $detail['product_attribute_combination'],
                    'quantity_ordered'     => (int) $detail['product_quantity'],
                    'quantity_withdrawn'   => (int) $detail['product_quantity'],
                    'unit_price_tax_incl'  => (float) $detail['unit_price_tax_incl'],
                    'currency_iso'         => '',
                );
            }
        }

        $customerStatement = strip_tags(trim(Tools::getValue('pcwdl_customer_statement', '')));
        if (strlen($customerStatement) > 5000) {
            $this->errors[] = $this->module->l('Your statement must not exceed 5000 characters.', 'select');
            $this->renderSelectForm();
            return;
        }

        // Store selection in session
        $_SESSION['pcwdl_scope_code']         = $scopeCode;
        $_SESSION['pcwdl_selected_items']     = $selectedItems;
        $_SESSION['pcwdl_customer_statement'] = $customerStatement;

        Tools::redirect($this->context->link->getModuleLink('pcwithdrawal', 'review'));
    }

    /**
     * Assign variables and render the selection template.
     *
     * @return void
     */
    protected function renderSelectForm()
    {
        $container   = Pcwithdrawal::getServiceContainer();
        $csrfManager = $container['csrf_token_manager'];
        $idShop      = (int) $this->context->shop->id;
        $csrfToken   = $csrfManager->generateToken('select', $idShop);

        $partialAllowed = (int) Configuration::get('PCWITHDRAWAL_ALLOW_PARTIAL');

        $this->context->smarty->assign(array(
            'pcwdl_csrf_token'     => $csrfToken,
            'pcwdl_action_url'     => $this->context->link->getModuleLink('pcwithdrawal', 'select'),
            'pcwdl_errors'         => $this->errors,
            'pcwdl_order'          => $this->order,
            'pcwdl_order_details'  => $this->orderDetails,
            'pcwdl_partial_allowed' => $partialAllowed,
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/select.tpl');
    }

    /**
     * Retrieve and re-verify the order from session.
     * For customer mode, re-verify ownership against logged-in customer.
     * For guest mode, the signature is verified.
     *
     * @return array|null
     */
    protected function getVerifiedOrder()
    {
        $idOrder   = isset($_SESSION['pcwdl_verified_order_id']) ? (int) $_SESSION['pcwdl_verified_order_id'] : 0;
        $signature = isset($_SESSION['pcwdl_verified_order_signature']) ? $_SESSION['pcwdl_verified_order_signature'] : '';
        $mode      = isset($_SESSION['pcwdl_identify_mode']) ? $_SESSION['pcwdl_identify_mode'] : '';

        if ($idOrder < 1 || '' === $signature) {
            return null;
        }

        // Verify session signature
        $expectedSignature = hash_hmac('sha256', (string) $idOrder, session_id() . _COOKIE_KEY_);
        if (!hash_equals($expectedSignature, $signature)) {
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
            $idCustomer = (int) $this->context->customer->id;
            return $orderService->findOrderForCustomer($idOrder, $idCustomer, $idShop);
        }

        // Guest mode — just verify order belongs to this shop (identity already verified by token)
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'orders`
                WHERE `id_order` = ' . $idOrder . '
                  AND `id_shop` = ' . $idShop . '
                LIMIT 1';

        $row = Db::getInstance()->getRow($sql);
        return $row ? $row : null;
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
