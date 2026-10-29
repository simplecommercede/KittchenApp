// Seitenlogik: Menü, Kopfzeile, Tisch reservieren, Öffnungsstatus, Einblenden, Speisekarten-Ansicht mit Großansicht.
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
    window.matchMedia('(min-width: 1200px)').addEventListener('change', e => { if (e.matches) setOpen(false); });
  }

  // Kopfzeile wird deckend, sobald die Seite gescrollt ist (sonst läge sie auf dem Foto)
  const header = document.querySelector('[data-header]');
  const hero = document.querySelector('.hero');
  if (header && hero) {
    const update = () => header.classList.toggle('is-solid', window.scrollY > 8);
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  }

  // Tisch reservieren: Fenster mit Anruf und E-Mail; ohne <dialog> führt der Link zur Kontaktseite
  const RESERVE_PHONE = '+4951948789828';
  const RESERVE_PHONE_TEXT = '05194 878 98 28';
  const RESERVE_MAIL = 'info@zumstimbeck.de';
  const reserveMail = `mailto:${RESERVE_MAIL}?subject=${encodeURIComponent('Tischreservierung')}&body=${encodeURIComponent('Hallo Stimbeck-Team,\n\nich möchte gern einen Tisch reservieren:\n\nDatum:\nUhrzeit:\nPersonen:\nName:\nTelefon:\n\nViele Grüße\n')}`;
  document.querySelectorAll('a[href^="mailto:"][data-reserve-mail]').forEach(a => { a.href = reserveMail; });
  let reserveBox = null;
  function openReserve() {
    if (!reserveBox) {
      reserveBox = document.createElement('dialog');
      reserveBox.className = 'reserve-dialog';
      reserveBox.setAttribute('aria-labelledby', 'reserve-title');
      reserveBox.innerHTML = `
        <div class="reserve-dialog-inner">
          <button class="reserve-close" type="button" aria-label="Schließen">×</button>
          <h2 id="reserve-title">Tisch <em>reservieren</em></h2>
          <p>Ruft uns an oder schreibt uns. Nennt uns bitte Datum, Uhrzeit und Personenzahl.</p>
          <div class="reserve-actions">
            <a class="btn btn-green" href="tel:${RESERVE_PHONE}">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.2 15.2 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.6a1 1 0 0 1-.25 1z"/></svg>
              <span>Anrufen<small>${RESERVE_PHONE_TEXT}</small></span>
            </a>
            <a class="btn btn-outline" href="${reserveMail}">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg>
              <span>E-Mail schreiben<small>${RESERVE_MAIL}</small></span>
            </a>
          </div>
          <p class="note">Telefonisch erreicht ihr uns Donnerstag bis Sonntag ab 17 Uhr.</p>
        </div>`;
      document.body.appendChild(reserveBox);
      reserveBox.querySelector('.reserve-close').addEventListener('click', () => reserveBox.close());
      reserveBox.addEventListener('click', e => { if (e.target === reserveBox) reserveBox.close(); });
    }
    reserveBox.showModal();
  }
  if (typeof HTMLDialogElement === 'function') {
    document.querySelectorAll('[data-reserve]').forEach(link => link.addEventListener('click', e => {
      e.preventDefault();
      if (document.body.classList.contains('nav-open') && toggle) toggle.click();
      openReserve();
    }));
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
    const today = document.querySelector(`[data-hours] tr[data-day~="${now.day}"]`);
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

  // Speisekarte: Seiten aus karte/karten.js zum Blättern anzeigen (ohne Download-Links)
  const viewer = document.querySelector('[data-karten]');
  const karten = Array.isArray(window.STIMBECK_KARTEN) ? window.STIMBECK_KARTEN.filter(k => k.seiten && k.seiten.length) : [];
  if (viewer && !karten.length) {
    viewer.innerHTML = '<p class="karte-empty">Die Speisekarte wird gerade aktualisiert. Fragt gern telefonisch nach: 05194 878 98 28.</p>';
  }
  if (viewer && karten.length) {
    const tabs = viewer.querySelector('[data-karte-tabs]');
    const track = viewer.querySelector('[data-karte-track]');
    const count = viewer.querySelector('[data-karte-count]');
    const pageNav = viewer.querySelector('[data-karte-pages]');
    const prev = viewer.querySelector('[data-karte-prev]');
    const next = viewer.querySelector('[data-karte-next]');
    const box = document.querySelector('[data-lightbox]');
    let karte = 0;
    let page = 0;

    // Bilder nicht per Rechtsklick, Ziehen oder langem Tippen speichern lassen
    const protect = img => {
      img.draggable = false;
      img.addEventListener('contextmenu', e => e.preventDefault());
    };

    const pageEls = () => [...track.children];
    const step = () => {
      const els = pageEls();
      return els.length > 1 ? els[1].offsetLeft - els[0].offsetLeft : track.clientWidth;
    };

    function updateBar() {
      const total = karten[karte].seiten.length;
      count.textContent = `Seite ${page + 1} von ${total}`;
      [...pageNav.children].forEach((b, i) => b.setAttribute('aria-current', String(i === page)));
      prev.disabled = page === 0;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    }

    function goTo(index, smooth = true) {
      const total = karten[karte].seiten.length;
      page = Math.max(0, Math.min(total - 1, index));
      track.scrollTo({ left: step() * page, behavior: smooth ? 'smooth' : 'auto' });
      updateBar();
    }

    function showKarte(index) {
      karte = index;
      page = 0;
      const { titel, seiten } = karten[karte];
      track.innerHTML = '';
      pageNav.innerHTML = '';
      seiten.forEach((seite, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'karte-page';
        btn.setAttribute('aria-label', `${titel}, Seite ${i + 1} vergrößern`);
        const img = document.createElement('img');
        img.src = seite.src;
        img.width = seite.w;
        img.height = seite.h;
        img.alt = `${titel}, Seite ${i + 1}`;
        img.loading = i < 2 ? 'eager' : 'lazy';
        img.decoding = 'async';
        protect(img);
        btn.appendChild(img);
        btn.addEventListener('click', () => openLightbox(i));
        track.appendChild(btn);

        const dot = document.createElement('button');
        dot.type = 'button';
        dot.textContent = String(i + 1);
        dot.setAttribute('aria-label', `Seite ${i + 1}`);
        dot.addEventListener('click', () => goTo(i));
        pageNav.appendChild(dot);
      });
      if (tabs) {
        [...tabs.children].forEach((t, i) => t.setAttribute('aria-selected', String(i === karte)));
      }
      track.scrollLeft = 0;
      updateBar();
    }

    if (karten.length > 1) {
      karten.forEach((k, i) => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.setAttribute('role', 'tab');
        tab.textContent = k.titel;
        tab.addEventListener('click', () => showKarte(i));
        tabs.appendChild(tab);
      });
      tabs.hidden = false;
    }

    prev.addEventListener('click', () => goTo(page - 1));
    next.addEventListener('click', () => goTo(page + 1));
    track.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(page - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); goTo(page + 1); }
    });
    let scrollTimer = null;
    track.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(() => {
        page = Math.round(track.scrollLeft / step());
        updateBar();
      }, 80);
    }, { passive: true });
    window.addEventListener('resize', () => goTo(page, false));

    // Großansicht mit Blättern
    let boxPage = 0;
    function renderLightbox() {
      const { titel, seiten } = karten[karte];
      const img = box.querySelector('[data-lightbox-img]');
      img.src = seiten[boxPage].src;
      img.alt = `${titel}, Seite ${boxPage + 1}`;
      box.querySelector('[data-lightbox-title]').textContent = `${titel} · Seite ${boxPage + 1} von ${seiten.length}`;
      box.querySelector('[data-lightbox-prev]').disabled = boxPage === 0;
      box.querySelector('[data-lightbox-next]').disabled = boxPage === seiten.length - 1;
      box.querySelector('.lightbox-body').scrollTop = 0;
    }
    function openLightbox(index) {
      if (!box || typeof box.showModal !== 'function') return;
      boxPage = index;
      renderLightbox();
      box.showModal();
    }
    if (box) {
      protect(box.querySelector('[data-lightbox-img]'));
      const move = d => {
        const total = karten[karte].seiten.length;
        boxPage = Math.max(0, Math.min(total - 1, boxPage + d));
        renderLightbox();
      };
      box.querySelector('[data-lightbox-prev]').addEventListener('click', () => move(-1));
      box.querySelector('[data-lightbox-next]').addEventListener('click', () => move(1));
      box.querySelector('[data-lightbox-close]').addEventListener('click', () => box.close());
      box.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') move(-1);
        if (e.key === 'ArrowRight') move(1);
      });
      box.addEventListener('click', e => { if (e.target === box || e.target.classList.contains('lightbox-body')) box.close(); });
      box.addEventListener('close', () => goTo(boxPage, false));
    }

    showKarte(0);
  }
})();
