(function () {
  const slides = Array.from(document.querySelectorAll('.recap-slide'));
  if (!slides.length) return;

  let current = 0;
  const progressBar = document.getElementById('recap-progress');

  if (progressBar) {
    slides.forEach(() => {
      const seg = document.createElement('div');
      seg.className = 'recap-progress-seg';
      seg.innerHTML = '<span></span>';
      progressBar.appendChild(seg);
    });
  }

  function animateCount(el) {
    const target = parseFloat(el.dataset.count);
    const suffix = el.dataset.suffix || '';
    if (isNaN(target)) return;
    const isInt = Number.isInteger(target);
    const duration = 700;
    const start = performance.now();

    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = target * eased;
      el.textContent = (isInt ? Math.round(value) : value.toFixed(1)) + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function showSlide(idx) {
    slides.forEach((s, i) => s.classList.toggle('active', i === idx));

    if (progressBar) {
      Array.from(progressBar.children).forEach((seg, i) => {
        seg.classList.toggle('done', i < idx);
        seg.classList.toggle('current', i === idx);
      });
    }

    const numberEl = slides[idx].querySelector('.recap-number');
    if (numberEl && !numberEl.dataset.animated) {
      numberEl.dataset.animated = '1';
      animateCount(numberEl);
    }

    current = idx;
  }

  function next() {
    if (current < slides.length - 1) showSlide(current + 1);
  }
  function prev() {
    if (current > 0) showSlide(current - 1);
  }

  const nextBtn = document.querySelector('.recap-next');
  const prevBtn = document.querySelector('.recap-prev');
  if (nextBtn) nextBtn.addEventListener('click', next);
  if (prevBtn) prevBtn.addEventListener('click', prev);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });

  slides.forEach((slide) => {
    slide.addEventListener('click', (e) => {
      if (e.target.closest('a, button, select')) return;
      const rect = slide.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      if (clickX < rect.width / 2) prev();
      else next();
    });
  });

  showSlide(0);
})();