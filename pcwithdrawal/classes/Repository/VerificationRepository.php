<?php
/**
 * Repository — guest order verification token storage.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class VerificationRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_verification';
    }

    /**
     * Create a new verification token record.
     *
     * @param int    $idShop
     * @param string $purpose         e.g. 'guest_order'
     * @param string $emailHash       sha256 of email (lowercased)
     * @param string $tokenHash       sha256 of the raw token
     * @param int    $lifetimeMinutes Token validity window
     * @param int    $maxAttempts
     * @param string $orderRefHash    sha256 of order reference, or empty
     *
     * @return int New ID, or 0 on failure.
     */
    public function create($idShop, $purpose, $emailHash, $tokenHash, $lifetimeMinutes = 15, $maxAttempts = 5, $orderRefHash = '')
    {
        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int) $lifetimeMinutes . ' minutes'));

        $row = array(
            'id_shop'              => (int) $idShop,
            'purpose'              => pSQL($purpose),
            'email_hash'           => pSQL($emailHash),
            'order_reference_hash' => '' !== $orderRefHash ? pSQL($orderRefHash) : null,
            'token_hash'           => pSQL($tokenHash),
            'attempt_count'        => 0,
            'max_attempts'         => (int) $maxAttempts,
            'expires_at'           => pSQL($expiresAt),
            'used_at'              => null,
            'created_at'           => pSQL($now),
        );

        if ($this->db->insert('pcwithdrawal_verification', $row)) {
            return (int) $this->db->Insert_ID();
        }

        return 0;
    }

    /**
     * Find an active (unexpired, unused) verification by email hash.
     *
     * @param int    $idShop
     * @param string $purpose
     * @param string $emailHash
     *
     * @return array|false
     */
    public function findActive($idShop, $purpose, $emailHash)
    {
        $now = date('Y-m-d H:i:s');

        $sql = 'SELECT * FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `purpose` = \'' . pSQL($purpose) . '\'
                  AND `email_hash` = \'' . pSQL($emailHash) . '\'
                  AND `used_at` IS NULL
                  AND `expires_at` > \'' . pSQL($now) . '\'
                  AND `attempt_count` < `max_attempts`
                ORDER BY `id_pcwithdrawal_verification` DESC
                LIMIT 1';

        return $this->db->getRow($sql);
    }

    /**
     * Increment attempt count for a verification record.
     *
     * @param int $idVerification
     *
     * @return bool
     */
    public function incrementAttempt($idVerification)
    {
        return (bool) $this->db->execute(
            'UPDATE `' . bqSQL($this->table) . '`
             SET `attempt_count` = `attempt_count` + 1
             WHERE `id_pcwithdrawal_verification` = ' . (int) $idVerification
        );
    }

    /**
     * Mark a verification as used.
     *
     * @param int $idVerification
     *
     * @return bool
     */
    public function markUsed($idVerification)
    {
        return (bool) $this->db->update(
            'pcwithdrawal_verification',
            array('used_at' => date('Y-m-d H:i:s')),
            '`id_pcwithdrawal_verification` = ' . (int) $idVerification
        );
    }

    /**
     * Invalidate all active tokens for a given email+shop+purpose.
     * Used to rotate tokens when a new one is issued.
     *
     * @param int    $idShop
     * @param string $purpose
     * @param string $emailHash
     *
     * @return bool
     */
    public function invalidateForEmail($idShop, $purpose, $emailHash)
    {
        return (bool) $this->db->update(
            'pcwithdrawal_verification',
            array('used_at' => date('Y-m-d H:i:s')),
            '`id_shop` = ' . (int) $idShop . '
             AND `purpose` = \'' . pSQL($purpose) . '\'
             AND `email_hash` = \'' . pSQL($emailHash) . '\'
             AND `used_at` IS NULL'
        );
    }

    /**
     * Delete expired verification records (cleanup cron task).
     *
     * @param int $idShop
     *
     * @return int Rows deleted.
     */
    public function purgeExpired($idShop)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->delete(
            'pcwithdrawal_verification',
            '`id_shop` = ' . (int) $idShop . '
             AND `expires_at` <= \'' . pSQL($now) . '\''
        );

        return (int) $this->db->Affected_Rows();
    }
}
