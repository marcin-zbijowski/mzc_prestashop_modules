/**
 * MZC Product Fields — live preview.
 *
 * Where a field's preview text renders depends, in priority order, on:
 *
 * 1. "combination-driven" (at least one globally-configured preview-background
 *    rule applies to this product — see Service/CombinationBackgroundResolver):
 *    the currently selected combination/attribute values (read from the native
 *    product-variants selectors — matched via VARIANTS_SELECTOR, which has to
 *    cover more than one class since themes vary here, see its own comment)
 *    are matched against the
 *    rules embedded in data-mzc-combination-rules (most-specific match wins:
 *    a rule requiring more attribute ids beats one requiring fewer, e.g.
 *    "Canvas AND 30x40cm" beats "30x40cm" alone). ALL preview-enabled fields
 *    move into whichever .mzc-bg-preview box the winning rule points to, and
 *    every other box is hidden. If nothing matches (rare — native selects
 *    always have a default), no box is shown.
 *
 * 2. "static" (no rules configured for this product, the common case): a
 *    field with its own "Preview background image" set anchors to that
 *    dedicated box (percentage position against the box's own image, no
 *    viewport tracking needed since it's a normal, static part of the page).
 *
 * 3. "floating" (no background at all): the preview text is positioned in
 *    viewport pixel coordinates computed from the theme's own product image
 *    via getBoundingClientRect(), since that image lives in completely
 *    different markup per theme (classic/hummingbird's Bootstrap carousel
 *    vs. artedeum-pl's "leon" theme's custom draggable slider) that we never
 *    insert into directly.
 *
 * All three resolve preview_pos_x/y (0-100%) and preview_font_size (also a
 * percentage, of the relevant image's rendered width) against that image's
 * CURRENT rendered size, so they scale correctly at any viewport.
 * preview_pos_x is the HORIZONTAL CENTER of the text (it grows left/right
 * evenly from that point); preview_pos_y is the TOP edge where the text
 * starts (it grows downward from that point), not its vertical center — see
 * .mzc-preview-text's `transform` in the CSS.
 *
 * A .mzc-bg-preview box (modes 1 and 2) only ever shows once at least one of
 * the fields feeding it actually has non-empty text — see
 * updateBoxVisibility() — rather than always displaying an otherwise-blank
 * mockup photo before the customer has typed anything. It renders after the
 * field inputs in the markup (not before), so the customer fills the field
 * in first and sees the preview appear below it.
 *
 * IMPORTANT — combination changes destroy and re-render this whole block:
 * confirmed empirically that selecting a different Size/Color re-renders the
 * product-info region (including our displayProductAdditionalInfo output)
 * via PrestaShop's own AJAX refresh, wiping any text the customer had
 * already typed and orphaning our event listeners/preview nodes. There is no
 * reliable "refresh finished" event to hook across every theme in scope, so
 * this is handled defensively with a MutationObserver: whenever a *new*
 * .mzc-product-fields element appears, we re-init against it and restore
 * every field's last known value from an in-memory cache that survives the
 * swap (keyed by field id, which stays stable across re-renders).
 *
 * There is no server round-trip here at all — this is purely a client-side
 * mirror of what the customer is typing/selecting, for a quick "see roughly
 * how this looks" preview; the priced value is computed server-side at the
 * content-confirmation step.
 */
(function () {
  'use strict';

  var DEBOUNCE_MS = 120;
  var REINIT_DEBOUNCE_MS = 150;
  var DEFAULT_FONT_SIZE_RATIO = 6; // percent of the image's rendered width, if a field doesn't set its own
  var RECOMPUTE_POLL_MS = 400; // catches slider/carousel transitions we can't hook into directly
  // core's own product-variants.tpl renders both classes in the classic theme
  // ("product-variants js-product-variants"), but artedeum-pl's "leon" theme
  // only renders the bare "product-variants" class (confirmed by inspecting
  // its actual rendered HTML) — match either so combination-driven preview
  // works on both themes instead of silently finding nothing on leon.
  var VARIANTS_SELECTOR = '.js-product-variants, .product-variants';

  var IMAGE_SELECTORS = [
    '.carousel-item.active img', // classic / hummingbird (Bootstrap carousel)
    '.productslider-main-image', // artedeum-pl's "leon" theme
    '#product-images img',
    '.js-images-container img',
    '.product-cover img',
  ];

  // Survives the AJAX re-render described above — the only state that must.
  var savedValues = {}; // fieldId -> current value (string, or array of strings for checkbox/multiselect)

  /**
   * offsetParent/offsetWidth/offsetHeight alone aren't enough: a carousel
   * commonly keeps EVERY slide in the DOM with real dimensions, just
   * scrolled outside a clipping ancestor (some wrapper with
   * overflow:hidden) — confirmed on artedeum-pl's "leon" theme, a Slick
   * slider where 12 of 13 same-selector matches passed this check despite
   * being hundreds of pixels off-screen, so findProductImage() picked
   * whichever happened to be first in DOM order rather than the one
   * actually showing. This walks up checking every clipping ancestor and
   * rejects the element if it falls outside any of their bounds — generic
   * to whatever carousel library a theme uses, not tied to one specific
   * slider's class names.
   */
  function isVisible(el) {
    if (!el || el.offsetParent === null || el.offsetWidth === 0 || el.offsetHeight === 0) {
      return false;
    }

    var rect = el.getBoundingClientRect();
    var ancestor = el.parentElement;
    while (ancestor && ancestor !== document.body) {
      var style = getComputedStyle(ancestor);
      if (style.overflow !== 'visible' || style.overflowX !== 'visible' || style.overflowY !== 'visible') {
        var ancestorRect = ancestor.getBoundingClientRect();
        if (
          rect.right <= ancestorRect.left ||
          rect.left >= ancestorRect.right ||
          rect.bottom <= ancestorRect.top ||
          rect.top >= ancestorRect.bottom
        ) {
          return false;
        }
      }
      ancestor = ancestor.parentElement;
    }

    return true;
  }

  function findProductImage() {
    for (var i = 0; i < IMAGE_SELECTORS.length; i++) {
      var candidates = document.querySelectorAll(IMAGE_SELECTORS[i]);
      for (var j = 0; j < candidates.length; j++) {
        if (isVisible(candidates[j])) {
          return candidates[j];
        }
      }
    }

    return null;
  }

  function debounce(fn, delay) {
    var timer = null;

    return function () {
      var args = arguments;
      var context = this;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(context, args);
      }, delay);
    };
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
      var name = input.name;
      var group = document.querySelectorAll('input[name="' + CSS.escape(name) + '"]:checked');
      var labels = [];
      for (var i = 0; i < group.length; i++) {
        var label = group[i].closest('.mzc-field__option');
        labels.push(label ? label.textContent.replace(/\s*\(\+.*\)\s*$/, '').trim() : group[i].value);
      }

      return labels.join(', ');
    }

    return input.value;
  }

  /**
   * Re-applies a previously saved value onto a (possibly freshly re-rendered)
   * input, so the customer's input survives the combination-change re-render
   * described in the file docblock.
   */
  function restoreValue(input, value) {
    if (value === undefined || value === null) {
      return;
    }

    if (input.tagName === 'SELECT') {
      input.value = value;

      return;
    }

    if (input.type === 'radio' || input.type === 'checkbox') {
      input.checked = value.indexOf(input.value) !== -1;

      return;
    }

    input.value = value;
  }

  /**
   * Wires up "typed/max" counters (data-mzc-counter-for, only rendered when
   * a field has both max_length and the "Show character counter" option
   * set — see product_fields.tpl). Deliberately independent of the preview
   * system above: a field with no preview position configured still gets a
   * working counter, since the two features have nothing to do with each
   * other.
   */
  function initCharCounters(container) {
    container.querySelectorAll('[data-mzc-counter-for]').forEach(function (counter) {
      var fieldId = counter.getAttribute('data-mzc-counter-for');
      var max = counter.getAttribute('data-mzc-counter-max');
      var input = container.querySelector('[data-mzc-preview-source="' + CSS.escape(fieldId) + '"]');
      if (!input) {
        return;
      }

      var update = function () {
        counter.textContent = input.value.length + '/' + max;
      };

      input.addEventListener('input', update);
      update();
    });
  }

  /**
   * @return {Object} set (map) of currently selected attribute ids, read from
   *                   the native Size/Color/etc selectors — works for both
   *                   <select> groups and radio/color-swatch groups.
   */
  function getSelectedAttributeIds() {
    var selected = {};
    var variants = document.querySelector(VARIANTS_SELECTOR);
    if (!variants) {
      return selected;
    }

    variants.querySelectorAll('select[name^="group["]').forEach(function (select) {
      if (select.value) {
        selected[select.value] = true;
      }
    });
    variants.querySelectorAll('input[name^="group["]:checked').forEach(function (input) {
      selected[input.value] = true;
    });

    return selected;
  }

  /**
   * @param {Object} selectedIds set from getSelectedAttributeIds()
   * @param {Array} rules pre-sorted most-specific-first (see ProductFieldsPresenter)
   * @return {Object|null} the winning rule, or null if none match
   */
  function resolveActiveRule(selectedIds, rules) {
    for (var i = 0; i < rules.length; i++) {
      var ids = rules[i].attribute_ids;
      var allSelected = true;
      for (var j = 0; j < ids.length; j++) {
        if (!selectedIds[ids[j]]) {
          allSelected = false;
          break;
        }
      }
      if (allSelected) {
        return rules[i];
      }
    }

    return null;
  }

  /**
   * Color only — font-family is deliberately NOT set here. Unlike color, font
   * can be overridden per active combination rule (see BackgroundRuleType's
   * docblock), so it has to be re-applied every time the active rule can
   * change, not just once when the preview node is created — see
   * applyEffectiveFont() below.
   */
  function applyPreviewStyle(el, wrapper) {
    el.style.color = wrapper.getAttribute('data-mzc-preview-color') || '#000';
  }

  function fontSizeRatio(wrapper) {
    var parsed = parseFloat(wrapper.getAttribute('data-mzc-preview-font-size'));

    return isNaN(parsed) ? DEFAULT_FONT_SIZE_RATIO : parsed;
  }

  /**
   * @return {number|null} percent of the relevant image's rendered width the
   *                        preview text should wrap within, or null for "use
   *                        the full available width" (the pre-existing
   *                        behaviour, unchanged when this isn't configured).
   */
  function maxWidthRatio(wrapper) {
    var raw = wrapper.getAttribute('data-mzc-preview-max-width');
    if (!raw) {
      return null;
    }
    var parsed = parseFloat(raw);

    return isNaN(parsed) ? null : parsed;
  }

  function parseRules(container) {
    var raw = container.getAttribute('data-mzc-combination-rules');
    if (!raw) {
      return [];
    }

    try {
      var parsed = JSON.parse(raw);

      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  /**
   * @param {Element} container the current .mzc-product-fields element (a
   *                            fresh one after a combination-change re-render,
   *                            or the original one on first load)
   */
  function initContainer(container) {
    var floatingOverlay = container.querySelector('.mzc-preview-overlay');
    var rules = parseRules(container);
    var dynamicMode = rules.length > 0;
    var previewNodes = {}; // fieldId -> { el, x, y, fontSizeRatio, staticBox (element|null) }

    function findBox(idBackground) {
      return container.querySelector('.mzc-bg-preview[data-mzc-bg-id="' + CSS.escape(String(idBackground)) + '"]');
    }

    var fieldWrappers = container.querySelectorAll('.mzc-field[data-mzc-preview-x]');
    fieldWrappers.forEach(function (wrapper) {
      var x = parseFloat(wrapper.getAttribute('data-mzc-preview-x'));
      var y = parseFloat(wrapper.getAttribute('data-mzc-preview-y'));
      if (isNaN(x) || isNaN(y)) {
        return;
      }

      var fieldId = wrapper.getAttribute('data-mzc-field-id');
      var bgId = wrapper.getAttribute('data-mzc-preview-bg');
      var node = document.createElement('div');
      node.className = 'mzc-preview-text';
      applyPreviewStyle(node, wrapper);

      previewNodes[fieldId] = {
        el: node,
        x: x,
        y: y,
        // Raw font-family name (no quoting/fallback applied yet) — this
        // field's OWN default, used whenever no active rule overrides it.
        // See applyEffectiveFont().
        ownFont: wrapper.getAttribute('data-mzc-preview-font') || null,
        fontSizeRatio: fontSizeRatio(wrapper),
        maxWidthRatio: maxWidthRatio(wrapper),
        staticBox: bgId ? findBox(bgId) : null,
        // Defaults to true (front field) if the attribute is somehow
        // missing, matching the PHP side's own default — see
        // MzcProductFieldsField::$is_front_field's docblock.
        isFrontField: wrapper.getAttribute('data-mzc-front-field') !== '0',
      };
    });

    if (!Object.keys(previewNodes).length) {
      // No field on this product has a preview position at all, so nothing
      // below is reachable — but counters are independent of the preview
      // system entirely (see initCharCounters' docblock) and must still work.
      initCharCounters(container);

      return;
    }

    function placeInline(entry, box, activeRule) {
      var overlay = box.querySelector('.mzc-bg-preview__overlay');
      if (entry.el.parentElement !== overlay) {
        overlay.appendChild(entry.el);
      }
      entry.el.style.left = entry.x + '%';
      entry.el.style.top = effectivePosY(entry, activeRule) + '%';
      // Sized against the overlay's own (inset) box, not the full image —
      // it's inset by a margin in CSS specifically so preview text never
      // touches the mockup's edge; sizing against the full image would let
      // maxWidth push text back out into that margin. Actual overflow
      // beyond the box (e.g. from a customer pasting far more text than the
      // mockup was designed for) is still hard-clipped in CSS regardless.
      var rect = overlay.getBoundingClientRect();
      var fontSizeRatioValue = effectiveFontSizeRatio(entry, activeRule);
      entry.el.style.fontSize = Math.max(10, rect.width * (fontSizeRatioValue / 100)) + 'px';
      setPreviewWidth(entry.el, availableWidth(entry, rect.width));
    }

    function placeFloating(entry, activeRule) {
      if (entry.el.parentElement !== floatingOverlay) {
        floatingOverlay.appendChild(entry.el);
      }
      var image = findProductImage();
      if (!image) {
        return;
      }
      var rect = image.getBoundingClientRect();
      var fontSizeRatioValue = effectiveFontSizeRatio(entry, activeRule);
      entry.el.style.left = (rect.left + (entry.x / 100) * rect.width) + 'px';
      entry.el.style.top = (rect.top + (effectivePosY(entry, activeRule) / 100) * rect.height) + 'px';
      entry.el.style.fontSize = Math.max(10, rect.width * (fontSizeRatioValue / 100)) + 'px';
      setPreviewWidth(entry.el, availableWidth(entry, rect.width));
    }

    /**
     * Whether the active combination rule's own font/font_size override (see
     * BackgroundRuleType's docblock) applies to this field right now — only
     * when the field is actually being routed onto that rule's background,
     * matching resolveBoxForEntry()'s own condition exactly. A front field,
     * or one falling back to its own static default because no rule is
     * active, always keeps its own font/size unchanged.
     */
    function ruleAppliesTo(entry, activeRule) {
      return !entry.isFrontField && dynamicMode && activeRule !== null;
    }

    function effectiveFontSizeRatio(entry, activeRule) {
      if (ruleAppliesTo(entry, activeRule) && activeRule.font_size) {
        return activeRule.font_size;
      }

      return entry.fontSizeRatio;
    }

    /**
     * Same override pattern as effectiveFontSizeRatio() above, for the rule's
     * own Y position — a mockup a rule points to can need its text sitting
     * lower/higher than the field's own default (e.g. a canvas print's usable
     * area starts further down the frame than a mug's).  0 is a valid
     * override value, so this checks for null/undefined explicitly rather
     * than falsiness like the font/font_size checks do.
     */
    function effectivePosY(entry, activeRule) {
      if (ruleAppliesTo(entry, activeRule) && activeRule.pos_y !== null && activeRule.pos_y !== undefined) {
        return activeRule.pos_y;
      }

      return entry.y;
    }

    /**
     * Re-applies font-family every reposition (unlike color, set once in
     * applyPreviewStyle) since which font is "correct" can change along with
     * the active rule — see BackgroundRuleType's docblock.
     */
    function applyEffectiveFont(entry, activeRule) {
      var font = (ruleAppliesTo(entry, activeRule) && activeRule.font) ? activeRule.font : entry.ownFont;
      entry.el.style.fontFamily = font ? '"' + font + '"' : '';
    }

    /**
     * A field's own "Preview max width" (if the admin set one) caps how much
     * of the box/image's width the text is allowed to wrap within — narrower
     * than the full inset area, e.g. to keep long personalizations away from
     * a mockup's edges even when the overlay itself has room to spare.
     * Uncapped (the pre-existing behaviour) when not configured.
     */
    function availableWidth(entry, fullWidth) {
      if (!entry.maxWidthRatio) {
        return fullWidth;
      }

      return fullWidth * (entry.maxWidthRatio / 100);
    }

    /**
     * An explicit width, not just max-width, is required: this element is
     * centered via `left: X%` + `transform: translate(-50%, ...)`, and a
     * browser's shrink-to-fit width calculation for an auto-width absolutely
     * positioned element runs BEFORE the transform is applied — so for
     * anything positioned past the container's center, it under-computes the
     * space actually available and wraps far too early (confirmed: text was
     * wrapping one word per line despite plenty of unused width). Fixing the
     * width in place sidesteps that calculation entirely.
     */
    function setPreviewWidth(el, availableWidth) {
      el.style.width = availableWidth + 'px';
      el.style.maxWidth = availableWidth + 'px';
    }

    /**
     * Which box (if any) a field currently belongs in. A FRONT field
     * (isFrontField — see MzcProductFieldsField::$is_front_field's docblock)
     * never belongs in any box at all, full stop: it always floats on the
     * product's own photo, regardless of its own staticBox or any active
     * combination rule. Confirmed as a real bug before this flag existed: a
     * front-facing field (e.g. a name printed on the poster itself) and a
     * back-facing one (e.g. engraving on the back of a frame) on the SAME
     * product both got swept into the SAME box the moment a combination rule
     * matched, since dynamicMode had no way to tell them apart — see the
     * file docblock's "ALL preview-enabled fields move into whichever box
     * the winning rule points to", which this flag now qualifies to mean
     * "all preview-enabled NON-FRONT fields".
     *
     * For a non-front field: when a rule is active, it shares that one box
     * with every other non-front field. But when the product is
     * dynamic-mode-eligible (has at least one combination rule that COULD
     * apply to some of its combinations) and the customer's CURRENT
     * selection just doesn't happen to match any of them (e.g. this rule
     * needs Canvas, they picked Paper), that must not blank out a field that
     * has its own perfectly good static default — confirmed as a real bug:
     * a field with id_background set showed nothing at all whenever the
     * product ALSO had an unrelated combination rule that didn't match the
     * current selection. Falling back to the static default here is what
     * fixes that.
     */
    function resolveBoxForEntry(entry, activeRule) {
      if (entry.isFrontField) {
        return null;
      }

      if (dynamicMode && activeRule !== null) {
        return findBox(activeRule.id_background);
      }

      return entry.staticBox || null;
    }

    /**
     * A box only ever shows once there's actually something typed/selected
     * to preview onto it — an empty field pointed at a background is not
     * worth showing an otherwise-blank mockup photo for.
     */
    function updateBoxVisibility(activeRule) {
      var boxHasContent = {};

      Object.keys(previewNodes).forEach(function (fieldId) {
        var entry = previewNodes[fieldId];
        if (!entry.el.textContent.trim()) {
          return;
        }

        var box = resolveBoxForEntry(entry, activeRule);
        if (box) {
          boxHasContent[box.getAttribute('data-mzc-bg-id')] = true;
        }
      });

      container.querySelectorAll('.mzc-bg-preview').forEach(function (box) {
        var bgId = box.getAttribute('data-mzc-bg-id');
        box.style.display = boxHasContent[bgId] ? '' : 'none';
      });
    }

    /**
     * Moves each background box's wrapper to sit directly after whichever
     * field is CURRENTLY feeding it content, instead of wherever the server
     * happened to render it once at page load. That server-side placement
     * (ProductFieldsPresenter::attachBackgroundsToFields()) is necessarily
     * just a best-guess static default — it can't know, at render time,
     * which field a customer will actually be typing into once dynamicMode
     * routes ALL positioned fields into one shared box (see
     * resolveBoxForEntry()'s docblock). Confirmed as a real, recurring
     * source of confusion: a box statically owned by one field (e.g.
     * "Personalizacja") but ALSO fed dynamically by a different field once
     * a combination rule was active (e.g. "Grawer na odwrocie") rendered
     * between the two — visually "above" the field the customer was
     * actually typing into rather than below it. Doing this here, live,
     * removes the guesswork entirely: the box now always ends up right
     * below whichever field is really driving what's shown in it.
     */
    function repositionBoxWrappers(activeRule) {
      container.querySelectorAll('.mzc-bg-previews').forEach(function (wrapper) {
        var visibleBox = null;
        wrapper.querySelectorAll('.mzc-bg-preview').forEach(function (box) {
          if (box.style.display !== 'none') {
            visibleBox = box;
          }
        });
        if (!visibleBox) {
          return;
        }

        // The LAST field (in page order) currently feeding this box wins —
        // same "appears right below whichever field the customer is filling
        // in" intent as the very first version of this feature, just
        // resolved live instead of guessed server-side.
        var targetField = null;
        Object.keys(previewNodes).forEach(function (fieldId) {
          var entry = previewNodes[fieldId];
          if (!entry.el.textContent.trim()) {
            return;
          }
          if (resolveBoxForEntry(entry, activeRule) === visibleBox) {
            var fieldEl = container.querySelector('.mzc-field[data-mzc-field-id="' + CSS.escape(fieldId) + '"]');
            if (fieldEl) {
              targetField = fieldEl;
            }
          }
        });

        if (targetField && targetField.nextElementSibling !== wrapper) {
          targetField.insertAdjacentElement('afterend', wrapper);
        }
      });
    }

    function repositionAll() {
      var activeRule = dynamicMode ? resolveActiveRule(getSelectedAttributeIds(), rules) : null;

      // Visibility must be resolved BEFORE placeInline() runs below: a box
      // that's still display:none reports a 0x0 getBoundingClientRect(), and
      // placeInline() would lock the preview text's width to that 0 (it sets
      // an explicit width, not just max-width — see setPreviewWidth's
      // docblock) — confirmed empirically as the cause of text collapsing to
      // 0 width on the very first keystroke, right when a box goes from
      // hidden to shown.
      updateBoxVisibility(activeRule);
      repositionBoxWrappers(activeRule);

      Object.keys(previewNodes).forEach(function (fieldId) {
        var entry = previewNodes[fieldId];
        var box = resolveBoxForEntry(entry, activeRule);

        applyEffectiveFont(entry, activeRule);

        if (box) {
          placeInline(entry, box, activeRule);
        } else {
          placeFloating(entry, activeRule);
        }
      });
    }

    /**
     * Exposes the CURRENT preview state (grouped by whichever box each field
     * with content resolves to — same resolveBoxForEntry() the visible
     * preview itself uses, so this can never drift out of sync with what the
     * customer is actually looking at) for front-snapshot.js to composite
     * into a JPG at content-confirmation time. Only set once previewNodes is
     * non-empty (see the early return above) — a product with no
     * preview-positioned fields has nothing meaningful to snapshot.
     *
     * @return {Array<{imageUrl:string, isFloating:boolean, fields:Array}>}
     */
    container.mzcGetSnapshotGroups = function () {
      var activeRule = dynamicMode ? resolveActiveRule(getSelectedAttributeIds(), rules) : null;
      var groups = {};

      Object.keys(previewNodes).forEach(function (fieldId) {
        var entry = previewNodes[fieldId];
        var text = entry.el.textContent;
        if (!text.trim()) {
          return;
        }

        var box = resolveBoxForEntry(entry, activeRule);
        var key = box ? 'box:' + box.getAttribute('data-mzc-bg-id') : 'floating';

        if (!groups[key]) {
          var imageUrl = null;
          if (box) {
            var boxImg = box.querySelector('.mzc-bg-preview__image');
            imageUrl = boxImg ? boxImg.src : null;
          } else {
            var productImage = findProductImage();
            imageUrl = productImage ? productImage.src : null;
          }
          if (!imageUrl) {
            return;
          }
          groups[key] = { imageUrl: imageUrl, isFloating: !box, fields: [] };
        }

        // Recomputed explicitly (not read off entry.el.style) so the JPG
        // snapshot matches the live preview even if repositionAll() hasn't
        // run since the active rule last changed.
        var effectiveFont = (ruleAppliesTo(entry, activeRule) && activeRule.font) ? activeRule.font : entry.ownFont;

        groups[key].fields.push({
          x: entry.x,
          y: effectivePosY(entry, activeRule),
          fontSizeRatio: effectiveFontSizeRatio(entry, activeRule),
          maxWidthRatio: entry.maxWidthRatio,
          color: entry.el.style.color || '#000',
          fontFamily: effectiveFont ? '"' + effectiveFont + '"' : '',
          text: text,
        });
      });

      return Object.keys(groups).map(function (key) {
        return groups[key];
      });
    };

    var updateOne = function (fieldId) {
      var input = container.querySelector('[data-mzc-preview-source="' + CSS.escape(fieldId) + '"]');
      var entry = previewNodes[fieldId];
      if (!input || !entry) {
        return;
      }

      entry.el.textContent = currentValue(input);
      repositionAll();
    };

    var debouncedUpdate = debounce(updateOne, DEBOUNCE_MS);

    container.querySelectorAll('[data-mzc-preview-source]').forEach(function (input) {
      var fieldId = input.getAttribute('data-mzc-preview-source');
      if (!previewNodes[fieldId]) {
        return;
      }

      // Restore whatever the customer had typed/selected before this
      // container was (re-)rendered, then seed the preview text from it.
      if (Object.prototype.hasOwnProperty.call(savedValues, fieldId)) {
        restoreValue(input, savedValues[fieldId]);
      }
      previewNodes[fieldId].el.textContent = currentValue(input);

      var eventName = input.tagName === 'SELECT' || input.type === 'radio' || input.type === 'checkbox' ? 'change' : 'input';
      input.addEventListener(eventName, function () {
        savedValues[fieldId] = currentValue(input);
        debouncedUpdate(fieldId);
      });
    });

    // After the loop above, so a value just restored onto a re-rendered
    // input (following a combination change) is what the counter's first
    // read reflects, not the fresh, empty value the server always renders.
    initCharCounters(container);

    if (dynamicMode) {
      // Same delegated selector/event PrestaShop's own core.js listens on to
      // emit "updatedProductCombination" — reacting to it directly here means
      // we don't have to wait for (or parse) its AJAX round-trip.
      document.body.addEventListener('change', function (e) {
        if (e.target.closest(VARIANTS_SELECTOR)) {
          repositionAll();
        }
      });
    }

    window.addEventListener('scroll', repositionAll, { passive: true });
    window.addEventListener('resize', repositionAll);
    setInterval(repositionAll, RECOMPUTE_POLL_MS);
    repositionAll();
  }

  function watchForReplacement() {
    var current = document.querySelector('.mzc-product-fields');

    var scheduleReinit = debounce(function () {
      var latest = document.querySelector('.mzc-product-fields');
      if (latest && latest !== current) {
        current = latest;
        initContainer(latest);
      }
    }, REINIT_DEBOUNCE_MS);

    new MutationObserver(scheduleReinit).observe(document.body, { childList: true, subtree: true });
  }

  function init() {
    var container = document.querySelector('.mzc-product-fields');
    if (!container) {
      return;
    }

    initContainer(container);
    watchForReplacement();
  }

  if (document.readyState !== 'loading') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
