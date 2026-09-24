document.addEventListener('DOMContentLoaded', function () {
  const main = document.querySelector('.presslms-theme-main');
  if (!main) return;
  const headers = Array.from(document.querySelectorAll('header, [data-elementor-type="header"], #masthead'))
    .filter(header => !main.contains(header));
  let scheduled = false;

  function update() {
    scheduled = false;
    const mainTop = main.getBoundingClientRect().top + window.scrollY;
    let offset = 0;
    headers.forEach(function (header) {
      const style = getComputedStyle(header);
      if (style.position !== 'absolute' && style.position !== 'fixed') return;
      const box = header.getBoundingClientRect();
      if (box.width < window.innerWidth / 2 || box.height <= 0) return;
      const bottom = box.bottom + (style.position === 'absolute' ? window.scrollY : 0);
      offset = Math.max(offset, bottom - mainTop);
    });
    main.style.setProperty('--presslms-header-offset', Math.max(0, Math.ceil(offset)) + 'px');
  }
  function schedule() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(update);
  }
  if (window.ResizeObserver) {
    const observer = new ResizeObserver(schedule);
    headers.forEach(header => observer.observe(header));
  }
  window.addEventListener('resize', schedule);
  window.addEventListener('load', schedule);
  schedule();
});
