{*
 * MZC Brevo — webhook URLs and the cron command, shown in the settings form.
 *}
<p><strong>{l s='Unsubscribes' d='Modules.Mzcbrevo.Admin'}</strong> — {l s='Brevo → Settings → Webhooks → Marketing, event "Unsubscribed". Sets newsletter = 0 in the shop.' d='Modules.Mzcbrevo.Admin'}</p>
<p><code style="word-break: break-all;">{$mzcbrevo.unsubscribe_url|escape:'html':'UTF-8'}</code></p>
<p><strong>{l s='Batch results' d='Modules.Mzcbrevo.Admin'}</strong> — {l s='Passed by the historical import as notifyUrl; nothing to set up in Brevo.' d='Modules.Mzcbrevo.Admin'}</p>
<p><code style="word-break: break-all;">{$mzcbrevo.batch_url|escape:'html':'UTF-8'}</code></p>
<p><strong>{l s='Cron, every minute' d='Modules.Mzcbrevo.Admin'}</strong> — {l s='a scheduled task in Dokploy, in the shop\'s root directory:' d='Modules.Mzcbrevo.Admin'}</p>
<p><code>{$mzcbrevo.cron|escape:'html':'UTF-8'}</code></p>
