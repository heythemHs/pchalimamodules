<?php
/**
 * Installer — admin tab management for pcwithdrawal.
 *
 * @namespace PerpetualCode\PcWithdrawal\Installer
 */

namespace PerpetualCode\PcWithdrawal\Installer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TabInstaller
{
    /**
     * Tab definitions. Each item:
     *   class_name    => controller class (or '' for parent with no controller)
     *   parent        => parent class_name
     *   name          => display name (for all languages)
     *   icon          => material icon name (PS 1.7.1+)
     *
     * @var array[]
     */
    private static $tabs = array(
        array(
            'class_name' => 'AdminPcWithdrawal',
            'parent'     => 'SELL',
            'name'       => 'EU Withdrawal',
            'icon'       => 'undo',
            'route_name' => '',
        ),
        array(
            'class_name' => 'AdminPcWithdrawalRequests',
            'parent'     => 'AdminPcWithdrawal',
            'name'       => 'Withdrawal Requests',
            'icon'       => 'list_alt',
            'route_name' => '',
        ),
        array(
            'class_name' => 'AdminPcWithdrawalSettings',
            'parent'     => 'AdminPcWithdrawal',
            'name'       => 'Withdrawal Settings',
            'icon'       => 'settings',
            'route_name' => '',
        ),
        array(
            'class_name' => 'AdminPcWithdrawalTemplates',
            'parent'     => 'AdminPcWithdrawal',
            'name'       => 'Email Templates',
            'icon'       => 'email',
            'route_name' => '',
        ),
        array(
            'class_name' => 'AdminPcWithdrawalRules',
            'parent'     => 'AdminPcWithdrawal',
            'name'       => 'Exception Rules',
            'icon'       => 'block',
            'route_name' => '',
        ),
    );

    /**
     * Install all tabs.
     *
     * @return bool
     */
    public function install()
    {
        $languages = \Language::getLanguages(false);
        $success   = true;

        foreach (self::$tabs as $tabData) {
            if ($this->tabExists($tabData['class_name'])) {
                continue;
            }

            $tab = new \Tab();
            $tab->class_name = $tabData['class_name'];
            $tab->active     = 1;
            $tab->module     = 'pcwithdrawal';

            // Parent tab ID
            if ('' === $tabData['parent'] || null === $tabData['parent']) {
                $tab->id_parent = -1;
            } else {
                $idParent = (int) \Tab::getIdFromClassName($tabData['parent']);
                $tab->id_parent = $idParent > 0 ? $idParent : 0;
            }

            // Name for all languages
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $tabData['name'];
            }

            // Icon (available PS 1.7.1+)
            if (!empty($tabData['icon']) && property_exists($tab, 'icon')) {
                $tab->icon = $tabData['icon'];
            }

            if (!$tab->add()) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove all module tabs.
     *
     * @return bool
     */
    public function uninstall()
    {
        $success = true;

        // Remove children first, then parent
        $reversed = array_reverse(self::$tabs);
        foreach ($reversed as $tabData) {
            $idTab = (int) \Tab::getIdFromClassName($tabData['class_name']);
            if ($idTab > 0) {
                $tab = new \Tab($idTab);
                if (!$tab->delete()) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Check whether a tab already exists by class name.
     *
     * @param string $className
     *
     * @return bool
     */
    private function tabExists($className)
    {
        return \Tab::getIdFromClassName($className) > 0;
    }
}
