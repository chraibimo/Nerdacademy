/* ─── NeuralPath — Main JavaScript ──────────────────────────────────────────
   Neural network canvas · Scroll effects · Interactions · Animations
   ─────────────────────────────────────────────────────────────────────────── */

'use strict';

// ─── 1. Neural Network Canvas Animation ───────────────────────────────────────
(function () {
  const canvas = document.getElementById('neuralCanvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  let W, H, nodes, animId;
  let mouse = { x: -999, y: -999 };

  const COLORS = ['#7c3aed', '#0ea5e9', '#10b981', '#ec4899', '#f59e0b'];
  const NODE_COUNT  = window.innerWidth < 768 ? 40 : 80;
  const CONN_DIST   = 160;
  const MOUSE_DIST  = 180;

  function resize() {
    W = canvas.width  = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
    if (!nodes) initNodes();
  }

  function initNodes() {
    nodes = Array.from({ length: NODE_COUNT }, () => ({
      x:    Math.random() * W,
      y:    Math.random() * H,
      vx:   (Math.random() - .5) * .6,
      vy:   (Math.random() - .5) * .6,
      r:    Math.random() * 2 + 1,
      color: COLORS[Math.floor(Math.random() * COLORS.length)],
      pulse: Math.random() * Math.PI * 2,
    }));
  }

  function dist(a, b) {
    const dx = a.x - b.x, dy = a.y - b.y;
    return Math.sqrt(dx * dx + dy * dy);
  }

  function draw(ts) {
    ctx.clearRect(0, 0, W, H);

    // Update
    for (const n of nodes) {
      n.x += n.vx;
      n.y += n.vy;
      n.pulse += .025;

      // Bounce
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
      n.x = Math.max(0, Math.min(W, n.x));
      n.y = Math.max(0, Math.min(H, n.y));

      // Mouse attraction (gentle)
      const md = dist(n, mouse);
      if (md < MOUSE_DIST) {
        const force = (MOUSE_DIST - md) / MOUSE_DIST * .015;
        n.vx += (mouse.x - n.x) * force;
        n.vy += (mouse.y - n.y) * force;
        // Speed cap
        const speed = Math.sqrt(n.vx * n.vx + n.vy * n.vy);
        if (speed > 2.5) { n.vx /= speed; n.vy /= speed; n.vx *= 2.5; n.vy *= 2.5; }
      }
    }

    // Draw connections
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const d = dist(nodes[i], nodes[j]);
        if (d < CONN_DIST) {
          const alpha = (1 - d / CONN_DIST) * .25;
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = `rgba(124,58,237,${alpha})`;
          ctx.lineWidth = .8;
          ctx.stroke();
        }
      }
    }

    // Draw nodes
    for (const n of nodes) {
      const pulseMod = Math.sin(n.pulse) * .5 + .5; // 0–1
      const r = n.r + pulseMod * 1.2;

      // Glow
      const grd = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, r * 4);
      grd.addColorStop(0, n.color + '80');
      grd.addColorStop(1, n.color + '00');
      ctx.beginPath();
      ctx.arc(n.x, n.y, r * 4, 0, Math.PI * 2);
      ctx.fillStyle = grd;
      ctx.fill();

      // Core
      ctx.beginPath();
      ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
      ctx.fillStyle = n.color;
      ctx.fill();
    }

    animId = requestAnimationFrame(draw);
  }

  window.addEventListener('resize', () => {
    cancelAnimationFrame(animId);
    resize();
    draw();
  });

  canvas.closest('section')?.addEventListener('mousemove', e => {
    const rect = canvas.getBoundingClientRect();
    mouse.x = e.clientX - rect.left;
    mouse.y = e.clientY - rect.top;
  });
  canvas.closest('section')?.addEventListener('mouseleave', () => {
    mouse.x = -999; mouse.y = -999;
  });

  resize();
  requestAnimationFrame(draw);
})();

// ─── 2. Navbar scroll effect ─────────────────────────────────────────────────
(function () {
  const nav = document.getElementById('navbar');
  if (!nav) return;

  function update() {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  }
  window.addEventListener('scroll', update, { passive: true });
  update();
})();

// ─── 3. Mobile nav toggle ─────────────────────────────────────────────────────
(function () {
  const nav = document.getElementById('navbar');
  const toggle = document.getElementById('navToggle');
  const mobile = document.getElementById('navMobile');
  if (!toggle || !mobile) return;

  toggle.addEventListener('click', () => {
    const open = mobile.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open);
    // Animate bars
    const bars = toggle.querySelectorAll('span');
    if (open) {
      bars[0].style.cssText = 'transform:translateY(7px) rotate(45deg)';
      bars[1].style.cssText = 'opacity:0';
      bars[2].style.cssText = 'transform:translateY(-7px) rotate(-45deg)';
    } else {
      bars.forEach(b => b.style.cssText = '');
    }
  });

  // Close on outside click
  document.addEventListener('click', e => {
    if (nav && !nav.contains(e.target) && mobile.classList.contains('open')) {
      mobile.classList.remove('open');
      toggle.querySelectorAll('span').forEach(b => b.style.cssText = '');
    }
  });
})();

// ─── 4. Profile dropdown toggle ───────────────────────────────────────────────
(function () {
  const userArea = document.getElementById('navUserArea');
  const userToggle = document.getElementById('navUserToggle');
  const userDropdown = document.getElementById('navUserDropdown');
  const userCaret = userToggle ? userToggle.querySelector('.nav-user-caret') : null;

  if (!userArea || !userToggle || !userDropdown) return;
  if (userToggle.getAttribute('data-dropdown-bound') === '1') return;
  userToggle.setAttribute('data-dropdown-bound', '1');

  function setOpen(open) {
    userDropdown.classList.toggle('open', open);
    userToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (userCaret) userCaret.style.transform = open ? 'rotate(180deg)' : '';
  }

  userToggle.setAttribute('aria-haspopup', 'menu');
  userToggle.setAttribute('aria-expanded', 'false');

  userToggle.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    setOpen(!userDropdown.classList.contains('open'));
  });

  document.addEventListener('click', (e) => {
    if (!userArea.contains(e.target)) setOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setOpen(false);
  });

  userDropdown.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });
})();

// ─── 5. Scroll reveal ─────────────────────────────────────────────────────────
(function () {
  const els = document.querySelectorAll('.reveal, .course-card, .step-card, .testimonial-card, .value-card, .team-card, .stat-item');
  if (!els.length) return;

  // Add reveal class to card-like elements if not already there
  els.forEach((el, i) => {
    if (!el.classList.contains('reveal')) {
      el.classList.add('reveal');
      el.style.transitionDelay = `${(i % 6) * 0.08}s`;
    }
  });

  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
})();

// ─── 6. Course card glow on hover ─────────────────────────────────────────────
(function () {
  document.querySelectorAll('.course-card').forEach(card => {
    const color = card.dataset.color || '#7c3aed';
    card.addEventListener('mouseenter', () => {
      card.style.boxShadow = `0 20px 60px ${color}30, 0 4px 24px rgba(0,0,0,.5)`;
      card.style.borderColor = color + '60';
    });
    card.addEventListener('mouseleave', () => {
      card.style.boxShadow = '';
      card.style.borderColor = '';
    });
  });
})();

// ─── 7. Countdown timer (course page) ─────────────────────────────────────────
(function () {
  const el = document.getElementById('offerTimer');
  if (!el) return;

  // Set expiry 2 days from now (stored in session for realism)
  const key = 'np_offer_expiry';
  let expiry = parseInt(sessionStorage.getItem(key));
  if (!expiry || expiry < Date.now()) {
    expiry = Date.now() + 2 * 24 * 60 * 60 * 1000 + 14 * 60 * 60 * 1000 + 32 * 60 * 1000;
    sessionStorage.setItem(key, expiry);
  }

  function tick() {
    const diff = expiry - Date.now();
    if (diff <= 0) { el.textContent = 'Offer expired'; return; }
    const d  = Math.floor(diff / 86400000);
    const h  = Math.floor((diff % 86400000) / 3600000);
    const m  = Math.floor((diff % 3600000) / 60000);
    const s  = Math.floor((diff % 60000) / 1000);
    el.textContent = `${d}d ${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  }
  tick();
  setInterval(tick, 1000);
})();

// ─── 8. Character counter (contact form) ──────────────────────────────────────
(function () {
  const textarea = document.getElementById('message');
  const counter  = document.getElementById('charCount');
  if (!textarea || !counter) return;

  textarea.addEventListener('input', () => {
    const len = textarea.value.length;
    counter.textContent = len;
    counter.style.color = len > 900 ? '#ef4444' : len > 700 ? '#f59e0b' : '';
    if (len > 1000) textarea.value = textarea.value.slice(0, 1000);
  });
})();

// ─── 9. Smooth scroll for anchor links ───────────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const id = a.getAttribute('href').slice(1);
    const target = document.getElementById(id);
    if (target) {
      e.preventDefault();
      const top = target.getBoundingClientRect().top + scrollY - 90;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  });
});

// ─── 10. Progress bar animation ───────────────────────────────────────────────
(function () {
  const fills = document.querySelectorAll('.pb-fill');
  if (!fills.length) return;

  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.width = e.target.style.width; // trigger repaint
        obs.unobserve(e.target);
      }
    });
  }, { threshold: .5 });

  fills.forEach(f => obs.observe(f));
})();

// ─── 11. Curriculum accordion ────────────────────────────────────────────────
// Already handled by inline onclick in PHP, but add keyboard support
document.querySelectorAll('.curriculum-module').forEach(mod => {
  mod.setAttribute('tabindex', '0');
  mod.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      mod.classList.toggle('open');
    }
  });
});

// ─── 12. FAQ accordion ───────────────────────────────────────────────────────
document.querySelectorAll('.faq-item').forEach(item => {
  item.setAttribute('tabindex', '0');
  item.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      item.classList.toggle('open');
    }
  });
});

// ─── 13. Active nav link highlight on scroll ─────────────────────────────────
(function () {
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link');
  if (!sections.length || !navLinks.length) return;

  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        navLinks.forEach(l => l.classList.remove('active'));
        const active = document.querySelector(`.nav-link[href="#${e.target.id}"]`);
        if (active) active.classList.add('active');
      }
    });
  }, { threshold: .4 });

  sections.forEach(s => obs.observe(s));
})();

// ─── 14. Cursor glow effect ──────────────────────────────────────────────────
(function () {
  const glow = document.createElement('div');
  glow.style.cssText = `
    position:fixed;pointer-events:none;z-index:9999;
    width:300px;height:300px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(124,58,237,.06) 0%, transparent 70%);
    transform:translate(-50%,-50%);
    transition:opacity .3s;
    will-change:transform;
  `;
  document.body.appendChild(glow);

  let tx = 0, ty = 0, cx = 0, cy = 0;
  window.addEventListener('mousemove', e => { tx = e.clientX; ty = e.clientY; }, { passive: true });

  function animGlow() {
    cx += (tx - cx) * .12;
    cy += (ty - cy) * .12;
    glow.style.left = cx + 'px';
    glow.style.top  = cy + 'px';
    requestAnimationFrame(animGlow);
  }
  requestAnimationFrame(animGlow);

  // Hide on mobile
  if (window.matchMedia('(pointer:coarse)').matches) glow.style.display = 'none';
})();

// ─── 15. Ripple effect on buttons ────────────────────────────────────────────
document.querySelectorAll('.btn-primary, .btn-ghost').forEach(btn => {
  btn.addEventListener('click', function(e) {
    const r = document.createElement('span');
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    r.style.cssText = `
      position:absolute;border-radius:50%;
      width:${size}px;height:${size}px;
      left:${e.clientX - rect.left - size/2}px;
      top:${e.clientY - rect.top - size/2}px;
      background:rgba(255,255,255,.15);
      transform:scale(0);animation:ripple .5s linear;
      pointer-events:none;
    `;
    if (!document.getElementById('rippleStyle')) {
      const s = document.createElement('style');
      s.id = 'rippleStyle';
      s.textContent = '@keyframes ripple{to{transform:scale(2.5);opacity:0}}';
      document.head.appendChild(s);
    }
    if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
    btn.style.overflow = 'hidden';
    btn.appendChild(r);
    setTimeout(() => r.remove(), 600);
  });
});

// ─── 15. Page load progress bar ──────────────────────────────────────────────
(function () {
  const bar = document.createElement('div');
  bar.style.cssText = `
    position:fixed;top:0;left:0;height:3px;z-index:9999;
    background:linear-gradient(90deg,#7c3aed,#0ea5e9,#10b981);
    transition:width .3s ease,opacity .3s ease;
    width:0%;
  `;
  document.body.appendChild(bar);

  let w = 0;
  const interval = setInterval(() => {
    w = Math.min(w + Math.random() * 15, 90);
    bar.style.width = w + '%';
  }, 100);

  window.addEventListener('load', () => {
    clearInterval(interval);
    bar.style.width = '100%';
    setTimeout(() => { bar.style.opacity = '0'; }, 400);
    setTimeout(() => bar.remove(), 800);
  });
})();
