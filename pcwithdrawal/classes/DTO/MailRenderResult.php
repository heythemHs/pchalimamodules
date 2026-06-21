<?php
/**
 * DTO — result of rendering an e-mail template.
 *
 * @namespace PerpetualCode\PcWithdrawal\DTO
 */

namespace PerpetualCode\PcWithdrawal\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MailRenderResult
{
    /** @var bool Whether the template was found and rendered. */
    public $success;

    /** @var string Rendered subject line. */
    public $subject;

    /** @var string Rendered HTML body. */
    public $htmlBody;

    /** @var string Rendered plain-text body. */
    public $textBody;

    /** @var string|null Error message if rendering failed. */
    public $errorMessage;

    /** @var string Template code that was used. */
    public $templateCode;

    /** @var int Language ID used for rendering. */
    public $idLang;

    public function __construct()
    {
        $this->success       = false;
        $this->subject       = '';
        $this->htmlBody      = '';
        $this->textBody      = '';
        $this->errorMessage  = null;
        $this->templateCode  = '';
        $this->idLang        = 0;
    }
}
