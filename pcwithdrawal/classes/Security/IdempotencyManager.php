<?php
/**
 * Security — idempotency manager to prevent double-submission on the final form.
 * Uses PrestaShop cookie (Context::getContext()->cookie) for session storage.
 *
 * @namespace PerpetualCode\PcWithdrawal\Security
 */

namespace PerpetualCode\PcWithdrawal\Security;

use PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class IdempotencyManager
{
    /** @var RandomGenerator */
    private $random;

    /** @var int Window in minutes during which a key is considered active. */
    const WINDOW_MINUTES = 30;

    /** @var string Cookie property prefix for idempotency keys. */
    const COOKIE_PREFIX = 'pcwithdrawal_idem_';

    /**
     * @param RandomGenerator $random
     */
    public function __construct(RandomGenerator $random)
    {
        $this->random = $random;
    }

    /**
     * Generate a unique idempotency key for this submission session.
     *
     * @param int      $idShop
     * @param int|null $idCustomer
     * @param string|null $sessionId
     *
     * @return string Hex idempotency key.
     *
     * @throws \RuntimeException
     */
    public function generateKey($idShop, $idCustomer = null, $sessionId = null)
    {
        $entropy = $this->random->generateToken(16);
        $parts   = array(
            (string) $idShop,
            $idCustomer !== null ? (string) $idCustomer : 'guest',
            $sessionId !== null ? (string) $sessionId : 'ns',
            $entropy,
            (string) time(),
        );

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Check if the key was already used in the last WINDOW_MINUTES minutes.
     * If not yet used, reserve it by storing it in the cookie.
     *
     * @param string $key
     * @param int    $idShop
     *
     * @return bool False if the key is new (safe to proceed), true if already used (double-submit).
     */
    public function checkAndReserve($key, $idShop)
    {
        if (!is_string($key) || '' === $key) {
            return false;
        }

        $cookie    = \Context::getContext()->cookie;
        $cookieKey = self::COOKIE_PREFIX . (int) $idShop . '_' . substr($key, 0, 32);

        $stored = isset($cookie->{$cookieKey}) ? $cookie->{$cookieKey} : null;

        if ($stored) {
            // Parse stored value: timestamp|request_id
            $parts     = explode('|', (string) $stored, 2);
            $storedTs  = isset($parts[0]) ? (int) $parts[0] : 0;
            $elapsed   = time() - $storedTs;

            if ($elapsed < (self::WINDOW_MINUTES * 60)) {
                // Key was used within the window — double-submit
                return true;
            }
        }

        // Reserve: store timestamp with a placeholder request_id of 0
        $cookie->{$cookieKey} = time() . '|0';
        $cookie->write();

        return false;
    }

    /**
     * Mark a key as permanently used with the resulting request ID.
     * Updates the cookie entry with the actual request ID so callers can redirect.
     *
     * @param string $key
     * @param int    $idShop
     * @param int    $requestId
     *
     * @return void
     */
    public function release($key, $idShop, $requestId = 0)
    {
        if (!is_string($key) || '' === $key) {
            return;
        }

        $cookie    = \Context::getContext()->cookie;
        $cookieKey = self::COOKIE_PREFIX . (int) $idShop . '_' . substr($key, 0, 32);

        // Overwrite with actual request ID
        $cookie->{$cookieKey} = time() . '|' . (int) $requestId;
        $cookie->write();
    }

    /**
     * Retrieve the request ID stored for an already-submitted key, if any.
     * Returns null if the key is new or expired.
     *
     * @param string $key
     * @param int    $idShop
     *
     * @return int|null Request ID on duplicate, null if new.
     */
    public function getExistingRequestId($key, $idShop)
    {
        if (!is_string($key) || '' === $key) {
            return null;
        }

        $cookie    = \Context::getContext()->cookie;
        $cookieKey = self::COOKIE_PREFIX . (int) $idShop . '_' . substr($key, 0, 32);

        $stored = isset($cookie->{$cookieKey}) ? $cookie->{$cookieKey} : null;

        if (!$stored) {
            return null;
        }

        $parts     = explode('|', (string) $stored, 2);
        $storedTs  = isset($parts[0]) ? (int) $parts[0] : 0;
        $requestId = isset($parts[1]) ? (int) $parts[1] : 0;
        $elapsed   = time() - $storedTs;

        if ($elapsed < (self::WINDOW_MINUTES * 60) && $requestId > 0) {
            return $requestId;
        }

        return null;
    }
}
