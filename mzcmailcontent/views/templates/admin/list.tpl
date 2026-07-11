{*
* MZC Mail Content — campaigns list view (table) + test-send panel.
*}
<div class="panel" id="mzc-mailcontent-list">
    <div class="panel-heading">
        <i class="icon-envelope"></i> {$heading_list|escape:'html':'UTF-8'}
        <span class="panel-heading-action">
            <a href="{$add_url|escape:'html':'UTF-8'}" class="btn btn-primary btn-sm">
                <i class="icon-plus"></i> {$label_add|escape:'html':'UTF-8'}
            </a>
        </span>
    </div>

    <p class="text-muted">{$desc_list|escape:'html':'UTF-8'}</p>

    {if $mzc_campaigns|@count == 0}
        <div class="alert alert-info">{$label_no_campaigns|escape:'html':'UTF-8'}</div>
    {else}
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>{$th_id|escape:'html':'UTF-8'}</th>
                    <th>{$th_name|escape:'html':'UTF-8'}</th>
                    <th>{$th_template|escape:'html':'UTF-8'}</th>
                    <th>{$th_position|escape:'html':'UTF-8'}</th>
                    <th>{$th_date_from|escape:'html':'UTF-8'}</th>
                    <th>{$th_date_to|escape:'html':'UTF-8'}</th>
                    <th>{$th_active|escape:'html':'UTF-8'}</th>
                    <th>{$th_actions|escape:'html':'UTF-8'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$mzc_campaigns item=row}
                    <tr>
                        <td>{$row.id_campaign|intval}</td>
                        <td><strong>{$row.name|escape:'html':'UTF-8'}</strong></td>
                        <td><code>{$row.template|escape:'html':'UTF-8'}</code></td>
                        <td>{$row.position|escape:'html':'UTF-8'}</td>
                        <td>{$row.date_from|escape:'html':'UTF-8'}</td>
                        <td>{$row.date_to|escape:'html':'UTF-8'}</td>
                        <td>
                            {if $row.active}
                                <span class="label label-success">{$label_active_yes|escape:'html':'UTF-8'}</span>
                            {else}
                                <span class="label label-default">{$label_active_no|escape:'html':'UTF-8'}</span>
                            {/if}
                        </td>
                        <td>
                            <a href="{$row.edit_url|escape:'html':'UTF-8'}" class="btn btn-default btn-xs">
                                <i class="icon-edit"></i> {$label_edit|escape:'html':'UTF-8'}
                            </a>
                            {foreach from=$row.preview_links item=pv}
                                <a href="{$pv.url|escape:'html':'UTF-8'}" target="_blank"
                                   class="btn btn-default btn-xs" rel="noopener">
                                    <i class="icon-eye"></i> {$label_preview|escape:'html':'UTF-8'} {$pv.iso|escape:'html':'UTF-8'|upper}
                                </a>
                            {/foreach}
                            <a href="{$row.delete_url|escape:'html':'UTF-8'}"
                               class="btn btn-danger btn-xs"
                               onclick="return confirm('{$confirm_delete|escape:'javascript':'UTF-8'}');">
                                <i class="icon-trash"></i> {$label_delete|escape:'html':'UTF-8'}
                            </a>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}
</div>

<div class="panel" id="mzc-mailcontent-test">
    <div class="panel-heading">
        <i class="icon-paper-plane"></i> {$heading_test|escape:'html':'UTF-8'}
    </div>

    <p class="text-muted">{$desc_test|escape:'html':'UTF-8'}</p>

    <form method="post" action="{$form_action|escape:'html':'UTF-8'}" class="form-horizontal">
        <div class="form-group">
            <label class="control-label col-lg-3" for="mzc_test_campaign">
                {$label_campaign|escape:'html':'UTF-8'}
            </label>
            <div class="col-lg-6">
                <select id="mzc_test_campaign" name="mzc_test_campaign" class="form-control" required>
                    {if $mzc_campaigns|@count == 0}
                        <option value="">{$label_no_active|escape:'html':'UTF-8'}</option>
                    {else}
                        {foreach from=$mzc_campaigns item=row}
                            <option value="{$row.id_campaign|intval}">
                                #{$row.id_campaign|intval} — {$row.name|escape:'html':'UTF-8'} ({$row.template|escape:'html':'UTF-8'})
                            </option>
                        {/foreach}
                    {/if}
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3" for="mzc_test_email">
                {$label_email|escape:'html':'UTF-8'}
            </label>
            <div class="col-lg-6">
                <input type="email" id="mzc_test_email" name="mzc_test_email" class="form-control"
                       value="{$mzc_default_email|escape:'html':'UTF-8'}" required>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3" for="mzc_test_lang">
                {$label_lang|escape:'html':'UTF-8'}
            </label>
            <div class="col-lg-6">
                <select id="mzc_test_lang" name="mzc_test_lang" class="form-control">
                    {foreach from=$mzc_languages item=lang}
                        <option value="{$lang.id_lang|intval}"
                            {if $lang.id_lang == $mzc_default_lang}selected="selected"{/if}>
                            {$lang.name|escape:'html':'UTF-8'} ({$lang.iso_code|escape:'html':'UTF-8'})
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
                <button type="submit" name="submitMzcMailcontentTest" class="btn btn-default"
                    {if $mzc_campaigns|@count == 0}disabled{/if}>
                    <i class="icon-paper-plane"></i> {$label_send|escape:'html':'UTF-8'}
                </button>
            </div>
        </div>
    </form>
</div>
