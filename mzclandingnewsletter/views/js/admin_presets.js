/**
 * MZC Landing Newsletter – Back-office CSS preset loader
 *
 * Reads preset CSS data from a script type application/json id mzc-presets-data block.
 * On click, loads the selected preset CSS into the Custom CSS textarea.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var dataEl = document.getElementById('mzc-presets-data');
    if (!dataEl) {
      return;
    }

    var presets;
    try {
      presets = JSON.parse(dataEl.dataset.presets);
    } catch (e) {
      return;
    }

    document.querySelectorAll('.mzc-preset-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var presetKey = this.getAttribute('data-preset');
        var css = presets[presetKey];
        if (!css) {
          return;
        }

        var textarea = document.querySelector('textarea[name="MZC_LANDING_CSS"]');
        if (textarea) {
          if (textarea.value.trim() !== '' && !confirm('This will replace your current Custom CSS. Continue?')) {
            return;
          }
          textarea.value = css;
          textarea.style.height = 'auto';
          textarea.style.height = textarea.scrollHeight + 'px';
          textarea.focus();
          textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });
  });
})();
