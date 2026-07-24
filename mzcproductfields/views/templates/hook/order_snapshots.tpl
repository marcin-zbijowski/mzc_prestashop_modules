{**
 * MZC Product Fields — backoffice order page: JPG snapshot(s) of what the
 * customer's personalization looked like at confirmation time (see
 * Mzcproductfields::hookDisplayAdminOrderMain and views/js/front-snapshot.js),
 * plus the plain-text values behind them (same data Anvanto showed as text,
 * never as an image) — the merchant fulfilling an order needs to read the
 * exact submitted text reliably, not decipher it off a mockup photo.
 *}
{if $mzc_text_values}
  <div class="panel mzc-order-text-values">
    <div class="panel-heading">
      <i class="icon-font"></i>
      {$mzc_text_values_title|escape:'html':'UTF-8'}
    </div>
    {foreach from=$mzc_text_values item=mzc_product}
      <div class="mzc-order-text-values__product">
        <strong>{$mzc_product.product_name|escape:'html':'UTF-8'}</strong>
        <table class="table">
          <tbody>
            {foreach from=$mzc_product.values item=mzc_value}
              <tr>
                <td class="mzc-order-text-values__label">{$mzc_value.label|escape:'html':'UTF-8'}</td>
                <td>{$mzc_value.value|escape:'html':'UTF-8'|nl2br}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {/foreach}
  </div>
{/if}

{if $mzc_snapshots}
<div class="panel mzc-order-snapshots">
  <div class="panel-heading">
    <i class="icon-picture"></i>
    {$mzc_snapshots_title|escape:'html':'UTF-8'}
  </div>
  {foreach from=$mzc_snapshots item=mzc_snapshot_product}
    <div class="mzc-order-snapshots__product">
      <strong>{$mzc_snapshot_product.product_name|escape:'html':'UTF-8'}</strong>
      <div class="mzc-order-snapshots__grid">
        {foreach from=$mzc_snapshot_product.items item=mzc_snapshot}
          <a class="mzc-order-snapshots__item" href="{$mzc_snapshot.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
            <img src="{$mzc_snapshot.url|escape:'html':'UTF-8'}" alt="">
          </a>
        {/foreach}
      </div>
    </div>
  {/foreach}
</div>
{/if}

<style>
  .mzc-order-text-values__product {
    padding: 0 15px;
  }
  .mzc-order-text-values__product:not(:last-child) {
    margin-bottom: 10px;
  }
  .mzc-order-text-values__label {
    width: 220px;
    font-weight: 600;
  }
  .mzc-order-snapshots__product {
    padding: 0 15px;
  }
  .mzc-order-snapshots__product:not(:last-child) {
    margin-bottom: 10px;
  }
  .mzc-order-snapshots__grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 0 15px;
  }
  .mzc-order-snapshots__item {
    display: block;
    width: 140px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
  }
  .mzc-order-snapshots__item img {
    display: block;
    width: 100%;
    height: auto;
  }
</style>
