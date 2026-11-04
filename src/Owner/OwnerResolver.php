<?php

namespace Summit\Wishlist\Owner;

use Context;

/**
 * Resolves the current visitor from the PrestaShop context, never from request input.
 * Guests get a random token in the (encrypted) front cookie; only its hash is stored.
 */
class OwnerResolver
{
    private const GUEST_COOKIE_KEY = 'swl_guest';

    /** @var Context */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function resolve(): WishlistOwner
    {
        $shopId = (int) $this->context->shop->id;
        $customer = $this->context->customer;

        if ($customer && $customer->isLogged()) {
            return new WishlistOwner($shopId, 'customer:' . (int) $customer->id, (int) $customer->id);
        }

        $cookie = $this->context->cookie;
        $token = (string) $cookie->{self::GUEST_COOKIE_KEY};
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            $cookie->{self::GUEST_COOKIE_KEY} = $token;
            $cookie->write();
        }

        return new WishlistOwner($shopId, 'guest:' . hash('sha256', $token), null);
    }
}
