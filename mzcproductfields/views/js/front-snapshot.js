/**
 * MZC Product Fields — order-visualization JPG snapshot.
 *
 * Captures "what the customer saw" at content-confirmation time by
 * compositing, via the Canvas API, the exact same background image + typed
 * text (position/font/color already driving the live HTML preview — see
 * front-product-fields.js) into a JPG, then uploads it (fire-and-forget) so
 * the backoffice order page can show it (see
 * Mzcproductfields::hookDisplayAdminOrderMain).
 *
 * Reads the current preview state via container.mzcGetSnapshotGroups()
 * (exposed by front-product-fields.js's initContainer()) instead of
 * re-deriving which background box/rule is currently active — that
 * resolution logic (dynamicMode, rule matching, static fallback) is
 * non-trivial and already correct there; duplicating it here would risk the
 * snapshot silently drifting out of sync with the live preview it's meant to
 * capture.
 *
 * Deliberately best-effort: called after the customer has already confirmed
 * (see front-confirmation.js), never awaited by — and never allowed to
 * block or fail — the actual add-to-cart flow. A missing/failed snapshot
 * just means no backoffice thumbnail for that line, not a broken checkout.
 */
(function () {
  'use strict';

  // Matches .mzc-bg-preview__overlay's `inset: 6%` in front-product-fields.css
  // — the live preview clips text to this same inset box, so the snapshot
  // must use the identical ratio to render what the customer actually saw.
  var OVERLAY_INSET_RATIO = 0.06;
  var JPEG_QUALITY = 0.85;

  /**
   * Canvas has no built-in text wrapping — greedily packs words onto each
   * line up to maxWidth, same visual effect as the live preview's CSS
   * `white-space: pre-wrap; word-break: break-word`.
   */
  function wrapLines(ctx, text, maxWidth) {
    var lines = [];

    String(text).split('\n').forEach(function (paragraph) {
      var words = paragraph.split(' ');
      var current = '';
      words.forEach(function (word) {
        var attempt = current ? current + ' ' + word : word;
        if (current && ctx.measureText(attempt).width > maxWidth) {
          lines.push(current);
          current = word;
        } else {
          current = attempt;
        }
      });
      lines.push(current);
    });

    return lines;
  }

  /**
   * @param {Object} group {imageUrl, fields: [{x,y,fontSizeRatio,color,fontFamily,text}]}
   * @return {Promise<Blob|null>}
   */
  function drawGroup(group) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.crossOrigin = 'anonymous';

      img.onload = function () {
        try {
          var canvas = document.createElement('canvas');
          canvas.width = img.naturalWidth;
          canvas.height = img.naturalHeight;
          var ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0);

          var insetX = canvas.width * OVERLAY_INSET_RATIO;
          var insetY = canvas.height * OVERLAY_INSET_RATIO;
          var overlayW = canvas.width * (1 - 2 * OVERLAY_INSET_RATIO);
          var overlayH = canvas.height * (1 - 2 * OVERLAY_INSET_RATIO);

          // Hard-clip to the overlay box, same backstop as
          // .mzc-bg-preview__clip { overflow: hidden } in the live preview,
          // so text far longer than the mockup was designed for doesn't spill
          // past the image in the captured JPG either.
          ctx.save();
          ctx.beginPath();
          ctx.rect(insetX, insetY, overlayW, overlayH);
          ctx.clip();

          group.fields.forEach(function (field) {
            var fontSizePx = Math.max(10, overlayW * (field.fontSizeRatio / 100));
            ctx.font = fontSizePx + 'px ' + (field.fontFamily || 'sans-serif');
            ctx.fillStyle = field.color || '#000';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';

            var x = insetX + (field.x / 100) * overlayW;
            var y = insetY + (field.y / 100) * overlayH;
            // Same "Preview max width" cap the live preview applies (see
            // front-product-fields.js's availableWidth()) — kept in sync so
            // the captured JPG wraps text exactly where the customer saw it
            // wrap, not wider.
            var wrapWidth = field.maxWidthRatio ? overlayW * (field.maxWidthRatio / 100) : overlayW;
            wrapLines(ctx, field.text, wrapWidth).forEach(function (line, i) {
              ctx.fillText(line, x, y + i * fontSizePx * 1.2);
            });
          });

          ctx.restore();

          canvas.toBlob(
            function (blob) {
              resolve(blob);
            },
            'image/jpeg',
            JPEG_QUALITY
          );
        } catch (e) {
          // Most likely a tainted canvas (cross-origin image served without
          // CORS headers) — best-effort, so just skip this group silently.
          resolve(null);
        }
      };
      img.onerror = function () {
        resolve(null);
      };
      img.src = group.imageUrl;
    });
  }

  function upload(saveUrl, idCustomization, blob) {
    if (!blob) {
      return;
    }

    var formData = new FormData();
    formData.append('id_customization', idCustomization);
    formData.append('snapshot', blob, 'snapshot.jpg');

    fetch(saveUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).catch(function () {});
  }

  /**
   * @param {Element} container the .mzc-product-fields element
   * @param {number|string} idCustomization returned by savecustomization.php
   */
  function captureAndUpload(container, idCustomization) {
    if (typeof container.mzcGetSnapshotGroups !== 'function') {
      return;
    }

    var saveUrl = container.getAttribute('data-mzc-snapshot-save-url');
    if (!saveUrl) {
      return;
    }

    // Custom @font-face fonts (see product_fields.tpl) must be fully loaded
    // before the canvas draws text with them, or it silently falls back to a
    // default font — document.fonts.ready resolves once all fonts the page
    // has requested so far have finished loading.
    var fontsReady = window.document.fonts ? document.fonts.ready : Promise.resolve();

    fontsReady.then(function () {
      container.mzcGetSnapshotGroups().forEach(function (group) {
        drawGroup(group).then(function (blob) {
          upload(saveUrl, idCustomization, blob);
        });
      });
    });
  }

  window.MzcProductFieldsSnapshot = { captureAndUpload: captureAndUpload };
})();
