/* ========= Dark-mode toggle (unchanged) ========= */
const toggle = document.getElementById('theme-toggle');
const root   = document.documentElement;
const stored = localStorage.getItem('theme');
if (stored === 'dark' || (!stored && matchMedia('(prefers-color-scheme: dark)').matches)) {
  root.classList.add('dark');
  if (toggle) toggle.checked = true;
}
toggle?.addEventListener('change', () => {
  if (toggle.checked) {
    root.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    root.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
});

/* ========= Video-modal logic ========= */
document.addEventListener('DOMContentLoaded', () => {
  const modal  = document.getElementById('videoModal');
  const frame  = document.getElementById('videoFrame');
  const close  = modal?.querySelector('.close');

  if (!modal || !frame || !close) return;        // page has no modal → skip

  document.querySelectorAll('.video-trigger').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const id = link.dataset.video;
      frame.src = `https://www.youtube.com/embed/${id}?autoplay=1&rel=0`;
      modal.classList.add('active');
    });
  });

  function closeModal() {
    modal.classList.remove('active');
    frame.src = '';            // stop playback
  }
  close.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
});
