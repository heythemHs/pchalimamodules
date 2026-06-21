<?php
/**
 * Admin controller — withdrawal request list and detail management.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PerpetualCode\PcWithdrawal\Domain\RequestStatus;
use PerpetualCode\PcWithdrawal\Domain\StatusTransitionMap;

class AdminPcWithdrawalRequestsController extends ModuleAdminController
{
    /** @var string */
    public $table = 'pcwithdrawal_request';

    /** @var string */
    public $className = 'Configuration';

    /** @var string */
    public $identifier = 'id_pcwithdrawal_request';

    /** @var bool */
    public $bootstrap = true;

    /** @var bool */
    public $list_simple_header = false;

    /** @var array */
    public $filters = array();

    /** @var array|null */
    public $currentRequest = null;

    /** @var int */
    public $shopContext = 0;

    /** @var int */
    protected $pageSize = 25;

    public function __construct()
    {
        parent::__construct();
        $this->shopContext = (int) Context::getContext()->shop->id;
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
     * Return shop ID for restriction, or 0 for all shops.
     *
     * @return int
     */
    protected function getAdminShopId()
    {
        if (Shop::getContext() === Shop::CONTEXT_SHOP) {
            return (int) Context::getContext()->shop->id;
        }
        return 0;
    }

    /**
     * Build WHERE string for list queries.
     *
     * @param array $filters
     * @param int   $adminShopId
     *
     * @return string
     */
    protected function buildListWhere(array $filters, $adminShopId)
    {
        $where = array('1=1');

        if ($adminShopId > 0) {
            $where[] = 'r.`id_shop` = ' . (int) $adminShopId;
        } elseif (!empty($filters['id_shop'])) {
            $where[] = 'r.`id_shop` = ' . (int) $filters['id_shop'];
        }

        if (!empty($filters['public_reference'])) {
            $where[] = 'r.`public_reference` LIKE \'%' . pSQL($filters['public_reference']) . '%\'';
        }
        if (!empty($filters['order_reference'])) {
            $where[] = 'r.`order_reference` LIKE \'%' . pSQL($filters['order_reference']) . '%\'';
        }
        if (!empty($filters['consumer_email'])) {
            $where[] = 'r.`consumer_email` LIKE \'%' . pSQL($filters['consumer_email']) . '%\'';
        }
        if (!empty($filters['status_code'])) {
            $where[] = 'r.`status_code` = \'' . pSQL($filters['status_code']) . '\'';
        }
        if (!empty($filters['eligibility_code'])) {
            $where[] = 'r.`eligibility_code` = \'' . pSQL($filters['eligibility_code']) . '\'';
        }
        if (!empty($filters['id_lang'])) {
            $where[] = 'r.`id_lang` = ' . (int) $filters['id_lang'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.`submitted_at_utc` >= \'' . pSQL($filters['date_from']) . ' 00:00:00\'';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.`submitted_at_utc` <= \'' . pSQL($filters['date_to']) . ' 23:59:59\'';
        }
        if (isset($filters['has_order']) && '' !== $filters['has_order']) {
            if ($filters['has_order'] == '1') {
                $where[] = 'r.`id_order` IS NOT NULL';
            } else {
                $where[] = 'r.`id_order` IS NULL';
            }
        }

        return implode(' AND ', $where);
    }

    /**
     * Fetch paginated list.
     *
     * @param array  $filters
     * @param int    $adminShopId
     * @param int    $page
     * @param string $orderBy
     * @param string $orderWay
     *
     * @return array Keys: rows, total
     */
    protected function fetchList(array $filters, $adminShopId, $page = 1, $orderBy = 'submitted_at_utc', $orderWay = 'DESC')
    {
        $allowed = array(
            'submitted_at_utc', 'public_reference', 'status_code',
            'consumer_email', 'order_reference', 'consumer_lastname',
        );
        if (!in_array($orderBy, $allowed, true)) {
            $orderBy = 'submitted_at_utc';
        }
        $orderWay = ('ASC' === strtoupper($orderWay)) ? 'ASC' : 'DESC';
        $page     = max(1, (int) $page);
        $offset   = ($page - 1) * $this->pageSize;

        $whereStr = $this->buildListWhere($filters, $adminShopId);
        $db       = Db::getInstance();

        $total = (int) $db->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request` r
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = r.`id_shop`
             WHERE ' . $whereStr
        );

        $rows = $db->executeS(
            'SELECT r.*, s.`name` AS shop_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request` r
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = r.`id_shop`
             WHERE ' . $whereStr . '
             ORDER BY r.`' . bqSQL($orderBy) . '` ' . $orderWay . '
             LIMIT ' . (int) $this->pageSize . ' OFFSET ' . (int) $offset
        ) ?: array();

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Render custom paginated list.
     *
     * @return string
     */
    public function renderList()
    {
        $adminShopId = $this->getAdminShopId();

        $filters = array(
            'public_reference' => Tools::getValue('filter_public_reference', ''),
            'order_reference'  => Tools::getValue('filter_order_reference', ''),
            'consumer_email'   => Tools::getValue('filter_consumer_email', ''),
            'status_code'      => Tools::getValue('filter_status_code', ''),
            'eligibility_code' => Tools::getValue('filter_eligibility_code', ''),
            'id_shop'          => Tools::getValue('filter_id_shop', ''),
            'id_lang'          => Tools::getValue('filter_id_lang', ''),
            'date_from'        => Tools::getValue('filter_date_from', ''),
            'date_to'          => Tools::getValue('filter_date_to', ''),
            'has_order'        => Tools::getValue('filter_has_order', ''),
        );

        $page     = max(1, (int) Tools::getValue('p', 1));
        $orderBy  = Tools::getValue('orderby', 'submitted_at_utc');
        $orderWay = Tools::getValue('orderway', 'DESC');

        $result     = $this->fetchList($filters, $adminShopId, $page, $orderBy, $orderWay);
        $totalRows  = $result['total'];
        $rows       = $result['rows'];
        $totalPages = max(1, (int) ceil($totalRows / $this->pageSize));

        $shops     = Shop::getShops(false);
        $languages = Language::getLanguages(true);

        $baseUrl = $this->context->link->getAdminLink('AdminPcWithdrawalRequests');

        $this->context->smarty->assign(array(
            'pcw_rows'               => $rows,
            'pcw_total'              => $totalRows,
            'pcw_total_pages'        => $totalPages,
            'pcw_current_page'       => $page,
            'pcw_page_size'          => $this->pageSize,
            'pcw_filters'            => $filters,
            'pcw_order_by'           => $orderBy,
            'pcw_order_way'          => $orderWay,
            'pcw_shops'              => $shops,
            'pcw_languages'          => $languages,
            'pcw_status_list'        => RequestStatus::all(),
            'pcw_status_colors'      => $this->getStatusColors(),
            'pcw_eligibility_list'   => array(
                'manual_review', 'within_period', 'outside_period',
                'no_delivery_date', 'not_delivered', 'excluded_goods',
                'digital_consumed', 'service_performed', 'order_not_found',
                'order_mismatch', 'no_reference',
            ),
            'pcw_eligibility_colors' => $this->getEligibilityColors(),
            'pcw_detail_base_url'    => $baseUrl,
            'pcw_admin_shop_id'      => $adminShopId,
            'pcw_list_url'           => $baseUrl,
        ));

        return $this->module->display($this->module->getLocalPath(), 'views/templates/admin/requests_list.tpl');
    }

    /**
     * Render detail page.
     *
     * @param int $idRequest
     *
     * @return string
     */
    protected function renderDetail($idRequest)
    {
        $adminShopId = $this->getAdminShopId();
        $db          = Db::getInstance();

        $shopCond = ($adminShopId > 0) ? ' AND r.`id_shop` = ' . (int) $adminShopId : '';

        $request = $db->getRow(
            'SELECT r.*, s.`name` AS shop_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request` r
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = r.`id_shop`
             WHERE r.`id_pcwithdrawal_request` = ' . (int) $idRequest . $shopCond . '
             LIMIT 1'
        );

        if (!$request) {
            $this->errors[] = $this->module->l('Request not found or access denied.', 'AdminPcWithdrawalRequestsController');
            return $this->renderList();
        }

        $this->currentRequest = $request;

        $items = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_item`
             WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
             ORDER BY `id_pcwithdrawal_item` ASC'
        ) ?: array();

        $events = $db->executeS(
            'SELECT e.*, CONCAT(emp.`firstname`, \' \', emp.`lastname`) AS employee_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_event` e
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` emp ON emp.`id_employee` = e.`id_employee`
             WHERE e.`id_pcwithdrawal_request` = ' . (int) $idRequest . '
             ORDER BY e.`created_at_utc` DESC'
        ) ?: array();

        $mailLog = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_mail_log`
             WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
             ORDER BY `created_at_utc` DESC'
        ) ?: array();

        $currentStatus = (string) $request['status_code'];
        $allowedNextStatuses = StatusTransitionMap::allowedFrom($currentStatus);

        $reasonsRequired = array();
        foreach ($allowedNextStatuses as $next) {
            $reasonsRequired[$next] = StatusTransitionMap::requiresReason($currentStatus, $next);
        }

        $actionUrl = $this->context->link->getAdminLink('AdminPcWithdrawalRequests');

        $this->context->smarty->assign(array(
            'pcw_request'                    => $request,
            'pcw_items'                      => $items,
            'pcw_events'                     => $events,
            'pcw_mail_log'                   => $mailLog,
            'pcw_allowed_next_statuses'      => $allowedNextStatuses,
            'pcw_transition_reasons_required' => $reasonsRequired,
            'pcw_transition_reasons_json'    => json_encode($reasonsRequired),
            'pcw_action_url'                 => $actionUrl,
            'pcw_status_colors'              => $this->getStatusColors(),
            'pcw_eligibility_colors'         => $this->getEligibilityColors(),
            'pcw_list_url'                   => $this->context->link->getAdminLink('AdminPcWithdrawalRequests'),
            'pcw_current_employee_name'      => $this->context->employee->firstname . ' ' . $this->context->employee->lastname,
        ));

        return $this->module->display($this->module->getLocalPath(), 'views/templates/admin/request_detail.tpl');
    }

    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        $idRequest = (int) Tools::getValue('id_pcwithdrawal_request');
        if ($idRequest) {
            $this->content .= $this->renderDetail($idRequest);
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
        $action = Tools::getValue('pcw_action');

        switch ($action) {
            case 'changeStatus':
                $this->processChangeStatus();
                break;
            case 'addNote':
                $this->processAddNote();
                break;
            case 'resendAcknowledgement':
                $this->processResendAcknowledgement();
                break;
            case 'linkOrder':
                $this->processLinkOrder();
                break;
            case 'exportCsv':
                $this->processExportCsv();
                break;
            case 'updateTracking':
                $this->processUpdateTracking();
                break;
        }

        if ('exportEvidence' === Tools::getValue('action')) {
            $this->processExportEvidence();
        }

        parent::postProcess();
    }

    /**
     * Process status change.
     */
    protected function processChangeStatus()
    {
        $idRequest      = (int) Tools::getValue('id_pcwithdrawal_request');
        $newStatus      = (string) Tools::getValue('new_status', '');
        $reason         = (string) Tools::getValue('reason', '');
        $notifyCustomer = (bool) Tools::getValue('notify_customer', false);
        $adminShopId    = $this->getAdminShopId();
        $db             = Db::getInstance();

        if (!$idRequest || !RequestStatus::isValid($newStatus)) {
            $this->errors[] = $this->module->l('Invalid request or status.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $row = $db->getRow(
            'SELECT `id_shop`, `status_code`, `id_lang`, `consumer_email`,
                    `consumer_firstname`, `public_reference`
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest
            . ($adminShopId > 0 ? ' AND `id_shop` = ' . $adminShopId : '')
            . ' LIMIT 1'
        );

        if (!$row) {
            $this->errors[] = $this->module->l('Request not found.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $idShop        = (int) $row['id_shop'];
        $currentStatus = (string) $row['status_code'];

        if (!StatusTransitionMap::isAllowed($currentStatus, $newStatus)) {
            $this->errors[] = $this->module->l('Invalid status transition.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        if (StatusTransitionMap::requiresReason($currentStatus, $newStatus) && '' === trim($reason)) {
            $this->errors[] = $this->module->l('A reason is required for this status change.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $updated = (bool) $db->update(
            'pcwithdrawal_request',
            array(
                'status_code' => pSQL($newStatus),
                'date_upd'    => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . $idRequest . ' AND `id_shop` = ' . $idShop
        );

        if (!$updated) {
            $this->errors[] = $this->module->l('Failed to update status.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $this->logEvent($idRequest, $idShop, 'status_changed', array(
            'previous_status' => $currentStatus,
            'new_status'      => $newStatus,
            'reason'          => $reason,
        ));

        Hook::exec('actionPcWithdrawalStatusChanged', array(
            'request_id'  => $idRequest,
            'id_shop'     => $idShop,
            'prev_status' => $currentStatus,
            'new_status'  => $newStatus,
            'id_employee' => (int) $this->context->employee->id,
            'reason'      => $reason,
        ));

        if ($notifyCustomer && (int) Configuration::get('PCWITHDRAWAL_CUSTOMER_EMAILS', null, null, $idShop)) {
            $variables = array(
                'request_reference'  => $row['public_reference'],
                'new_status'         => $newStatus,
                'reason'             => $reason,
                'shop_name'          => Configuration::get('PS_SHOP_NAME', null, null, $idShop),
                'customer_firstname' => $row['consumer_firstname'],
                'recipient_email'    => $row['consumer_email'],
            );
            $this->sendTemplateMail(
                'customer_status_update',
                $idRequest,
                $idShop,
                (int) $row['id_lang'],
                $row['consumer_email'],
                $variables
            );
        }

        $this->confirmations[] = $this->module->l('Status updated successfully.', 'AdminPcWithdrawalRequestsController');
    }

    /**
     * Process add internal note.
     */
    protected function processAddNote()
    {
        $idRequest   = (int) Tools::getValue('id_pcwithdrawal_request');
        $note        = (string) Tools::getValue('note', '');
        $adminShopId = $this->getAdminShopId();

        if (!$idRequest || '' === trim($note)) {
            $this->errors[] = $this->module->l('Note cannot be empty.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $db  = Db::getInstance();
        $row = $db->getRow(
            'SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest
            . ($adminShopId > 0 ? ' AND `id_shop` = ' . $adminShopId : '')
            . ' LIMIT 1'
        );

        if (!$row) {
            $this->errors[] = $this->module->l('Request not found.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $this->logEvent($idRequest, (int) $row['id_shop'], 'admin_note', array('note' => $note));

        $this->confirmations[] = $this->module->l('Note added.', 'AdminPcWithdrawalRequestsController');
    }

    /**
     * Resend acknowledgement email.
     */
    protected function processResendAcknowledgement()
    {
        $idRequest   = (int) Tools::getValue('id_pcwithdrawal_request');
        $adminShopId = $this->getAdminShopId();
        $db          = Db::getInstance();

        $row = $db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest
            . ($adminShopId > 0 ? ' AND `id_shop` = ' . $adminShopId : '')
            . ' LIMIT 1'
        );

        if (!$row) {
            $this->errors[] = $this->module->l('Request not found.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $variables = array(
            'request_reference'  => $row['public_reference'],
            'submitted_at'       => $row['submitted_at_utc'],
            'customer_firstname' => $row['consumer_firstname'],
            'customer_lastname'  => $row['consumer_lastname'],
            'withdrawal_scope'   => $row['scope_code'],
            'shop_name'          => Configuration::get('PS_SHOP_NAME', null, null, (int) $row['id_shop']),
            'order_reference'    => (string) $row['order_reference'],
            'recipient_email'    => $row['consumer_email'],
        );

        $sent = $this->sendTemplateMail(
            'customer_acknowledgement',
            $idRequest,
            (int) $row['id_shop'],
            (int) $row['id_lang'],
            $row['consumer_email'],
            $variables
        );

        if ($sent) {
            $this->confirmations[] = $this->module->l('Acknowledgement resent.', 'AdminPcWithdrawalRequestsController');
        } else {
            $this->errors[] = $this->module->l('Failed to resend acknowledgement.', 'AdminPcWithdrawalRequestsController');
        }
    }

    /**
     * Link an order to an unlinked request.
     */
    protected function processLinkOrder()
    {
        $idRequest   = (int) Tools::getValue('id_pcwithdrawal_request');
        $orderRef    = trim((string) Tools::getValue('link_order_reference', ''));
        $forceLink   = (bool) Tools::getValue('force_link', false);
        $adminShopId = $this->getAdminShopId();
        $db          = Db::getInstance();

        if (!$idRequest || '' === $orderRef) {
            $this->errors[] = $this->module->l('Order reference required.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $request = $db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest
            . ($adminShopId > 0 ? ' AND `id_shop` = ' . $adminShopId : '')
            . ' LIMIT 1'
        );

        if (!$request) {
            $this->errors[] = $this->module->l('Request not found.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $order = $db->getRow(
            'SELECT `id_order`, `id_customer`, `id_shop`, `reference`
             FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `reference` = \'' . pSQL($orderRef) . '\'
               AND `id_shop` = ' . (int) $request['id_shop'] . '
             LIMIT 1'
        );

        if (!$order) {
            $this->errors[] = $this->module->l('Order not found in this shop.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        if (!$forceLink
            && (int) $request['id_customer']
            && (int) $order['id_customer'] !== (int) $request['id_customer']
        ) {
            $this->errors[] = $this->module->l(
                'Order customer does not match the request submitter. Check "Force link" to override.',
                'AdminPcWithdrawalRequestsController'
            );
            return;
        }

        $db->update(
            'pcwithdrawal_request',
            array(
                'id_order'        => (int) $order['id_order'],
                'order_reference' => pSQL($orderRef),
                'matched_at'      => gmdate('Y-m-d H:i:s'),
                'date_upd'        => date('Y-m-d H:i:s'),
            ),
            '`id_pcwithdrawal_request` = ' . $idRequest . ' AND `id_shop` = ' . (int) $request['id_shop']
        );

        $this->logEvent($idRequest, (int) $request['id_shop'], 'order_linked', array(
            'id_order'        => (int) $order['id_order'],
            'order_reference' => $orderRef,
            'forced'          => (int) $forceLink,
        ));

        Hook::exec('actionPcWithdrawalOrderLinked', array(
            'request_id' => $idRequest,
            'id_shop'    => (int) $request['id_shop'],
            'id_order'   => (int) $order['id_order'],
        ));

        $this->confirmations[] = $this->module->l('Order linked successfully.', 'AdminPcWithdrawalRequestsController');
    }

    /**
     * Log tracking/refund refs as audit event.
     */
    protected function processUpdateTracking()
    {
        $idRequest   = (int) Tools::getValue('id_pcwithdrawal_request');
        $trackingRef = (string) Tools::getValue('tracking_ref', '');
        $refundRef   = (string) Tools::getValue('refund_ref', '');
        $adminShopId = $this->getAdminShopId();
        $db          = Db::getInstance();

        $row = $db->getRow(
            'SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest
            . ($adminShopId > 0 ? ' AND `id_shop` = ' . $adminShopId : '')
            . ' LIMIT 1'
        );

        if (!$row) {
            $this->errors[] = $this->module->l('Request not found.', 'AdminPcWithdrawalRequestsController');
            return;
        }

        $this->logEvent($idRequest, (int) $row['id_shop'], 'tracking_updated', array(
            'tracking_ref' => $trackingRef,
            'refund_ref'   => $refundRef,
        ));

        $this->confirmations[] = $this->module->l('Tracking information logged.', 'AdminPcWithdrawalRequestsController');
    }

    /**
     * Output printable HTML evidence document.
     */
    protected function processExportEvidence()
    {
        $idRequest   = (int) Tools::getValue('id_pcwithdrawal_request');
        $adminShopId = $this->getAdminShopId();
        $db          = Db::getInstance();
        $shopCond    = ($adminShopId > 0) ? ' AND r.`id_shop` = ' . $adminShopId : '';

        $request = $db->getRow(
            'SELECT r.*, s.`name` AS shop_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request` r
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = r.`id_shop`
             WHERE r.`id_pcwithdrawal_request` = ' . $idRequest . $shopCond . '
             LIMIT 1'
        );

        if (!$request) {
            die('Request not found or access denied.');
        }

        $items = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_item`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest . '
             ORDER BY `id_pcwithdrawal_item` ASC'
        ) ?: array();

        $events = $db->executeS(
            'SELECT e.*, CONCAT(emp.`firstname`, \' \', emp.`lastname`) AS employee_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_event` e
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` emp ON emp.`id_employee` = e.`id_employee`
             WHERE e.`id_pcwithdrawal_request` = ' . $idRequest . '
             ORDER BY e.`created_at_utc` ASC'
        ) ?: array();

        $mailLog = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_mail_log`
             WHERE `id_pcwithdrawal_request` = ' . $idRequest . '
             ORDER BY `created_at_utc` ASC'
        ) ?: array();

        $employeeName = $this->context->employee->firstname . ' ' . $this->context->employee->lastname;
        $printedAt    = date('Y-m-d H:i:s');

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="evidence_' . $request['public_reference'] . '.html"');

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<title>Withdrawal Evidence &mdash; ' . htmlspecialchars($request['public_reference'], ENT_QUOTES) . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;font-size:13px;color:#222;margin:24px}';
        echo 'h1{color:#333;border-bottom:2px solid #333;padding-bottom:6px}';
        echo 'h2{color:#555;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:24px}';
        echo 'table{border-collapse:collapse;width:100%;margin-bottom:16px}';
        echo 'th,td{border:1px solid #ccc;padding:5px 9px;text-align:left;vertical-align:top}';
        echo 'th{background:#f4f4f4;font-weight:bold}';
        echo '.footer{margin-top:40px;border-top:1px solid #ccc;padding-top:8px;font-size:11px;color:#888}';
        echo '</style></head><body>';

        echo '<h1>EU Withdrawal Function &mdash; Evidence Document</h1>';
        echo '<p><strong>Module:</strong> pcwithdrawal &nbsp;|&nbsp; <strong>Shop:</strong> '
            . htmlspecialchars((string) $request['shop_name'], ENT_QUOTES) . '</p>';

        echo '<h2>1. Withdrawal Declaration (Immutable)</h2><table>';
        $declFields = array(
            'Public Reference'   => $request['public_reference'],
            'Submitted At (UTC)' => $request['submitted_at_utc'],
            'Consumer Name'      => $request['consumer_firstname'] . ' ' . $request['consumer_lastname'],
            'Consumer Email'     => $request['consumer_email'],
            'Order Reference'    => (string) $request['order_reference'],
            'Scope'              => $request['scope_code'],
            'Contract Type'      => $request['contract_type'],
            'Source'             => $request['source_code'],
            'Status'             => $request['status_code'],
            'Eligibility Code'   => $request['eligibility_code'],
            'Eligibility Reason' => (string) $request['eligibility_reason'],
            'Deadline Start'     => (string) $request['deadline_start_at'],
            'Deadline End'       => (string) $request['deadline_end_at'],
            'Customer Statement' => (string) $request['customer_statement'],
        );
        foreach ($declFields as $label => $value) {
            echo '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>'
                . '<td>' . nl2br(htmlspecialchars((string) $value, ENT_QUOTES)) . '</td></tr>';
        }
        echo '</table>';

        if ($items) {
            echo '<h2>2. Selected Items</h2><table>';
            echo '<tr><th>Product</th><th>Reference</th><th>Attribute</th><th>Qty Ordered</th>'
                . '<th>Qty Withdrawn</th><th>Unit Price</th><th>Total</th><th>Currency</th><th>Exception</th></tr>';
            foreach ($items as $item) {
                echo '<tr>'
                    . '<td>' . htmlspecialchars($item['product_name'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($item['product_reference'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars((string) $item['attribute_name'], ENT_QUOTES) . '</td>'
                    . '<td>' . (int) $item['quantity_ordered'] . '</td>'
                    . '<td>' . (int) $item['quantity_withdrawn'] . '</td>'
                    . '<td>' . htmlspecialchars($item['unit_price_tax_incl'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($item['total_price_tax_incl'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($item['currency_iso'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars((string) $item['exception_code'], ENT_QUOTES) . '</td>'
                    . '</tr>';
            }
            echo '</table>';
        }

        echo '<h2>3. Event Timeline</h2><table>';
        echo '<tr><th>Date (UTC)</th><th>Event</th><th>Actor</th><th>Status Change</th><th>Notes</th></tr>';
        foreach ($events as $ev) {
            $notes = '';
            if (!empty($ev['event_payload'])) {
                $dec = json_decode($ev['event_payload'], true);
                if (is_array($dec) && isset($dec['note'])) {
                    $notes = htmlspecialchars($dec['note'], ENT_QUOTES);
                } elseif (is_array($dec) && isset($dec['reason'])) {
                    $notes = htmlspecialchars($dec['reason'], ENT_QUOTES);
                }
            }
            $statusChange = '';
            if (!empty($ev['previous_status']) || !empty($ev['new_status'])) {
                $statusChange = htmlspecialchars((string) $ev['previous_status'], ENT_QUOTES)
                    . ' &rarr; '
                    . htmlspecialchars((string) $ev['new_status'], ENT_QUOTES);
            }
            echo '<tr>'
                . '<td>' . htmlspecialchars($ev['created_at_utc'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($ev['event_code'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $ev['employee_name'], ENT_QUOTES)
                    . ' (' . htmlspecialchars($ev['actor_type'], ENT_QUOTES) . ')</td>'
                . '<td>' . $statusChange . '</td>'
                . '<td>' . $notes . '</td>'
                . '</tr>';
        }
        echo '</table>';

        if ($mailLog) {
            echo '<h2>4. Mail Log Summary</h2><table>';
            echo '<tr><th>Date (UTC)</th><th>Template</th><th>Recipient</th><th>Status</th></tr>';
            foreach ($mailLog as $ml) {
                echo '<tr>'
                    . '<td>' . htmlspecialchars($ml['created_at_utc'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($ml['template_code'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($ml['recipient'], ENT_QUOTES) . '</td>'
                    . '<td>' . htmlspecialchars($ml['send_status'], ENT_QUOTES) . '</td>'
                    . '</tr>';
            }
            echo '</table>';
        }

        echo '<div class="footer">Printed by: ' . htmlspecialchars($employeeName, ENT_QUOTES)
            . ' &nbsp;|&nbsp; Date: ' . htmlspecialchars($printedAt, ENT_QUOTES)
            . ' UTC &nbsp;|&nbsp; Module: pcwithdrawal</div>';
        echo '</body></html>';
        exit;
    }

    /**
     * Export current filter result as CSV.
     */
    protected function processExportCsv()
    {
        $adminShopId = $this->getAdminShopId();

        $filters = array(
            'public_reference' => Tools::getValue('filter_public_reference', ''),
            'order_reference'  => Tools::getValue('filter_order_reference', ''),
            'consumer_email'   => Tools::getValue('filter_consumer_email', ''),
            'status_code'      => Tools::getValue('filter_status_code', ''),
            'eligibility_code' => Tools::getValue('filter_eligibility_code', ''),
            'id_shop'          => Tools::getValue('filter_id_shop', ''),
            'date_from'        => Tools::getValue('filter_date_from', ''),
            'date_to'          => Tools::getValue('filter_date_to', ''),
            'has_order'        => Tools::getValue('filter_has_order', ''),
        );

        $whereStr = $this->buildListWhere($filters, $adminShopId);
        $db       = Db::getInstance();

        $rows = $db->executeS(
            'SELECT r.*, s.`name` AS shop_name
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_request` r
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = r.`id_shop`
             WHERE ' . $whereStr . '
             ORDER BY r.`submitted_at_utc` DESC
             LIMIT 5000'
        ) ?: array();

        $filename = 'withdrawal_requests_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, array(
            'ID', 'Public Reference', 'Shop', 'Order Reference',
            'Firstname', 'Lastname', 'Email', 'Scope', 'Status',
            'Eligibility', 'Submitted At (UTC)', 'Deadline End',
            'Acknowledgement Status',
        ));

        foreach ($rows as $row) {
            fputcsv($out, array(
                $row['id_pcwithdrawal_request'],
                $row['public_reference'],
                $row['shop_name'],
                (string) $row['order_reference'],
                $row['consumer_firstname'],
                $row['consumer_lastname'],
                $row['consumer_email'],
                $row['scope_code'],
                $row['status_code'],
                $row['eligibility_code'],
                $row['submitted_at_utc'],
                (string) $row['deadline_end_at'],
                $row['acknowledgement_status'],
            ));
        }

        fclose($out);
        exit;
    }

    /**
     * Insert an audit event row.
     *
     * @param int    $idRequest
     * @param int    $idShop
     * @param string $eventCode
     * @param array  $payload
     */
    protected function logEvent($idRequest, $idShop, $eventCode, array $payload = array())
    {
        $db         = Db::getInstance();
        $idEmployee = (int) $this->context->employee->id;
        $jsonPayload = json_encode($payload);
        $now        = gmdate('Y-m-d H:i:s');

        $prevHash = $db->getValue(
            'SELECT `event_hash`
             FROM `' . _DB_PREFIX_ . 'pcwithdrawal_event`
             WHERE `id_pcwithdrawal_request` = ' . (int) $idRequest . '
             ORDER BY `id_pcwithdrawal_event` DESC
             LIMIT 1'
        );

        $hashData  = $idRequest . $eventCode . $now . $jsonPayload . $prevHash;
        $eventHash = hash('sha256', $hashData);

        $prevStatus = isset($payload['previous_status']) ? pSQL($payload['previous_status']) : null;
        $newStatus  = isset($payload['new_status']) ? pSQL($payload['new_status']) : null;

        $db->insert('pcwithdrawal_event', array(
            'id_pcwithdrawal_request' => (int) $idRequest,
            'id_shop'                 => (int) $idShop,
            'event_code'              => pSQL($eventCode),
            'id_employee'             => $idEmployee ?: null,
            'actor_type'              => 'employee',
            'previous_status'         => $prevStatus,
            'new_status'              => $newStatus,
            'event_payload'           => pSQL($jsonPayload, true),
            'created_at_utc'          => pSQL($now),
            'previous_event_hash'     => $prevHash ? pSQL((string) $prevHash) : null,
            'event_hash'              => pSQL($eventHash),
        ));
    }

    /**
     * Minimal template mail send helper.
     *
     * @param string $templateCode
     * @param int    $idRequest
     * @param int    $idShop
     * @param int    $idLang
     * @param string $toEmail
     * @param array  $variables
     *
     * @return bool
     */
    protected function sendTemplateMail($templateCode, $idRequest, $idShop, $idLang, $toEmail, array $variables)
    {
        if (!Validate::isEmail($toEmail)) {
            return false;
        }

        $db  = Db::getInstance();
        $tpl = $db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_template`
             WHERE `id_shop` = ' . (int) $idShop . '
               AND `id_lang` = ' . (int) $idLang . '
               AND `template_code` = \'' . pSQL($templateCode) . '\'
               AND `is_enabled` = 1
             LIMIT 1'
        );

        if (!$tpl) {
            return false;
        }

        $subject = (string) $tpl['subject'];
        $html    = (string) $tpl['html_content'];
        $text    = (string) $tpl['text_content'];

        foreach ($variables as $k => $v) {
            if (is_scalar($v) || null === $v) {
                $ph      = '{{' . $k . '}}';
                $subject = str_replace($ph, (string) $v, $subject);
                $html    = str_replace($ph, (string) $v, $html);
                $text    = str_replace($ph, (string) $v, $text);
            }
        }

        $fromEmail = (string) Configuration::get('PCWITHDRAWAL_SENDER_EMAIL', null, null, $idShop);
        if (!$fromEmail || !Validate::isEmail($fromEmail)) {
            $fromEmail = (string) Configuration::get('PS_SHOP_EMAIL', null, null, $idShop);
        }
        $fromName = (string) Configuration::get('PCWITHDRAWAL_SENDER_NAME', null, null, $idShop);
        if (!$fromName) {
            $fromName = (string) Configuration::get('PS_SHOP_NAME', null, null, $idShop);
        }

        $sent = (bool) Mail::Send(
            (int) $idLang,
            'pcwithdrawal_generic',
            $subject,
            array('{message_html}' => $html, '{message_txt}' => $text),
            $toEmail,
            '',
            $fromEmail,
            $fromName,
            null,
            null,
            _PS_MODULE_DIR_ . 'pcwithdrawal/mails/',
            false,
            (int) $idShop
        );

        $db->insert('pcwithdrawal_mail_log', array(
            'id_pcwithdrawal_request' => $idRequest > 0 ? (int) $idRequest : null,
            'id_shop'                 => (int) $idShop,
            'id_lang'                 => (int) $idLang,
            'template_code'           => pSQL($templateCode),
            'recipient'               => pSQL($toEmail),
            'subject_snapshot'        => pSQL($subject),
            'html_snapshot'           => pSQL($html, true),
            'text_snapshot'           => pSQL($text, true),
            'send_status'             => $sent ? 'sent' : 'failed',
            'ps_mail_return'          => (int) $sent,
            'created_at_utc'          => gmdate('Y-m-d H:i:s'),
        ));

        return $sent;
    }

    /**
     * @return array
     */
    protected function getStatusColors()
    {
        return array(
            'submitted'             => 'default',
            'acknowledged'          => 'info',
            'matched'               => 'primary',
            'under_review'          => 'warning',
            'accepted'              => 'success',
            'rejected'              => 'danger',
            'reimbursed'            => 'success',
            'withdrawn_by_consumer' => 'default',
            'closed'                => 'default',
        );
    }

    /**
     * @return array
     */
    protected function getEligibilityColors()
    {
        return array(
            'within_period'     => 'success',
            'outside_period'    => 'danger',
            'excluded_goods'    => 'danger',
            'digital_consumed'  => 'danger',
            'service_performed' => 'danger',
            'manual_review'     => 'warning',
            'no_delivery_date'  => 'warning',
            'not_delivered'     => 'warning',
            'order_not_found'   => 'danger',
            'order_mismatch'    => 'danger',
            'no_reference'      => 'warning',
        );
    }
}
