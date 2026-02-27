<?php
/**
 * MZC Carrier Attribute (v1.2.0)
 *
 * Restrict available shipping carriers based on product attribute values.
 * Rules use multi-select conditions: within an attribute group, checked values
 * are OR'd; between groups, the constraint is AND'd.  Unchecked groups impose
 * no restriction.  One rule can cover many combinations at once.
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

class Mzccarrierattribute extends Module
{
    /** @var array Back-office menu tab under Shipping */
    public $tabs = [
        [
            'class_name' => 'AdminMzcCarrierAttribute',
            'parent_class_name' => 'AdminParentShipping',
            'visible' => true,
            'name' => [
                'en' => 'Carrier Attributes',
                'pl' => 'Atrybuty przewoźników',
                'fr' => 'Attributs transporteurs',
                'es' => 'Atributos transportistas',
                'it' => 'Attributi corrieri',
            ],
        ],
    ];

    /** @var MzcCarrierAttrRuleRepository */
    private $repository;

    /** @var MzcCarrierAttrCarrierFilter */
    private $filter;

    public function __construct()
    {
        $this->name = 'mzccarrierattribute';
        $this->tab = 'shipping_logistics';
        $this->version = '1.2.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('MZC Carrier Attribute');
        $this->description = $this->l('Restrict available shipping carriers based on product attribute values. Select multiple values per attribute group — OR within group, AND between groups.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module? All carrier-attribute rules will be deleted.');

        $this->repository = new MzcCarrierAttrRuleRepository();
        $this->filter = new MzcCarrierAttrCarrierFilter($this->repository);

        $this->ensureUpgrade();
    }

    /**
     * Automatically migrate from v1 single-attribute schema when needed.
     */
    private function ensureUpgrade()
    {
        if (Configuration::get('MZC_CARRIER_ATTR_VERSION') !== '2.0') {
            $installer = new MzcCarrierAttrInstaller();
            $installer->installDb();
            Configuration::updateValue('MZC_CARRIER_ATTR_VERSION', '2.0');
        }
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
            && Configuration::updateValue('MZC_CARRIER_ATTR_RULES', '')
            && Configuration::updateValue('MZC_CARRIER_ATTR_VERSION', '2.0');
    }

    public function uninstall()
    {
        $installer = new MzcCarrierAttrInstaller();

        return parent::uninstall()
            && $installer->uninstallDb()
            && Configuration::deleteByName('MZC_CARRIER_ATTR_RULES')
            && Configuration::deleteByName('MZC_CARRIER_ATTR_VERSION');
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
            $subtotals = $presentedCart['subtotals'];
            $notice = $this->l('(some methods restricted by product attributes)');
            $subtotals['shipping']['label'] .= ' ' . $notice;
            $presentedCart['subtotals'] = $subtotals;
        }
    }

    // =========================================================================
    // Back-office configuration
    // =========================================================================

    public function getContent()
    {
        $output = '';

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css?v=' . $this->version);
        $this->context->controller->addJS($this->_path . 'views/js/admin.js?v=' . $this->version);

        if (Tools::isSubmit('submitMzcCarrierAttribute')) {
            $output .= $this->processSave();
        }

        $output .= $this->renderRulesForm();

        return $output;
    }

    /**
     * Render the rules configuration form.
     * Existing rules and metadata are passed as JSON; the admin.js script
     * renders dynamic rule panels with per-group checkbox sections.
     */
    private function renderRulesForm()
    {
        $idLang = (int) $this->context->language->id;

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

        /* Build attribute groups with values for the JS rule builder */
        $attrGroupsData = [];

        foreach ($attributeGroups as $group) {
            $idGroup = (int) $group['id_attribute_group'];
            $attributes = AttributeGroup::getAttributes($idLang, $idGroup);

            if (empty($attributes)) {
                continue;
            }

            $attrValues = [];
            foreach ($attributes as $attr) {
                $attrValues[] = [
                    'id' => (int) $attr['id_attribute'],
                    'name' => $attr['name'],
                ];
            }

            $attrGroupsData[] = [
                'id' => $idGroup,
                'name' => $group['name'],
                'values' => $attrValues,
            ];
        }

        /* Load existing rule groups — conditions are already grouped */
        $ruleGroups = $this->repository->getRuleGroups();
        $enriched = [];

        foreach ($ruleGroups as $rg) {
            /* Ensure condition group keys are strings for JS object */
            $groupedConds = [];
            foreach ($rg['conditions'] as $gid => $attrIds) {
                $groupedConds[(string) $gid] = array_values($attrIds);
            }
            $enriched[] = [
                'conditions' => $groupedConds,
                'carriers' => $rg['carriers'],
            ];
        }

        $configureUrl = AdminController::$currentIndex . '&configure=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'mzc_data_json' => json_encode([
                'attributeGroups' => $attrGroupsData,
                'carrierList' => $carrierList,
                'existingRules' => $enriched,
            ], JSON_HEX_TAG),
            'configure_url' => $configureUrl,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/rules_form.tpl');
    }

    /**
     * Process the rules form save.
     * Reads rule[<index>][conditions][<groupId>][] and rule[<index>][carriers][] arrays.
     */
    private function processSave()
    {
        $rulesData = Tools::getValue('rule', []);

        $ruleGroups = [];
        if (is_array($rulesData)) {
            foreach ($rulesData as $ruleData) {
                if (!is_array($ruleData)) {
                    continue;
                }

                $rawConditions = isset($ruleData['conditions']) ? $ruleData['conditions'] : [];
                $carriers = isset($ruleData['carriers']) ? $ruleData['carriers'] : [];

                if (!is_array($rawConditions) || !is_array($carriers)) {
                    continue;
                }

                /* Build grouped conditions: groupId => [attrId, ...] */
                $groupedConditions = [];
                foreach ($rawConditions as $groupId => $attrIds) {
                    if (!is_array($attrIds)) {
                        continue;
                    }
                    $cleaned = array_unique(array_filter(array_map('intval', $attrIds)));
                    if (!empty($cleaned)) {
                        sort($cleaned);
                        $groupedConditions[(int) $groupId] = array_values($cleaned);
                    }
                }

                $carriers = array_unique(array_filter(array_map('intval', $carriers)));

                if (!empty($groupedConditions) && !empty($carriers)) {
                    ksort($groupedConditions);
                    $ruleGroups[] = [
                        'conditions' => $groupedConditions,
                        'carriers' => array_values($carriers),
                    ];
                }
            }
        }

        $this->repository->saveRuleGroups($ruleGroups);

        return $this->displayConfirmation($this->l('Carrier attribute rules saved successfully.'));
    }
}
