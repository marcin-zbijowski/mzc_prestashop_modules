{*
 * MZC Landing Newsletter
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-paint-brush"></i> {$heading_presets|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
        <p>{$desc_presets|escape:'html':'UTF-8'}</p>
        <div class="mzc-preset-grid">
            {foreach $presets as $key => $preset}
                <div class="mzc-preset-card">
                    <i class="{$preset.icon|escape:'html':'UTF-8'} mzc-preset-icon"></i>
                    <strong>{$preset.label|escape:'html':'UTF-8'}</strong>
                    <p class="mzc-preset-desc">{$preset.desc|escape:'html':'UTF-8'}</p>
                    <button type="button" class="btn btn-default mzc-preset-btn" data-preset="{$key|escape:'html':'UTF-8'}">
                        <i class="icon-download"></i> {$label_load|escape:'html':'UTF-8'}
                    </button>
                </div>
            {/foreach}
        </div>
    </div>
    {* Preset CSS data for the external JS file – stored as an escaped HTML attribute *}
    <div id="mzc-presets-data" data-presets="{$presets_json|escape:'html':'UTF-8'}" style="display:none"></div>
</div>
