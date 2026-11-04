<?php

namespace Summit\Wishlist\Security;

use Cookie;
use Summit\Wishlist\Owner\WishlistOwner;

/**
 * Per-visitor CSRF token. Tools::getToken() is identical for every guest,
 * so the module issues its own random token, bound to the owner it was
 * issued for. A login or logout changes the owner and therefore the token.
 */
class CsrfTokenManager
{
    public const FIELD = 'swl_token';

    private const TOKEN_KEY = 'swl_csrf';
    private const OWNER_KEY = 'swl_csrf_owner';

    /** @var Cookie */
    private $cookie;

    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    public function getToken(WishlistOwner $owner): string
    {
        $token = (string) $this->cookie->{self::TOKEN_KEY};
        if ($token === '' || (string) $this->cookie->{self::OWNER_KEY} !== $owner->getOwnerKey()) {
            $token = bin2hex(random_bytes(32));
            $this->cookie->{self::TOKEN_KEY} = $token;
            $this->cookie->{self::OWNER_KEY} = $owner->getOwnerKey();
            $this->cookie->write();
        }

        return $token;
    }

    public function isValid(WishlistOwner $owner, $submitted): bool
    {
        $token = (string) $this->cookie->{self::TOKEN_KEY};
        if (!is_string($submitted) || $submitted === '' || $token === '') {
            return false;
        }
        if ((string) $this->cookie->{self::OWNER_KEY} !== $owner->getOwnerKey()) {
            return false;
        }

        return hash_equals($token, $submitted);
    }
}
