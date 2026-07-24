<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use MzcProductFieldsField;

/**
 * Create/edit form for a single mzc_productfields_field row.
 *
 * "Applies to" (scope_type) drives which of `category_id` / `products_json`
 * is relevant — both are backed by a small custom autocomplete widget (see
 * views/js/admin-field-form.js), not PrestaShop core's EntitySearchInputType.
 *
 * That's a deliberate departure from the first version of this form: core's
 * `ProductSearchType` does not exist in PS 8.2.7 (confirmed — only PS 9.x has
 * it), and the client-side JS that hydrates EntitySearchInputType's markup
 * into a working widget is only loaded by core's own page-specific webpack
 * bundles (e.g. product.bundle.js) — a module's own Symfony page never gets
 * it. Both are real, observed failures, not theoretical risk. This form now
 * only exposes plain hidden fields; the search UI is entirely our own JS
 * talking to our own category-search endpoint and to core's stable
 * `admin_products_search_products_for_association` route (same route name in
 * both versions, resolved server-side — see FieldController).
 *
 * `products_json` has no selection limit, so "one product" is just the
 * one-item case — there's no separate enum value for "many products".
 */
class FieldType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('label', TranslatableType::class, [
                'label' => $this->trans('Label', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Shown to the customer on the product page.', 'Modules.Mzcproductfields.Admin'),
                'constraints' => [new DefaultLanguage()],
            ])
            ->add('placeholder', TranslatableType::class, [
                'label' => $this->trans('Placeholder', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
            ])
            ->add('field_type', ChoiceType::class, [
                'label' => $this->trans('Field type', 'Modules.Mzcproductfields.Admin'),
                'choices' => [
                    $this->trans('Text', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_TEXT,
                    $this->trans('Text area', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_TEXTAREA,
                    $this->trans('Date', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_DATE,
                    $this->trans('Checkbox', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_CHECKBOX,
                    $this->trans('Radio buttons', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_RADIO,
                    $this->trans('Select (dropdown)', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_SELECT,
                    $this->trans('Multi-select', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::TYPE_MULTISELECT,
                ],
                'attr' => ['class' => 'js-mzc-field-type'],
            ])
            ->add('scope_type', ChoiceType::class, [
                'label' => $this->trans('Applies to', 'Modules.Mzcproductfields.Admin'),
                'choices' => [
                    $this->trans('All products', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::SCOPE_ALL,
                    $this->trans('One category', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::SCOPE_CATEGORY,
                    $this->trans('Specific products', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::SCOPE_PRODUCTS,
                ],
                'attr' => ['class' => 'js-mzc-scope-type'],
            ])
            ->add('category_id', HiddenType::class, [
                'required' => false,
            ])
            ->add('products_json', HiddenType::class, [
                'required' => false,
            ])
            ->add('required', ChoiceType::class, [
                'label' => $this->trans('Required', 'Modules.Mzcproductfields.Admin'),
                'choices' => [
                    $this->trans('No', 'Modules.Mzcproductfields.Admin') => 0,
                    $this->trans('Yes', 'Modules.Mzcproductfields.Admin') => 1,
                ],
            ])
            ->add('is_paid', ChoiceType::class, [
                'label' => $this->trans('Paid field', 'Modules.Mzcproductfields.Admin'),
                'choices' => [
                    $this->trans('Free', 'Modules.Mzcproductfields.Admin') => 0,
                    $this->trans('Paid', 'Modules.Mzcproductfields.Admin') => 1,
                ],
            ])
            ->add('price_type', ChoiceType::class, [
                'label' => $this->trans('Price entered as', 'Modules.Mzcproductfields.Admin'),
                'choices' => [
                    $this->trans('Tax excluded (net)', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::PRICE_TYPE_NET,
                    $this->trans('Tax included (gross)', 'Modules.Mzcproductfields.Admin') => MzcProductFieldsField::PRICE_TYPE_GROSS,
                ],
            ])
            ->add('price_amount', NumberType::class, [
                'label' => $this->trans('Price amount', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Up to 6 decimal places, to avoid rounding drift when converting net/gross.', 'Modules.Mzcproductfields.Admin'),
                'scale' => 6,
                'html5' => true,
                // Not required at the form-type level: it's only meaningful
                // (and only read at all — see CartFieldValueWriter::resolveFreeTextValue())
                // when "Paid field" is Paid. Blanket-requiring it blocked
                // saving a FREE field with this left empty, which is the
                // normal, expected state for one — confirmed as a real bug.
                // The conditional check that price_amount must be a real
                // value when is_paid IS Paid is enforced below instead (see
                // the SUBMIT listener), not by this option.
                'required' => false,
                // Explicit, not left to NumberType's own default: newer Symfony
                // (PS 9.x) auto-fills step="any" for html5 number inputs, but
                // PS 8.2.7's older bundled Symfony does NOT — confirmed as a
                // real bug there, since HTML5's own default step is 1 when
                // unset, and (per spec) an unset `min` falls back to the
                // input's current VALUE as the step base. That combination
                // silently blocked saving any price_amount other than the
                // one already stored ±a whole number (e.g. editing 14.19
                // rejected 15.00000 with "nearest valid values are 14.19 and
                // 15.19"). Setting this ourselves works identically on both
                // versions.
                'attr' => ['step' => 'any'],
            ])
            ->add('id_tax_rules_group', ChoiceType::class, [
                'label' => $this->trans('Tax rule group', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Leave as "no tax" for a free field. Can differ from the parent product\'s own tax rule.', 'Modules.Mzcproductfields.Admin'),
                'choices' => array_flip($options['tax_rules_groups_choices']),
                'required' => false,
            ])
            ->add('max_length', IntegerType::class, [
                'label' => $this->trans('Maximum length', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
            ])
            ->add('char_counter', SwitchType::class, [
                'label' => $this->trans('Show character counter', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Shows a "typed/max" counter under the field on the product page — only has an effect when a maximum length is set above.', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
            ])
            ->add('position', IntegerType::class, [
                'label' => $this->trans('Display position', 'Modules.Mzcproductfields.Admin'),
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Active', 'Modules.Mzcproductfields.Admin'),
            ])
            ->add('preview_pos_x', NumberType::class, [
                'label' => $this->trans('Preview position X (%)', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'scale' => 2,
            ])
            ->add('preview_pos_y', NumberType::class, [
                'label' => $this->trans('Preview position Y (%)', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'scale' => 2,
            ])
            ->add('preview_font', ChoiceType::class, [
                'label' => $this->trans('Preview font', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Upload custom fonts on the "Manage fonts" screen (linked below).', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'placeholder' => empty($options['font_choices'])
                    ? $this->trans('No custom fonts uploaded yet', 'Modules.Mzcproductfields.Admin')
                    : $this->trans('Browser default', 'Modules.Mzcproductfields.Admin'),
                'choices' => array_flip($options['font_choices']),
            ])
            ->add('preview_font_size', NumberType::class, [
                'label' => $this->trans('Preview font size (%)', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('As a percentage of the preview image\'s width, so it scales correctly at any size. Default 6.', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'scale' => 2,
            ])
            ->add('preview_max_width', NumberType::class, [
                'label' => $this->trans('Preview max width (%)', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('Wraps the preview text sooner than the full available width, as a percentage of the preview image\'s width. Leave empty to use the full width.', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'scale' => 2,
            ])
            ->add('is_front_field', SwitchType::class, [
                'label' => $this->trans('Front field', 'Modules.Mzcproductfields.Admin'),
                'help' => $this->trans('On: this field always overlays the product\'s own photo — the right choice for personalization printed on the front of the item. Off: renders on a background image instead, chosen entirely by a matching combination rule (configure those on the "Background per combination / attribute value" screen) — use this for a field meant for the back/reverse (e.g. engraving on the back of a frame). A product with both a front and a back field must have the back one set to Off, or both would render on the same background the moment a rule matches.', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                'attr' => ['class' => 'js-mzc-front-field'],
            ])
            ->add('preview_color', ColorType::class, [
                'label' => $this->trans('Preview text color', 'Modules.Mzcproductfields.Admin'),
                'required' => false,
                // Fixed width/height via inline style (wins over any external
                // stylesheet, incl. Bootstrap's `.form-control { width: 100% }`):
                // PS 8's admin theme has no rules at all for `input[type="color"]`
                // (that input type didn't exist as a Symfony form type until PS 9),
                // so without this override the browser's native color swatch gets
                // stretched into a thin full-width line instead of a compact box.
                'attr' => ['style' => 'width: 70px; height: 38px; padding: 4px; cursor: pointer;'],
            ])
        ;

        // price_amount is deliberately not 'required' above (see its own
        // comment) so a FREE field can be saved with it left blank. This is
        // the cross-field check that replaces that blanket requirement: a
        // PAID field must still have a real, non-zero price — otherwise
        // "Paid" would silently behave exactly like "Free" (CartFieldValueWriter
        // only ever charges is_paid fields, so an empty/zero amount there is
        // never a valid, intentional state).
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if ((int) ($data['is_paid'] ?? 0) !== 1) {
                return;
            }

            $priceAmount = $data['price_amount'] ?? null;
            if ($priceAmount === null || $priceAmount === '' || (float) $priceAmount === 0.0) {
                $event->getForm()->get('price_amount')->addError(new \Symfony\Component\Form\FormError(
                    $this->trans('Price amount is required for a paid field.', 'Modules.Mzcproductfields.Admin')
                ));
            }
        });
    }

    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'tax_rules_groups_choices' => [],
            'font_choices' => [],
        ]);
    }
}
