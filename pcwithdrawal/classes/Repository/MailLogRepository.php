<?php
/**
 * Repository — e-mail send log persistence.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailLogRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_mail_log';
    }

    /**
     * Insert a mail log entry.
     *
     * @param array $data
     *
     * @return int New log ID, or 0 on failure.
     */
    public function insert(array $data)
    {
        if (!isset($data['created_at_utc'])) {
            $data['created_at_utc'] = gmdate('Y-m-d H:i:s');
        }

        $row = array(
            'id_pcwithdrawal_request' => isset($data['id_pcwithdrawal_request']) && $data['id_pcwithdrawal_request'] ? (int) $data['id_pcwithdrawal_request'] : null,
            'id_shop'                 => (int) $data['id_shop'],
            'id_lang'                 => (int) $data['id_lang'],
            'template_code'           => pSQL($data['template_code']),
            'recipient'               => pSQL($data['recipient']),
            'subject_snapshot'        => pSQL(isset($data['subject_snapshot']) ? $data['subject_snapshot'] : '', true),
            'html_snapshot'           => isset($data['html_snapshot']) ? pSQL($data['html_snapshot'], true) : null,
            'text_snapshot'           => isset($data['text_snapshot']) ? pSQL($data['text_snapshot'], true) : null,
            'send_status'             => pSQL(isset($data['send_status']) ? $data['send_status'] : 'pending'),
            'error_message'           => isset($data['error_message']) ? pSQL($data['error_message'], true) : null,
            'ps_mail_return'          => isset($data['ps_mail_return']) ? (int) $data['ps_mail_return'] : null,
            'provider_message_id'     => isset($data['provider_message_id']) ? pSQL($data['provider_message_id']) : null,
            'created_at_utc'          => pSQL($data['created_at_utc']),
        );

        if ($this->db->insert('pcwithdrawal_mail_log', $row)) {
            return (int) $this->db->Insert_ID();
        }

        return 0;
    }

    /**
     * Update send status and optional error/provider fields.
     *
     * @param int    $idLog
     * @param string $status
     * @param array  $extra  Optional: error_message, ps_mail_return, provider_message_id
     *
     * @return bool
     */
    public function updateStatus($idLog, $status, array $extra = array())
    {
        $data = array('send_status' => pSQL($status));

        if (isset($extra['error_message'])) {
            $data['error_message'] = pSQL($extra['error_message'], true);
        }
        if (isset($extra['ps_mail_return'])) {
            $data['ps_mail_return'] = (int) $extra['ps_mail_return'];
        }
        if (isset($extra['provider_message_id'])) {
            $data['provider_message_id'] = pSQL($extra['provider_message_id']);
        }

        return (bool) $this->db->update(
            'pcwithdrawal_mail_log',
            $data,
            '`id_pcwithdrawal_mail_log` = ' . (int) $idLog
        );
    }

    /**
     * Get all mail log entries for a request.
     *
     * @param int $idRequest
     * @param int $idShop
     *
     * @return array[]
     */
    public function findByRequestId($idRequest, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                  AND `id_shop` = ' . (int) $idShop . '
                ORDER BY `created_at_utc` DESC';

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Purge mail log entries older than $days days (for retention policy).
     *
     * @param int $idShop
     * @param int $days
     *
     * @return int Number of rows deleted.
     */
    public function purgeOlderThan($idShop, $days)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));

        $this->db->delete(
            'pcwithdrawal_mail_log',
            '`id_shop` = ' . (int) $idShop . '
             AND `created_at_utc` < \'' . pSQL($cutoff) . '\''
        );

        return (int) $this->db->Affected_Rows();
    }
}
