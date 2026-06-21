<?php
/**
 * Validator — server-side validation for withdrawal form submissions.
 *
 * @namespace PerpetualCode\PcWithdrawal\Validator
 */

namespace PerpetualCode\PcWithdrawal\Validator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SubmissionValidator
{
    /**
     * Validate a logged-in customer submission.
     *
     * @param array $data
     *
     * @return array ['valid' => bool, 'errors' => ['field' => 'message', ...]]
     */
    public function validateIdentifiedSubmission(array $data)
    {
        $errors = array();

        // id_order must be a positive integer
        if (!isset($data['id_order']) || !ctype_digit((string) $data['id_order']) || (int) $data['id_order'] < 1) {
            $errors['id_order'] = 'A valid order must be selected.';
        }

        // scope_code: full or partial
        $scopeCode = isset($data['scope_code']) ? (string) $data['scope_code'] : '';
        if (!in_array($scopeCode, array('full', 'partial'), true)) {
            $errors['scope_code'] = 'Scope must be "full" or "partial".';
        }

        // If partial: items must have at least one with quantity_withdrawn >= 1
        if ('partial' === $scopeCode) {
            $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
            $hasValidItem = false;

            foreach ($items as $item) {
                $qty = isset($item['quantity_withdrawn']) ? (int) $item['quantity_withdrawn'] : 0;
                if ($qty >= 1) {
                    $hasValidItem = true;
                    break;
                }
            }

            if (!$hasValidItem) {
                $errors['items'] = 'At least one item with a quantity of 1 or more must be selected for a partial withdrawal.';
            }
        }

        // customer_statement: optional, max 5000 chars, no HTML
        if (isset($data['customer_statement']) && '' !== (string) $data['customer_statement']) {
            $statement = (string) $data['customer_statement'];
            $cleaned   = strip_tags($statement);

            if (strlen($cleaned) > 5000) {
                $errors['customer_statement'] = 'Your statement must not exceed 5000 characters.';
            }
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validate a guest submission with order reference.
     *
     * @param array $data
     *
     * @return array ['valid' => bool, 'errors' => [...]]
     */
    public function validateGuestSubmission(array $data)
    {
        $errors = array();

        // email
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if ('' === $email || !\Validate::isEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // order_reference: alphanumeric + dash, max 32 chars
        $orderRef = isset($data['order_reference']) ? trim((string) $data['order_reference']) : '';
        if ('' === $orderRef) {
            $errors['order_reference'] = 'Please enter your order reference.';
        } elseif (!preg_match('/^[A-Z0-9\-]{1,32}$/i', $orderRef)) {
            $errors['order_reference'] = 'Order reference must contain only letters, numbers, and dashes (max 32 characters).';
        }

        // scope_code
        $scopeCode = isset($data['scope_code']) ? (string) $data['scope_code'] : '';
        if (!in_array($scopeCode, array('full', 'partial'), true)) {
            $errors['scope_code'] = 'Scope must be "full" or "partial".';
        }

        // If partial: items check
        if ('partial' === $scopeCode) {
            $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
            $hasValidItem = false;

            foreach ($items as $item) {
                $qty = isset($item['quantity_withdrawn']) ? (int) $item['quantity_withdrawn'] : 0;
                if ($qty >= 1) {
                    $hasValidItem = true;
                    break;
                }
            }

            if (!$hasValidItem) {
                $errors['items'] = 'At least one item with a quantity of 1 or more must be selected for a partial withdrawal.';
            }
        }

        // customer_statement: optional, max 5000 chars
        if (isset($data['customer_statement']) && '' !== (string) $data['customer_statement']) {
            $statement = strip_tags((string) $data['customer_statement']);
            if (strlen($statement) > 5000) {
                $errors['customer_statement'] = 'Your statement must not exceed 5000 characters.';
            }
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validate a guest submission without order reference (no-ref form).
     *
     * @param array $data
     *
     * @return array ['valid' => bool, 'errors' => [...]]
     */
    public function validateNoRefSubmission(array $data)
    {
        $errors = array();

        // firstname
        $firstname = isset($data['firstname']) ? trim((string) $data['firstname']) : '';
        if ('' === $firstname || !\Validate::isName($firstname)) {
            $errors['firstname'] = 'Please enter a valid first name.';
        }

        // lastname
        $lastname = isset($data['lastname']) ? trim((string) $data['lastname']) : '';
        if ('' === $lastname || !\Validate::isName($lastname)) {
            $errors['lastname'] = 'Please enter a valid last name.';
        }

        // email
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if ('' === $email || !\Validate::isEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // purchase_date_declared: optional, validate if provided
        if (isset($data['purchase_date_declared']) && '' !== trim((string) $data['purchase_date_declared'])) {
            $dateStr = trim((string) $data['purchase_date_declared']);
            $ts      = strtotime($dateStr);
            if (false === $ts || $ts <= 0) {
                $errors['purchase_date_declared'] = 'Please enter a valid purchase date or leave it blank.';
            }
        }

        // description: required, max 2000 chars
        $description = isset($data['description']) ? trim(strip_tags((string) $data['description'])) : '';
        if ('' === $description) {
            $errors['description'] = 'Please describe your withdrawal request.';
        } elseif (strlen($description) > 2000) {
            $errors['description'] = 'Description must not exceed 2000 characters.';
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }
}
