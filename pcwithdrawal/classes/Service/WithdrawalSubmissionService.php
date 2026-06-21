<?php
/**
 * Service — orchestrates the full withdrawal submission flow inside a DB transaction.
 * Mail failure does NOT rollback the legal record.
 * No automatic order cancellation, refund, status change, or RMA.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\Compatibility\RandomGenerator;
use PerpetualCode\PcWithdrawal\Domain\RequestStatus;
use PerpetualCode\PcWithdrawal\DTO\WithdrawalSubmission;
use PerpetualCode\PcWithdrawal\Exception\SubmissionException;
use PerpetualCode\PcWithdrawal\Repository\WithdrawalRequestRepository;
use PerpetualCode\PcWithdrawal\Repository\WithdrawalItemRepository;
use PerpetualCode\PcWithdrawal\Security\IdempotencyManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalSubmissionService
{
    /** @var WithdrawalRequestRepository */
    private $requestRepo;

    /** @var WithdrawalItemRepository */
    private $itemRepo;

    /** @var WithdrawalDeadlineCalculator */
    private $deadlineCalc;

    /** @var EligibilityService */
    private $eligibilityService;

    /** @var AuditService */
    private $auditService;

    /** @var MailSender */
    private $mailSender;

    /** @var RandomGenerator */
    private $random;

    /** @var IdempotencyManager */
    private $idempotency;

    /**
     * @param WithdrawalRequestRepository  $requestRepo
     * @param WithdrawalItemRepository     $itemRepo
     * @param WithdrawalDeadlineCalculator $deadlineCalc
     * @param EligibilityService           $eligibilityService
     * @param AuditService                 $auditService
     * @param MailSender                   $mailSender
     * @param RandomGenerator              $random
     * @param IdempotencyManager           $idempotency
     */
    public function __construct(
        WithdrawalRequestRepository $requestRepo,
        WithdrawalItemRepository $itemRepo,
        WithdrawalDeadlineCalculator $deadlineCalc,
        EligibilityService $eligibilityService,
        AuditService $auditService,
        MailSender $mailSender,
        RandomGenerator $random,
        IdempotencyManager $idempotency
    ) {
        $this->requestRepo        = $requestRepo;
        $this->itemRepo           = $itemRepo;
        $this->deadlineCalc       = $deadlineCalc;
        $this->eligibilityService = $eligibilityService;
        $this->auditService       = $auditService;
        $this->mailSender         = $mailSender;
        $this->random             = $random;
        $this->idempotency        = $idempotency;
    }

    /**
     * Execute the full submission flow.
     *
     * @param WithdrawalSubmission $submission
     * @param string               $idempotencyKey
     * @param array                $orderData       Order row from DB, may be empty for no-ref submissions.
     *
     * @return array ['success' => true, 'request_id' => int, 'public_reference' => string]
     *
     * @throws SubmissionException
     */
    public function submit(WithdrawalSubmission $submission, $idempotencyKey, array $orderData = array())
    {
        $idShop = (int) $submission->idShop;

        // Step 1: Validate idempotency
        if ($this->idempotency->checkAndReserve($idempotencyKey, $idShop)) {
            $existingId = $this->idempotency->getExistingRequestId($idempotencyKey, $idShop);
            if ($existingId) {
                $existingRequest = $this->requestRepo->findById($existingId, $idShop);
                if ($existingRequest) {
                    return array(
                        'success'          => true,
                        'request_id'       => $existingId,
                        'public_reference' => $existingRequest['public_reference'],
                        'duplicate'        => true,
                    );
                }
            }

            throw new SubmissionException('Duplicate submission detected. Please wait before trying again.');
        }

        $db = \Db::getInstance();

        // Step 2: Begin transaction
        $db->execute('START TRANSACTION');

        try {
            // Step 3: Generate public reference
            $publicReference = $this->random->generateReference('WD');

            $submittedAtUtc = '' !== $submission->submittedAtUtc
                ? $submission->submittedAtUtc
                : gmdate('Y-m-d H:i:s');

            // Step 4: Insert withdrawal request (immutable fields)
            $requestData = array(
                'id_shop'                => $idShop,
                'id_customer'            => $submission->idCustomer,
                'id_lang'                => (int) $submission->idLang,
                'id_order'               => !empty($orderData['id_order']) ? (int) $orderData['id_order'] : null,
                'public_reference'       => $publicReference,
                'order_reference'        => $submission->orderReference,
                'consumer_firstname'     => $submission->consumerFirstname,
                'consumer_lastname'      => $submission->consumerLastname,
                'consumer_email'         => $submission->consumerEmail,
                'customer_statement'     => $submission->customerStatement,
                'purchase_date_declared' => '' !== $submission->purchaseDateDeclared ? $submission->purchaseDateDeclared : null,
                'scope_code'             => $submission->scopeCode,
                'contract_type'          => $submission->contractType,
                'source_code'            => $submission->sourceCode,
                'status_code'            => RequestStatus::SUBMITTED,
                'submitted_at_utc'       => $submittedAtUtc,
                'submitted_timezone'     => $submission->submittedTimezone,
                'submitted_local_at'     => $submission->submittedLocalAt,
                'client_ip_hash'         => '' !== $submission->clientIp
                    ? hash('sha256', $submission->clientIp)
                    : null,
                'eligibility_code'       => 'manual_review',
                'eligibility_reason'     => '',
                'date_add'               => date('Y-m-d H:i:s'),
                'date_upd'               => date('Y-m-d H:i:s'),
            );

            $requestId = $this->requestRepo->insert($requestData);

            if (!$requestId) {
                throw new SubmissionException('Failed to save withdrawal request. Please try again.');
            }

            // Step 5: Insert withdrawal items (snapshots)
            foreach ($submission->items as $item) {
                $itemRow = array(
                    'id_pcwithdrawal_request' => $requestId,
                    'id_shop'                 => $idShop,
                    'id_order_detail'         => $item->idOrderDetail,
                    'id_product'              => $item->idProduct,
                    'id_product_attribute'    => $item->idProductAttribute,
                    'product_name_snapshot'   => $item->productName,
                    'product_reference'       => $item->productReference,
                    'attribute_name_snapshot' => $item->attributeName,
                    'quantity_ordered'        => $item->quantityOrdered,
                    'quantity_withdrawn'      => $item->quantityWithdrawn,
                    'unit_price_tax_incl'     => $item->unitPriceTaxIncl,
                    'total_price_tax_incl'    => $item->totalPriceTaxIncl,
                    'currency_iso'            => $item->currencyIso,
                    'exception_code'          => $item->exceptionCode,
                    'date_add'                => date('Y-m-d H:i:s'),
                );

                $this->itemRepo->insert($itemRow);
            }

            // Step 6: Calculate deadline and eligibility
            $deadlineResult = $this->deadlineCalc->calculate(
                $orderData,
                $submission->purchaseDateDeclared,
                null
            );

            $itemsArray = array();
            foreach ($submission->items as $item) {
                $itemsArray[] = array(
                    'id_product'           => $item->idProduct,
                    'id_product_attribute' => $item->idProductAttribute,
                );
            }

            $eligibility = $this->eligibilityService->evaluate(
                $requestId,
                $orderData,
                $itemsArray,
                $deadlineResult
            );

            // Step 7: Update request with eligibility_code, eligibility_reason, deadline fields
            $db->update(
                'pcwithdrawal_request',
                array(
                    'eligibility_code'   => pSQL($eligibility['eligibility_code']),
                    'eligibility_reason' => pSQL($eligibility['eligibility_reason'], true),
                    'delivery_date_used' => $deadlineResult->deliveryDateUsed ? pSQL($deadlineResult->deliveryDateUsed) : null,
                    'deadline_start_at'  => $deadlineResult->startAt ? pSQL($deadlineResult->startAt) : null,
                    'deadline_end_at'    => $deadlineResult->endAt ? pSQL($deadlineResult->endAt) : null,
                    'date_upd'           => date('Y-m-d H:i:s'),
                ),
                '`id_pcwithdrawal_request` = ' . (int) $requestId . ' AND `id_shop` = ' . $idShop
            );

            // Step 8: Insert submission audit event
            $this->auditService->logSubmission($requestId, $idShop, array(
                'scope_code'  => $submission->scopeCode,
                'source_code' => $submission->sourceCode,
                'item_count'  => count($submission->items),
            ));

            // Step 9: Commit transaction
            $db->execute('COMMIT');

        } catch (\Exception $e) {
            $db->execute('ROLLBACK');
            throw new SubmissionException('Submission failed: ' . $e->getMessage(), array(), 0, $e);
        }

        // Mark idempotency key as permanently used with the request ID
        $this->idempotency->release($idempotencyKey, $idShop, $requestId);

        // Step 10: Fire actionPcWithdrawalSubmitted hook (after commit)
        \Hook::exec('actionPcWithdrawalSubmitted', array(
            'request_id'       => $requestId,
            'public_reference' => $publicReference,
            'id_shop'          => $idShop,
            'submission'       => $submission,
        ));

        $idLang    = (int) $submission->idLang;
        $variables = array(
            'request_reference'    => $publicReference,
            'submitted_at'         => $submittedAtUtc,
            'customer_firstname'   => $submission->consumerFirstname,
            'customer_lastname'    => $submission->consumerLastname,
            'order_reference'      => $submission->orderReference,
            'withdrawal_scope'     => $submission->scopeCode,
            'shop_name'            => \Configuration::get('PS_SHOP_NAME'),
        );

        // Step 11: Send customer acknowledgement email (after commit, failure must not rollback)
        $ackSuccess = $this->mailSender->sendCustomerAcknowledgement(
            $requestId,
            $idShop,
            $idLang,
            array_merge($variables, array('recipient_email' => $submission->consumerEmail))
        );

        // Step 12: Send admin notification email (after commit)
        $this->mailSender->sendAdminNotification($requestId, $idShop, $variables);

        // Step 13: Log mail results separately
        $this->auditService->logMailSent(
            $requestId,
            $idShop,
            'customer_acknowledgement',
            $submission->consumerEmail,
            (bool) $ackSuccess
        );

        return array(
            'success'          => true,
            'request_id'       => $requestId,
            'public_reference' => $publicReference,
        );
    }
}
