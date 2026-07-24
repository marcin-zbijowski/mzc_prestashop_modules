<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use MzcProductFieldsBackground;

/**
 * Upload form for a preview background image (Background\BackgroundController)
 * — mirrors FontUploadType: a plain Symfony `FileType`, not any
 * PrestaShop-specific upload widget.
 */
class BackgroundUploadType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $this->trans('Name', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('E.g. "Front", "Back" — shown in the "Preview background image" dropdown when configuring a field.', 'Modules.Mzcproductfields.Admin'),
            ])
            ->add('file', FileType::class, [
                'label' => $this->trans('Image file', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Allowed: %extensions% — max 5 MB.', 'Modules.Mzcproductfields.Admin', [
                    '%extensions%' => implode(', ', MzcProductFieldsBackground::ALLOWED_EXTENSIONS),
                ]),
                'mapped' => false,
                'required' => true,
            ])
        ;
    }
}
