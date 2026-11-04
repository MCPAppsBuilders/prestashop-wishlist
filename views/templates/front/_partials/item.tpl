{$swl_available = ($item.status == 'ok')}
<li class="summitwishlist-item{if !$swl_available} summitwishlist-item--unavailable{/if}">
  <label class="summitwishlist-item__select">
    <input type="checkbox" name="ids[]" value="{$item.id|intval}"{if !$swl_available} disabled{/if}>
    <span class="visually-hidden">{l s='Select %product%' sprintf=['%product%' => $item.name] d='Modules.Summitwishlist.Shop'}</span>
  </label>

  <div class="summitwishlist-item__image">
    {if $item.image}
      <img src="{$item.image|escape:'htmlall':'UTF-8'}" alt="{$item.name|escape:'htmlall':'UTF-8'}" loading="lazy" width="125" height="125">
    {else}
      <img src="{$urls.no_picture_image.bySize.home_default.url|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" width="125" height="125">
    {/if}
  </div>

  <div class="summitwishlist-item__info">
    {if $item.url}
      <a class="summitwishlist-item__name" href="{$item.url|escape:'htmlall':'UTF-8'}">{$item.name|escape:'htmlall':'UTF-8'}</a>
    {else}
      <span class="summitwishlist-item__name">{$item.name|escape:'htmlall':'UTF-8'}</span>
    {/if}
    {if $item.combination}
      <div class="summitwishlist-item__combination">{$item.combination|escape:'htmlall':'UTF-8'}</div>
    {/if}
    {if $item.price}
      <div class="summitwishlist-item__price">{$item.price|escape:'htmlall':'UTF-8'}</div>
    {/if}
    {if !$swl_available}
      <span class="badge bg-secondary summitwishlist-item__status">
        {if $item.status == 'out_of_stock'}
          {l s='Out of stock' d='Modules.Summitwishlist.Shop'}
        {elseif $item.status == 'no_access'}
          {l s='Not available for your account' d='Modules.Summitwishlist.Shop'}
        {elseif $item.status == 'needs_options'}
          {l s='Options required' d='Modules.Summitwishlist.Shop'}
        {else}
          {l s='No longer available' d='Modules.Summitwishlist.Shop'}
        {/if}
      </span>
    {/if}
  </div>

  <div class="summitwishlist-item__actions">
    {if $swl_available}
      <button type="submit" name="single" value="{$item.id|intval}" class="btn btn-primary btn-sm js-summitwishlist-submit">
        {l s='Add to cart' d='Shop.Theme.Actions'}
      </button>
    {elseif $item.status == 'needs_options' && $item.url}
      <a class="btn btn-outline-primary btn-sm" href="{$item.url|escape:'htmlall':'UTF-8'}">
        {l s='Choose options' d='Modules.Summitwishlist.Shop'}
      </a>
    {else}
      <button type="button" class="btn btn-primary btn-sm" disabled>
        {l s='Add to cart' d='Shop.Theme.Actions'}
      </button>
    {/if}
    <button type="submit" formaction="{$swl_item_url|escape:'htmlall':'UTF-8'}" name="id_item" value="{$item.id|intval}" class="btn btn-link btn-sm js-summitwishlist-submit">
      {l s='Remove' d='Modules.Summitwishlist.Shop'}
    </button>
  </div>
</li>
