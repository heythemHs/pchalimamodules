<?php
/**
 * Repository — withdrawal request persistence.
 * Every query is shop-restricted.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalRequestRepository
{
    /** @var \Db */
    private $db;

    /** @var string Table name with PS prefix. */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_request';
    }

    /**
     * Find a request by its public reference, restricted to the given shop.
     *
     * @param string $publicReference
     * @param int    $idShop
     *
     * @return array|false Row or false.
     */
    public function findByPublicReference($publicReference, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `public_reference` = \'' . pSQL($publicReference) . '\'
                  AND `id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Find a request by its linked order ID, restricted to shop.
     *
     * @param int $idOrder
     * @param int $idShop
     *
     * @return array|false
     */
    public function findByOrderId($idOrder, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_order` = ' . (int) $idOrder . '
                  AND `id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Find a request by order reference string, restricted to shop.
     *
     * @param string $orderReference
     * @param int    $idShop
     *
     * @return array|false
     */
    public function findByOrderReference($orderReference, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `order_reference` = \'' . pSQL($orderReference) . '\'
                  AND `id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Find all requests for a customer, shop-restricted.
     *
     * @param int $idCustomer
     * @param int $idShop
     * @param int $limit
     * @param int $offset
     *
     * @return array[]
     */
    public function findByCustomerId($idCustomer, $idShop, $limit = 20, $offset = 0)
    {
        $limit  = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_customer` = ' . (int) $idCustomer . '
                  AND `id_shop` = ' . (int) $idShop . '
                ORDER BY `submitted_at_utc` DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Find a single request by its primary key, restricted to shop.
     *
     * @param int $idRequest
     * @param int $idShop
     *
     * @return array|false
     */
    public function findById($idRequest, $idShop)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                  AND `id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Insert a new withdrawal request row.
     *
     * @param array $data Associative array of column => value pairs.
     *
     * @return int New row ID, or 0 on failure.
     */
    public function insert(array $data)
    {
        $now = date('Y-m-d H:i:s');

        if (!isset($data['date_add'])) {
            $data['date_add'] = $now;
        }
        if (!isset($data['date_upd'])) {
            $data['date_upd'] = $now;
        }

        // Cast and sanitize
        $row = $this->prepareRow($data);

        if ($this->db->insert('pcwithdrawal_request', $row)) {
            return (int) $this->db->Insert_ID();
        }

        return 0;
    }

    /**
     * Update only the status of a request (never touches immutable fields).
     *
     * @param int    $idRequest
     * @param string $statusCode
     * @param int    $idShop
     *
     * @return bool
     */
    public function updateStatus($idRequest, $statusCode, $idShop)
    {
        return (bool) $this->db->update(
            'pcwithdrawal_request',
            array(
                'status_code' => pSQL($statusCode),
                'date_upd'    => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . (int) $idRequest . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Update the matched order ID.
     *
     * @param int      $idRequest
     * @param int      $idOrder
     * @param int      $idShop
     * @param string   $matchedAt UTC datetime.
     *
     * @return bool
     */
    public function updateMatchedOrder($idRequest, $idOrder, $idShop, $matchedAt = '')
    {
        if ('' === $matchedAt) {
            $matchedAt = gmdate('Y-m-d H:i:s');
        }

        return (bool) $this->db->update(
            'pcwithdrawal_request',
            array(
                'id_order'   => (int) $idOrder,
                'matched_at' => pSQL($matchedAt),
                'date_upd'   => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . (int) $idRequest . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Update the acknowledgement status and sent timestamp.
     *
     * @param int    $idRequest
     * @param string $ackStatus e.g. 'sent', 'failed'
     * @param string $sentAt    UTC datetime
     * @param int    $idShop
     *
     * @return bool
     */
    public function updateAcknowledgement($idRequest, $ackStatus, $sentAt, $idShop)
    {
        return (bool) $this->db->update(
            'pcwithdrawal_request',
            array(
                'acknowledgement_status'  => pSQL($ackStatus),
                'acknowledgement_sent_at' => pSQL($sentAt),
                'date_upd'                => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . (int) $idRequest . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Mark a request as closed.
     *
     * @param int    $idRequest
     * @param int    $idShop
     * @param string $closedAt UTC datetime
     *
     * @return bool
     */
    public function markClosed($idRequest, $idShop, $closedAt = '')
    {
        if ('' === $closedAt) {
            $closedAt = gmdate('Y-m-d H:i:s');
        }

        return (bool) $this->db->update(
            'pcwithdrawal_request',
            array(
                'status_code' => 'closed',
                'closed_at'   => pSQL($closedAt),
                'date_upd'    => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . (int) $idRequest . '
             AND `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Find requests for list view with filters, shop-restricted.
     *
     * @param array  $filters  Associative: field => value (safe subset only).
     * @param int    $idShop
     * @param int    $limit
     * @param int    $offset
     * @param string $orderBy  Column name (whitelist enforced).
     * @param string $orderWay ASC|DESC
     *
     * @return array[]
     */
    public function findForList(array $filters, $idShop, $limit = 20, $offset = 0, $orderBy = 'submitted_at_utc', $orderWay = 'DESC')
    {
        $allowedOrderBy = array(
            'id_pcwithdrawal_request', 'public_reference', 'status_code',
            'submitted_at_utc', 'consumer_email', 'order_reference',
            'eligibility_code', 'consumer_lastname', 'date_add',
        );
        $allowedFilters = array(
            'status_code', 'eligibility_code', 'scope_code', 'source_code',
            'id_customer', 'id_order', 'order_reference',
        );

        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'submitted_at_utc';
        }
        $orderWay = ('ASC' === strtoupper($orderWay)) ? 'ASC' : 'DESC';
        $limit    = max(1, (int) $limit);
        $offset   = max(0, (int) $offset);

        $where = array('`id_shop` = ' . (int) $idShop);

        foreach ($filters as $field => $value) {
            if (!in_array($field, $allowedFilters, true)) {
                continue;
            }
            if (is_null($value)) {
                $where[] = '`' . bqSQL($field) . '` IS NULL';
            } elseif (is_int($value) || ctype_digit((string) $value)) {
                $where[] = '`' . bqSQL($field) . '` = ' . (int) $value;
            } else {
                $where[] = '`' . bqSQL($field) . '` = \'' . pSQL($value) . '\'';
            }
        }

        // Text search
        if (!empty($filters['_search'])) {
            $search  = pSQL($filters['_search']);
            $where[] = '(`public_reference` LIKE \'%' . $search . '%\'
                      OR `consumer_email` LIKE \'%' . $search . '%\'
                      OR `consumer_lastname` LIKE \'%' . $search . '%\'
                      OR `order_reference` LIKE \'%' . $search . '%\')';
        }

        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY `' . bqSQL($orderBy) . '` ' . $orderWay . '
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Count requests matching filters for pagination, shop-restricted.
     *
     * @param array $filters
     * @param int   $idShop
     *
     * @return int
     */
    public function countForList(array $filters, $idShop)
    {
        $allowedFilters = array(
            'status_code', 'eligibility_code', 'scope_code', 'source_code',
            'id_customer', 'id_order', 'order_reference',
        );

        $where = array('`id_shop` = ' . (int) $idShop);

        foreach ($filters as $field => $value) {
            if (!in_array($field, $allowedFilters, true)) {
                continue;
            }
            if (is_null($value)) {
                $where[] = '`' . bqSQL($field) . '` IS NULL';
            } elseif (is_int($value) || ctype_digit((string) $value)) {
                $where[] = '`' . bqSQL($field) . '` = ' . (int) $value;
            } else {
                $where[] = '`' . bqSQL($field) . '` = \'' . pSQL($value) . '\'';
            }
        }

        if (!empty($filters['_search'])) {
            $search  = pSQL($filters['_search']);
            $where[] = '(`public_reference` LIKE \'%' . $search . '%\'
                      OR `consumer_email` LIKE \'%' . $search . '%\'
                      OR `consumer_lastname` LIKE \'%' . $search . '%\'
                      OR `order_reference` LIKE \'%' . $search . '%\')';
        }

        $sql = 'SELECT COUNT(*) FROM `' . bqSQL($this->table) . '`
                WHERE ' . implode(' AND ', $where);

        return (int) $this->db->getValue($sql);
    }

    /**
     * Sanitize and map data keys for DB insert/update.
     * Returns array safe for Db::insert().
     *
     * @param array $data
     *
     * @return array
     */
    private function prepareRow(array $data)
    {
        $intFields = array(
            'id_shop', 'id_customer', 'id_order', 'id_lang',
        );
        $dateFields = array(
            'delivery_date_used', 'deadline_start_at', 'deadline_end_at',
            'submitted_at_utc', 'submitted_local_at', 'acknowledgement_sent_at',
            'matched_at', 'closed_at', 'date_add', 'date_upd', 'purchase_date_declared',
        );

        $row = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $intFields, true)) {
                $row[$key] = is_null($value) ? null : (int) $value;
            } elseif (in_array($key, $dateFields, true)) {
                $row[$key] = is_null($value) ? null : pSQL($value);
            } else {
                $row[$key] = is_null($value) ? null : pSQL($value);
            }
        }

        return $row;
    }
}
