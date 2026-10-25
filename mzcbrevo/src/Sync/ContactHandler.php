<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\CustomerMapper;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\Result;

/**
 * Creates or updates a customer's contact (job type "contact").
 *
 * Update first, by ext_id: that is what follows an e-mail change in the shop. Brevo
 * answers 404 when no contact carries the ext_id yet — a new customer, or one that
 * so far exists in Brevo only under the e-mail (historical import, footer sign-up).
 * Then create with updateEnabled, which updates the e-mail's contact if there is one
 * and attaches the ext_id to it.
 *
 * Double opt-in for account consent (MZCBREVO_DOI_ACCOUNT): the contact is then
 * synced without joining the newsletter list, and a DOI e-mail is requested — unless
 * Brevo shows the contact on the list already, so nobody gets a confirmation e-mail
 * on every order.
 */
class ContactHandler extends AbstractHandler
{
    private ShopData $data;
    private OccasionExtractor $occasions;

    public function __construct(BrevoClient $client, Config $config, ShopData $data, OccasionExtractor $occasions)
    {
        parent::__construct($client, $config);
        $this->data = $data;
        $this->occasions = $occasions;
    }

    public function handle(array $jobs): array
    {
        $results = [];

        foreach ($jobs as $job) {
            $results[$job->id] = $job->type === Job::TYPE_UNSUBSCRIBE
                ? $this->unsubscribe((string) ($job->payload['email'] ?? ''))
                : $this->sync($job);
        }

        return $results;
    }

    private function sync(Job $job): Result
    {
        $customer = $this->data->customer($job->entityId);

        if ($customer === null || (int) $customer['deleted'] === 1 || ShopData::isAnonymous((string) $customer['email'])) {
            return Result::done(['skipped' => 'customer not found, deleted or anonymized']);
        }

        $email = (string) $customer['email'];
        $extId = $this->config->extId((int) $customer['id_customer']);
        $consent = (bool) $customer['consent'];
        $newsletterList = $this->config->getInt(Config::LIST_NEWSLETTER);
        $source = isset($job->payload['newsletter_source']) ? (string) $job->payload['newsletter_source'] : null;

        $needsDoi = false;
        $join = true;

        if ($consent && $newsletterList > 0 && $this->config->getBool(Config::DOI_ACCOUNT)) {
            $join = $this->isOnList($extId, $email, $newsletterList);
            $needsDoi = !$join;
        }

        $attributes = CustomerMapper::attributes($customer, $this->data->customerStats((int) $customer['id_customer'], $this->occasions), $source);
        $lists = CustomerMapper::lists($consent, $newsletterList, $this->config->getInt(Config::LIST_CUSTOMERS), $join);

        $result = $this->upsert($email, $extId, $attributes, $lists);

        if ($result->kind === Result::FAIL && isset($attributes['SMS']) && stripos($result->error, 'sms') !== false) {
            // Brevo refuses a number it cannot parse, or one that another contact
            // already has. Neither is worth losing the rest of the contact over.
            unset($attributes['SMS']);
            $result = $this->upsert($email, $extId, $attributes, $lists);
        }

        if ($result->kind === Result::DONE && $needsDoi) {
            $doi = DoiHandler::body($this->config, $email, $source ?? 'konto');

            if ($doi === null) {
                return Result::fail(DoiHandler::NOT_CONFIGURED, $result->request);
            }

            return $this->call(fn (): Response => $this->client->post(DoiHandler::PATH, $doi), $doi);
        }

        return $result;
    }

    /**
     * @param array<string,scalar> $attributes
     * @param array{listIds:int[],unlinkListIds:int[]} $lists
     */
    private function upsert(string $email, string $extId, array $attributes, array $lists): Result
    {
        $update = CustomerMapper::updateBody($email, $attributes, $lists);
        $response = $this->raw(fn (): Response => $this->client->put(BrevoClient::contactPath($extId), $update, ['identifierType' => 'ext_id']));

        if ($response instanceof Result) {
            return $response;
        }

        if ($response->status !== 404) {
            return $this->interpret($response, $update);
        }

        $create = CustomerMapper::createBody($email, $extId, $attributes, $lists);
        $result = $this->call(fn (): Response => $this->client->post('contacts', $create), $create);

        if ($result->kind !== Result::DONE || !$lists['unlinkListIds']) {
            return $result;
        }

        // POST cannot take a contact off a list; an existing contact may well be on it.
        $unlink = ['unlinkListIds' => $lists['unlinkListIds']];
        $response = $this->raw(fn (): Response => $this->client->put(BrevoClient::contactPath($email), $unlink, ['identifierType' => 'email_id']));

        if ($response instanceof Result) {
            return $response;
        }

        // 400 here means "contact is not on that list" — exactly the state we want.
        return $response->isSuccess() || $response->status === 400 || $response->status === 404
            ? Result::done($create)
            : $this->interpret($response, $create);
    }

    private function unsubscribe(string $email): Result
    {
        $newsletterList = $this->config->getInt(Config::LIST_NEWSLETTER);

        if ($email === '' || $newsletterList <= 0) {
            return Result::done(['skipped' => 'no e-mail or no newsletter list']);
        }

        $body = ['unlinkListIds' => [$newsletterList]];
        $response = $this->raw(fn (): Response => $this->client->put(BrevoClient::contactPath($email), $body, ['identifierType' => 'email_id']));

        if ($response instanceof Result) {
            return $response;
        }

        // Unknown contact, or one that is not on the list: nothing to take it off.
        if ($response->status === 404 || $response->status === 400) {
            return Result::done($body);
        }

        return $this->interpret($response, $body);
    }

    private function isOnList(string $extId, string $email, int $idList): bool
    {
        foreach ([[$extId, 'ext_id'], [$email, 'email_id']] as [$identifier, $type]) {
            $response = $this->raw(fn (): Response => $this->client->get(BrevoClient::contactPath($identifier), ['identifierType' => $type]));

            if ($response instanceof Response && $response->isSuccess()) {
                return in_array($idList, array_map('intval', (array) ($response->data['listIds'] ?? [])), true);
            }
        }

        return false;
    }
}
