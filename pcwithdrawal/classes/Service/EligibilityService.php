<?php
/**
 * Service — evaluates eligibility from deadline result and exception rules.
 * Never returns a result that prevents submission.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\DTO\DeadlineResult;
use PerpetualCode\PcWithdrawal\Domain\EligibilityCode;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EligibilityService
{
    /** @var int */
    private $idShop;

    /** @var \Db */
    private $db;

    /**
     * @param int $idShop
     */
    public function __construct($idShop)
    {
        $this->idShop = (int) $idShop;
        $this->db     = \Db::getInstance();
    }

    /**
     * Evaluate eligibility for a withdrawal request.
     * Returns advisory information only; never blocks submission.
     *
     * @param int            $requestId
     * @param array          $orderData
     * @param array          $items        Array of WithdrawalItemData or plain arrays.
     * @param DeadlineResult $deadlineResult
     *
     * @return array Array with keys: eligibility_code, eligibility_reason
     */
    public function evaluate($requestId, array $orderData, array $items, DeadlineResult $deadlineResult)
    {
        $eligibilityCode   = $deadlineResult->eligibilityCode;
        $eligibilityReason = $deadlineResult->eligibilityReason;

        // Check item-level exception rules if table exists and items are present
        if (!empty($items)) {
            $itemException = $this->checkItemExceptions($items);
            if (null !== $itemException) {
                $eligibilityCode   = $itemException['code'];
                $eligibilityReason = $itemException['reason'];
            }
        }

        // Allow external overrides via hook
        $hookResult = \Hook::exec(
            'filterPcWithdrawalEligibility',
            array(
                'request_id'        => (int) $requestId,
                'id_shop'           => $this->idShop,
                'order_data'        => $orderData,
                'items'             => $items,
                'deadline_result'   => $deadlineResult,
                'eligibility_code'  => $eligibilityCode,
                'eligibility_reason' => $eligibilityReason,
            ),
            null,
            true
        );

        if (is_array($hookResult)) {
            foreach ($hookResult as $moduleResult) {
                if (!is_array($moduleResult)) {
                    continue;
                }
                if (isset($moduleResult['eligibility_code'])
                    && EligibilityCode::isValid($moduleResult['eligibility_code'])
                ) {
                    $eligibilityCode   = $moduleResult['eligibility_code'];
                    $eligibilityReason = isset($moduleResult['eligibility_reason'])
                        ? (string) $moduleResult['eligibility_reason']
                        : $eligibilityReason;
                    break;
                }
            }
        }

        return array(
            'eligibility_code'   => $eligibilityCode,
            'eligibility_reason' => $eligibilityReason,
        );
    }

    /**
     * Check items against exception rules table.
     * Returns first matching exception or null if no exception found.
     * Gracefully handles missing table.
     *
     * @param array $items
     *
     * @return array|null Array with keys 'code' and 'reason', or null.
     */
    private function checkItemExceptions(array $items)
    {
        $tableName = _DB_PREFIX_ . 'pcwithdrawal_exception_rule';

        // Check if table exists before querying
        $tableExists = $this->db->getValue(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = \'' . pSQL($tableName) . '\''
        );

        if (!$tableExists) {
            return null;
        }

        foreach ($items as $item) {
            $idProduct          = isset($item['id_product']) ? (int) $item['id_product'] : 0;
            $idProductAttribute = isset($item['id_product_attribute']) ? (int) $item['id_product_attribute'] : 0;

            if (!$idProduct) {
                continue;
            }

            // Check product-level exception
            $rule = $this->db->getRow(
                'SELECT * FROM `' . bqSQL($tableName) . '`
                 WHERE `id_shop` = ' . $this->idShop . '
                   AND `id_product` = ' . $idProduct . '
                   AND `is_active` = 1
                 LIMIT 1'
            );

            if ($rule) {
                return array(
                    'code'   => EligibilityCode::EXCLUDED_GOODS,
                    'reason' => 'One or more items may be excluded from the right of withdrawal. Merchant review required. Exception rule: ' . pSQL($rule['exception_code']),
                );
            }
        }

        return null;
    }
}
