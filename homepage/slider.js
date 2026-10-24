// Hero-Slider: automatischer Wechsel, Pfeile, Punkte, Tastatur, Wischen.
// Jede Folie setzt data-mood am Hero; daran hängen die Farben der gezeichneten Landschaft.
(() => {
  const hero = document.querySelector('.hero');
  if (!hero) return;
  const slides = [...hero.querySelectorAll('.slide')];
  const dotsBox = hero.querySelector('.dots');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const interval = 7000;
  let current = 0;
  let timer = null;

  hero.style.setProperty('--interval', `${interval}ms`);

  const dots = slides.map((_, i) => {
    const dot = document.createElement('button');
    dot.className = 'dot';
    dot.type = 'button';
    dot.setAttribute('aria-label', `Bild ${i + 1} von ${slides.length}`);
    dot.addEventListener('click', () => { show(i); restart(); });
    dotsBox.appendChild(dot);
    return dot;
  });

  function show(index) {
    current = (index + slides.length) % slides.length;
    hero.dataset.mood = String(current + 1);
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
      slide.querySelectorAll('a, button').forEach(el => { el.tabIndex = active ? 0 : -1; });
    });
    dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === current)));
    // Fortschrittsbalken neu starten
    if (timer) {
      hero.classList.remove('is-playing');
      void hero.offsetWidth;
      hero.classList.add('is-playing');
    }
  }

  function stop() {
    clearInterval(timer);
    timer = null;
    hero.classList.remove('is-playing');
  }
  function restart() {
    stop();
    if (reduceMotion) return;
    timer = setInterval(() => show(current + 1), interval);
    hero.classList.add('is-playing');
  }

  hero.querySelector('.prev').addEventListener('click', () => { show(current - 1); restart(); });
  hero.querySelector('.next').addEventListener('click', () => { show(current + 1); restart(); });
  hero.addEventListener('mouseenter', () => { if (window.matchMedia('(hover: hover)').matches) stop(); });
  hero.addEventListener('mouseleave', restart);
  hero.addEventListener('focusin', stop);
  hero.addEventListener('focusout', e => { if (!hero.contains(e.relatedTarget)) restart(); });
  hero.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') { show(current - 1); restart(); }
    if (e.key === 'ArrowRight') { show(current + 1); restart(); }
  });

  let touchX = null;
  let touchY = null;
  hero.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; touchY = e.touches[0].clientY; }, { passive: true });
  hero.addEventListener('touchend', e => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    const dy = e.changedTouches[0].clientY - touchY;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) { show(current + (dx < 0 ? 1 : -1)); restart(); }
    touchX = touchY = null;
  });

  document.addEventListener('visibilitychange', () => (document.hidden ? stop() : restart()));

  show(0);
  restart();
})();
