<?php
/**
 * Base exception class for the pcwithdrawal module.
 *
 * @namespace PerpetualCode\PcWithdrawal\Exception
 */

namespace PerpetualCode\PcWithdrawal\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalException extends \RuntimeException
{
}
