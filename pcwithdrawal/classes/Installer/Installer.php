<?php
/**
 * Module installer — orchestrates table creation and configuration seeding.
 *
 * @namespace PerpetualCode\PcWithdrawal\Installer
 */

namespace PerpetualCode\PcWithdrawal\Installer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Installer
{
    /** @var \Module */
    private $module;

    /** @var array Configuration defaults indexed by key. */
    private static $configDefaults = array(
        'PCWITHDRAWAL_ENABLED'              => 1,
        'PCWITHDRAWAL_FOOTER_LINK'          => 1,
        'PCWITHDRAWAL_ACCOUNT_LINK'         => 1,
        'PCWITHDRAWAL_ORDER_BUTTON'         => 1,
        'PCWITHDRAWAL_WITHDRAWAL_PERIOD'    => 14,
        'PCWITHDRAWAL_REQUIRE_GUEST_VERIFY' => 1,
        'PCWITHDRAWAL_ALLOW_NO_REF'         => 1,
        'PCWITHDRAWAL_PARTIAL'              => 1,
        'PCWITHDRAWAL_CUSTOMER_EMAILS'      => 1,
        'PCWITHDRAWAL_ADMIN_NOTIFY'         => 1,
        'PCWITHDRAWAL_ADMIN_RECIPIENTS'     => '',
        'PCWITHDRAWAL_ADMIN_LANG'           => 0,
        'PCWITHDRAWAL_SENDER_EMAIL'         => '',
        'PCWITHDRAWAL_SENDER_NAME'          => '',
        'PCWITHDRAWAL_REPLY_TO'             => '',
        'PCWITHDRAWAL_TIMEZONE'             => 'UTC',
        'PCWITHDRAWAL_DATE_FORMAT'          => 'Y-m-d H:i',
        'PCWITHDRAWAL_DELIVERED_STATES'     => '',
        'PCWITHDRAWAL_USE_STATE_HISTORY'    => 1,
        'PCWITHDRAWAL_SHOW_ELIGIBILITY'     => 0,
        'PCWITHDRAWAL_TOKEN_LIFETIME'       => 15,
        'PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS'  => 5,
        'PCWITHDRAWAL_MAX_ID_PER_HOUR'      => 10,
        'PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR'  => 5,
        'PCWITHDRAWAL_RATE_RETENTION'       => 48,
        'PCWITHDRAWAL_HONEYPOT'             => 1,
        'PCWITHDRAWAL_STATUS_LINK_LIFETIME' => 7,
        'PCWITHDRAWAL_KEEP_DATA'            => 1,
        'PCWITHDRAWAL_TOKEN_RETENTION'      => 1440,
        'PCWITHDRAWAL_MAIL_LOG_RETENTION'   => 365,
        'PCWITHDRAWAL_REQUEST_RETENTION'    => 0,
        'PCWITHDRAWAL_DB_SCHEMA_VERSION'    => '1.0.0',
    );

    /**
     * @param \Module $module
     */
    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    /**
     * Run full installation: tables, config, (tabs handled by main class).
     *
     * @return bool
     */
    public function install()
    {
        return $this->createTables() && $this->installConfig();
    }

    /**
     * Create all DB tables.
     *
     * @return bool
     */
    public function createTables()
    {
        $sqlFile = dirname(__FILE__) . '/../../sql/install.php';
        if (!file_exists($sqlFile)) {
            return false;
        }

        $statements = include $sqlFile;
        if (!is_array($statements)) {
            return false;
        }

        $db = \Db::getInstance();

        foreach ($statements as $key => $sql) {
            $sql = str_replace('{prefix}', _DB_PREFIX_, $sql);
            if (!$db->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Seed default configuration values (does not overwrite existing keys).
     *
     * @return bool
     */
    public function installConfig()
    {
        $idShop      = (int) \Context::getContext()->shop->id;
        $idShopGroup = (int) \Context::getContext()->shop->id_shop_group;

        foreach (self::$configDefaults as $key => $default) {
            // Only set if not already defined
            $existing = \Configuration::get($key, null, $idShopGroup, $idShop);
            if (false === $existing || null === $existing) {
                \Configuration::updateValue($key, $default);
            }
        }

        return true;
    }

    /**
     * Get the list of default configuration keys and values.
     *
     * @return array
     */
    public static function getConfigDefaults()
    {
        return self::$configDefaults;
    }
}
