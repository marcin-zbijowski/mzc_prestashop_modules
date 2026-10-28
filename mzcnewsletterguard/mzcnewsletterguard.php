<?php
/**
 * MZC Newsletter Guard
 *
 * Chroni zapis do newslettera (ps_emailsubscription) i formularz kontaktowy
 * (contactform) przed botami. Warstwy, od najtańszej:
 *   1. pole-pułapka i token czasowy (tylko newsletter),
 *   2. filtr adresów e-mail (tylko newsletter),
 *   3. Cloudflare Turnstile — rozstrzyga, czy po drugiej stronie jest człowiek,
 *   4. limity zapisów na IP i globalny (tylko newsletter).
 * Nie dotyka koszyka, zamówień, logowania ani rejestracji konta.
 *
 * Awaria po stronie Cloudflare (brak odpowiedzi, błędny klucz tajny) nigdy nie
 * blokuje formularza — wtedy działają pozostałe warstwy (fail-open). Brak tokenu
 * Turnstile w żądaniu to co innego: tak wygląda bot bez JavaScriptu, więc jest
 * odrzucany.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mzcnewsletterguard extends Module
{
    const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Nazwa pola, które widżet Turnstile sam dokłada do formularza. */
    const TURNSTILE_FIELD = 'cf-turnstile-response';

    /** Ustawienia zapisywane w bazie (klucze Turnstile mogą zamiast tego przyjść ze środowiska). */
    const CONFIG_KEYS = [
        'MZCNG_ENABLED', 'MZCNG_IP_LIMIT', 'MZCNG_GLOBAL_LIMIT', 'MZCNG_MIN_SECONDS', 'MZCNG_REQUIRE_TOKEN',
        'MZCNG_PATTERNS', 'MZCNG_TS_NEWSLETTER', 'MZCNG_TS_CONTACT', 'MZCNG_TS_SITEKEY', 'MZCNG_TS_SECRET',
        'MZCNG_TS_APPEARANCE',
    ];

    const HOOKS = ['actionNewsletterRegistrationBefore', 'actionFrontControllerSetMedia', 'actionFrontControllerInitAfter'];

    public function __construct()
    {
        $this->name = 'mzcnewsletterguard';
        $this->tab = 'front_office_features';
        $this->version = '1.1.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->l('MZC Newsletter Guard');
        $this->description = $this->l('Ochrona zapisu do newslettera i formularza kontaktowego przed botami (Cloudflare Turnstile, honeypot, limity). Nie blokuje zakupów.');
    }

    // ---------------------------------------------------------------- install

    public function install()
    {
        return parent::install()
            && $this->registerHook(self::HOOKS)
            && $this->installDb()
            && $this->installDefaults();
    }

    /**
     * Wartości domyślne; istniejących ustawień nie nadpisuje (używa tego też upgrade).
     */
    public function installDefaults()
    {
        $defaults = [
            'MZCNG_ENABLED' => 1,
            'MZCNG_IP_LIMIT' => 3,
            'MZCNG_GLOBAL_LIMIT' => 40,
            'MZCNG_MIN_SECONDS' => 3,
            'MZCNG_REQUIRE_TOKEN' => 1,
            'MZCNG_PATTERNS' => "@(mailinator|guerrillamail|10minutemail|tempmail|yopmail|trashmail)\\.\n",
            'MZCNG_TS_NEWSLETTER' => 1,
            'MZCNG_TS_CONTACT' => 1,
            'MZCNG_TS_SITEKEY' => '',
            'MZCNG_TS_SECRET' => '',
            'MZCNG_TS_APPEARANCE' => 'interaction-only',
        ];

        foreach ($defaults as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }

        return true;
    }

    public function uninstall()
    {
        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    private function installDb()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzcng_log` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
                `email` VARCHAR(255) NOT NULL DEFAULT \'\',
                `reason` VARCHAR(32) NOT NULL DEFAULT \'\',
                `allowed` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ip_time` (`ip`, `created_at`),
                KEY `idx_time` (`created_at`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    // ------------------------------------------------------------------ hooks

    public function hookActionFrontControllerSetMedia()
    {
        try {
            if (!Configuration::get('MZCNG_ENABLED')) {
                return;
            }

            $ts = time();
            $def = ['ts' => (string) $ts, 'sig' => $this->sign($ts)];

            // Klucz witryny jest publiczny z założenia; tajny nigdy nie trafia do strony.
            if ($this->turnstileActive()) {
                $def['turnstile'] = [
                    'sitekey' => $this->turnstileKeys()['site'],
                    'appearance' => $this->turnstileAppearance(),
                    'newsletter' => (bool) Configuration::get('MZCNG_TS_NEWSLETTER'),
                    'contact' => (bool) Configuration::get('MZCNG_TS_CONTACT'),
                ];
            }

            Media::addJsDef(['mzcng' => $def]);
            $this->context->controller->registerJavascript(
                'mzcng-guard',
                'modules/' . $this->name . '/views/js/guard.js',
                ['position' => 'bottom', 'priority' => 200, 'version' => $this->version]
            );
        } catch (\Throwable $e) {
            // fail-open
        }
    }

    public function hookActionNewsletterRegistrationBefore(&$params)
    {
        try {
            if (!Configuration::get('MZCNG_ENABLED')) {
                return;
            }
            // tylko zapis (action = 0); wypisanie zawsze dozwolone
            if ((int) (isset($params['action']) ? $params['action'] : 0) !== 0) {
                return;
            }
            $email = Tools::strtolower(trim((string) (isset($params['email']) ? $params['email'] : '')));
            $ip = $this->getClientIp();

            $reason = $this->detect($email, $ip);
            if ($reason !== null) {
                $this->log($ip, $email, $reason, 0);
                $params['hookError'] = $this->messageFor($reason);

                return;
            }
            $this->log($ip, $email, 'ok', 1);
        } catch (\Throwable $e) {
            return; // fail-open
        }
    }

    /**
     * Formularz kontaktowy. Moduł contactform nie ma własnego hooka walidacji, więc
     * sprawdzamy Turnstile, zanim obsłuży wysłanie: przy niepowodzeniu usuwamy
     * `submitMessage` z żądania (moduł uznaje wtedy, że nic nie wysłano i tylko
     * pokazuje formularz) i dokładamy komunikat do błędów strony.
     */
    public function hookActionFrontControllerInitAfter($params)
    {
        try {
            $controller = isset($params['controller']) ? $params['controller'] : null;

            if (!Configuration::get('MZCNG_ENABLED')
                || !Configuration::get('MZCNG_TS_CONTACT')
                || !$controller
                || (string) $controller->php_self !== 'contact'
                || !Tools::isSubmit('submitMessage')
                || !$this->turnstileActive()
            ) {
                return;
            }

            $ip = $this->getClientIp();
            $from = Tools::strtolower(trim((string) Tools::getValue('from')));
            $reason = $this->checkTurnstile($ip);

            if (in_array($reason, ['ok', 'ts_unavailable', 'ts_misconfig'], true)) {
                $this->log($ip, $from, $reason === 'ok' ? 'ok_contact' : $reason, 1);

                return;
            }

            $this->log($ip, $from, $reason, 0);
            unset($_POST['submitMessage'], $_GET['submitMessage']);
            $controller->errors[] = $this->messageFor($reason);
        } catch (\Throwable $e) {
            return; // fail-open
        }
    }

    // -------------------------------------------------------------- detection

    private function detect($email, $ip)
    {
        // pole-pułapka: prawdziwy użytkownik go nie widzi
        if ((string) Tools::getValue('mzcng_hp', '') !== '') {
            return 'honeypot';
        }

        // token czasowy
        $ts = (int) Tools::getValue('mzcng_ts', 0);
        $sig = (string) Tools::getValue('mzcng_sig', '');
        $hasToken = $ts > 0 && $sig !== '';
        if (Configuration::get('MZCNG_REQUIRE_TOKEN') && !$hasToken) {
            return 'no_token';
        }
        if ($hasToken) {
            if (!hash_equals($this->sign($ts), $sig)) {
                return 'bad_token';
            }
            $age = time() - $ts;
            if ($age < (int) Configuration::get('MZCNG_MIN_SECONDS')) {
                return 'too_fast';
            }
            if ($age > 86400) {
                return 'token_expired';
            }
        }

        // wzorce adresów (regex, po jednym w linii)
        foreach (preg_split('/\R/', (string) Configuration::get('MZCNG_PATTERNS')) as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '' || $email === '') {
                continue;
            }
            if (@preg_match('~' . str_replace('~', '\~', $pattern) . '~i', $email) === 1) {
                return 'pattern';
            }
        }

        // Turnstile — po tanich filtrach, bo to zapytanie do zewnętrznej usługi
        if (Configuration::get('MZCNG_TS_NEWSLETTER') && $this->turnstileActive()) {
            $verdict = $this->checkTurnstile($ip);

            if (!in_array($verdict, ['ok', 'ts_unavailable', 'ts_misconfig'], true)) {
                return $verdict;
            }
            if ($verdict !== 'ok') {
                // przepuszczone mimo awarii Cloudflare — zostaje ślad w statystykach
                $this->log($ip, $email, $verdict, 1);
            }
        }

        // limity (liczone z dozwolonych prób w ostatniej godzinie)
        $since = date('Y-m-d H:i:s', time() - 3600);
        if ($ip !== '') {
            $perIp = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mzcng_log` WHERE allowed=1 AND ip=\''
                . pSQL($ip) . '\' AND reason=\'ok\' AND created_at >= \'' . pSQL($since) . '\''
            );
            if ($perIp >= (int) Configuration::get('MZCNG_IP_LIMIT')) {
                return 'ip_rate';
            }
        }
        $global = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mzcng_log` WHERE allowed=1 AND reason=\'ok\' AND created_at >= \''
            . pSQL($since) . '\''
        );
        if ($global >= (int) Configuration::get('MZCNG_GLOBAL_LIMIT')) {
            return 'global_rate';
        }

        return null;
    }

    private function messageFor($reason)
    {
        if (in_array($reason, ['ip_rate', 'global_rate'], true)) {
            return $this->l('Zbyt wiele prób zapisu. Spróbuj ponownie później.');
        }
        if ($reason === 'ts_expired') {
            return $this->l('Weryfikacja wygasła. Spróbuj jeszcze raz.');
        }
        if (strpos($reason, 'ts_') === 0) {
            return $this->l('Nie udało się potwierdzić, że nie jesteś robotem. Odśwież stronę i spróbuj ponownie.');
        }

        return $this->l('Nie udało się zapisać adresu. Odśwież stronę i spróbuj ponownie.');
    }

    // -------------------------------------------------------------- turnstile

    /**
     * Klucze: zmienne środowiskowe TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY mają
     * pierwszeństwo (klucz tajny wtedy nie leży w bazie ani w kopiach zapasowych),
     * w przeciwnym razie ustawienia z panelu.
     *
     * @return array{site:string,secret:string,from_env:bool}
     */
    private function turnstileKeys()
    {
        $envSite = trim((string) getenv('TURNSTILE_SITE_KEY'));
        $envSecret = trim((string) getenv('TURNSTILE_SECRET_KEY'));

        if ($envSite !== '' && $envSecret !== '') {
            return ['site' => $envSite, 'secret' => $envSecret, 'from_env' => true];
        }

        return [
            'site' => trim((string) Configuration::get('MZCNG_TS_SITEKEY')),
            'secret' => trim((string) Configuration::get('MZCNG_TS_SECRET')),
            'from_env' => false,
        ];
    }

    /** Czy warstwa Turnstile jest skonfigurowana (oba klucze) i włączona dla któregoś formularza. */
    private function turnstileActive()
    {
        $keys = $this->turnstileKeys();

        return $keys['site'] !== '' && $keys['secret'] !== ''
            && (Configuration::get('MZCNG_TS_NEWSLETTER') || Configuration::get('MZCNG_TS_CONTACT'));
    }

    private function turnstileAppearance()
    {
        return Configuration::get('MZCNG_TS_APPEARANCE') === 'always' ? 'always' : 'interaction-only';
    }

    /**
     * Sprawdza token Turnstile z bieżącego żądania.
     *
     * @return string 'ok' | 'ts_missing' | 'ts_failed' | 'ts_expired'
     *                | 'ts_unavailable' (Cloudflare nie odpowiedział) | 'ts_misconfig' (zły klucz tajny)
     *                — dwa ostatnie przepuszczają formularz.
     */
    private function checkTurnstile($ip)
    {
        $response = (string) Tools::getValue(self::TURNSTILE_FIELD, '');

        if ($response === '') {
            return 'ts_missing';
        }
        if (strlen($response) > 2048) {
            return 'ts_failed';
        }

        $fields = ['secret' => $this->turnstileKeys()['secret'], 'response' => $response];
        if ($ip !== '') {
            $fields['remoteip'] = $ip;
        }

        $ch = curl_init(self::TURNSTILE_VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = is_string($body) ? json_decode($body, true) : null;

        // Brak odpowiedzi, której można zaufać (awaria sieci, 5xx, nie JSON) — formularza nie blokujemy.
        // Status 4xx nie jest awarią: Cloudflare odpowiada 400 z opisem błędu w JSON-ie, np. przy
        // złym kluczu tajnym, i ten opis czytamy poniżej.
        if ($status === 0 || $status >= 500 || !is_array($json) || !array_key_exists('success', $json)) {
            return 'ts_unavailable';
        }
        if (!empty($json['success'])) {
            return 'ok';
        }

        $codes = isset($json['error-codes']) ? (array) $json['error-codes'] : [];
        // Zły lub brakujący klucz tajny to błąd konfiguracji, nie bota: nie wolno przez niego
        // odrzucać wszystkich klientów.
        if (array_intersect(['missing-input-secret', 'invalid-input-secret'], $codes)) {
            return 'ts_misconfig';
        }
        // Błąd po stronie żądania lub usługi to nie werdykt o odwiedzającym.
        if (array_intersect(['bad-request', 'internal-error'], $codes)) {
            return 'ts_unavailable';
        }

        return in_array('timeout-or-duplicate', $codes, true) ? 'ts_expired' : 'ts_failed';
    }

    // ----------------------------------------------------------------- helpers

    private function sign($ts)
    {
        return hash_hmac('sha256', 'mzcng|' . (int) $ts, _COOKIE_KEY_);
    }

    /**
     * Adres klienta: wyłącznie REMOTE_ADDR. Za Cloudflare i Traefikiem ustawia go
     * mod_remoteip (docker/apache/remoteip.conf) z nagłówka CF-Connecting-IP, ale tylko gdy
     * żądanie przyszło od zaufanego proxy. Czytanie CF-Connecting-IP czy X-Forwarded-For
     * tutaj wprost pozwalałoby botowi podawać za każdym razem inny adres i omijać limity.
     */
    private function getClientIp()
    {
        $ip = trim(isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private function log($ip, $email, $reason, $allowed)
    {
        Db::getInstance()->insert('mzcng_log', [
            'ip' => pSQL($ip),
            'email' => pSQL(Tools::substr($email, 0, 255)),
            'reason' => pSQL(Tools::substr($reason, 0, 32)),
            'allowed' => (int) $allowed,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (mt_rand(1, 50) === 1) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'mzcng_log` WHERE created_at < \''
                . pSQL(date('Y-m-d H:i:s', time() - 30 * 86400)) . '\''
            );
        }
    }

    // ------------------------------------------------------------------ admin

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMzcng')) {
            if (!$this->isTokenValid()) {
                $output .= $this->displayError($this->l('Nieprawidłowy token zabezpieczający. Odśwież stronę i spróbuj ponownie.'));
            } else {
                $this->saveSettings();
                Tools::redirectAdmin($this->moduleUrl(['conf' => 4]));
            }
        }

        return $output . $this->renderStatus() . $this->renderForm() . $this->renderStats();
    }

    private function saveSettings()
    {
        foreach (['MZCNG_ENABLED', 'MZCNG_REQUIRE_TOKEN', 'MZCNG_TS_NEWSLETTER', 'MZCNG_TS_CONTACT'] as $key) {
            Configuration::updateValue($key, (int) (bool) Tools::getValue($key));
        }
        Configuration::updateValue('MZCNG_IP_LIMIT', max(1, (int) Tools::getValue('MZCNG_IP_LIMIT')));
        Configuration::updateValue('MZCNG_GLOBAL_LIMIT', max(1, (int) Tools::getValue('MZCNG_GLOBAL_LIMIT')));
        Configuration::updateValue('MZCNG_MIN_SECONDS', max(0, (int) Tools::getValue('MZCNG_MIN_SECONDS')));
        Configuration::updateValue('MZCNG_PATTERNS', (string) Tools::getValue('MZCNG_PATTERNS'));
        Configuration::updateValue('MZCNG_TS_APPEARANCE', Tools::getValue('MZCNG_TS_APPEARANCE') === 'always' ? 'always' : 'interaction-only');
        Configuration::updateValue('MZCNG_TS_SITEKEY', trim((string) Tools::getValue('MZCNG_TS_SITEKEY')));

        // Klucza tajnego nie wyświetlamy z powrotem w formularzu, więc puste pole znaczy
        // „bez zmian”; usuwa się go osobnym przełącznikiem.
        $secret = trim((string) Tools::getValue('MZCNG_TS_SECRET'));
        if (Tools::getValue('MZCNG_TS_SECRET_CLEAR')) {
            Configuration::updateValue('MZCNG_TS_SECRET', '');
        } elseif ($secret !== '') {
            Configuration::updateValue('MZCNG_TS_SECRET', $secret);
        }
    }

    /**
     * Token CSRF. W PrestaShop 9 jest zamaskowany (inny przy każdym wywołaniu), więc
     * porównać go ze świeżo wygenerowanym się nie da — sprawdza go UserTokenManager z rdzenia.
     */
    private function isTokenValid()
    {
        $manager = 'PrestaShopBundle\\Security\\Admin\\UserTokenManager';
        $container = class_exists($manager) ? \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance() : null;

        if ($container !== null) {
            return (bool) $container->get($manager)->isTokenValid();
        }

        return hash_equals((string) Tools::getAdminTokenLite('AdminModules'), (string) Tools::getValue('token'));
    }

    /**
     * Adres tej strony. W PrestaShop 9 daje go router (razem z tokenem `_token`);
     * AdminController::$currentIndex prowadzi przez przekierowanie, które gubi treść POST.
     */
    private function moduleUrl(array $params = [])
    {
        $url = null;
        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();

        if ($container !== null) {
            try {
                $url = (string) $container->get('router')->generate('admin_module_configure_action', ['module_name' => $this->name]);
            } catch (\Throwable $e) {
                // brak tej trasy (PrestaShop 8): poniżej
            }
        }

        if ($url === null) {
            $url = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
        }

        return $params ? $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params) : $url;
    }

    private function renderStatus()
    {
        $keys = $this->turnstileKeys();
        $e = static function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        };

        if ($keys['site'] === '' || $keys['secret'] === '') {
            return '<div class="alert alert-warning">' . $this->l('Turnstile jest wyłączony: brak kluczy. Działają honeypot, token czasowy, filtr adresów i limity. Klucze tworzy się w panelu Cloudflare (Turnstile → Add widget).') . '</div>';
        }

        $source = $keys['from_env']
            ? $this->l('Klucze pochodzą ze zmiennych środowiskowych TURNSTILE_SITE_KEY i TURNSTILE_SECRET_KEY; pola klucza poniżej są wtedy ignorowane.')
            : $this->l('Klucze pochodzą z ustawień poniżej.');
        $recent = Db::getInstance()->getValue(
            'SELECT created_at FROM `' . _DB_PREFIX_ . 'mzcng_log` WHERE reason = \'ts_misconfig\' AND created_at >= \''
            . pSQL(date('Y-m-d H:i:s', time() - 86400)) . '\' ORDER BY id DESC'
        );

        $html = '<div class="alert alert-success">' . $this->l('Turnstile jest aktywny.') . ' ' . $e($source) . '</div>';
        if ($recent) {
            $html .= '<div class="alert alert-danger">' . $this->l('Cloudflare odrzucił klucz tajny (ostatnio:') . ' ' . $e($recent) . '). '
                . $this->l('Sprawdź klucz — do tego czasu formularze przepuszczają każdego, kto przejdzie pozostałe filtry.') . '</div>';
        }

        return $html;
    }

    private function renderForm()
    {
        $switch = function ($name, $label, $desc = '') {
            return [
                'type' => 'switch', 'label' => $label, 'name' => $name, 'is_bool' => true, 'desc' => $desc,
                'values' => [
                    ['id' => $name . '_on', 'value' => 1, 'label' => $this->l('Tak')],
                    ['id' => $name . '_off', 'value' => 0, 'label' => $this->l('Nie')],
                ],
            ];
        };
        $hasSecret = trim((string) Configuration::get('MZCNG_TS_SECRET')) !== '';

        $form = [
            'form' => [
                'legend' => ['title' => $this->l('Ustawienia'), 'icon' => 'icon-cogs'],
                'input' => [
                    $switch('MZCNG_ENABLED', $this->l('Ochrona włączona')),
                    $switch('MZCNG_REQUIRE_TOKEN', $this->l('Wymagaj tokenu z przeglądarki'),
                        $this->l('Newsletter: odrzuca zapisy wysyłane bezpośrednio przez boty (bez JavaScript).')),
                    ['type' => 'text', 'label' => $this->l('Limit zapisów z jednego IP na godzinę'), 'name' => 'MZCNG_IP_LIMIT', 'class' => 'fixed-width-sm'],
                    ['type' => 'text', 'label' => $this->l('Limit zapisów w sumie na godzinę'), 'name' => 'MZCNG_GLOBAL_LIMIT', 'class' => 'fixed-width-sm'],
                    ['type' => 'text', 'label' => $this->l('Minimalny czas od załadowania strony (s)'), 'name' => 'MZCNG_MIN_SECONDS', 'class' => 'fixed-width-sm'],
                    ['type' => 'textarea', 'label' => $this->l('Zablokowane wzorce adresów (regex, po jednym w linii)'), 'name' => 'MZCNG_PATTERNS', 'rows' => 6, 'cols' => 60],
                    $switch('MZCNG_TS_NEWSLETTER', $this->l('Turnstile w zapisie do newslettera')),
                    $switch('MZCNG_TS_CONTACT', $this->l('Turnstile w formularzu kontaktowym')),
                    ['type' => 'text', 'label' => $this->l('Turnstile: klucz witryny (site key)'), 'name' => 'MZCNG_TS_SITEKEY', 'class' => 'fixed-width-xxl'],
                    [
                        'type' => 'password',
                        'label' => $this->l('Turnstile: klucz tajny (secret key)'),
                        'name' => 'MZCNG_TS_SECRET',
                        'autocomplete' => false,
                        'desc' => $hasSecret
                            ? $this->l('Klucz jest zapisany. Pozostaw puste, aby go nie zmieniać; wpisz nowy, aby zastąpić.')
                            : $this->l('Wklej klucz tajny z panelu Cloudflare.'),
                    ],
                    $switch('MZCNG_TS_SECRET_CLEAR', $this->l('Usuń zapisany klucz tajny'), $this->l('Zaznacz „Tak” i zapisz, aby go skasować.')),
                    [
                        'type' => 'select',
                        'label' => $this->l('Turnstile: widoczność widżetu'),
                        'name' => 'MZCNG_TS_APPEARANCE',
                        'options' => [
                            'query' => [
                                ['id' => 'interaction-only', 'name' => $this->l('Tylko gdy wymagana interakcja (zalecane)')],
                                ['id' => 'always', 'name' => $this->l('Zawsze widoczny')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Zapisz')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitMzcng';
        $helper->currentIndex = $this->moduleUrl();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = [
            'MZCNG_ENABLED' => (int) Configuration::get('MZCNG_ENABLED'),
            'MZCNG_REQUIRE_TOKEN' => (int) Configuration::get('MZCNG_REQUIRE_TOKEN'),
            'MZCNG_IP_LIMIT' => (int) Configuration::get('MZCNG_IP_LIMIT'),
            'MZCNG_GLOBAL_LIMIT' => (int) Configuration::get('MZCNG_GLOBAL_LIMIT'),
            'MZCNG_MIN_SECONDS' => (int) Configuration::get('MZCNG_MIN_SECONDS'),
            'MZCNG_PATTERNS' => (string) Configuration::get('MZCNG_PATTERNS'),
            'MZCNG_TS_NEWSLETTER' => (int) Configuration::get('MZCNG_TS_NEWSLETTER'),
            'MZCNG_TS_CONTACT' => (int) Configuration::get('MZCNG_TS_CONTACT'),
            'MZCNG_TS_SITEKEY' => (string) Configuration::get('MZCNG_TS_SITEKEY'),
            // Klucza tajnego nigdy nie wysyłamy z powrotem do przeglądarki.
            'MZCNG_TS_SECRET' => '',
            'MZCNG_TS_SECRET_CLEAR' => 0,
            'MZCNG_TS_APPEARANCE' => $this->turnstileAppearance(),
        ];

        return $helper->generateForm([$form]);
    }

    private function renderStats()
    {
        $e = static function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        };
        $since = pSQL(date('Y-m-d H:i:s', time() - 86400));
        $rows = Db::getInstance()->executeS(
            'SELECT reason, allowed, COUNT(*) n FROM `' . _DB_PREFIX_ . 'mzcng_log`
             WHERE created_at >= \'' . $since . '\' GROUP BY reason, allowed ORDER BY n DESC'
        );
        $last = Db::getInstance()->executeS(
            'SELECT created_at, ip, email, reason, allowed FROM `' . _DB_PREFIX_ . 'mzcng_log`
             ORDER BY id DESC LIMIT 30'
        );
        $h = '<div class="panel"><div class="panel-heading">' . $this->l('Ostatnie 24 godziny') . '</div>';
        if (!$rows) {
            $h .= '<p>' . $this->l('Brak prób.') . '</p>';
        } else {
            $h .= '<table class="table"><tr><th>' . $this->l('Powód') . '</th><th>' . $this->l('Przepuszczone') . '</th><th>' . $this->l('Liczba') . '</th></tr>';
            foreach ($rows as $r) {
                $h .= '<tr><td>' . $e($r['reason']) . '</td><td>' . ($r['allowed'] ? 'tak' : 'nie') . '</td><td>' . (int) $r['n'] . '</td></tr>';
            }
            $h .= '</table>';
        }
        $h .= '</div><div class="panel"><div class="panel-heading">' . $this->l('Ostatnie próby') . '</div>';
        $h .= '<table class="table"><tr><th>Data</th><th>IP</th><th>E-mail</th><th>Wynik</th></tr>';
        foreach ((array) $last as $r) {
            $h .= '<tr><td>' . $e($r['created_at']) . '</td><td>' . $e($r['ip'])
                . '</td><td>' . $e($r['email']) . '</td><td>'
                . ($r['allowed'] ? 'OK' : 'odrzucony: ' . $e($r['reason'])) . '</td></tr>';
        }
        $h .= '</table></div>';

        return $h;
    }
}
