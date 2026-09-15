/* Campus APP ERP — Main JS */
(function () {
  'use strict';

  // Mobile nav toggle
  var toggle = document.querySelector('.nav-toggle');
  var mobileNav = document.querySelector('.nav-mobile');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobileNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        mobileNav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Toast helper
  function showToast(message) {
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add('show');
    });
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 300);
    }, 3200);
  }

  // Contact form → POST /api/contact.php (SMTP via PHP on Apache)
  var contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var statusEl = document.getElementById('contact-form-status');
      var btn = contactForm.querySelector('button[type="submit"]');

      if (!contactForm.checkValidity()) {
        contactForm.reportValidity();
        return;
      }

      var fd = new FormData(contactForm);
      var payload = {};
      fd.forEach(function (value, key) {
        payload[key] = typeof value === 'string' ? value.trim() : value;
      });
      payload.page_url = window.location.href;

      var submittedOk = false;
      if (btn) {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
      }
      if (statusEl) {
        statusEl.textContent = 'Sending your enquiry…';
      }

      fetch('/api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { res: res, data: data };
          }).catch(function () {
            return { res: res, data: { ok: false, error: 'Unexpected server response.' } };
          });
        })
        .then(function (result) {
          if (result.data && result.data.ok) {
            submittedOk = true;
            showToast('Thank you! Redirecting…');
            if (statusEl) {
              statusEl.textContent = 'You’ll get a confirmation email; our team replies within 24 business hours.';
            }
            contactForm.reset();
            window.setTimeout(function () {
              window.location.href = '/thank-you';
            }, 600);
            return;
          } else {
            var err = (result.data && result.data.error) || 'Could not send. Please try again or email us directly.';
            showToast(err);
            if (statusEl) statusEl.textContent = err;
          }
        })
        .catch(function () {
          var err = 'Network error. Please try again or email info@campusapperp.com.';
          showToast(err);
          if (statusEl) statusEl.textContent = err;
        })
        .finally(function () {
          if (btn && !submittedOk) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
          }
        });
    });
  }

  // Legacy toast-only forms (if any remain)
  document.querySelectorAll('form[data-toast]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.getAttribute('data-toast') || 'Thank you! We will get back to you soon.';
      showToast(msg);
      form.reset();
    });
  });

  // Login form — UI only
  var loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showToast('Demo only — no real authentication. Explore the sample dashboard!');
    });
  }

  // Table search / filter
  document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    var tableId = input.getAttribute('data-table-filter');
    var table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  });

  // Highlight active nav based on clean URL path (/features, /contact, …)
  var path = window.location.pathname.replace(/\/index\.html$/, '').replace(/\/$/, '') || '/';
  document.querySelectorAll('.nav-desktop a, .nav-mobile a, .app-sidebar nav a, .mobile-side-nav a').forEach(function (a) {
    var href = (a.getAttribute('href') || '').split('#')[0].replace(/\/$/, '') || '/';
    if (href === path || (path === '/' && (href === '/' || href === ''))) {
      a.classList.add('active');
    }
  });

  // Accessible hero value rotator
  var rotator = document.getElementById('hero-rotator');
  if (rotator) {
    var panels = Array.prototype.slice.call(rotator.querySelectorAll('.hero-rotator-panel'));
    var dots = Array.prototype.slice.call(rotator.querySelectorAll('.hero-rotator-dots button'));
    var index = 0;
    var timer = null;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function showPanel(i) {
      index = (i + panels.length) % panels.length;
      panels.forEach(function (panel, n) {
        var on = n === index;
        panel.classList.toggle('active', on);
        if (on) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      });
      dots.forEach(function (dot, n) {
        dot.setAttribute('aria-selected', n === index ? 'true' : 'false');
      });
    }

    function startAuto() {
      if (reduceMotion || panels.length < 2) return;
      stopAuto();
      timer = setInterval(function () {
        showPanel(index + 1);
      }, 6500);
    }

    function stopAuto() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        var go = parseInt(dot.getAttribute('data-go'), 10);
        showPanel(go);
        startAuto();
      });
    });

    rotator.addEventListener('mouseenter', stopAuto);
    rotator.addEventListener('mouseleave', startAuto);
    rotator.addEventListener('focusin', stopAuto);
    rotator.addEventListener('focusout', function (e) {
      if (!rotator.contains(e.relatedTarget)) startAuto();
    });

    showPanel(0);
    startAuto();
  }
})();
