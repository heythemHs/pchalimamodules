<?php
/**
 * SQL install — CREATE TABLE statements for pcwithdrawal module.
 * Returns an array of SQL strings; prefix placeholder is {prefix}.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

return array(
    'pcwithdrawal_request' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_request` (
          `id_pcwithdrawal_request` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `id_customer` int(10) unsigned DEFAULT NULL,
          `id_order` int(10) unsigned DEFAULT NULL,
          `id_lang` int(10) unsigned NOT NULL,
          `public_reference` varchar(32) NOT NULL,
          `order_reference` varchar(64) DEFAULT NULL,
          `status_code` varchar(64) NOT NULL DEFAULT \'submitted\',
          `scope_code` varchar(32) NOT NULL DEFAULT \'full\',
          `eligibility_code` varchar(32) NOT NULL DEFAULT \'manual_review\',
          `eligibility_reason` text DEFAULT NULL,
          `contract_type` varchar(32) NOT NULL DEFAULT \'distance\',
          `source_code` varchar(32) NOT NULL DEFAULT \'front_online\',
          `consumer_firstname` varchar(255) NOT NULL DEFAULT \'\',
          `consumer_lastname` varchar(255) NOT NULL DEFAULT \'\',
          `consumer_email` varchar(255) NOT NULL DEFAULT \'\',
          `customer_statement` text DEFAULT NULL,
          `purchase_date_declared` date DEFAULT NULL,
          `delivery_date_used` datetime DEFAULT NULL,
          `deadline_start_at` datetime DEFAULT NULL,
          `deadline_end_at` datetime DEFAULT NULL,
          `submitted_at_utc` datetime NOT NULL,
          `submitted_timezone` varchar(64) NOT NULL DEFAULT \'UTC\',
          `submitted_local_at` datetime NOT NULL,
          `acknowledgement_sent_at` datetime DEFAULT NULL,
          `acknowledgement_status` varchar(32) NOT NULL DEFAULT \'pending\',
          `matched_at` datetime DEFAULT NULL,
          `closed_at` datetime DEFAULT NULL,
          `date_add` datetime NOT NULL,
          `date_upd` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_request`),
          UNIQUE KEY `public_reference` (`public_reference`),
          KEY `idx_shop_status` (`id_shop`, `status_code`),
          KEY `idx_shop_submitted` (`id_shop`, `submitted_at_utc`),
          KEY `idx_customer` (`id_customer`),
          KEY `idx_order` (`id_order`),
          KEY `idx_order_reference` (`id_shop`, `order_reference`(32)),
          KEY `idx_consumer_email` (`id_shop`, `consumer_email`(64)),
          KEY `idx_eligibility` (`id_shop`, `eligibility_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_item' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_item` (
          `id_pcwithdrawal_item` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_pcwithdrawal_request` int(10) unsigned NOT NULL,
          `id_order_detail` int(10) unsigned DEFAULT NULL,
          `id_product` int(10) unsigned DEFAULT NULL,
          `id_product_attribute` int(10) unsigned DEFAULT NULL,
          `product_name` varchar(512) NOT NULL DEFAULT \'\',
          `product_reference` varchar(128) NOT NULL DEFAULT \'\',
          `attribute_name` varchar(512) NOT NULL DEFAULT \'\',
          `quantity_ordered` int(10) unsigned NOT NULL DEFAULT 0,
          `quantity_withdrawn` int(10) unsigned NOT NULL DEFAULT 0,
          `unit_price_tax_incl` decimal(20,6) NOT NULL DEFAULT 0.000000,
          `total_price_tax_incl` decimal(20,6) NOT NULL DEFAULT 0.000000,
          `currency_iso` varchar(3) NOT NULL DEFAULT \'\',
          `exception_code` varchar(32) DEFAULT NULL,
          `date_add` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_item`),
          KEY `idx_request` (`id_pcwithdrawal_request`),
          KEY `idx_product` (`id_product`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_event' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_event` (
          `id_pcwithdrawal_event` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_pcwithdrawal_request` int(10) unsigned NOT NULL,
          `id_shop` int(10) unsigned NOT NULL,
          `event_code` varchar(64) NOT NULL,
          `id_employee` int(10) unsigned DEFAULT NULL,
          `id_customer` int(10) unsigned DEFAULT NULL,
          `actor_type` varchar(32) NOT NULL DEFAULT \'system\',
          `previous_status` varchar(64) DEFAULT NULL,
          `new_status` varchar(64) DEFAULT NULL,
          `event_payload` text DEFAULT NULL,
          `created_at_utc` datetime NOT NULL,
          `previous_event_hash` varchar(64) DEFAULT NULL,
          `event_hash` varchar(64) NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_event`),
          KEY `idx_request` (`id_pcwithdrawal_request`),
          KEY `idx_shop` (`id_shop`),
          KEY `idx_created` (`created_at_utc`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_template' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_template` (
          `id_pcwithdrawal_template` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `id_lang` int(10) unsigned NOT NULL,
          `template_code` varchar(64) NOT NULL,
          `subject` varchar(512) NOT NULL DEFAULT \'\',
          `html_content` mediumtext DEFAULT NULL,
          `text_content` mediumtext DEFAULT NULL,
          `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
          `version` int(10) unsigned NOT NULL DEFAULT 1,
          `date_add` datetime NOT NULL,
          `date_upd` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_template`),
          UNIQUE KEY `uk_shop_lang_code` (`id_shop`, `id_lang`, `template_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_mail_log' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_mail_log` (
          `id_pcwithdrawal_mail_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_pcwithdrawal_request` int(10) unsigned DEFAULT NULL,
          `id_shop` int(10) unsigned NOT NULL,
          `id_lang` int(10) unsigned NOT NULL,
          `template_code` varchar(64) NOT NULL,
          `recipient` varchar(255) NOT NULL,
          `subject_snapshot` varchar(512) NOT NULL DEFAULT \'\',
          `html_snapshot` mediumtext DEFAULT NULL,
          `text_snapshot` mediumtext DEFAULT NULL,
          `send_status` varchar(32) NOT NULL DEFAULT \'pending\',
          `error_message` text DEFAULT NULL,
          `ps_mail_return` tinyint(1) DEFAULT NULL,
          `provider_message_id` varchar(255) DEFAULT NULL,
          `created_at_utc` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_mail_log`),
          KEY `idx_request` (`id_pcwithdrawal_request`),
          KEY `idx_shop` (`id_shop`),
          KEY `idx_created` (`created_at_utc`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_verification' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_verification` (
          `id_pcwithdrawal_verification` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `purpose` varchar(32) NOT NULL DEFAULT \'guest_order\',
          `email_hash` varchar(128) NOT NULL,
          `order_reference_hash` varchar(128) DEFAULT NULL,
          `token_hash` varchar(128) NOT NULL,
          `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
          `max_attempts` int(10) unsigned NOT NULL DEFAULT 5,
          `expires_at` datetime NOT NULL,
          `used_at` datetime DEFAULT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_verification`),
          KEY `idx_shop_email` (`id_shop`, `email_hash`),
          KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_rate_limit' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_rate_limit` (
          `id_pcwithdrawal_rate_limit` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `action_code` varchar(64) NOT NULL,
          `identity_hash` varchar(128) NOT NULL,
          `hit_count` int(10) unsigned NOT NULL DEFAULT 1,
          `window_start` datetime NOT NULL,
          `last_hit` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_rate_limit`),
          UNIQUE KEY `uk_shop_action_identity_window` (`id_shop`, `action_code`, `identity_hash`, `window_start`),
          KEY `idx_last_hit` (`last_hit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_template_history' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_template_history` (
          `id_pcwithdrawal_template_history` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_pcwithdrawal_template` int(10) unsigned NOT NULL,
          `id_shop` int(10) unsigned NOT NULL,
          `id_lang` int(10) unsigned NOT NULL,
          `template_code` varchar(64) NOT NULL,
          `previous_subject` varchar(512) NOT NULL DEFAULT \'\',
          `previous_html` mediumtext DEFAULT NULL,
          `previous_text` mediumtext DEFAULT NULL,
          `id_employee` int(10) unsigned DEFAULT NULL,
          `change_reason` text DEFAULT NULL,
          `date_add` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_template_history`),
          KEY `idx_template` (`id_pcwithdrawal_template`),
          KEY `idx_shop` (`id_shop`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',

    'pcwithdrawal_exception_rule' => '
        CREATE TABLE IF NOT EXISTS `{prefix}pcwithdrawal_exception_rule` (
          `id_pcwithdrawal_exception_rule` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `id_shop` int(10) unsigned NOT NULL,
          `rule_type` varchar(32) NOT NULL DEFAULT \'product\',
          `id_reference` int(10) unsigned DEFAULT NULL,
          `reference_name` varchar(512) NOT NULL DEFAULT \'\',
          `exception_code` varchar(32) NOT NULL,
          `notes` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT 1,
          `date_add` datetime NOT NULL,
          `date_upd` datetime NOT NULL,
          PRIMARY KEY (`id_pcwithdrawal_exception_rule`),
          KEY `idx_shop_type` (`id_shop`, `rule_type`),
          KEY `idx_reference` (`id_reference`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ',
);
