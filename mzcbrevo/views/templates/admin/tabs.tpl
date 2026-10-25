{*
 * MZC Brevo — configuration page tabs.
 *}
<ul class="nav nav-tabs" style="margin-bottom: 15px;">
  {foreach from=$mzcbrevo.tabs key=key item=label}
    <li class="nav-item{if $key == $mzcbrevo.current} active{/if}">
      <a class="nav-link{if $key == $mzcbrevo.current} active{/if}" href="{$mzcbrevo.base_url|escape:'html':'UTF-8'}&amp;mzcbrevo_tab={$key|escape:'url'}">{$label|escape:'html':'UTF-8'}</a>
    </li>
  {/foreach}
</ul>
