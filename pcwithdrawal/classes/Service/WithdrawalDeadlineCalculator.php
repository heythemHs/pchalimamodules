<?php
/**
 * Service — calculates the legal withdrawal deadline from available delivery date sources.
 * Returns a DeadlineResult DTO. Never blocks submission regardless of result.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\DTO\DeadlineResult;
use PerpetualCode\PcWithdrawal\Domain\EligibilityCode;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalDeadlineCalculator
{
    /** @var int */
    private $idShop;

    /** @var int Default withdrawal period in days. */
    const DEFAULT_PERIOD_DAYS = 14;

    /** @var string Disclaimer appended to all results. */
    const DISCLAIMER = 'This is an indicative calculation only and does not constitute a final legal determination.';

    /**
     * @param int $idShop
     */
    public function __construct($idShop)
    {
        $this->idShop = (int) $idShop;
    }

    /**
     * Calculate the withdrawal deadline.
     *
     * Priority for delivery date:
     *   1. $moduleDeliveryDate — explicit verified date stored by module (passed in)
     *   2. filterPcWithdrawalDeliveryDate hook result
     *   3. Order state history transition to a "delivered" state
     *   4. $customerDeclaredDate
     *   5. null — manual review required
     *
     * @param array       $orderData           Array with keys: id_order, date_add, id_lang, etc.
     * @param string|null $customerDeclaredDate Customer-declared delivery date (Y-m-d or datetime).
     * @param string|null $moduleDeliveryDate   Verified delivery date stored by the module.
     *
     * @return DeadlineResult
     */
    public function calculate(array $orderData, $customerDeclaredDate = null, $moduleDeliveryDate = null)
    {
        $result             = new DeadlineResult();
        $deliveryDate       = null;
        $confidence         = 'none';
        $deliverySource     = null;

        // Priority 1: module-stored verified delivery date
        if (null !== $moduleDeliveryDate && '' !== (string) $moduleDeliveryDate) {
            $deliveryDate   = (string) $moduleDeliveryDate;
            $confidence     = 'high';
            $deliverySource = 'module_verified';
        }

        // Priority 2: hook filterPcWithdrawalDeliveryDate
        if (null === $deliveryDate) {
            $hookResult = $this->invokeDeliveryDateHook($orderData);
            if ($hookResult !== null && '' !== $hookResult) {
                $deliveryDate   = $hookResult;
                $confidence     = 'high';
                $deliverySource = 'hook';
            }
        }

        // Priority 3: order state history (order_history table)
        if (null === $deliveryDate) {
            $historyDate = $this->findDeliveredStateDate($orderData);
            if (null !== $historyDate) {
                $deliveryDate   = $historyDate;
                $confidence     = 'medium';
                $deliverySource = 'order_history';
            }
        }

        // Priority 4: customer-declared date
        if (null === $deliveryDate) {
            if (null !== $customerDeclaredDate && '' !== (string) $customerDeclaredDate) {
                $deliveryDate   = (string) $customerDeclaredDate;
                $confidence     = 'low';
                $deliverySource = 'customer_declared';
            }
        }

        // Priority 5: no delivery date — manual review
        if (null === $deliveryDate) {
            $result->resolved        = false;
            $result->eligibilityCode = EligibilityCode::NO_DELIVERY_DATE;
            $result->eligibilityReason = self::DISCLAIMER . ' No delivery date could be determined; manual review is required.';

            return $result;
        }

        // Normalise delivery date to datetime string
        $deliveryTs = strtotime($deliveryDate);
        if (false === $deliveryTs || $deliveryTs <= 0) {
            $result->resolved        = false;
            $result->eligibilityCode = EligibilityCode::MANUAL_REVIEW;
            $result->eligibilityReason = self::DISCLAIMER . ' Delivery date could not be parsed; manual review is required.';

            return $result;
        }

        $periodDays = (int) \Configuration::get('PCWITHDRAWAL_WITHDRAWAL_PERIOD_DAYS');
        if ($periodDays < 1 || $periodDays > 365) {
            $periodDays = self::DEFAULT_PERIOD_DAYS;
        }

        $deadlineTs     = strtotime('+' . $periodDays . ' days', $deliveryTs);
        $nowTs          = time();
        $daysRemaining  = (int) ceil(($deadlineTs - $nowTs) / 86400);
        $withinPeriod   = ($nowTs <= $deadlineTs);

        $result->resolved         = true;
        $result->startAt          = gmdate('Y-m-d H:i:s', $deliveryTs);
        $result->endAt            = gmdate('Y-m-d H:i:s', $deadlineTs);
        $result->withinPeriod     = $withinPeriod;
        $result->daysRemaining    = $daysRemaining;
        $result->deliveryDateUsed = $deliveryDate;

        if ($withinPeriod) {
            if ('high' === $confidence) {
                $result->eligibilityCode   = EligibilityCode::WITHIN_PERIOD;
                $result->eligibilityReason = self::DISCLAIMER . ' Request appears to be within the ' . $periodDays . '-day withdrawal period (confidence: ' . $confidence . ').';
            } else {
                $result->eligibilityCode   = EligibilityCode::MANUAL_REVIEW;
                $result->eligibilityReason = self::DISCLAIMER . ' Request may be within the withdrawal period but delivery date confidence is ' . $confidence . '; manual review recommended.';
            }
        } else {
            $result->eligibilityCode   = EligibilityCode::OUTSIDE_PERIOD;
            $result->eligibilityReason = self::DISCLAIMER . ' Request appears to be outside the ' . $periodDays . '-day withdrawal period (confidence: ' . $confidence . '). Merchant must review.';
        }

        return $result;
    }

    /**
     * Invoke the filterPcWithdrawalDeliveryDate hook and return date string or null.
     *
     * @param array $orderData
     *
     * @return string|null
     */
    private function invokeDeliveryDateHook(array $orderData)
    {
        $hookResult = \Hook::exec(
            'filterPcWithdrawalDeliveryDate',
            array('order_data' => $orderData, 'id_shop' => $this->idShop),
            null,
            true
        );

        if (!is_array($hookResult)) {
            return null;
        }

        foreach ($hookResult as $moduleResult) {
            if (isset($moduleResult['delivery_date']) && is_string($moduleResult['delivery_date']) && '' !== $moduleResult['delivery_date']) {
                return $moduleResult['delivery_date'];
            }
        }

        return null;
    }

    /**
     * Look for the earliest transition to a delivered state in order_history.
     *
     * @param array $orderData
     *
     * @return string|null datetime string or null
     */
    private function findDeliveredStateDate(array $orderData)
    {
        if (!isset($orderData['id_order']) || !(int) $orderData['id_order']) {
            return null;
        }

        $idOrder = (int) $orderData['id_order'];

        // Find delivered state IDs: order states that mark an order as delivered
        $db              = \Db::getInstance();
        $deliveredStates = $db->executeS(
            'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state`
             WHERE `delivery` = 1
             LIMIT 50'
        );

        if (empty($deliveredStates)) {
            return null;
        }

        $stateIds = array();
        foreach ($deliveredStates as $row) {
            $stateIds[] = (int) $row['id_order_state'];
        }

        $inList = implode(',', $stateIds);

        $sql = 'SELECT `date_add` FROM `' . _DB_PREFIX_ . 'order_history`
                WHERE `id_order` = ' . $idOrder . '
                  AND `id_order_state` IN (' . $inList . ')
                ORDER BY `date_add` ASC
                LIMIT 1';

        $dateAdd = $db->getValue($sql);

        return $dateAdd ? (string) $dateAdd : null;
    }
}
