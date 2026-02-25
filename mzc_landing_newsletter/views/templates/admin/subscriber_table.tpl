{*
 * MZC Landing Newsletter
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-envelope"></i> {$heading_subscribers|escape:'html':'UTF-8'}
        <span class="badge">{$subscriber_count|intval}</span>
    </div>

    {* Refresh + Export buttons *}
    <div class="panel-body">
        <a href="{$refresh_link|escape:'html':'UTF-8'}" class="btn btn-default">
            <i class="icon-refresh"></i> {$label_refresh|escape:'html':'UTF-8'}
        </a>
        {if $subscriber_count > 0}
            <form method="post" action="{$csv_link|escape:'html':'UTF-8'}" class="mzc-inline-form">
                <button type="submit" name="exportCsv" class="btn btn-default">
                    <i class="icon-download"></i> {$label_export|escape:'html':'UTF-8'}
                </button>
            </form>
        {/if}
    </div>

    {if $subscriber_count > 0}
        <div class="panel-body mzc-subscriber-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$th_id|escape:'html':'UTF-8'}</th>
                            <th>{$th_email|escape:'html':'UTF-8'}</th>
                            <th>{$th_date|escape:'html':'UTF-8'}</th>
                            <th>{$th_ip|escape:'html':'UTF-8'}</th>
                            <th>{$th_language|escape:'html':'UTF-8'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $subscribers as $sub}
                            <tr>
                                <td>{$sub.id|intval}</td>
                                <td>{$sub.email|escape:'html':'UTF-8'}</td>
                                <td>{$sub.newsletter_date_add|escape:'html':'UTF-8'}</td>
                                <td>{$sub.ip_registration_newsletter|escape:'html':'UTF-8'}</td>
                                <td>{$sub.lang_name|escape:'html':'UTF-8'}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            {* Pagination *}
            {if $total_pages > 1}
                <div class="mzc-pagination" style="text-align:center; margin-top:15px;">
                    {if $current_page > 1}
                        <a href="{$page_base_url|escape:'html':'UTF-8'}&mzc_page={($current_page - 1)|intval}" class="btn btn-default btn-sm">
                            &laquo; {$label_prev|escape:'html':'UTF-8'}
                        </a>
                    {/if}
                    <span style="margin: 0 10px;">
                        {$label_page|escape:'html':'UTF-8'} {$current_page|intval} {$label_of|escape:'html':'UTF-8'} {$total_pages|intval}
                    </span>
                    {if $current_page < $total_pages}
                        <a href="{$page_base_url|escape:'html':'UTF-8'}&mzc_page={($current_page + 1)|intval}" class="btn btn-default btn-sm">
                            {$label_next|escape:'html':'UTF-8'} &raquo;
                        </a>
                    {/if}
                </div>
            {/if}
        </div>
    {else}
        <div class="panel-body mzc-subscriber-body">
            <p>{$label_no_subscribers|escape:'html':'UTF-8'}</p>
        </div>
    {/if}
</div>
