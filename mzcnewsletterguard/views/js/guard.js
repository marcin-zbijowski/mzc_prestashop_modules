/**
 * MZC Newsletter Guard — front.
 *
 * 1. Formularze newslettera dostają pole-pułapkę i token czasowy.
 * 2. Gdy skonfigurowano Cloudflare Turnstile, formularz newslettera i formularz
 *    kontaktowy dostają widżet. Skrypt Cloudflare ładuje się dopiero, gdy formularz
 *    zbliża się do widoku albo ktoś w nim klika, a nie na każdej stronie od razu.
 *    Widżet sam dokłada do formularza pole `cf-turnstile-response`.
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

  function mountTurnstile(form) {
    var kind = kindOf(form);
    if (!ts || !kind || !ts[kind] || form.__mzcngTs) { return; }

    var st = form.__mzcngTs = { box: null, id: null, token: '', failed: false, pending: null, timer: null };

    function render() {
      if (st.id !== null || st.failed) { return; }
      st.box = document.createElement('div');
      st.box.className = 'mzcng-turnstile';
      form.appendChild(st.box);

      st.id = window.turnstile.render(st.box, {
        sitekey: ts.sitekey,
        appearance: ts.appearance,
        callback: function (token) { st.token = token; release(); },
        'expired-callback': function () { st.token = ''; window.turnstile.reset(st.id); },
        'error-callback': function () { st.failed = true; release(); }
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
      if (st.token || st.failed || st.pending) {
        // Token jest, widżet zawiódł (formularz idzie dalej bez niego) albo właśnie czekamy.
        if (st.pending) { e.preventDefault(); }
        return;
      }
      e.preventDefault();
      st.pending = { submitter: e.submitter || null };
      st.timer = setTimeout(function () { st.failed = true; release(); }, WAIT_MS);
      ensure();
    }, true);

    form.addEventListener('focusin', function () { ensure(); }, { once: true });

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
