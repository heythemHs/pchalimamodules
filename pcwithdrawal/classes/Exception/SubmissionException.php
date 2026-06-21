<?php
/**
 * Exception for validation failures during submission.
 *
 * @namespace PerpetualCode\PcWithdrawal\Exception
 */

namespace PerpetualCode\PcWithdrawal\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SubmissionException extends WithdrawalException
{
    /** @var array Associative array of field => error message. */
    private $errors;

    /**
     * @param string $message
     * @param array  $errors
     * @param int    $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', array $errors = array(), $code = 0, $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
