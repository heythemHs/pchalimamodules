<?php
/**
 * Service — front-office order identification and validation.
 * All methods enforce shop restriction. Generic error responses prevent
 * information disclosure about whether a given order reference exists.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderIdentificationService
{
    /** @var \Db */
    private $db;

    public function __construct()
    {
        $this->db = \Db::getInstance();
    }

    /**
     * Find an order by ID, verifying customer ownership and shop.
     * Returns order row or null.
     *
     * @param int $idOrder
     * @param int $idCustomer
     * @param int $idShop
     *
     * @return array|null
     */
    public function findOrderForCustomer($idOrder, $idCustomer, $idShop)
    {
        $sql = 'SELECT o.* FROM `' . _DB_PREFIX_ . 'orders` o
                WHERE o.`id_order` = ' . (int) $idOrder . '
                  AND o.`id_customer` = ' . (int) $idCustomer . '
                  AND o.`id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        $row = $this->db->getRow($sql);

        return $row ? $row : null;
    }

    /**
     * Find an order by reference, restricted to shop.
     * Caller must not reveal whether the order exists without verifying email.
     *
     * @param string $reference
     * @param int    $idShop
     *
     * @return array|null
     */
    public function findOrderByReference($reference, $idShop)
    {
        $sql = 'SELECT o.* FROM `' . _DB_PREFIX_ . 'orders` o
                WHERE o.`reference` = \'' . pSQL($reference) . '\'
                  AND o.`id_shop` = ' . (int) $idShop . '
                LIMIT 1';

        $row = $this->db->getRow($sql);

        return $row ? $row : null;
    }

    /**
     * Get order detail rows with product snapshots for a given order.
     * Shop restriction is enforced by joining to the orders table.
     *
     * @param int $idOrder
     * @param int $idShop
     *
     * @return array[]
     */
    public function getOrderDetails($idOrder, $idShop)
    {
        $sql = 'SELECT od.* FROM `' . _DB_PREFIX_ . 'order_detail` od
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o
                    ON o.`id_order` = od.`id_order`
                   AND o.`id_shop` = ' . (int) $idShop . '
                WHERE od.`id_order` = ' . (int) $idOrder . '
                ORDER BY od.`id_order_detail` ASC';

        return $this->db->executeS($sql) ?: array();
    }

    /**
     * Verify a guest order by reference and email.
     * Returns the order row only if reference AND email match.
     * Generic null return prevents disclosing whether the reference exists.
     *
     * @param string $reference
     * @param string $email
     * @param int    $idShop
     *
     * @return array|null
     */
    public function verifyGuestOrder($reference, $email, $idShop)
    {
        // Never reveal order existence without email match
        $order = $this->findOrderByReference($reference, $idShop);

        if (!$order) {
            return null;
        }

        // Look up the customer email from the customer table
        $idCustomer    = (int) $order['id_customer'];
        $customerEmail = null;

        if ($idCustomer > 0) {
            $customerEmail = $this->db->getValue(
                'SELECT `email` FROM `' . _DB_PREFIX_ . 'customer`
                 WHERE `id_customer` = ' . $idCustomer . '
                 LIMIT 1'
            );
        }

        // For guest orders (id_customer may be 0), check order addresses
        if (!$customerEmail) {
            // Guest orders store email in ps_customer with is_guest=1, or in the order itself
            // Try to find associated guest customer
            $customerEmail = $this->db->getValue(
                'SELECT c.`email` FROM `' . _DB_PREFIX_ . 'customer` c
                 INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_customer` = c.`id_customer`
                 WHERE o.`id_order` = ' . (int) $order['id_order'] . '
                 LIMIT 1'
            );
        }

        if (!$customerEmail) {
            return null;
        }

        // Constant-time email comparison (case-insensitive)
        $providedHash = hash('sha256', strtolower(trim((string) $email)));
        $storedHash   = hash('sha256', strtolower(trim((string) $customerEmail)));

        if (!hash_equals($storedHash, $providedHash)) {
            return null;
        }

        return $order;
    }

    /**
     * Get recent orders for a logged-in customer, shop-restricted.
     *
     * @param int $idCustomer
     * @param int $idShop
     * @param int $limit
     *
     * @return array[]
     */
    public function getCustomerOrders($idCustomer, $idShop, $limit = 20)
    {
        $limit = max(1, (int) $limit);

        $sql = 'SELECT o.* FROM `' . _DB_PREFIX_ . 'orders` o
                WHERE o.`id_customer` = ' . (int) $idCustomer . '
                  AND o.`id_shop` = ' . (int) $idShop . '
                ORDER BY o.`date_add` DESC
                LIMIT ' . $limit;

        return $this->db->executeS($sql) ?: array();
    }
}
