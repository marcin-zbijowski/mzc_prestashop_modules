<?php

declare(strict_types=1);

namespace Mzc\Brevo\Mapper;

use Mzc\Brevo\Occasion\OccasionDetector;

/**
 * Customer (+ order history) → Brevo contact.
 *
 * Contacts are identified by e-mail and, in addition, by ext_id "ps_<id_customer>",
 * so that an e-mail change in the shop renames the contact instead of creating a
 * second one (see Sync\ContactHandler for how both are used).
 *
 * Lists: every customer goes to the "customers" list (segmentation and exclusions,
 * never marketing). Only a customer with marketing consent goes to the "newsletter"
 * list, and one without consent is taken off it.
 */
final class CustomerMapper
{
    /**
     * Contact attributes. A value the shop does not know (no orders yet, no
     * occasion) is left out, never sent empty: an empty value would erase what
     * Brevo already has, e.g. from the historical import.
     *
     * @param array<string,mixed> $customer ShopData::customer()
     * @param array<string,mixed> $stats ShopData::customerStats()
     *
     * @return array<string,scalar>
     */
    public static function attributes(array $customer, array $stats, ?string $newsletterSource = null): array
    {
        $consent = (bool) ($customer['consent'] ?? false);

        $attributes = [
            'FIRSTNAME' => (string) $customer['firstname'],
            'LASTNAME' => (string) $customer['lastname'],
            'PS_CUSTOMER_ID' => (int) $customer['id_customer'],
            'PS_GROUP' => (int) $customer['id_default_group'],
            'PS_LANG' => strtolower((string) ($customer['lang_iso'] ?? '')),
            'ORDERS_COUNT' => (int) ($stats['orders_count'] ?? 0),
            'TOTAL_SPENT' => (float) ($stats['total_spent'] ?? 0),
            'MARKETING_CONSENT' => $consent,
        ];

        if ($consent) {
            // The phone number is personal data used for SMS marketing: only with consent.
            [$phone, $country] = array_pad(explode('|', (string) ($customer['phone'] ?? '')), 2, 'PL');
            $sms = PhoneNormalizer::toE164($phone, $country !== '' ? $country : 'PL');

            if ($sms !== null) {
                $attributes['SMS'] = $sms;
            }

            if (!empty($customer['consent_date'])) {
                $attributes['CONSENT_DATE'] = (string) $customer['consent_date'];
            }
        }

        foreach ([
            'FIRST_ORDER_DATE' => 'first_order_date',
            'LAST_ORDER_DATE' => 'last_order_date',
            'LAST_CATEGORY' => 'last_category',
            'OCCASION_ORDER_DATE' => 'occasion_order_date',
        ] as $attribute => $key) {
            if (!empty($stats[$key])) {
                $attributes[$attribute] = (string) $stats[$key];
            }
        }

        $occasion = (string) ($stats['occasion'] ?? '');

        if (isset(OccasionDetector::BREVO_VALUES[$occasion])) {
            $attributes['OCCASION_TYPE'] = OccasionDetector::BREVO_VALUES[$occasion];
        }

        if ($newsletterSource !== null && $newsletterSource !== '') {
            $attributes['NEWSLETTER_SOURCE'] = $newsletterSource;
        }

        return $attributes;
    }

    /**
     * Which lists to add the contact to and which to take it off.
     *
     * @param bool $joinNewsletter false when consent is to be confirmed by double opt-in
     *                             first: the contact is then neither added nor removed
     *
     * @return array{listIds:int[],unlinkListIds:int[]}
     */
    public static function lists(bool $consent, int $newsletterList, int $customersList, bool $joinNewsletter = true): array
    {
        $link = [];
        $unlink = [];

        if ($customersList > 0) {
            $link[] = $customersList;
        }

        if ($newsletterList > 0) {
            if (!$consent) {
                $unlink[] = $newsletterList;
            } elseif ($joinNewsletter) {
                $link[] = $newsletterList;
            }
        }

        return ['listIds' => $link, 'unlinkListIds' => $unlink];
    }

    /**
     * Body of PUT /v3/contacts/{ext_id}?identifierType=ext_id — the update path,
     * which also carries an e-mail change (attribute EMAIL).
     *
     * @param array<string,scalar> $attributes
     * @param array{listIds:int[],unlinkListIds:int[]} $lists
     */
    public static function updateBody(string $email, array $attributes, array $lists): array
    {
        $body = ['attributes' => ['EMAIL' => $email] + $attributes];

        if ($lists['listIds']) {
            $body['listIds'] = $lists['listIds'];
        }

        if ($lists['unlinkListIds']) {
            $body['unlinkListIds'] = $lists['unlinkListIds'];
        }

        return $body;
    }

    /**
     * Body of POST /v3/contacts — the create path. With updateEnabled, a contact that
     * already exists under this e-mail is updated and gets the ext_id.
     *
     * POST cannot unlink lists; ContactHandler follows up with a PUT when it must.
     *
     * @param array<string,scalar> $attributes
     * @param array{listIds:int[],unlinkListIds:int[]} $lists
     */
    public static function createBody(string $email, string $extId, array $attributes, array $lists): array
    {
        $body = [
            'email' => $email,
            'ext_id' => $extId,
            'attributes' => $attributes,
            'updateEnabled' => true,
        ];

        if ($lists['listIds']) {
            $body['listIds'] = $lists['listIds'];
        }

        return $body;
    }
}
