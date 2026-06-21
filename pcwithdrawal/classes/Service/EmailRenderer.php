<?php
/**
 * Service — renders merchant email templates with safe placeholder replacement.
 * Block placeholders (items_table, items_text) are pre-rendered and not re-escaped.
 * Unknown placeholders are left as-is.
 * HTML is sanitized to strip dangerous tags and attributes.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\DTO\MailRenderResult;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailRenderer
{
    /**
     * Block placeholder names that contain pre-rendered HTML/text — not escaped.
     *
     * @var string[]
     */
    private static $blockPlaceholders = array('items_table', 'items_text');

    /**
     * Render a template pair with variable substitution.
     *
     * @param string $htmlTemplate  Raw HTML template with {{placeholder}} tags.
     * @param string $textTemplate  Raw plain-text template with {{placeholder}} tags.
     * @param array  $variables     Associative array of placeholder_name => value.
     *
     * @return MailRenderResult
     */
    public function render($htmlTemplate, $textTemplate, array $variables)
    {
        $result                   = new MailRenderResult();
        $result->success          = true;
        $result->unknownPlaceholders = array();

        // Pre-render block placeholders
        $blockValues = array();
        if (isset($variables['items']) && is_array($variables['items'])) {
            $blockValues['items_table'] = $this->renderItemsTable($variables['items']);
            $blockValues['items_text']  = $this->renderItemsText($variables['items']);
            unset($variables['items']);
        }

        // Merge block values into variables for substitution tracking
        // but they will be injected raw (not escaped)
        $allKnown = array_merge($this->getSupportedPlaceholders(), array_keys($blockValues));

        // Find all placeholders used in template
        $foundInHtml = array();
        $foundInText = array();
        preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $htmlTemplate, $matchesHtml);
        preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $textTemplate, $matchesText);

        if (!empty($matchesHtml[1])) {
            $foundInHtml = $matchesHtml[1];
        }
        if (!empty($matchesText[1])) {
            $foundInText = $matchesText[1];
        }

        $allFound = array_unique(array_merge($foundInHtml, $foundInText));

        // Collect unknown placeholders (neither in variables nor in block values nor in supported list)
        foreach ($allFound as $placeholder) {
            if (!array_key_exists($placeholder, $variables)
                && !array_key_exists($placeholder, $blockValues)
            ) {
                $result->unknownPlaceholders[] = $placeholder;
            }
        }

        // Substitute scalar variables (escaped in HTML, raw in text)
        $htmlOutput = $htmlTemplate;
        $textOutput = $textTemplate;

        foreach ($variables as $key => $value) {
            if (is_scalar($value) || null === $value) {
                $safeKey   = (string) $key;
                $safeValue = (string) $value;

                $htmlOutput = str_replace('{{' . $safeKey . '}}', htmlspecialchars($safeValue, ENT_QUOTES, 'UTF-8'), $htmlOutput);
                $textOutput = str_replace('{{' . $safeKey . '}}', $safeValue, $textOutput);
            }
        }

        // Substitute block placeholders (pre-rendered, NOT escaped)
        foreach ($blockValues as $key => $value) {
            $htmlOutput = str_replace('{{' . $key . '}}', (string) $value, $htmlOutput);
            // For text version, use the text variant
            if ('items_table' === $key && isset($blockValues['items_text'])) {
                // items_table in text version gets items_text
                $textOutput = str_replace('{{' . $key . '}}', $blockValues['items_text'], $textOutput);
            } else {
                $textOutput = str_replace('{{' . $key . '}}', strip_tags((string) $value), $textOutput);
            }
        }

        // Sanitize HTML output
        $htmlOutput = $this->sanitizeHtml($htmlOutput);

        // Text version: strip remaining HTML tags, preserve line breaks
        $textOutput = $this->htmlToText($textOutput);

        $result->htmlBody = $htmlOutput;
        $result->textBody = $textOutput;

        return $result;
    }

    /**
     * Render an HTML table of withdrawal items.
     *
     * @param array $items
     *
     * @return string HTML string (pre-rendered, not escaped again).
     */
    public function renderItemsTable(array $items)
    {
        if (empty($items)) {
            return '';
        }

        $html  = '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;width:100%">';
        $html .= '<thead><tr>';
        $html .= '<th>' . htmlspecialchars('Product', ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '<th>' . htmlspecialchars('Reference', ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '<th>' . htmlspecialchars('Qty ordered', ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '<th>' . htmlspecialchars('Qty withdrawn', ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '<th>' . htmlspecialchars('Unit price (incl. tax)', ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $productName  = isset($item['product_name']) ? (string) $item['product_name'] : (isset($item->productName) ? (string) $item->productName : '');
            $productRef   = isset($item['product_reference']) ? (string) $item['product_reference'] : (isset($item->productReference) ? (string) $item->productReference : '');
            $qtyOrdered   = isset($item['quantity_ordered']) ? (int) $item['quantity_ordered'] : (isset($item->quantityOrdered) ? (int) $item->quantityOrdered : 0);
            $qtyWithdrawn = isset($item['quantity_withdrawn']) ? (int) $item['quantity_withdrawn'] : (isset($item->quantityWithdrawn) ? (int) $item->quantityWithdrawn : 0);
            $unitPrice    = isset($item['unit_price_tax_incl']) ? (float) $item['unit_price_tax_incl'] : (isset($item->unitPriceTaxIncl) ? (float) $item->unitPriceTaxIncl : 0.0);
            $currency     = isset($item['currency_iso']) ? (string) $item['currency_iso'] : (isset($item->currencyIso) ? (string) $item->currencyIso : '');
            $attribute    = isset($item['attribute_name']) ? (string) $item['attribute_name'] : (isset($item->attributeName) ? (string) $item->attributeName : '');

            $displayName = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
            if ('' !== $attribute) {
                $displayName .= ' (' . htmlspecialchars($attribute, ENT_QUOTES, 'UTF-8') . ')';
            }

            $html .= '<tr>';
            $html .= '<td>' . $displayName . '</td>';
            $html .= '<td>' . htmlspecialchars($productRef, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td style="text-align:center">' . $qtyOrdered . '</td>';
            $html .= '<td style="text-align:center">' . $qtyWithdrawn . '</td>';
            $html .= '<td style="text-align:right">' . htmlspecialchars(number_format($unitPrice, 2) . ' ' . $currency, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render a plain-text list of withdrawal items.
     *
     * @param array $items
     *
     * @return string
     */
    public function renderItemsText(array $items)
    {
        if (empty($items)) {
            return '';
        }

        $lines = array();
        foreach ($items as $item) {
            $productName  = isset($item['product_name']) ? (string) $item['product_name'] : (isset($item->productName) ? (string) $item->productName : '');
            $productRef   = isset($item['product_reference']) ? (string) $item['product_reference'] : (isset($item->productReference) ? (string) $item->productReference : '');
            $qtyWithdrawn = isset($item['quantity_withdrawn']) ? (int) $item['quantity_withdrawn'] : (isset($item->quantityWithdrawn) ? (int) $item->quantityWithdrawn : 0);
            $unitPrice    = isset($item['unit_price_tax_incl']) ? (float) $item['unit_price_tax_incl'] : (isset($item->unitPriceTaxIncl) ? (float) $item->unitPriceTaxIncl : 0.0);
            $currency     = isset($item['currency_iso']) ? (string) $item['currency_iso'] : (isset($item->currencyIso) ? (string) $item->currencyIso : '');
            $attribute    = isset($item['attribute_name']) ? (string) $item['attribute_name'] : (isset($item->attributeName) ? (string) $item->attributeName : '');

            $displayName = $productName;
            if ('' !== $attribute) {
                $displayName .= ' (' . $attribute . ')';
            }

            $lines[] = '- ' . $displayName
                . ' [Ref: ' . $productRef . ']'
                . ' | Qty: ' . $qtyWithdrawn
                . ' | Unit price: ' . number_format($unitPrice, 2) . ' ' . $currency;
        }

        return implode("\n", $lines);
    }

    /**
     * Returns all supported (known) placeholder names.
     *
     * @return string[]
     */
    public function getSupportedPlaceholders()
    {
        return array(
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
    }

    /**
     * Strip dangerous HTML tags and attributes.
     * Removes: script, iframe, form, object, embed tags.
     * Removes: event attributes (onclick, onload, etc.).
     * Removes: javascript: hrefs.
     *
     * @param string $html
     *
     * @return string
     */
    private function sanitizeHtml($html)
    {
        // Strip dangerous tags (with their content for script)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<form\b[^>]*>.*?<\/form>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*\/?>/is', '', $html);

        // Strip event handler attributes (on*)
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        // Strip javascript: hrefs
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:[^"\'>\s]*/i', 'href="#"', $html);

        return (string) $html;
    }

    /**
     * Convert HTML to plain text, preserving line breaks.
     *
     * @param string $html
     *
     * @return string
     */
    private function htmlToText($html)
    {
        // Replace block-level tags with newlines before stripping
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/tr>/i', "\n", $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);
        $html = preg_replace('/<td[^>]*>/i', ' | ', $html);

        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Collapse excessive blank lines (more than 2 consecutive)
        $text = preg_replace("/(\n\s*){3,}/", "\n\n", $text);

        return trim($text);
    }
}
