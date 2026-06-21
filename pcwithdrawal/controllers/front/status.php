<?php
/**
 * Front controller — public status tracking page.
 * Accessible via a tokenised link sent in the acknowledgement email.
 * Displays only public-facing events; never internal audit details.
 *
 * Security rules:
 *  - Requires both `ref` and `token` parameters.
 *  - Token is validated against a status-view token stored at submission.
 *  - Invalid or expired tokens produce a generic "not found" message.
 *  - Shop restriction enforced: never display data from another shop's reference.
 *  - Link lifetime enforced by PCWITHDRAWAL_STATUS_LINK_LIFETIME config (days).
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalStatusModuleFrontController extends ModuleFrontController
{
    /** @var array|null */
    public $requestData;

    /** @var array */
    public $events = array();

    /** @var bool */
    public $tokenValid = false;

    /** @var string */
    public $php_self = 'module';

    /** @var bool */
    public $auth = false;

    /** @var bool */
    public $guestAllowed = true;

    /** Public-facing status labels (translatable in template, codes here for lookup). */
    private static $publicStatusLabels = array(
        'submitted'            => 'Received',
        'acknowledged'         => 'Acknowledged',
        'matched'              => 'Under review',
        'under_review'         => 'Under review',
        'accepted'             => 'Accepted',
        'rejected'             => 'Rejected',
        'reimbursed'           => 'Reimbursed',
        'withdrawn_by_consumer' => 'Cancelled',
        'closed'               => 'Closed',
    );

    /**
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        $publicRef     = trim(Tools::getValue('ref', ''));
        $submittedToken = trim(Tools::getValue('token', ''));
        $idShop        = (int) $this->context->shop->id;

        // Sanitise ref
        if ('' !== $publicRef) {
            $publicRef = preg_replace('/[^A-Z0-9\-]/i', '', strtoupper($publicRef));
        }

        if ('' === $publicRef || '' === $submittedToken) {
            $this->renderNotFound();
            return;
        }

        // Load request by public reference + shop
        $container   = Pcwithdrawal::getServiceContainer();
        $requestRepo = $container['withdrawal_request_repository'];
        $request     = $requestRepo->findByPublicReference($publicRef, $idShop);

        if (!$request || (int) $request['id_shop'] !== $idShop) {
            $this->renderNotFound();
            return;
        }

        // Validate the status view token
        $storedToken = isset($request['status_view_token']) ? (string) $request['status_view_token'] : '';

        if ('' === $storedToken) {
            $this->renderNotFound();
            return;
        }

        // Constant-time comparison
        if (!hash_equals($storedToken, $submittedToken)) {
            $this->renderNotFound();
            return;
        }

        // Check link lifetime
        $lifetimeDays = (int) Configuration::get('PCWITHDRAWAL_STATUS_LINK_LIFETIME');
        if ($lifetimeDays < 1) {
            $lifetimeDays = 365;
        }

        $submittedAt  = isset($request['submitted_at_utc']) ? strtotime($request['submitted_at_utc']) : 0;
        $expiresAt    = $submittedAt + ($lifetimeDays * 86400);

        if ($expiresAt < time()) {
            $this->renderNotFound();
            return;
        }

        // Token valid and not expired
        $this->tokenValid  = true;
        $this->requestData = $request;

        // Load public events (non-internal only)
        $this->events = $this->loadPublicEvents((int) $request['id_pcwithdrawal_request'], $idShop, $container);

        // Determine public status label
        $statusCode  = isset($request['status_code']) ? (string) $request['status_code'] : '';
        $statusLabel = isset(self::$publicStatusLabels[$statusCode])
            ? self::$publicStatusLabels[$statusCode]
            : 'Processing';

        $this->context->smarty->assign(array(
            'pcwdl_request'      => $this->requestData,
            'pcwdl_events'       => $this->events,
            'pcwdl_status_label' => $statusLabel,
            'pcwdl_public_ref'   => htmlspecialchars($publicRef, ENT_QUOTES, 'UTF-8'),
            'pcwdl_token_valid'  => $this->tokenValid,
            'pcwdl_shop_name'    => Configuration::get('PS_SHOP_NAME'),
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/status.tpl');
    }

    /**
     * Load publicly visible events for a request.
     * Filters out any event with is_public = 0 (internal audit entries).
     *
     * @param int   $requestId
     * @param int   $idShop
     * @param array $container
     *
     * @return array
     */
    protected function loadPublicEvents($requestId, $idShop, array $container)
    {
        if (!isset($container['withdrawal_event_repository'])) {
            return array();
        }

        $eventRepo = $container['withdrawal_event_repository'];

        if (method_exists($eventRepo, 'findPublicByRequestId')) {
            return $eventRepo->findPublicByRequestId($requestId, $idShop);
        }

        // Fallback: raw query filtering public events
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'pcwithdrawal_event`
             WHERE `id_pcwithdrawal_request` = ' . (int) $requestId . '
               AND `id_shop` = ' . (int) $idShop . '
               AND `is_public` = 1
             ORDER BY `date_add` ASC'
        );

        return $rows ? $rows : array();
    }

    /**
     * Render the generic "not found" response.
     * Never reveals whether the reference is real.
     *
     * @return void
     */
    protected function renderNotFound()
    {
        $this->context->smarty->assign(array(
            'pcwdl_token_valid'  => false,
            'pcwdl_request'      => null,
            'pcwdl_events'       => array(),
            'pcwdl_status_label' => '',
            'pcwdl_public_ref'   => '',
            'pcwdl_shop_name'    => Configuration::get('PS_SHOP_NAME'),
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/status.tpl');
    }

    /**
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->registerStylesheet(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/css/front.css',
            array('media' => 'all', 'priority' => 200)
        );
    }
}
