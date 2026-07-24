<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Modifier;

use Context;
use MZConsulting\MzcProductFields\Form\Type\ProductFieldsTabType;
use MZConsulting\MzcProductFields\Repository\FieldRepository;
use MzcProductFieldsField;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Injects the "Personalization fields" tab into the product edit page via the
 * actionProductFormBuilderModifier hook (see Mzcproductfields::hookActionProductFormBuilderModifier)
 * — the first real implementation of this mechanism in this codebase (Anvanto
 * registers the hook but never implemented a handler for it).
 *
 * Modeled on the (never-enabled) ProductFormModifier prototype in
 * mzc_an_productattributes_visual, which showed the addAfter()/FormBuilderModifier
 * pattern but never verified it actually renders as a tab — confirmed
 * separately, empirically, before building this: 'options' exists as a
 * top-level product form field in both PS 8.2.7 and PS 9.x, and the per-field
 * 'form_theme' option (which ProductFieldsTabType relies on) is honored by
 * both versions' FormThemeExtension (different internal path, same behavior).
 */
final class ProductFieldsFormModifier
{
    private FormBuilderModifier $formBuilderModifier;
    private FieldRepository $fieldRepository;
    private RouterInterface $router;

    public function __construct(
        FormBuilderModifier $formBuilderModifier,
        FieldRepository $fieldRepository,
        RouterInterface $router
    ) {
        $this->formBuilderModifier = $formBuilderModifier;
        $this->fieldRepository = $fieldRepository;
        $this->router = $router;
    }

    public function modify(int $productId, FormBuilderInterface $productFormBuilder): void
    {
        if ($productId <= 0) {
            return;
        }

        $idLang = (int) Context::getContext()->language->id;

        $resolvedFields = MzcProductFieldsField::getFieldsForProduct($productId, $idLang);

        $productScopedFields = MzcProductFieldsField::getActiveProductScopedFields($idLang);
        $choices = [];
        foreach ($productScopedFields as $field) {
            $choices[(int) $field['id_mzc_productfields_field']] = $field['label'];
        }

        $checkedFieldIds = $this->fieldRepository->getFieldIdsForProduct($productId);

        $this->formBuilderModifier->addAfter(
            $productFormBuilder,
            'options',
            'mzc_product_fields',
            ProductFieldsTabType::class,
            [
                'data' => ['product_field_ids' => $checkedFieldIds],
                'resolved_fields' => $resolvedFields,
                'product_field_choices' => $choices,
                'manage_fields_url' => $this->router->generate('mzc_productfields_field_index'),
            ]
        );
    }
}
