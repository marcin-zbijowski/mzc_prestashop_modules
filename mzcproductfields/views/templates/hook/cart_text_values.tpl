{**
 * MZC Product Fields — shopping cart page: plain-text personalization values
 * for what's currently in the cart (see
 * Mzcproductfields::hookDisplayShoppingCartFooter's docblock) — a
 * theme-independent fallback/complement to core's own native
 * "Product customization" modal, which some custom themes drop when they
 * replace the cart product-line template.
 *}
<div class="mzc-cart-text-values card">
  <div class="card-block">
    <h3 class="mzc-cart-text-values__title">{$mzc_cart_text_values_title|escape:'html':'UTF-8'}</h3>
    {foreach from=$mzc_cart_text_values item=mzc_product}
      <div class="mzc-cart-text-values__product">
        <strong>{$mzc_product.product_name|escape:'html':'UTF-8'}</strong>
        <dl class="mzc-cart-text-values__list">
          {foreach from=$mzc_product.values item=mzc_value}
            <dt>{$mzc_value.label|escape:'html':'UTF-8'}</dt>
            <dd>{$mzc_value.value|escape:'html':'UTF-8'|nl2br}</dd>
          {/foreach}
        </dl>
      </div>
    {/foreach}
  </div>
</div>

<style>
  .mzc-cart-text-values {
    margin-top: 20px;
    padding: 15px;
  }
  .mzc-cart-text-values__title {
    font-size: 1.1em;
    margin-bottom: 10px;
  }
  .mzc-cart-text-values__product:not(:last-child) {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
  }
  .mzc-cart-text-values__list {
    margin: 6px 0 0;
  }
  .mzc-cart-text-values__list dt {
    font-weight: 600;
  }
  .mzc-cart-text-values__list dd {
    margin: 0 0 6px;
  }
</style>
