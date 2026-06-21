<?php
/**
 * Validator — validates email templates before saving.
 * Checks required placeholders, detects unknowns, warns about dangerous HTML.
 *
 * @namespace PerpetualCode\PcWithdrawal\Validator
 */

namespace PerpetualCode\PcWithdrawal\Validator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailTemplateValidator
{
    /**
     * Required placeholders per template code.
     * 'any_of' means at least one from the group must be present.
     *
     * @var array
     */
    private static $requiredByTemplate = array(
        'customer_acknowledgement' => array(
            'all'    => array('request_reference', 'submitted_at', 'customer_firstname', 'withdrawal_scope', 'shop_name'),
            'any_of' => array('order_reference', 'contract_information'),
        ),
        'admin_notification' => array(
            'all'    => array('request_reference', 'submitted_at', 'withdrawal_scope', 'shop_name'),
            'any_of' => array(),
        ),
        'customer_status_update' => array(
            'all'    => array('request_reference', 'new_status', 'shop_name'),
            'any_of' => array(),
        ),
        'guest_verification' => array(
            'all'    => array('verification_link', 'shop_name'),
            'any_of' => array(),
        ),
        'return_instructions' => array(
            'all'    => array('request_reference', 'return_instructions', 'shop_name'),
            'any_of' => array(),
        ),
    );

    /**
     * All known placeholders (from EmailRenderer::getSupportedPlaceholders).
     *
     * @var string[]
     */
    private static $knownPlaceholders = array(
        'request_reference',
        'submitted_at',
        'customer_firstname',
        'customer_lastname',
        'order_reference',
        'contract_information',
        'withdrawal_scope',
        'shop_name',
        'shop_url',
        'shop_logo',
        'deadline_end_at',
        'delivery_date_used',
        'eligibility_code',
        'eligibility_reason',
        'new_status',
        'reason',
        'recipient_email',
        'verification_link',
        'return_instructions',
        'reimbursement_info',
        'items_table',
        'items_text',
    );

    /**
     * Dangerous HTML patterns to warn about.
     *
     * @var string[]
     */
    private static $dangerousPatterns = array(
        '/<script\b/i',
        '/<iframe\b/i',
        '/<form\b/i',
        '/<object\b/i',
        '/<embed\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
    );

    /**
     * Validate an email template before saving.
     *
     * @param string $templateCode
     * @param string $subject
     * @param string $htmlContent
     * @param string $textContent
     *
     * @return array ['errors' => [...], 'warnings' => [...]]
     */
    public function validate($templateCode, $subject, $htmlContent, $textContent)
    {
        $errors   = array();
        $warnings = array();

        // Validate subject
        if ('' === trim($subject)) {
            $errors[] = 'Subject line must not be empty.';
        }

        // Validate HTML content
        if ('' === trim($htmlContent)) {
            $errors[] = 'HTML content must not be empty.';
        }

        // Find all placeholders used in HTML
        $placeholdersInHtml = array();
        if (preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $htmlContent, $matches)) {
            $placeholdersInHtml = $matches[1];
        }

        $placeholdersInText = array();
        if (preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $textContent, $matches)) {
            $placeholdersInText = $matches[1];
        }

        $allFoundPlaceholders = array_unique(array_merge($placeholdersInHtml, $placeholdersInText));

        // Check required placeholders for this template code
        if (isset(self::$requiredByTemplate[$templateCode])) {
            $rules = self::$requiredByTemplate[$templateCode];

            // All required
            if (!empty($rules['all'])) {
                foreach ($rules['all'] as $required) {
                    if (!in_array($required, $placeholdersInHtml, true)) {
                        $errors[] = 'Required placeholder {{' . $required . '}} is missing from the HTML content.';
                    }
                }
            }

            // Any-of group
            if (!empty($rules['any_of'])) {
                $hasAny = false;
                foreach ($rules['any_of'] as $optional) {
                    if (in_array($optional, $placeholdersInHtml, true)) {
                        $hasAny = true;
                        break;
                    }
                }
                if (!$hasAny) {
                    $warnings[] = 'At least one of the following placeholders is recommended: {{' . implode('}}, {{', $rules['any_of']) . '}}.';
                }
            }
        }

        // Detect unknown placeholders
        $unknownPlaceholders = array();
        foreach ($allFoundPlaceholders as $placeholder) {
            if (!in_array($placeholder, self::$knownPlaceholders, true)) {
                $unknownPlaceholders[] = $placeholder;
            }
        }
        if (!empty($unknownPlaceholders)) {
            $warnings[] = 'Unknown placeholder(s) detected (will be left as-is): {{' . implode('}}, {{', $unknownPlaceholders) . '}}.';
        }

        // Warn if text_content is empty
        if ('' === trim($textContent)) {
            $warnings[] = 'Plain-text content is empty. Some email clients may not display an HTML-only message correctly.';
        }

        // Warn about dangerous HTML patterns
        foreach (self::$dangerousPatterns as $pattern) {
            if (preg_match($pattern, $htmlContent)) {
                $warnings[] = 'Potentially dangerous HTML pattern detected in template. Content will be sanitized before sending.';
                break;
            }
        }

        return array(
            'errors'   => $errors,
            'warnings' => $warnings,
        );
    }
}
