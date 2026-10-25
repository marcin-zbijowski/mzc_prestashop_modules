<?php

declare(strict_types=1);

namespace Mzc\Brevo\Mapper;

/**
 * Cart and order → Brevo event (/v3/events, /v3/events/batch).
 *
 * Every event carries, in event_properties, the cart or order id, its value, the
 * currency and the lines (name, image, URL, price, quantity and the personalization
 * as plain text) — what a Brevo template needs to show "what you left behind" or
 * "what you bought" without calling the shop.
 *
 * Events:
 *   cart_updated          the cart changed (at most once per 5 minutes per cart)
 *   cart_abandoned        no order N minutes after the last change (once per cart)
 *   order_completed       an order was placed — also what stops the abandoned-cart flow
 *   order_status_changed  an order changed state
 */
final class EventMapper
{
    public const CART_UPDATED = 'cart_updated';
    public const CART_ABANDONED = 'cart_abandoned';
    public const ORDER_COMPLETED = 'order_completed';
    public const ORDER_STATUS_CHANGED = 'order_status_changed';

    /** event_properties is capped at 50 KB by Brevo; a cart with more lines is not a normal cart. */
    public const MAX_ITEMS = 30;
    public const MAX_TEXT = 500;

    /**
     * @param array<string,mixed> $cart ShopData::cart()
     */
    public static function cartEvent(string $name, array $cart, string $recoverUrl, ?int $timestamp = null): array
    {
        return self::event($name, (string) $cart['email'], $timestamp, [
            'cart_id' => (int) $cart['id_cart'],
            'value' => round((float) $cart['total'], 2),
            'currency' => (string) $cart['currency_iso'],
            'items_count' => array_sum(array_map(static fn (array $line): int => (int) $line['quantity'], $cart['lines'])),
            'items' => self::items($cart['lines']),
            'recover_url' => $recoverUrl,
        ]);
    }

    /**
     * @param array<string,mixed> $order ShopData::order()
     * @param array<int,array<string,mixed>> $lines ShopData::orderEventLines()
     */
    public static function orderEvent(string $name, array $order, array $lines, string $brevoOrderId, string $status, string $stateName, ?int $timestamp = null): array
    {
        return self::event($name, (string) $order['email'], $timestamp, [
            'order_id' => $brevoOrderId,
            'cart_id' => (int) $order['id_cart'],
            'reference' => (string) $order['reference'],
            'value' => round((float) $order['total_paid_tax_incl'], 2),
            'shipping' => round((float) $order['total_shipping_tax_incl'], 2),
            'currency' => (string) $order['currency_iso'],
            'status' => $status,
            'state_name' => $stateName,
            'coupons' => implode(',', (array) ($order['coupons'] ?? [])),
            'items' => self::items($lines),
        ]);
    }

    /**
     * @param array<string,mixed> $properties
     */
    private static function event(string $name, string $email, ?int $timestamp, array $properties): array
    {
        return [
            'event_name' => $name,
            'event_date' => gmdate('Y-m-d\TH:i:s\Z', $timestamp ?? time()),
            'identifiers' => ['email_id' => $email],
            'event_properties' => $properties,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     *
     * @return array<int,array<string,mixed>>
     */
    public static function items(array $lines): array
    {
        $items = [];

        foreach (array_slice($lines, 0, self::MAX_ITEMS) as $line) {
            $item = [
                'product_id' => (int) $line['id_product'],
                'variant_id' => (int) ($line['id_product_attribute'] ?? 0),
                'name' => self::cut((string) $line['name']),
                'quantity' => (int) $line['quantity'],
                'price' => round((float) $line['price'], 2),
                'url' => (string) ($line['url'] ?? ''),
                'image' => (string) ($line['image'] ?? ''),
                'personalization' => self::cut((string) ($line['personalization'] ?? '')),
            ];

            if (!empty($line['attributes'])) {
                $item['variant'] = self::cut((string) $line['attributes']);
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function cut(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > self::MAX_TEXT ? mb_substr($text, 0, self::MAX_TEXT - 1) . '…' : $text;
    }
}
