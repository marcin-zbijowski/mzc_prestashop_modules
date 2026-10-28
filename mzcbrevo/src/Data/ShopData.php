<?php

declare(strict_types=1);

namespace Mzc\Brevo\Data;

use Configuration;
use Context;
use Db;
use ImageType;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Product;

/**
 * Everything the mappers need, read straight from the shop's database into plain
 * arrays. The mappers never touch PrestaShop, so they can be tested with fixtures;
 * this class is the one place that knows the schema.
 *
 * Read at send time, not at hook time: a job queued for a customer sends the
 * customer as they are when the worker gets to it, which is what makes the dedup
 * in the queue safe.
 */
class ShopData
{
    /**
     * psgdpr moves the orders of a customer who asked to be forgotten onto one
     * placeholder customer, anonymous@psgdpr.com. That is not a person: never a contact,
     * never an event. Its orders still count as revenue, sent without an identifier.
     */
    public const ANONYMOUS_EMAIL_SUFFIX = '@psgdpr.com';

    private Db $db;
    private int $idShop;
    private int $idLang;

    /** @var bool|null whether mzcproductfields' tables exist */
    private ?bool $hasProductFields = null;

    private ?bool $hasEmailSubscription = null;

    public function __construct(int $idShop, ?int $idLang = null, ?Db $db = null)
    {
        $this->db = $db ?? Db::getInstance();
        $this->idShop = $idShop;
        $this->idLang = $idLang ?? (int) Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);
    }

    public static function isAnonymous(string $email): bool
    {
        $email = strtolower(trim($email));

        return $email === '' || substr($email, -strlen(self::ANONYMOUS_EMAIL_SUFFIX)) === self::ANONYMOUS_EMAIL_SUFFIX;
    }

    public function idLang(): int
    {
        return $this->idLang;
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    public function customer(int $idCustomer): ?array
    {
        $row = $this->db->getRow(
            'SELECT c.`id_customer`, c.`id_shop`, c.`email`, c.`firstname`, c.`lastname`, c.`newsletter`,
                c.`newsletter_date_add`, c.`id_default_group`, c.`id_lang`, c.`is_guest`, c.`deleted`, c.`date_add`,
                l.`iso_code` AS lang_iso
            FROM `' . _DB_PREFIX_ . 'customer` c
            LEFT JOIN `' . _DB_PREFIX_ . 'lang` l ON l.`id_lang` = c.`id_lang`
            WHERE c.`id_customer` = ' . $idCustomer
        );

        if (!$row) {
            return null;
        }

        $row['phone'] = $this->customerPhone($idCustomer);
        $row += $this->consent((string) $row['email'], $row);

        return $row;
    }

    /**
     * Customer ids for the historical import, oldest first.
     *
     * @return int[]
     */
    public function customerIds(?string $since = null, int $afterId = 0, int $limit = 1000): array
    {
        $rows = $this->db->executeS(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
            WHERE `id_shop` = ' . $this->idShop . ' AND `deleted` = 0 AND `id_customer` > ' . $afterId . '
            AND `email` NOT LIKE \'%' . pSQL(self::ANONYMOUS_EMAIL_SUFFIX) . '\''
            . ($since !== null ? ' AND `date_upd` >= \'' . pSQL($since) . '\'' : '')
            . ' ORDER BY `id_customer` ASC LIMIT ' . $limit
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_customer'));
    }

    public function customerIdByEmail(string $email): int
    {
        return (int) $this->db->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
            WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . $this->idShop . ' AND `deleted` = 0
            ORDER BY `is_guest` ASC, `id_customer` DESC'
        );
    }

    /** Phone of the most recently updated address: mobile first. */
    private function customerPhone(int $idCustomer): string
    {
        $row = $this->db->getRow(
            'SELECT a.`phone_mobile`, a.`phone`, co.`iso_code` AS country_iso
            FROM `' . _DB_PREFIX_ . 'address` a
            LEFT JOIN `' . _DB_PREFIX_ . 'country` co ON co.`id_country` = a.`id_country`
            WHERE a.`id_customer` = ' . $idCustomer . ' AND a.`deleted` = 0
            ORDER BY a.`date_upd` DESC'
        );

        if (!$row) {
            return '';
        }

        $phone = trim((string) $row['phone_mobile']) !== '' ? (string) $row['phone_mobile'] : (string) $row['phone'];

        return $phone === '' ? '' : $phone . '|' . (string) $row['country_iso'];
    }

    /**
     * Marketing consent: the customer's own newsletter flag, or an active footer
     * subscription (ps_emailsubscription) under the same e-mail.
     *
     * @param array<string,mixed>|null $customer a customer row, when there is one
     *
     * @return array{consent:bool,consent_date:?string}
     */
    public function consent(string $email, ?array $customer = null): array
    {
        if ($customer !== null && (int) $customer['newsletter'] === 1) {
            return ['consent' => true, 'consent_date' => self::date((string) $customer['newsletter_date_add'])];
        }

        if ($this->hasEmailSubscription()) {
            $date = $this->db->getValue(
                'SELECT `newsletter_date_add` FROM `' . _DB_PREFIX_ . 'emailsubscription`
                WHERE `email` = \'' . pSQL($email) . '\' AND `active` = 1 AND `id_shop` = ' . $this->idShop
            );

            if ($date !== false && $date !== null) {
                return ['consent' => true, 'consent_date' => self::date((string) $date)];
            }
        }

        return ['consent' => false, 'consent_date' => null];
    }

    public function hasMarketingConsent(string $email, int $idCustomer = 0): bool
    {
        $customer = $idCustomer > 0 ? $this->db->getRow(
            'SELECT `newsletter`, `newsletter_date_add` FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . $idCustomer
        ) : null;

        return $this->consent($email, $customer ?: null)['consent'];
    }

    /**
     * Footer-only subscribers (no customer account), for the historical import.
     *
     * @return array<int,array{email:string,newsletter_date_add:?string,id_lang:int}>
     */
    public function footerSubscribers(): array
    {
        if (!$this->hasEmailSubscription()) {
            return [];
        }

        return $this->db->executeS(
            'SELECT e.`email`, e.`newsletter_date_add`, e.`id_lang`, l.`iso_code` AS lang_iso
            FROM `' . _DB_PREFIX_ . 'emailsubscription` e
            LEFT JOIN `' . _DB_PREFIX_ . 'lang` l ON l.`id_lang` = e.`id_lang`
            WHERE e.`active` = 1 AND e.`id_shop` = ' . $this->idShop
        ) ?: [];
    }

    /**
     * Order history of a customer, as the contact attributes need it.
     *
     * Paid orders only (`orders.valid`, which PrestaShop sets from the "logable"
     * states), amounts converted to the shop's default currency.
     *
     * @return array{orders_count:int,total_spent:float,first_order_date:?string,last_order_date:?string,last_category:string,occasion:?string,occasion_order_date:?string}
     */
    public function customerStats(int $idCustomer, OccasionExtractor $occasions): array
    {
        $in = implode(',', $this->sameEmailCustomerIds($idCustomer));
        $stats = $this->db->getRow(
            'SELECT COUNT(*) AS n,
                SUM(o.`total_paid_tax_incl` / IF(o.`conversion_rate` > 0, o.`conversion_rate`, 1)) AS total,
                MIN(o.`date_add`) AS first_date, MAX(o.`date_add`) AS last_date
            FROM `' . _DB_PREFIX_ . 'orders` o
            WHERE o.`id_customer` IN (' . $in . ') AND o.`valid` = 1'
        ) ?: [];

        $result = [
            'orders_count' => (int) ($stats['n'] ?? 0),
            'total_spent' => round((float) ($stats['total'] ?? 0), 2),
            'first_order_date' => self::date((string) ($stats['first_date'] ?? '')),
            'last_order_date' => self::date((string) ($stats['last_date'] ?? '')),
            'last_category' => '',
            'occasion' => null,
            'occasion_order_date' => null,
        ];

        // Every line of every order, newest first. Unpaid orders count here too: an
        // occasion is about the event the customer is preparing for, not about money.
        $lines = $this->db->executeS(
            'SELECT o.`id_order`, o.`date_add`, o.`valid`, od.`product_id`, od.`total_price_tax_incl`, cl.`name` AS category_name
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.`id_order` = o.`id_order`
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = od.`product_id`
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = p.`id_category_default`
                AND cl.`id_lang` = ' . $this->idLang . ' AND cl.`id_shop` = o.`id_shop`
            WHERE o.`id_customer` IN (' . $in . ')
            AND o.`current_state` NOT IN (' . implode(',', $this->cancelledStates()) . ')
            ORDER BY o.`date_add` DESC, od.`total_price_tax_incl` DESC'
        ) ?: [];

        $occasions->forProducts(array_column($lines, 'product_id'));

        foreach ($lines as $line) {
            if ($result['last_category'] === '' && (int) $line['valid'] === 1) {
                // The most expensive product of the last paid order says best what was bought.
                $result['last_category'] = (string) $line['category_name'];
            }

            if ($result['occasion'] === null) {
                $occasion = $occasions->forProduct((int) $line['product_id']);

                if ($occasion !== null) {
                    $result['occasion'] = $occasion['occasion'];
                    $result['occasion_order_date'] = self::date((string) $line['date_add']);
                }
            }

            if ($result['last_category'] !== '' && $result['occasion'] !== null) {
                break;
            }
        }

        return $result;
    }

    /**
     * Every customer row under the same e-mail in this shop: a guest checkout leaves
     * one per order, and the contact in Brevo is one person.
     *
     * @return int[]
     */
    private function sameEmailCustomerIds(int $idCustomer): array
    {
        $rows = $this->db->executeS(
            'SELECT c2.`id_customer`
            FROM `' . _DB_PREFIX_ . 'customer` c
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c2 ON c2.`email` = c.`email` AND c2.`id_shop` = c.`id_shop`
            WHERE c.`id_customer` = ' . $idCustomer
        ) ?: [];

        return array_values(array_unique(array_merge([$idCustomer], array_map('intval', array_column($rows, 'id_customer')))));
    }

    /** @return int[] cancelled and error states: orders that never happened */
    private function cancelledStates(): array
    {
        return array_values(array_filter([
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_ERROR'),
        ])) ?: [0];
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null the order with `lines`, `coupons`, `billing` and `customer`
     */
    public function order(int $idOrder): ?array
    {
        $order = $this->db->getRow(
            'SELECT o.`id_order`, o.`id_shop`, o.`id_cart`, o.`id_customer`, o.`reference`, o.`current_state`, o.`payment`,
                o.`total_paid_tax_incl`, o.`total_paid_tax_excl`, o.`total_shipping_tax_incl`, o.`total_discounts_tax_incl`,
                o.`date_add`, o.`date_upd`, o.`valid`, o.`id_address_invoice`,
                cu.`iso_code` AS currency_iso, c.`email`, c.`firstname`, c.`lastname`
            FROM `' . _DB_PREFIX_ . 'orders` o
            LEFT JOIN `' . _DB_PREFIX_ . 'currency` cu ON cu.`id_currency` = o.`id_currency`
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = o.`id_customer`
            WHERE o.`id_order` = ' . $idOrder
        );

        if (!$order) {
            return null;
        }

        $order['lines'] = $this->db->executeS(
            'SELECT `product_id`, `product_attribute_id`, `product_name`, `product_reference`, `product_quantity`,
                `unit_price_tax_incl`, `total_price_tax_incl`
            FROM `' . _DB_PREFIX_ . 'order_detail`
            WHERE `id_order` = ' . $idOrder . '
            ORDER BY `id_order_detail` ASC'
        ) ?: [];

        $order['coupons'] = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['code'] !== '' && $row['code'] !== null ? $row['code'] : $row['name'])),
            $this->db->executeS(
                'SELECT ocr.`name`, cr.`code`
                FROM `' . _DB_PREFIX_ . 'order_cart_rule` ocr
                LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.`id_cart_rule` = ocr.`id_cart_rule`
                WHERE ocr.`id_order` = ' . $idOrder . ' AND ocr.`deleted` = 0'
            ) ?: []
        )));

        $order['billing'] = $this->db->getRow(
            'SELECT a.`address1`, a.`address2`, a.`city`, a.`postcode`, a.`phone`, a.`phone_mobile`,
                co.`iso_code` AS country_iso, cl.`name` AS country_name, s.`name` AS state_name
            FROM `' . _DB_PREFIX_ . 'address` a
            LEFT JOIN `' . _DB_PREFIX_ . 'country` co ON co.`id_country` = a.`id_country`
            LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` cl ON cl.`id_country` = a.`id_country` AND cl.`id_lang` = ' . $this->idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON s.`id_state` = a.`id_state`
            WHERE a.`id_address` = ' . (int) $order['id_address_invoice']
        ) ?: [];

        return $order;
    }

    public function stateName(int $idOrderState): string
    {
        return (string) $this->db->getValue(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'order_state_lang`
            WHERE `id_order_state` = ' . $idOrderState . ' AND `id_lang` = ' . $this->idLang
        );
    }

    /**
     * Order ids for the historical import, oldest first.
     *
     * @return int[]
     */
    public function orderIds(?string $since = null, int $afterId = 0, int $limit = 1000): array
    {
        $rows = $this->db->executeS(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_shop` = ' . $this->idShop . ' AND `id_order` > ' . $afterId
            . ($since !== null ? ' AND `date_add` >= \'' . pSQL($since) . '\'' : '')
            . ' ORDER BY `id_order` ASC LIMIT ' . $limit
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_order'));
    }

    // -------------------------------------------------------------------------
    // Carts
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null the cart with `lines`, `email`, `currency_iso`, `total`
     */
    public function cart(int $idCart): ?array
    {
        $cart = $this->db->getRow(
            'SELECT ca.`id_cart`, ca.`id_shop`, ca.`id_customer`, ca.`id_lang`, ca.`date_upd`,
                cu.`iso_code` AS currency_iso, c.`email`, c.`newsletter`, c.`newsletter_date_add`
            FROM `' . _DB_PREFIX_ . 'cart` ca
            LEFT JOIN `' . _DB_PREFIX_ . 'currency` cu ON cu.`id_currency` = ca.`id_currency`
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = ca.`id_customer`
            WHERE ca.`id_cart` = ' . $idCart
        );

        if (!$cart) {
            return null;
        }

        $cartObject = new \Cart($idCart);
        $products = $cartObject->getProducts(true);
        $personalization = $this->personalizationForCart($idCart);
        $lines = [];

        foreach ($products as $product) {
            $idProduct = (int) $product['id_product'];
            $lines[] = [
                'id_product' => $idProduct,
                'id_product_attribute' => (int) $product['id_product_attribute'],
                'name' => (string) $product['name'],
                'attributes' => (string) ($product['attributes'] ?? ''),
                'quantity' => (int) $product['cart_quantity'],
                'price' => round((float) $product['price_wt'], 2),
                'url' => $this->productUrl($idProduct, (int) $product['id_product_attribute']),
                'image' => $this->imageUrl((string) $product['link_rewrite'], (int) ($product['id_image'] ?? 0), $idProduct),
                'personalization' => $personalization[$idProduct] ?? '',
            ];
        }

        $cart['lines'] = $lines;
        $cart['total'] = $lines ? round((float) $cartObject->getOrderTotal(true, \Cart::BOTH_WITHOUT_SHIPPING), 2) : 0.0;
        $cart['has_order'] = (bool) \Order::getIdByCartId($idCart);

        return $cart;
    }

    /**
     * Same lines as cart(), for an order: the order_completed event lists what was bought.
     *
     * @return array<int,array<string,mixed>>
     */
    public function orderEventLines(array $order): array
    {
        $personalization = $this->personalizationForOrder((int) $order['id_order']);
        $lines = [];

        foreach ($order['lines'] as $line) {
            $idProduct = (int) $line['product_id'];
            $lines[] = [
                'id_product' => $idProduct,
                'id_product_attribute' => (int) $line['product_attribute_id'],
                'name' => (string) $line['product_name'],
                'quantity' => (int) $line['product_quantity'],
                'price' => round((float) $line['unit_price_tax_incl'], 2),
                'url' => $this->productUrl($idProduct, (int) $line['product_attribute_id']),
                'image' => $this->coverUrl($idProduct),
                'personalization' => $personalization[$idProduct] ?? '',
            ];
        }

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Catalogue
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    public function product(int $idProduct): ?array
    {
        $row = $this->db->getRow(
            'SELECT p.`id_product`, p.`reference`, ps.`active`, ps.`id_category_default`, pl.`name`, pl.`link_rewrite`,
                pl.`description_short`, m.`name` AS brand
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $this->idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = p.`id_product`
                AND pl.`id_lang` = ' . $this->idLang . ' AND pl.`id_shop` = ' . $this->idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
            WHERE p.`id_product` = ' . $idProduct
        );

        if (!$row) {
            return null;
        }

        $row['categories'] = array_map('intval', array_column($this->db->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_product` = ' . $idProduct
        ) ?: [], 'id_category'));
        $row['price'] = round((float) Product::getPriceStatic($idProduct, true, null, 2), 2);
        $row['stock'] = (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, null, $this->idShop);
        $row['url'] = $this->productUrl($idProduct);
        $row['image'] = $this->coverUrl($idProduct);

        return $row;
    }

    /** @return int[] */
    public function productIds(int $afterId = 0, int $limit = 100): array
    {
        $rows = $this->db->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_shop`
            WHERE `id_shop` = ' . $this->idShop . ' AND `id_product` > ' . $afterId . '
            ORDER BY `id_product` ASC LIMIT ' . $limit
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_product'));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function category(int $idCategory): ?array
    {
        $row = $this->db->getRow(
            'SELECT c.`id_category`, c.`active`, c.`is_root_category`, c.`id_parent`, cl.`name`, cl.`link_rewrite`
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $this->idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category`
                AND cl.`id_lang` = ' . $this->idLang . ' AND cl.`id_shop` = ' . $this->idShop . '
            WHERE c.`id_category` = ' . $idCategory
        );

        if (!$row) {
            return null;
        }

        $row['url'] = (string) $this->link()->getCategoryLink($idCategory, null, $this->idLang, null, $this->idShop);

        return $row;
    }

    /**
     * Categories below the shop's root, for the import and the occasion mapping.
     *
     * @return array<int,array{id_category:int,name:string,level_depth:int}>
     */
    public function categories(): array
    {
        return $this->db->executeS(
            'SELECT c.`id_category`, cl.`name`, c.`level_depth`
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $this->idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.`id_category` = c.`id_category`
                AND cl.`id_lang` = ' . $this->idLang . ' AND cl.`id_shop` = ' . $this->idShop . '
            WHERE c.`id_parent` > 0 AND c.`is_root_category` = 0
            ORDER BY c.`nleft` ASC'
        ) ?: [];
    }

    // -------------------------------------------------------------------------
    // mzcproductfields personalization (optional: the module may be absent)
    // -------------------------------------------------------------------------

    /** @return array<int,string> id_product => "Label: value; Label: value" */
    public function personalizationForCart(int $idCart): array
    {
        return $this->personalization('WHERE c.`id_cart` = ' . $idCart);
    }

    /** @return array<int,string> id_product => "Label: value; Label: value" */
    public function personalizationForOrder(int $idOrder): array
    {
        return $this->personalization(
            'INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_cart` = c.`id_cart` WHERE o.`id_order` = ' . $idOrder
        );
    }

    /**
     * Same query as MzcProductFieldsField::getTextValuesByCartCondition(), written out
     * here because the worker runs from the CLI, where mzcproductfields' classes are
     * not loaded.
     */
    private function personalization(string $joinAndWhere): array
    {
        if (!$this->hasProductFields()) {
            return [];
        }

        $rows = $this->db->executeS(
            'SELECT c.`id_product`, fl.`label`, cd.`value`
            FROM `' . _DB_PREFIX_ . 'customization` c
            INNER JOIN `' . _DB_PREFIX_ . 'customized_data` cd ON cd.`id_customization` = c.`id_customization`
                AND cd.`type` = ' . (int) Product::CUSTOMIZE_TEXTFIELD . '
            INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_customization` fc ON fc.`id_customization_field` = cd.`index`
            INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_lang` fl
                ON fl.`id_mzc_productfields_field` = fc.`id_mzc_productfields_field` AND fl.`id_lang` = ' . $this->idLang . '
            ' . $joinAndWhere . ' AND cd.`value` != \'\'
            ORDER BY c.`id_product` ASC, fc.`id_mzc_productfields_field` ASC'
        ) ?: [];

        $byProduct = [];

        foreach ($rows as $row) {
            $byProduct[(int) $row['id_product']][] = trim((string) $row['label']) . ': ' . trim((string) $row['value']);
        }

        return array_map(static fn (array $parts): string => implode('; ', $parts), $byProduct);
    }

    private function hasProductFields(): bool
    {
        return $this->hasProductFields ??= $this->tableExists('mzc_productfields_field_customization');
    }

    private function hasEmailSubscription(): bool
    {
        return $this->hasEmailSubscription ??= $this->tableExists('emailsubscription');
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->db->executeS('SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . $table) . '\'');
    }

    // -------------------------------------------------------------------------
    // Links
    // -------------------------------------------------------------------------

    public function productUrl(int $idProduct, int $idProductAttribute = 0): string
    {
        return (string) $this->link()->getProductLink($idProduct, null, null, null, $this->idLang, $this->idShop, $idProductAttribute ?: null);
    }

    public function coverUrl(int $idProduct): string
    {
        $cover = \Image::getCover($idProduct);
        $rewrite = (string) $this->db->getValue(
            'SELECT `link_rewrite` FROM `' . _DB_PREFIX_ . 'product_lang`
            WHERE `id_product` = ' . $idProduct . ' AND `id_lang` = ' . $this->idLang . ' AND `id_shop` = ' . $this->idShop
        );

        return $this->imageUrl($rewrite, $cover ? (int) $cover['id_image'] : 0, $idProduct);
    }

    private function imageUrl(string $linkRewrite, int $idImage, int $idProduct): string
    {
        if ($idImage <= 0) {
            $cover = \Image::getCover($idProduct);
            $idImage = $cover ? (int) $cover['id_image'] : 0;
        }

        if ($idImage <= 0) {
            return '';
        }

        $url = (string) $this->link()->getImageLink($linkRewrite ?: 'product', (string) $idImage, ImageType::getFormattedName('large'));

        return preg_match('#^https?://#', $url) ? $url : 'https://' . ltrim($url, '/');
    }

    private function link(): \Link
    {
        $context = Context::getContext();

        if (!isset($context->link) || !$context->link instanceof \Link) {
            $context->link = new \Link();
        }

        return $context->link;
    }

    /** "2026-10-24 14:03:00" → "2026-10-24"; empty or zero dates → null. */
    public static function date(string $datetime): ?string
    {
        $datetime = trim($datetime);

        if ($datetime === '' || strpos($datetime, '0000-00-00') === 0) {
            return null;
        }

        return substr($datetime, 0, 10);
    }
}
