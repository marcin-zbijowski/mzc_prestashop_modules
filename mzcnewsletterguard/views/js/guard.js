/**
 * MZC Newsletter Guard — front.
 *
 * 1. Formularze newslettera dostają pole-pułapkę i token czasowy.
 * 2. Gdy skonfigurowano Cloudflare Turnstile, formularz newslettera i formularz
 *    kontaktowy dostają widżet. Skrypt Cloudflare ładuje się dopiero, gdy ktoś zaczyna
 *    wypełniać formularz (kontakt: gdy formularz jest widoczny), a nie na każdej stronie
 *    od razu. Token trafia do formularza przez ukryte pole `cf-turnstile-response`.
 */
(function () {
  'use strict';

  var cfg = window.mzcng || {};
  var ts = cfg.turnstile || null;

  var NEWSLETTER = 'input[name="blockHookName"], [name="submitNewsletter"]';
  var CONTACT = '[name="submitMessage"]';
  var API = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
  // Ile czekać na token po kliknięciu „Wyślij”, zanim formularz pójdzie bez niego
  // (serwer wtedy odpowie komunikatem z prośbą o ponowną próbę).
  var WAIT_MS = 8000;

  function kindOf(form) {
    if (form.querySelector(NEWSLETTER)) { return 'newsletter'; }
    if (form.querySelector(CONTACT)) { return 'contact'; }
    return null;
  }

  // ---------------------------------------------------------------- honeypot

  function addHidden(form, name, value) {
    var el = document.createElement('input');
    el.type = 'hidden';
    el.name = name;
    el.value = value;
    form.appendChild(el);
  }

  function decorateNewsletter(form) {
    if (!cfg.ts || kindOf(form) !== 'newsletter' || form.querySelector('input[name="mzcng_ts"]')) {
      return;
    }
    addHidden(form, 'mzcng_ts', cfg.ts);
    addHidden(form, 'mzcng_sig', cfg.sig);

    var hp = document.createElement('input');
    hp.type = 'text';
    hp.name = 'mzcng_hp';
    hp.value = '';
    hp.tabIndex = -1;
    hp.setAttribute('autocomplete', 'off');
    hp.setAttribute('aria-hidden', 'true');
    hp.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0;';
    form.appendChild(hp);
  }

  // --------------------------------------------------------------- turnstile

  var apiState = 'idle'; // idle | loading | ready | failed
  var apiWaiting = [];

  function loadApi(done) {
    if (apiState === 'ready') { done(true); return; }
    if (apiState === 'failed') { done(false); return; }
    apiWaiting.push(done);
    if (apiState === 'loading') { return; }

    apiState = 'loading';
    var s = document.createElement('script');
    s.src = API;
    s.async = true;
    s.onload = function () { settle('ready', true); };
    s.onerror = function () { settle('failed', false); };
    document.head.appendChild(s);
  }

  function settle(state, ok) {
    apiState = state;
    var list = apiWaiting;
    apiWaiting = [];
    list.forEach(function (fn) { fn(ok); });
  }

  // Motyw widżetu z tła, na którym stoi formularz: ciemna stopka dostaje ciemny widżet.
  function themeFor(el) {
    for (var node = el; node && node.nodeType === 1; node = node.parentElement) {
      var m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(getComputedStyle(node).backgroundColor);
      if (m && (m[4] === undefined || parseFloat(m[4]) > 0.5)) {
        // luminancja względna, wystarczy przybliżenie
        var lum = (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255;
        return lum < 0.45 ? 'dark' : 'light';
      }
    }
    return 'light';
  }

  /**
   * Widżet dostaje własne miejsce poza układem formularza. Wstawiony do środka stawał się
   * kolejnym elementem rzędu i zgniatał pole e-mail w formularzach typu „pole + przycisk”.
   * Newsletter: tuż pod formularzem. Kontakt: nad przyciskiem wysyłania.
   * Token trafia do formularza przez własne ukryte pole, a nie przez pole, które
   * Turnstile dokładałby sam do swojego kontenera.
   */
  function placeBox(form, kind, box) {
    if (kind === 'contact') {
      var btn = form.querySelector(CONTACT);
      var anchor = btn && btn.parentElement && btn.parentElement !== form ? btn.parentElement : btn;
      if (anchor && anchor.parentNode) {
        anchor.parentNode.insertBefore(box, anchor);
        return;
      }
    }
    form.insertAdjacentElement('afterend', box);
  }

  function mountTurnstile(form) {
    var kind = kindOf(form);
    if (!ts || !kind || !ts[kind] || form.__mzcngTs) { return; }

    var field = document.createElement('input');
    field.type = 'hidden';
    field.name = 'cf-turnstile-response';
    form.appendChild(field);

    var st = form.__mzcngTs = { box: null, id: null, token: '', failed: false, interactive: false, pending: null, timer: null };

    function render() {
      if (st.id !== null || st.failed) { return; }
      st.box = document.createElement('div');
      st.box.className = 'mzcng-turnstile';
      st.box.style.cssText = 'margin:12px 0 0;max-width:100%;';
      placeBox(form, kind, st.box);

      st.id = window.turnstile.render(st.box, {
        sitekey: ts.sitekey,
        appearance: ts.appearance,
        // Wąska kolumna stopki: wersja kompaktowa (150 px) zamiast 300 px.
        size: kind === 'newsletter' ? 'compact' : 'normal',
        theme: themeFor(form),
        'response-field': false,
        callback: function (token) { st.token = token; field.value = token; release(); },
        'expired-callback': function () { st.token = ''; field.value = ''; window.turnstile.reset(st.id); },
        'error-callback': function () { st.failed = true; release(); },
        // Cloudflare uznał, że bez kliknięcia się nie obejdzie: widżet jest teraz widoczny.
        'before-interactive-callback': function () { st.interactive = true; }
      });
    }

    function ensure(cb) {
      loadApi(function (ok) {
        if (!ok) { st.failed = true; }
        else { render(); }
        if (cb) { cb(); }
      });
    }

    // Wysłanie wstrzymane do czasu tokenu: dostarcza go callback albo upływ WAIT_MS.
    function release() {
      if (!st.pending) { return; }
      var pending = st.pending;
      st.pending = null;
      clearTimeout(st.timer);
      // requestSubmit(submitter) zachowuje wartość przycisku (submitMessage, submitNewsletter),
      // po której serwer rozpoznaje wysłanie formularza.
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(pending.submitter || undefined);
      } else {
        form.submit();
      }
    }

    form.addEventListener('submit', function (e) {
      if (st.token || st.failed) { return; }

      e.preventDefault();

      // Widżet czeka na kliknięcie: nie ma sensu odliczać, trzeba pokazać go odwiedzającemu.
      if (st.interactive && st.box) {
        st.pending = { submitter: e.submitter || null };
        clearTimeout(st.timer);
        st.box.scrollIntoView({ block: 'center', behavior: 'smooth' });
        return;
      }
      if (st.pending) { return; }

      st.pending = { submitter: e.submitter || null };
      st.timer = setTimeout(function () {
        // Dalej bez tokenu, o ile widżet nie zdążył się pokazać: serwer odpowie komunikatem.
        if (!st.interactive) { st.failed = true; release(); }
      }, WAIT_MS);
      ensure();
    }, true);

    // Zawsze, gdy ktoś zaczyna wypełniać formularz (focusin obejmuje też dotyk na telefonie).
    form.addEventListener('focusin', function () { ensure(); }, { once: true });

    // Kontakt to osobna strona z jednym formularzem, więc widżet przygotowujemy, gdy formularz
    // jest widoczny. Newsletter siedzi w stopce każdej strony: tam dopiero po kliknięciu, żeby
    // nie ładować skryptu Cloudflare i nie generować wyzwań komuś, kto go nie używa.
    if (kind === 'contact') {
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          if (entries.some(function (en) { return en.isIntersecting; })) {
            io.disconnect();
            ensure();
          }
        }, { rootMargin: '300px' });
        io.observe(form);
      } else {
        ensure();
      }
    }
  }

  // -------------------------------------------------------------------- init

  function decorate(form) {
    if (!form || form.tagName !== 'FORM') { return; }
    decorateNewsletter(form);
    mountTurnstile(form);
  }

  function init() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      decorate(forms[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // formularze doładowane później (modale, AJAX) oraz zabezpieczenie tuż przed wysłaniem
  document.addEventListener('submit', function (e) {
    decorate(e.target);
  }, true);
})();
