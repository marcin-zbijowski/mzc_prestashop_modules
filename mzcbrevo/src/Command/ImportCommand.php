<?php

declare(strict_types=1);

namespace Mzc\Brevo\Command;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Json;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Api\TransportException;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopContext;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\CatalogMapper;
use Mzc\Brevo\Mapper\CustomerMapper;
use Mzc\Brevo\Mapper\OrderMapper;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php bin/console mzcbrevo:import [--dry-run] [--since=YYYY-MM-DD] [--step=...] [--shop=1]`
 *
 * One-off: sends the shop's whole history before the live streams are switched on,
 * so segments and anniversary campaigns work from day one.
 *
 *   1. categories, then products            /v3/categories/batch, /v3/products/batch
 *   2. contacts of every customer            /v3/contacts/import — all to the
 *      "customers" list, those with consent (and footer-only subscribers) also to
 *      "newsletter"; attributes include the order statistics and OCCASION_*
 *   3. orders, 1000 per batch                /v3/orders/status/batch, historical:
 *      true, which starts no automation; Brevo reports each batch to the webhook
 *
 * Idempotent: every endpoint used is an upsert, so a second run overwrites, never
 * duplicates. --dry-run writes the request bodies as JSON files and sends nothing.
 *
 * Talks to Brevo directly, not through the queue: tens of thousands of rows in one
 * go, and an operator watching. A 429 or 5xx waits and tries the same batch again.
 */
class ImportCommand extends Command
{
    public const STEPS = ['categories', 'products', 'contacts', 'orders'];

    public const CONTACTS_PER_IMPORT = 2000;
    public const ORDERS_PER_BATCH = 1000;
    public const CATALOG_PER_BATCH = 100;
    public const MAX_TRIES = 6;

    private OutputInterface $output;
    private ?BrevoClient $client = null;
    private ?string $dumpDir = null;
    private Config $config;
    private ShopData $data;
    private int $failures = 0;

    protected function configure(): void
    {
        $this
            ->setName('mzcbrevo:import')
            ->setDescription('One-off import of categories, products, contacts and historical orders to Brevo.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write the request bodies to JSON files instead of sending them')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only orders placed / customers changed on or after this date (YYYY-MM-DD)')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Comma-separated steps: ' . implode(',', self::STEPS), implode(',', self::STEPS))
            ->addOption('shop', null, InputOption::VALUE_REQUIRED, 'Shop id', (string) \Configuration::get('PS_SHOP_DEFAULT'))
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Directory for --dry-run files (default: var/mzcbrevo-import-<date>)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $idShop = (int) $input->getOption('shop');
        $since = $input->getOption('since');

        if ($since !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since)) {
            $output->writeln('<error>--since must be YYYY-MM-DD</error>');

            return Command::INVALID;
        }

        $steps = array_values(array_intersect(self::STEPS, array_map('trim', explode(',', (string) $input->getOption('step')))));

        if (!$steps) {
            $output->writeln('<error>No known step in --step. Steps: ' . implode(', ', self::STEPS) . '</error>');

            return Command::INVALID;
        }

        ShopContext::switchTo($idShop);
        $this->config = new Config($idShop);
        $this->data = new ShopData($idShop);

        if ($input->getOption('dry-run')) {
            $this->dumpDir = rtrim((string) ($input->getOption('output') ?: _PS_ROOT_DIR_ . '/var/mzcbrevo-import-' . date('Ymd-His')), '/');

            if (!is_dir($this->dumpDir) && !mkdir($this->dumpDir, 0750, true)) {
                $output->writeln('<error>Cannot create ' . $this->dumpDir . '</error>');

                return Command::FAILURE;
            }

            $output->writeln('Dry run: request bodies go to ' . $this->dumpDir);
        } else {
            if ($this->config->apiKey() === '') {
                $output->writeln('<error>No API key for shop ' . $idShop . '.</error>');

                return Command::FAILURE;
            }

            $this->client = new BrevoClient($this->config->apiKey());
        }

        $since = $since !== null ? $since . ' 00:00:00' : null;

        foreach ($steps as $step) {
            $output->writeln('<info>== ' . $step . '</info>');

            switch ($step) {
                case 'categories':
                    $this->importCategories();
                    break;
                case 'products':
                    $this->importProducts();
                    break;
                case 'contacts':
                    if (!$this->importContacts($since)) {
                        return Command::FAILURE;
                    }
                    break;
                case 'orders':
                    $this->importOrders($since);
                    break;
            }
        }

        $output->writeln($this->failures ? '<comment>Finished with ' . $this->failures . ' refused request(s), see above.</comment>' : '<info>Finished.</info>');

        return $this->failures ? Command::FAILURE : Command::SUCCESS;
    }

    private function importCategories(): void
    {
        $brevoId = [$this->config, 'brevoId'];
        $items = [];

        foreach ($this->data->categories() as $row) {
            $category = $this->data->category((int) $row['id_category']);

            if ($category !== null) {
                $items[] = CatalogMapper::category($category, $brevoId);
            }
        }

        foreach (array_chunk($items, self::CATALOG_PER_BATCH) as $i => $chunk) {
            $this->send('categories/batch', ['categories' => $chunk, 'updateEnabled' => true], 'categories-' . ($i + 1));
        }

        $this->output->writeln(count($items) . ' categories');
    }

    private function importProducts(): void
    {
        $brevoId = [$this->config, 'brevoId'];
        $after = 0;
        $count = 0;
        $batch = 0;

        while ($ids = $this->data->productIds($after, self::CATALOG_PER_BATCH)) {
            $items = [];

            foreach ($ids as $id) {
                $product = $this->data->product($id);

                if ($product !== null && (string) $product['name'] !== '') {
                    $items[] = CatalogMapper::product($product, $brevoId);
                }
            }

            $after = (int) end($ids);

            if ($items) {
                $this->send('products/batch', ['products' => $items, 'updateEnabled' => true], 'products-' . ++$batch);
                $count += count($items);
            }
        }

        $this->output->writeln($count . ' products');
    }

    private function importContacts(?string $since): bool
    {
        $customersList = $this->config->getInt(Config::LIST_CUSTOMERS);
        $newsletterList = $this->config->getInt(Config::LIST_NEWSLETTER);

        if ($customersList <= 0 || $newsletterList <= 0) {
            $this->output->writeln('<error>Set both list ids (newsletter, customers) in the module settings first.</error>');

            return false;
        }

        $occasions = new OccasionExtractor($this->config->categoryOccasions(), $this->data->idLang());
        $notifyUrl = $this->webhookUrl('import');
        $all = [];
        $consenting = [];
        $seen = [];
        $after = 0;
        $batch = 0;

        $flush = function (bool $force) use (&$all, &$consenting, &$batch, $customersList, $newsletterList, $notifyUrl): void {
            if ($all && ($force || count($all) >= self::CONTACTS_PER_IMPORT)) {
                $this->send('contacts/import', self::importBody($all, [$customersList], $notifyUrl), 'contacts-' . ++$batch);
                $all = [];
            }

            if ($consenting && ($force || count($consenting) >= self::CONTACTS_PER_IMPORT)) {
                $this->send('contacts/import', self::importBody($consenting, [$newsletterList], $notifyUrl), 'newsletter-' . ++$batch);
                $consenting = [];
            }
        };

        $count = 0;
        $withConsent = 0;

        while ($ids = $this->data->customerIds($since, $after)) {
            foreach ($ids as $id) {
                $customer = $this->data->customer($id);
                $after = $id;

                if ($customer === null || (string) $customer['email'] === '') {
                    continue;
                }

                $email = strtolower((string) $customer['email']);

                // Guest checkouts leave one customer row per order under the same
                // e-mail: one contact, whose statistics already cover all of them
                // (see ShopData::customerStats()).
                if (isset($seen[$email])) {
                    continue;
                }

                $seen[$email] = true;
                $attributes = CustomerMapper::attributes($customer, $this->data->customerStats($id, $occasions));
                $attributes['EXT_ID'] = $this->config->extId($id);
                $all[] = ['email' => (string) $customer['email'], 'attributes' => $attributes];
                ++$count;

                if ($customer['consent']) {
                    $consenting[] = ['email' => (string) $customer['email'], 'attributes' => ['MARKETING_CONSENT' => true]];
                    ++$withConsent;
                }

                $flush(false);
            }
        }

        foreach ($this->data->footerSubscribers() as $subscriber) {
            $email = strtolower((string) $subscriber['email']);

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $attributes = [
                'MARKETING_CONSENT' => true,
                'NEWSLETTER_SOURCE' => 'formularz',
                'PS_LANG' => strtolower((string) $subscriber['lang_iso']),
            ];

            if ($date = ShopData::date((string) $subscriber['newsletter_date_add'])) {
                $attributes['CONSENT_DATE'] = $date;
            }

            $consenting[] = ['email' => (string) $subscriber['email'], 'attributes' => $attributes];
            ++$withConsent;
            $flush(false);
        }

        $flush(true);
        $this->output->writeln(sprintf('%d customers, %d contacts with marketing consent', $count, $withConsent));

        return true;
    }

    private function importOrders(?string $since): void
    {
        $statusMap = $this->config->statusMap();

        if (!$statusMap) {
            $this->output->writeln('<comment>No order status mapping saved: every order would be skipped. Save the mapping in the module settings first.</comment>');

            return;
        }

        $brevoId = [$this->config, 'brevoId'];
        $notifyUrl = $this->webhookUrl('batch');
        $after = 0;
        $batch = 0;
        $count = 0;
        $skipped = 0;
        $revenue = 0.0;

        while ($ids = $this->data->orderIds($since, $after, self::ORDERS_PER_BATCH)) {
            $orders = [];

            foreach ($ids as $id) {
                $order = $this->data->order($id);
                $mapped = $order !== null ? OrderMapper::toOrder($order, $statusMap, $brevoId) : null;

                if ($mapped === null) {
                    ++$skipped;
                    continue;
                }

                $orders[] = $mapped;
                $revenue += (float) $mapped['amount'];
            }

            $after = (int) end($ids);

            if ($orders) {
                $this->send('orders/status/batch', ['orders' => $orders, 'historical' => true, 'notifyUrl' => $notifyUrl], 'orders-' . ++$batch);
                $count += count($orders);
            }
        }

        // The numbers to compare with Brevo's eCommerce dashboard and the shop's own report.
        $this->output->writeln(sprintf('%d orders sent (%d skipped by state), total amount %.2f', $count, $skipped, $revenue));
    }

    /**
     * @param list<array{email:string,attributes:array}> $contacts
     */
    private static function importBody(array $contacts, array $listIds, string $notifyUrl): array
    {
        return [
            'jsonBody' => $contacts,
            'listIds' => $listIds,
            'updateExistingContacts' => true,
            'emptyContactsAttributes' => false,
            'disableNotification' => true,
            'notifyUrl' => $notifyUrl,
        ];
    }

    /**
     * Sends one request, or writes it to the dry-run directory. Waits and repeats on
     * 429, 5xx and network errors; reports and moves on after a 4xx.
     */
    private function send(string $path, array $body, string $label): void
    {
        if ($this->dumpDir !== null) {
            file_put_contents(
                $this->dumpDir . '/' . $label . '.json',
                Json::encode(['path' => $path, 'body' => $body], JSON_PRETTY_PRINT)
            );
            $this->output->writeln('  ' . $label . ' → ' . $label . '.json');

            return;
        }

        for ($try = 1; $try <= self::MAX_TRIES; ++$try) {
            try {
                $response = $this->client->post($path, $body);
            } catch (TransportException $e) {
                $this->wait($label, $e->getMessage(), 30 * $try);

                continue;
            }

            if ($response->isSuccess()) {
                $this->output->writeln('  ' . $label . ': HTTP ' . $response->status . self::describe($response));

                return;
            }

            if ($response->status === 429 || $response->status >= 500) {
                $this->wait($label, $response->errorMessage(), $response->retryAfter() ?? 60);

                continue;
            }

            ++$this->failures;
            $this->output->writeln('<error>  ' . $label . ': ' . $response->errorMessage() . '</error>');

            return;
        }

        ++$this->failures;
        $this->output->writeln('<error>  ' . $label . ': gave up after ' . self::MAX_TRIES . ' tries</error>');
    }

    private function wait(string $label, string $why, int $seconds): void
    {
        $this->output->writeln('<comment>  ' . $label . ': ' . $why . ' — waiting ' . $seconds . ' s</comment>');
        sleep($seconds);
    }

    private static function describe(Response $response): string
    {
        $parts = [];

        foreach (['processId', 'batch_id', 'count', 'createdCount', 'updatedCount'] as $key) {
            if (isset($response->data[$key])) {
                $parts[] = $key . ' ' . $response->data[$key];
            }
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';
    }

    private function webhookUrl(string $kind): string
    {
        return (string) \Context::getContext()->link->getModuleLink(
            'mzcbrevo',
            'webhook',
            ['kind' => $kind, 'token' => $this->config->get(Config::WEBHOOK_SECRET)],
            true,
            null,
            $this->config->idShop()
        );
    }
}
