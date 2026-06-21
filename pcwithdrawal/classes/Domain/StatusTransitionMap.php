<?php
/**
 * Domain — valid status transition map with reason requirements.
 *
 * @namespace PerpetualCode\PcWithdrawal\Domain
 */

namespace PerpetualCode\PcWithdrawal\Domain;

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatusTransitionMap
{
    /**
     * Transition map: from_status => array of allowed to_status codes.
     * Each entry is: to_status => requires_reason (bool)
     *
     * @var array
     */
    private static $map = array(
        RequestStatus::SUBMITTED => array(
            RequestStatus::ACKNOWLEDGED         => false,
            RequestStatus::MATCHED              => false,
            RequestStatus::UNDER_REVIEW         => false,
            RequestStatus::REJECTED             => true,
            RequestStatus::WITHDRAWN_BY_CONSUMER => false,
            RequestStatus::CLOSED               => true,
        ),
        RequestStatus::ACKNOWLEDGED => array(
            RequestStatus::MATCHED              => false,
            RequestStatus::UNDER_REVIEW         => false,
            RequestStatus::REJECTED             => true,
            RequestStatus::WITHDRAWN_BY_CONSUMER => false,
            RequestStatus::CLOSED               => true,
        ),
        RequestStatus::MATCHED => array(
            RequestStatus::UNDER_REVIEW         => false,
            RequestStatus::ACCEPTED             => false,
            RequestStatus::REJECTED             => true,
            RequestStatus::WITHDRAWN_BY_CONSUMER => false,
            RequestStatus::CLOSED               => true,
        ),
        RequestStatus::UNDER_REVIEW => array(
            RequestStatus::ACCEPTED             => false,
            RequestStatus::REJECTED             => true,
            RequestStatus::WITHDRAWN_BY_CONSUMER => false,
            RequestStatus::CLOSED               => true,
        ),
        RequestStatus::ACCEPTED => array(
            RequestStatus::REIMBURSED           => false,
            RequestStatus::CLOSED               => false,
        ),
        RequestStatus::REJECTED => array(
            // Rejected can be appealed/re-opened only explicitly
            RequestStatus::UNDER_REVIEW         => true,
            RequestStatus::CLOSED               => false,
        ),
        RequestStatus::REIMBURSED => array(
            RequestStatus::CLOSED               => false,
        ),
        RequestStatus::WITHDRAWN_BY_CONSUMER => array(
            RequestStatus::CLOSED               => false,
        ),
        // CLOSED is terminal — no transitions out
        RequestStatus::CLOSED => array(),
    );

    /**
     * Check whether a transition from $fromStatus to $toStatus is allowed.
     *
     * @param string $fromStatus
     * @param string $toStatus
     *
     * @return bool
     */
    public static function isAllowed($fromStatus, $toStatus)
    {
        if (!isset(self::$map[$fromStatus])) {
            return false;
        }

        return array_key_exists($toStatus, self::$map[$fromStatus]);
    }

    /**
     * Check whether the transition requires a reason to be supplied.
     *
     * @param string $fromStatus
     * @param string $toStatus
     *
     * @return bool
     */
    public static function requiresReason($fromStatus, $toStatus)
    {
        if (!self::isAllowed($fromStatus, $toStatus)) {
            return false;
        }

        return (bool) self::$map[$fromStatus][$toStatus];
    }

    /**
     * Get all reachable statuses from a given status.
     *
     * @param string $fromStatus
     *
     * @return string[]
     */
    public static function allowedFrom($fromStatus)
    {
        if (!isset(self::$map[$fromStatus])) {
            return array();
        }

        return array_keys(self::$map[$fromStatus]);
    }

    /**
     * Assert a transition is valid; throws \InvalidArgumentException if not.
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @param string $reason
     *
     * @throws \InvalidArgumentException
     */
    public static function assertValid($fromStatus, $toStatus, $reason = '')
    {
        if (!self::isAllowed($fromStatus, $toStatus)) {
            throw new \InvalidArgumentException(sprintf(
                'Status transition from "%s" to "%s" is not permitted.',
                $fromStatus,
                $toStatus
            ));
        }

        if (self::requiresReason($fromStatus, $toStatus) && '' === trim((string) $reason)) {
            throw new \InvalidArgumentException(sprintf(
                'Status transition from "%s" to "%s" requires a reason.',
                $fromStatus,
                $toStatus
            ));
        }
    }
}
