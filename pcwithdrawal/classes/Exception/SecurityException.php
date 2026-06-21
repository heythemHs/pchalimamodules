<?php
/**
 * Exception for CSRF, rate limit, and token violations.
 *
 * @namespace PerpetualCode\PcWithdrawal\Exception
 */

namespace PerpetualCode\PcWithdrawal\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SecurityException extends WithdrawalException
{
}
