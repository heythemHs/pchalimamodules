<?php
/**
 * Admin controller — module settings management.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PerpetualCode\PcWithdrawal\Validator\ConfigurationValidator;

class AdminPcWithdrawalSettingsController extends ModuleAdminController
{
    /** @var array */
    public $errors = array();

    /** @var array */
    public $confirmations = array();

    /** @var bool */
    public $bootstrap = true;

    public function __construct()
    {
        parent::__construct();
        $this->meta_title = $this->module->l('Withdrawal Settings', 'AdminPcWithdrawalSettingsController');
    }

    /**
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->context->controller->addCSS(
            $this->module->getPathUri() . 'views/css/admin.css',
            'all'
        );
        $this->context->controller->addJS(
            $this->module->getPathUri() . 'views/js/admin.js'
        );
    }

    /**
     * @return int
     */
    protected function getShopId()
    {
        return (int) Context::getContext()->shop->id;
    }

    /**
     * Retrieve config value for the current shop.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function cfg($key, $default = '')
    {
        $val = Configuration::get($key, null, null, $this->getShopId());
        return (false === $val || null === $val) ? $default : $val;
    }

    /**
     * Render the settings page.
     *
     * @return string
     */
    public function renderView()
    {
        $idShop    = $this->getShopId();
        $validator = new ConfigurationValidator();

        // Collect all order states for the multi-select
        $orderStates = OrderState::getOrderStates((int) $this->context->language->id);

        // Selected delivered states (comma-separated IDs)
        $deliveredStatesRaw = (string) $this->cfg('PCWITHDRAWAL_DELIVERED_STATES', '');
        $deliveredStatesIds = array_filter(array_map('intval', explode(',', $deliveredStatesRaw)));

        // Timezones
        $timezones = DateTimeZone::listIdentifiers();

        $this->context->smarty->assign(array(
            // General
            'pcw_enabled'              => (int) $this->cfg('PCWITHDRAWAL_ENABLED', 1),
            'pcw_footer_link'          => (int) $this->cfg('PCWITHDRAWAL_FOOTER_LINK', 1),
            'pcw_account_link'         => (int) $this->cfg('PCWITHDRAWAL_ACCOUNT_LINK', 1),
            'pcw_order_button'         => (int) $this->cfg('PCWITHDRAWAL_ORDER_BUTTON', 1),
            'pcw_withdrawal_period'    => (int) $this->cfg('PCWITHDRAWAL_WITHDRAWAL_PERIOD', 14),
            'pcw_timezone'             => $this->cfg('PCWITHDRAWAL_TIMEZONE', 'UTC'),
            'pcw_date_format'          => $this->cfg('PCWITHDRAWAL_DATE_FORMAT', 'Y-m-d H:i'),
            'pcw_require_guest_verify' => (int) $this->cfg('PCWITHDRAWAL_REQUIRE_GUEST_VERIFY', 1),
            'pcw_allow_no_ref'         => (int) $this->cfg('PCWITHDRAWAL_ALLOW_NO_REF', 1),
            'pcw_partial'              => (int) $this->cfg('PCWITHDRAWAL_PARTIAL', 1),
            'pcw_customer_emails'      => (int) $this->cfg('PCWITHDRAWAL_CUSTOMER_EMAILS', 1),

            // Notifications
            'pcw_admin_notify'         => (int) $this->cfg('PCWITHDRAWAL_ADMIN_NOTIFY', 1),
            'pcw_admin_recipients'     => $this->cfg('PCWITHDRAWAL_ADMIN_RECIPIENTS', ''),
            'pcw_sender_email'         => $this->cfg('PCWITHDRAWAL_SENDER_EMAIL', ''),
            'pcw_sender_name'          => $this->cfg('PCWITHDRAWAL_SENDER_NAME', ''),
            'pcw_reply_to'             => $this->cfg('PCWITHDRAWAL_REPLY_TO', ''),

            // Delivery
            'pcw_delivered_states'     => $deliveredStatesIds,
            'pcw_use_state_history'    => (int) $this->cfg('PCWITHDRAWAL_USE_STATE_HISTORY', 1),
            'pcw_show_eligibility'     => (int) $this->cfg('PCWITHDRAWAL_SHOW_ELIGIBILITY', 0),

            // Security
            'pcw_token_lifetime'       => (int) $this->cfg('PCWITHDRAWAL_TOKEN_LIFETIME', 15),
            'pcw_max_verify_attempts'  => (int) $this->cfg('PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS', 5),
            'pcw_max_id_per_hour'      => (int) $this->cfg('PCWITHDRAWAL_MAX_ID_PER_HOUR', 10),
            'pcw_max_submit_per_hour'  => (int) $this->cfg('PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR', 5),
            'pcw_rate_retention'       => (int) $this->cfg('PCWITHDRAWAL_RATE_RETENTION', 48),
            'pcw_honeypot'             => (int) $this->cfg('PCWITHDRAWAL_HONEYPOT', 1),
            'pcw_status_link_lifetime' => (int) $this->cfg('PCWITHDRAWAL_STATUS_LINK_LIFETIME', 7),

            // Retention
            'pcw_keep_data'            => (int) $this->cfg('PCWITHDRAWAL_KEEP_DATA', 1),
            'pcw_token_retention'      => (int) $this->cfg('PCWITHDRAWAL_TOKEN_RETENTION', 1440),
            'pcw_mail_log_retention'   => (int) $this->cfg('PCWITHDRAWAL_MAIL_LOG_RETENTION', 365),
            'pcw_request_retention'    => (int) $this->cfg('PCWITHDRAWAL_REQUEST_RETENTION', 0),

            // References
            'pcw_order_states'         => $orderStates,
            'pcw_timezones'            => $timezones,
            'pcw_action_url'           => $this->context->link->getAdminLink('AdminPcWithdrawalSettings'),
            'pcw_is_superadmin'        => (bool) $this->context->employee->isSuperAdmin(),
        ));

        return $this->module->display($this->module->getLocalPath(), 'views/templates/admin/settings.tpl');
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        $this->content .= $this->renderView();
        parent::initContent();
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        $action = Tools::getValue('pcw_action');

        switch ($action) {
            case 'saveSettings':
                $this->processSaveSettings();
                break;
            case 'sendTestEmail':
                $this->processSendTestEmail();
                break;
            case 'purgePermanently':
                $this->processPurgePermanently();
                break;
        }

        parent::postProcess();
    }

    /**
     * Save all configuration values.
     */
    protected function processSaveSettings()
    {
        $idShop    = $this->getShopId();
        $validator = new ConfigurationValidator();
        $errors    = array();

        // --- General ---
        $enabled             = (int) (bool) Tools::getValue('PCWITHDRAWAL_ENABLED');
        $footerLink          = (int) (bool) Tools::getValue('PCWITHDRAWAL_FOOTER_LINK');
        $accountLink         = (int) (bool) Tools::getValue('PCWITHDRAWAL_ACCOUNT_LINK');
        $orderButton         = (int) (bool) Tools::getValue('PCWITHDRAWAL_ORDER_BUTTON');
        $withdrawalPeriod    = (int) Tools::getValue('PCWITHDRAWAL_WITHDRAWAL_PERIOD', 14);
        $timezone            = (string) Tools::getValue('PCWITHDRAWAL_TIMEZONE', 'UTC');
        $dateFormat          = (string) Tools::getValue('PCWITHDRAWAL_DATE_FORMAT', 'Y-m-d H:i');
        $requireGuestVerify  = (int) (bool) Tools::getValue('PCWITHDRAWAL_REQUIRE_GUEST_VERIFY');
        $allowNoRef          = (int) (bool) Tools::getValue('PCWITHDRAWAL_ALLOW_NO_REF');
        $partial             = (int) (bool) Tools::getValue('PCWITHDRAWAL_PARTIAL');
        $customerEmails      = (int) (bool) Tools::getValue('PCWITHDRAWAL_CUSTOMER_EMAILS');

        // --- Notifications ---
        $adminNotify         = (int) (bool) Tools::getValue('PCWITHDRAWAL_ADMIN_NOTIFY');
        $adminRecipients     = (string) Tools::getValue('PCWITHDRAWAL_ADMIN_RECIPIENTS', '');
        $senderEmail         = (string) Tools::getValue('PCWITHDRAWAL_SENDER_EMAIL', '');
        $senderName          = (string) Tools::getValue('PCWITHDRAWAL_SENDER_NAME', '');
        $replyTo             = (string) Tools::getValue('PCWITHDRAWAL_REPLY_TO', '');

        // --- Delivery ---
        $deliveredStates     = Tools::getValue('PCWITHDRAWAL_DELIVERED_STATES', array());
        $useStateHistory     = (int) (bool) Tools::getValue('PCWITHDRAWAL_USE_STATE_HISTORY');
        $showEligibility     = (int) (bool) Tools::getValue('PCWITHDRAWAL_SHOW_ELIGIBILITY');

        // --- Security ---
        $tokenLifetime       = (int) Tools::getValue('PCWITHDRAWAL_TOKEN_LIFETIME', 15);
        $maxVerifyAttempts   = (int) Tools::getValue('PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS', 5);
        $maxIdPerHour        = (int) Tools::getValue('PCWITHDRAWAL_MAX_ID_PER_HOUR', 10);
        $maxSubmitPerHour    = (int) Tools::getValue('PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR', 5);
        $rateRetention       = (int) Tools::getValue('PCWITHDRAWAL_RATE_RETENTION', 48);
        $honeypot            = (int) (bool) Tools::getValue('PCWITHDRAWAL_HONEYPOT');
        $statusLinkLifetime  = (int) Tools::getValue('PCWITHDRAWAL_STATUS_LINK_LIFETIME', 7);

        // --- Retention ---
        $keepData            = (int) (bool) Tools::getValue('PCWITHDRAWAL_KEEP_DATA');
        $tokenRetention      = (int) Tools::getValue('PCWITHDRAWAL_TOKEN_RETENTION', 1440);
        $mailLogRetention    = (int) Tools::getValue('PCWITHDRAWAL_MAIL_LOG_RETENTION', 365);
        $requestRetention    = (int) Tools::getValue('PCWITHDRAWAL_REQUEST_RETENTION', 0);

        // Validate
        if (!$validator->validateWithdrawalPeriod($withdrawalPeriod)) {
            $errors[] = $this->module->l('Withdrawal period must be between 1 and 365 days.', 'AdminPcWithdrawalSettingsController');
        }
        if (!$validator->validateTimezone($timezone)) {
            $errors[] = $this->module->l('Invalid timezone selected.', 'AdminPcWithdrawalSettingsController');
            $timezone = 'UTC';
        }
        if ($senderEmail !== '' && !Validate::isEmail($senderEmail)) {
            $errors[] = $this->module->l('Sender email is not a valid email address.', 'AdminPcWithdrawalSettingsController');
        }
        if ($replyTo !== '' && !Validate::isEmail($replyTo)) {
            $errors[] = $this->module->l('Reply-to email is not a valid email address.', 'AdminPcWithdrawalSettingsController');
        }
        if (!$validator->validateTokenLifetime($tokenLifetime)) {
            $errors[] = $this->module->l('Token lifetime must be between 5 and 1440 minutes.', 'AdminPcWithdrawalSettingsController');
            $tokenLifetime = 15;
        }
        if ($maxVerifyAttempts < 1 || $maxVerifyAttempts > 20) {
            $errors[] = $this->module->l('Max verify attempts must be between 1 and 20.', 'AdminPcWithdrawalSettingsController');
            $maxVerifyAttempts = 5;
        }

        if (!empty($errors)) {
            $this->errors = array_merge($this->errors, $errors);
            return;
        }

        // Sanitize delivered states
        if (is_array($deliveredStates)) {
            $cleanStates = array_filter(array_map('intval', $deliveredStates));
            $deliveredStatesStr = implode(',', $cleanStates);
        } else {
            $deliveredStatesStr = '';
        }

        // Save all
        $pairs = array(
            'PCWITHDRAWAL_ENABLED'              => $enabled,
            'PCWITHDRAWAL_FOOTER_LINK'          => $footerLink,
            'PCWITHDRAWAL_ACCOUNT_LINK'         => $accountLink,
            'PCWITHDRAWAL_ORDER_BUTTON'         => $orderButton,
            'PCWITHDRAWAL_WITHDRAWAL_PERIOD'    => $withdrawalPeriod,
            'PCWITHDRAWAL_TIMEZONE'             => $timezone,
            'PCWITHDRAWAL_DATE_FORMAT'          => $dateFormat,
            'PCWITHDRAWAL_REQUIRE_GUEST_VERIFY' => $requireGuestVerify,
            'PCWITHDRAWAL_ALLOW_NO_REF'         => $allowNoRef,
            'PCWITHDRAWAL_PARTIAL'              => $partial,
            'PCWITHDRAWAL_CUSTOMER_EMAILS'      => $customerEmails,
            'PCWITHDRAWAL_ADMIN_NOTIFY'         => $adminNotify,
            'PCWITHDRAWAL_ADMIN_RECIPIENTS'     => $adminRecipients,
            'PCWITHDRAWAL_SENDER_EMAIL'         => $senderEmail,
            'PCWITHDRAWAL_SENDER_NAME'          => $senderName,
            'PCWITHDRAWAL_REPLY_TO'             => $replyTo,
            'PCWITHDRAWAL_DELIVERED_STATES'     => $deliveredStatesStr,
            'PCWITHDRAWAL_USE_STATE_HISTORY'    => $useStateHistory,
            'PCWITHDRAWAL_SHOW_ELIGIBILITY'     => $showEligibility,
            'PCWITHDRAWAL_TOKEN_LIFETIME'       => $tokenLifetime,
            'PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS'  => $maxVerifyAttempts,
            'PCWITHDRAWAL_MAX_ID_PER_HOUR'      => $maxIdPerHour,
            'PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR'  => $maxSubmitPerHour,
            'PCWITHDRAWAL_RATE_RETENTION'       => $rateRetention,
            'PCWITHDRAWAL_HONEYPOT'             => $honeypot,
            'PCWITHDRAWAL_STATUS_LINK_LIFETIME' => $statusLinkLifetime,
            'PCWITHDRAWAL_KEEP_DATA'            => $keepData,
            'PCWITHDRAWAL_TOKEN_RETENTION'      => $tokenRetention,
            'PCWITHDRAWAL_MAIL_LOG_RETENTION'   => $mailLogRetention,
            'PCWITHDRAWAL_REQUEST_RETENTION'    => $requestRetention,
        );

        foreach ($pairs as $key => $value) {
            Configuration::updateValue($key, $value, false, null, $idShop);
        }

        $this->confirmations[] = $this->module->l('Settings saved successfully.', 'AdminPcWithdrawalSettingsController');
    }

    /**
     * Send a test email to the sender address.
     */
    protected function processSendTestEmail()
    {
        $idShop = $this->getShopId();

        $toEmail = (string) Configuration::get('PCWITHDRAWAL_SENDER_EMAIL', null, null, $idShop);
        if (!$toEmail || !Validate::isEmail($toEmail)) {
            $toEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        }

        if (!Validate::isEmail($toEmail)) {
            $this->errors[] = $this->module->l('No valid sender email configured.', 'AdminPcWithdrawalSettingsController');
            return;
        }

        $idLang   = (int) $this->context->language->id;
        $shopName = (string) Configuration::get('PS_SHOP_NAME', null, null, $idShop);

        $result = Mail::Send(
            $idLang,
            'pcwithdrawal_generic',
            '[pcwithdrawal] Test email',
            array(
                '{message_html}' => '<p>This is a test email from the EU Withdrawal Function module.</p><p>Shop: ' . htmlspecialchars($shopName, ENT_QUOTES) . '</p>',
                '{message_txt}'  => 'Test email from EU Withdrawal Function module. Shop: ' . $shopName,
            ),
            $toEmail,
            '',
            $toEmail,
            $shopName,
            null,
            null,
            _PS_MODULE_DIR_ . 'pcwithdrawal/mails/',
            false,
            $idShop
        );

        if ($result) {
            $this->confirmations[] = sprintf(
                $this->module->l('Test email sent to %s.', 'AdminPcWithdrawalSettingsController'),
                $toEmail
            );
        } else {
            $this->errors[] = $this->module->l('Failed to send test email. Check your mail configuration.', 'AdminPcWithdrawalSettingsController');
        }
    }

    /**
     * Permanent data purge — requires superadmin + typed confirmation phrase.
     */
    protected function processPurgePermanently()
    {
        if (!$this->context->employee->isSuperAdmin()) {
            $this->errors[] = $this->module->l('Only super-administrators can perform a permanent purge.', 'AdminPcWithdrawalSettingsController');
            return;
        }

        $confirmPhrase = (string) Tools::getValue('purge_confirm_phrase', '');
        if ('CONFIRM PURGE' !== $confirmPhrase) {
            $this->errors[] = $this->module->l('Please type "CONFIRM PURGE" exactly to confirm the permanent purge.', 'AdminPcWithdrawalSettingsController');
            return;
        }

        $purgeFile = dirname(__FILE__) . '/../../sql/purge.php';
        if (!file_exists($purgeFile)) {
            $this->errors[] = $this->module->l('Purge SQL file not found.', 'AdminPcWithdrawalSettingsController');
            return;
        }

        $statements = include $purgeFile;
        if (!is_array($statements)) {
            $this->errors[] = $this->module->l('Invalid purge SQL file.', 'AdminPcWithdrawalSettingsController');
            return;
        }

        $db = Db::getInstance();
        foreach ($statements as $sql) {
            $sql = str_replace('{prefix}', _DB_PREFIX_, $sql);
            $db->execute($sql);
        }

        $this->confirmations[] = $this->module->l('All module data has been permanently deleted.', 'AdminPcWithdrawalSettingsController');
    }
}
