{**
 * MZC Carrier Attribute — Rules configuration template
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-truck"></i> {l s='Carrier Restrictions by Attribute Value' mod='mzc_carrier_attribute'}
    </div>

    <div class="row" style="margin-bottom: 15px;">
        <div class="col-lg-12">
            {if $show_only_configured}
                <a href="{$configure_url|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                    <i class="icon-list"></i> {l s='Show all attributes' mod='mzc_carrier_attribute'}
                </a>
            {else}
                <a href="{$configure_url|escape:'htmlall':'UTF-8'}&show_configured=1" class="btn btn-default">
                    <i class="icon-filter"></i> {l s='Show only configured attributes' mod='mzc_carrier_attribute'}
                </a>
            {/if}
        </div>
    </div>

    <div class="alert alert-info">
        <p><strong>{l s='How it works:' mod='mzc_carrier_attribute'}</strong></p>
        <ul>
            <li>{l s='Select which carriers are allowed for each attribute value.' mod='mzc_carrier_attribute'}</li>
            <li>{l s='If an attribute value has no carriers selected, it imposes no restriction (all carriers allowed).' mod='mzc_carrier_attribute'}</li>
            <li>{l s='If multiple restricted attributes are in the cart, only carriers allowed by ALL of them will be available.' mod='mzc_carrier_attribute'}</li>
            <li>{l s='Simple products (no combinations) never restrict carriers.' mod='mzc_carrier_attribute'}</li>
        </ul>
    </div>

    <form method="post" action="{$configure_url|escape:'htmlall':'UTF-8'}">
        {foreach $attribute_groups as $group}
            <div class="panel panel-default mzc-attr-group">
                <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#mzc-group-{$group.id_attribute_group|intval}">
                    <i class="icon-chevron-down"></i>
                    <strong>{$group.name|escape:'htmlall':'UTF-8'}</strong>
                    <span class="badge">{$group.attributes|count} {l s='values' mod='mzc_carrier_attribute'}</span>
                </div>
                <div class="panel-body collapse" id="mzc-group-{$group.id_attribute_group|intval}">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 200px;">{l s='Attribute Value' mod='mzc_carrier_attribute'}</th>
                                <th>{l s='Allowed Carriers' mod='mzc_carrier_attribute'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $group.attributes as $attr}
                                <tr>
                                    <td><strong>{$attr.name|escape:'htmlall':'UTF-8'}</strong></td>
                                    <td>
                                        {foreach $carrier_list as $carrier}
                                            <label class="mzc-carrier-checkbox">
                                                <input
                                                    type="checkbox"
                                                    name="rule_{$attr.id_attribute|intval}[]"
                                                    value="{$carrier.id_reference|intval}"
                                                    {if in_array($carrier.id_reference, $attr.current_refs)}checked="checked"{/if}
                                                >
                                                {$carrier.label|escape:'htmlall':'UTF-8'}
                                            </label>
                                        {/foreach}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        {/foreach}

        <input type="hidden" name="mzc_attr_ids" value="{$all_attr_ids|escape:'htmlall':'UTF-8'}">

        <div class="panel-footer">
            <button type="submit" name="submitMzcCarrierAttribute" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save Rules' mod='mzc_carrier_attribute'}
            </button>
            <div class="clearfix"></div>
        </div>
    </form>
</div>
