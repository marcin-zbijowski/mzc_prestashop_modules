<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Renders a native <input type="color"> — no JS, no CSS, works identically
 * everywhere.
 *
 * Not Symfony's own `Symfony\Component\Form\Extension\Core\Type\ColorType`:
 * that class doesn't exist in the Symfony Form component bundled with PS
 * 8.2.7 (confirmed — it was added in Symfony 6.3, PS 9.x only). Not core's
 * `PrestaShopBundle\Form\Admin\Type\ColorPickerType` either: its ".colorpicker"
 * class is only hydrated into a working swatch picker by core's own
 * page-specific webpack bundles (e.g. order_states_form.bundle.js) — the
 * exact same failure mode already hit with the product/category autocomplete.
 *
 * The `color_widget` Twig block this reuses via `getBlockPrefix()` is part of
 * Symfony's own foundational form theme (form_div_layout.html.twig, the base
 * every Symfony form theme — including PrestaShop's bootstrap_4_layout —
 * extends), confirmed present in both PS versions regardless of the
 * package-layout difference (symfony/twig-bridge vs the older monolithic
 * symfony/symfony). It just sets the `type` view var to "color" before
 * delegating to the plain text input renderer — no separate PHP class needed
 * for that to work.
 */
class ColorType extends TextType
{
    public function getBlockPrefix(): string
    {
        return 'color';
    }
}
