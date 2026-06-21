<?php
/**
 * Front controller — post-submission success page.
 * Accepts the public reference in the URL, loads the request by that reference
 * (restricted to the current shop), and displays acknowledgement information.
 *
 * Security rules:
 *  - Only the public reference is accepted — never the internal request ID.
 *  - Reference is validated against the current shop ID before display.
 *  - Sensitive order details are not exposed in the URL or page.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalSuccessModuleFrontController extends ModuleFrontController
{
    /** @var array|null */
    public $requestData;

    /** @var string */
    public $php_self = 'module';

    /** @var bool */
    public $auth = false;

    /** @var bool */
    public $guestAllowed = true;

    /**
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        $publicRef = trim(Tools::getValue('ref', ''));
        $idShop    = (int) $this->context->shop->id;

        if ('' !== $publicRef) {
            $publicRef = preg_replace('/[^A-Z0-9\-]/i', '', strtoupper($publicRef));
        }

        if ('' !== $publicRef) {
            $container   = Pcwithdrawal::getServiceContainer();
            $requestRepo = $container['withdrawal_request_repository'];
            $request     = $requestRepo->findByPublicReference($publicRef, $idShop);

            if ($request && (int) $request['id_shop'] === $idShop) {
                $this->requestData = $request;
            }
        }

        // Determine timezone label for display
        $tz = isset($this->requestData['submitted_timezone'])
            ? (string) $this->requestData['submitted_timezone']
            : 'UTC';

        // Generate status URL if request is loaded
        $statusUrl = '';
        if ($this->requestData && isset($this->requestData['status_view_token'])) {
            $statusUrl = $this->context->link->getModuleLink('pcwithdrawal', 'status', array(
                'ref'   => $publicRef,
                'token' => (string) $this->requestData['status_view_token'],
            ));
        }

        $this->context->smarty->assign(array(
            'pcwdl_request'    => $this->requestData,
            'pcwdl_public_ref' => htmlspecialchars($publicRef, ENT_QUOTES, 'UTF-8'),
            'pcwdl_timezone'   => htmlspecialchars($tz, ENT_QUOTES, 'UTF-8'),
            'pcwdl_status_url' => $statusUrl,
            'pcwdl_shop_name'  => Configuration::get('PS_SHOP_NAME'),
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/success.tpl');
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

        $this->registerJavascript(
            'pcwithdrawal-front',
            'modules/pcwithdrawal/views/js/front.js',
            array('position' => 'bottom', 'priority' => 200)
        );
    }
}
