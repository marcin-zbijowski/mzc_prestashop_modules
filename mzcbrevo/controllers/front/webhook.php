<?php
/**
 * MZC Brevo — incoming webhooks.
 *
 *   ?kind=unsubscribe  Brevo marketing webhook "Unsubscribed": sets newsletter = 0 in
 *                      the shop (and removes a footer-only subscription), so the next
 *                      sync does not put the contact back on the list
 *   ?kind=batch        notifyUrl of /v3/orders/status/batch: the batch result
 *   ?kind=import       notifyUrl of /v3/contacts/import: the import result
 *
 * Every request must carry the module's webhook secret, as ?token= (how the URL is
 * pasted into Brevo) or as "Authorization: Bearer <secret>". Everything received is
 * written to mzcbrevo_webhook_log and shown on the Queue tab.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcbrevoWebhookModuleFrontController extends ModuleFrontController
{
    public const KINDS = ['unsubscribe', 'batch', 'import'];

    /** Brevo's names for an unsubscribe, across webhook versions. */
    public const UNSUBSCRIBE_EVENTS = ['unsubscribe', 'unsubscribed', 'contact_unsubscribed'];

    public $auth = false;
    public $guestAllowed = true;
    public $ajax = true;

    /**
     * Brevo is a server, not a visitor: a shop in maintenance (staging, above all)
     * must still receive its webhooks. The secret is the access control here.
     */
    protected function displayMaintenancePage()
    {
    }

    protected function displayRestrictedCountryPage()
    {
    }

    public function postProcess(): void
    {
        $idShop = (int) $this->context->shop->id;
        $config = new \Mzc\Brevo\Config($idShop);
        $secret = $config->get(\Mzc\Brevo\Config::WEBHOOK_SECRET);

        if ($secret === '' || !hash_equals($secret, $this->presentedToken())) {
            $this->respond(403, ['ok' => false, 'error' => 'forbidden']);
        }

        $kind = (string) Tools::getValue('kind');

        if (!in_array($kind, self::KINDS, true)) {
            $this->respond(400, ['ok' => false, 'error' => 'unknown kind']);
        }

        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);

        $this->log($idShop, $kind, $raw);

        if ($kind !== 'unsubscribe') {
            $this->respond(200, ['ok' => true]);
        }

        // One event per request is what Brevo sends; a list is accepted all the same.
        $events = is_array($payload) && array_is_list($payload) ? $payload : [$payload];
        $done = 0;

        foreach ($events as $event) {
            if (!is_array($event) || !in_array(strtolower((string) ($event['event'] ?? '')), self::UNSUBSCRIBE_EVENTS, true)) {
                continue;
            }

            $email = trim((string) ($event['email'] ?? ''));

            if ($email !== '' && Validate::isEmail($email)) {
                $this->unsubscribe($email, $idShop);
                ++$done;
            }
        }

        $this->respond(200, ['ok' => true, 'unsubscribed' => $done]);
    }

    /**
     * Straight SQL, not Customer::update(): the ObjectModel hook would queue a contact
     * sync, which would tell Brevo what Brevo has just told us.
     */
    private function unsubscribe(string $email, int $idShop): void
    {
        $db = Db::getInstance();
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'customer` SET `newsletter` = 0
            WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . $idShop
        );

        if ($db->executeS('SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . 'emailsubscription') . '\'')) {
            $db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'emailsubscription`
                WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . $idShop
            );
        }
    }

    private function presentedToken(): string
    {
        $token = (string) Tools::getValue('token');

        if ($token !== '') {
            return $token;
        }

        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        return stripos($header, 'Bearer ') === 0 ? trim(substr($header, 7)) : '';
    }

    private function log(int $idShop, string $kind, string $raw): void
    {
        Db::getInstance()->insert(\Mzc\Brevo\Installer::WEBHOOK_LOG_TABLE, [
            'id_shop' => $idShop,
            'kind' => pSQL($kind),
            'payload' => pSQL(mb_substr($raw, 0, 60000), true),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return never
     */
    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }
}
