<?php
/**
 * MZC Landing Newsletter
 *
 * Display a landing page with newsletter signup when your store is not ready yet.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mzc_landing_newsletter extends Module
{
    public function __construct()
    {
        $this->name = 'mzc_landing_newsletter';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->controllers = ['subscribe'];
        $this->module_key = '1712969570c596890d1dfad75f469782';
        parent::__construct();

        $this->displayName = $this->l('MZC Landing Newsletter');
        $this->description = $this->l('Display a landing page with newsletter signup when your store is not ready yet.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Module installation
     */
    public function install()
    {
        // Set default message for all languages
        $languages = Language::getLanguages(false);
        $defaultMessage = [];
        $defaultHtml = $this->getDefaultMessageHtml();
        foreach ($languages as $lang) {
            $defaultMessage[$lang['id_lang']] = $defaultHtml;
        }

        $result = parent::install()
            && $this->registerHook('actionFrontControllerInitAfter')
            && $this->registerHook('registerGDPRConsent')
            && $this->registerHook('actionDeleteGDPRCustomer')
            && $this->registerHook('actionExportGDPRData')
            && $this->installDb()
            && $this->ensureEmailSubscriptionTable()
            && Configuration::updateValue('MZC_LANDING_ENABLED', 0)
            && Configuration::updateValue('MZC_LANDING_MESSAGE', $defaultMessage, true)
            && Configuration::updateValue('MZC_LANDING_CSS', '');

        if ($result) {
            $this->fixGdprHookRegistration();
        }

        return $result;
    }

    /**
     * Module uninstallation
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && Configuration::deleteByName('MZC_LANDING_ENABLED')
            && Configuration::deleteByName('MZC_LANDING_MESSAGE')
            && Configuration::deleteByName('MZC_LANDING_CSS');
    }

    /**
     * Create the rate-limit table
     */
    private function installDb()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_landing_ratelimit` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `ip` varchar(45) NOT NULL,
                        `created_at` datetime NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_ip_created` (`ip`, `created_at`)
                ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Drop the rate-limit table
     */
    private function uninstallDb()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_landing_ratelimit`'
        );
    }

    /**
     * Ensure the emailsubscription table exists (in case ps_emailsubscription is not installed)
     */
    private function ensureEmailSubscriptionTable()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'emailsubscription` (
                        `id` int(6) NOT NULL AUTO_INCREMENT,
                        `id_shop` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
                        `id_shop_group` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
                        `email` varchar(255) NOT NULL,
                        `newsletter_date_add` DATETIME NULL,
                        `ip_registration_newsletter` varchar(45) NOT NULL,
                        `http_referer` VARCHAR(255) NULL,
                        `active` TINYINT(1) NOT NULL DEFAULT \'0\',
                        `id_lang` int(10) NOT NULL DEFAULT \'0\',
                        PRIMARY KEY(`id`),
                        UNIQUE KEY `uk_email_shop` (`email`, `id_shop`)
                ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8;';

        $result = Db::getInstance()->execute($sql);

        // Upgrade path: if the table already existed without the unique index,
        // try adding it (will silently fail if duplicates exist — admin can clean up)
        Db::getInstance()->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'emailsubscription`
                        MODIFY `ip_registration_newsletter` varchar(45) NOT NULL'
        );
        Db::getInstance()->execute(
            'ALTER IGNORE TABLE `' . _DB_PREFIX_ . 'emailsubscription`
                        ADD UNIQUE KEY `uk_email_shop` (`email`, `id_shop`)'
        );

        return $result;
    }

    /**
     * Fix a known psgdpr bug where the displayGDPRConsent hook is not registered.
     * psgdpr upgrade 1.4.3 is supposed to re-register it, but it often fails.
     * Without this hook, no GDPR consent checkbox renders anywhere.
     */
    private function fixGdprHookRegistration()
    {
        // Skip if already checked during this request (avoids repeated DB queries)
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $psgdpr = Module::getInstanceByName('psgdpr');
        if ($psgdpr && Validate::isLoadedObject($psgdpr)) {
            if (!$psgdpr->isRegisteredInHook('displayGDPRConsent')) {
                $psgdpr->registerHook('displayGDPRConsent');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Back-office configuration
    // -------------------------------------------------------------------------

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        // Load admin assets
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin_presets.js');

        // Handle CSV export
        if (Tools::isSubmit('exportCsv')) {
            $this->exportCsv();
        }

        // Handle form submission
        if (Tools::isSubmit('submitMzcLandingNewsletter')) {
            $this->saveConfig();
            $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
        }

        // Documentation notice
        $maintenanceUrl = $this->context->link->getAdminLink('AdminMaintenance');
        $maintenanceLink = '<a href="' . $maintenanceUrl . '">' . $this->l('Shop Parameters → General → Maintenance') . '</a>';
        $output .= $this->displayWarning(
            sprintf(
                $this->l('Important: Disable PrestaShop\'s built-in maintenance mode (%s) when using Landing Page mode. If both are enabled, PrestaShop\'s maintenance page takes priority and renders before this module\'s landing page. Add your IP to the Maintenance IP whitelist to bypass the landing page while it is active.'),
                $maintenanceLink
            )
        );

        return $output . $this->renderForm() . $this->renderCssPresets() . $this->renderCssDocumentation() . $this->renderSubscriberTable();
    }

    /**
     * Get CSS preset definitions
     *
     * Metadata (labels, icons, descriptions) stays in PHP for $this->l() translation.
     * CSS content is loaded from standalone files under views/css/presets/.
     */
    private function getCssPresets()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $presetsDir = dirname(__FILE__) . '/views/css/presets/';

        $cache = [
            'modern_dark' => [
                'label' => $this->l('Modern Dark'),
                'icon' => 'icon-moon-o',
                'desc' => $this->l('Dark background with a glassmorphism card and vibrant accent color.'),
                'css' => file_get_contents($presetsDir . 'modern_dark.css'),
            ],
            'modern_light' => [
                'label' => $this->l('Modern Light'),
                'icon' => 'icon-sun-o',
                'desc' => $this->l('Clean white design with warm accent colors and subtle shadows.'),
                'css' => file_get_contents($presetsDir . 'modern_light.css'),
            ],
            'soft_gray' => [
                'label' => $this->l('Soft Gray'),
                'icon' => 'icon-cloud',
                'desc' => $this->l('Minimal and elegant with soft gray tones and muted accents.'),
                'css' => file_get_contents($presetsDir . 'soft_gray.css'),
            ],
        ];

        return $cache;
    }

    /**
     * Render CSS presets panel (delegates to Smarty template)
     */
    private function renderCssPresets()
    {
        $presets = $this->getCssPresets();

        // Build JSON map of preset key => CSS content for the JS loader
        $presetsJson = json_encode(
            array_map(function ($p) {
                return $p['css'];
            }, $presets),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        // Pass only metadata to the template (CSS content goes in JSON block)
        $presetsMeta = [];
        foreach ($presets as $key => $preset) {
            $presetsMeta[$key] = [
                'label' => $preset['label'],
                'icon' => $preset['icon'],
                'desc' => $preset['desc'],
            ];
        }

        $this->context->smarty->assign([
            'presets' => $presetsMeta,
            'presets_json' => $presetsJson,
            'heading_presets' => $this->l('CSS Presets'),
            'desc_presets' => $this->l('Click a preset to load it into the Custom CSS field above. Remember to click Save to apply.'),
            'label_load' => $this->l('Load preset'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/css_presets.tpl');
    }

    /**
     * Render CSS class documentation panel (delegates to Smarty template)
     */
    private function renderCssDocumentation()
    {
        $cssClasses = [
            ['selector' => 'mzc-landing-container', 'element' => $this->l('Outer wrapper'), 'description' => $this->l('The outermost container. Controls max-width and centering of the entire landing page content.')],
            ['selector' => 'mzc-landing-content', 'element' => $this->l('Content card'), 'description' => $this->l('The white card/box that holds logo, message, and form. Controls background, border-radius, padding, and box-shadow.')],
            ['selector' => 'mzc-landing-logo', 'element' => $this->l('Logo wrapper'), 'description' => $this->l('The div wrapping the store logo image. Use to control logo spacing and alignment.')],
            ['selector' => 'mzc-landing-logo img', 'element' => $this->l('Logo image'), 'description' => $this->l('The logo img element itself. Controls max-width, max-height of the logo.')],
            ['selector' => 'mzc-landing-message', 'element' => $this->l('Message area'), 'description' => $this->l('The div containing the admin-configured HTML message. Style h1-h3 and p tags inside it.')],
            ['selector' => 'mzc-landing-form-wrapper', 'element' => $this->l('Form wrapper'), 'description' => $this->l('Wraps the entire form area including the GDPR checkbox. Controls max-width of the form.')],
            ['selector' => 'mzc-landing-form', 'element' => $this->l('Form element'), 'description' => $this->l('The form element itself.')],
            ['selector' => 'mzc-form-group', 'element' => $this->l('Input + button row'), 'description' => $this->l('Flex container holding the email input and subscribe button side by side.')],
            ['selector' => 'mzc-form-input', 'element' => $this->l('Email input'), 'description' => $this->l('The email input field. Controls border, padding, font-size, focus state.')],
            ['selector' => 'mzc-form-button', 'element' => $this->l('Subscribe button'), 'description' => $this->l('The submit button. Controls background color, text color, hover/disabled states.')],
            ['selector' => 'mzc-gdpr-consent', 'element' => $this->l('GDPR checkbox area'), 'description' => $this->l('Wraps the GDPR consent checkbox rendered by the psgdpr module.')],
            ['selector' => 'mzc-form-feedback', 'element' => $this->l('Feedback message'), 'description' => $this->l('The div that appears after form submission showing success or error messages.')],
            ['selector' => 'mzc-form-feedback.mzc-success', 'element' => $this->l('Success message'), 'description' => $this->l('Applied when subscription succeeds. Controls background/text color for success state.')],
            ['selector' => 'mzc-form-feedback.mzc-error', 'element' => $this->l('Error message'), 'description' => $this->l('Applied when subscription fails. Controls background/text color for error state.')],
        ];

        $cssExample = "/* Change background color */\nbody {\n    background-color: #1a1a2e;\n}\n\n/* Style the content card */\n.mzc-landing-content {\n    background: rgba(255,255,255,0.95);\n    border-radius: 12px;\n}\n\n/* Custom button color */\n.mzc-form-button {\n    background: #e94560;\n}\n.mzc-form-button:hover {\n    background: #c73e54;\n}";

        $this->context->smarty->assign([
            'css_classes' => $cssClasses,
            'css_example' => $cssExample,
            'heading_docs' => $this->l('CSS Classes Reference'),
            'desc_docs' => $this->l('Use these CSS classes in the "Custom CSS" field above to style the landing page. All classes are prefixed with "mzc-" to avoid conflicts.'),
            'th_class' => $this->l('CSS Class'),
            'th_element' => $this->l('Element'),
            'th_description' => $this->l('Description'),
            'label_example' => $this->l('Example:'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/css_documentation.tpl');
    }

    /**
     * Save configuration values
     */
    private function saveConfig()
    {
        Configuration::updateValue('MZC_LANDING_ENABLED', (int) Tools::getValue('MZC_LANDING_ENABLED'));
        Configuration::updateValue('MZC_LANDING_CSS', $this->sanitizeCss(Tools::getValue('MZC_LANDING_CSS')), false);

        $languages = Language::getLanguages(false);
        $messageValues = [];
        foreach ($languages as $lang) {
            $messageValues[$lang['id_lang']] = Tools::getValue('MZC_LANDING_MESSAGE_' . $lang['id_lang']);
        }
        Configuration::updateValue('MZC_LANDING_MESSAGE', $messageValues, true);
    }

    /**
     * Render the admin configuration form
     */
    private function renderForm()
    {
        $languages = Language::getLanguages(false);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Landing Page Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Landing Page'),
                        'name' => 'MZC_LANDING_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('When enabled, all front-office visitors will see the landing page instead of the store.'),
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'lang' => true,
                        'label' => $this->l('Landing Page Message'),
                        'name' => 'MZC_LANDING_MESSAGE',
                        'desc' => $this->l('The message displayed on the landing page. HTML is allowed.'),
                        'autoload_rte' => true,
                        'cols' => 60,
                        'rows' => 10,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Custom CSS'),
                        'name' => 'MZC_LANDING_CSS',
                        'desc' => $this->l('Add custom CSS to style the landing page. Do not include style tags.'),
                        'cols' => 60,
                        'rows' => 15,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitMzcLandingNewsletter';
        $helper->languages = $languages;

        // Multi-store context
        if (Shop::isFeatureActive()) {
            $helper->id = (int) Tools::getValue('id_shop');
        }

        // Load current values (shop-context-aware)
        $helper->fields_value['MZC_LANDING_ENABLED'] = Configuration::get('MZC_LANDING_ENABLED');
        $helper->fields_value['MZC_LANDING_CSS'] = Configuration::get('MZC_LANDING_CSS');

        foreach ($languages as $lang) {
            $helper->fields_value['MZC_LANDING_MESSAGE'][$lang['id_lang']] =
                Configuration::get('MZC_LANDING_MESSAGE', $lang['id_lang']);
        }

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Render the subscriber table in admin (delegates to Smarty template)
     */
    private function renderSubscriberTable()
    {
        $perPage = 20;
        $page = max(1, (int) Tools::getValue('mzc_page', 1));
        $totalCount = $this->getSubscriberCount();
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $subscribers = $this->getSubscribers($perPage, $offset);

        // Resolve language names using cached map (avoids N+1 Language instantiation)
        $langMap = $this->getLanguageNameMap();
        foreach ($subscribers as &$sub) {
            $sub['lang_name'] = isset($langMap[(int) $sub['id_lang']])
                ? $langMap[(int) $sub['id_lang']]
                : '';
        }
        unset($sub);

        $baseUrl = AdminController::$currentIndex
            . '&configure=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'subscribers' => $subscribers,
            'subscriber_count' => $totalCount,
            'refresh_link' => $baseUrl,
            'csv_link' => $baseUrl,
            'heading_subscribers' => $this->l('Subscribers from Landing Page'),
            'label_refresh' => $this->l('Refresh list'),
            'label_export' => $this->l('Export CSV'),
            'label_no_subscribers' => $this->l('No subscribers collected from the landing page yet.'),
            'th_id' => $this->l('ID'),
            'th_email' => $this->l('Email'),
            'th_date' => $this->l('Date'),
            'th_ip' => $this->l('IP'),
            'th_language' => $this->l('Language'),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_base_url' => $baseUrl,
            'label_page' => $this->l('Page'),
            'label_of' => $this->l('of'),
            'label_prev' => $this->l('Previous'),
            'label_next' => $this->l('Next'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/subscriber_table.tpl');
    }

    /**
     * Get subscribers that came from the landing page
     *
     * @param int $limit Number of rows to return (0 = all, used by CSV export)
     * @param int $offset Number of rows to skip for pagination
     */
    private function getSubscribers($limit = 0, $offset = 0)
    {
        $idShop = (int) $this->context->shop->id;

        $sql = 'SELECT `id`, `email`, `newsletter_date_add`, `ip_registration_newsletter`, `id_lang`
                        FROM `' . _DB_PREFIX_ . 'emailsubscription`
                        WHERE `http_referer` = \'mzc_landing_page\'
                        AND `id_shop` = ' . $idShop . '
                        ORDER BY `newsletter_date_add` DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $offset . ', ' . (int) $limit;
        }

        $result = Db::getInstance()->executeS($sql);

        return $result ? $result : [];
    }

    /**
     * Count total subscribers from the landing page (for pagination)
     */
    private function getSubscriberCount()
    {
        $idShop = (int) $this->context->shop->id;

        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'emailsubscription`
                        WHERE `http_referer` = \'mzc_landing_page\'
                        AND `id_shop` = ' . $idShop
        );
    }

    /**
     * Build a cached map of language ID to name for subscriber display.
     * Uses Language::getLanguages() (single query) instead of instantiating
     * a Language object per subscriber row (N+1 problem).
     */
    private function getLanguageNameMap()
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Language::getLanguages(false) as $lang) {
                $map[(int) $lang['id_lang']] = $lang['name'];
            }
        }

        return $map;
    }

    /**
     * Export subscribers as CSV
     */
    private function exportCsv()
    {
        $subscribers = $this->getSubscribers();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="landing_newsletter_subscribers_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Email', 'Date', 'IP', 'Language']);

        $langMap = $this->getLanguageNameMap();
        foreach ($subscribers as $sub) {
            $langName = isset($langMap[(int) $sub['id_lang']])
                ? $langMap[(int) $sub['id_lang']]
                : '';

            fputcsv($output, [
                $sub['id'],
                $sub['email'],
                $sub['newsletter_date_add'],
                $sub['ip_registration_newsletter'],
                $langName,
            ]);
        }

        fclose($output);
        exit;
    }

    // -------------------------------------------------------------------------
    // Front-office hook: intercept all requests when landing mode is active
    // -------------------------------------------------------------------------

    /**
     * Intercept front-office requests and display the landing page
     */
    public function hookActionFrontControllerInitAfter($params)
    {
        // Only activate when enabled
        if (!Configuration::get('MZC_LANDING_ENABLED')) {
            return;
        }

        // Skip for the module's own controllers (subscribe endpoint)
        if (
            $this->context->controller instanceof ModuleFrontController
            && $this->context->controller->module instanceof Module
            && $this->context->controller->module->name === $this->name
        ) {
            return;
        }

        // Skip for admin users and whitelisted IPs
        if ($this->isAdminOrWhitelisted()) {
            return;
        }

        // Build shop logo URL
        $shopLogo = '';
        $logoFilename = Configuration::get('PS_LOGO');
        if ($logoFilename) {
            $shopLogo = $this->context->link->getMediaLink(_PS_IMG_ . $logoFilename);
        }

        // Get landing message for current language
        $idLang = (int) $this->context->language->id;
        $message = Configuration::get('MZC_LANDING_MESSAGE', $idLang);
        if (!$message) {
            $message = $this->getDefaultMessageHtml();
        }

        // Generate subscribe URL and CSRF token
        $subscribeUrl = $this->context->link->getModuleLink($this->name, 'subscribe');
        $token = $this->generateToken();

        // Execute GDPR consent hook
        $this->fixGdprHookRegistration();
        $gdprConsent = '';
        try {
            $gdprConsent = Hook::exec('displayGDPRConsent', ['id_module' => (int) $this->id]);
        } catch (\Exception $e) {
            // Landing page still works without the GDPR consent widget
        }

        // Fetch SEO meta from the index (home) page
        $metaTitle = $this->context->shop->name;
        $metaDescription = '';
        $metaKeywords = '';

        $idMeta = (int) Db::getInstance()->getValue(
            'SELECT id_meta FROM `' . _DB_PREFIX_ . 'meta` WHERE page = "index"'
        );
        if ($idMeta) {
            $meta = new Meta($idMeta, $idLang);
            if (Validate::isLoadedObject($meta)) {
                if (!empty($meta->title)) {
                    $metaTitle = $meta->title;
                }
                if (!empty($meta->description)) {
                    $metaDescription = $meta->description;
                }
                if (!empty($meta->keywords)) {
                    $metaKeywords = $meta->keywords;
                }
            }
        }

        // Assign template variables
        $this->context->smarty->assign([
            'shop_logo' => $shopLogo,
            'shop_name' => $this->context->shop->name,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'landing_message' => $message,
            'custom_css' => Configuration::get('MZC_LANDING_CSS') ?: '',
            'id_module' => (int) $this->id,
            'gdpr_consent' => $gdprConsent ?: '',
            'subscribe_url' => $subscribeUrl,
            'token' => $token,
            'css_url' => $this->_path . 'views/css/landing.css',
            'js_url' => $this->_path . 'views/js/landing.js',
            'language' => [
                'iso_code' => $this->context->language->iso_code,
            ],
        ]);

        // Send 503 + render the landing page
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Retry-After: 3600');
        header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self'; frame-src 'self'");

        echo $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/front/landing.tpl'
        );
        exit;
    }

    /**
     * Check if the current visitor is an admin or has a whitelisted IP
     */
    private function isAdminOrWhitelisted()
    {
        // Check maintenance IP whitelist (reuses PrestaShop's PS_MAINTENANCE_IP setting)
        $maintenanceIps = Configuration::get('PS_MAINTENANCE_IP');
        if (!empty($maintenanceIps)) {
            $ips = array_map('trim', explode(',', $maintenanceIps));
            $currentIp = Tools::getRemoteAddr();

            // Use Symfony IpUtils for CIDR support (e.g. 192.168.1.0/24), fall back to exact match
            if (class_exists('Symfony\\Component\\HttpFoundation\\IpUtils')) {
                if (\Symfony\Component\HttpFoundation\IpUtils::checkIp($currentIp, $ips)) {
                    return true;
                }
            } elseif (in_array($currentIp, $ips)) {
                return true;
            }
        }

        // Check if an employee is logged in via the back-office cookie.
        // Note: $this->context->employee is NOT populated in front-office context,
        // so we read the admin cookie directly — matching PrestaShop's own maintenance bypass.
        // Default to true if PS_MAINTENANCE_ALLOW_ADMINS is not set (fail-open for admins).
        $allowAdmins = Configuration::get('PS_MAINTENANCE_ALLOW_ADMINS');
        if ($allowAdmins === false || $allowAdmins) {
            $adminCookie = new Cookie('psAdmin', '', (int) Configuration::get('PS_COOKIE_LIFETIME_BO'));
            if ($adminCookie->id_employee) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize CSS input to prevent style-tag breakout (stored XSS).
     *
     * Strips any occurrence of </style (case-insensitive) which would allow
     * an admin to break out of the style block and inject arbitrary HTML/JS.
     * Also removes HTML tags entirely — valid CSS never contains them.
     */
    private function sanitizeCss($css)
    {
        if (empty($css)) {
            return '';
        }

        // Remove any HTML tags (CSS should never contain them)
        $css = strip_tags($css);

        // Belt-and-suspenders: neutralise </style breakout even in encoded forms
        $css = preg_replace('/<\s*\/\s*style/i', '/* blocked */', $css);

        return $css;
    }

    /**
     * Generate a CSRF token for the subscribe form
     *
     * @param int $hourOffset hour offset for token generation window
     *
     * @return string
     */
    public function generateToken($hourOffset = 0)
    {
        $hour = (int) floor(time() / 3600) + $hourOffset;

        return hash('sha256', _COOKIE_KEY_ . 'mzc_landing_newsletter' . $hour);
    }

    /**
     * Get the default landing page message HTML from template
     *
     * @return string
     */
    private function getDefaultMessageHtml()
    {
        $this->context->smarty->assign([
            'coming_soon_title' => $this->l('We\'re Coming Soon!'),
            'coming_soon_text' => $this->l('Our store is under construction. Subscribe to our newsletter to be notified when we launch.'),
        ]);

        return $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/front/default_message.tpl'
        );
    }

    // -------------------------------------------------------------------------
    // GDPR hooks
    // -------------------------------------------------------------------------

    /**
     * GDPR: Delete customer data collected by this module
     */
    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (!empty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'emailsubscription`
                                        WHERE `email` = \'' . pSQL($customer['email']) . '\'
                                        AND `http_referer` = \'mzc_landing_page\'';

            if (Db::getInstance()->execute($sql)) {
                // Also purge any expired rate-limit entries (IP-based, not email-linked,
                // but we perform housekeeping here to minimise personal data retention)
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'mzc_landing_ratelimit`
                                        WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
                );

                return json_encode(true);
            }
        }

        return json_encode($this->l('Unable to delete customer data from MZC Landing Newsletter.'));
    }

    /**
     * GDPR: Export customer data collected by this module
     */
    public function hookActionExportGDPRData($customer)
    {
        if (!empty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'SELECT `email`, `newsletter_date_add` AS `subscribed_on`,
                                                    `ip_registration_newsletter` AS `ip`
                                        FROM `' . _DB_PREFIX_ . 'emailsubscription`
                                        WHERE `email` = \'' . pSQL($customer['email']) . '\'
                                        AND `http_referer` = \'mzc_landing_page\'';

            $result = Db::getInstance()->executeS($sql);

            if ($result && count($result)) {
                return json_encode($result);
            }
        }

        return json_encode($this->l('No data found for this customer in MZC Landing Newsletter. Note: IP-based rate-limit entries are automatically purged after 10 minutes and are not linked to email addresses.'));
    }
}
