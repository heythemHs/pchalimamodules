<?php
/**
 * SQL purge — Hard DELETE/DROP for permanent data removal.
 * This is a destructive action executed only via explicit admin purge action.
 * Returns an array of SQL strings; prefix placeholder is {prefix}.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

return array(
    // Delete child data first (referential integrity)
    'DELETE FROM `{prefix}pcwithdrawal_template_history`',
    'DELETE FROM `{prefix}pcwithdrawal_rate_limit`',
    'DELETE FROM `{prefix}pcwithdrawal_verification`',
    'DELETE FROM `{prefix}pcwithdrawal_mail_log`',
    'DELETE FROM `{prefix}pcwithdrawal_template`',
    'DELETE FROM `{prefix}pcwithdrawal_event`',
    'DELETE FROM `{prefix}pcwithdrawal_item`',
    'DELETE FROM `{prefix}pcwithdrawal_request`',

    // Then drop the tables
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_template_history`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_rate_limit`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_verification`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_mail_log`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_template`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_event`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_item`',
    'DROP TABLE IF EXISTS `{prefix}pcwithdrawal_request`',
);
