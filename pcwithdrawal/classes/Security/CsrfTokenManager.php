<?php
/**
 * Security — CSRF token manager using PrestaShop session storage.
 * One-time-use tokens with constant-time comparison.
 *
 * @namespace PerpetualCode\PcWithdrawal\Security
 */

namespace PerpetualCode\PcWithdrawal\Security;

use PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CsrfTokenManager
{
    /** @var RandomGenerator */
    private $random;

    /** @var string Session key prefix. */
    const SESSION_PREFIX = 'pcwithdrawal_csrf_';

    /**
     * @param RandomGenerator $random
     */
    public function __construct(RandomGenerator $random)
    {
        $this->random = $random;
    }

    /**
     * Generate and store a CSRF token for the given form name and shop.
     *
     * @param string $formName
     * @param int    $idShop
     *
     * @return string Hex token.
     *
     * @throws \RuntimeException
     */
    public function generateToken($formName, $idShop)
    {
        $token      = $this->random->generateToken(32);
        $sessionKey = $this->buildKey($formName, (int) $idShop);

        $this->writeSession($sessionKey, $token);

        return $token;
    }

    /**
     * Validate a submitted CSRF token (one-time use; token is consumed on success).
     *
     * @param string $formName
     * @param string $token
     * @param int    $idShop
     *
     * @return bool
     */
    public function validateToken($formName, $token, $idShop)
    {
        $sessionKey   = $this->buildKey($formName, (int) $idShop);
        $storedToken  = $this->readSession($sessionKey);

        if (!$storedToken || !is_string($token) || '' === $token) {
            return false;
        }

        // Constant-time comparison (hash_equals available PHP 5.6+)
        $valid = hash_equals($storedToken, $token);

        if ($valid) {
            // Consume token (one-time use)
            $this->clearSession($sessionKey);
        }

        return $valid;
    }

    /**
     * @param string $formName
     * @param int    $idShop
     *
     * @return string
     */
    private function buildKey($formName, $idShop)
    {
        return self::SESSION_PREFIX . preg_replace('/[^a-z0-9_]/i', '_', $formName) . '_' . (int) $idShop;
    }

    /**
     * @param string $key
     * @param string $value
     */
    private function writeSession($key, $value)
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Cannot start session here in all PS contexts; store in cookie fallback
            // In practice PS has already started a session by the time this is called.
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    private function readSession($key)
    {
        if (isset($_SESSION[$key]) && is_string($_SESSION[$key])) {
            return $_SESSION[$key];
        }

        return null;
    }

    /**
     * @param string $key
     */
    private function clearSession($key)
    {
        unset($_SESSION[$key]);
    }
}
