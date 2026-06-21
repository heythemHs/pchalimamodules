<?php
/**
 * Domain value object — all valid eligibility determination codes.
 *
 * @namespace PerpetualCode\PcWithdrawal\Domain
 */

namespace PerpetualCode\PcWithdrawal\Domain;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EligibilityCode
{
    /** Eligibility could not be determined automatically; needs manual review. */
    const MANUAL_REVIEW = 'manual_review';

    /** Request is within the withdrawal period; automatically eligible. */
    const WITHIN_PERIOD = 'within_period';

    /** Request is outside the 14-day withdrawal period. */
    const OUTSIDE_PERIOD = 'outside_period';

    /** No delivery date available to determine period start. */
    const NO_DELIVERY_DATE = 'no_delivery_date';

    /** Order is not in a delivered state. */
    const NOT_DELIVERED = 'not_delivered';

    /** One or more items are categorically excluded from withdrawal (Art. 16 CRDII). */
    const EXCLUDED_GOODS = 'excluded_goods';

    /** Digital content already started with consumer consent. */
    const DIGITAL_CONSUMED = 'digital_consumed';

    /** Service fully performed with consumer consent. */
    const SERVICE_PERFORMED = 'service_performed';

    /** Order reference could not be matched to a shop order. */
    const ORDER_NOT_FOUND = 'order_not_found';

    /** Order does not belong to the submitting customer. */
    const ORDER_MISMATCH = 'order_mismatch';

    /** No order reference provided (guest or lost reference). */
    const NO_REFERENCE = 'no_reference';

    /**
     * @return string[]
     */
    public static function all()
    {
        return array(
            self::MANUAL_REVIEW,
            self::WITHIN_PERIOD,
            self::OUTSIDE_PERIOD,
            self::NO_DELIVERY_DATE,
            self::NOT_DELIVERED,
            self::EXCLUDED_GOODS,
            self::DIGITAL_CONSUMED,
            self::SERVICE_PERFORMED,
            self::ORDER_NOT_FOUND,
            self::ORDER_MISMATCH,
            self::NO_REFERENCE,
        );
    }

    /**
     * Codes that indicate automatic eligibility (no merchant action needed for acceptance).
     *
     * @return string[]
     */
    public static function autoEligible()
    {
        return array(
            self::WITHIN_PERIOD,
        );
    }

    /**
     * Codes that indicate automatic ineligibility (hard block, not just advisory).
     *
     * @return string[]
     */
    public static function autoIneligible()
    {
        return array(
            self::OUTSIDE_PERIOD,
            self::EXCLUDED_GOODS,
            self::DIGITAL_CONSUMED,
            self::SERVICE_PERFORMED,
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
