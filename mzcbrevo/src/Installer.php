<?php

declare(strict_types=1);

namespace Mzc\Brevo;

use Db;
use Mzc\Brevo\Cart\AbandonedCartDetector;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Mzc\Brevo\Queue\QueueRepository;

/**
 * Tables and default settings.
 *
 *   mzcbrevo_queue             every job bound for Brevo (see Queue\QueueRepository)
 *   mzcbrevo_cart              last change per cart, for the abandoned-cart detector
 *   mzcbrevo_product_occasion  manual occasion per product (the product page tab)
 *   mzcbrevo_webhook_log       what Brevo reported back: batch and import results
 */
final class Installer
{
    public const WEBHOOK_LOG_TABLE = 'mzcbrevo_webhook_log';

    public static function installDb(): bool
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
        $prefix = _DB_PREFIX_;

        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . $prefix . QueueRepository::TABLE . '` (
                `id_mzcbrevo_queue` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 1,
                `type` VARCHAR(20) NOT NULL,
                `entity_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `dedup_key` VARCHAR(191) NULL,
                `payload` MEDIUMTEXT NULL,
                `last_request` MEDIUMTEXT NULL,
                `status` VARCHAR(12) NOT NULL DEFAULT "pending",
                `attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                `next_attempt_at` DATETIME NOT NULL,
                `last_error` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_mzcbrevo_queue`),
                UNIQUE KEY `uniq_dedup_key` (`dedup_key`),
                KEY `idx_due` (`status`, `next_attempt_at`),
                KEY `idx_updated` (`status`, `updated_at`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . $prefix . AbandonedCartDetector::TABLE . '` (
                `id_cart` INT(10) UNSIGNED NOT NULL,
                `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 1,
                `id_customer` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `date_upd` DATETIME NOT NULL,
                `abandoned_at` DATETIME NULL,
                PRIMARY KEY (`id_cart`),
                KEY `idx_pending` (`id_shop`, `abandoned_at`, `date_upd`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . $prefix . OccasionExtractor::TABLE . '` (
                `id_product` INT(10) UNSIGNED NOT NULL,
                `occasion` VARCHAR(20) NOT NULL,
                PRIMARY KEY (`id_product`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . $prefix . self::WEBHOOK_LOG_TABLE . '` (
                `id_mzcbrevo_webhook_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 1,
                `kind` VARCHAR(20) NOT NULL,
                `payload` MEDIUMTEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_mzcbrevo_webhook_log`),
                KEY `idx_kind` (`kind`, `created_at`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The queue goes with the module; the manual occasions too. Nothing in them is
     * the only copy of anything: the shop and Brevo both keep the data itself.
     */
    public static function uninstallDb(): bool
    {
        $ok = true;

        foreach ([QueueRepository::TABLE, AbandonedCartDetector::TABLE, OccasionExtractor::TABLE, self::WEBHOOK_LOG_TABLE] as $table) {
            $ok = Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`') && $ok;
        }

        return $ok;
    }

    /** Seeds every setting that is not set yet, including a fresh webhook secret. */
    public static function installConfig(): bool
    {
        $ok = true;

        foreach (Config::DEFAULTS as $key => $value) {
            if (\Configuration::get($key) === false) {
                $ok = \Configuration::updateValue($key, $value) && $ok;
            }
        }

        if (!\Configuration::get(Config::WEBHOOK_SECRET)) {
            $ok = \Configuration::updateValue(Config::WEBHOOK_SECRET, bin2hex(random_bytes(24))) && $ok;
        }

        if (!\Configuration::get(Config::STATUS_MAP)) {
            $ok = \Configuration::updateValue(Config::STATUS_MAP, json_encode(self::defaultStatusMap())) && $ok;
        }

        return $ok;
    }

    public static function uninstallConfig(): bool
    {
        $ok = true;

        foreach (array_merge(array_keys(Config::DEFAULTS), [Config::WEBHOOK_SECRET]) as $key) {
            $ok = \Configuration::deleteByName($key) && $ok;
        }

        return $ok;
    }

    /**
     * A starting point for the state mapping, to be checked against the test account
     * (Brevo does not document its status values): paid states → completed,
     * cancelled → cancelled, refunded → refunded, payment error → cancelled, the rest
     * → pending.
     *
     * @return array<int,string>
     */
    public static function defaultStatusMap(): array
    {
        $map = [];
        $rows = Db::getInstance()->executeS('SELECT `id_order_state`, `paid`, `deleted` FROM `' . _DB_PREFIX_ . 'order_state`') ?: [];

        foreach ($rows as $row) {
            $map[(int) $row['id_order_state']] = (int) $row['paid'] === 1 ? 'completed' : 'pending';
        }

        foreach (['PS_OS_CANCELED' => 'cancelled', 'PS_OS_ERROR' => 'cancelled', 'PS_OS_REFUND' => 'refunded'] as $key => $status) {
            $id = (int) \Configuration::get($key);

            if ($id > 0) {
                $map[$id] = $status;
            }
        }

        return $map;
    }
}
