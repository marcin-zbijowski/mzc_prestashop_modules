{*
 * MZC Brevo — connection test, attribute sync and test job.
 *}
<div class="panel">
  <div class="panel-heading"><i class="icon-wrench"></i> {l s='Tools' d='Modules.Mzcbrevo.Admin'}</div>
  <form method="post" action="{$mzcbrevo.action_url|escape:'html':'UTF-8'}" class="form-inline">
    <button type="submit" name="submitMzcBrevoTest" class="btn btn-default"{if !$mzcbrevo.has_key} disabled{/if}>
      <i class="icon-plug"></i> {l s='Test connection' d='Modules.Mzcbrevo.Admin'}
    </button>
    <button type="submit" name="submitMzcBrevoAttributes" class="btn btn-default"{if !$mzcbrevo.has_key} disabled{/if}>
      <i class="icon-list"></i> {l s='Synchronize attributes' d='Modules.Mzcbrevo.Admin'}
    </button>
    <button type="submit" name="submitMzcBrevoPing" class="btn btn-default"{if !$mzcbrevo.has_key} disabled{/if}>
      <i class="icon-time"></i> {l s='Queue a test job' d='Modules.Mzcbrevo.Admin'}
    </button>
  </form>
  <p class="help-block">
    {l s='Synchronize attributes creates the contact attributes this module writes (ORDERS_COUNT, OCCASION_TYPE, ...) in Brevo. Run it once after saving the key, and again after adding a customer group. The test job goes through the queue and the worker, without any shop data.' d='Modules.Mzcbrevo.Admin'}
  </p>
</div>
