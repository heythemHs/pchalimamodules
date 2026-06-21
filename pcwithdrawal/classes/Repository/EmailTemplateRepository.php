<?php
/**
 * Repository — e-mail template storage with versioning history.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailTemplateRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    /** @var string */
    private $historyTable;

    public function __construct()
    {
        $this->db           = \Db::getInstance();
        $this->table        = _DB_PREFIX_ . 'pcwithdrawal_template';
        $this->historyTable = _DB_PREFIX_ . 'pcwithdrawal_template_history';
    }

    /**
     * Find a template by shop, language, and template code.
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $templateCode
     *
     * @return array|false
     */
    public function findByCode($idShop, $idLang, $templateCode)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `id_lang` = ' . (int) $idLang . '
                  AND `template_code` = \'' . pSQL($templateCode) . '\'
                  AND `is_enabled` = 1
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Find a template including disabled ones (for admin management).
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $templateCode
     *
     * @return array|false
     */
    public function findByCodeAdmin($idShop, $idLang, $templateCode)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `id_lang` = ' . (int) $idLang . '
                  AND `template_code` = \'' . pSQL($templateCode) . '\'
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Get all templates for a shop.
     *
     * @param int $idShop
     *
     * @return array[]
     */
    public function findAllByShop($idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                ORDER BY `template_code` ASC, `id_lang` ASC';

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Upsert (insert or update) a template, writing current version to history first.
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $templateCode
     * @param array  $data         Keys: subject, html_content, text_content, is_enabled.
     * @param int    $idEmployee   Who made the change.
     * @param string $changeReason
     *
     * @return bool
     */
    public function upsert($idShop, $idLang, $templateCode, array $data, $idEmployee = 0, $changeReason = '')
    {
        $existing = $this->findByCodeAdmin($idShop, $idLang, $templateCode);
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            // Snapshot current version to history
            $this->appendHistory($existing, $idEmployee, $changeReason);

            return (bool) $this->db->update(
                'pcwithdrawal_template',
                array(
                    'subject'      => pSQL($data['subject'], true),
                    'html_content' => pSQL($data['html_content'], true),
                    'text_content' => pSQL($data['text_content'], true),
                    'is_enabled'   => isset($data['is_enabled']) ? (int) $data['is_enabled'] : 1,
                    'version'      => (int) $existing['version'] + 1,
                    'date_upd'     => $now,
                ),
                '`id_pcwithdrawal_template` = ' . (int) $existing['id_pcwithdrawal_template']
            );
        }

        return (bool) $this->db->insert('pcwithdrawal_template', array(
            'id_shop'      => (int) $idShop,
            'id_lang'      => (int) $idLang,
            'template_code' => pSQL($templateCode),
            'subject'      => pSQL($data['subject'], true),
            'html_content' => pSQL($data['html_content'], true),
            'text_content' => pSQL($data['text_content'], true),
            'is_enabled'   => isset($data['is_enabled']) ? (int) $data['is_enabled'] : 1,
            'version'      => 1,
            'date_add'     => $now,
            'date_upd'     => $now,
        ));
    }

    /**
     * Get version history for a template.
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $templateCode
     * @param int    $limit
     *
     * @return array[]
     */
    public function getHistory($idShop, $idLang, $templateCode, $limit = 20)
    {
        $sql = 'SELECT th.* FROM `' . bqSQL($this->historyTable) . '` th
                WHERE th.`id_shop` = ' . (int) $idShop . '
                  AND th.`id_lang` = ' . (int) $idLang . '
                  AND th.`template_code` = \'' . pSQL($templateCode) . '\'
                ORDER BY th.`id_pcwithdrawal_template_history` DESC
                LIMIT ' . max(1, (int) $limit);

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Snapshot current template data into history.
     *
     * @param array  $existing
     * @param int    $idEmployee
     * @param string $changeReason
     *
     * @return bool
     */
    private function appendHistory(array $existing, $idEmployee, $changeReason)
    {
        return (bool) $this->db->insert('pcwithdrawal_template_history', array(
            'id_pcwithdrawal_template' => (int) $existing['id_pcwithdrawal_template'],
            'id_shop'                  => (int) $existing['id_shop'],
            'id_lang'                  => (int) $existing['id_lang'],
            'template_code'            => pSQL($existing['template_code']),
            'previous_subject'         => pSQL($existing['subject'], true),
            'previous_html'            => pSQL($existing['html_content'], true),
            'previous_text'            => pSQL($existing['text_content'], true),
            'id_employee'              => $idEmployee ? (int) $idEmployee : null,
            'change_reason'            => pSQL($changeReason, true),
            'date_add'                 => date('Y-m-d H:i:s'),
        ));
    }
}
