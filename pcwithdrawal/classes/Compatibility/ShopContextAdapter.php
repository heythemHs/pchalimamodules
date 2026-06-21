<?php
/**
 * Compatibility — shop context helper for multistore-safe ID resolution.
 *
 * @namespace PerpetualCode\PcWithdrawal\Compatibility
 */

namespace PerpetualCode\PcWithdrawal\Compatibility;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopContextAdapter
{
    /**
     * Get the current shop ID.
     * Always returns an int >= 1.
     *
     * @return int
     */
    public function getShopId()
    {
        $idShop = (int) \Context::getContext()->shop->id;

        return $idShop > 0 ? $idShop : (int) \Configuration::get('PS_SHOP_DEFAULT');
    }

    /**
     * Get the current language ID from context.
     *
     * @return int
     */
    public function getLangId()
    {
        $idLang = (int) \Context::getContext()->language->id;

        return $idLang > 0 ? $idLang : (int) \Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Get the current customer ID or null.
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        $ctx = \Context::getContext();
        if (isset($ctx->customer) && $ctx->customer instanceof \Customer && $ctx->customer->id) {
            return (int) $ctx->customer->id;
        }

        return null;
    }

    /**
     * Get all shop IDs the current employee has access to.
     *
     * @return int[]
     */
    public function getAccessibleShopIds()
    {
        $shops = \Shop::getShops(true);
        $ids   = array();
        foreach ($shops as $shop) {
            $ids[] = (int) $shop['id_shop'];
        }

        return $ids ?: array($this->getShopId());
    }

    /**
     * Determine whether multistore is active.
     *
     * @return bool
     */
    public function isMultistore()
    {
        return \Shop::isFeatureActive();
    }

    /**
     * Execute a callable in single-shop context, restoring context afterwards.
     *
     * @param int      $idShop
     * @param callable $callback
     *
     * @return mixed
     */
    public function runInShopContext($idShop, $callback)
    {
        $currentIdShop   = $this->getShopId();
        $currentContext  = \Shop::getContext();
        $currentGroupId  = \Shop::getContextShopGroupID();

        \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $idShop);

        try {
            $result = call_user_func($callback);
        } catch (\Exception $e) {
            \Shop::setContext($currentContext, $currentGroupId ?: $currentIdShop);
            throw $e;
        }

        \Shop::setContext($currentContext, $currentGroupId ?: $currentIdShop);

        return $result;
    }
}
