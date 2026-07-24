<?php
/**
 * MZC Product Fields — front-office AJAX endpoint backing the content-
 * confirmation step (plan section 5): validates and stages a product's
 * submitted personalization values into the cart (via CartFieldValueWriter)
 * BEFORE the native add-to-cart submit, so the confirmation modal can show
 * the authoritative computed price and the real submit can reference the
 * resulting id_customization.
 *
 * A plain legacy ModuleFrontController, not a Symfony route: front-office
 * requests never boot a Symfony kernel (confirmed while building the live
 * preview — see Mzcproductfields::hookDisplayProductAdditionalInfo's
 * docblock), so our services are instantiated directly here too.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcproductfieldsSaveCustomizationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        $response = [
            'success' => false,
            'errors' => [],
            'id_customization' => null,
            'total_surcharge_formatted' => null,
        ];

        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0) {
            $response['errors'][] = $this->trans('Invalid product.', [], 'Shop.Notifications.Error');
            $this->renderJson($response);

            return;
        }

        // The native product form never actually submits a plain
        // 'id_product_attribute' field for a product with combinations —
        // confirmed empirically: it only carries raw 'group[idAttributeGroup]
        // => idAttribute' selections (e.g. group[4]=24), and core's own
        // add-to-cart AJAX resolves those into the real id_product_attribute
        // itself, client-side, in a separate request our own AJAX call never
        // sees. So we resolve it here the same way core does — required so
        // the customization row we create lines up with the actual
        // cart_product row's own id_product_attribute (see
        // CartFieldValueWriter::write()'s docblock: a mismatch here silently
        // breaks Cart::getProducts()'s own join and drops the whole surcharge
        // from every total). Confirmed as the second half of a real,
        // shipped bug — the first fix (reading a plain 'id_product_attribute'
        // field) never actually worked, since that field was never there.
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        if ($idProductAttribute <= 0) {
            $groupSelections = Tools::getValue('group');
            if (is_array($groupSelections) && $groupSelections) {
                try {
                    $idProductAttribute = (int) Product::getIdProductAttributeByIdAttributes(
                        $idProduct,
                        array_map('intval', $groupSelections),
                        true
                    );
                } catch (\PrestaShopException $e) {
                    $idProductAttribute = 0;
                }
            }
        }

        // Same as core's own ProductController::postProcess() — a cart may
        // not exist yet for a brand new visitor.
        if (!$this->context->cart->id && isset($_COOKIE[$this->context->cookie->getName()])) {
            $this->context->cart->add();
            $this->context->cookie->id_cart = (int) $this->context->cart->id;
        }

        $submittedValues = [];
        foreach ($_POST as $key => $value) {
            if (strpos((string) $key, 'mzc_field_') === 0) {
                $idField = (int) substr((string) $key, strlen('mzc_field_'));
                if ($idField > 0) {
                    $submittedValues[$idField] = $value;
                }
            }
        }

        $idLang = (int) $this->context->language->id;
        $priceCalculator = new \MZConsulting\MzcProductFields\Service\CustomizationPriceCalculator();
        $writer = new \MZConsulting\MzcProductFields\Service\CartFieldValueWriter(
            new \MZConsulting\MzcProductFields\Service\CustomizationFieldSynchronizer(),
            $priceCalculator
        );

        $result = $writer->write($this->context->cart, $idProduct, $idProductAttribute, $submittedValues, $idLang);

        if ($result['errors']) {
            $response['errors'] = $result['errors'];
            $this->renderJson($response);

            return;
        }

        $grossTotal = $priceCalculator->netToGrossAtProductRate($result['total_net_surcharge'], $idProduct);
        $converted = Tools::convertPrice((float) $grossTotal, $this->context->currency);

        $response['success'] = true;
        $response['id_customization'] = $result['id_customization'];
        $response['total_surcharge_formatted'] = $this->context->getCurrentLocale()->formatPrice($converted, $this->context->currency->iso_code);

        $this->renderJson($response);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderJson(array $data): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
