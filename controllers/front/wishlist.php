<?php

/**
 * The wishlist page, available to guests and customers.
 *
 * @property Summitwishlist $module
 */
class SummitwishlistWishlistModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        parent::initContent();

        $services = $this->module->getServices();
        $owner = $services->owner();
        $service = $services->wishlistService();
        $wishlist = $service->getDefaultWishlist($owner);
        $items = $wishlist ? $service->getItems($owner, (int) $wishlist['id_wishlist']) : [];

        $this->context->smarty->assign([
            'swl_items' => $services->presenter()->present($items),
            'swl_token' => $services->csrf()->getToken($owner),
            'swl_cart_url' => $this->context->link->getModuleLink($this->module->name, 'cart'),
            'swl_item_url' => $this->context->link->getModuleLink($this->module->name, 'item'),
            'swl_continue_url' => $this->context->link->getPageLink('index'),
        ]);

        $this->setTemplate('module:summitwishlist/views/templates/front/wishlist.tpl');
    }

    public function getBreadcrumbLinks(): array
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->trans('My wishlist', [], 'Modules.Summitwishlist.Shop'),
            'url' => $this->module->getWishlistUrl(),
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage(): array
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->trans('My wishlist', [], 'Modules.Summitwishlist.Shop');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }
}
