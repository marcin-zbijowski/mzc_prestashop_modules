<?php
/**
 * MZC Carrier Attribute
 *
 * Restrict available shipping carriers based on product attribute values (e.g. sizes).
 * Uses a whitelist model: configured attribute values only allow specified carriers.
 * Unconfigured attribute values and simple products impose no restriction.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/MzcCarrierAttrInstaller.php';
require_once dirname(__FILE__) . '/classes/MzcCarrierAttrRuleRepository.php';
require_once dirname(__FILE__) . '/classes/MzcCarrierAttrCarrierFilter.php';

class Mzc_carrier_attribute extends Module
{
    /** @var MzcCarrierAttrRuleRepository */
    private $repository;

    /** @var MzcCarrierAttrCarrierFilter */
    private $filter;

    public function __construct()
    {
        $this->name = 'mzc_carrier_attribute';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('MZC Carrier Attribute');
        $this->description = $this->l('Restrict available shipping carriers based on product attribute values (e.g. sizes). Whitelist model: map attribute values to allowed carriers.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module? Check the "Keep data" option in settings if you want to preserve your rules.');

        $this->repository = new MzcCarrierAttrRuleRepository();
        $this->filter = new MzcCarrierAttrCarrierFilter($this->repository);
    }

    // =========================================================================
    // Install / Uninstall
    // =========================================================================

    public function install()
    {
        $installer = new MzcCarrierAttrInstaller();

        return parent::install()
            && $this->registerHook('actionFilterDeliveryOptionList')
            && $this->registerHook('actionCarrierUpdate')
            && $this->registerHook('actionPresentCart')
            && $installer->installDb()
            && Configuration::updateValue('MZC_CARRIER_ATTR_KEEPDATA', 0)
            && Configuration::updateValue('MZC_CARRIER_ATTR_RULES', '');
    }

    public function uninstall()
    {
        $keepData = (bool) Configuration::get('MZC_CARRIER_ATTR_KEEPDATA');

        $result = parent::uninstall();

        if (!$keepData) {
            $installer = new MzcCarrierAttrInstaller();
            $result = $result && $installer->uninstallDb();
        }

        $result = $result
            && Configuration::deleteByName('MZC_CARRIER_ATTR_KEEPDATA')
            && Configuration::deleteByName('MZC_CARRIER_ATTR_RULES');

        return $result;
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    /**
     * Core carrier filtering — remove disallowed carriers from delivery options
     */
    public function hookActionFilterDeliveryOptionList($params)
    {
        $this->filter->filterDeliveryOptions($params['delivery_option_list'], $params['cart']);
    }

    /**
     * Invalidate caches when a carrier is updated in the back office
     */
    public function hookActionCarrierUpdate($params)
    {
        $this->repository->invalidate();
    }

    /**
     * Annotate shipping subtotal label when restrictions are active
     */
    public function hookActionPresentCart($params)
    {
        if (!isset($params['presentedCart'])) {
            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return;
        }

        if (!$this->filter->cartHasRestrictions($cart)) {
            return;
        }

        $presentedCart = &$params['presentedCart'];
        if (isset($presentedCart['subtotals']['shipping'])) {
            $currentLabel = $presentedCart['subtotals']['shipping']['label'];
            $notice = $this->l('(some methods restricted by product attributes)');
            $presentedCart['subtotals']['shipping']['label'] = $currentLabel . ' ' . $notice;
        }
    }

    // =========================================================================
    // Back-office configuration
    // =========================================================================

    public function getContent()
    {
        $output = '';

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');

        if (Tools::isSubmit('submitMzcCarrierAttribute')) {
            $output .= $this->processSave();
        }

        if (Tools::isSubmit('submitMzcCarrierAttributeSettings')) {
            Configuration::updateValue(
                'MZC_CARRIER_ATTR_KEEPDATA',
                (int) Tools::getValue('MZC_CARRIER_ATTR_KEEPDATA')
            );
            $output .= $this->displayConfirmation($this->l('Settings saved.'));
        }

        $output .= $this->renderSettingsForm();
        $output .= $this->renderRulesForm();

        return $output;
    }

    /**
     * Render the general settings form (keep data toggle) via HelperForm
     */
    private function renderSettingsForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Keep data on uninstall'),
                        'name' => 'MZC_CARRIER_ATTR_KEEPDATA',
                        'desc' => $this->l('If enabled, the rules table will be preserved when uninstalling the module, allowing you to reinstall without losing your configuration.'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'keepdata_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'keepdata_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Settings'),
                    'name' => 'submitMzcCarrierAttributeSettings',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitMzcCarrierAttributeSettings';
        $helper->fields_value = [
            'MZC_CARRIER_ATTR_KEEPDATA' => (int) Configuration::get('MZC_CARRIER_ATTR_KEEPDATA'),
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Render the rules configuration form via Smarty template
     */
    private function renderRulesForm()
    {
        $idLang = (int) $this->context->language->id;
        $showOnlyConfigured = (bool) Tools::getValue('show_configured', false);

        $attributeGroups = AttributeGroup::getAttributesGroups($idLang);
        if (empty($attributeGroups)) {
            return $this->display(__FILE__, 'views/templates/admin/no_attributes.tpl');
        }

        $carriers = Carrier::getCarriers($idLang, true, false, false, null, Carrier::ALL_CARRIERS);
        if (empty($carriers)) {
            return $this->display(__FILE__, 'views/templates/admin/no_carriers.tpl');
        }

        $carrierList = [];
        foreach ($carriers as $carrier) {
            $carrierList[] = [
                'id_reference' => (int) $carrier['id_reference'],
                'label' => $carrier['name'] . ' (ref: ' . (int) $carrier['id_reference'] . ')',
            ];
        }

        $rules = $this->repository->getRules();

        $groups = [];
        foreach ($attributeGroups as $group) {
            $idGroup = (int) $group['id_attribute_group'];
            $attributes = AttributeGroup::getAttributes($idLang, $idGroup);

            if (empty($attributes)) {
                continue;
            }

            if ($showOnlyConfigured) {
                $hasConfigured = false;
                foreach ($attributes as $attr) {
                    if (isset($rules[(int) $attr['id_attribute']])) {
                        $hasConfigured = true;
                        break;
                    }
                }
                if (!$hasConfigured) {
                    continue;
                }
            }

            $attrList = [];
            foreach ($attributes as $attr) {
                $idAttr = (int) $attr['id_attribute'];
                if ($showOnlyConfigured && !isset($rules[$idAttr])) {
                    continue;
                }
                $attrList[] = [
                    'id_attribute' => $idAttr,
                    'name' => $attr['name'],
                    'current_refs' => isset($rules[$idAttr]) ? $rules[$idAttr] : [],
                ];
            }

            if (!empty($attrList)) {
                $groups[] = [
                    'id_attribute_group' => $idGroup,
                    'name' => $group['name'],
                    'attributes' => $attrList,
                ];
            }
        }

        $configureUrl = AdminController::$currentIndex . '&configure=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'attribute_groups' => $groups,
            'carrier_list' => $carrierList,
            'configure_url' => $configureUrl,
            'show_only_configured' => $showOnlyConfigured,
            'all_attr_ids' => implode(',', $this->repository->getAllAttributeIds()),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/rules_form.tpl');
    }

    /**
     * Process rules form save
     */
    private function processSave()
    {
        $allAttrIds = Tools::getValue('mzc_attr_ids', '');
        $attrIds = array_filter(array_map('intval', explode(',', $allAttrIds)));

        if (empty($attrIds)) {
            return $this->displayError($this->l('No attributes found to save.'));
        }

        $newRules = [];
        foreach ($attrIds as $idAttr) {
            $selectedRefs = Tools::getValue('rule_' . $idAttr, []);
            if (!is_array($selectedRefs) || empty($selectedRefs)) {
                continue;
            }

            $cleaned = [];
            foreach ($selectedRefs as $ref) {
                $ref = (int) $ref;
                if ($ref > 0) {
                    $cleaned[] = $ref;
                }
            }

            if (!empty($cleaned)) {
                $newRules[$idAttr] = $cleaned;
            }
        }

        $this->repository->saveRules($newRules);

        return $this->displayConfirmation($this->l('Carrier attribute rules saved successfully.'));
    }
}
