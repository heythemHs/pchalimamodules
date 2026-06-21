<?php
/**
 * Compatibility — translation helper bridging PS 1.7 and PS 8/9 translation APIs.
 *
 * @namespace PerpetualCode\PcWithdrawal\Compatibility
 */

namespace PerpetualCode\PcWithdrawal\Compatibility;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TranslationAdapter
{
    /** @var \Module */
    private $module;

    /**
     * @param \Module $module
     */
    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    /**
     * Translate a string using the module's translation system.
     * Compatible with PS 1.7 ($this->l()) as well as the newer
     * getTranslator() approach present in PS 8+.
     *
     * @param string $string  The source string.
     * @param string $domain  Optional domain/context (ignored on PS 1.7).
     *
     * @return string
     */
    public function trans($string, $domain = '')
    {
        // PS 8+ exposes getTranslator() on the module
        if (method_exists($this->module, 'getTranslator')) {
            try {
                $translator = $this->module->getTranslator();
                if ($translator && method_exists($translator, 'trans')) {
                    $translated = $translator->trans($string, array(), $domain ?: 'Modules.Pcwithdrawal.Admin');
                    if ($translated !== $string) {
                        return $translated;
                    }
                }
            } catch (\Exception $e) {
                // Fall through to classic l()
            }
        }

        // Classic PS 1.7 fallback
        return $this->module->l($string, $domain ?: 'pcwithdrawal');
    }
}
