<?php

declare(strict_types=1);

namespace Mzc\Brevo\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The "Brevo" tab on the product page (see Mzcbrevo::hookActionProductFormBuilderModifier()).
 *
 * A plain AbstractType rather than PrestaShop's TranslatorAwareType: the module passes
 * the translated labels in as options, so the type needs no service definition.
 */
class ProductOccasionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('occasion', ChoiceType::class, [
            'label' => $options['occasion_label'],
            'help' => $options['occasion_help'],
            'choices' => $options['occasion_choices'],
            'required' => false,
            'placeholder' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Brevo',
            'required' => false,
            'occasion_choices' => [],
            'occasion_label' => 'Occasion',
            'occasion_help' => '',
        ]);
    }
}
