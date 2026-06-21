<?php
/**
 * Security — one-time verification token manager for guest order lookup.
 * Raw tokens are never logged; only SHA-256 hashes are stored.
 *
 * @namespace PerpetualCode\PcWithdrawal\Security
 */

namespace PerpetualCode\PcWithdrawal\Security;

use PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator;
use PerpetualCode\PcWithdrawal\Repository\VerificationRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class VerificationTokenManager
{
    /** @var RandomGenerator */
    private $random;

    /** @var VerificationRepository */
    private $repo;

    /** @var int Default token lifetime in minutes if config not set. */
    const DEFAULT_LIFETIME_MINUTES = 15;

    /** @var int Maximum verification attempts per token. */
    const MAX_ATTEMPTS = 5;

    /**
     * @param RandomGenerator        $random
     * @param VerificationRepository $repo
     */
    public function __construct(RandomGenerator $random, VerificationRepository $repo)
    {
        $this->random = $random;
        $this->repo   = $repo;
    }

    /**
     * Generate a raw verification token, store its hash, and return the raw token.
     * Any existing active tokens for the same email+shop+purpose are invalidated first.
     *
     * @param int         $idShop
     * @param string      $purpose    e.g. 'guest_order'
     * @param string      $email      Consumer email (stored only as hash)
     * @param string|null $orderRef   Order reference (stored only as hash, or null)
     *
     * @return string Raw hex token (must be sent to user; never stored).
     *
     * @throws \RuntimeException
     */
    public function createToken($idShop, $purpose, $email, $orderRef = null)
    {
        $emailHash    = hash('sha256', strtolower(trim((string) $email)));
        $orderRefHash = null !== $orderRef && '' !== $orderRef
            ? hash('sha256', (string) $orderRef)
            : '';

        // Invalidate any existing active tokens for this email+shop+purpose
        $this->repo->invalidateForEmail((int) $idShop, (string) $purpose, $emailHash);

        // Generate cryptographically secure raw token
        $rawToken  = $this->random->generateToken(32);
        $tokenHash = hash('sha256', $rawToken);

        $lifetimeMinutes = (int) \Configuration::get('PCWITHDRAWAL_TOKEN_LIFETIME');
        if ($lifetimeMinutes < 1) {
            $lifetimeMinutes = self::DEFAULT_LIFETIME_MINUTES;
        }

        $this->repo->create(
            (int) $idShop,
            (string) $purpose,
            $emailHash,
            $tokenHash,
            $lifetimeMinutes,
            self::MAX_ATTEMPTS,
            (string) $orderRefHash
        );

        return $rawToken;
    }

    /**
     * Verify a raw token against the stored hash.
     * Increments attempt count on every call.
     * Marks token as used on success.
     * Uses constant-time comparison.
     *
     * @param int         $idShop
     * @param string      $purpose
     * @param string      $rawToken
     * @param string      $email
     * @param string|null $orderRef
     *
     * @return bool
     */
    public function verifyToken($idShop, $purpose, $rawToken, $email, $orderRef = null)
    {
        if (!is_string($rawToken) || '' === $rawToken) {
            return false;
        }

        $emailHash = hash('sha256', strtolower(trim((string) $email)));

        $record = $this->repo->findActive((int) $idShop, (string) $purpose, $emailHash);

        if (!$record) {
            return false;
        }

        $idVerification = (int) $record['id_pcwithdrawal_verification'];

        // Increment attempt count before comparing to prevent timing attacks on attempts
        $this->repo->incrementAttempt($idVerification);

        // Compute hash of provided raw token and compare constant-time
        $providedHash = hash('sha256', $rawToken);
        $storedHash   = (string) $record['token_hash'];

        if (!hash_equals($storedHash, $providedHash)) {
            return false;
        }

        // If order reference was provided, verify it also matches
        if (null !== $orderRef && '' !== $orderRef) {
            $providedRefHash = hash('sha256', (string) $orderRef);
            $storedRefHash   = (string) $record['order_reference_hash'];
            if ('' !== $storedRefHash && !hash_equals($storedRefHash, $providedRefHash)) {
                return false;
            }
        }

        // Mark as used
        $this->repo->markUsed($idVerification);

        return true;
    }

    /**
     * Invalidate all active tokens for a given email, shop, and purpose.
     *
     * @param int    $idShop
     * @param string $purpose
     * @param string $email
     *
     * @return bool
     */
    public function invalidateForEmail($idShop, $purpose, $email)
    {
        $emailHash = hash('sha256', strtolower(trim((string) $email)));

        return $this->repo->invalidateForEmail((int) $idShop, (string) $purpose, $emailHash);
    }
}
