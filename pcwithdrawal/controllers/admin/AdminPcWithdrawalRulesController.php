<?php
/**
 * Admin controller — exception rule management.
 * Manages pcwithdrawal_exception_rule table via HelperList / HelperForm.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPcWithdrawalRulesController extends ModuleAdminController
{
    /** @var string */
    public $table = 'pcwithdrawal_exception_rule';

    /** @var string */
    public $className = 'Configuration';

    /** @var string */
    public $identifier = 'id_pcwithdrawal_exception_rule';

    /** @var bool */
    public $bootstrap = true;

    /** @var array Exception codes */
    protected static $exceptionCodes = array(
        'excluded_goods'    => 'Excluded Goods (Art. 16)',
        'digital_consumed'  => 'Digital Content Consumed',
        'service_performed' => 'Service Fully Performed',
        'perishable'        => 'Perishable Goods',
        'hygiene_sealed'    => 'Hygiene / Sealed Goods',
        'custom_made'       => 'Custom-Made Goods',
        'newspaper_periodical' => 'Newspapers / Periodicals',
        'event_ticket'      => 'Event Ticket / Time-Specific',
    );

    /** @var array Rule types */
    protected static $ruleTypes = array(
        'product'      => 'Specific Product',
        'category'     => 'Product Category',
        'product_type' => 'Product Type (virtual/download)',
        'service_type' => 'Service Type',
    );

    public function __construct()
    {
        parent::__construct();
        $this->meta_title = $this->module->l('Withdrawal Exception Rules', 'AdminPcWithdrawalRulesController');
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
    }

    /**
     * @return int
     */
    protected function getShopId()
    {
        if (Shop::getContext() === Shop::CONTEXT_SHOP) {
            return (int) Context::getContext()->shop->id;
        }
        return (int) Context::getContext()->shop->id;
    }

    /**
     * @return string
     */
    public function renderList()
    {
        $idShop   = $this->getShopId();
        $db       = Db::getInstance();
        $idLang   = (int) $this->context->language->id;

        $rows = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_exception_rule`
             WHERE `id_shop` = ' . $idShop . '
             ORDER BY `date_add` DESC'
        ) ?: array();

        $helper               = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier   = $this->identifier;
        $helper->actions      = array('edit', 'delete');
        $helper->show_toolbar = true;
        $helper->title        = $this->module->l('Exception Rules', 'AdminPcWithdrawalRulesController');
        $helper->table        = $this->table;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminPcWithdrawalRules', false);
        $helper->token        = Tools::getAdminTokenLite('AdminPcWithdrawalRules');
        $helper->no_link      = true;

        $fields_list = array(
            'id_pcwithdrawal_exception_rule' => array(
                'title' => 'ID',
                'width' => 40,
                'type'  => 'int',
            ),
            'rule_type' => array(
                'title' => $this->module->l('Type', 'AdminPcWithdrawalRulesController'),
                'width' => 100,
            ),
            'reference_name' => array(
                'title' => $this->module->l('Reference', 'AdminPcWithdrawalRulesController'),
                'width' => 200,
            ),
            'exception_code' => array(
                'title' => $this->module->l('Exception Code', 'AdminPcWithdrawalRulesController'),
                'width' => 160,
            ),
            'is_active' => array(
                'title'  => $this->module->l('Active', 'AdminPcWithdrawalRulesController'),
                'width'  => 60,
                'active' => 'status',
                'type'   => 'bool',
            ),
            'date_add' => array(
                'title' => $this->module->l('Created', 'AdminPcWithdrawalRulesController'),
                'width' => 130,
                'type'  => 'datetime',
            ),
        );

        $helper->listTotal = count($rows);
        $helper->bulk_actions = array(
            'delete' => array(
                'text'    => $this->module->l('Delete selected', 'AdminPcWithdrawalRulesController'),
                'confirm' => $this->module->l('Delete selected rules?', 'AdminPcWithdrawalRulesController'),
                'icon'    => 'icon-trash',
            ),
        );

        $addUrl = $this->context->link->getAdminLink('AdminPcWithdrawalRules') . '&addrule=1';

        $toolbarBtn = array(
            'add' => array(
                'href' => $addUrl,
                'desc' => $this->module->l('Add new rule', 'AdminPcWithdrawalRulesController'),
                'icon' => 'process-icon-new',
            ),
        );
        $helper->toolbar_btn = $toolbarBtn;

        return $helper->generateList($rows, $fields_list);
    }

    /**
     * Render add/edit form.
     *
     * @param array $defaultValues
     *
     * @return string
     */
    public function renderForm(array $defaultValues = array())
    {
        $idShop = $this->getShopId();

        // Build exception code select options
        $exceptionOptions = array();
        foreach (self::$exceptionCodes as $code => $label) {
            $exceptionOptions[] = array('id' => $code, 'name' => $label);
        }

        $ruleTypeOptions = array();
        foreach (self::$ruleTypes as $code => $label) {
            $ruleTypeOptions[] = array('id' => $code, 'name' => $label);
        }

        $helper = new HelperForm();
        $helper->show_toolbar        = false;
        $helper->table               = $this->table;
        $helper->identifier          = $this->identifier;
        $helper->submit_action       = 'submitRule';
        $helper->currentIndex        = $this->context->link->getAdminLink('AdminPcWithdrawalRules', false);
        $helper->token               = Tools::getAdminTokenLite('AdminPcWithdrawalRules');
        $helper->tpl_vars            = array(
            'fields_value' => array_merge(array(
                'id_pcwithdrawal_exception_rule' => 0,
                'rule_type'                      => 'product',
                'id_reference'                   => '',
                'reference_name'                 => '',
                'exception_code'                 => 'excluded_goods',
                'notes'                          => '',
                'is_active'                      => 1,
            ), $defaultValues),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->module->l('Exception Rule', 'AdminPcWithdrawalRulesController'),
                    'icon'  => 'icon-warning-sign',
                ),
                'input' => array(
                    array(
                        'type'     => 'select',
                        'label'    => $this->module->l('Rule Type', 'AdminPcWithdrawalRulesController'),
                        'name'     => 'rule_type',
                        'required' => true,
                        'options'  => array(
                            'query' => $ruleTypeOptions,
                            'id'    => 'id',
                            'name'  => 'name',
                        ),
                    ),
                    array(
                        'type'  => 'text',
                        'label' => $this->module->l('Reference ID (product or category)', 'AdminPcWithdrawalRulesController'),
                        'name'  => 'id_reference',
                        'hint'  => $this->module->l('Leave blank for type-level rules.', 'AdminPcWithdrawalRulesController'),
                        'size'  => 10,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->module->l('Reference Name', 'AdminPcWithdrawalRulesController'),
                        'name'     => 'reference_name',
                        'size'     => 64,
                        'required' => true,
                        'hint'     => $this->module->l('Snapshot of product/category name for audit purposes.', 'AdminPcWithdrawalRulesController'),
                    ),
                    array(
                        'type'     => 'select',
                        'label'    => $this->module->l('Exception Code', 'AdminPcWithdrawalRulesController'),
                        'name'     => 'exception_code',
                        'required' => true,
                        'options'  => array(
                            'query' => $exceptionOptions,
                            'id'    => 'id',
                            'name'  => 'name',
                        ),
                    ),
                    array(
                        'type'  => 'textarea',
                        'label' => $this->module->l('Notes', 'AdminPcWithdrawalRulesController'),
                        'name'  => 'notes',
                        'rows'  => 4,
                    ),
                    array(
                        'type'   => 'switch',
                        'label'  => $this->module->l('Active', 'AdminPcWithdrawalRulesController'),
                        'name'   => 'is_active',
                        'values' => array(
                            array('id' => 'active_on', 'value' => 1, 'label' => $this->module->l('Yes', 'AdminPcWithdrawalRulesController')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->module->l('No', 'AdminPcWithdrawalRulesController')),
                        ),
                    ),
                    array(
                        'type'  => 'hidden',
                        'name'  => 'id_pcwithdrawal_exception_rule',
                    ),
                ),
                'submit' => array(
                    'title' => $this->module->l('Save Rule', 'AdminPcWithdrawalRulesController'),
                    'class' => 'btn btn-default pull-right',
                ),
                'buttons' => array(
                    array(
                        'href'  => $this->context->link->getAdminLink('AdminPcWithdrawalRules'),
                        'title' => $this->module->l('Cancel', 'AdminPcWithdrawalRulesController'),
                        'icon'  => 'process-icon-cancel',
                    ),
                ),
            ),
        );

        return $helper->generateForm(array($fieldsForm));
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        $addRule  = Tools::getValue('addrule');
        $editId   = (int) Tools::getValue('update' . $this->table);
        $deleteId = (int) Tools::getValue('delete' . $this->table);
        $idRule   = (int) Tools::getValue($this->identifier);

        if ($deleteId) {
            $this->processDelete($deleteId);
        }

        if ($addRule || $editId || $idRule) {
            $defaults = array();
            if ($editId || $idRule) {
                $rid      = $editId ?: $idRule;
                $defaults = $this->loadRule($rid);
            }
            $this->content .= $this->renderForm($defaults);
        } else {
            $this->content .= $this->renderList();
        }

        parent::initContent();
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitRule')) {
            $this->processSaveRule();
        }

        // Bulk delete
        if (Tools::isSubmit('submitBulkdeletepcwithdrawal_exception_rule')) {
            $ids = Tools::getValue('pcwithdrawal_exception_ruleBox', array());
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $this->processDelete((int) $id);
                }
            }
        }

        parent::postProcess();
    }

    /**
     * Save (insert or update) an exception rule.
     */
    protected function processSaveRule()
    {
        $idShop      = $this->getShopId();
        $idRule      = (int) Tools::getValue('id_pcwithdrawal_exception_rule');
        $ruleType    = (string) Tools::getValue('rule_type', 'product');
        $idReference = Tools::getValue('id_reference', '');
        $refName     = (string) Tools::getValue('reference_name', '');
        $exceptCode  = (string) Tools::getValue('exception_code', 'excluded_goods');
        $notes       = (string) Tools::getValue('notes', '');
        $isActive    = (int) (bool) Tools::getValue('is_active', 1);

        if (!array_key_exists($ruleType, self::$ruleTypes)) {
            $this->errors[] = $this->module->l('Invalid rule type.', 'AdminPcWithdrawalRulesController');
            return;
        }
        if (!array_key_exists($exceptCode, self::$exceptionCodes)) {
            $this->errors[] = $this->module->l('Invalid exception code.', 'AdminPcWithdrawalRulesController');
            return;
        }
        if ('' === trim($refName)) {
            $this->errors[] = $this->module->l('Reference name is required.', 'AdminPcWithdrawalRulesController');
            return;
        }

        $idRef = ($idReference !== '' && ctype_digit((string) $idReference)) ? (int) $idReference : null;

        $db  = Db::getInstance();
        $now = date('Y-m-d H:i:s');

        if ($idRule) {
            // Update
            $db->update(
                'pcwithdrawal_exception_rule',
                array(
                    'rule_type'      => pSQL($ruleType),
                    'id_reference'   => $idRef,
                    'reference_name' => pSQL($refName),
                    'exception_code' => pSQL($exceptCode),
                    'notes'          => pSQL($notes, true),
                    'is_active'      => $isActive,
                    'date_upd'       => $now,
                ),
                '`id_pcwithdrawal_exception_rule` = ' . $idRule . ' AND `id_shop` = ' . $idShop
            );
            $this->confirmations[] = $this->module->l('Rule updated.', 'AdminPcWithdrawalRulesController');
        } else {
            // Insert
            $db->insert('pcwithdrawal_exception_rule', array(
                'id_shop'        => $idShop,
                'rule_type'      => pSQL($ruleType),
                'id_reference'   => $idRef,
                'reference_name' => pSQL($refName),
                'exception_code' => pSQL($exceptCode),
                'notes'          => pSQL($notes, true),
                'is_active'      => $isActive,
                'date_add'       => $now,
                'date_upd'       => $now,
            ));
            $this->confirmations[] = $this->module->l('Rule created.', 'AdminPcWithdrawalRulesController');
        }
    }

    /**
     * Delete a rule.
     *
     * @param int $idRule
     */
    protected function processDelete($idRule)
    {
        $idShop = $this->getShopId();
        Db::getInstance()->delete(
            'pcwithdrawal_exception_rule',
            '`id_pcwithdrawal_exception_rule` = ' . (int) $idRule . ' AND `id_shop` = ' . $idShop
        );
        $this->confirmations[] = $this->module->l('Rule deleted.', 'AdminPcWithdrawalRulesController');
    }

    /**
     * Load rule row for edit form.
     *
     * @param int $idRule
     *
     * @return array
     */
    protected function loadRule($idRule)
    {
        $idShop = $this->getShopId();
        $row    = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_exception_rule`
             WHERE `id_pcwithdrawal_exception_rule` = ' . (int) $idRule . '
               AND `id_shop` = ' . $idShop . '
             LIMIT 1'
        );
        return $row ?: array();
    }
}
