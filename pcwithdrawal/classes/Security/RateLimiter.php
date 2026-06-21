<?php
/**
 * Security — sliding-window rate limiter.
 * IP addresses are hashed with HMAC before storage.
 *
 * @namespace PerpetualCode\PcWithdrawal\Security
 */

namespace PerpetualCode\PcWithdrawal\Security;

use PerpetualCode\PcWithdrawal\Repository\RateLimitRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RateLimiter
{
    /** @var RateLimitRepository */
    private $repo;

    /** @var int Sliding window in seconds. */
    const WINDOW_SECONDS = 3600;

    /** @var int Default max verify attempts if config not set. */
    const DEFAULT_MAX_VERIFY  = 5;

    /** @var int Default max identification attempts per hour if config not set. */
    const DEFAULT_MAX_ID      = 10;

    /** @var int Default max submission attempts per hour if config not set. */
    const DEFAULT_MAX_SUBMIT  = 5;

    /**
     * @param RateLimitRepository $repo
     */
    public function __construct(RateLimitRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Check whether the IP has exceeded the rate limit for a given action.
     *
     * @param int    $idShop
     * @param string $actionCode
     * @param string $ip
     *
     * @return bool True if limit exceeded.
     */
    public function isLimitExceeded($idShop, $actionCode, $ip)
    {
        $identityHash = $this->hashIp($ip, (int) $idShop);
        $windowStart  = $this->getCurrentWindowStart();
        $maxHits      = $this->getLimit($actionCode);

        return $this->repo->isLimitExceeded(
            (int) $idShop,
            (string) $actionCode,
            $identityHash,
            $windowStart,
            $maxHits
        );
    }

    /**
     * Record a hit for the given action and IP.
     *
     * @param int    $idShop
     * @param string $actionCode
     * @param string $ip
     *
     * @return int Current hit count in this window.
     */
    public function hit($idShop, $actionCode, $ip)
    {
        $identityHash = $this->hashIp($ip, (int) $idShop);
        $windowStart  = $this->getCurrentWindowStart();

        return $this->repo->recordHit(
            (int) $idShop,
            (string) $actionCode,
            $identityHash,
            $windowStart
        );
    }

    /**
     * Derive a shop-specific secret for IP hashing.
     *
     * @param int $idShop
     *
     * @return string
     */
    public function getSecret($idShop)
    {
        return _COOKIE_KEY_ . (int) $idShop;
    }

    /**
     * Hash an IP address using HMAC-SHA256 with the shop secret.
     * The raw IP is never stored.
     *
     * @param string $ip
     * @param int    $idShop
     *
     * @return string
     */
    private function hashIp($ip, $idShop)
    {
        return hash_hmac('sha256', (string) $ip, $this->getSecret($idShop));
    }

    /**
     * Get the start of the current 1-hour window (floor to hour boundary).
     *
     * @return string Y-m-d H:i:s
     */
    private function getCurrentWindowStart()
    {
        $ts = (int) (floor(time() / self::WINDOW_SECONDS) * self::WINDOW_SECONDS);

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Get the configured max hits for an action code.
     *
     * @param string $actionCode
     *
     * @return int
     */
    private function getLimit($actionCode)
    {
        switch ($actionCode) {
            case 'verify_identity':
                $configured = (int) \Configuration::get('PCWITHDRAWAL_MAX_VERIFY_ATTEMPTS');

                return $configured > 0 ? $configured : self::DEFAULT_MAX_VERIFY;

            case 'identify_order':
                $configured = (int) \Configuration::get('PCWITHDRAWAL_MAX_ID_PER_HOUR');

                return $configured > 0 ? $configured : self::DEFAULT_MAX_ID;

            case 'submit_form':
                $configured = (int) \Configuration::get('PCWITHDRAWAL_MAX_SUBMIT_PER_HOUR');

                return $configured > 0 ? $configured : self::DEFAULT_MAX_SUBMIT;

            default:
                return self::DEFAULT_MAX_VERIFY;
        }
    }
}
