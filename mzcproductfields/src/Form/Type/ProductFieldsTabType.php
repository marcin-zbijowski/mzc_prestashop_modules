<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Content injected into the product edit page via actionProductFormBuilderModifier
 * (see Form\Modifier\ProductFieldsFormModifier) — a read-only summary of which
 * personalization fields apply to this product (inherited from its
 * category/all-products fields, plus its own individually-assigned ones), and
 * a lightweight per-product override (checkboxes to add/remove this product
 * from "specific products"-scoped fields). Full field authoring still only
 * happens on the Field CRUD screen (a); background-per-combination rules are
 * configured globally on their own screen (see
 * Controller\BackgroundRuleController), not here.
 *
 * The block name Symfony derives from this class ("product_fields_tab")
 * must match the block defined in the Twig file below — confirmed this
 * per-field 'form_theme' mechanism exists in both PS 8.2.7 and PS 9.x
 * (different internal extension class, same public option) before relying
 * on it, after the ProductSearchType/ColorPickerType surprises.
 */
class ProductFieldsTabType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('product_field_ids', ChoiceType::class, [
            'label' => false,
            'choices' => array_flip($options['product_field_choices']),
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['resolved_fields'] = $options['resolved_fields'];
        $view->vars['manage_fields_url'] = $options['manage_fields_url'];
        $view->vars['has_product_scoped_fields'] = !empty($options['product_field_choices']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'label' => $this->trans('Personalization fields', 'Modules.Mzcproductfields.Admin'),
                'form_theme' => '@Modules/mzcproductfields/views/templates/admin/product/product_fields_tab.html.twig',
                'resolved_fields' => [],
                'product_field_choices' => [],
                'manage_fields_url' => '',
            ])
            ->setRequired(['resolved_fields', 'product_field_choices', 'manage_fields_url'])
        ;
    }
}
