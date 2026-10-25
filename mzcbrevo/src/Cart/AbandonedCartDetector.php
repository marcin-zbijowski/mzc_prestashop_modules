<?php

declare(strict_types=1);

namespace Mzc\Brevo\Cart;

use Db;
use Mzc\Brevo\Config;
use Mzc\Brevo\Mapper\EventMapper;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\QueueRepository;

/**
 * Finds abandoned carts and queues one cart_abandoned event for each.
 *
 * Abandoned: last changed more than MZCBREVO_ABANDON_MINUTES ago, no order, worth at
 * least MZCBREVO_ABANDON_MIN_TOTAL. The decision is made here, not in a Brevo
 * workflow that waits and checks for a purchase: the automation then starts from one
 * clear event. Each cart is reported once (`abandoned_at`), and carts older than a
 * week are left alone — a reminder about last month's cart is noise.
 *
 * The cart's value and the consent are checked by the event handler when it sends,
 * against the cart as it is then.
 */
class AbandonedCartDetector
{
    public const TABLE = 'mzcbrevo_cart';
    public const MAX_AGE_DAYS = 7;

    private QueueRepository $queue;
    private Db $db;

    public function __construct(QueueRepository $queue, ?Db $db = null)
    {
        $this->queue = $queue;
        $this->db = $db ?? Db::getInstance();
    }

    /** Called from actionCartSave: one REPLACE, nothing else on the customer's request. */
    public static function touch(int $idCart, int $idShop, int $idCustomer): void
    {
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '` (`id_cart`, `id_shop`, `id_customer`, `date_upd`)
            VALUES (' . $idCart . ', ' . $idShop . ', ' . $idCustomer . ', \'' . QueueRepository::now() . '\')
            ON DUPLICATE KEY UPDATE `id_customer` = VALUES(`id_customer`), `date_upd` = VALUES(`date_upd`)'
        );
    }

    /**
     * @return int number of carts reported
     */
    public function detect(Config $config): int
    {
        $idShop = (int) $config->idShop();
        $minutes = max(5, $config->getInt(Config::ABANDON_MINUTES));
        $minTotal = (float) str_replace(',', '.', $config->get(Config::ABANDON_MIN_TOTAL));

        $rows = $this->db->executeS(
            'SELECT mc.`id_cart`
            FROM `' . _DB_PREFIX_ . self::TABLE . '` mc
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_cart` = mc.`id_cart`
            WHERE mc.`id_shop` = ' . $idShop . '
            AND mc.`abandoned_at` IS NULL
            AND mc.`date_upd` < \'' . QueueRepository::at(time() - $minutes * 60) . '\'
            AND mc.`date_upd` > \'' . QueueRepository::at(time() - self::MAX_AGE_DAYS * 86400) . '\'
            AND o.`id_order` IS NULL
            LIMIT 500'
        ) ?: [];

        $reported = 0;

        foreach ($rows as $row) {
            $idCart = (int) $row['id_cart'];

            if ($minTotal > 0) {
                $total = (float) (new \Cart($idCart))->getOrderTotal(true, \Cart::BOTH_WITHOUT_SHIPPING);

                if ($total < $minTotal) {
                    $this->mark($idCart);

                    continue;
                }
            }

            $this->queue->enqueue(
                Job::TYPE_EVENT,
                $idCart,
                'event:' . EventMapper::CART_ABANDONED . ':' . $idCart,
                ['name' => EventMapper::CART_ABANDONED, 'ts' => time()],
                $idShop
            );
            $this->mark($idCart);
            ++$reported;
        }

        // Rows past the window can never be reported: keep the table small.
        $this->db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `date_upd` < \'' . QueueRepository::at(time() - 2 * self::MAX_AGE_DAYS * 86400) . '\''
        );

        return $reported;
    }

    private function mark(int $idCart): void
    {
        $this->db->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE . '`
            SET `abandoned_at` = \'' . QueueRepository::now() . '\'
            WHERE `id_cart` = ' . $idCart
        );
    }
}
