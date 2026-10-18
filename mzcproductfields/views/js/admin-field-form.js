/**
 * MZC Product Fields — admin field form.
 *
 * Three small, self-contained pieces:
 *
 * 1. Shows only the scope row relevant to the selected "Applies to" value:
 *    SCOPE_ALL (0) shows neither, SCOPE_CATEGORY (1) shows .js-mzc-scope-category,
 *    SCOPE_PRODUCTS (2) shows .js-mzc-scope-products.
 *
 * 2. A minimal search-and-select autocomplete for both rows, writing into the
 *    hidden `field[category_id]` / `field[products_json]` inputs that
 *    FieldRepository reads on submit.
 *
 * 3. The same product picker on the field list screen, for the products shown in the
 *    home page's live-personalisation section (.js-mzc-showcase-products): ordered,
 *    capped at data-mzc-max, and a product the home page would skip (an entry with a
 *    `warning`) is flagged rather than refused.
 *
 * This does NOT use PrestaShop core's EntitySearchInputType/ProductSearchType:
 * `ProductSearchType` doesn't exist as a class in PS 8.2.7 (confirmed), and
 * EntitySearchInputType's client-side hydration is only wired up by core's own
 * page-specific webpack bundles (e.g. product.bundle.js), which this module's
 * Symfony page never loads — the widget markup would render but stay inert.
 * Both are real, observed failures on this exact form, not theoretical risk.
 */
(function () {
  var SCOPE_CATEGORY = '1';
  var SCOPE_PRODUCTS = '2';
  var MIN_QUERY_LENGTH = 2;
  var DEBOUNCE_MS = 250;

  function toggleScopeRows() {
    var scopeSelect = document.querySelector('.js-mzc-scope-type');
    if (!scopeSelect) {
      return;
    }

    var categoryRow = document.querySelector('.js-mzc-scope-category');
    var productsRow = document.querySelector('.js-mzc-scope-products');
    var value = scopeSelect.value;

    if (categoryRow) {
      categoryRow.style.display = value === SCOPE_CATEGORY ? '' : 'none';
    }
    if (productsRow) {
      productsRow.style.display = value === SCOPE_PRODUCTS ? '' : 'none';
    }
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var args = arguments;
      var self = this;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(self, args);
      }, delay);
    };
  }

  /**
   * @param {Element} row .js-mzc-scope-category or .js-mzc-scope-products
   * @param {boolean} multiple
   * @param {Element} hiddenInput the field[category_id] or field[products_json] input
   */
  function initPicker(row, multiple, hiddenInput) {
    var searchInput = row.querySelector('.js-mzc-search-input');
    var suggestions = row.querySelector('.js-mzc-suggestions');
    var selectedContainer = row.querySelector('.js-mzc-selected');
    var searchUrl = row.getAttribute('data-mzc-search-url');
    // 0 = unlimited. Only the home page section sets a limit.
    var max = parseInt(row.getAttribute('data-mzc-max') || '0', 10) || 0;
    var counter = row.querySelector('.js-mzc-count');

    var initial = [];
    try {
      initial = JSON.parse(selectedContainer.getAttribute('data-initial') || '[]');
    } catch (e) {
      initial = [];
    }
    var selected = Array.isArray(initial) ? initial.filter(Boolean) : [];

    function writeHiddenValue() {
      if (multiple) {
        hiddenInput.value = JSON.stringify(selected.map(function (item) { return item.id; }));
      } else {
        hiddenInput.value = selected.length ? String(selected[0].id) : '';
      }
    }

    function atLimit() {
      return max > 0 && selected.length >= max;
    }

    function renderSelected() {
      selectedContainer.innerHTML = '';
      if (counter) {
        counter.textContent = max > 0 ? selected.length + ' / ' + max + '.' : '';
      }
      searchInput.disabled = atLimit();
      selected.forEach(function (item) {
        var chip = document.createElement('span');
        // A warning (the product exists but would not be shown) is amber and says why.
        chip.className = item.warning ? 'badge badge-warning' : 'badge badge-info';
        if (item.warning) {
          chip.title = item.warning;
        }
        chip.style.marginRight = '6px';
        chip.style.display = 'inline-block';
        chip.style.padding = '4px 8px';
        chip.textContent = (item.warning ? '\u26A0 ' : '') + item.name + ' ';

        var remove = document.createElement('a');
        remove.href = '#';
        remove.textContent = '×';
        remove.style.color = 'inherit';
        remove.style.marginLeft = '4px';
        remove.addEventListener('click', function (e) {
          e.preventDefault();
          selected = selected.filter(function (s) { return s.id !== item.id; });
          writeHiddenValue();
          renderSelected();
        });

        chip.appendChild(remove);
        selectedContainer.appendChild(chip);
      });
    }

    function hideSuggestions() {
      suggestions.style.display = 'none';
      suggestions.innerHTML = '';
    }

    function selectItem(item) {
      if (multiple) {
        if (!selected.some(function (s) { return s.id === item.id; }) && !atLimit()) {
          selected.push(item);
        }
      } else {
        selected = [item];
      }
      writeHiddenValue();
      renderSelected();
      searchInput.value = '';
      hideSuggestions();
    }

    function renderSuggestions(items) {
      suggestions.innerHTML = '';
      if (!items.length) {
        hideSuggestions();
        return;
      }
      items.forEach(function (item) {
        var li = document.createElement('li');
        li.className = 'list-group-item';
        li.style.cursor = 'pointer';
        li.textContent = item.name;
        li.addEventListener('mousedown', function (e) {
          // mousedown (not click) so it fires before the input's blur event
          e.preventDefault();
          selectItem(item);
        });
        suggestions.appendChild(li);
      });
      suggestions.style.display = '';
    }

    var runSearch = debounce(function () {
      var query = searchInput.value.trim();
      if (query.length < MIN_QUERY_LENGTH) {
        hideSuggestions();
        return;
      }

      var url = searchUrl.replace('__QUERY__', encodeURIComponent(query));
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (data) {
          renderSuggestions(Array.isArray(data) ? data : []);
        })
        .catch(function () {
          hideSuggestions();
        });
    }, DEBOUNCE_MS);

    searchInput.addEventListener('input', runSearch);
    searchInput.addEventListener('blur', function () {
      // Delay so a suggestion's mousedown can still register first.
      setTimeout(hideSuggestions, 150);
    });
    // Prevent Enter in the search box from submitting the whole form — with
    // no suggestion highlighted/selected there is nothing useful to submit,
    // and the underlying required fields (e.g. Label) are very likely still
    // empty at that point.
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
      }
    });

    writeHiddenValue();
    renderSelected();
  }

  function init() {
    var scopeSelect = document.querySelector('.js-mzc-scope-type');
    if (scopeSelect) {
      toggleScopeRows();
      scopeSelect.addEventListener('change', toggleScopeRows);
    }

    var categoryRow = document.querySelector('.js-mzc-scope-category');
    var categoryHidden = document.getElementById('field_category_id');
    if (categoryRow && categoryHidden) {
      initPicker(categoryRow, false, categoryHidden);
    }

    var productsRow = document.querySelector('.js-mzc-scope-products');
    var productsHidden = document.getElementById('field_products_json');
    if (productsRow && productsHidden) {
      initPicker(productsRow, true, productsHidden);
    }

    var showcaseRow = document.querySelector('.js-mzc-showcase-products');
    var showcaseHidden = document.getElementById('mzc_showcase_products');
    if (showcaseRow && showcaseHidden) {
      initPicker(showcaseRow, true, showcaseHidden);
    }
  }

  // This script is loaded at the end of <body> (PrestaShop admin's
  // `javascripts` block), so the DOM is already parsed by the time it runs —
  // `DOMContentLoaded` has typically already fired and would never call back.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
