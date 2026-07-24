<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use MzcProductFieldsFont;

/**
 * Upload form for a custom font (Font\FontController) — plain Symfony
 * `FileType`, not any PrestaShop-specific upload widget, so it has none of
 * the cross-version rendering risk already hit with the product/category
 * autocomplete and the color picker.
 */
class FontUploadType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $this->trans('Name', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Shown in the "Preview font" dropdown when configuring a field.', 'Modules.Mzcproductfields.Admin'),
            ])
            ->add('file', FileType::class, [
                'label' => $this->trans('Font file', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Allowed: %extensions% — max 2 MB.', 'Modules.Mzcproductfields.Admin', [
                    '%extensions%' => implode(', ', MzcProductFieldsFont::ALLOWED_EXTENSIONS),
                ]),
                'mapped' => false,
                'required' => true,
            ])
        ;
    }
}
