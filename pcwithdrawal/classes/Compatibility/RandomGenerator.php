<?php
/**
 * Compatibility — cryptographically secure random byte / token generator.
 * PHP 5.6 compatible; uses random_bytes() when available, falls back to
 * openssl_random_pseudo_bytes().
 *
 * @namespace PerpetualCode\PcWithdrawal\Compatibility
 */

namespace PerpetualCode\PcWithdrawal\Compatibility;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RandomGenerator
{
    /**
     * Generate cryptographically secure random bytes.
     *
     * @param int $length Number of bytes to generate.
     *
     * @return string Raw binary string.
     *
     * @throws \RuntimeException If no secure source is available.
     */
    public function generateBytes($length)
    {
        $length = (int) $length;
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be at least 1.');
        }

        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes  = openssl_random_pseudo_bytes($length, $strong);
            if (false !== $bytes && true === $strong) {
                return $bytes;
            }
            if (false !== $bytes) {
                // openssl returned bytes but didn't flag as cryptographically strong
                // Still better than nothing; warn via error_log but continue.
                error_log('[pcwithdrawal] openssl_random_pseudo_bytes: weak source used for random generation.');

                return $bytes;
            }
        }

        throw new \RuntimeException(
            'No cryptographically secure random source available. '
            . 'Install the openssl extension or upgrade to PHP 7+.'
        );
    }

    /**
     * Generate a hex-encoded random token.
     *
     * @param int $byteLength Number of raw bytes (token hex length = byteLength * 2).
     *
     * @return string Lowercase hex string.
     *
     * @throws \RuntimeException
     */
    public function generateToken($byteLength = 32)
    {
        return bin2hex($this->generateBytes((int) $byteLength));
    }

    /**
     * Generate a public reference in the format WD-YYYYMM-XXXXXX
     * where XXXXXX is 6 uppercase alphanumeric characters derived from random bytes.
     *
     * @param string $prefix Two-letter prefix (default 'WD').
     *
     * @return string e.g. "WD-202601-A3F9K2"
     *
     * @throws \RuntimeException
     */
    public function generateReference($prefix = 'WD')
    {
        $prefix  = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', (string) $prefix), 0, 4));
        $datePart = date('Ym'); // YYYYMM

        // Generate 6 alphanumeric chars from random bytes
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $alphabetLen = strlen($alphabet);
        $result  = '';
        // Request more bytes than needed to avoid modulo bias across the alphabet
        $bytes   = $this->generateBytes(12);
        $idx     = 0;
        $len     = strlen($bytes);

        while (strlen($result) < 6) {
            if ($idx >= $len) {
                $bytes = $this->generateBytes(12);
                $idx   = 0;
                $len   = strlen($bytes);
            }
            $byte = ord($bytes[$idx]);
            $idx++;
            // Rejection sampling: ignore values that would introduce bias
            $maxUsable = floor(256 / $alphabetLen) * $alphabetLen;
            if ($byte >= $maxUsable) {
                continue;
            }
            $result .= $alphabet[$byte % $alphabetLen];
        }

        return $prefix . '-' . $datePart . '-' . $result;
    }
}
