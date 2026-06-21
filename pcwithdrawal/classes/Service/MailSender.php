<?php
/**
 * Service — sends emails using PrestaShop Mail::Send().
 * Mail failure does NOT throw exceptions to the caller; errors are logged.
 * Each send creates a mail log entry before sending and updates it after.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\Repository\MailLogRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailSender
{
    /** @var EmailTemplateService */
    private $templateService;

    /** @var EmailRenderer */
    private $renderer;

    /** @var MailLogRepository */
    private $logRepo;

    /** @var AuditService */
    private $auditService;

    /**
     * @param EmailTemplateService $templateService
     * @param EmailRenderer        $renderer
     * @param MailLogRepository    $logRepo
     * @param AuditService         $auditService
     */
    public function __construct(
        EmailTemplateService $templateService,
        EmailRenderer $renderer,
        MailLogRepository $logRepo,
        AuditService $auditService
    ) {
        $this->templateService = $templateService;
        $this->renderer        = $renderer;
        $this->logRepo         = $logRepo;
        $this->auditService    = $auditService;
    }

    /**
     * Send the customer acknowledgement email.
     *
     * @param int    $requestId
     * @param int    $idShop
     * @param int    $idLang
     * @param array  $variables
     *
     * @return bool
     */
    public function sendCustomerAcknowledgement($requestId, $idShop, $idLang, array $variables)
    {
        $toEmail = isset($variables['recipient_email']) ? (string) $variables['recipient_email'] : '';
        $toName  = trim(
            (isset($variables['customer_firstname']) ? $variables['customer_firstname'] : '')
            . ' '
            . (isset($variables['customer_lastname']) ? $variables['customer_lastname'] : '')
        );

        return $this->sendTemplate(
            'customer_acknowledgement',
            $requestId,
            $idShop,
            $idLang,
            $toEmail,
            $toName,
            $variables
        );
    }

    /**
     * Send an admin notification email to all configured recipients.
     *
     * @param int   $requestId
     * @param int   $idShop
     * @param array $variables
     *
     * @return bool True if at least one notification was sent.
     */
    public function sendAdminNotification($requestId, $idShop, array $variables)
    {
        $recipientsRaw = (string) \Configuration::get('PCWITHDRAWAL_ADMIN_RECIPIENTS', null, null, $idShop);

        if ('' === trim($recipientsRaw)) {
            $recipientsRaw = (string) \Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        }

        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);

        $emails = $this->parseRecipientList($recipientsRaw);

        if (empty($emails)) {
            return false;
        }

        $atLeastOne = false;
        foreach ($emails as $email) {
            $sent = $this->sendTemplate(
                'admin_notification',
                $requestId,
                $idShop,
                $idLang,
                $email,
                \Configuration::get('PS_SHOP_NAME', null, null, $idShop),
                $variables
            );
            if ($sent) {
                $atLeastOne = true;
            }
        }

        return $atLeastOne;
    }

    /**
     * Send a guest verification link email.
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $toEmail
     * @param array  $variables
     *
     * @return bool
     */
    public function sendGuestVerification($idShop, $idLang, $toEmail, array $variables)
    {
        return $this->sendTemplate(
            'guest_verification',
            0,
            $idShop,
            $idLang,
            $toEmail,
            '',
            $variables
        );
    }

    /**
     * Send a status update email to the customer.
     *
     * @param int    $requestId
     * @param int    $idShop
     * @param int    $idLang
     * @param array  $variables
     *
     * @return bool
     */
    public function sendStatusUpdate($requestId, $idShop, $idLang, array $variables)
    {
        $toEmail = isset($variables['recipient_email']) ? (string) $variables['recipient_email'] : '';
        $toName  = isset($variables['customer_firstname']) ? (string) $variables['customer_firstname'] : '';

        return $this->sendTemplate(
            'customer_status_update',
            $requestId,
            $idShop,
            $idLang,
            $toEmail,
            $toName,
            $variables
        );
    }

    /**
     * Send return instructions email to the customer.
     *
     * @param int    $requestId
     * @param int    $idShop
     * @param int    $idLang
     * @param array  $variables
     *
     * @return bool
     */
    public function sendReturnInstructions($requestId, $idShop, $idLang, array $variables)
    {
        $toEmail = isset($variables['recipient_email']) ? (string) $variables['recipient_email'] : '';
        $toName  = isset($variables['customer_firstname']) ? (string) $variables['customer_firstname'] : '';

        return $this->sendTemplate(
            'return_instructions',
            $requestId,
            $idShop,
            $idLang,
            $toEmail,
            $toName,
            $variables
        );
    }

    /**
     * Resend a mail by looking up its log entry.
     *
     * @param int $idMailLog
     * @param int $idShop
     *
     * @return bool
     */
    public function resend($idMailLog, $idShop)
    {
        $db  = \Db::getInstance();
        $log = $db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_mail_log`
             WHERE `id_pcwithdrawal_mail_log` = ' . (int) $idMailLog . '
               AND `id_shop` = ' . (int) $idShop . '
             LIMIT 1'
        );

        if (!$log) {
            return false;
        }

        $idLang      = (int) $log['id_lang'];
        $idShop      = (int) $log['id_shop'];
        $recipient   = (string) $log['recipient'];
        $templateCode = (string) $log['template_code'];
        $requestId   = isset($log['id_pcwithdrawal_request']) ? (int) $log['id_pcwithdrawal_request'] : 0;

        // Resolve template fresh
        $resolved = $this->templateService->resolve($templateCode, $idShop, $idLang);

        if (!$resolved) {
            return false;
        }

        $tplRow = $resolved['template'];
        $result = $this->renderer->render(
            $tplRow['html_content'],
            $tplRow['text_content'],
            array()
        );

        // Use snapshots from log if available
        $htmlBody = $log['html_snapshot'] ? (string) $log['html_snapshot'] : $result->htmlBody;
        $textBody = $log['text_snapshot'] ? (string) $log['text_snapshot'] : $result->textBody;
        $subject  = $log['subject_snapshot'] ? (string) $log['subject_snapshot'] : (string) $tplRow['subject'];

        $newLogId = $this->logRepo->insert(array(
            'id_pcwithdrawal_request' => $requestId > 0 ? $requestId : null,
            'id_shop'                 => $idShop,
            'id_lang'                 => $idLang,
            'template_code'           => $templateCode,
            'recipient'               => $recipient,
            'subject_snapshot'        => $subject,
            'html_snapshot'           => $htmlBody,
            'text_snapshot'           => $textBody,
            'send_status'             => 'pending',
        ));

        $sent = $this->dispatchMail($idShop, $idLang, $recipient, '', $subject, $htmlBody, $textBody);

        $this->logRepo->updateStatus($newLogId, $sent ? 'sent' : 'failed', array(
            'ps_mail_return' => (int) $sent,
        ));

        return $sent;
    }

    /**
     * Internal: resolve template, render, log, send.
     *
     * @param string $templateCode
     * @param int    $requestId     0 for pre-request mails (e.g. verification).
     * @param int    $idShop
     * @param int    $idLang
     * @param string $toEmail
     * @param string $toName
     * @param array  $variables
     *
     * @return bool
     */
    private function sendTemplate($templateCode, $requestId, $idShop, $idLang, $toEmail, $toName, array $variables)
    {
        if (!\Validate::isEmail($toEmail)) {
            error_log('[pcwithdrawal][MailSender] Invalid recipient email for template ' . $templateCode);

            return false;
        }

        $resolved = $this->templateService->resolve($templateCode, $idShop, $idLang);

        if (!$resolved) {
            error_log('[pcwithdrawal][MailSender] Template not found: ' . $templateCode . ' (shop=' . $idShop . ', lang=' . $idLang . ')');

            return false;
        }

        $tplRow = $resolved['template'];

        // Render subject (simple placeholder substitution)
        $subject = (string) $tplRow['subject'];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || null === $value) {
                $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
            }
        }

        $renderResult = $this->renderer->render(
            $tplRow['html_content'],
            $tplRow['text_content'],
            $variables
        );

        // Create log entry before sending
        $logId = $this->logRepo->insert(array(
            'id_pcwithdrawal_request' => $requestId > 0 ? $requestId : null,
            'id_shop'                 => $idShop,
            'id_lang'                 => $idLang,
            'template_code'           => $templateCode,
            'recipient'               => $toEmail,
            'subject_snapshot'        => $subject,
            'html_snapshot'           => $renderResult->htmlBody,
            'text_snapshot'           => $renderResult->textBody,
            'send_status'             => 'pending',
        ));

        // Attempt to send
        $sent = false;
        try {
            $sent = $this->dispatchMail($idShop, $idLang, $toEmail, $toName, $subject, $renderResult->htmlBody, $renderResult->textBody);
        } catch (\Exception $e) {
            error_log('[pcwithdrawal][MailSender] Exception sending ' . $templateCode . ': ' . $e->getMessage());
            if ($logId) {
                $this->logRepo->updateStatus($logId, 'failed', array(
                    'error_message' => substr($e->getMessage(), 0, 500),
                    'ps_mail_return' => 0,
                ));
            }

            return false;
        }

        if ($logId) {
            $this->logRepo->updateStatus($logId, $sent ? 'sent' : 'failed', array(
                'ps_mail_return' => (int) $sent,
            ));
        }

        return (bool) $sent;
    }

    /**
     * Dispatch mail via PrestaShop Mail::Send().
     *
     * @param int    $idShop
     * @param int    $idLang
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $htmlBody
     * @param string $textBody
     *
     * @return bool
     */
    private function dispatchMail($idShop, $idLang, $toEmail, $toName, $subject, $htmlBody, $textBody)
    {
        $fromEmail = (string) \Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        $fromName  = (string) \Configuration::get('PS_SHOP_NAME', null, null, $idShop);

        $templateVars = array(
            '{message_html}' => $htmlBody,
            '{message_txt}'  => $textBody,
        );

        // Use PS Mail::Send with a generic wrapper template if available,
        // otherwise send raw HTML via a minimal approach.
        // PS Mail::Send signature: ($idLang, $template, $subject, $templateVars, $to, $toName, $from, $fromName, ...)
        // We use a synthetic module mail approach: write temp file or use inline template.
        // Because the rendered HTML is already complete, use the 'pcwithdrawal_generic' template key
        // and pass the full HTML in vars — the template just outputs {message_html}.

        $result = \Mail::Send(
            (int) $idLang,
            'pcwithdrawal_generic',
            $subject,
            array(
                '{message_html}' => $htmlBody,
                '{message_txt}'  => $textBody,
            ),
            $toEmail,
            $toName,
            $fromEmail,
            $fromName,
            null, // file attachment
            null, // file inline
            _PS_MODULE_DIR_ . 'pcwithdrawal/mails/',
            false,
            $idShop
        );

        return (bool) $result;
    }

    /**
     * Parse a comma/semicolon/newline-separated recipient list into validated emails.
     *
     * @param string $raw
     *
     * @return string[]
     */
    private function parseRecipientList($raw)
    {
        $candidates = preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $valid      = array();

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ('' !== $candidate && \Validate::isEmail($candidate)) {
                $valid[] = $candidate;
            }
        }

        return $valid;
    }
}
