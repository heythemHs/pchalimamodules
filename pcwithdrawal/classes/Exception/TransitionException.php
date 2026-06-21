<?php
/**
 * Exception for invalid status transitions.
 *
 * @namespace PerpetualCode\PcWithdrawal\Exception
 */

namespace PerpetualCode\PcWithdrawal\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransitionException extends WithdrawalException
{
}
