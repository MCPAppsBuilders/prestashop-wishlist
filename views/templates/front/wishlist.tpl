{extends file='page.tpl'}

{block name='page_title'}
  {l s='My wishlist' d='Modules.Summitwishlist.Shop'}
{/block}

{block name='page_content'}
  {if $swl_items}
    {* One form: "Add to cart" and "Add selected" post to the cart controller,
       "Remove" posts to the item controller through formaction. *}
    <form class="summitwishlist-page" action="{$swl_cart_url|escape:'htmlall':'UTF-8'}" method="post">
      <input type="hidden" name="swl_token" value="{$swl_token|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="op" value="remove">

      <ul class="summitwishlist-list list-unstyled">
        {foreach from=$swl_items item=item}
          {include file='module:summitwishlist/views/templates/front/_partials/item.tpl' item=$item}
        {/foreach}
      </ul>

      <div class="summitwishlist-page__actions">
        <button type="submit" name="bulk" value="1" class="btn btn-primary js-summitwishlist-submit">
          {l s='Add selected to cart' d='Modules.Summitwishlist.Shop'}
        </button>
        <a class="btn btn-link" href="{$swl_continue_url|escape:'htmlall':'UTF-8'}">
          {l s='Continue shopping' d='Modules.Summitwishlist.Shop'}
        </a>
      </div>
    </form>
  {else}
    {include file='module:summitwishlist/views/templates/front/_partials/empty.tpl'}
  {/if}
{/block}
