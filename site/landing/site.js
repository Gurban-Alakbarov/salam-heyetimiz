(() => {
  const text = JSON.parse(document.getElementById('site-texts').textContent);

  // Old links such as /#services: each page has its own address now.
  const hash = location.hash.slice(1);
  if (/^[a-z-]+$/.test(hash) && !document.getElementById(hash)) {
    const link = document.querySelector(`a[href$="/${hash}"]`);
    if (link) location.replace(link.href);
  }

  // Menu labels can be edited and differ by language: when they do not fit, use the phone menu.
  const root = document.documentElement;
  const bar = document.querySelector('.topbar-inner');
  const fitMenu = () => {
    root.classList.remove('compact-menu');
    root.classList.toggle('compact-menu', bar.getBoundingClientRect().left + bar.scrollWidth > root.clientWidth);
  };
  fitMenu();
  window.addEventListener('resize', fitMenu);
  document.fonts?.ready.then(fitMenu);

  const menu = document.querySelector('.mobile-menu');
  const toggle = document.querySelector('.nav-toggle');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const open = menu.hasAttribute('hidden');
      if (open) menu.removeAttribute('hidden');
      else menu.setAttribute('hidden', '');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? text.menu_close : text.menu_open);
    });
    menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => {
      menu.setAttribute('hidden', '');
      toggle.setAttribute('aria-expanded', 'false');
    }));
  }

  const toast = document.getElementById('toast');
  let timer;
  function message(s) {
    if (!toast) return;
    toast.textContent = s;
    toast.classList.add('show');
    clearTimeout(timer);
    timer = setTimeout(() => toast.classList.remove('show'), 3000);
  }

  document.querySelectorAll('[data-demo-pult]').forEach((b) => b.addEventListener('click', () => {
    const note = document.querySelector('.mock-response');
    if (note) {
      note.hidden = false;
      setTimeout(() => { note.hidden = true; }, 2800);
    }
    message(text.msg_demo);
  }));

  const form = document.getElementById('request-form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('button[type=submit]');
      const body = Object.fromEntries(new FormData(form).entries());
      body.locale = document.documentElement.lang || 'az';
      button.disabled = true;
      try {
        const r = await fetch('/v1/site/contact', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(body),
        });
        if (r.status === 429) { message(text.msg_busy); return; }
        if (!r.ok) { message(text.msg_failed); return; }
        form.reset();
        form.hidden = true;
        const done = document.getElementById('request-result');
        done.hidden = false;
        done.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (_) {
        message(text.msg_offline);
      } finally {
        button.disabled = false;
      }
    });
  }
})();
