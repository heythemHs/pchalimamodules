<?php
/**
 * DTO — data transfer object for a single line item within a withdrawal.
 *
 * @namespace PerpetualCode\PcWithdrawal\DTO
 */

namespace PerpetualCode\PcWithdrawal\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WithdrawalItemData
{
    /** @var int|null */
    public $idOrderDetail;

    /** @var int|null */
    public $idProduct;

    /** @var int|null */
    public $idProductAttribute;

    /** @var string */
    public $productName;

    /** @var string */
    public $productReference;

    /** @var string */
    public $attributeName;

    /** @var int */
    public $quantityOrdered;

    /** @var int */
    public $quantityWithdrawn;

    /** @var float */
    public $unitPriceTaxIncl;

    /** @var float */
    public $totalPriceTaxIncl;

    /** @var string ISO-4217 currency code */
    public $currencyIso;

    /** @var string|null exception code for this item */
    public $exceptionCode;

    public function __construct()
    {
        $this->idOrderDetail      = null;
        $this->idProduct          = null;
        $this->idProductAttribute = null;
        $this->productName        = '';
        $this->productReference   = '';
        $this->attributeName      = '';
        $this->quantityOrdered    = 0;
        $this->quantityWithdrawn  = 0;
        $this->unitPriceTaxIncl   = 0.0;
        $this->totalPriceTaxIncl  = 0.0;
        $this->currencyIso        = '';
        $this->exceptionCode      = null;
    }
}
