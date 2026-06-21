<?php
/**
 * Compatibility — hook registration helper for PS 1.7 / PS 8 / PS 9.
 * Handles hooks that may not exist in older core versions.
 *
 * @namespace PerpetualCode\PcWithdrawal\Compatibility
 */

namespace PerpetualCode\PcWithdrawal\Compatibility;

if (!defined('_PS_VERSION_')) {
    exit;
}

class HookCompatibility
{
    /**
     * Hooks that must be created if missing (custom module hooks).
     *
     * @var string[]
     */
    private static $customHooks = array(
        'actionPcWithdrawalSubmitted',
        'actionPcWithdrawalStatusChanged',
        'actionPcWithdrawalMailSent',
        'actionPcWithdrawalOrderLinked',
        'filterPcWithdrawalEligibility',
        'filterPcWithdrawalDeliveryDate',
        'filterPcWithdrawalEmailVariables',
    );

    /**
     * Register module hooks, creating custom hooks first if necessary.
     *
     * @param \Module  $module
     * @param string[] $hookNames
     *
     * @return bool True if all hooks registered successfully.
     */
    public static function registerAll(\Module $module, array $hookNames)
    {
        // Ensure custom hooks exist in the DB
        foreach (self::$customHooks as $hookName) {
            if (!self::hookExists($hookName)) {
                self::createHook($hookName);
            }
        }

        $success = true;
        foreach ($hookNames as $hookName) {
            if (!\Hook::getIdByName($hookName)) {
                // Hook doesn't exist in core and wasn't created above — skip gracefully
                continue;
            }
            if (!$module->registerHook($hookName)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Unregister all provided hooks.
     *
     * @param \Module  $module
     * @param string[] $hookNames
     *
     * @return bool
     */
    public static function unregisterAll(\Module $module, array $hookNames)
    {
        $success = true;
        foreach ($hookNames as $hookName) {
            if (!\Hook::getIdByName($hookName)) {
                continue;
            }
            if (!$module->unregisterHook($hookName)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check whether a hook exists by name.
     *
     * @param string $hookName
     *
     * @return bool
     */
    public static function hookExists($hookName)
    {
        return (bool) \Hook::getIdByName($hookName);
    }

    /**
     * Create a custom hook in the ps_hook table.
     *
     * @param string $hookName
     * @param string $title
     * @param string $description
     *
     * @return bool
     */
    public static function createHook($hookName, $title = '', $description = '')
    {
        if ('' === $title) {
            $title = $hookName;
        }

        $db = \Db::getInstance();

        return (bool) $db->insert('hook', array(
            'name'        => pSQL($hookName),
            'title'       => pSQL($title),
            'description' => pSQL($description),
            'position'    => 1,
        ), false, true, \Db::INSERT_IGNORE);
    }

    /**
     * Remove custom module hooks created during install.
     *
     * @return void
     */
    public static function removeCustomHooks()
    {
        $db = \Db::getInstance();
        foreach (self::$customHooks as $hookName) {
            $idHook = (int) \Hook::getIdByName($hookName);
            if ($idHook > 0) {
                $db->delete('hook_module', '`id_hook` = ' . $idHook);
                $db->delete('hook_module_exceptions', '`id_hook` = ' . $idHook);
                $db->delete('hook', '`id_hook` = ' . $idHook);
            }
        }
    }
}
