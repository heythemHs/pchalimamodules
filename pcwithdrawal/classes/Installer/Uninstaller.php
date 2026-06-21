<?php
/**
 * Module uninstaller — drops tables (if keep_data=0) and removes configuration.
 *
 * @namespace PerpetualCode\PcWithdrawal\Installer
 */

namespace PerpetualCode\PcWithdrawal\Installer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Uninstaller
{
    /** @var \Module */
    private $module;

    /**
     * @param \Module $module
     */
    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    /**
     * Run uninstallation. By default data is preserved (PCWITHDRAWAL_KEEP_DATA=1).
     *
     * @return bool
     */
    public function uninstall()
    {
        $keepData = (bool) \Configuration::get('PCWITHDRAWAL_KEEP_DATA');

        if (!$keepData) {
            $this->dropTables();
        }

        $this->removeConfig();

        return true;
    }

    /**
     * Drop all module tables.
     *
     * @return bool
     */
    public function dropTables()
    {
        $sqlFile = dirname(__FILE__) . '/../../sql/uninstall.php';
        if (!file_exists($sqlFile)) {
            return false;
        }

        $statements = include $sqlFile;
        if (!is_array($statements)) {
            return false;
        }

        $db = \Db::getInstance();

        foreach ($statements as $sql) {
            $sql = str_replace('{prefix}', _DB_PREFIX_, $sql);
            $db->execute($sql);
        }

        return true;
    }

    /**
     * Permanently purge all tables and data (admin action).
     *
     * @return bool
     */
    public function purge()
    {
        $sqlFile = dirname(__FILE__) . '/../../sql/purge.php';
        if (!file_exists($sqlFile)) {
            return false;
        }

        $statements = include $sqlFile;
        if (!is_array($statements)) {
            return false;
        }

        $db = \Db::getInstance();

        foreach ($statements as $sql) {
            $sql = str_replace('{prefix}', _DB_PREFIX_, $sql);
            $db->execute($sql);
        }

        return true;
    }

    /**
     * Delete all module configuration keys.
     *
     * @return void
     */
    private function removeConfig()
    {
        $keys = array_keys(Installer::getConfigDefaults());

        foreach ($keys as $key) {
            \Configuration::deleteByName($key);
        }
    }
}
