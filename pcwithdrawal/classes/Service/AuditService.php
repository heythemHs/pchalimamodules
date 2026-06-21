<?php
/**
 * Service — clean wrapper around WithdrawalEventRepository for audit event creation.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\Repository\WithdrawalEventRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AuditService
{
    /** @var WithdrawalEventRepository */
    private $eventRepo;

    /**
     * @param WithdrawalEventRepository $eventRepo
     */
    public function __construct(WithdrawalEventRepository $eventRepo)
    {
        $this->eventRepo = $eventRepo;
    }

    /**
     * Log a submission event.
     *
     * @param int   $requestId
     * @param int   $idShop
     * @param array $requestData Relevant submission metadata (no raw PII beyond what is already in the record).
     *
     * @return int Event ID, or 0 on failure.
     */
    public function logSubmission($requestId, $idShop, array $requestData)
    {
        $payload = array(
            'scope_code'   => isset($requestData['scope_code']) ? $requestData['scope_code'] : null,
            'source_code'  => isset($requestData['source_code']) ? $requestData['source_code'] : null,
            'item_count'   => isset($requestData['item_count']) ? (int) $requestData['item_count'] : 0,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => (int) $requestId,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'request_submitted',
            'actor_type'              => 'customer',
            'event_payload'           => json_encode($payload),
        ));
    }

    /**
     * Log a status change event.
     *
     * @param int         $requestId
     * @param int         $idShop
     * @param string      $prevStatus
     * @param string      $newStatus
     * @param int|null    $idEmployee
     * @param string      $reason
     *
     * @return int
     */
    public function logStatusChange($requestId, $idShop, $prevStatus, $newStatus, $idEmployee = null, $reason = '')
    {
        $actorType = ($idEmployee && (int) $idEmployee > 0) ? 'employee' : 'system';

        $payload = array(
            'reason' => (string) $reason,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => (int) $requestId,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'status_changed',
            'actor_type'              => $actorType,
            'id_employee'             => ($idEmployee && (int) $idEmployee > 0) ? (int) $idEmployee : null,
            'previous_status'         => (string) $prevStatus,
            'new_status'              => (string) $newStatus,
            'event_payload'           => json_encode($payload),
        ));
    }

    /**
     * Log an outbound mail event.
     *
     * @param int    $requestId
     * @param int    $idShop
     * @param string $templateCode
     * @param string $recipient  Email address of recipient.
     * @param bool   $success
     *
     * @return int
     */
    public function logMailSent($requestId, $idShop, $templateCode, $recipient, $success)
    {
        $payload = array(
            'template_code'    => (string) $templateCode,
            'recipient_hash'   => hash('sha256', strtolower(trim((string) $recipient))),
            'success'          => (bool) $success,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => (int) $requestId,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'mail_sent',
            'actor_type'              => 'system',
            'event_payload'           => json_encode($payload),
        ));
    }

    /**
     * Log an order link event.
     *
     * @param int $requestId
     * @param int $idShop
     * @param int $prevOrderId
     * @param int $newOrderId
     * @param int $idEmployee
     *
     * @return int
     */
    public function logOrderLinked($requestId, $idShop, $prevOrderId, $newOrderId, $idEmployee)
    {
        $payload = array(
            'prev_id_order' => (int) $prevOrderId,
            'new_id_order'  => (int) $newOrderId,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => (int) $requestId,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'order_linked',
            'actor_type'              => 'employee',
            'id_employee'             => (int) $idEmployee,
            'event_payload'           => json_encode($payload),
        ));
    }

    /**
     * Log an admin note event.
     *
     * @param int    $requestId
     * @param int    $idShop
     * @param string $note
     * @param int    $idEmployee
     *
     * @return int
     */
    public function logAdminNote($requestId, $idShop, $note, $idEmployee)
    {
        $payload = array(
            'note' => (string) $note,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => (int) $requestId,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'admin_note',
            'actor_type'              => 'employee',
            'id_employee'             => (int) $idEmployee,
            'event_payload'           => json_encode($payload),
        ));
    }

    /**
     * Log a verification email sent event.
     * emailHash is SHA-256 of the lowercased email — raw email never logged.
     *
     * @param int|null $requestId  May be null for pre-submission verification.
     * @param int      $idShop
     * @param string   $emailHash  SHA-256 hash of the consumer email.
     *
     * @return int
     */
    public function logVerificationSent($requestId = null, $idShop, $emailHash)
    {
        $payload = array(
            'email_hash' => (string) $emailHash,
        );

        return $this->eventRepo->appendEvent(array(
            'id_pcwithdrawal_request' => ($requestId !== null && (int) $requestId > 0) ? (int) $requestId : 0,
            'id_shop'                 => (int) $idShop,
            'event_code'              => 'verification_sent',
            'actor_type'              => 'system',
            'event_payload'           => json_encode($payload),
        ));
    }
}
