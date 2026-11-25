/**
 * HulamBee Interactive UI & Animations
 * Pure vanilla JS, accessible, and respects prefers-reduced-motion.
 */
(function () {
  'use strict';

  var prefersReducedMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initScrollEffects() {
    var progressBar = document.querySelector('.scroll-progress');
    var topbar = document.querySelector('.topbar');

    var topBtn = document.createElement('button');
    topBtn.type = 'button';
    topBtn.className = 'hb-top-btn';
    topBtn.setAttribute('aria-label', 'Back to top');
    topBtn.title = 'Back to top';
    topBtn.innerHTML = '&#8593;';
    document.body.appendChild(topBtn);

    topBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    });

    function onScroll() {
      var scrollY = window.scrollY || document.documentElement.scrollTop || 0;
      var docHeight = document.documentElement.scrollHeight - window.innerHeight;
      var pct = docHeight > 0 ? Math.min(100, Math.max(0, (scrollY / docHeight) * 100)) : 0;

      if (progressBar) {
        progressBar.style.width = pct.toFixed(2) + '%';
      }
      if (topbar) {
        topbar.classList.toggle('is-scrolled', scrollY > 12);
      }
      topBtn.classList.toggle('is-visible', scrollY > 280);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function initRevealAnimations() {
    var targets = document.querySelectorAll(
      '.stats > div, .card, .steps li, .panel, .kpi, .detail, .auth, .scroll'
    );
    if (!targets.length) return;

    targets.forEach(function (el, idx) {
      el.classList.add('hb-reveal');
      el.style.setProperty('--reveal-delay', ((idx % 6) * 55) + 'ms');
    });

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

    targets.forEach(function (el) { observer.observe(el); });
  }

  function initCounters() {
    var counters = document.querySelectorAll('.stats strong, .kpi strong');
    if (!counters.length || prefersReducedMotion) return;

    function animateValue(el) {
      var raw = (el.textContent || '').trim();
      if (!/^\d+$/.test(raw)) return;
      var target = parseInt(raw, 10);
      if (target <= 0) return;

      var duration = Math.min(900, Math.max(350, target * 45));
      var startTime = performance.now();

      function tick(now) {
        var progress = Math.min(1, (now - startTime) / duration);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = String(Math.round(target * eased));
        if (progress < 1) {
          requestAnimationFrame(tick);
        } else {
          el.textContent = String(target);
        }
      }
      requestAnimationFrame(tick);
    }

    if (!('IntersectionObserver' in window)) {
      counters.forEach(animateValue);
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateValue(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.25 });

    counters.forEach(function (el) { observer.observe(el); });
  }

  function initHeroSpotlight() {
    var hero = document.querySelector('.hero');
    if (!hero || prefersReducedMotion) return;

    hero.addEventListener('pointermove', function (e) {
      var rect = hero.getBoundingClientRect();
      var x = ((e.clientX - rect.left) / rect.width) * 100;
      var y = ((e.clientY - rect.top) / rect.height) * 100;
      hero.style.setProperty('--mx', x.toFixed(1) + '%');
      hero.style.setProperty('--my', y.toFixed(1) + '%');
    });
  }

  function initCardTilt() {
    var cards = document.querySelectorAll('.card');
    if (!cards.length || prefersReducedMotion) return;
    if (window.matchMedia && !window.matchMedia('(hover: hover)').matches) return;

    cards.forEach(function (card) {
      card.addEventListener('pointermove', function (e) {
        var rect = card.getBoundingClientRect();
        var px = (e.clientX - rect.left) / rect.width;
        var py = (e.clientY - rect.top) / rect.height;
        var rx = (0.5 - py) * 5;
        var ry = (px - 0.5) * 6;

        card.style.setProperty('--cx', (px * 100).toFixed(1) + '%');
        card.style.setProperty('--cy', (py * 100).toFixed(1) + '%');
        card.style.transform =
          'perspective(800px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg) translateY(-6px)';
      });

      card.addEventListener('pointerleave', function () {
        card.style.transform = '';
      });
    });
  }

  function initButtonRipples() {
    if (prefersReducedMotion) return;

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn');
      if (!btn) return;

      var rect = btn.getBoundingClientRect();
      var size = Math.max(rect.width, rect.height);
      var ripple = document.createElement('span');
      ripple.className = 'btn-ripple';
      ripple.style.width = size + 'px';
      ripple.style.height = size + 'px';
      ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
      ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

      btn.appendChild(ripple);
      setTimeout(function () {
        if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
      }, 600);
    });
  }

  function initStepsInteraction() {
    var items = document.querySelectorAll('.steps li');
    if (!items.length) return;

    items.forEach(function (item) {
      item.addEventListener('click', function () {
        items.forEach(function (other) { other.classList.remove('is-active'); });
        item.classList.add('is-active');
      });
    });
  }

  function initFlashes() {
    var flashes = document.querySelectorAll('.flash');
    flashes.forEach(function (flashEl) {
      if (flashEl.querySelector('.flash-close')) return;

      var closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'flash-close';
      closeBtn.setAttribute('aria-label', 'Dismiss notice');
      closeBtn.innerHTML = '&times;';
      flashEl.appendChild(closeBtn);

      var dismiss = function () {
        flashEl.classList.add('is-leaving');
        setTimeout(function () {
          if (flashEl.parentNode) flashEl.parentNode.removeChild(flashEl);
        }, 260);
      };

      closeBtn.addEventListener('click', dismiss);

      if (!flashEl.classList.contains('error')) {
        setTimeout(dismiss, 6500);
      }
    });
  }

  function initDetailLightbox() {
    var img = document.querySelector('.detail > .detail-img');
    if (!img) return;

    img.classList.add('is-zoomable');
    img.title = 'Click to enlarge';

    var overlay = document.createElement('div');
    overlay.className = 'hb-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', img.alt || 'Equipment preview');

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'hb-lightbox-close';
    closeBtn.setAttribute('aria-label', 'Close preview');
    closeBtn.innerHTML = '&times;';

    var fullImg = document.createElement('img');
    fullImg.src = img.src;
    fullImg.alt = img.alt || 'Equipment image';

    overlay.appendChild(closeBtn);
    overlay.appendChild(fullImg);
    document.body.appendChild(overlay);

    function open() {
      fullImg.src = img.src;
      overlay.classList.add('is-open');
    }

    function close() {
      overlay.classList.remove('is-open');
    }

    img.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
    });
  }

  function initPasswordToggles() {
    var pwInputs = document.querySelectorAll('input[type="password"]');
    pwInputs.forEach(function (input) {
      if (input.parentElement && input.parentElement.classList.contains('pw-wrap')) return;

      var wrap = document.createElement('div');
      wrap.className = 'pw-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle';
      btn.textContent = 'Show';
      btn.setAttribute('aria-label', 'Show password');
      wrap.appendChild(btn);

      btn.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.textContent = showing ? 'Show' : 'Hide';
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });
  }

  function initSearchEnhancement() {
    var searchForm = document.querySelector('form.search');
    if (!searchForm) return;
    var input = searchForm.querySelector('input[name="q"]');
    if (!input) return;

    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'search-clear';
    clearBtn.setAttribute('aria-label', 'Clear search');
    clearBtn.innerHTML = '&times;';
    searchForm.insertBefore(clearBtn, searchForm.querySelector('button[type="submit"]'));

    function syncClear() {
      clearBtn.style.display = input.value.trim() ? 'inline-flex' : 'none';
    }

    input.addEventListener('input', syncClear);
    clearBtn.addEventListener('click', function () {
      input.value = '';
      syncClear();
      searchForm.submit();
    });
    syncClear();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initScrollEffects();
    initRevealAnimations();
    initCounters();
    initHeroSpotlight();
    initCardTilt();
    initButtonRipples();
    initStepsInteraction();
    initFlashes();
    initDetailLightbox();
    initPasswordToggles();
    initSearchEnhancement();
  });
})();
