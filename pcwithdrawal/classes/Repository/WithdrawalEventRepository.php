<?php
/**
 * Repository — append-only audit event log with SHA-256 hash chain.
 *
 * Hash chain formula:
 *   event_hash = sha256(previous_hash . request_id . event_code . created_at_utc . payload)
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalEventRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_event';
    }

    /**
     * Append a new event to the log, computing the hash chain automatically.
     *
     * Required keys in $data:
     *   - id_pcwithdrawal_request (int)
     *   - id_shop (int)
     *   - event_code (string)
     *   - actor_type (string) system|employee|customer
     *
     * Optional keys:
     *   - id_employee (int|null)
     *   - id_customer (int|null)
     *   - previous_status (string|null)
     *   - new_status (string|null)
     *   - event_payload (string|null) JSON
     *   - created_at_utc (string) defaults to gmdate('Y-m-d H:i:s')
     *
     * @param array $data
     *
     * @return int New event ID, or 0 on failure.
     */
    public function appendEvent(array $data)
    {
        $idRequest   = (int) $data['id_pcwithdrawal_request'];
        $idShop      = (int) $data['id_shop'];
        $eventCode   = pSQL((string) $data['event_code']);
        $createdAt   = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : gmdate('Y-m-d H:i:s');
        $payload     = isset($data['event_payload']) ? (string) $data['event_payload'] : '';

        // Retrieve the last event hash for this request (hash chain)
        $previousHash = $this->getLastEventHash($idRequest);

        // Compute new hash
        $eventHash = hash('sha256', $previousHash . $idRequest . $eventCode . $createdAt . $payload);

        $row = array(
            'id_pcwithdrawal_request' => $idRequest,
            'id_shop'                 => $idShop,
            'event_code'              => pSQL($eventCode),
            'id_employee'             => isset($data['id_employee']) && $data['id_employee'] ? (int) $data['id_employee'] : null,
            'id_customer'             => isset($data['id_customer']) && $data['id_customer'] ? (int) $data['id_customer'] : null,
            'actor_type'              => pSQL(isset($data['actor_type']) ? $data['actor_type'] : 'system'),
            'previous_status'         => isset($data['previous_status']) ? pSQL($data['previous_status']) : null,
            'new_status'              => isset($data['new_status']) ? pSQL($data['new_status']) : null,
            'event_payload'           => '' !== $payload ? pSQL($payload, true) : null,
            'created_at_utc'          => pSQL($createdAt),
            'previous_event_hash'     => '' !== $previousHash ? pSQL($previousHash) : null,
            'event_hash'              => pSQL($eventHash),
        );

        if ($this->db->insert('pcwithdrawal_event', $row)) {
            return (int) $this->db->Insert_ID();
        }

        return 0;
    }

    /**
     * Get all events for a request, ordered chronologically.
     *
     * @param int $idRequest
     * @param int $idShop   Used for shop restriction.
     *
     * @return array[]
     */
    public function findByRequestId($idRequest, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                  AND `id_shop` = ' . (int) $idShop . '
                ORDER BY `id_pcwithdrawal_event` ASC';

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Get recent events across all requests for a shop.
     *
     * @param int $idShop
     * @param int $limit
     *
     * @return array[]
     */
    public function findRecentByShop($idShop, $limit = 50)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                ORDER BY `id_pcwithdrawal_event` DESC
                LIMIT ' . max(1, (int) $limit);

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Verify the hash chain integrity for a request.
     * Returns true if the chain is intact, false if tampered.
     *
     * @param int $idRequest
     *
     * @return bool
     */
    public function verifyChain($idRequest)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                ORDER BY `id_pcwithdrawal_event` ASC';

        $events = $this->db->executeS($sql) ?: array();

        if (empty($events)) {
            return true;
        }

        $previousHash = '';

        foreach ($events as $event) {
            $expectedHash = hash(
                'sha256',
                $previousHash
                . $event['id_pcwithdrawal_request']
                . $event['event_code']
                . $event['created_at_utc']
                . (string) $event['event_payload']
            );

            if (!hash_equals($expectedHash, (string) $event['event_hash'])) {
                return false;
            }

            $previousHash = $event['event_hash'];
        }

        return true;
    }

    /**
     * Get the hash of the last event for a request, or empty string if none.
     *
     * @param int $idRequest
     *
     * @return string
     */
    private function getLastEventHash($idRequest)
    {
        $sql = 'SELECT `event_hash` FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                ORDER BY `id_pcwithdrawal_event` DESC
                LIMIT 1';

        $hash = $this->db->getValue($sql);

        return $hash ? (string) $hash : '';
    }
}
