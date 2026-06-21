<?php
/**
 * Admin controller — email template management.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PerpetualCode\PcWithdrawal\Repository\EmailTemplateRepository;
use PerpetualCode\PcWithdrawal\Validator\EmailTemplateValidator;

class AdminPcWithdrawalTemplatesController extends ModuleAdminController
{
    /** @var array|null */
    public $currentTemplate = null;

    /** @var int */
    public $currentLang = 0;

    /** @var array */
    public $availableTemplates = array();

    /** @var array */
    public $availableLangs = array();

    /** @var array */
    public $errors = array();

    /** @var array */
    public $confirmations = array();

    /** @var bool */
    public $bootstrap = true;

    /** @var array Template codes with descriptions */
    protected static $templateCodes = array(
        'customer_acknowledgement'   => 'Acknowledgement sent to consumer on submission',
        'admin_new_request'          => 'Admin notification on new request',
        'guest_verification'         => 'Guest email verification link',
        'customer_status_update'     => 'Status change notification to consumer',
        'customer_return_instructions' => 'Return instructions sent to consumer',
        'admin_identification_required' => 'Notify admin that manual identification is required',
    );

    /** @var array All supported placeholders with descriptions */
    protected static $placeholderDocs = array(
        'request_reference'    => 'Unique public reference of the withdrawal request',
        'submitted_at'         => 'UTC date/time of submission',
        'customer_firstname'   => 'Consumer first name',
        'customer_lastname'    => 'Consumer last name',
        'order_reference'      => 'Linked order reference',
        'contract_information' => 'Contract type information',
        'withdrawal_scope'     => 'Scope of withdrawal (full / partial)',
        'shop_name'            => 'Shop name',
        'shop_url'             => 'Shop URL',
        'shop_logo'            => 'Shop logo URL',
        'deadline_end_at'      => 'Withdrawal deadline end date',
        'delivery_date_used'   => 'Delivery date used for deadline calculation',
        'eligibility_code'     => 'Eligibility determination code',
        'eligibility_reason'   => 'Human-readable eligibility reason',
        'new_status'           => 'New status code after a change',
        'reason'               => 'Admin reason for status change',
        'recipient_email'      => 'Recipient email address',
        'verification_link'    => 'Email verification link for guests',
        'return_instructions'  => 'Return shipping instructions',
        'reimbursement_info'   => 'Reimbursement information',
        'items_table'          => 'HTML table of selected items',
        'items_text'           => 'Plain-text list of selected items',
    );

    public function __construct()
    {
        parent::__construct();
        $this->currentLang = (int) Context::getContext()->language->id;
        $this->availableTemplates = array_keys(self::$templateCodes);
        $this->availableLangs     = Language::getLanguages(true);
    }

    /**
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->context->controller->addCSS(
            $this->module->getPathUri() . 'views/css/admin.css',
            'all'
        );
        $this->context->controller->addJS(
            $this->module->getPathUri() . 'views/js/admin.js'
        );
        $this->context->controller->addJS(
            $this->module->getPathUri() . 'views/js/template-editor.js'
        );
    }

    /**
     * @return int
     */
    protected function getShopId()
    {
        return (int) Context::getContext()->shop->id;
    }

    /**
     * Render template editor.
     *
     * @return string
     */
    public function renderView()
    {
        $idShop        = $this->getShopId();
        $idLang        = (int) Tools::getValue('id_lang', $this->currentLang);
        $templateCode  = (string) Tools::getValue('template_code', 'customer_acknowledgement');

        if (!in_array($templateCode, $this->availableTemplates, true)) {
            $templateCode = 'customer_acknowledgement';
        }

        // Validate lang
        $validLangIds = array_map(function ($l) {
            return (int) $l['id_lang'];
        }, $this->availableLangs);

        if (!in_array($idLang, $validLangIds, true)) {
            $idLang = (int) $this->context->language->id;
        }

        $repo  = new EmailTemplateRepository();
        $template = $repo->findByCodeAdmin($idShop, $idLang, $templateCode);

        $history  = $repo->getHistory($idShop, $idLang, $templateCode, 10);

        $this->currentTemplate = $template ?: null;

        $actionUrl = $this->context->link->getAdminLink('AdminPcWithdrawalTemplates');

        $this->context->smarty->assign(array(
            'pcw_template'            => $template,
            'pcw_template_code'       => $templateCode,
            'pcw_id_lang'             => $idLang,
            'pcw_id_shop'             => $idShop,
            'pcw_available_templates' => self::$templateCodes,
            'pcw_available_langs'     => $this->availableLangs,
            'pcw_placeholder_docs'    => self::$placeholderDocs,
            'pcw_history'             => $history,
            'pcw_action_url'          => $actionUrl,
            'pcw_current_lang_name'   => Language::getIsoById($idLang),
        ));

        return $this->module->display($this->module->getLocalPath(), 'views/templates/admin/templates_editor.tpl');
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        $this->content .= $this->renderView();
        parent::initContent();
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        $action = Tools::getValue('pcw_action');

        switch ($action) {
            case 'saveTemplate':
                $this->processSaveTemplate();
                break;
            case 'previewTemplate':
                $this->processPreviewTemplate();
                break;
            case 'sendTestTemplate':
                $this->processSendTestTemplate();
                break;
            case 'resetTemplate':
                $this->processResetTemplate();
                break;
            case 'copyFromLang':
                $this->processCopyFromLang();
                break;
        }

        parent::postProcess();
    }

    /**
     * Save a template (create/update).
     */
    protected function processSaveTemplate()
    {
        $idShop       = $this->getShopId();
        $idLang       = (int) Tools::getValue('id_lang');
        $templateCode = (string) Tools::getValue('template_code');
        $subject      = (string) Tools::getValue('subject', '');
        $htmlContent  = (string) Tools::getValue('html_content', '');
        $textContent  = (string) Tools::getValue('text_content', '');
        $isEnabled    = (int) (bool) Tools::getValue('is_enabled', 1);
        $changeReason = (string) Tools::getValue('change_reason', '');

        if (!in_array($templateCode, $this->availableTemplates, true)) {
            $this->errors[] = $this->module->l('Invalid template code.', 'AdminPcWithdrawalTemplatesController');
            return;
        }

        $validator = new EmailTemplateValidator();
        $result    = $validator->validate($templateCode, $subject, $htmlContent, $textContent);

        if (!empty($result['errors'])) {
            $this->errors = array_merge($this->errors, $result['errors']);
            return;
        }

        // Warnings are non-blocking
        foreach ($result['warnings'] as $w) {
            $this->warnings[] = $w;
        }

        $repo = new EmailTemplateRepository();
        $saved = $repo->upsert(
            $idShop,
            $idLang,
            $templateCode,
            array(
                'subject'      => $subject,
                'html_content' => $htmlContent,
                'text_content' => $textContent,
                'is_enabled'   => $isEnabled,
            ),
            (int) $this->context->employee->id,
            $changeReason
        );

        if ($saved) {
            $this->confirmations[] = $this->module->l('Template saved.', 'AdminPcWithdrawalTemplatesController');
        } else {
            $this->errors[] = $this->module->l('Failed to save template.', 'AdminPcWithdrawalTemplatesController');
        }
    }

    /**
     * Render template with sample data for preview.
     * Returns JSON for modal display.
     */
    protected function processPreviewTemplate()
    {
        $subject     = (string) Tools::getValue('subject', '');
        $htmlContent = (string) Tools::getValue('html_content', '');

        $sampleVars = array(
            'request_reference'    => 'WD-20240601-ABCD',
            'submitted_at'         => date('Y-m-d H:i:s'),
            'customer_firstname'   => 'Jane',
            'customer_lastname'    => 'Doe',
            'order_reference'      => 'ABCDEFGH',
            'contract_information' => 'Distance contract',
            'withdrawal_scope'     => 'full',
            'shop_name'            => Configuration::get('PS_SHOP_NAME'),
            'shop_url'             => Context::getContext()->link->getBaseLink(),
            'deadline_end_at'      => date('Y-m-d', strtotime('+14 days')),
            'delivery_date_used'   => date('Y-m-d', strtotime('-5 days')),
            'eligibility_code'     => 'within_period',
            'eligibility_reason'   => 'Within 14-day withdrawal period',
            'new_status'           => 'accepted',
            'reason'               => 'Request approved after review',
            'recipient_email'      => 'jane.doe@example.com',
            'verification_link'    => 'https://example.com/verify?token=SAMPLE',
            'return_instructions'  => 'Please ship the items to our warehouse.',
            'reimbursement_info'   => 'Refund will be issued within 14 days.',
            'items_table'          => '<table><tr><td>Sample Product</td><td>1x</td><td>€29.99</td></tr></table>',
            'items_text'           => 'Sample Product x1 - €29.99',
        );

        $rendered = $htmlContent;
        $subj     = $subject;
        foreach ($sampleVars as $k => $v) {
            $ph      = '{{' . $k . '}}';
            $rendered = str_replace($ph, htmlspecialchars((string) $v, ENT_QUOTES), $rendered);
            $subj    = str_replace($ph, (string) $v, $subj);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'subject' => $subj,
            'html'    => $rendered,
        ));
        exit;
    }

    /**
     * Send current template content as test to admin email.
     */
    protected function processSendTestTemplate()
    {
        $idShop      = $this->getShopId();
        $idLang      = (int) Tools::getValue('id_lang');
        $subject     = (string) Tools::getValue('subject', '');
        $htmlContent = (string) Tools::getValue('html_content', '');
        $textContent = (string) Tools::getValue('text_content', '');

        $toEmail = (string) Configuration::get('PCWITHDRAWAL_SENDER_EMAIL', null, null, $idShop);
        if (!$toEmail || !Validate::isEmail($toEmail)) {
            $toEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        }
        if (!Validate::isEmail($toEmail)) {
            $this->errors[] = $this->module->l('No valid recipient email found.', 'AdminPcWithdrawalTemplatesController');
            return;
        }

        $sampleVars = array(
            'request_reference'  => 'WD-TEST-0001',
            'submitted_at'       => date('Y-m-d H:i:s'),
            'customer_firstname' => 'Test',
            'customer_lastname'  => 'User',
            'shop_name'          => Configuration::get('PS_SHOP_NAME', null, null, $idShop),
        );

        $html = $htmlContent;
        $text = $textContent;
        $subj = '[TEST] ' . $subject;

        foreach ($sampleVars as $k => $v) {
            $ph   = '{{' . $k . '}}';
            $subj = str_replace($ph, (string) $v, $subj);
            $html = str_replace($ph, (string) $v, $html);
            $text = str_replace($ph, (string) $v, $text);
        }

        $fromEmail = (string) Configuration::get('PCWITHDRAWAL_SENDER_EMAIL', null, null, $idShop);
        if (!$fromEmail || !Validate::isEmail($fromEmail)) {
            $fromEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        }
        $fromName = (string) Configuration::get('PS_SHOP_NAME', null, null, $idShop);

        $sent = (bool) Mail::Send(
            $idLang,
            'pcwithdrawal_generic',
            $subj,
            array('{message_html}' => $html, '{message_txt}' => $text),
            $toEmail,
            '',
            $fromEmail,
            $fromName,
            null,
            null,
            _PS_MODULE_DIR_ . 'pcwithdrawal/mails/',
            false,
            $idShop
        );

        if ($sent) {
            $this->confirmations[] = sprintf(
                $this->module->l('Test email sent to %s.', 'AdminPcWithdrawalTemplatesController'),
                $toEmail
            );
        } else {
            $this->errors[] = $this->module->l('Failed to send test email.', 'AdminPcWithdrawalTemplatesController');
        }
    }

    /**
     * Reset template to built-in default (delete DB record).
     */
    protected function processResetTemplate()
    {
        $idShop       = $this->getShopId();
        $idLang       = (int) Tools::getValue('id_lang');
        $templateCode = (string) Tools::getValue('template_code');

        $db = Db::getInstance();
        $db->delete(
            'pcwithdrawal_template',
            '`id_shop` = ' . $idShop . '
             AND `id_lang` = ' . $idLang . '
             AND `template_code` = \'' . pSQL($templateCode) . '\''
        );

        $this->confirmations[] = $this->module->l('Template reset to built-in default.', 'AdminPcWithdrawalTemplatesController');
    }

    /**
     * Copy template content from another language.
     */
    protected function processCopyFromLang()
    {
        $idShop       = $this->getShopId();
        $targetLang   = (int) Tools::getValue('id_lang');
        $templateCode = (string) Tools::getValue('template_code');
        $fromLang     = (int) Tools::getValue('copy_from_lang');

        if (!$fromLang || $fromLang === $targetLang) {
            $this->errors[] = $this->module->l('Invalid source language.', 'AdminPcWithdrawalTemplatesController');
            return;
        }

        $repo   = new EmailTemplateRepository();
        $source = $repo->findByCodeAdmin($idShop, $fromLang, $templateCode);

        if (!$source) {
            $this->errors[] = $this->module->l('Source template not found.', 'AdminPcWithdrawalTemplatesController');
            return;
        }

        $saved = $repo->upsert(
            $idShop,
            $targetLang,
            $templateCode,
            array(
                'subject'      => $source['subject'],
                'html_content' => $source['html_content'],
                'text_content' => $source['text_content'],
                'is_enabled'   => (int) $source['is_enabled'],
            ),
            (int) $this->context->employee->id,
            'Copied from language ID ' . $fromLang
        );

        if ($saved) {
            $this->confirmations[] = $this->module->l('Template copied from source language.', 'AdminPcWithdrawalTemplatesController');
        } else {
            $this->errors[] = $this->module->l('Failed to copy template.', 'AdminPcWithdrawalTemplatesController');
        }
    }
}
