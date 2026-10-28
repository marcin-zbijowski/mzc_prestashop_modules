<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\TransportException;
use Mzc\Brevo\Occasion\OccasionDetector;

/**
 * Creates the contact attributes the module writes ("Synchronize attributes" in the
 * back office). Brevo silently drops a value for an attribute that does not exist,
 * so this has to run before the first contact is sent.
 *
 * Existing attributes are left alone, except the two category attributes, whose
 * value lists are refreshed: PS_GROUP from the shop's customer groups, OCCASION_TYPE
 * from OccasionDetector.
 */
class AttributeSynchronizer
{
    /** name => Brevo type ("float" is Brevo's number). FIRSTNAME, LASTNAME and SMS exist in every account. */
    public const ATTRIBUTES = [
        'FIRSTNAME' => 'text',
        'LASTNAME' => 'text',
        'SMS' => 'text',
        'PS_CUSTOMER_ID' => 'float',
        'PS_GROUP' => 'category',
        'PS_LANG' => 'text',
        'ORDERS_COUNT' => 'float',
        'TOTAL_SPENT' => 'float',
        'FIRST_ORDER_DATE' => 'date',
        'LAST_ORDER_DATE' => 'date',
        'LAST_CATEGORY' => 'text',
        'OCCASION_TYPE' => 'category',
        'OCCASION_ORDER_DATE' => 'date',
        'NEWSLETTER_SOURCE' => 'text',
        'MARKETING_CONSENT' => 'boolean',
        'CONSENT_DATE' => 'date',
    ];

    private BrevoClient $client;

    public function __construct(BrevoClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param array<int,string> $groups id_group => name
     *
     * @return array{ok:bool,lines:string[]}
     */
    public function sync(array $groups): array
    {
        $lines = [];

        try {
            $response = $this->client->get('contacts/attributes');

            if (!$response->isSuccess()) {
                return ['ok' => false, 'lines' => ['GET contacts/attributes: ' . $response->errorMessage()]];
            }

            $existing = [];

            foreach ((array) ($response->data['attributes'] ?? []) as $attribute) {
                $existing[strtoupper((string) $attribute['name'])] = (string) ($attribute['type'] ?? '');
            }

            $ok = true;

            foreach (self::ATTRIBUTES as $name => $type) {
                $enumeration = $this->enumeration($name, $groups);

                if (isset($existing[$name])) {
                    if ($enumeration === null) {
                        $lines[] = $name . ': exists';

                        continue;
                    }

                    $update = $this->client->put('contacts/attributes/normal/' . $name, ['enumeration' => $enumeration]);
                    $ok = $ok && $update->isSuccess();
                    $lines[] = $name . ': ' . ($update->isSuccess() ? 'values updated' : $update->errorMessage());

                    continue;
                }

                $body = ['type' => $type];

                if ($enumeration !== null) {
                    $body['enumeration'] = $enumeration;
                }

                $create = $this->client->post('contacts/attributes/normal/' . $name, $body);
                $ok = $ok && $create->isSuccess();
                $lines[] = $name . ': ' . ($create->isSuccess() ? 'created (' . $type . ')' : $create->errorMessage());
            }
        } catch (TransportException $e) {
            return ['ok' => false, 'lines' => array_merge($lines, [$e->getMessage()])];
        }

        return ['ok' => $ok, 'lines' => $lines];
    }

    /**
     * @param array<int,string> $groups
     *
     * @return list<array{value:int,label:string}>|null
     */
    private function enumeration(string $name, array $groups): ?array
    {
        if ($name === 'PS_GROUP') {
            $values = [];

            foreach ($groups as $id => $label) {
                $values[] = ['value' => (int) $id, 'label' => (string) $label];
            }

            return $values;
        }

        if ($name === 'OCCASION_TYPE') {
            $values = [];

            foreach (OccasionDetector::BREVO_VALUES as $code => $value) {
                $values[] = ['value' => $value, 'label' => OccasionDetector::LABELS[$code]];
            }

            return $values;
        }

        return null;
    }
}
