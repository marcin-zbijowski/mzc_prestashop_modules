/**
 * MZC Landing Newsletter – Front-office form handler
 *
 * Reads translated strings from window.mzcLandingConfig (set inline in the template).
 * Handles AJAX subscription, GDPR consent validation, and user feedback.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var config = window.mzcLandingConfig || {};
    var strings = config.strings || {};

    var form = document.getElementById('mzc-newsletter-form');
    var feedback = document.getElementById('mzc-form-feedback');
    var btn = document.getElementById('mzc-submit-btn');
    var emailEl = document.getElementById('mzc-email');

    if (!form || !btn || !emailEl) {
      return;
    }

    var labelSubscribe = strings.subscribe || 'Subscribe';
    var labelSubscribing = strings.subscribing || 'Subscribing...';
    var labelGdpr = strings.gdprError || 'Please accept the privacy policy to continue.';
    var labelGenericErr = strings.genericError || 'An error occurred. Please try again.';

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      // Validate GDPR consent checkbox (if present)
      var gdprCb = form.querySelector('input[name="psgdpr_consent_checkbox"]');
      if (gdprCb && !gdprCb.checked) {
        showFeedback(false, labelGdpr);
        return;
      }

      var email = emailEl.value.trim();
      if (!email) {
        return;
      }

      btn.disabled = true;
      btn.textContent = labelSubscribing;

      var params = new URLSearchParams();
      params.append('email', email);
      params.append('token', form.querySelector('input[name="token"]').value);
      if (gdprCb) {
        params.append('psgdpr_consent_checkbox', gdprCb.checked ? '1' : '0');
      }

      fetch(form.action, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: params.toString(),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (resp) {
          btn.disabled = false;
          btn.textContent = labelSubscribe;
          showFeedback(resp.success, resp.message);
          if (resp.success) {
            emailEl.value = '';
            form.style.display = 'none';
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = labelSubscribe;
          showFeedback(false, labelGenericErr);
        });
    });

    function showFeedback(success, message) {
      feedback.style.display = 'block';
      feedback.className = 'mzc-form-feedback ' + (success ? 'mzc-success' : 'mzc-error');
      feedback.textContent = message;
    }
  });
})();
