<?php
/**
 * Domain value object — all valid withdrawal request status codes.
 *
 * @namespace PerpetualCode\PcWithdrawal\Domain
 */

namespace PerpetualCode\PcWithdrawal\Domain;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RequestStatus
{
    /** Customer has submitted the form; initial state. */
    const SUBMITTED = 'submitted';

    /** Acknowledgement e-mail sent to consumer. */
    const ACKNOWLEDGED = 'acknowledged';

    /** Back-office has matched the request to an order. */
    const MATCHED = 'matched';

    /** Merchant is reviewing the request. */
    const UNDER_REVIEW = 'under_review';

    /** Withdrawal accepted by merchant; processing reimbursement. */
    const ACCEPTED = 'accepted';

    /** Withdrawal rejected (outside period, excluded goods, etc.). */
    const REJECTED = 'rejected';

    /** Reimbursement has been issued. */
    const REIMBURSED = 'reimbursed';

    /** Consumer withdrew the withdrawal request (cancelled). */
    const WITHDRAWN_BY_CONSUMER = 'withdrawn_by_consumer';

    /** Request closed after completion / no action required. */
    const CLOSED = 'closed';

    /**
     * Returns all valid status codes.
     *
     * @return string[]
     */
    public static function all()
    {
        return array(
            self::SUBMITTED,
            self::ACKNOWLEDGED,
            self::MATCHED,
            self::UNDER_REVIEW,
            self::ACCEPTED,
            self::REJECTED,
            self::REIMBURSED,
            self::WITHDRAWN_BY_CONSUMER,
            self::CLOSED,
        );
    }

    /**
     * Returns terminal statuses (no further transitions allowed).
     *
     * @return string[]
     */
    public static function terminal()
    {
        return array(
            self::REIMBURSED,
            self::REJECTED,
            self::WITHDRAWN_BY_CONSUMER,
            self::CLOSED,
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

    /**
     * @param string $code
     *
     * @return bool
     */
    public static function isTerminal($code)
    {
        return in_array($code, self::terminal(), true);
    }
}
