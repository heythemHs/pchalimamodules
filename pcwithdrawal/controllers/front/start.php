<?php
/**
 * Front controller — withdrawal start / landing page.
 * Shows two identification paths for guests; redirects logged-in customers.
 *
 * @author    Perpetual Code <digital.perpetualcode@gmail.com>
 * @copyright 2024 Perpetual Code
 * @license   AFL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PcWithdrawalStartModuleFrontController extends ModuleFrontController
{
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

        if (!(int) Configuration::get('PCWITHDRAWAL_ENABLED')) {
            $this->errors[] = $this->module->l('The withdrawal form is currently unavailable.', 'start');
            $this->setTemplate('module:pcwithdrawal/views/templates/front/start.tpl');
            return;
        }

        // Logged-in customer → skip landing and go straight to their order list.
        if ($this->context->customer->isLogged()) {
            Tools::redirect(
                $this->context->link->getModuleLink('pcwithdrawal', 'identify', array('mode' => 'customer'))
            );
            return;
        }

        $guestUrl = $this->context->link->getModuleLink('pcwithdrawal', 'identify', array('mode' => 'guest'));
        $noRefUrl = $this->context->link->getModuleLink('pcwithdrawal', 'identify', array('mode' => 'noref'));

        $this->context->smarty->assign(array(
            'pcwdl_guest_url' => $guestUrl,
            'pcwdl_noref_url' => $noRefUrl,
            'pcwdl_is_logged' => false,
        ));

        $this->setTemplate('module:pcwithdrawal/views/templates/front/start.tpl');
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
