<?php
/**
 * Repository — withdrawal item persistence.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalItemRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_item';
    }

    /**
     * Retrieve all items for a given request.
     *
     * @param int $idRequest
     *
     * @return array[]
     */
    public function findByRequestId($idRequest)
    {
        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
                ORDER BY `id_pcwithdrawal_item` ASC';

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Insert a single item row.
     *
     * @param array $data
     *
     * @return int New item ID or 0 on failure.
     */
    public function insert(array $data)
    {
        if (!isset($data['date_add'])) {
            $data['date_add'] = date('Y-m-d H:i:s');
        }

        $row = $this->prepareRow($data);

        if ($this->db->insert('pcwithdrawal_item', $row)) {
            return (int) $this->db->Insert_ID();
        }

        return 0;
    }

    /**
     * Bulk insert items for a request within a transaction.
     *
     * @param int     $idRequest
     * @param array[] $items Array of item data arrays.
     *
     * @return bool
     */
    public function insertBulk($idRequest, array $items)
    {
        if (empty($items)) {
            return true;
        }

        $now = date('Y-m-d H:i:s');

        $success = true;
        foreach ($items as $item) {
            $item['id_pcwithdrawal_request'] = (int) $idRequest;
            if (!isset($item['date_add'])) {
                $item['date_add'] = $now;
            }
            $row = $this->prepareRow($item);
            if (!$this->db->insert('pcwithdrawal_item', $row)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete all items belonging to a request.
     *
     * @param int $idRequest
     *
     * @return bool
     */
    public function deleteByRequestId($idRequest)
    {
        return (bool) $this->db->delete(
            'pcwithdrawal_item',
            '`id_pcwithdrawal_request` = ' . (int) $idRequest
        );
    }

    /**
     * Sanitize a data row for DB insertion.
     *
     * @param array $data
     *
     * @return array
     */
    private function prepareRow(array $data)
    {
        $intFields     = array('id_pcwithdrawal_request', 'id_order_detail', 'id_product', 'id_product_attribute', 'quantity_ordered', 'quantity_withdrawn');
        $decimalFields = array('unit_price_tax_incl', 'total_price_tax_incl');

        $row = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $intFields, true)) {
                $row[$key] = is_null($value) ? null : (int) $value;
            } elseif (in_array($key, $decimalFields, true)) {
                $row[$key] = is_null($value) ? null : (float) $value;
            } else {
                $row[$key] = is_null($value) ? null : pSQL($value);
            }
        }

        return $row;
    }
}
