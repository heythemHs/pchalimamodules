<?php
/**
 * EU Withdrawal Function module for PrestaShop.
 *
 * Implements the 14-day right of withdrawal for distance and off-premises
 * contracts as required by EU Directive 2011/83/EU (Consumer Rights Directive).
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Register autoloader for module classes (PSR-4 via Composer or fallback)
$autoloadFile = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
} else {
    // Fallback manual autoloader for environments without Composer
    spl_autoload_register(function ($class) {
        $prefix    = 'PerpetualCode\\PcWithdrawal\\';
        $baseDir   = dirname(__FILE__) . '/classes/';
        $prefixLen = strlen($prefix);

        if (strncmp($prefix, $class, $prefixLen) !== 0) {
            return;
        }

        $relative = substr($class, $prefixLen);
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

use PerpetualCode\PcWithdrawal\Installer\Installer;
use PerpetualCode\PcWithdrawal\Installer\Uninstaller;
use PerpetualCode\PcWithdrawal\Installer\TabInstaller;
use PerpetualCode\PcWithdrawal\Compatibility\HookCompatibility;
use PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator;
use PerpetualCode\PcWithdrawal\Compatibility\ShopContextAdapter;

class Pcwithdrawal extends Module
{
    // -------------------------------------------------------------------------
    // Explicit property declarations (PHP 8.2 dynamic property fix)
    // -------------------------------------------------------------------------

    /** @var string Module technical name */
    public $name;

    /** @var string Back-office tab */
    public $tab;

    /** @var string Module version */
    public $version;

    /** @var string Author */
    public $author;

    /** @var int */
    public $need_instance;

    /** @var array PS version compatibility range */
    public $ps_versions_compliancy;

    /** @var bool Use Bootstrap in back-office */
    public $bootstrap;

    /** @var string Module key from Addons marketplace */
    public $module_key;

    /** @var int */
    public $currencies;

    /** @var string */
    public $currencies_mode;

    /** @var string Module display name */
    public $displayName;

    /** @var string Module description */
    public $description;

    /** @var string Confirmation message after save */
    public $confirmUninstall;

    // -------------------------------------------------------------------------
    // Internal state
    // -------------------------------------------------------------------------

    /** @var array|null Lazy-loaded service container (not Symfony, simple array). */
    private static $serviceContainer = null;

    /**
     * Hooks registered by this module.
     *
     * @var string[]
     */
    private static $moduleHooks = array(
        'displayFooter',
        'displayCustomerAccount',
        'displayOrderDetail',
        'displayHeader',
        'actionFrontControllerSetMedia',
        'displayAdminOrderMainBottom',
        'displayAdminOrderSide',
        // Custom module hooks
        'actionPcWithdrawalSubmitted',
        'actionPcWithdrawalStatusChanged',
        'actionPcWithdrawalMailSent',
        'actionPcWithdrawalOrderLinked',
        'filterPcWithdrawalEligibility',
        'filterPcWithdrawalDeliveryDate',
        'filterPcWithdrawalEmailVariables',
    );

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->name       = 'pcwithdrawal';
        $this->tab        = 'administration';
        $this->version    = '1.0.0';
        $this->author     = 'Perpetual Code';
        $this->module_key = '';

        $this->need_instance = 0;
        $this->bootstrap     = true;
        $this->currencies    = false;
        $this->currencies_mode = 'checkbox';

        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => '9.1.99',
        );

        parent::__construct();

        $this->displayName      = $this->l('EU Withdrawal Function');
        $this->description      = $this->l('Compliant 14-day right of withdrawal (cooling-off) for EU distance and off-premises contracts. Allows customers to submit withdrawal requests online.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the EU Withdrawal Function module?');
    }

    // -------------------------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------------------------

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Create DB tables and seed config
        $installer = new Installer($this);
        if (!$installer->install()) {
            $this->_errors[] = $this->l('Failed to create database tables.');

            return false;
        }

        // Install admin tabs
        $tabInstaller = new TabInstaller();
        if (!$tabInstaller->install()) {
            // Non-fatal: log but continue
            $this->_errors[] = $this->l('Warning: could not install all admin tabs.');
        }

        // Register hooks (creates custom hooks if missing)
        if (!HookCompatibility::registerAll($this, self::$moduleHooks)) {
            $this->_errors[] = $this->l('Warning: could not register all hooks.');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        // Uninstall tabs
        $tabInstaller = new TabInstaller();
        $tabInstaller->uninstall();

        // Unregister hooks
        HookCompatibility::unregisterAll($this, self::$moduleHooks);

        // Drop tables if keep_data = 0; remove config
        $uninstaller = new Uninstaller($this);
        $uninstaller->uninstall();

        return parent::uninstall();
    }

    // -------------------------------------------------------------------------
    // Back-office configuration redirect
    // -------------------------------------------------------------------------

    /**
     * Redirect merchant to the dedicated settings controller.
     *
     * @return string
     */
    public function getContent()
    {
        $idTab = (int) \Tab::getIdFromClassName('AdminPcWithdrawalSettings');
        if ($idTab > 0) {
            $url = $this->context->link->getAdminLink('AdminPcWithdrawalSettings');
            Tools::redirectAdmin($url);
        }

        return $this->l('EU Withdrawal settings controller not found. Please reinstall the module.');
    }

    // -------------------------------------------------------------------------
    // Front hooks
    // -------------------------------------------------------------------------

    /**
     * Display the withdrawal link in the shop footer.
     * Only generates the URL and label — no DB queries.
     *
     * @param array $params
     *
     * @return string|void
     */
    public function hookDisplayFooter($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }
        if (!(int) Configuration::get('PCWITHDRAWAL_FOOTER_LINK')) {
            return;
        }

        $idLang  = (int) $this->context->language->id;
        $isoCode = strtolower((string) $this->context->language->iso_code);

        // Resolve label for current language
        $label = $this->getFooterLabel($isoCode);
        if ('' === $label) {
            // No button for this locale (e.g. en-us)
            return;
        }

        $withdrawalUrl = $this->context->link->getModuleLink('pcwithdrawal', 'form');

        $this->context->smarty->assign(array(
            'pcwithdrawal_url'   => $withdrawalUrl,
            'pcwithdrawal_label' => $label,
        ));

        return $this->display(__FILE__, 'views/templates/hook/footer_link.tpl');
    }

    /**
     * Display withdrawal link on the customer account page.
     *
     * @param array $params
     *
     * @return string|void
     */
    public function hookDisplayCustomerAccount($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }
        if (!(int) Configuration::get('PCWITHDRAWAL_ACCOUNT_LINK')) {
            return;
        }

        $withdrawalUrl = $this->context->link->getModuleLink('pcwithdrawal', 'form');

        $this->context->smarty->assign(array(
            'pcwithdrawal_url' => $withdrawalUrl,
        ));

        return $this->display(__FILE__, 'views/templates/hook/customer_account.tpl');
    }

    /**
     * Display withdrawal button on the order detail page.
     * Verifies customer ownership before showing the button.
     *
     * @param array $params Expected: ['order' => Order]
     *
     * @return string|void
     */
    public function hookDisplayOrderDetail($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }
        if (!(int) Configuration::get('PCWITHDRAWAL_ORDER_BUTTON')) {
            return;
        }

        if (!isset($params['order']) || !($params['order'] instanceof Order)) {
            return;
        }

        /** @var Order $order */
        $order = $params['order'];

        // Verify customer ownership
        $customerId = (int) $this->context->customer->id;
        if (!$customerId || (int) $order->id_customer !== $customerId) {
            return;
        }

        $withdrawalUrl = $this->context->link->getModuleLink(
            'pcwithdrawal',
            'form',
            array('order_reference' => $order->reference)
        );

        $this->context->smarty->assign(array(
            'pcwithdrawal_order_url'       => $withdrawalUrl,
            'pcwithdrawal_order_reference' => $order->reference,
        ));

        return $this->display(__FILE__, 'views/templates/hook/order_detail.tpl');
    }

    /**
     * Inject front-end CSS/JS via displayHeader hook.
     *
     * @param array $params
     */
    public function hookDisplayHeader($params)
    {
        // Actual media registration is done in actionFrontControllerSetMedia
        // This hook is here for PS < 1.7 compatibility if needed.
    }

    /**
     * Register front-end media assets on relevant pages.
     *
     * @param array $params
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }

        $controllerName = Tools::getValue('controller');

        // Only load assets on module pages or order pages
        if (!in_array($controllerName, array('order-detail', 'history'), true)
            && $this->context->controller->php_self !== 'module'
        ) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/css/front.css',
            array('media' => 'all', 'priority' => 200)
        );

        $this->context->controller->registerJavascript(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/js/front.js',
            array('position' => 'bottom', 'priority' => 200)
        );
    }

    // -------------------------------------------------------------------------
    // Admin order hooks
    // -------------------------------------------------------------------------

    /**
     * Display withdrawal information in the main order panel (bottom).
     *
     * @param array $params Expected: ['id_order' => int]
     *
     * @return string|void
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }

        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        if (!$idOrder) {
            return;
        }

        $shopAdapter = $this->getService('shop_context');
        $idShop      = $shopAdapter->getShopId();

        $requestRepo = $this->getService('withdrawal_request_repository');
        $request     = $requestRepo->findByOrderId($idOrder, $idShop);

        $this->context->smarty->assign(array(
            'pcwithdrawal_request' => $request ?: null,
            'pcwithdrawal_id_order' => $idOrder,
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin_order_bottom.tpl');
    }

    /**
     * Display withdrawal link/badge in the order sidebar.
     *
     * @param array $params Expected: ['id_order' => int]
     *
     * @return string|void
     */
    public function hookDisplayAdminOrderSide($params)
    {
        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            return;
        }

        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        if (!$idOrder) {
            return;
        }

        $shopAdapter = $this->getService('shop_context');
        $idShop      = $shopAdapter->getShopId();

        $requestRepo = $this->getService('withdrawal_request_repository');
        $request     = $requestRepo->findByOrderId($idOrder, $idShop);

        if (!$request) {
            return;
        }

        $this->context->smarty->assign(array(
            'pcwithdrawal_request_side' => $request,
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin_order_side.tpl');
    }

    // -------------------------------------------------------------------------
    // Custom action/filter hooks (implementations call Hook::exec externally)
    // These methods are stubs — third-party modules can listen to these.
    // -------------------------------------------------------------------------

    /**
     * Fired after a withdrawal request is successfully submitted.
     *
     * @param array $params ['request' => array, 'items' => array]
     */
    public function hookActionPcWithdrawalSubmitted($params)
    {
        // Extension point. No default implementation.
    }

    /**
     * Fired when a request status changes.
     *
     * @param array $params ['request' => array, 'previous_status' => string, 'new_status' => string]
     */
    public function hookActionPcWithdrawalStatusChanged($params)
    {
        // Extension point. No default implementation.
    }

    /**
     * Fired after a mail is sent.
     *
     * @param array $params ['log_id' => int, 'template_code' => string, 'recipient' => string, 'success' => bool]
     */
    public function hookActionPcWithdrawalMailSent($params)
    {
        // Extension point. No default implementation.
    }

    /**
     * Fired when a request is linked to an order.
     *
     * @param array $params ['request' => array, 'id_order' => int]
     */
    public function hookActionPcWithdrawalOrderLinked($params)
    {
        // Extension point. No default implementation.
    }

    /**
     * Filter hook to override eligibility determination.
     *
     * @param array $params ['submission' => WithdrawalSubmission, 'result' => DeadlineResult]
     *
     * @return array Modified $params['result']
     */
    public function hookFilterPcWithdrawalEligibility($params)
    {
        return isset($params['result']) ? $params['result'] : null;
    }

    /**
     * Filter hook to override delivery date resolution.
     *
     * @param array $params ['id_order' => int, 'id_shop' => int, 'delivery_date' => string|null]
     *
     * @return string|null Override delivery date or return null to use default.
     */
    public function hookFilterPcWithdrawalDeliveryDate($params)
    {
        return isset($params['delivery_date']) ? $params['delivery_date'] : null;
    }

    /**
     * Filter hook to inject extra variables into e-mail templates.
     *
     * @param array $params ['variables' => array, 'template_code' => string, 'request' => array]
     *
     * @return array Modified variables array.
     */
    public function hookFilterPcWithdrawalEmailVariables($params)
    {
        return isset($params['variables']) ? $params['variables'] : array();
    }

    // -------------------------------------------------------------------------
    // Service locator (no Symfony, simple lazy-loaded array)
    // -------------------------------------------------------------------------

    /**
     * Get a service by key from the module's service locator.
     * Services are instantiated once and cached.
     *
     * @param string $key
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException If service key is unknown.
     */
    public function getService($key)
    {
        $container = self::getServiceContainer($this);

        if (!isset($container[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown service "%s" in pcwithdrawal container.', $key));
        }

        return $container[$key];
    }

    /**
     * Returns (and caches) the service container.
     * Static so it survives multiple hookXxx() calls within a single request.
     *
     * @param Pcwithdrawal $module Reference to module instance for services that need it.
     *
     * @return array
     */
    public static function getServiceContainer(Pcwithdrawal $module = null)
    {
        if (null !== self::$serviceContainer) {
            return self::$serviceContainer;
        }

        $random     = new \PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator();
        $shopCtx    = new \PerpetualCode\PcWithdrawal\Compatibility\ShopContextAdapter();

        self::$serviceContainer = array(
            'random_generator'              => $random,
            'shop_context'                  => $shopCtx,
            'withdrawal_request_repository' => new \PerpetualCode\PcWithdrawal\Repository\WithdrawalRequestRepository(),
            'withdrawal_item_repository'    => new \PerpetualCode\PcWithdrawal\Repository\WithdrawalItemRepository(),
            'withdrawal_event_repository'   => new \PerpetualCode\PcWithdrawal\Repository\WithdrawalEventRepository(),
            'email_template_repository'     => new \PerpetualCode\PcWithdrawal\Repository\EmailTemplateRepository(),
            'mail_log_repository'           => new \PerpetualCode\PcWithdrawal\Repository\MailLogRepository(),
            'verification_repository'       => new \PerpetualCode\PcWithdrawal\Repository\VerificationRepository(),
            'rate_limit_repository'         => new \PerpetualCode\PcWithdrawal\Repository\RateLimitRepository(),
            'csrf_token_manager'            => new \PerpetualCode\PcWithdrawal\Security\CsrfTokenManager($random),
        );

        return self::$serviceContainer;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the footer button label for the given ISO language code.
     * Returns empty string to suppress the button for that locale.
     *
     * @param string $isoCode Lowercase 2-letter ISO code.
     *
     * @return string
     */
    private function getFooterLabel($isoCode)
    {
        $labels = array(
            'nl' => 'Bestelling herroepen',
            'en' => 'Withdraw order',
            'es' => 'Desistir del pedido',
            'it' => 'Recedi dall\'ordine',
            'fr' => 'Se rétracter de la commande',
            'pl' => 'Odstąp od zamówienia',
            'cs' => 'Odstoupit od objednávky',
            'de' => 'Bestellung widerrufen',
            'ru' => 'Отказаться от заказа',
            'ca' => 'Desistir de la comanda',
            // en-us deliberately omitted (no button per spec)
        );

        return isset($labels[$isoCode]) ? $labels[$isoCode] : '';
    }
}
