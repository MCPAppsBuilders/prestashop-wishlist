# Wishlist module: Stage 1 implementation plan

Target: PrestaShop 9.2.0 (Symfony 6.4, PHP 8.5 in the Flashlight container), themes `classic` and `hummingbird`.

Sources: "Copy of Hackathon PS Summit Task 1" (Rules, Task 1, Implementation proposal, Future business requirement anticipations, review 2 §1), PrestaShop 9 devdocs (module upgrade scripts, Admin API resources), and the theme templates in `prestashop/themes/`.

This plan merges two drafts: the Claude draft (structure and file layout, checked against this shop) and the "Implementation proposal" tab (stricter ownership integrity and edge-case rules). Stage 1 ships only what Task 1 asks for. The future requirements shape the **structure** (tables, service boundaries, extension seams) so each later feature arrives as a module upgrade, not a rewrite or data migration.

## 1. Module identity

| | |
|---|---|
| Technical name | `summitwishlist` (placeholder; avoid the `ps_` prefix, which is reserved for official modules, and `blockwishlist`, the core module's name) |
| Version | `1.0.0` = Stage 1. Every later stage is a minor bump with an `upgrade/upgrade-1.x.0.php` script |
| Namespace | `Summit\Wishlist\` → `src/` (PSR-4 via the module's `composer.json`) |
| Location | `prestashop/modules/summitwishlist/`, kept as its own Git repo or a self-contained folder so it installs into any shop |

Verified in this shop: PS 9.2.0; both themes call `displayProductActions` and `displayProductListReviews`; `blockwishlist` is not installed; `ps_apiresources` is present, so module Admin API resources can be auto-loaded later.

## 2. Key design decisions

### 2.1 Data model

A wishlist is a row of its own from day one, owned through an internal `owner_key`. Multiple, named, and shared wishlists then become *more rows plus new tables*, never a reshaping of existing data.

```sql
-- PREFIX_summitwishlist
id_wishlist      INT UNSIGNED AUTO_INCREMENT PK
id_shop          INT UNSIGNED NOT NULL
owner_key        VARCHAR(80)  NOT NULL   -- "customer:<id_customer>" or "guest:<sha256 of token>"
id_customer      INT UNSIGNED NULL       -- denormalised for joins/cleanup; NULL for guests
name             VARCHAR(128) NOT NULL   -- translated "My wishlist" in Stage 1
default_marker   TINYINT(1)   NULL       -- 1 = the owner's default list, NULL = any other list
date_add, date_upd DATETIME
UNIQUE (id_shop, owner_key, default_marker)   -- at most one default per owner; NULLs may repeat
INDEX  (id_shop, owner_key)

-- PREFIX_summitwishlist_product
id_wishlist_product  INT UNSIGNED AUTO_INCREMENT PK
id_wishlist          INT UNSIGNED NOT NULL
id_product           INT UNSIGNED NOT NULL
id_product_attribute INT UNSIGNED NOT NULL DEFAULT 0
fallback_name        VARCHAR(255) NOT NULL   -- product name at add time, shown only if the product is deleted
date_add             DATETIME
UNIQUE (id_wishlist, id_product, id_product_attribute)   -- enforces Scenario 3 at DB level
INDEX  (id_wishlist, id_wishlist_product)                -- stable ordering / future pagination
```

Why it looks like this:
- **`owner_key` + `default_marker`** give the database itself a guarantee of one default list per owner, even if two requests race to create it. Creation is `INSERT IGNORE`, then re-read. This takes the integrity rule of the proposal's separate owner and default tables while keeping two tables.
- **The guest token is stored only as a hash** (inside `owner_key`), so a database leak can't be used to take over guest wishlists.
- **`name`** costs nothing now; without it, "multiple wishlists" would need a backfill.
- **`fallback_name`** keeps deleted products visible as "no longer available" (Scenario 11). It is never used for price or availability.
- **`id_shop`** keeps the module multistore-safe.

Deliberately **not** added yet, each via a later `upgrade-1.x.0.php`:
- `quantity` (recurring orders): `ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1`. This is a harmless additive change and a good live upgrade demo.
- Sharing: a separate hashed, expiring, revocable share-token table plus membership/permission rows. These are never the guest ownership credential.
- Reservations / "already purchased" marks and recurring schedules: their own tables (see section 3).

### 2.2 Wishlist entries are purchasable variants

The task asks for one consistent choice. Each entry stores `(id_product, id_product_attribute)`:
- From the **product page**: the combination currently selected.
- From a **listing page**: the product's default combination (`Product::getDefaultAttribute`). Products without combinations store 0.
- Different combinations are different entries; re-adding the same combination is a duplicate and returns an "already in your wishlist" message.
- The wishlist page **shows the saved combination** (e.g. "Size: M") so customers understand this.
- If the saved combination disappears, the item shows as unavailable. **No silent substitution** of another variant.
- **Products that require customisation** can't be added to the cart from the wishlist (the core cart would reject them). Instead, show "Choose options" linking to the product page.

### 2.3 Owner resolution (guest vs customer)

A `WishlistOwner` value object (shop + `owner_key`) is built by a single `OwnerResolver` from the PrestaShop `Context`. It is never built from IDs supplied by the browser.
- Logged in → `customer:<id_customer>`.
- Guest → a random 32-byte token in the PrestaShop front cookie (`$context->cookie`), which is encrypted and long-lived, so it survives refreshes (Scenario 4). The database only sees `guest:<sha256(token)>`.
- The account list is resolved only from a logged-in customer, so it is never shown after logout. The storefront logout (`Customer::mylogout`) is a soft logout that keeps module cookie keys, so the visitor returns to their earlier guest list, which is harmless and expected.

**Login policy for Stage 1: guest and account lists stay separate.** On login the customer sees their account list; on logout a fresh guest list starts. Task 1 doesn't require merging, and separate lists are less code and risk. Merging is a ready P1 option if the organiser asks: an `actionAuthentication` handler that runs a transactional `INSERT IGNORE ... SELECT` into the account's default list, then deletes the guest list.

### 2.4 Access control seam

Every read and write goes through `WishlistAccessPolicy::canView(owner, wishlist)` / `canEdit(...)`. In Stage 1 the rule is "owner and same shop only"; all lists are private. When sharing arrives, this class gains the share and permission rules, and every controller and API endpoint inherits them. Nothing else calls the repositories directly.

Every mutation is also checked as follows:
- POST only, with a **module CSRF token bound to the visitor's session** (review 2, P1). `Tools::getToken(false)` is *not* used: in 9.2.0 it hashes `customer->id . customer->passwd . page` (`classes/Tools.php:1237`), which is identical for every guest. Instead, `CsrfTokenManager`:
  - generates a random 32-byte token per visitor and stores it in the PrestaShop front cookie (`summitwishlist_csrf`), separately from the guest ownership token;
  - validates submissions with `hash_equals()` before any wishlist or cart work. A missing or invalid token returns an error and changes nothing;
  - rotates the token whenever the identity changes. The token is stored together with the `owner_key` it was issued for; on login or logout the keys no longer match, so a new token is issued and the old one is rejected.
- The CSRF token is the only token allowed in markup (a hidden field or a `data-` attribute on the same origin). Ownership and future share credentials never appear in markup or logs.
- IDs are cast and validated: the product exists in the current shop, and the combination belongs to that product.
- The item belongs to the caller's wishlist. A missing or already-removed item produces a harmless "nothing to remove" result.
- Output is escaped.

### 2.5 Layering: logic in services, not controllers

```
Front controllers / hooks / (later) API resources
        │
        ▼
WishlistService  ──► WishlistAccessPolicy
   │   getDefaultWishlist(owner)
   │   getWishlist(owner, wishlistId)
   │   addItem(owner, productId, attrId, wishlistId = null)
   │   removeItem(owner, itemId)
   │   addItemsToCart(owner, itemIds)  → CartAdditionResult[]
   ▼
WishlistRepository (Db::getInstance(), behind an interface)
ProductCatalog     (interface; PrestaShop adapter: existence, availability, presentation)
CartGateway        (interface; PrestaShop adapter around Cart::updateQty)

WishlistPresenter  – turns items + catalog data into template data
```

- The optional `wishlistId` (null means default) is the seam for multiple wishlists and the "choose a list" modal.
- Services return structured results with error codes (`added`, `already_present`, `unavailable`, `no_access`, `needs_options`, `min_quantity`, `max_quantity`, `out_of_stock`, `invalid`, `failed`). Controllers translate them into notices, redirects, or JSON. The future API reuses the same codes.
- The structure is a flat `src/` rather than full Domain/Application/Infrastructure folders, to fit four hours. Only catalog and cart sit behind interfaces, because those are where PrestaShop specifics live and where tests would need fakes.
- Repositories use `Db` rather than Doctrine, because it works identically in the front office, back office, CLI, and Admin API.
- **Stage 1 wires services through a small `ServiceFactory`**, not Symfony YAML. Front-office service definitions only take effect after a Symfony cache rebuild, which this environment can't trigger from the console. The factory also covers install/upgrade, where module services aren't available yet (review 2 §3). Move to `config/common.yml`, imported by front and admin, when the API stage needs container injection; the classes don't change.

### 2.6 Cart integration and availability

- **A `PurchasePolicy` reproduces the core `CartController` checks before any cart change** (review 2, P1; verified against `controllers/front/CartController.php` in 9.2.0). `Cart::updateQty()` alone skips several of them. For each item, in this order:
  1. The product exists, is associated with the current shop, is `active` and `available_for_order`.
  2. `Product::checkAccess($cart->id_customer)` passes. This is the customer-group restriction (core line 384) and is checked live, so a product later restricted to another group is refused.
  3. The saved combination still belongs to the product. A product that has combinations but none was saved is refused rather than substituted.
  4. No required customisation is missing (`hasAllRequiredCustomizableFields()`, core line 489). Otherwise the result is `needs_options`. This is enforced server-side, so a direct POST can't bypass it.
  5. Quantity: the amount to add is `max(1, minimal_quantity − quantity already in cart)`, using the combination's minimal quantity when there is one (core lines 454–476). The result reports requested and added quantities, so "added 3 (minimum purchase)" is explicit.
  6. Stock: the same rule as core `shouldAvailabilityErrorBeRaised` (lines 612–625). If backorders aren't allowed (`!Product::isAvailableWhenOutOfStock`), the cart quantity after the add must be available (`ProductAttribute::checkAttributeQty` / `Product::getQuantity`).
- `CartGateway` then calls the core `Cart::updateQty($qty, $idProduct, $idAttr, 0, 'up', 0, null, true, false)` on `Context::cart`. These are core's arguments except the last: core passes `true` (skip the stock check) because it checks stock itself before and after, while the module passes `false` to keep updateQty's own check as a second guard. It also does what core does, creating and attaching a cart first if the visitor has none (`$cart->add()` + `cookie->id_cart`). It **never writes to cart tables directly**. The return value is interpreted explicitly, as core does (lines 508–524):
  - `< 0`: minimum quantity not met (`min_quantity`);
  - `false` / `0`: maximum quantity already in cart (`max_quantity`);
  - `true`: `added`. The stock pre-check plus updateQty's own stock check (not skipped) take the place of core's post-check. A truthy check alone would misreport negative values as success.
- It only ever **adds** the given items. It never removes from the wishlist or touches other cart lines (UAC: "doesn't unexpectedly modify cart").
- **Single and bulk add share one code path.** Item IDs are deduplicated, foreign-list IDs are rejected, and availability is rechecked at submit time, not just at page load. Valid items are added, failures are reported per item, and the wishlist is unchanged. The requirements don't define mixed-availability behaviour, so **confirm partial success with the organiser.**
- **Availability** comes from live data in the current shop, language, currency, and customer context. An item is available only if the product exists, is `active` and `available_for_order`, and its combination still exists. Zero stock alone does not make an item unavailable when backorders are allowed (`isAvailableWhenOutOfStock`).
- Unavailable items render with a reason ("Out of stock", "No longer available"), a disabled button and checkbox, and a working remove action (Scenario 11).
- Deleted products are **not** cascaded out of wishlists. They stay visible via `fallback_name` until the customer removes them. Customer deletion (`actionObjectCustomerDeleteAfter`) does clean up that customer's lists.
- Double clicks: buttons disable on submit. A server-side idempotency token for bulk cart adds is a P1 item.

### 2.7 One reusable "add to wishlist" component

- Template partial `views/templates/hook/button.tpl` (product ID, attribute ID, context `pdp|miniature`) plus a small JS module exposing `SummitWishlist.add(productId, attrId, {wishlistId})`.
- **Without JS:** a plain POST form redirecting back with a flash notification (accepted by the rules).
- **With JS:** the same endpoint returns JSON and a toast confirms "Added" or "Already in your wishlist" (Scenarios 1–3).
- On the product page, JS reads the selected combination from the core `#add-to-cart-or-refresh` form at click time, so the button stays correct after the AJAX combination switch.
- **Future heart + modal:** swap the partial's markup for a heart icon and have the JS open a list picker before calling `add(..., {wishlistId})`. No controller or schema change is needed.

### 2.8 Hooks

| Hook | Purpose | Notes |
|---|---|---|
| `displayProductActions` | Button on the product page | Present in both themes' `product-add-to-cart.tpl` |
| `displayProductListReviews` | Button on product cards | The only module hook in both themes' miniature template. The same miniature is used by category, search, and widget modules (featured/new/bestsellers), so the future "heart on widgets" requirement is covered by this one hook |
| `displayNav2` | Header link to the wishlist page (with count) | Optional, nice for the demo |
| `displayCustomerAccount` | Link in My Account | |
| `actionFrontControllerSetMedia` | Register JS and CSS | |
| `actionObjectCustomerDeleteAfter` | Remove a deleted customer's lists | |
| `moduleRoutes` | Friendly URL `/wishlist` | |

### 2.9 Front controllers

- `wishlist` (GET): the wishlist page. Works for guests and customers. It shows:
  - name, image, current price (from the core presenter, so tax and specific prices match the shop), and the selected combination;
  - an availability badge, remove, and add-to-cart, or "Choose options" for products that need customisation;
  - checkboxes with "Add selected to cart";
  - an empty state with a "Continue shopping" link.

  Catalog data is batch-loaded for all items rather than queried once per row.
- `item` (POST): `op=add|remove`.
- `cart` (POST): single or selected items to the cart.

Both POST controllers return JSON for AJAX, otherwise a redirect with notifications.

## 3. How the future requirements map onto Stage 1

| Future requirement | What Stage 1 already provides | What the later upgrade adds |
|---|---|---|
| Multiple named wishlists per customer | `name`, `owner_key`, `default_marker` (non-default lists already allowed by the unique key); every service method accepts `wishlistId` | Create/rename/delete UI and endpoints. No schema change; existing IDs and items keep working |
| Share with others, ACL, "already purchased" marks | `WishlistAccessPolicy` seam; internal owner keys | Membership/permission table (owner/editor/viewer); separate hashed, expiring, revocable share-token table; `summitwishlist_reservation` table (item, actor, qty, timestamps) handled in transactions so two gifters can't claim the same item. Open questions: is a mark manual or based on a paid order, can it be reversed, and does the owner see it? |
| Accessible via API | All logic in services with structured result codes; repository/catalog/cart behind interfaces | Customer-facing JSON front controllers (the PS Admin API is for back-office and client-credential clients, not shoppers), and optionally Admin API resources in `src/ApiPlatform/Resources`, which PS 9 auto-loads from installed modules (devdocs: Admin API › API Resources). Customer auth, rate limits, pagination, and idempotency must be defined before exposing writes |
| Recurring orders with quantities and cadence | Concrete variant per item; one cart code path with per-item results | `quantity` column; `summitwishlist_subscription` and execution tables (cadence, timezone, next run, status, unique execution key); a cron/worker outside HTTP requests that rechecks stock and price. **Open business question:** charging automatically needs a payment module with saved payment methods, which most PS payment modules don't offer. A "reorder to cart" flow is the safe first step |
| Heart button on listings and widgets with a list-picker modal | Single partial + JS `add(..., {wishlistId})`; one hook covers all miniatures | Heart markup and a modal fed by a "my wishlists" endpoint |

## 4. File layout

```
summitwishlist/
├── summitwishlist.php            # Module class: install/uninstall, hook handlers (thin)
├── composer.json                 # PSR-4 autoload for src/
├── src/Install/Installer.php     # schema create/drop (works before services exist)
├── upgrade/                      # empty in 1.0.0; upgrade-1.x.0.php per stage
├── controllers/front/wishlist.php   # GET page
├── controllers/front/item.php       # POST add/remove
├── controllers/front/cart.php       # POST single/selected to cart
├── src/
│   ├── Owner/WishlistOwner.php, OwnerResolver.php
│   ├── Repository/WishlistRepositoryInterface.php, DbWishlistRepository.php
│   ├── Catalog/ProductCatalogInterface.php, PrestaShopProductCatalog.php
│   ├── Cart/CartGatewayInterface.php, PrestaShopCartGateway.php, PurchasePolicy.php, CartAdditionResult.php
│   ├── Security/CsrfTokenManager.php
│   ├── Service/WishlistService.php, WishlistAccessPolicy.php, ServiceFactory.php
│   └── Presenter/WishlistPresenter.php
├── views/templates/front/wishlist.tpl, _partials/item.tpl, _partials/empty.tpl
├── views/templates/hook/button.tpl, nav.tpl, account-link.tpl
├── views/js/wishlist.js, views/css/wishlist.css
├── translations/
├── tests/                        # P1: ownership, uniqueness, variant identity, bulk outcomes
└── README.md                     # install, upgrade, decisions (encouraged by the rules)
```

## 5. Build order (about 4 hours, demo-able at each step)

1. **Skeleton (20 min):** module class, `composer.json`, install SQL, hook registration. Exit: installs cleanly from the back office.
2. **Ownership and domain (45 min):** owner resolver (hashed guest token + customer), repository, access policy, `WishlistService` add/remove/read with dedupe and race-safe default list. Exit: two browsers can't see or change each other's lists.
3. **Add buttons (40 min):** partial + `item` controller (form POST first, then JSON + toast). Product page with combination, and listing cards. Exit: Scenarios 1–3.
4. **Wishlist page (45 min):** presenter (name, image, price, combination, availability, deleted fallback), remove, empty state, continue shopping. Exit: Scenarios 4–8.
5. **Cart (40 min):** `cart` controller + `CartGateway`, single and bulk, per-item messages, unavailable and customisation handling. Exit: Scenarios 9–11 with an existing cart left intact.
6. **Polish and safety (20 min):** header and My Account links, customer-deletion cleanup, CSRF/ID validation review.
7. **README + upgrade rehearsal (15 min):** bump to `1.0.1` with a no-op `upgrade-1.0.1.php` and upgrade in place with data present, to prove the path before Stage 2 depends on it. Then commit, push, and request review.

P1, only after acceptance: guest → account merge (if the organiser wants it), bulk cart idempotency token, automated tests, AJAX counters.

## 6. Demo checklist (UAC → how to show it)

| UAC | Demo |
|---|---|
| Add from product page and listing | Click on a product page (switch combination first) and on a category card plus a featured-products widget; toast appears |
| Guests and customers | Do the above in a private window, then logged in |
| Guest survives refresh | Refresh, then open the wishlist page |
| Customer persists across sessions | Log out (the guest session doesn't show the account list), log back in, open the wishlist page |
| No duplicates | Click add twice; "already in your wishlist", one row shown |
| Dedicated page with name, image, price | `/wishlist`, combination shown |
| Remove one | Remove; the others are unchanged |
| Empty message | Remove all; continue shopping works |
| Add one / add selected to cart | Seed the cart with an unrelated item first. Add one, then select A+B with C unselected. Cart has A+B plus the original line; wishlist unchanged |
| Unavailable product | Disable a product, set stock 0 with "deny orders", and delete a combination in the back office. Each shows a reason and can't be added; remove still works; a mixed selection reports per item |
| Cart not modified unexpectedly | Add/remove wishlist items with a non-empty cart; the cart is unchanged |
| CSRF bound to the session (review 2) | Copy a form token from guest browser A and POST it from guest browser B: rejected, nothing changes. A POST with no token is rejected too |
| Purchase checks match core (review 2) | Restrict a saved product to another customer group: refused with `no_access`. Set minimal quantity 3: adds 3 and says so. A POST for a product with a required customisation: refused with `needs_options` |
| Upgrade (from Stage 2 on) | Seed guest and customer lists, upgrade in place, rerun the checks above |

## 7. Risks and notes

- **The database is restored from the image dump every time the `prestashop` container starts** (per the repo README). A container restart wipes the installed module and all wishlist data. That breaks the "upgrade keeps data" demo. Don't restart the container between installing and demoing an upgrade, or change the setup to persist the database before Stage 2.
- If a target shop has the core `blockwishlist` module installed, both buttons would appear. Worth a README note.
- Hummingbird and classic differ in markup. The partial should use minimal, theme-neutral HTML, and both themes should be tested if the demo shop's active theme might change.
- Uninstall drops the module's tables, which is destructive. Disabling keeps data. The README should say so, and upgrades never go through uninstall/reinstall.
- Guest lists accumulate. An expiry policy for stale guest lists is a product decision, not a Stage 1 rule.
