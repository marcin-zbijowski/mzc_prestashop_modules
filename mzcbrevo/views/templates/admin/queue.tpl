{*
 * MZC Brevo — queue overview: counts, failed and dead jobs, batch reports.
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-tasks"></i> {l s='Queue' d='Modules.Mzcbrevo.Admin'}</div>
  <div class="row">
    {foreach from=$mzcbrevo.counts key=status item=count}
      <div class="col-xs-6 col-sm-2">
        <div class="well text-center" style="padding: 10px;">
          <div style="font-size: 22px;{if ($status == 'dead' || $status == 'failed') && $count > 0} color: #c0392b;{/if}">{$count|intval}</div>
          <div>{$status|escape:'html':'UTF-8'}</div>
        </div>
      </div>
    {/foreach}
  </div>
  <p>
    {if $mzcbrevo.oldest_minutes !== null}
      {l s='Oldest job waiting for its first attempt: %minutes% min.' sprintf=['%minutes%' => $mzcbrevo.oldest_minutes] d='Modules.Mzcbrevo.Admin'}
    {else}
      {l s='Nothing is waiting.' d='Modules.Mzcbrevo.Admin'}
    {/if}
    {l s='Sent jobs are kept for 30 days.' d='Modules.Mzcbrevo.Admin'}
  </p>
  <form method="post" action="{$mzcbrevo.action_url|escape:'html':'UTF-8'}" class="form-inline">
    <button type="submit" name="submitMzcBrevoRetryAll" class="btn btn-default"{if !$mzcbrevo.problems} disabled{/if}>
      <i class="icon-refresh"></i> {l s='Retry all failed and dead' d='Modules.Mzcbrevo.Admin'}
    </button>
    {if $mzcbrevo.paused_seconds > 0}
      <button type="submit" name="submitMzcBrevoUnpause" class="btn btn-warning">
        <i class="icon-play"></i> {l s='End the pause now (%seconds% s left)' sprintf=['%seconds%' => $mzcbrevo.paused_seconds] d='Modules.Mzcbrevo.Admin'}
      </button>
    {/if}
  </form>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-warning-sign"></i> {l s='Failed and dead jobs' d='Modules.Mzcbrevo.Admin'}</div>
  <p class="help-block">
    {l s='failed: Brevo refused the data (4xx), a retry only helps once the cause is fixed. dead: still failing after 6 attempts over about 7.5 hours (Brevo down, network, a bad key).' d='Modules.Mzcbrevo.Admin'}
  </p>
  {if $mzcbrevo.problems}
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>{l s='Type' d='Modules.Mzcbrevo.Admin'}</th>
          <th>{l s='Object id' d='Modules.Mzcbrevo.Admin'}</th>
          <th>{l s='Status' d='Modules.Mzcbrevo.Admin'}</th>
          <th>{l s='Attempts' d='Modules.Mzcbrevo.Admin'}</th>
          <th>{l s='Error' d='Modules.Mzcbrevo.Admin'}</th>
          <th>{l s='Updated' d='Modules.Mzcbrevo.Admin'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$mzcbrevo.problems item=job}
          <tr>
            <td>{$job.id_mzcbrevo_queue|intval}</td>
            <td>{$job.type|escape:'html':'UTF-8'}</td>
            <td>{$job.entity_id|intval}</td>
            <td>{$job.status|escape:'html':'UTF-8'}</td>
            <td>{$job.attempts|intval}</td>
            <td style="max-width: 480px;">
              {$job.last_error|escape:'html':'UTF-8'}
              <details>
                <summary>{l s='Details' d='Modules.Mzcbrevo.Admin'}</summary>
                <pre style="max-height: 300px; overflow: auto;">{$job.payload_pretty|escape:'html':'UTF-8'}</pre>
                {if $job.request_pretty}<pre style="max-height: 300px; overflow: auto;">{$job.request_pretty|escape:'html':'UTF-8'}</pre>{/if}
              </details>
            </td>
            <td>{$job.updated_at|escape:'html':'UTF-8'}</td>
            <td>
              <form method="post" action="{$mzcbrevo.action_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="id_job" value="{$job.id_mzcbrevo_queue|intval}">
                <button type="submit" name="submitMzcBrevoRetry" class="btn btn-default btn-sm"><i class="icon-refresh"></i> {l s='Retry' d='Modules.Mzcbrevo.Admin'}</button>
              </form>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p>{l s='None.' d='Modules.Mzcbrevo.Admin'}</p>
  {/if}
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-inbox"></i> {l s='Reports from Brevo' d='Modules.Mzcbrevo.Admin'}</div>
  <p class="help-block">{l s='What Brevo posted back to the webhook: results of order batches and contact imports, unsubscribes.' d='Modules.Mzcbrevo.Admin'}</p>
  {if $mzcbrevo.imports}
    <table class="table">
      <thead><tr><th>{l s='Received' d='Modules.Mzcbrevo.Admin'}</th><th>{l s='Kind' d='Modules.Mzcbrevo.Admin'}</th><th>{l s='Content' d='Modules.Mzcbrevo.Admin'}</th></tr></thead>
      <tbody>
        {foreach from=$mzcbrevo.imports item=report}
          <tr>
            <td>{$report.created_at|escape:'html':'UTF-8'}</td>
            <td>{$report.kind|escape:'html':'UTF-8'}</td>
            <td><code style="word-break: break-all;">{$report.payload|truncate:600:'…'|escape:'html':'UTF-8'}</code></td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p>{l s='None yet.' d='Modules.Mzcbrevo.Admin'}</p>
  {/if}
</div>
