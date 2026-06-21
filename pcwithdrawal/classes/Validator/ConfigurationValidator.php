<?php
/**
 * Validator — validates module settings before saving.
 *
 * @namespace PerpetualCode\PcWithdrawal\Validator
 */

namespace PerpetualCode\PcWithdrawal\Validator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationValidator
{
    /**
     * Parse and validate a comma/semicolon/newline-separated email recipient list.
     * Returns an array of valid email addresses.
     *
     * @param string $value
     *
     * @return string[] Valid email addresses.
     */
    public function validateRecipientList($value)
    {
        $candidates = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $valid      = array();

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ('' !== $candidate && \Validate::isEmail($candidate)) {
                $valid[] = $candidate;
            }
        }

        return $valid;
    }

    /**
     * Validate a timezone string against PHP's list of valid timezones.
     *
     * @param string $tz
     *
     * @return bool
     */
    public function validateTimezone($tz)
    {
        if (!is_string($tz) || '' === trim($tz)) {
            return false;
        }

        return in_array($tz, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Validate the withdrawal period in days.
     * Must be an integer between 1 and 365 inclusive.
     *
     * @param mixed $days
     *
     * @return bool
     */
    public function validateWithdrawalPeriod($days)
    {
        if (!ctype_digit((string) $days)) {
            return false;
        }

        $int = (int) $days;

        return $int >= 1 && $int <= 365;
    }

    /**
     * Validate the token lifetime in minutes.
     * Must be an integer between 5 and 1440 inclusive.
     *
     * @param mixed $minutes
     *
     * @return bool
     */
    public function validateTokenLifetime($minutes)
    {
        if (!ctype_digit((string) $minutes)) {
            return false;
        }

        $int = (int) $minutes;

        return $int >= 5 && $int <= 1440;
    }
}
