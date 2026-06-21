<?php
/**
 * Repository — sliding-window rate limiter storage.
 *
 * @namespace PerpetualCode\PcWithdrawal\Repository
 */

namespace PerpetualCode\PcWithdrawal\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RateLimitRepository
{
    /** @var \Db */
    private $db;

    /** @var string */
    private $table;

    public function __construct()
    {
        $this->db    = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'pcwithdrawal_rate_limit';
    }

    /**
     * Record a hit for the given identity / action / window.
     * Uses INSERT … ON DUPLICATE KEY UPDATE to atomically increment.
     *
     * @param int    $idShop
     * @param string $actionCode    e.g. 'submit_form', 'verify_identity'
     * @param string $identityHash  sha256 of IP or email
     * @param string $windowStart   datetime string for window boundary
     *
     * @return int Current hit count in this window.
     */
    public function recordHit($idShop, $actionCode, $identityHash, $windowStart)
    {
        $now = date('Y-m-d H:i:s');

        // Try to insert; if duplicate key (same window), increment
        $sql = '
            INSERT INTO `' . bqSQL($this->table) . '`
                (`id_shop`, `action_code`, `identity_hash`, `hit_count`, `window_start`, `last_hit`)
            VALUES
                (' . (int) $idShop . ', \'' . pSQL($actionCode) . '\', \'' . pSQL($identityHash) . '\', 1, \'' . pSQL($windowStart) . '\', \'' . pSQL($now) . '\')
            ON DUPLICATE KEY UPDATE
                `hit_count` = `hit_count` + 1,
                `last_hit`  = \'' . pSQL($now) . '\'
        ';

        $this->db->execute($sql);

        // Fetch the current count
        return $this->getHitCount($idShop, $actionCode, $identityHash, $windowStart);
    }

    /**
     * Get current hit count for a window.
     *
     * @param int    $idShop
     * @param string $actionCode
     * @param string $identityHash
     * @param string $windowStart
     *
     * @return int
     */
    public function getHitCount($idShop, $actionCode, $identityHash, $windowStart)
    {
        $sql = 'SELECT `hit_count` FROM `' . bqSQL($this->table) . '`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `action_code` = \'' . pSQL($actionCode) . '\'
                  AND `identity_hash` = \'' . pSQL($identityHash) . '\'
                  AND `window_start` = \'' . pSQL($windowStart) . '\'
                LIMIT 1';

        return (int) $this->db->getValue($sql);
    }

    /**
     * Purge rate limit rows whose last_hit is older than $hours hours.
     *
     * @param int $hours
     *
     * @return int Rows deleted.
     */
    public function purgeOlderThan($hours = 48)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $hours . ' hours'));

        $this->db->delete(
            'pcwithdrawal_rate_limit',
            '`last_hit` < \'' . pSQL($cutoff) . '\''
        );

        return (int) $this->db->Affected_Rows();
    }

    /**
     * Check whether an identity has exceeded the limit for an action within a window.
     *
     * @param int    $idShop
     * @param string $actionCode
     * @param string $identityHash
     * @param string $windowStart
     * @param int    $maxHits
     *
     * @return bool True if limit exceeded.
     */
    public function isLimitExceeded($idShop, $actionCode, $identityHash, $windowStart, $maxHits)
    {
        return $this->getHitCount($idShop, $actionCode, $identityHash, $windowStart) >= (int) $maxHits;
    }
}
