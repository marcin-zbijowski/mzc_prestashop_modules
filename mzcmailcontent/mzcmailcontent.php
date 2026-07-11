<?php
/**
 * MZC Mail Content
 *
 * Inject extra HTML content into selected transactional emails for a configurable
 * date range, per language. Multiple independent campaigns can be defined; each
 * targets one mail template, has its own window and per-language HTML body.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MzcMailcontentCampaign.php';

class Mzcmailcontent extends Module
{
    public function __construct()
    {
        $this->name = 'mzcmailcontent';
        $this->tab = 'emailing';
        $this->version = '1.0.1';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        parent::__construct();

        $this->displayName = $this->l('MZC Mail Content');
        $this->description = $this->l('Inject extra HTML content into selected transactional emails for a configurable date range, per language.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    // -------------------------------------------------------------------------
    // Install / uninstall
    // -------------------------------------------------------------------------

    public function install()
    {
        return parent::install()
            && MzcMailcontentCampaign::installDb();
    }

    public function uninstall()
    {
        $this->cleanupTmpDir();

        return MzcMailcontentCampaign::uninstallDb()
            && parent::uninstall();
    }

    // -------------------------------------------------------------------------
    // Hook: rewrite the resolved template path with injected content
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Back-office configuration page — dispatch
    // -------------------------------------------------------------------------

    public function getContent()
    {
        $action = (string) Tools::getValue('mzcaction', 'list');
        $idCampaign = (int) Tools::getValue('id_campaign');
        $output = '';

        if ($action === 'preview') {
            // Outputs full HTML and exits — bypass everything else.
            $this->renderPreview($idCampaign, (int) Tools::getValue('id_lang'));
        }

        if (Tools::isSubmit('submitMzcMailcontentDelete')) {
            $output .= $this->handleDelete((int) Tools::getValue('id_campaign'));
            $action = 'list';
        }

        if (Tools::isSubmit('submitMzcMailcontentSave')) {
            $result = $this->handleSave();
            if ($result['ok']) {
                $output .= $this->displayConfirmation($result['msg']);
                $action = 'list';
            } else {
                $output .= $this->displayError($result['msg']);
                $action = 'edit';
                $idCampaign = (int) Tools::getValue('id_campaign');
            }
        }

        if (Tools::isSubmit('submitMzcMailcontentTest')) {
            $output .= $this->handleTestSubmit();
        }

        if ($action === 'add' || $action === 'edit') {
            return $output . $this->renderEditForm($idCampaign);
        }

        return $output . $this->renderList();
    }

    // -------------------------------------------------------------------------
    // List view
    // -------------------------------------------------------------------------

    private function renderList()
    {
        $campaigns = MzcMailcontentCampaign::listAll();
        $languages = Language::getLanguages(false);
        $token = Tools::getAdminTokenLite('AdminModules');
        $base = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . $token;

        $rows = [];
        foreach ($campaigns as $c) {
            $rows[] = [
                'id_campaign' => (int) $c['id_mzc_mailcontent_campaign'],
                'name' => $c['name'],
                'template' => $c['template'],
                'position' => $c['position'],
                'date_from' => $this->formatDate($c['date_from']),
                'date_to' => $this->formatDate($c['date_to']),
                'active' => (int) $c['active'],
                'edit_url' => $base . '&mzcaction=edit&id_campaign=' . (int) $c['id_mzc_mailcontent_campaign'],
                'delete_url' => $base . '&submitMzcMailcontentDelete=1&id_campaign=' . (int) $c['id_mzc_mailcontent_campaign'],
                'preview_links' => $this->buildPreviewLinks((int) $c['id_mzc_mailcontent_campaign'], $languages, $base),
            ];
        }

        $employeeEmail = '';
        if (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)) {
            $employeeEmail = (string) $this->context->employee->email;
        }

        $this->context->smarty->assign([
            'mzc_campaigns' => $rows,
            'mzc_languages' => $languages,
            'mzc_default_lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
            'mzc_default_email' => $employeeEmail,
            'add_url' => $base . '&mzcaction=add',
            'form_action' => $base,
            'heading_list' => $this->l('Mail content campaigns'),
            'desc_list' => $this->l('Each campaign injects HTML content into one email template, during its date window, for languages with content.'),
            'label_add' => $this->l('Add new campaign'),
            'label_no_campaigns' => $this->l('No campaigns yet. Use the button above to create the first one.'),
            'th_id' => $this->l('ID'),
            'th_name' => $this->l('Name'),
            'th_template' => $this->l('Template'),
            'th_position' => $this->l('Position'),
            'th_date_from' => $this->l('From'),
            'th_date_to' => $this->l('To'),
            'th_active' => $this->l('Active'),
            'th_actions' => $this->l('Actions'),
            'label_edit' => $this->l('Edit'),
            'label_delete' => $this->l('Delete'),
            'label_preview' => $this->l('Preview'),
            'label_active_yes' => $this->l('Yes'),
            'label_active_no' => $this->l('No'),
            'confirm_delete' => $this->l('Delete this campaign? This cannot be undone.'),
            'heading_test' => $this->l('Send a test email'),
            'desc_test' => $this->l('Sends one campaign\'s template to the chosen address with sample variables. The campaign date window is bypassed; only the matching campaign\'s HTML is injected.'),
            'label_campaign' => $this->l('Campaign'),
            'label_email' => $this->l('Recipient email'),
            'label_lang' => $this->l('Language'),
            'label_send' => $this->l('Send test'),
            'label_no_active' => $this->l('— no campaigns available —'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/list.tpl');
    }

    private function buildPreviewLinks($idCampaign, array $languages, $baseUrl)
    {
        $links = [];
        foreach ($languages as $lang) {
            $links[] = [
                'iso' => $lang['iso_code'],
                'url' => $baseUrl . '&mzcaction=preview&id_campaign=' . $idCampaign . '&id_lang=' . (int) $lang['id_lang'],
            ];
        }

        return $links;
    }

    private function formatDate($value)
    {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '';
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Edit form (add or edit)
    // -------------------------------------------------------------------------

    private function renderEditForm($idCampaign)
    {
        $languages = Language::getLanguages(false);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        foreach ($languages as &$lang) {
            if (!isset($lang['is_default'])) {
                $lang['is_default'] = ((int) $lang['id_lang'] === $defaultLang) ? 1 : 0;
            }
        }
        unset($lang);

        $isEdit = $idCampaign > 0;
        $campaign = $isEdit ? new MzcMailcontentCampaign($idCampaign) : new MzcMailcontentCampaign();

        $templateOptions = [['id' => '', 'name' => $this->l('— select a template —')]];
        foreach ($this->getAvailableMailTemplates() as $tplName) {
            $templateOptions[] = ['id' => $tplName, 'name' => $tplName];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $isEdit
                        ? sprintf($this->l('Edit campaign #%d'), $idCampaign)
                        : $this->l('New campaign'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Name'),
                        'name' => 'name',
                        'required' => true,
                        'desc' => $this->l('Internal label for this campaign — visible only in admin (e.g. "May 2026 promo").'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'active',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Email template'),
                        'name' => 'template',
                        'required' => true,
                        'desc' => $this->l('The transactional email into which the extra content will be injected.'),
                        'options' => ['query' => $templateOptions, 'id' => 'id', 'name' => 'name'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Injection position'),
                        'name' => 'position',
                        'options' => [
                            'query' => [
                                ['id' => 'top', 'name' => $this->l('Top — right after the opening body tag')],
                                ['id' => 'bottom', 'name' => $this->l('Bottom — right before the closing body tag')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'datetime',
                        'label' => $this->l('Date from'),
                        'name' => 'date_from',
                        'desc' => $this->l('Start of the campaign window. Leave empty for no lower bound.'),
                    ],
                    [
                        'type' => 'datetime',
                        'label' => $this->l('Date to'),
                        'name' => 'date_to',
                        'desc' => $this->l('End of the campaign window. Leave empty for no upper bound.'),
                    ],
                    [
                        'type' => 'textarea',
                        'lang' => true,
                        'label' => $this->l('Extra HTML content'),
                        'name' => 'html',
                        'desc' => $this->l('Content injected into the email. Languages without content are skipped.'),
                        'autoload_rte' => true,
                        'cols' => 60,
                        'rows' => 10,
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'id_campaign',
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'mzcaction',
                    ],
                ],
                'buttons' => [
                    [
                        'href' => AdminController::$currentIndex . '&configure=' . $this->name
                            . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->l('Cancel'),
                        'icon' => 'process-icon-cancel',
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
        $helper->submit_action = 'submitMzcMailcontentSave';
        $helper->languages = $languages;

        $helper->fields_value['name'] = $campaign->name ?: Tools::getValue('name', '');
        $helper->fields_value['template'] = $campaign->template ?: Tools::getValue('template', '');
        $helper->fields_value['position'] = $campaign->position ?: Tools::getValue('position', 'bottom');
        $helper->fields_value['date_from'] = $this->formatDate($campaign->date_from) ?: Tools::getValue('date_from', '');
        $helper->fields_value['date_to'] = $this->formatDate($campaign->date_to) ?: Tools::getValue('date_to', '');
        $helper->fields_value['active'] = $isEdit ? (int) $campaign->active : 1;
        $helper->fields_value['id_campaign'] = $idCampaign;
        $helper->fields_value['mzcaction'] = $isEdit ? 'edit' : 'add';

        foreach ($languages as $lang) {
            $helper->fields_value['html'][$lang['id_lang']] = $isEdit && is_array($campaign->html)
                ? (isset($campaign->html[$lang['id_lang']]) ? $campaign->html[$lang['id_lang']] : '')
                : Tools::getValue('html_' . $lang['id_lang'], '');
        }

        return $helper->generateForm([$fieldsForm]);
    }

    // -------------------------------------------------------------------------
    // Save / delete handlers
    // -------------------------------------------------------------------------

    /**
     * @return array{ok:bool,msg:string}
     */
    private function handleSave()
    {
        $idCampaign = (int) Tools::getValue('id_campaign');
        $name = trim((string) Tools::getValue('name'));
        $template = trim((string) Tools::getValue('template'));
        $position = (string) Tools::getValue('position');
        $dateFrom = trim((string) Tools::getValue('date_from'));
        $dateTo = trim((string) Tools::getValue('date_to'));
        $active = (int) Tools::getValue('active');

        if ($name === '') {
            return ['ok' => false, 'msg' => $this->l('Name is required.')];
        }
        if ($template === '' || !in_array($template, $this->getAvailableMailTemplates(), true)) {
            return ['ok' => false, 'msg' => $this->l('Please choose a valid email template.')];
        }
        if (!in_array($position, ['top', 'bottom'], true)) {
            $position = 'bottom';
        }
        if ($dateFrom !== '' && !Validate::isDateFormat($dateFrom)) {
            return ['ok' => false, 'msg' => $this->l('Invalid "Date from" format. Use YYYY-MM-DD HH:MM:SS.')];
        }
        if ($dateTo !== '' && !Validate::isDateFormat($dateTo)) {
            return ['ok' => false, 'msg' => $this->l('Invalid "Date to" format. Use YYYY-MM-DD HH:MM:SS.')];
        }
        if ($dateFrom !== '' && $dateTo !== '' && strtotime($dateFrom) > strtotime($dateTo)) {
            return ['ok' => false, 'msg' => $this->l('"Date from" must be earlier than "Date to".')];
        }

        $campaign = $idCampaign > 0
            ? new MzcMailcontentCampaign($idCampaign)
            : new MzcMailcontentCampaign();

        $campaign->name = $name;
        $campaign->template = $template;
        $campaign->position = $position;
        $campaign->date_from = $dateFrom !== '' ? $dateFrom : null;
        $campaign->date_to = $dateTo !== '' ? $dateTo : null;
        $campaign->active = $active;

        $htmlByLang = [];
        foreach (Language::getLanguages(false) as $lang) {
            $htmlByLang[(int) $lang['id_lang']] = (string) Tools::getValue('html_' . $lang['id_lang'], '');
        }
        $campaign->html = $htmlByLang;

        try {
            $saved = $idCampaign > 0 ? $campaign->update() : $campaign->add();
        } catch (\Exception $e) {
            return ['ok' => false, 'msg' => $this->l('Database error while saving.') . ' ' . $e->getMessage()];
        }

        if (!$saved) {
            return ['ok' => false, 'msg' => $this->l('Failed to save the campaign.')];
        }

        return ['ok' => true, 'msg' => $this->l('Campaign saved.')];
    }

    private function handleDelete($idCampaign)
    {
        if ($idCampaign <= 0) {
            return $this->displayError($this->l('Invalid campaign ID.'));
        }

        $campaign = new MzcMailcontentCampaign($idCampaign);
        if (!Validate::isLoadedObject($campaign)) {
            return $this->displayError($this->l('Campaign not found.'));
        }

        if ($campaign->delete()) {
            return $this->displayConfirmation($this->l('Campaign deleted.'));
        }

        return $this->displayError($this->l('Failed to delete the campaign.'));
    }

    // -------------------------------------------------------------------------
    // Test send
    // -------------------------------------------------------------------------

    private function handleTestSubmit()
    {
        $idCampaign = (int) Tools::getValue('mzc_test_campaign');
        $email = trim((string) Tools::getValue('mzc_test_email'));
        $idLang = (int) Tools::getValue('mzc_test_lang');

        if ($idCampaign <= 0) {
            return $this->displayError($this->l('Please choose a campaign to test.'));
        }
        if (!Validate::isEmail($email)) {
            return $this->displayError($this->l('Invalid recipient email address.'));
        }
        if ($idLang <= 0) {
            return $this->displayError($this->l('Please choose a language for the test email.'));
        }

        $campaign = new MzcMailcontentCampaign($idCampaign);
        if (!Validate::isLoadedObject($campaign)) {
            return $this->displayError($this->l('Campaign not found.'));
        }

        $sent = $this->sendTestEmail($email, $idLang, $campaign);
        if ($sent) {
            return $this->displayConfirmation(sprintf(
                $this->l('Test email "%1$s" sent to %2$s.'),
                $campaign->template,
                $email
            ));
        }

        return $this->displayError($this->l('Failed to send the test email. Check the PrestaShop email configuration.'));
    }

    /**
     * Send the campaign's template to $email in $idLang.
     *
     * Injection is done HERE (not via the Mail override's setForcedId mechanism)
     * so the test always works regardless of whether the override is installed or
     * has bugs. We write the modified template to tmp/ and pass it directly to
     * MailCore::Send, bypassing the override entirely to avoid double-injection.
     */
    private function sendTestEmail($email, $idLang, MzcMailcontentCampaign $campaign)
    {
        $shopName = Configuration::get('PS_SHOP_NAME', null, null, (int) $this->context->shop->id);
        $shopUrl = $this->context->link->getBaseLink();

        $vars = [
            '{firstname}' => 'Test',
            '{lastname}' => 'Recipient',
            '{email}' => $email,
            '{shop_name}' => $shopName,
            '{shop_url}' => $shopUrl,
            '{shop_logo}' => '',
            '{order_name}' => 'TEST-ORDER',
            '{id_order}' => '0',
            '{date}' => Tools::displayDate(date('Y-m-d H:i:s')),
            '{total_paid}' => '0.00',
            '{message}' => $this->l('This is a test email from MZC Mail Content.'),
            '{passwd}' => '********',
            '{lastname_address}' => 'Recipient',
            '{firstname_address}' => 'Test',
        ];

        $iso = Language::getIsoById($idLang);
        if (!$iso) {
            return false;
        }

        // Locate the source .html template using the same priority as the Mail override.
        $srcHtml = $this->resolveSourceTemplate($campaign->template, $iso);
        if ($srcHtml === null) {
            return false;
        }

        $body = @file_get_contents($srcHtml);
        if ($body === false || $body === '') {
            return false;
        }

        // Inject campaign HTML for this language (may be empty — send anyway).
        $extraHtml = isset($campaign->html[$idLang]) ? trim((string) $campaign->html[$idLang]) : '';
        if ($extraHtml !== '') {
            $body = $this->injectHtml($body, $extraHtml, $campaign->position ?: 'bottom');
        }

        // Write modified template to the module mails/ dir.
        // MailCore::Send detects "modules/mzcmailcontent" in the path and looks for
        // the template at {ps_root}/modules/mzcmailcontent/mails/{iso}/{name}.html —
        // which is exactly where we write it.
        $tmpBase = _PS_MODULE_DIR_ . $this->name . '/mails/';
        $tmpDir  = $tmpBase . $iso . '/';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            return false;
        }

        $hash    = substr(md5($body), 0, 10);
        $tmpName = $campaign->template . '_' . $hash;
        $tmpFile = $tmpDir . $tmpName . '.html';

        if (!is_file($tmpFile) && @file_put_contents($tmpFile, $body) === false) {
            return false;
        }

        // Copy .txt version so plain-text clients are happy.
        $srcTxt = dirname($srcHtml) . '/' . $campaign->template . '.txt';
        $tmpTxt = $tmpDir . $tmpName . '.txt';
        if (is_file($srcTxt) && !is_file($tmpTxt)) {
            @copy($srcTxt, $tmpTxt);
        }

        // Call MailCore::Send (the unoverridden class) with our pre-injected template.
        // This prevents the Mail override from trying to inject again on top of what
        // we already did, and — crucially — avoids any bugs in the override that could
        // make the send fail.
        try {
            $sent = (bool) MailCore::Send(
                (int) $idLang,
                $tmpName,
                '[TEST] ' . $campaign->template,
                $vars,
                $email,
                null,
                null,
                null,
                null,
                null,
                $tmpBase,
                false,
                (int) $this->context->shop->id
            );
        } catch (\Exception $e) {
            $sent = false;
        }

        return $sent;
    }

    /**
     * Find the first readable .html file for $template/$iso.
     * Priority: theme legacy override → core mails dir.
     */
    private function resolveSourceTemplate($template, $iso)
    {
        $candidates = [];

        if (defined('_PS_THEME_DIR_')) {
            $candidates[] = rtrim(_PS_THEME_DIR_, '/') . '/mails/' . $iso . '/' . $template . '.html';
        }
        $candidates[] = rtrim(_PS_MAIL_DIR_, '/') . '/' . $iso . '/' . $template . '.html';
        $candidates[] = rtrim(_PS_ROOT_DIR_, '/') . '/mails/' . $iso . '/' . $template . '.html';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    /**
     * Output a preview of the email body with the campaign HTML injected.
     * Echoes full HTML and exits — no PrestaShop chrome.
     */
    private function renderPreview($idCampaign, $idLang)
    {
        if ($idCampaign <= 0 || $idLang <= 0) {
            http_response_code(400);
            exit('Invalid preview parameters.');
        }

        $campaign = new MzcMailcontentCampaign($idCampaign);
        if (!Validate::isLoadedObject($campaign)) {
            http_response_code(404);
            exit('Campaign not found.');
        }

        $iso = Language::getIsoById($idLang);
        if (!$iso) {
            http_response_code(404);
            exit('Language not found.');
        }

        $candidates = [
            rtrim(_PS_ROOT_DIR_, '/') . '/mails/' . $iso . '/' . $campaign->template . '.html',
        ];
        if (defined('_PS_THEME_DIR_')) {
            array_unshift(
                $candidates,
                rtrim(_PS_THEME_DIR_, '/') . '/mails/' . $iso . '/' . $campaign->template . '.html'
            );
        }

        $srcHtml = '';
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $srcHtml = $path;
                break;
            }
        }

        if ($srcHtml === '') {
            http_response_code(404);
            exit('Template file not found for ' . htmlspecialchars($iso) . ': ' . htmlspecialchars($campaign->template) . '.html');
        }

        $body = Tools::file_get_contents($srcHtml);
        $extra = isset($campaign->html[$idLang]) ? trim((string) $campaign->html[$idLang]) : '';

        if ($extra === '') {
            $extra = '<em style="color:#999">' . $this->l('(no content for this language)') . '</em>';
        }

        $body = $this->injectHtml($body, $extra, $campaign->position ?: 'bottom');

        // Replace common placeholders so the preview is readable.
        $shopName = Configuration::get('PS_SHOP_NAME', null, null, (int) $this->context->shop->id);
        $body = strtr($body, [
            '{shop_name}' => $shopName,
            '{shop_url}' => $this->context->link->getBaseLink(),
            '{shop_logo}' => '',
            '{firstname}' => 'Test',
            '{lastname}' => 'Recipient',
            '{email}' => 'test@example.com',
            '{order_name}' => 'TEST-ORDER',
            '{date}' => Tools::displayDate(date('Y-m-d H:i:s')),
            '{message}' => $this->l('Preview message body.'),
        ]);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $body;
        exit;
    }

    private function injectHtml($body, $extraHtml, $position)
    {
        $wrapped = '<div class="mzc-mail-extra" style="margin:24px 0;">' . $extraHtml . '</div>';

        if ($position === 'top') {
            $newBody = preg_replace('/(<body[^>]*>)/i', '$1' . $wrapped, $body, 1, $count);

            return $count > 0 ? $newBody : $wrapped . $body;
        }

        if (stripos($body, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $wrapped . '</body>', $body, 1);
        }

        return $body . $wrapped;
    }

    // -------------------------------------------------------------------------
    // Template discovery
    // -------------------------------------------------------------------------

    /**
     * Build a sorted, deduplicated list of available email template names
     * (basenames without `.html`), drawing from:
     *   1. Core mails:  _PS_ROOT_DIR_/mails/{iso}/*.html
     *   2. Theme legacy override: _PS_THEME_DIR_/mails/{iso}/*.html
     *   3. New renderer (PS 1.7.6+): _PS_ROOT_DIR_/mails/themes/{theme}/core/*.html
     */
    private function getAvailableMailTemplates()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $names = [];
        $languages = Language::getLanguages(true);
        $rootMails = rtrim(_PS_ROOT_DIR_, '/') . '/mails/';

        foreach ($languages as $lang) {
            $this->collectTemplateBasenames($rootMails . $lang['iso_code'] . '/', $names);
        }

        if (defined('_PS_THEME_DIR_')) {
            foreach ($languages as $lang) {
                $this->collectTemplateBasenames(
                    rtrim(_PS_THEME_DIR_, '/') . '/mails/' . $lang['iso_code'] . '/',
                    $names
                );
            }
            $themeName = basename(rtrim(_PS_THEME_DIR_, '/'));
            $this->collectTemplateBasenames($rootMails . 'themes/' . $themeName . '/core/', $names);
        }

        $names = array_values(array_unique($names));
        sort($names);
        $cache = $names;

        return $cache;
    }

    private function collectTemplateBasenames($dir, array &$names)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '*.html');
        if (!$files) {
            return;
        }
        foreach ($files as $file) {
            $base = basename($file, '.html');
            if ($base !== '' && $base !== 'index') {
                $names[] = $base;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Generated mails/ directory housekeeping
    // -------------------------------------------------------------------------

    /**
     * Delete generated injected-HTML files older than $maxAgeSeconds from mails/.
     * Only files whose name matches the {template}_{10hex}.{ext} pattern are removed
     * to avoid touching any real module mail templates that might exist.
     */
    private function cleanupOldTmpFiles($maxAgeSeconds)
    {
        $base = _PS_MODULE_DIR_ . $this->name . '/mails/';
        if (!is_dir($base)) {
            return;
        }
        $threshold = time() - (int) $maxAgeSeconds;
        foreach (glob($base . '*/*.{html,txt}', GLOB_BRACE) ?: [] as $file) {
            if (!preg_match('/_.{10}\.(html|txt)$/', $file)) {
                continue; // skip real module templates
            }
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($file);
            }
        }
    }

    private function cleanupTmpDir()
    {
        $base = _PS_MODULE_DIR_ . $this->name . '/mails/';
        if (!is_dir($base)) {
            return;
        }
        // Only remove generated files (name ends with _{10hex}.html/.txt).
        foreach (glob($base . '*/*.{html,txt}', GLOB_BRACE) ?: [] as $file) {
            if (preg_match('/_.{10}\.(html|txt)$/', $file)) {
                @unlink($file);
            }
        }
        // Remove now-empty iso subdirectories.
        foreach (glob($base . '*', GLOB_ONLYDIR) ?: [] as $subdir) {
            @rmdir($subdir); // only succeeds if the directory is empty
        }
    }
}
