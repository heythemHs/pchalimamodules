<?php
/**
 * Service — status transition management for withdrawal requests.
 * Validates transitions via StatusTransitionMap, logs audit events,
 * fires hooks, and optionally sends customer status update emails.
 * No automatic order cancellation, refund, or RMA.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\Domain\RequestStatus;
use PerpetualCode\PcWithdrawal\Domain\StatusTransitionMap;
use PerpetualCode\PcWithdrawal\Exception\TransitionException;
use PerpetualCode\PcWithdrawal\Repository\WithdrawalRequestRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalStatusService
{
    /** @var WithdrawalRequestRepository */
    private $requestRepo;

    /** @var AuditService */
    private $auditService;

    /** @var MailSender */
    private $mailSender;

    /** @var array Statuses that require a non-empty reason. */
    private static $requireReasonStatuses = array(
        RequestStatus::REJECTED,
        RequestStatus::CLOSED,
    );

    /**
     * @param WithdrawalRequestRepository $requestRepo
     * @param AuditService                $auditService
     * @param MailSender                  $mailSender
     */
    public function __construct(
        WithdrawalRequestRepository $requestRepo,
        AuditService $auditService,
        MailSender $mailSender
    ) {
        $this->requestRepo  = $requestRepo;
        $this->auditService = $auditService;
        $this->mailSender   = $mailSender;
    }

    /**
     * Transition a withdrawal request to a new status.
     *
     * @param int      $requestId
     * @param string   $newStatus
     * @param int      $idShop
     * @param int|null $idEmployee
     * @param string   $reason
     * @param bool     $notifyCustomer
     *
     * @return bool
     *
     * @throws TransitionException If transition is invalid or reason is missing.
     */
    public function transition($requestId, $newStatus, $idShop, $idEmployee = null, $reason = '', $notifyCustomer = false)
    {
        $requestId = (int) $requestId;
        $idShop    = (int) $idShop;

        $request = $this->requestRepo->findById($requestId, $idShop);

        if (!$request) {
            throw new TransitionException(sprintf(
                'Withdrawal request #%d not found in shop #%d.',
                $requestId,
                $idShop
            ));
        }

        $currentStatus = (string) $request['status_code'];

        // Validate via domain map (throws InvalidArgumentException on failure)
        try {
            StatusTransitionMap::assertValid($currentStatus, $newStatus, $reason);
        } catch (\InvalidArgumentException $e) {
            throw new TransitionException($e->getMessage(), $e->getCode(), $e);
        }

        // Additional reason check for specific statuses
        if (in_array($newStatus, self::$requireReasonStatuses, true) && '' === trim((string) $reason)) {
            throw new TransitionException(sprintf(
                'A reason is required when transitioning to status "%s".',
                $newStatus
            ));
        }

        // Update status in DB
        $updated = $this->requestRepo->updateStatus($requestId, $newStatus, $idShop);

        if (!$updated) {
            return false;
        }

        // Log audit event
        $this->auditService->logStatusChange(
            $requestId,
            $idShop,
            $currentStatus,
            $newStatus,
            $idEmployee,
            $reason
        );

        // Fire hook
        \Hook::exec('actionPcWithdrawalStatusChanged', array(
            'request_id'     => $requestId,
            'id_shop'        => $idShop,
            'prev_status'    => $currentStatus,
            'new_status'     => $newStatus,
            'id_employee'    => $idEmployee,
            'reason'         => $reason,
        ));

        // Optionally notify customer
        if ($notifyCustomer) {
            $idLang    = isset($request['id_lang']) ? (int) $request['id_lang'] : (int) \Configuration::get('PS_LANG_DEFAULT');
            $variables = array(
                'request_reference' => $request['public_reference'],
                'new_status'        => $newStatus,
                'reason'            => $reason,
                'shop_name'         => \Configuration::get('PS_SHOP_NAME'),
                'customer_firstname' => isset($request['consumer_firstname']) ? $request['consumer_firstname'] : '',
            );

            $mailSuccess = $this->mailSender->sendStatusUpdate($requestId, $idShop, $idLang, $variables);

            $this->auditService->logMailSent(
                $requestId,
                $idShop,
                'customer_status_update',
                isset($request['consumer_email']) ? $request['consumer_email'] : '',
                (bool) $mailSuccess
            );
        }

        return true;
    }
}
