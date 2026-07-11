<?php
/**
 * MZC Mail Content — campaign ObjectModel.
 *
 * One row = one mail content injection campaign for a single email template,
 * with a date window, position, active flag, and per-language HTML body.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcMailcontentCampaign extends ObjectModel
{
    /** Campaign ID forced for the next send (test mode — bypasses date window). */
    private static $forcedId = 0;

    public static function setForcedId($id)
    {
        self::$forcedId = (int) $id;
    }

    public static function getForcedId()
    {
        return self::$forcedId;
    }


    /** @var int */
    public $id_mzc_mailcontent_campaign;

    /** @var string admin label */
    public $name;

    /** @var string mail template name (e.g. order_conf) */
    public $template;

    /** @var string 'top' | 'bottom' */
    public $position;

    /** @var string|null Y-m-d H:i:s */
    public $date_from;

    /** @var string|null Y-m-d H:i:s */
    public $date_to;

    /** @var int 0|1 */
    public $active;

    /** @var array<int,string> id_lang => html */
    public $html;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = [
        'table' => 'mzc_mailcontent_campaign',
        'primary' => 'id_mzc_mailcontent_campaign',
        'multilang' => true,
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
            'template' => ['type' => self::TYPE_STRING, 'validate' => 'isLinkRewrite', 'required' => true, 'size' => 64],
            'position' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 8],
            'date_from' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull'],
            'date_to' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'html' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml'],
        ],
    ];

    /**
     * Find the first active campaign matching $template at $now whose HTML
     * for $idLang is non-empty. Returns the row as array, or null.
     */
    public static function findMatching($template, $idLang, $now = null)
    {
        $template = (string) $template;
        $idLang = (int) $idLang;
        if ($template === '' || $idLang <= 0) {
            return null;
        }

        $now = $now ?: date('Y-m-d H:i:s');

        $sql = 'SELECT c.*, cl.`html`
                FROM `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign` c
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign_lang` cl
                    ON cl.`id_mzc_mailcontent_campaign` = c.`id_mzc_mailcontent_campaign`
                    AND cl.`id_lang` = ' . $idLang . '
                WHERE c.`active` = 1
                    AND c.`template` = "' . pSQL($template) . '"
                    AND (c.`date_from` IS NULL OR c.`date_from` = "0000-00-00 00:00:00" OR c.`date_from` <= "' . pSQL($now) . '")
                    AND (c.`date_to` IS NULL OR c.`date_to` = "0000-00-00 00:00:00" OR c.`date_to` >= "' . pSQL($now) . '")
                    AND cl.`html` IS NOT NULL
                    AND TRIM(cl.`html`) <> ""
                ORDER BY c.`id_mzc_mailcontent_campaign` ASC';
        // Note: Db::getRow() automatically appends LIMIT 1 — do not add it here.

        $row = Db::getInstance()->getRow($sql);

        return $row ?: null;
    }

    /**
     * Return all campaigns for the admin list view, ordered newest-first.
     */
    public static function listAll()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign`
             ORDER BY `id_mzc_mailcontent_campaign` DESC'
        );

        return $rows ?: [];
    }

    /**
     * Create the database tables. Called from the module install().
     */
    public static function installDb()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        $sql1 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign` (
            `id_mzc_mailcontent_campaign` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(128) NOT NULL,
            `template` VARCHAR(64) NOT NULL,
            `position` VARCHAR(8) NOT NULL DEFAULT "bottom",
            `date_from` DATETIME NULL,
            `date_to` DATETIME NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_mzc_mailcontent_campaign`),
            KEY `idx_lookup` (`active`, `template`, `date_from`, `date_to`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $sql2 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign_lang` (
            `id_mzc_mailcontent_campaign` INT(11) UNSIGNED NOT NULL,
            `id_lang` INT(11) UNSIGNED NOT NULL,
            `html` LONGTEXT NULL,
            PRIMARY KEY (`id_mzc_mailcontent_campaign`, `id_lang`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql1) && Db::getInstance()->execute($sql2);
    }

    public static function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign_lang`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_mailcontent_campaign`');
    }
}
