{**
 * Reusable "Add to wishlist" control. On the product page it sits inside the core
 * #add-to-cart-or-refresh form, so it submits that form (id_product + group[]) to the
 * module via formaction; on product cards it is a small form of its own.
 * Later, the heart + list picker replaces this markup and keeps the same endpoint.
 *}
{if $swl_context == 'pdp'}
  <div class="summitwishlist-add summitwishlist-add--pdp">
    <input type="hidden" name="swl_token" value="{$swl_token|escape:'htmlall':'UTF-8'}">
    <button
      type="submit"
      class="btn btn-outline-primary js-summitwishlist-add"
      formaction="{$swl_action_url|escape:'htmlall':'UTF-8'}"
      formmethod="post"
      name="op"
      value="add"
    >
      <i class="material-icons" aria-hidden="true">favorite_border</i>
      {l s='Add to wishlist' d='Modules.Summitwishlist.Shop'}
    </button>
  </div>
{else}
  <form class="summitwishlist-add summitwishlist-add--miniature" action="{$swl_action_url|escape:'htmlall':'UTF-8'}" method="post">
    <input type="hidden" name="swl_token" value="{$swl_token|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="op" value="add">
    <input type="hidden" name="id_product" value="{$swl_id_product|intval}">
    <input type="hidden" name="swl_id_product_attribute" value="{$swl_id_product_attribute|intval}">
    <button type="submit" class="btn btn-link btn-sm js-summitwishlist-add">
      <i class="material-icons" aria-hidden="true">favorite_border</i>
      {l s='Add to wishlist' d='Modules.Summitwishlist.Shop'}
    </button>
  </form>
{/if}
