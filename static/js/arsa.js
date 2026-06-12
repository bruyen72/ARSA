/* ARSA — comportamento compartilhado entre páginas */
(function () {
  // ── Aplicar tweaks salvos antes da pintura ──
  var saved = {};
  try { saved = JSON.parse(localStorage.getItem('arsa-tweaks-v1') || '{}'); } catch (e) {}
  window.__arsaApplyTweaks = function (t) {
    var theme = t.theme || 'claro';
    var anim = (t.anim === undefined ? true : t.anim) ? 'on' : 'off';
    document.body.dataset.theme = theme;
    document.body.dataset.anim = anim;
    if (t.accent) {
      document.documentElement.style.setProperty('--accent', t.accent.main);
      document.documentElement.style.setProperty('--accent-deep', t.accent.deep);
      document.documentElement.style.setProperty('--accent-soft', t.accent.soft);
    }
    if (t.radius !== undefined) {
      document.documentElement.style.setProperty('--radius', t.radius + 'px');
    }
  };
  window.__arsaSavedTweaks = saved;
  if (document.body) window.__arsaApplyTweaks(saved);

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    window.__arsaApplyTweaks(saved);

    // ── Header com fundo ao rolar ──
    var header = document.querySelector('.site-header');
    function onHeaderScroll() {
      if (header) header.classList.toggle('scrolled', window.scrollY > 24);
    }
    window.addEventListener('scroll', onHeaderScroll, { passive: true });
    onHeaderScroll();

    // ── Menu móvel ──
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.main-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // ── Contadores animados ──
    function startCounter(el) {
      if (el.__counted) return;
      el.__counted = true;
      var target = parseInt(el.getAttribute('data-count'), 10);
      var animOn = document.body.dataset.anim !== 'off' &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!animOn) { el.textContent = target; return; }
      var dur = 1400, t0 = null;
      function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased);
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    // ── Reveal on scroll (IO + fallback por posição) ──
    var reveals = Array.prototype.slice.call(document.querySelectorAll('.rv'));
    var counters = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));

    function inViewport(el, margin) {
      var r = el.getBoundingClientRect();
      return r.top < (window.innerHeight - (margin || 0)) && r.bottom > 0;
    }

    // Checagem por posição: funciona mesmo sem IntersectionObserver.
    function checkVisible() {
      reveals.forEach(function (el) {
        if (!el.classList.contains('in') && inViewport(el, 30)) el.classList.add('in');
      });
      counters.forEach(function (el) {
        if (!el.__counted && inViewport(el, 30)) startCounter(el);
      });
    }

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) {
            en.target.classList.add('in');
            io.unobserve(en.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      reveals.forEach(function (el) { io.observe(el); });

      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          cio.unobserve(en.target);
          startCounter(en.target);
        });
      }, { threshold: 0.5 });
      counters.forEach(function (el) { cio.observe(el); });
    }

    // Fallbacks: revela o que já está na tela agora, reavalia a cada rolagem
    // e, por segurança, revela tudo o que está acima da dobra após 800ms
    // caso o IntersectionObserver nunca dispare neste ambiente.
    checkVisible();
    window.addEventListener('scroll', checkVisible, { passive: true });
    window.addEventListener('resize', checkVisible);
    setTimeout(checkVisible, 400);
    setTimeout(function () {
      var anyIn = reveals.some(function (el) { return el.classList.contains('in'); });
      if (!anyIn) {
        reveals.forEach(function (el) { el.classList.add('in'); });
        counters.forEach(startCounter);
      } else {
        checkVisible();
      }
    }, 800);
  });
})();
