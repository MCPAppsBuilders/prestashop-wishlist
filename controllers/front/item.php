<?php

use Summit\Wishlist\Controller\RespondsToShopper;
use Summit\Wishlist\Security\CsrfTokenManager;

/**
 * POST op=add|remove. Guests are allowed; ownership comes from the server-side context.
 *
 * @property Summitwishlist $module
 */
class SummitwishlistItemModuleFrontController extends ModuleFrontController
{
    use RespondsToShopper;

    public function postProcess(): void
    {
        if (!$this->isPostRequest()) {
            Tools::redirect($this->module->getWishlistUrl());
        }

        $services = $this->module->getServices();
        $owner = $services->owner();
        if (!$services->csrf()->isValid($owner, Tools::getValue(CsrfTokenManager::FIELD))) {
            $this->respond(false, [$this->module->messageFor('invalid_token')]);
        }

        $service = $services->wishlistService();
        switch (Tools::getValue('op')) {
            case 'add':
                $wishlist = $service->getDefaultWishlist($owner, true);
                $groups = Tools::getValue('group');
                $result = $service->addItem(
                    $owner,
                    (int) $wishlist['id_wishlist'],
                    (int) Tools::getValue('id_product'),
                    (int) Tools::getValue('swl_id_product_attribute'),
                    is_array($groups) ? array_values($groups) : []
                );
                $this->respond(
                    in_array($result['code'], ['added', 'already_present'], true),
                    [$this->module->messageFor($result['code'], $result['product_name'])],
                    ['code' => $result['code'], 'count' => $service->countItems($owner)]
                );
                break;

            case 'remove':
                $wishlist = $service->getDefaultWishlist($owner);
                $result = $wishlist
                    ? $service->removeItem($owner, (int) $wishlist['id_wishlist'], (int) Tools::getValue('id_item'))
                    : ['code' => 'not_found', 'product_name' => ''];
                $this->respond(
                    $result['code'] === 'removed',
                    [$this->module->messageFor($result['code'], $result['product_name'])],
                    ['code' => $result['code'], 'count' => $service->countItems($owner)]
                );
                break;
        }

        $this->respond(false, [$this->module->messageFor('failed')]);
    }
}
