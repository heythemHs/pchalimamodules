<?php
/**
 * Service — resolves the correct email template with a 5-level fallback chain.
 * Falls back to static built-in module templates if no DB record exists.
 *
 * @namespace PerpetualCode\PcWithdrawal\Service
 */

namespace PerpetualCode\PcWithdrawal\Service;

use PerpetualCode\PcWithdrawal\Repository\EmailTemplateRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailTemplateService
{
    /** @var EmailTemplateRepository */
    private $repo;

    /**
     * @param EmailTemplateRepository $repo
     */
    public function __construct(EmailTemplateRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Resolve the best-matching template using the fallback chain.
     *
     * Fallback order:
     *   1. Exact shop + lang
     *   2. Exact shop + shop default lang
     *   3. Global (id_shop=0) + lang
     *   4. Global (id_shop=0) + English lang ID
     *   5. Static built-in module template from mails/ directory
     *
     * @param string $templateCode
     * @param int    $idShop
     * @param int    $idLang
     *
     * @return array|null Array: ['template' => [...row...], 'fallback_level' => 1-5] or null if not found.
     */
    public function resolve($templateCode, $idShop, $idLang)
    {
        $idShop = (int) $idShop;
        $idLang = (int) $idLang;

        // Level 1: Exact shop + lang
        $template = $this->repo->findByCode($idShop, $idLang, $templateCode);
        if ($template) {
            return array('template' => $template, 'fallback_level' => 1);
        }

        // Level 2: Exact shop + shop default lang
        $shopDefaultLang = (int) \Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);
        if ($shopDefaultLang && $shopDefaultLang !== $idLang) {
            $template = $this->repo->findByCode($idShop, $shopDefaultLang, $templateCode);
            if ($template) {
                return array('template' => $template, 'fallback_level' => 2);
            }
        }

        // Level 3: Global (id_shop=0) + lang
        $template = $this->repo->findByCode(0, $idLang, $templateCode);
        if ($template) {
            return array('template' => $template, 'fallback_level' => 3);
        }

        // Level 4: Global (id_shop=0) + English lang ID
        $englishLangId = $this->getEnglishLangId();
        if ($englishLangId && $englishLangId !== $idLang) {
            $template = $this->repo->findByCode(0, $englishLangId, $templateCode);
            if ($template) {
                return array('template' => $template, 'fallback_level' => 4);
            }
        }

        // Level 5: Static built-in module template
        $langIso      = $this->getLangIso($idLang);
        $builtinTpl   = $this->getBuiltinTemplate($templateCode, $langIso);
        if ($builtinTpl) {
            return array('template' => $builtinTpl, 'fallback_level' => 5);
        }

        return null;
    }

    /**
     * Read a built-in template from the module's mails/ directory.
     * Falls back to mails/en/ if the requested language ISO directory is not found.
     *
     * @param string $templateCode
     * @param string $langIso      e.g. 'fr', 'en'
     *
     * @return array|null Array with keys: subject, html_content, text_content; or null.
     */
    public function getBuiltinTemplate($templateCode, $langIso)
    {
        $moduleDir = _PS_MODULE_DIR_ . 'pcwithdrawal/mails/';
        $langIso   = preg_replace('/[^a-z]/i', '', strtolower((string) $langIso));

        $htmlPath = $moduleDir . $langIso . '/' . $templateCode . '.html';
        $txtPath  = $moduleDir . $langIso . '/' . $templateCode . '.txt';
        $sbjPath  = $moduleDir . $langIso . '/' . $templateCode . '.subject';

        // Fallback to English if lang-specific files do not exist
        if (!file_exists($htmlPath)) {
            $htmlPath = $moduleDir . 'en/' . $templateCode . '.html';
            $txtPath  = $moduleDir . 'en/' . $templateCode . '.txt';
            $sbjPath  = $moduleDir . 'en/' . $templateCode . '.subject';
        }

        if (!file_exists($htmlPath)) {
            return null;
        }

        $subject     = file_exists($sbjPath) ? trim(file_get_contents($sbjPath)) : $templateCode;
        $htmlContent = file_get_contents($htmlPath);
        $textContent = file_exists($txtPath) ? file_get_contents($txtPath) : '';

        if (false === $htmlContent) {
            return null;
        }

        return array(
            'template_code' => $templateCode,
            'subject'       => $subject,
            'html_content'  => $htmlContent,
            'text_content'  => $textContent,
            'is_enabled'    => 1,
            'version'       => 0,
        );
    }

    /**
     * Get the ID of the English language in PS.
     *
     * @return int|null
     */
    private function getEnglishLangId()
    {
        $id = \Db::getInstance()->getValue(
            'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang`
             WHERE `iso_code` = \'en\'
             LIMIT 1'
        );

        return $id ? (int) $id : null;
    }

    /**
     * Get the ISO code for a language ID.
     *
     * @param int $idLang
     *
     * @return string
     */
    private function getLangIso($idLang)
    {
        $iso = \Db::getInstance()->getValue(
            'SELECT `iso_code` FROM `' . _DB_PREFIX_ . 'lang`
             WHERE `id_lang` = ' . (int) $idLang . '
             LIMIT 1'
        );

        return $iso ? (string) $iso : 'en';
    }
}
