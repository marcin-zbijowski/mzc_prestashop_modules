/**
 * MZC Carrier Attribute — Admin rule builder
 *
 * Renders compound-rule panels dynamically.  Each rule shows every attribute
 * group as a checkbox section.  Checked values within a group are OR'd;
 * groups with at least one check are AND'd together.  Unchecked groups
 * impose no constraint.
 *
 * Data is read from a JSON block injected by the Smarty template.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
(function () {
  'use strict';

  function init() {

    /* ── bootstrap ──────────────────────────────────────────────────── */

    var dataEl = document.getElementById('mzc-data');
    if (!dataEl) {
      return;
    }

    var data = JSON.parse(dataEl.textContent);
    var t = window.mzcTranslations || {};

    var container = document.getElementById('mzc-rules-container');
    var nextIndex = 0;

    /* ── helpers ─────────────────────────────────────────────────────── */

    function escapeHtml(str) {
      var el = document.createElement('span');
      el.appendChild(document.createTextNode(str));
      return el.innerHTML;
    }

    /* ── rule panel ─────────────────────────────────────────────────── */

    function createRulePanel(ruleData) {
      var idx = nextIndex++;
      var panel = document.createElement('div');
      panel.className = 'mzc-rule panel panel-default';

      /* heading */
      var heading = document.createElement('div');
      heading.className = 'panel-heading mzc-rule-heading';

      var title = document.createElement('strong');
      title.textContent = (t.rule || 'Rule') + ' #' + (idx + 1);

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-danger btn-xs pull-right';
      removeBtn.innerHTML = '<i class="icon-trash"></i> ' + escapeHtml(t.remove || 'Remove');
      removeBtn.addEventListener('click', function () { panel.remove(); });

      heading.appendChild(title);
      heading.appendChild(removeBtn);
      panel.appendChild(heading);

      /* body */
      var body = document.createElement('div');
      body.className = 'panel-body';

      /* — conditions: one checkbox section per attribute group — */
      var condWrapper = document.createElement('div');
      condWrapper.className = 'mzc-section';

      var condTitle = document.createElement('h5');
      condTitle.textContent = t.conditions || 'Conditions (select values per attribute group):';
      condWrapper.appendChild(condTitle);

      var condHint = document.createElement('p');
      condHint.className = 'mzc-hint text-muted';
      condHint.textContent = t.conditionsHint || 'Within a group: any checked value matches (OR). Between groups: all checked groups must match (AND). Unchecked group = no constraint.';
      condWrapper.appendChild(condHint);

      /* checked conditions: { "groupId": [attrId, ...] } */
      var checkedMap = {};
      if (ruleData && ruleData.conditions) {
        checkedMap = ruleData.conditions;
      }

      data.attributeGroups.forEach(function (group) {
        var section = document.createElement('fieldset');
        section.className = 'mzc-group-section';

        var legend = document.createElement('legend');
        legend.className = 'mzc-group-title';
        legend.textContent = group.name;
        section.appendChild(legend);

        var grid = document.createElement('div');
        grid.className = 'mzc-attr-grid';

        var groupChecked = checkedMap[String(group.id)] || [];

        group.values.forEach(function (val) {
          var isChecked = groupChecked.indexOf(val.id) !== -1;

          var label = document.createElement('label');
          label.className = 'mzc-attr-checkbox';

          var cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.name = 'rule[' + idx + '][conditions][' + group.id + '][]';
          cb.value = val.id;
          if (isChecked) {
            cb.checked = true;
            label.classList.add('is-checked');
          }
          cb.addEventListener('change', function () {
            label.classList.toggle('is-checked', this.checked);
          });

          label.appendChild(cb);
          label.appendChild(document.createTextNode(' ' + val.name));
          grid.appendChild(label);
        });

        section.appendChild(grid);
        condWrapper.appendChild(section);
      });

      body.appendChild(condWrapper);

      /* — carriers section — */
      var carrierSection = document.createElement('div');
      carrierSection.className = 'mzc-section';

      var carrierTitle = document.createElement('h5');
      carrierTitle.textContent = t.allowedCarriers || 'Allowed Carriers:';
      carrierSection.appendChild(carrierTitle);

      var carrierChecks = document.createElement('div');
      carrierChecks.className = 'mzc-carrier-checks';

      data.carrierList.forEach(function (c) {
        var isChecked = ruleData && ruleData.carriers &&
          ruleData.carriers.indexOf(c.id_reference) !== -1;

        var label = document.createElement('label');
        label.className = 'mzc-carrier-checkbox';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'rule[' + idx + '][carriers][]';
        cb.value = c.id_reference;
        if (isChecked) {
          cb.checked = true;
          label.classList.add('is-checked');
        }
        cb.addEventListener('change', function () {
          label.classList.toggle('is-checked', this.checked);
        });

        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + c.label));
        carrierChecks.appendChild(label);
      });

      carrierSection.appendChild(carrierChecks);
      body.appendChild(carrierSection);

      panel.appendChild(body);
      container.appendChild(panel);
    }

    /* ── initialisation ─────────────────────────────────────────────── */

    if (data.existingRules && data.existingRules.length > 0) {
      data.existingRules.forEach(function (rule) {
        createRulePanel(rule);
      });
    }

    document.getElementById('mzc-add-rule').addEventListener('click', function () {
      createRulePanel(null);
    });

  } /* end init */

  if (document.readyState !== 'loading') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
