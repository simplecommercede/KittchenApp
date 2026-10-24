// Hero-Slider: automatischer Wechsel, Pfeile, Punkte, Tastatur, Wischen.
(() => {
  const hero = document.querySelector('.hero');
  if (!hero) return;
  const slides = [...hero.querySelectorAll('.slide')];
  const dotsBox = hero.querySelector('.dots');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const interval = 6000;
  let current = 0;
  let timer = null;

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
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
      slide.querySelectorAll('a, button').forEach(el => { el.tabIndex = active ? 0 : -1; });
    });
    dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === current)));
  }

  function stop() { clearInterval(timer); timer = null; }
  function restart() {
    stop();
    if (!reduceMotion) timer = setInterval(() => show(current + 1), interval);
  }

  hero.querySelector('.prev').addEventListener('click', () => { show(current - 1); restart(); });
  hero.querySelector('.next').addEventListener('click', () => { show(current + 1); restart(); });
  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', restart);
  hero.addEventListener('focusin', stop);
  hero.addEventListener('focusout', restart);
  hero.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') { show(current - 1); restart(); }
    if (e.key === 'ArrowRight') { show(current + 1); restart(); }
  });

  let touchX = null;
  hero.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
  hero.addEventListener('touchend', e => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) { show(current + (dx < 0 ? 1 : -1)); restart(); }
    touchX = null;
  });

  document.addEventListener('visibilitychange', () => (document.hidden ? stop() : restart()));

  show(0);
  restart();
})();
