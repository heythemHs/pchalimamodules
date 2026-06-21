<?php
/**
 * Domain value object — scope of the withdrawal (full order vs partial).
 *
 * @namespace PerpetualCode\PcWithdrawal\Domain
 */

namespace PerpetualCode\PcWithdrawal\Domain;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalScope
{
    /** Consumer is withdrawing from the full order. */
    const FULL = 'full';

    /** Consumer is withdrawing from selected items only. */
    const PARTIAL = 'partial';

    /**
     * @return string[]
     */
    public static function all()
    {
        return array(
            self::FULL,
            self::PARTIAL,
        );
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public static function isValid($code)
    {
        return in_array($code, self::all(), true);
    }
}
