<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One global background-per-attribute-value rule (see
 * Service/CombinationBackgroundResolver's docblock) — picks any set of
 * attribute values from anywhere in the catalog (e.g. "Canvas" + "30x40cm",
 * or just "21x30cm" on its own) and a background image to show whenever a
 * customer has all of them currently selected, on any product.
 *
 * Font, font size, and vertical position are optional PER-RULE overrides, not
 * per-field or per-background: the mockup image a rule points to is what
 * actually determines which font/position looks right (e.g. a rustic font
 * sitting lower on a canvas photo, a modern one sitting higher on a black
 * frame), independent of whichever field's text happens to render there —
 * see front-product-fields.js's resolveActiveRule()/repositionAll() for how a
 * rule's own font/size/pos_y takes priority over the field's own
 * preview_font/preview_font_size/preview_pos_y once it's set. Left empty, the
 * field's own default is used, unchanged from before this existed.
 */
class BackgroundRuleType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('attribute_ids', ChoiceType::class, [
            'label' => $this->trans('Attribute values (all must be selected together)', 'Modules.Mzcproductfields.Admin'),
            'choices' => $options['attribute_value_choices'],
            'multiple' => true,
            'required' => true,
            // The native `size` attribute controls how many rows the
            // multiselect shows before scrolling — left at the browser
            // default (4) this was unusable once the catalog has several
            // attribute groups totalling ~40 values, per real feedback.
            'attr' => ['size' => 20],
        ]);

        $builder->add('id_background', ChoiceType::class, [
            'label' => $this->trans('Background image', 'Modules.Mzcproductfields.Admin'),
            'choices' => array_flip($options['background_choices']),
            'required' => true,
        ]);

        $builder->add('font', ChoiceType::class, [
            'label' => $this->trans('Font', 'Modules.Mzcproductfields.Admin'),
            'help' => $this->trans('Overrides whichever field ends up rendering on this rule\'s background image. Leave as "Field default" to keep using each field\'s own font.', 'Modules.Mzcproductfields.Admin'),
            'required' => false,
            'placeholder' => empty($options['font_choices'])
                ? $this->trans('No custom fonts uploaded yet', 'Modules.Mzcproductfields.Admin')
                : $this->trans('Field default', 'Modules.Mzcproductfields.Admin'),
            'choices' => array_flip($options['font_choices']),
        ]);

        $builder->add('font_size', NumberType::class, [
            'label' => $this->trans('Font size (%)', 'Modules.Mzcproductfields.Admin'),
            'help' => $this->trans('As a percentage of the background image\'s width. Leave empty to keep using the field\'s own font size.', 'Modules.Mzcproductfields.Admin'),
            'required' => false,
            'scale' => 2,
        ]);

        $builder->add('pos_y', NumberType::class, [
            'label' => $this->trans('Vertical position (%)', 'Modules.Mzcproductfields.Admin'),
            'help' => $this->trans('As a percentage of the background image\'s height, from the top. Leave empty to keep using the field\'s own vertical position.', 'Modules.Mzcproductfields.Admin'),
            'required' => false,
            'scale' => 2,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attribute_value_choices' => [],
            'background_choices' => [],
            'font_choices' => [],
        ]);
    }
}
