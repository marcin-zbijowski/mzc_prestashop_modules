{**
 * MZC Carrier Attribute — Rules configuration template
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-truck"></i> {l s='Carrier Restriction Rules' mod='mzccarrierattribute'}
    </div>

    <div class="alert alert-info">
        <p><strong>{l s='How it works:' mod='mzccarrierattribute'}</strong></p>
        <ul>
            <li>{l s='Create rules that restrict carriers based on attribute values.' mod='mzccarrierattribute'}</li>
            <li>{l s='Each rule shows all attribute groups. Check the values that should trigger the rule.' mod='mzccarrierattribute'}</li>
            <li>{l s='Within a group: any checked value matches (OR). Between groups: all checked groups must match (AND).' mod='mzccarrierattribute'}</li>
            <li>{l s='Unchecked groups impose no constraint. Products with no matching rules keep all carriers.' mod='mzccarrierattribute'}</li>
            <li>{l s='When multiple rules match, only carriers allowed by ALL matching rules are available.' mod='mzccarrierattribute'}</li>
        </ul>
    </div>

    <form method="post" action="{$configure_url|escape:'htmlall':'UTF-8'}" id="mzc-rules-form">
        <div id="mzc-rules-container"></div>

        <div class="mzc-add-rule-row">
            <button type="button" id="mzc-add-rule" class="btn btn-default">
                <i class="icon-plus"></i> {l s='Add Rule' mod='mzccarrierattribute'}
            </button>
        </div>

        <div class="panel-footer">
            <button type="submit" name="submitMzcCarrierAttribute" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save Rules' mod='mzccarrierattribute'}
            </button>
            <div class="clearfix"></div>
        </div>
    </form>
</div>

{* Configuration data for the JS rule builder *}
<script type="application/json" id="mzc-data">
{$mzc_data_json nofilter}
</script>

{* Translatable strings for JS *}
<script>
var mzcTranslations = {ldelim}
    rule: "{l s='Rule' mod='mzccarrierattribute' js=1}",
    remove: "{l s='Remove' mod='mzccarrierattribute' js=1}",
    conditions: "{l s='Conditions (select values per attribute group):' mod='mzccarrierattribute' js=1}",
    conditionsHint: "{l s='Within a group: any checked value matches (OR). Between groups: all checked groups must match (AND). Unchecked group = no constraint.' mod='mzccarrierattribute' js=1}",
    allowedCarriers: "{l s='Allowed Carriers:' mod='mzccarrierattribute' js=1}"
{rdelim};
</script>
