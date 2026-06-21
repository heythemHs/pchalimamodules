<?php
/**
 * DTO — result of a withdrawal deadline calculation.
 *
 * @namespace PerpetualCode\PcWithdrawal\DTO
 */

namespace PerpetualCode\PcWithdrawal\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DeadlineResult
{
    /** @var bool Whether the deadline could be calculated. */
    public $resolved;

    /** @var string|null UTC datetime of period start (delivery date). */
    public $startAt;

    /** @var string|null UTC datetime when the period ends. */
    public $endAt;

    /** @var bool Whether submission is within the period. */
    public $withinPeriod;

    /** @var int Number of days remaining (0 = today is last day, negative = expired). */
    public $daysRemaining;

    /** @var string Eligibility code from \PerpetualCode\PcWithdrawal\Domain\EligibilityCode */
    public $eligibilityCode;

    /** @var string Human-readable reason for the eligibility determination. */
    public $eligibilityReason;

    /** @var string|null The delivery datetime actually used for calculation. */
    public $deliveryDateUsed;

    public function __construct()
    {
        $this->resolved          = false;
        $this->startAt           = null;
        $this->endAt             = null;
        $this->withinPeriod      = false;
        $this->daysRemaining     = 0;
        $this->eligibilityCode   = 'manual_review';
        $this->eligibilityReason = '';
        $this->deliveryDateUsed  = null;
    }
}
