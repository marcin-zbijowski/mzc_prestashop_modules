<?php

declare(strict_types=1);

namespace Mzc\Brevo\Admin;

use Configuration;
use Context;
use Db;
use HelperForm;
use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\TransportException;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Installer;
use Mzc\Brevo\Occasion\OccasionDetector;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Mzc\Brevo\Queue\Enqueuer;
use Mzc\Brevo\Queue\QueueRepository;
use Mzc\Brevo\Sync\AttributeSynchronizer;
use Tools;

/**
 * The module's configuration page, in four tabs:
 *
 *   settings   API key and connection test, lists and double opt-in, the stream
 *              switches, cart thresholds, id prefixes, webhook URLs
 *   mapping    order state → Brevo status; category → occasion
 *   queue      counts per status, failed and dead jobs with their error, "retry"
 *   occasions  every product with a detected occasion and where it came from, to
 *              review the catalogue once and fix the exceptions on the product page
 *
 * Every form posts to the module's own configure route; the core refuses the request
 * without a valid admin token before this class runs.
 */
class ConfigurationPage
{
    public const TABS = ['settings', 'mapping', 'queue', 'occasions'];

    /** Brevo order statuses offered in the mapping. Brevo does not document a list. */
    public const BREVO_STATUSES = ['completed', 'pending', 'processing', 'shipped', 'cancelled', 'refunded'];

    /** An "oldest pending job" older than this means the cron has stopped. */
    public const STALE_QUEUE_SECONDS = 1800;

    private \Mzcbrevo $module;
    private Context $context;
    private Config $config;
    private int $idShop;

    public function __construct(\Mzcbrevo $module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
        $this->idShop = (int) $this->context->shop->id;
        $this->config = new Config($this->idShop);
    }

    public function render(): string
    {
        $tab = (string) Tools::getValue('mzcbrevo_tab', 'settings');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'settings';
        $html = $this->process();

        $html .= $this->warnings();
        $html .= $this->fetch('tabs.tpl', [
            'tabs' => [
                'settings' => $this->t('Settings'),
                'mapping' => $this->t('Mapping'),
                'queue' => $this->t('Queue'),
                'occasions' => $this->t('Occasions'),
            ],
            'current' => $tab,
            'base_url' => $this->baseUrl(),
        ]);

        switch ($tab) {
            case 'mapping':
                return $html . $this->mappingForm();
            case 'queue':
                return $html . $this->queuePage();
            case 'occasions':
                return $html . $this->occasionsPage();
            default:
                return $html . $this->settingsForm() . $this->actionsPanel();
        }
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    private function process(): string
    {
        if (Tools::isSubmit('submitMzcBrevoSettings')) {
            return $this->saveSettings();
        }

        if (Tools::isSubmit('submitMzcBrevoMapping')) {
            return $this->saveMapping();
        }

        if (Tools::isSubmit('submitMzcBrevoTest')) {
            return $this->testConnection();
        }

        if (Tools::isSubmit('submitMzcBrevoAttributes')) {
            return $this->syncAttributes();
        }

        if (Tools::isSubmit('submitMzcBrevoPing')) {
            (new Enqueuer($this->config, $this->idShop))->ping();

            return $this->module->displayConfirmation($this->t('A test job is in the queue. The next worker run sends it; see the Queue tab.'));
        }

        if (Tools::isSubmit('submitMzcBrevoRetry')) {
            (new QueueRepository())->retry((int) Tools::getValue('id_job'));

            return $this->module->displayConfirmation($this->t('The job is back in the queue.'));
        }

        if (Tools::isSubmit('submitMzcBrevoRetryAll')) {
            $count = (new QueueRepository())->retryAll($this->idShop);

            return $this->module->displayConfirmation($this->t('%count% job(s) are back in the queue.', ['%count%' => $count]));
        }

        if (Tools::isSubmit('submitMzcBrevoUnpause')) {
            $this->config->set(Config::PAUSED_UNTIL, 0);

            return $this->module->displayConfirmation($this->t('The worker is no longer paused.'));
        }

        return '';
    }

    private function saveSettings(): string
    {
        $errors = [];
        $key = trim((string) Tools::getValue(Config::API_KEY));

        // The key field is never filled in: empty means "keep the stored key".
        if ($key !== '') {
            $this->config->setApiKey($key);
        } elseif (Tools::getValue('MZCBREVO_API_KEY_CLEAR')) {
            $this->config->setApiKey('');
        }

        foreach ([Config::LIST_NEWSLETTER, Config::LIST_CUSTOMERS, Config::DOI_TEMPLATE] as $intKey) {
            $this->config->set($intKey, max(0, (int) Tools::getValue($intKey)));
        }

        $redirect = trim((string) Tools::getValue(Config::DOI_REDIRECT));

        if ($redirect !== '' && !\Validate::isAbsoluteUrl($redirect)) {
            $errors[] = $this->t('The double opt-in redirect must be a full URL, starting with https://.');
        } else {
            $this->config->set(Config::DOI_REDIRECT, $redirect);
        }

        foreach (array_merge(Config::STREAMS, [Config::DOI_ACCOUNT, Config::CART_REQUIRE_CONSENT]) as $boolKey) {
            $this->config->set($boolKey, Tools::getValue($boolKey) ? 1 : 0);
        }

        $this->config->set(Config::ABANDON_MINUTES, max(5, (int) Tools::getValue(Config::ABANDON_MINUTES)));
        $this->config->set(Config::ABANDON_MIN_TOTAL, max(0, (float) str_replace(',', '.', (string) Tools::getValue(Config::ABANDON_MIN_TOTAL))));

        foreach ([Config::EXT_ID_PREFIX, Config::ID_PREFIX] as $prefixKey) {
            $prefix = trim((string) Tools::getValue($prefixKey));

            if (!preg_match('/^[A-Za-z0-9_\-]{0,20}$/', $prefix)) {
                $errors[] = $this->t('Prefixes may contain only letters, digits, "_" and "-", at most 20 characters.');
            } else {
                $this->config->set($prefixKey, $prefix);
            }
        }

        $secret = trim((string) Tools::getValue(Config::WEBHOOK_SECRET));

        if (strlen($secret) < 16) {
            $errors[] = $this->t('The webhook secret must be at least 16 characters long.');
        } else {
            $this->config->set(Config::WEBHOOK_SECRET, $secret);
        }

        if ($errors) {
            return $this->module->displayError(implode('<br>', array_map('htmlspecialchars', $errors)));
        }

        return $this->module->displayConfirmation($this->t('Settings saved.'));
    }

    private function saveMapping(): string
    {
        $statusMap = [];

        foreach ($this->orderStates() as $state) {
            $value = (string) Tools::getValue('status_' . (int) $state['id_order_state']);
            $statusMap[(int) $state['id_order_state']] = in_array($value, self::BREVO_STATUSES, true) ? $value : '';
        }

        $categoryMap = [];

        foreach ((new ShopData($this->idShop))->categories() as $category) {
            $value = (string) Tools::getValue('occasion_' . (int) $category['id_category']);

            if (isset(OccasionDetector::BREVO_VALUES[$value])) {
                $categoryMap[(int) $category['id_category']] = $value;
            }
        }

        $this->config->set(Config::STATUS_MAP, json_encode($statusMap));
        $this->config->set(Config::CATEGORY_OCCASIONS, json_encode($categoryMap));

        return $this->module->displayConfirmation($this->t('Mapping saved.'));
    }

    private function testConnection(): string
    {
        if ($this->config->apiKey() === '') {
            return $this->module->displayError($this->t('Save an API key first.'));
        }

        try {
            $response = (new BrevoClient($this->config->apiKey()))->get('account');
        } catch (TransportException $e) {
            return $this->module->displayError($this->t('Brevo could not be reached: %error%', ['%error%' => $e->getMessage()]));
        }

        if (!$response->isSuccess()) {
            return $this->module->displayError($this->t('Brevo refused the request: %error%', ['%error%' => $response->errorMessage()]));
        }

        $plans = [];

        foreach ((array) ($response->data['plan'] ?? []) as $plan) {
            $plans[] = (string) ($plan['type'] ?? '') . (isset($plan['credits']) ? ' (' . $plan['credits'] . ')' : '');
        }

        return $this->module->displayConfirmation($this->t('Connected to the Brevo account %email% (%company%). Plan: %plan%.', [
            '%email%' => htmlspecialchars((string) ($response->data['email'] ?? '')),
            '%company%' => htmlspecialchars((string) ($response->data['companyName'] ?? '')),
            '%plan%' => htmlspecialchars(implode(', ', $plans) ?: '?'),
        ]));
    }

    private function syncAttributes(): string
    {
        if ($this->config->apiKey() === '') {
            return $this->module->displayError($this->t('Save an API key first.'));
        }

        $groups = [];

        foreach (\Group::getGroups((int) $this->context->language->id, $this->idShop) as $group) {
            $groups[(int) $group['id_group']] = (string) $group['name'];
        }

        $result = (new AttributeSynchronizer(new BrevoClient($this->config->apiKey())))->sync($groups);
        $list = '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $result['lines'])) . '</li></ul>';

        return $result['ok']
            ? $this->module->displayConfirmation($this->t('Attributes synchronized.') . $list)
            : $this->module->displayError($this->t('Some attributes could not be synchronized.') . $list);
    }

    // -------------------------------------------------------------------------
    // Warnings shown above every tab
    // -------------------------------------------------------------------------

    private function warnings(): string
    {
        $html = '';
        $queue = new QueueRepository();
        $age = $queue->oldestPendingAge($this->idShop);

        if ($this->config->apiKey() === '') {
            $html .= $this->module->displayWarning($this->t('No API key yet: nothing is sent to Brevo.'));
        }

        if ($age !== null && $age > self::STALE_QUEUE_SECONDS) {
            $html .= $this->module->displayWarning($this->t(
                'The oldest job has been waiting for %minutes% minutes. Is the cron running? It should run "%command%" every minute.',
                ['%minutes%' => (int) floor($age / 60), '%command%' => 'php bin/console mzcbrevo:worker --limit=200 --detect-abandoned']
            ));
        }

        $paused = $this->config->getInt(Config::PAUSED_UNTIL) - time();

        if ($paused > 0) {
            $html .= $this->module->displayWarning($this->t(
                'The worker is paused for another %seconds% s after Brevo answered with a rate limit, an outage or a refused key. See the Queue tab.',
                ['%seconds%' => $paused]
            ));
        }

        $counts = $queue->counts($this->idShop);

        if ($counts['dead'] > 0) {
            $html .= $this->module->displayWarning($this->t(
                '%count% job(s) gave up after every retry. See the Queue tab.',
                ['%count%' => $counts['dead']]
            ));
        }

        if ($this->config->getBool(Config::STREAM_CONTACTS) && Configuration::get('NW_VERIFICATION_EMAIL')) {
            $html .= $this->module->displayWarning($this->t(
                'ps_emailsubscription sends its own verification e-mail, and Brevo sends the double opt-in: a footer sign-up is confirmed twice. Switch off "Would you like to send a verification email after subscription?" in ps_emailsubscription.'
            ));
        }

        if ($this->config->getBool(Config::STREAM_CONTACTS)
            && (!$this->config->getInt(Config::LIST_NEWSLETTER) || !$this->config->getInt(Config::LIST_CUSTOMERS))) {
            $html .= $this->module->displayWarning($this->t('Contacts are switched on, but the newsletter and customers list ids are not both set.'));
        }

        if (\Module::isInstalled('sendinblue') && \Module::isEnabled('sendinblue')) {
            $html .= $this->module->displayWarning($this->t(
                'The official Brevo plugin (sendinblue) is still active. Disable it before switching streams on here, or every contact and order is sent twice.'
            ));
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Settings tab
    // -------------------------------------------------------------------------

    private function settingsForm(): string
    {
        $yesNo = fn (string $id): array => [
            ['id' => $id . '_on', 'value' => 1, 'label' => $this->t('Yes')],
            ['id' => $id . '_off', 'value' => 0, 'label' => $this->t('No')],
        ];
        $switch = fn (string $name, string $label, string $desc = ''): array => [
            'type' => 'switch',
            'label' => $label,
            'name' => $name,
            'is_bool' => true,
            'desc' => $desc,
            'values' => $yesNo($name),
        ];
        $submit = ['title' => $this->t('Save'), 'name' => 'submitMzcBrevoSettings'];

        $forms = [
            ['form' => [
                'legend' => ['title' => $this->t('Connection'), 'icon' => 'icon-plug'],
                'input' => [
                    [
                        'type' => 'password',
                        'label' => $this->t('API key'),
                        'name' => Config::API_KEY,
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->config->apiKey() !== ''
                            ? $this->t('A key is stored (ending in …%end%). Leave empty to keep it.', ['%end%' => substr($this->config->apiKey(), -4)])
                            : $this->t('Brevo → SMTP & API → API keys. Stored encrypted.'),
                    ],
                    $switch('MZCBREVO_API_KEY_CLEAR', $this->t('Remove the stored key')),
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Lists and double opt-in'), 'icon' => 'icon-envelope'],
                'input' => [
                    ['type' => 'text', 'label' => $this->t('Newsletter list id'), 'name' => Config::LIST_NEWSLETTER, 'class' => 'fixed-width-sm',
                        'desc' => $this->t('Contacts with marketing consent only.')],
                    ['type' => 'text', 'label' => $this->t('Customers list id'), 'name' => Config::LIST_CUSTOMERS, 'class' => 'fixed-width-sm',
                        'desc' => $this->t('Every customer, for segments and exclusions. Never use it to send marketing.')],
                    ['type' => 'text', 'label' => $this->t('Double opt-in template id'), 'name' => Config::DOI_TEMPLATE, 'class' => 'fixed-width-sm',
                        'desc' => $this->t('A Brevo template with the "double opt-in" tag, in Polish.')],
                    ['type' => 'text', 'label' => $this->t('Redirect after confirmation'), 'name' => Config::DOI_REDIRECT,
                        'desc' => $this->t('The shop\'s thank-you page, e.g. a CMS page.')],
                    $switch(Config::DOI_ACCOUNT, $this->t('Double opt-in for account consent'), $this->t(
                        'Off: the newsletter box ticked at registration or checkout adds the contact to the newsletter list directly. On: it sends the double opt-in e-mail instead. The footer form always uses double opt-in.'
                    )),
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Streams'), 'icon' => 'icon-exchange'],
                'description' => $this->t('Switch the streams on one at a time, after the historical import: contacts, then orders, then carts. A stream that is off queues nothing.'),
                'input' => [
                    $switch(Config::STREAM_CONTACTS, $this->t('Contacts'), $this->t('Accounts, consent changes, footer sign-ups, unsubscribes, deletions.')),
                    $switch(Config::STREAM_ORDERS, $this->t('Orders'), $this->t('Orders, status changes, order_completed and order_status_changed events.')),
                    $switch(Config::STREAM_CARTS, $this->t('Carts'), $this->t('cart_updated and cart_abandoned events.')),
                    $switch(Config::STREAM_CATALOG, $this->t('Catalogue'), $this->t('Products and categories, for product blocks in e-mails.')),
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Abandoned carts'), 'icon' => 'icon-shopping-cart'],
                'input' => [
                    ['type' => 'text', 'label' => $this->t('Abandoned after'), 'name' => Config::ABANDON_MINUTES, 'class' => 'fixed-width-sm',
                        'suffix' => $this->t('minutes'), 'desc' => $this->t('Without a change and without an order. At least 5.')],
                    ['type' => 'text', 'label' => $this->t('Minimum cart value'), 'name' => Config::ABANDON_MIN_TOTAL, 'class' => 'fixed-width-sm',
                        'suffix' => $this->context->currency->iso_code, 'desc' => $this->t('Products incl. tax, without shipping. 0 = any cart.')],
                    $switch(Config::CART_REQUIRE_CONSENT, $this->t('Only with marketing consent'), $this->t(
                        'Recommended. In Poland an abandoned-cart e-mail is usually treated as commercial information. Sending without consent is a legal decision, not a technical one.'
                    )),
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Identifiers and webhooks'), 'icon' => 'icon-key'],
                'input' => [
                    ['type' => 'text', 'label' => $this->t('Contact ext_id prefix'), 'name' => Config::EXT_ID_PREFIX, 'class' => 'fixed-width-md',
                        'desc' => $this->t('ext_id = prefix + id_customer, e.g. ps_123. Different per shop when two shops share one Brevo account.')],
                    ['type' => 'text', 'label' => $this->t('Product, category and order id prefix'), 'name' => Config::ID_PREFIX, 'class' => 'fixed-width-md',
                        'desc' => $this->t('Empty for one shop per Brevo account. Changing it later makes Brevo see a new catalogue.')],
                    ['type' => 'text', 'label' => $this->t('Webhook secret'), 'name' => Config::WEBHOOK_SECRET,
                        'desc' => $this->t('Part of the webhook URLs below. Changing it breaks the webhooks set up in Brevo until they are updated.')],
                    ['type' => 'html', 'label' => $this->t('Webhook URLs'), 'name' => 'mzcbrevo_webhooks', 'html_content' => $this->webhookHelp()],
                ],
                'submit' => $submit,
            ]],
        ];

        $values = [
            Config::API_KEY => '',
            'MZCBREVO_API_KEY_CLEAR' => 0,
        ];

        foreach (array_keys(Config::DEFAULTS) as $key) {
            if ($key !== Config::API_KEY) {
                $values[$key] = $this->config->get($key);
            }
        }

        $values[Config::WEBHOOK_SECRET] = $this->config->get(Config::WEBHOOK_SECRET);

        return $this->helperForm('submitMzcBrevoSettings', $values)->generateForm($forms);
    }

    private function webhookHelp(): string
    {
        $unsubscribe = $this->webhookUrl('unsubscribe');

        return $this->fetch('webhooks.tpl', [
            'unsubscribe_url' => $unsubscribe,
            'batch_url' => $this->webhookUrl('batch'),
            'cron' => 'php bin/console mzcbrevo:worker --limit=200 --detect-abandoned',
        ]);
    }

    private function actionsPanel(): string
    {
        return $this->fetch('actions.tpl', ['action_url' => $this->baseUrl(), 'has_key' => $this->config->apiKey() !== '']);
    }

    // -------------------------------------------------------------------------
    // Mapping tab
    // -------------------------------------------------------------------------

    private function mappingForm(): string
    {
        $statusOptions = [['id' => '', 'name' => $this->t('— do not send —')]];

        foreach (self::BREVO_STATUSES as $status) {
            $statusOptions[] = ['id' => $status, 'name' => $status];
        }

        $occasionOptions = [['id' => '', 'name' => $this->t('— none —')]];

        foreach (OccasionDetector::LABELS as $code => $label) {
            $occasionOptions[] = ['id' => $code, 'name' => $label];
        }

        $statusInputs = [];
        $values = [];
        $statusMap = $this->config->statusMap();

        foreach ($this->orderStates() as $state) {
            $id = (int) $state['id_order_state'];
            $statusInputs[] = [
                'type' => 'select',
                'label' => (string) $state['name'] . ((int) $state['paid'] ? ' ✓' : ''),
                'name' => 'status_' . $id,
                'options' => ['query' => $statusOptions, 'id' => 'id', 'name' => 'name'],
            ];
            $values['status_' . $id] = $statusMap[$id] ?? '';
        }

        $categoryInputs = [];
        $categoryMap = $this->config->categoryOccasions();

        foreach ((new ShopData($this->idShop))->categories() as $category) {
            $id = (int) $category['id_category'];
            $categoryInputs[] = [
                'type' => 'select',
                'label' => str_repeat('— ', max(0, (int) $category['level_depth'] - 2)) . (string) $category['name'],
                'name' => 'occasion_' . $id,
                'options' => ['query' => $occasionOptions, 'id' => 'id', 'name' => 'name'],
            ];
            $values['occasion_' . $id] = $categoryMap[$id] ?? '';
        }

        $submit = ['title' => $this->t('Save'), 'name' => 'submitMzcBrevoMapping'];
        $forms = [
            ['form' => [
                'legend' => ['title' => $this->t('Order state → Brevo status'), 'icon' => 'icon-exchange'],
                'description' => $this->t('✓ marks paid states. Brevo does not document its status values: check the revenue in Brevo\'s eCommerce dashboard on the test account before relying on this. Customer statistics (ORDERS_COUNT, TOTAL_SPENT) count paid orders whatever is chosen here.'),
                'input' => $statusInputs,
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Category → occasion'), 'icon' => 'icon-gift'],
                'description' => $this->t('Step 2 of the occasion detection. A product\'s default category is checked first, then its other categories. A manual choice on the product page wins over this; the product name is checked only when no category matches.'),
                'input' => $categoryInputs,
                'submit' => $submit,
            ]],
        ];

        return $this->helperForm('submitMzcBrevoMapping', $values, 'mapping')->generateForm($forms);
    }

    /** @return array<int,array{id_order_state:int,name:string,paid:int}> */
    private function orderStates(): array
    {
        return Db::getInstance()->executeS(
            'SELECT os.`id_order_state`, os.`paid`, osl.`name`
            FROM `' . _DB_PREFIX_ . 'order_state` os
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON osl.`id_order_state` = os.`id_order_state`
                AND osl.`id_lang` = ' . (int) $this->context->language->id . '
            WHERE os.`deleted` = 0
            ORDER BY os.`id_order_state`'
        ) ?: [];
    }

    // -------------------------------------------------------------------------
    // Queue tab
    // -------------------------------------------------------------------------

    private function queuePage(): string
    {
        $queue = new QueueRepository();
        $problems = [];

        foreach ($queue->problems(100, $this->idShop) as $row) {
            $row['payload_pretty'] = self::pretty((string) $row['payload']);
            $row['request_pretty'] = self::pretty((string) $row['last_request']);
            $problems[] = $row;
        }

        $age = $queue->oldestPendingAge($this->idShop);

        return $this->fetch('queue.tpl', [
            'counts' => $queue->counts($this->idShop),
            'problems' => $problems,
            'oldest_minutes' => $age !== null ? (int) floor($age / 60) : null,
            'paused_seconds' => max(0, $this->config->getInt(Config::PAUSED_UNTIL) - time()),
            'action_url' => $this->baseUrl('queue'),
            'imports' => Db::getInstance()->executeS(
                'SELECT `kind`, `payload`, `created_at` FROM `' . _DB_PREFIX_ . Installer::WEBHOOK_LOG_TABLE . '`
                WHERE `id_shop` = ' . $this->idShop . ' ORDER BY `id_mzcbrevo_webhook_log` DESC LIMIT 20'
            ) ?: [],
        ]);
    }

    private static function pretty(string $json): string
    {
        $data = json_decode($json, true);

        return $data === null ? $json : (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // -------------------------------------------------------------------------
    // Occasions tab
    // -------------------------------------------------------------------------

    private function occasionsPage(): string
    {
        $idLang = (int) $this->context->language->id;
        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product`, pl.`name`, p.`reference`, ps.`active`
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $this->idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = p.`id_product`
                AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $this->idShop . '
            ORDER BY pl.`name` ASC'
        ) ?: [];

        $extractor = new OccasionExtractor($this->config->categoryOccasions(), $idLang);
        $detected = $extractor->forProducts(array_column($rows, 'id_product'));
        $showAll = (bool) Tools::getValue('mzcbrevo_all');
        $products = [];
        $counts = array_fill_keys(array_keys(OccasionDetector::LABELS), 0);
        $none = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $occasion = $detected[$id] ?? null;

            if ($occasion === null) {
                ++$none;

                if (!$showAll) {
                    continue;
                }
            } else {
                ++$counts[$occasion['occasion']];
            }

            $products[] = [
                'id_product' => $id,
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
                'active' => (int) $row['active'],
                'occasion' => $occasion ? OccasionDetector::LABELS[$occasion['occasion']] : '',
                'source' => $occasion ? $this->module->sourceLabel($occasion['source']) : '',
                'edit_url' => $this->productEditUrl($id),
            ];
        }

        $countLabels = [];

        foreach ($counts as $code => $count) {
            $countLabels[OccasionDetector::LABELS[$code]] = $count;
        }

        return $this->fetch('occasions.tpl', [
            'products' => $products,
            'counts' => $countLabels,
            'none' => $none,
            'show_all' => $showAll,
            'toggle_url' => $this->baseUrl('occasions') . ($showAll ? '' : '&mzcbrevo_all=1'),
        ]);
    }

    private function productEditUrl(int $idProduct): string
    {
        try {
            return (string) $this->module->get('router')->generate('admin_products_edit', ['productId' => $idProduct]);
        } catch (\Throwable $e) {
            return $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => $idProduct, 'updateproduct' => 1]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function helperForm(string $submitAction, array $values, string $tab = 'settings'): HelperForm
    {
        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->table = $this->module->name;
        $helper->name_controller = $this->module->name;
        $helper->submit_action = $submitAction;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->baseUrl($tab);
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->tpl_vars = [
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper;
    }

    /**
     * URL of this page, carrying a query string already. The Symfony router adds the
     * CSRF `_token`; the legacy URL is the fallback.
     */
    public function baseUrl(?string $tab = null): string
    {
        $url = null;
        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();

        if ($container !== null) {
            try {
                $url = (string) $container->get('router')->generate('admin_module_configure_action', ['module_name' => $this->module->name]);
            } catch (\Throwable $e) {
                $url = null;
            }
        }

        $url = $url ?? \AdminController::$currentIndex . '&configure=' . $this->module->name . '&token=' . Tools::getAdminTokenLite('AdminModules');

        return $url . ($tab !== null ? (strpos($url, '?') === false ? '?' : '&') . 'mzcbrevo_tab=' . $tab : '');
    }

    private function webhookUrl(string $kind): string
    {
        return $this->context->link->getModuleLink(
            $this->module->name,
            'webhook',
            ['kind' => $kind, 'token' => $this->config->get(Config::WEBHOOK_SECRET)],
            true,
            null,
            $this->idShop
        );
    }

    private function fetch(string $template, array $vars): string
    {
        $this->context->smarty->assign(['mzcbrevo' => $vars]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/' . $template);
    }

    private function t(string $id, array $parameters = []): string
    {
        return $this->module->t($id, $parameters);
    }
}
