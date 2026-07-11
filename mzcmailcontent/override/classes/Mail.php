<?php
/**
 * MZC Mail Content — Mail class override.
 *
 * Injects campaign HTML into the resolved email template before sending.
 * The actionEmailSendBefore hook does NOT pass templatePath by reference
 * in PS 8, so this override is the only reliable way to redirect Mail::Send
 * to a rewritten copy of the template.
 *
 * Installed automatically to override/classes/Mail.php by PS on module install.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
class Mail extends MailCore
{
    public static function Send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $mode = null,
        $templatePath = _PS_MAIL_DIR_,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null,
        $replyToName = null
    ) {
        list($template, $templatePath) = static::mzcInjectContent(
            (int) $idLang,
            (string) $template,
            (string) $templatePath
        );

        return parent::Send(
            $idLang, $template, $subject, $templateVars, $to, $toName,
            $from, $fromName, $fileAttachment, $mode, $templatePath, $die,
            $idShop, $bcc, $replyTo, $replyToName
        );
    }

    /**
     * Attempt to inject MZC campaign HTML into the template.
     * Returns [$template, $templatePath] — either original or rewritten.
     *
     * MailCore::Send detects "/modules/{name}/" in templatePath and resolves
     * the template as {ps_root}/modules/{name}/mails/{iso}/{template}.html.
     *
     * So we write our modified copy to:
     *   mzcmailcontent/mails/{iso}/{template}_{hash}.html
     * and return the module's mails/ dir as the new $templatePath.
     */
    protected static function mzcInjectContent($idLang, $template, $templatePath)
    {
        if (!defined('_PS_MODULE_DIR_')) {
            return [$template, $templatePath];
        }

        $campaignFile = _PS_MODULE_DIR_ . 'mzcmailcontent/classes/MzcMailcontentCampaign.php';
        if (!is_file($campaignFile)) {
            return [$template, $templatePath];
        }
        require_once $campaignFile;

        // Wrap everything in try/catch so any DB error (e.g. table not yet created)
        // causes a silent fallback — the original template is sent unmodified.
        try {
            return static::mzcDoInject($idLang, $template, $templatePath);
        } catch (\Throwable $e) {
            return [$template, $templatePath];
        } catch (\Exception $e) {
            return [$template, $templatePath];
        }
    }

    /** @return array{0:string,1:string} [$template, $templatePath] */
    protected static function mzcDoInject($idLang, $template, $templatePath)
    {
        // Determine which campaign to use (test forced or normal matching).
        $forcedId = MzcMailcontentCampaign::getForcedId();
        if ($forcedId > 0) {
            // Db::getRow() appends LIMIT 1 automatically — do not add it here.
            $row = Db::getInstance()->getRow(
                'SELECT c.*, cl.`html`
                 FROM `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign` c
                 INNER JOIN `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign_lang` cl
                     ON cl.`id_mzc_mailcontent_campaign` = c.`id_mzc_mailcontent_campaign`
                     AND cl.`id_lang` = ' . $idLang . '
                 WHERE c.`id_mzc_mailcontent_campaign` = ' . $forcedId . '
                   AND c.`template` = "' . pSQL($template) . '"'
            );
        } else {
            $row = MzcMailcontentCampaign::findMatching($template, $idLang);
        }

        if (!$row || trim((string) $row['html']) === '') {
            return [$template, $templatePath];
        }

        $iso = Language::getIsoById($idLang);
        if (!$iso) {
            return [$template, $templatePath];
        }

        // Resolve source HTML — mirror PS's own lookup order.
        $srcHtml = static::resolveMailTemplatePath($template, $iso, $templatePath);
        if (!$srcHtml) {
            return [$template, $templatePath];
        }

        $body = @file_get_contents($srcHtml);
        if ($body === false || $body === '') {
            return [$template, $templatePath];
        }

        // Inject the campaign HTML.
        $position = !empty($row['position']) ? $row['position'] : 'bottom';
        $extraHtml = '<div class="mzc-mail-extra" style="margin:24px 0;">' . trim((string) $row['html']) . '</div>';

        if ($position === 'top') {
            $modified = preg_replace('/(<body[^>]*>)/i', '$1' . $extraHtml, $body, 1, $count);
            $body = $count > 0 ? $modified : $extraHtml . $body;
        } else {
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('/<\/body>/i', $extraHtml . '</body>', $body, 1);
            } else {
                $body .= $extraHtml;
            }
        }

        // Write to mails/{iso}/ inside the module dir.
        // MailCore::Send detects "modules/mzcmailcontent" in the path, sets
        // $moduleName = 'mzcmailcontent', and looks for the template at:
        //   {ps_root}/modules/mzcmailcontent/mails/{iso}/{name}.html  ← here.
        $tmpBase = _PS_MODULE_DIR_ . 'mzcmailcontent/mails/';
        $tmpDir = $tmpBase . $iso . '/';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            return [$template, $templatePath];
        }

        $hash = substr(md5($body), 0, 10);
        $tmpName = $template . '_' . $hash;
        $tmpFile = $tmpDir . $tmpName . '.html';

        if (!is_file($tmpFile) && @file_put_contents($tmpFile, $body) === false) {
            return [$template, $templatePath];
        }

        // Copy .txt version unchanged (plain-text clients).
        $srcDir = dirname($srcHtml) . '/';
        $srcTxt = $srcDir . $template . '.txt';
        $tmpTxt = $tmpDir . $tmpName . '.txt';
        if (is_file($srcTxt) && !is_file($tmpTxt)) {
            @copy($srcTxt, $tmpTxt);
        }

        return [$tmpName, $tmpBase];
    }

    /**
     * Find the actual .html file for $template/$iso, replicating PS's lookup order:
     *   1. Active theme override  (_PS_THEME_DIR_/mails/{iso}/)
     *   2. Caller-provided path   ($templatePath/{iso}/)
     *   3. Core mails dir         (_PS_MAIL_DIR_/{iso}/)
     */
    protected static function resolveMailTemplatePath($template, $iso, $templatePath)
    {
        $candidates = [];

        if (defined('_PS_THEME_DIR_')) {
            $candidates[] = rtrim(_PS_THEME_DIR_, '/') . '/mails/' . $iso . '/' . $template . '.html';
        }
        $candidates[] = rtrim($templatePath, '/') . '/' . $iso . '/' . $template . '.html';
        if (defined('_PS_MAIL_DIR_')) {
            $candidates[] = rtrim(_PS_MAIL_DIR_, '/') . '/' . $iso . '/' . $template . '.html';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
