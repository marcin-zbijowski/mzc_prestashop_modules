/**
 * MZC GA4 checkout step tracker.
 *
 * Loaded only on checkout pages (injected by hookDisplayBeforeBodyClosingTag).
 *
 * Events handled:
 *   add_shipping_info — fires when delivery step is active AND carrier selection changes
 *   add_payment_info  — fires when payment step is active AND payment method changes
 *
 * `begin_checkout` is emitted server-side (PHP) on page load — not here.
 *
 * Design: track the LAST SENT VALUE for each event type. A new push fires only
 * when the value actually changes. This replaces the old boolean guards
 * (sentShipping / sentPayment) that prevented re-firing when the user changed
 * their delivery/payment selection within the same step.
 *
 * Triggers:
 *   1. prestashop.on('updatedCheckout') — step change via AJAX
 *   2. document change events on delivery and payment radio buttons
 *      (covers same-step selection changes that don't trigger updatedCheckout)
 *   3. DOMContentLoaded — initial state on page load / reload on a specific step
 */
(function () {
    'use strict';

    window.dataLayer = window.dataLayer || [];

    // Track the last pushed values to avoid duplicate events
    var lastShippingTier = null;
    var lastPaymentType  = null;

    // -------------------------------------------------------------------------
    // Step / element detection
    // -------------------------------------------------------------------------

    function getCurrentStepId() {
        var el = document.querySelector('.checkout-step.-current');
        return el ? (el.getAttribute('id') || '') : '';
    }

    /**
     * Returns the currently selected carrier name.
     * Tries multiple selectors to handle different PS8 theme structures.
     */
    function getCarrierName() {
        // Checked radio button for delivery_option (name starts with "delivery_option")
        var checked = document.querySelector('input[name^="delivery_option"]:checked');
        if (!checked) { return ''; }

        // Try .carrier-name inside the associated label or parent
        var parent = checked.closest('.delivery-option') || checked.parentElement;
        if (parent) {
            var nameEl = parent.querySelector('.carrier-name, .carrier-delay, h6');
            if (nameEl) { return nameEl.textContent.trim(); }
        }

        // Try label[for=id]
        var label = document.querySelector('label[for="' + checked.id + '"]');
        if (label) {
            var nameEl2 = label.querySelector('.carrier-name, h6');
            if (nameEl2) { return nameEl2.textContent.trim(); }
            // Use full label text as last resort
            return label.textContent.trim().split('\n')[0].trim();
        }

        return '';
    }

    /**
     * Returns the currently selected payment method name.
     */
    function getPaymentName() {
        var checked = document.querySelector('input[name="payment-option"]:checked');
        if (!checked) { return ''; }

        // Try label[for=id]
        var label = document.querySelector('label[for="' + checked.id + '"]');
        if (label) {
            var nameEl = label.querySelector('.payment-name, span');
            if (nameEl) { return nameEl.textContent.trim(); }
            return label.textContent.trim().split('\n')[0].trim();
        }

        // Try data-module-name attribute as fallback
        return checked.getAttribute('data-module-name') || '';
    }

    // -------------------------------------------------------------------------
    // Event pushers
    // -------------------------------------------------------------------------

    function pushShippingInfo() {
        var tier = getCarrierName();
        if (!tier || tier === lastShippingTier) { return; }
        lastShippingTier = tier;

        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({
            event:     'add_shipping_info',
            ecommerce: {
                currency:      window.mzc_ga4_currency  || '',
                value:         window.mzc_ga4_cart_value || 0,
                shipping_tier: tier,
                items:         getCheckoutItems()
            }
        });
    }

    function pushPaymentInfo() {
        var type = getPaymentName();
        if (!type || type === lastPaymentType) { return; }
        lastPaymentType = type;

        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({
            event:     'add_payment_info',
            ecommerce: {
                currency:     window.mzc_ga4_currency  || '',
                value:        window.mzc_ga4_cart_value || 0,
                payment_type: type,
                items:        getCheckoutItems()
            }
        });
    }

    /**
     * Reads items from the last begin_checkout push in dataLayer.
     * Avoids duplicating the PHP→JS data bridge.
     */
    function getCheckoutItems() {
        var dl = window.dataLayer;
        for (var i = dl.length - 1; i >= 0; i--) {
            if (dl[i].event === 'begin_checkout' && Array.isArray(dl[i].items)) {
                return dl[i].items;
            }
        }
        return [];
    }

    // -------------------------------------------------------------------------
    // Step change handler (called on both initial load and updatedCheckout)
    // -------------------------------------------------------------------------

    function handleCurrentStep() {
        var stepId = getCurrentStepId();
        if (stepId === 'checkout-delivery-step') { pushShippingInfo(); }
        if (stepId === 'checkout-payment-step')  { pushPaymentInfo(); }
    }

    // -------------------------------------------------------------------------
    // Listeners
    // -------------------------------------------------------------------------

    // 1. Initial page load (user may land directly on a non-first step, or refresh)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', handleCurrentStep);
    } else {
        handleCurrentStep();
    }

    // 2. PS8 checkout AJAX step changes
    if (window.prestashop) {
        prestashop.on('updatedCheckout', function () {
            setTimeout(handleCurrentStep, 50);
        });
    }

    // 3. Delivery option radio buttons changed within the same step
    //    (does NOT trigger updatedCheckout — only happens inside the step)
    document.addEventListener('change', function (e) {
        var target = e.target;
        if (!target) { return; }

        // Delivery carrier selection
        if (target.name && target.name.indexOf('delivery_option') === 0) {
            setTimeout(pushShippingInfo, 50);
            return;
        }

        // Payment method selection
        if (target.name === 'payment-option') {
            setTimeout(pushPaymentInfo, 50);
        }
    });

}());
