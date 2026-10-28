<?php

declare(strict_types=1);

namespace Mzc\Brevo\Mapper;

use Mzc\Brevo\Data\ShopData;

/**
 * Order → Brevo eCommerce order (/v3/orders/status, /v3/orders/status/batch).
 *
 * productId is the shop's id_product and variantId its id_product_attribute, the
 * same ids CatalogMapper sends to the catalogue, so Brevo can join the two.
 */
final class OrderMapper
{
    /**
     * @param array<string,mixed> $order ShopData::order()
     * @param array<int,string> $statusMap id_order_state => Brevo status ('' = do not send)
     * @param callable(int): string $brevoId the shop id → Brevo id (adds the per-shop prefix)
     *
     * @return array<string,mixed>|null null when the order's state is not to be sent
     */
    public static function toOrder(array $order, array $statusMap, callable $brevoId): ?array
    {
        $status = $statusMap[(int) $order['current_state']] ?? '';

        if ($status === '') {
            return null;
        }

        $products = [];

        foreach ($order['lines'] as $line) {
            $product = [
                'productId' => $brevoId((int) $line['product_id']),
                'quantity' => (int) $line['product_quantity'],
                'price' => round((float) $line['unit_price_tax_incl'], 2),
            ];

            if ((int) $line['product_attribute_id'] > 0) {
                $product['variantId'] = $brevoId((int) $line['product_attribute_id']);
            }

            $products[] = $product;
        }

        $result = [
            'id' => $brevoId((int) $order['id_order']),
            'createdAt' => self::utc((string) $order['date_add']),
            'updatedAt' => self::utc((string) $order['date_upd']),
            'status' => $status,
            'amount' => round((float) $order['total_paid_tax_incl'], 2),
            'products' => $products,
            'metaInfo' => [
                'reference' => (string) $order['reference'],
                'currency' => (string) $order['currency_iso'],
                'shipping' => round((float) $order['total_shipping_tax_incl'], 2),
                'discounts' => round((float) $order['total_discounts_tax_incl'], 2),
            ],
        ];

        // No identifier for an order of an anonymized customer: revenue without a person.
        if (!ShopData::isAnonymous((string) $order['email'])) {
            $result['identifiers'] = ['email_id' => (string) $order['email']];
        }

        if (!empty($order['coupons'])) {
            $result['coupons'] = array_values(array_map('strval', $order['coupons']));
        }

        $billing = self::billing((array) ($order['billing'] ?? []), (string) $order['payment']);

        if ($billing) {
            $result['billing'] = $billing;
        }

        return $result;
    }

    /**
     * @return array<string,string>
     */
    private static function billing(array $address, string $payment): array
    {
        $billing = array_filter([
            'address' => trim((string) ($address['address1'] ?? '') . ' ' . (string) ($address['address2'] ?? '')),
            'city' => (string) ($address['city'] ?? ''),
            'postCode' => (string) ($address['postcode'] ?? ''),
            'country' => (string) ($address['country_name'] ?? ''),
            'countryCode' => (string) ($address['country_iso'] ?? ''),
            'region' => (string) ($address['state_name'] ?? ''),
            'phone' => (string) (($address['phone_mobile'] ?? '') ?: ($address['phone'] ?? '')),
            'paymentMethod' => $payment,
        ], static fn (string $value): bool => $value !== '');

        return $billing;
    }

    /** Shop-local "Y-m-d H:i:s" → UTC "Y-m-d\TH:i:s\Z", which is what Brevo asks for. */
    public static function utc(string $datetime, ?string $timezone = null): string
    {
        $zone = new \DateTimeZone($timezone ?? date_default_timezone_get());

        try {
            $date = new \DateTimeImmutable($datetime, $zone);
        } catch (\Exception $e) {
            $date = new \DateTimeImmutable('now', $zone);
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
