<?php
/**
 * SQL uninstall — DROP TABLE statements for pcwithdrawal module.
 * Only executed when PCWITHDRAWAL_KEEP_DATA = 0.
 * Returns an array of SQL strings; prefix placeholder is {prefix}.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

return array(
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_exception_rule`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_template_history`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_rate_limit`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_verification`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_mail_log`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_template`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_event`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_item`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_request`',
);
