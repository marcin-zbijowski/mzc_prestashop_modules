/**
 * MZC Product Fields — content-confirmation step (plan section 5).
 *
 * Unlike Anvanto (which never intercepts the add-to-cart submit at all), this
 * intercepts the "Add to cart" button exactly once: if the customer has
 * filled in any personalization field, the click is held, the values are
 * validated and staged into the cart via our own AJAX endpoint
 * (savecustomization — see that controller's docblock for why this is a
 * plain legacy front controller, not a Symfony route), and a summary modal
 * shows exactly what will be personalized (and the price impact) before the
 * customer confirms and the real add-to-cart proceeds.
 *
 * This intercepts the button's CLICK, not the form's submit event: confirmed
 * empirically (a real add-to-cart went through with zero interception on
 * first implementation) that core's own theme JS (core.js, identical on
 * every theme in scope) binds its OWN click handler on
 * '[data-button-action="add-to-cart"]' that calls preventDefault() and does
 * its own AJAX POST directly — the native HTML form is never actually
 * "submitted" (no submit event ever fires) for the classic AJAX add-to-cart
 * flow. Our handler is registered on `document` in the CAPTURE phase
 * specifically so it runs and can stopPropagation() BEFORE that bubble-phase
 * jQuery handler ever sees the event. Once confirmed, the SAME button is
 * re-clicked (with a one-shot marker so this handler lets it through
 * untouched the second time), which lets core's own AJAX flow proceed
 * exactly as if the customer had clicked it with nothing to confirm.
 *
 * Delegated on `document` rather than bound directly to the button: the
 * form's containing region gets replaced wholesale when the customer changes
 * Size/Color (see front-product-fields.js's docblock for why), and
 * delegation is the only way to survive that without re-binding.
 */
(function () {
  'use strict';

  var FORM_ID = 'add-to-cart-or-refresh';
  var BUTTON_SELECTOR = '[data-button-action="add-to-cart"]';
  var CONFIRMED_ATTR = 'data-mzc-confirmed';

  var DEFAULT_I18N = {
    confirmTitle: 'Please confirm your personalization',
    edit: 'Edit',
    confirm: 'Confirm and add to cart',
    priceNote: 'Personalization adds %price% to your order.',
    genericError: 'Something went wrong — please try again.',
  };

  function getI18n(container) {
    var raw = container.getAttribute('data-mzc-i18n');
    if (!raw) {
      return DEFAULT_I18N;
    }

    try {
      var parsed = JSON.parse(raw);

      return Object.assign({}, DEFAULT_I18N, parsed);
    } catch (e) {
      return DEFAULT_I18N;
    }
  }

  function selectedOptionLabels(select) {
    var labels = [];
    for (var i = 0; i < select.options.length; i++) {
      if (select.options[i].selected && select.options[i].value !== '') {
        labels.push(select.options[i].textContent.replace(/\s*\(\+.*\)\s*$/, '').trim());
      }
    }

    return labels.join(', ');
  }

  function currentValue(input) {
    if (input.tagName === 'SELECT') {
      return selectedOptionLabels(input);
    }

    if (input.type === 'radio' || input.type === 'checkbox') {
      var group = document.querySelectorAll('input[name="' + CSS.escape(input.name) + '"]:checked');
      var labels = [];
      for (var i = 0; i < group.length; i++) {
        var label = group[i].closest('.mzc-field__option');
        labels.push(label ? label.textContent.replace(/\s*\(\+.*\)\s*$/, '').trim() : group[i].value);
      }

      return labels.join(', ');
    }

    return input.value;
  }

  function fieldLabel(wrapper) {
    var label = wrapper.querySelector('.mzc-field__label');
    if (!label) {
      return '';
    }

    var clone = label.cloneNode(true);
    clone.querySelectorAll('.mzc-field__required, .mzc-field__price').forEach(function (el) {
      el.remove();
    });

    return clone.textContent.trim();
  }

  /**
   * @return {Array} [{label, value}] for every field the customer actually filled in
   */
  function collectFilledFields(container) {
    var filled = [];

    container.querySelectorAll('.mzc-field[data-mzc-field-id]').forEach(function (wrapper) {
      var fieldId = wrapper.getAttribute('data-mzc-field-id');
      var input = container.querySelector('[data-mzc-preview-source="' + CSS.escape(fieldId) + '"]');
      if (!input) {
        return;
      }

      var value = currentValue(input);
      if (value) {
        filled.push({ label: fieldLabel(wrapper), value: value });
      }
    });

    return filled;
  }

  function buildModal(i18n, filledFields, totalSurchargeFormatted, onConfirm, onCancel) {
    var overlay = document.createElement('div');
    overlay.className = 'mzc-confirm-overlay';

    var modal = document.createElement('div');
    modal.className = 'mzc-confirm-modal';

    var title = document.createElement('h3');
    title.className = 'mzc-confirm-modal__title';
    title.textContent = i18n.confirmTitle;
    modal.appendChild(title);

    var list = document.createElement('dl');
    list.className = 'mzc-confirm-modal__list';
    filledFields.forEach(function (field) {
      var dt = document.createElement('dt');
      dt.textContent = field.label;
      var dd = document.createElement('dd');
      dd.textContent = field.value;
      list.appendChild(dt);
      list.appendChild(dd);
    });
    modal.appendChild(list);

    if (totalSurchargeFormatted) {
      var price = document.createElement('p');
      price.className = 'mzc-confirm-modal__price';
      price.textContent = i18n.priceNote.replace('%price%', totalSurchargeFormatted);
      modal.appendChild(price);
    }

    var actions = document.createElement('div');
    actions.className = 'mzc-confirm-modal__actions';

    var editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.className = 'mzc-confirm-modal__edit';
    editButton.textContent = i18n.edit;
    editButton.addEventListener('click', function () {
      overlay.remove();
      onCancel();
    });

    var confirmButton = document.createElement('button');
    confirmButton.type = 'button';
    confirmButton.className = 'mzc-confirm-modal__confirm';
    confirmButton.textContent = i18n.confirm;
    confirmButton.addEventListener('click', function () {
      overlay.remove();
      onConfirm();
    });

    actions.appendChild(editButton);
    actions.appendChild(confirmButton);
    modal.appendChild(actions);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
  }

  function showErrors(container, errors) {
    var existing = container.querySelector('.mzc-confirm-errors');
    if (existing) {
      existing.remove();
    }

    var box = document.createElement('div');
    box.className = 'mzc-confirm-errors';
    errors.forEach(function (message) {
      var p = document.createElement('p');
      p.textContent = message;
      box.appendChild(p);
    });

    container.insertBefore(box, container.firstChild);
    box.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  function setIdCustomization(form, idCustomization) {
    var hidden = form.querySelector('input[name="id_customization"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'id_customization';
      form.appendChild(hidden);
    }
    hidden.value = idCustomization;
  }

  document.addEventListener(
    'click',
    function (e) {
      var button = e.target.closest ? e.target.closest(BUTTON_SELECTOR) : null;
      if (!button) {
        return;
      }

      if (button.hasAttribute(CONFIRMED_ATTR)) {
        // Our own re-dispatched click after the customer confirmed — let it
        // through untouched this time, so core's own AJAX add-to-cart runs.
        button.removeAttribute(CONFIRMED_ATTR);

        return;
      }

      var form = button.form;
      if (!form || form.id !== FORM_ID) {
        return;
      }

      var container = document.querySelector('.mzc-product-fields');
      if (!container) {
        return;
      }

      var i18n = getI18n(container);
      var filledFields = collectFilledFields(container);
      var hasRequiredField = !!container.querySelector('.mzc-field__required');
      if (!filledFields.length && !hasRequiredField) {
        // Nothing personalized and nothing required — nothing to confirm or
        // validate, let the click through natively. (If a required field
        // exists we must still go through the AJAX call below even when
        // empty, so CartFieldValueWriter's required-field check actually
        // runs — otherwise a required-but-empty field would silently let
        // the native add-to-cart through unvalidated.)
        return;
      }

      e.preventDefault();
      e.stopPropagation();

      var saveUrl = container.getAttribute('data-mzc-save-url');
      var idProduct = container.getAttribute('data-mzc-product-id');
      var formData = new FormData(form);
      formData.set('id_product', idProduct);

      fetch(saveUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.success) {
            showErrors(container, data.errors && data.errors.length ? data.errors : [i18n.genericError]);

            return;
          }

          if (!filledFields.length) {
            // Only reached if a required field exists but validated fine
            // with nothing filled in (shouldn't normally happen) — nothing
            // to summarize, so just proceed.
            setIdCustomization(form, data.id_customization);
            button.setAttribute(CONFIRMED_ATTR, '1');
            button.click();

            return;
          }

          buildModal(
            i18n,
            filledFields,
            data.total_surcharge_formatted,
            function onConfirm() {
              setIdCustomization(form, data.id_customization);
              // Best-effort, fire-and-forget — see front-snapshot.js's
              // docblock. Never awaited: the add-to-cart click right below
              // must never be delayed or blocked by this.
              if (window.MzcProductFieldsSnapshot) {
                window.MzcProductFieldsSnapshot.captureAndUpload(container, data.id_customization);
              }
              button.setAttribute(CONFIRMED_ATTR, '1');
              button.click();
            },
            function onCancel() {}
          );
        })
        .catch(function () {
          showErrors(container, [i18n.genericError]);
        });
    },
    true
  );
})();
