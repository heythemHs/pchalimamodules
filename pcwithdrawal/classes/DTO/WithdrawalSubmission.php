<?php
/**
 * DTO — data transfer object for a withdrawal form submission.
 *
 * @namespace PerpetualCode\PcWithdrawal\DTO
 */

namespace PerpetualCode\PcWithdrawal\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalSubmission
{
    /** @var int */
    public $idShop;

    /** @var int|null */
    public $idCustomer;

    /** @var int */
    public $idLang;

    /** @var string */
    public $orderReference;

    /** @var string */
    public $consumerFirstname;

    /** @var string */
    public $consumerLastname;

    /** @var string */
    public $consumerEmail;

    /** @var string */
    public $customerStatement;

    /** @var string Y-m-d format or empty */
    public $purchaseDateDeclared;

    /** @var string full|partial */
    public $scopeCode;

    /** @var string distance|off_premises */
    public $contractType;

    /** @var string front_online|front_paper|back_office */
    public $sourceCode;

    /** @var \PerpetualCode\PcWithdrawal\DTO\WithdrawalItemData[] */
    public $items;

    /** @var string UTC datetime string */
    public $submittedAtUtc;

    /** @var string timezone name */
    public $submittedTimezone;

    /** @var string local datetime string */
    public $submittedLocalAt;

    /** @var string client IP address */
    public $clientIp;

    public function __construct()
    {
        $this->idShop            = 0;
        $this->idCustomer        = null;
        $this->idLang            = 0;
        $this->orderReference    = '';
        $this->consumerFirstname = '';
        $this->consumerLastname  = '';
        $this->consumerEmail     = '';
        $this->customerStatement = '';
        $this->purchaseDateDeclared = '';
        $this->scopeCode         = 'full';
        $this->contractType      = 'distance';
        $this->sourceCode        = 'front_online';
        $this->items             = array();
        $this->submittedAtUtc    = '';
        $this->submittedTimezone = 'UTC';
        $this->submittedLocalAt  = '';
        $this->clientIp          = '';
    }
}
