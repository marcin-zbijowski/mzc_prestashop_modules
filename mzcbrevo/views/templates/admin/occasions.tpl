{*
 * MZC Brevo — products with a detected occasion, for a one-off review of the catalogue.
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-gift"></i> {l s='Occasions in the catalogue' d='Modules.Mzcbrevo.Admin'}</div>
  <p class="help-block">
    {l s='Where each occasion comes from: a manual choice on the product page (Brevo tab), the category mapping (Mapping tab), or a keyword in the product name (chrz, komuni, bierzmowan, slub, wesel, zaslubin). Correct a wrong one on the product page; "None" there overrides the name and the category.' d='Modules.Mzcbrevo.Admin'}
  </p>
  <p>
    {foreach from=$mzcbrevo.counts key=label item=count}
      <span class="badge" style="margin-right: 6px;">{$label|escape:'html':'UTF-8'}: {$count|intval}</span>
    {/foreach}
    <span class="badge">{l s='no occasion' d='Modules.Mzcbrevo.Admin'}: {$mzcbrevo.none|intval}</span>
    <a href="{$mzcbrevo.toggle_url|escape:'html':'UTF-8'}" class="btn btn-link">
      {if $mzcbrevo.show_all}{l s='Show only products with an occasion' d='Modules.Mzcbrevo.Admin'}{else}{l s='Show all products' d='Modules.Mzcbrevo.Admin'}{/if}
    </a>
  </p>
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>{l s='Product' d='Modules.Mzcbrevo.Admin'}</th>
        <th>{l s='Reference' d='Modules.Mzcbrevo.Admin'}</th>
        <th>{l s='Occasion' d='Modules.Mzcbrevo.Admin'}</th>
        <th>{l s='Source' d='Modules.Mzcbrevo.Admin'}</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$mzcbrevo.products item=product}
        <tr{if !$product.active} class="text-muted"{/if}>
          <td>{$product.id_product|intval}</td>
          <td><a href="{$product.edit_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$product.name|escape:'html':'UTF-8'}</a></td>
          <td>{$product.reference|escape:'html':'UTF-8'}</td>
          <td>{$product.occasion|escape:'html':'UTF-8'}</td>
          <td>{$product.source|escape:'html':'UTF-8'}</td>
        </tr>
      {foreachelse}
        <tr><td colspan="5">{l s='No product has an occasion yet.' d='Modules.Mzcbrevo.Admin'}</td></tr>
      {/foreach}
    </tbody>
  </table>
</div>
