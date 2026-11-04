<?php

namespace Summit\Wishlist\Service;

use Context;
use Db;
use Summit\Wishlist\Cart\PrestaShopCartGateway;
use Summit\Wishlist\Cart\PurchasePolicy;
use Summit\Wishlist\Catalog\PrestaShopProductCatalog;
use Summit\Wishlist\Owner\OwnerResolver;
use Summit\Wishlist\Owner\WishlistOwner;
use Summit\Wishlist\Presenter\WishlistPresenter;
use Summit\Wishlist\Repository\DbWishlistRepository;
use Summit\Wishlist\Security\CsrfTokenManager;

/**
 * Explicit wiring for Stage 1. Works in front office, back office and install code
 * without a Symfony cache rebuild; replace with config/common.yml when the API needs DI.
 */
class ServiceFactory
{
    /** @var Context */
    private $context;
    /** @var array<string, object> */
    private $instances = [];
    /** @var WishlistOwner|null */
    private $owner;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function owner(): WishlistOwner
    {
        if ($this->owner === null) {
            $this->owner = (new OwnerResolver($this->context))->resolve();
        }

        return $this->owner;
    }

    public function repository(): DbWishlistRepository
    {
        return $this->instances['repository'] ??= new DbWishlistRepository(Db::getInstance());
    }

    public function catalog(): PrestaShopProductCatalog
    {
        return $this->instances['catalog'] ??= new PrestaShopProductCatalog($this->context);
    }

    public function csrf(): CsrfTokenManager
    {
        return $this->instances['csrf'] ??= new CsrfTokenManager($this->context->cookie);
    }

    public function wishlistService(): WishlistService
    {
        return $this->instances['service'] ??= new WishlistService(
            $this->repository(),
            new WishlistAccessPolicy(),
            $this->catalog(),
            new PurchasePolicy($this->context, $this->catalog()),
            new PrestaShopCartGateway($this->context),
            $this->context->getTranslator()->trans('My wishlist', [], 'Modules.Summitwishlist.Shop')
        );
    }

    public function presenter(): WishlistPresenter
    {
        return $this->instances['presenter'] ??= new WishlistPresenter($this->context, $this->wishlistService(), $this->catalog());
    }
}
