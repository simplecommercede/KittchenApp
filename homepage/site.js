// Seitenlogik: Menü, Kopfzeile, Öffnungsstatus, Einblenden, Karten-Dateien, Großansicht.
(() => {
  document.documentElement.classList.add('js');

  // Handy-Menü
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    const setOpen = open => {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.querySelector('.sr-only').textContent = open ? 'Menü schließen' : 'Menü';
    };
    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
    nav.addEventListener('click', e => { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setOpen(false); toggle.focus(); }
    });
    window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { if (e.matches) setOpen(false); });
  }

  // Kopfzeile wird beim Scrollen über dem Hero deckend
  const header = document.querySelector('[data-header]');
  const hero = document.querySelector('.hero');
  if (header && hero) {
    const update = () => header.classList.toggle('is-solid', window.scrollY > hero.offsetHeight - header.offsetHeight - 10);
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  }

  // Öffnungsstatus nach deutscher Zeit: Do–So 17–22 Uhr
  const OPEN_DAYS = [4, 5, 6, 0];
  const OPEN_FROM = 17;
  const OPEN_TO = 22;
  const DAY_NAMES = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

  function berlinNow() {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Europe/Berlin', weekday: 'short', hour: 'numeric', minute: 'numeric', hourCycle: 'h23'
    }).formatToParts(new Date());
    const get = type => parts.find(p => p.type === type).value;
    const day = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(get('weekday'));
    return { day, hour: Number(get('hour')) + Number(get('minute')) / 60 };
  }

  function statusText({ day, hour }) {
    const openToday = OPEN_DAYS.includes(day);
    if (openToday && hour >= OPEN_FROM && hour < OPEN_TO) {
      return { open: true, text: hour < 21 ? 'Jetzt geöffnet · warme Küche bis 21 Uhr' : 'Jetzt geöffnet bis 22 Uhr' };
    }
    if (openToday && hour < OPEN_FROM) return { open: false, text: 'Heute ab 17 Uhr geöffnet' };
    for (let i = 1; i <= 7; i++) {
      const next = (day + i) % 7;
      if (OPEN_DAYS.includes(next)) {
        return { open: false, text: i === 1 ? 'Morgen ab 17 Uhr geöffnet' : `Wieder geöffnet ab ${DAY_NAMES[next]}, 17 Uhr` };
      }
    }
    return null;
  }

  try {
    const now = berlinNow();
    const status = document.querySelector('[data-status]');
    const info = statusText(now);
    if (status && info) {
      status.querySelector('[data-status-text]').textContent = info.text;
      status.classList.toggle('is-open', info.open);
      status.hidden = false;
    }
    const today = document.querySelector(`[data-hours] tr[data-day="${now.day}"]`);
    if (today) today.classList.add('is-today');
  } catch (e) {
    // Ohne Intl-Zeitzonen bleibt der Status einfach ausgeblendet.
  }

  // Abschnitte beim Scrollen einblenden
  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    revealItems.forEach(el => io.observe(el));
  } else {
    revealItems.forEach(el => el.classList.add('is-visible'));
  }

  // Großansicht für Kartenbilder
  const box = document.querySelector('[data-lightbox]');
  function openLightbox(src, title) {
    if (!box || typeof box.showModal !== 'function') { window.open(src, '_blank', 'noopener'); return; }
    box.querySelector('[data-lightbox-img]').src = src;
    box.querySelector('[data-lightbox-img]').alt = title;
    box.querySelector('[data-lightbox-title]').textContent = title;
    box.querySelector('[data-lightbox-download]').href = src;
    box.showModal();
  }
  if (box) {
    box.querySelector('[data-lightbox-close]').addEventListener('click', () => box.close());
    box.addEventListener('click', e => { if (e.target === box || e.target.classList.contains('lightbox-body')) box.close(); });
    box.addEventListener('close', () => { box.querySelector('[data-lightbox-img]').removeAttribute('src'); });
  }

  // Karten-Dateien: erste vorhandene Datei aus data-files verwenden (PDF, PNG oder JPG)
  const formatSize = bytes => bytes > 1048576
    ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;

  async function findFile(candidates) {
    for (const url of candidates) {
      try {
        const res = await fetch(url, { method: 'HEAD', cache: 'no-cache' });
        if (res.ok) return { url, size: Number(res.headers.get('content-length')) || 0 };
      } catch (e) {
        return null; // z. B. bei file:// – dann bleibt das HTML, wie es ist
      }
    }
    return false;
  }

  document.querySelectorAll('[data-menu-file]').forEach(async link => {
    const title = link.querySelector('strong').textContent;
    const found = await findFile(link.dataset.files.split(/\s+/));
    if (found === null) return;
    if (found === false) { if (link.hasAttribute('data-optional')) link.hidden = true; return; }

    const isImage = /\.(png|jpe?g|webp)$/i.test(found.url);
    const type = found.url.split('.').pop().toUpperCase().replace('JPEG', 'JPG');
    link.href = found.url;
    link.hidden = false;

    const icon = link.querySelector('.menu-file-icon');
    icon.dataset.type = type.toLowerCase();
    if (isImage) {
      icon.innerHTML = '';
      const img = document.createElement('img');
      img.src = found.url; img.alt = ''; img.loading = 'lazy';
      icon.appendChild(img);
      link.removeAttribute('target');
      link.addEventListener('click', e => { e.preventDefault(); openLightbox(found.url, title); });
    } else if (!icon.querySelector('img')) {
      icon.textContent = type;
    }

    const meta = link.querySelector('[data-file-meta]');
    const base = meta.dataset.base || meta.textContent.replace(/^(PDF|PNG|JPG)\s·\s/, '');
    meta.dataset.base = base;
    meta.textContent = [type, base, found.size ? formatSize(found.size) : ''].filter(Boolean).join(' · ');
  });
})();
