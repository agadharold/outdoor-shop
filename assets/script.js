// mobile menu
const menuBtn = document.getElementById('menu-btn');
const catnav = document.getElementById('catnav');
if (menuBtn && catnav) {
  menuBtn.addEventListener('click', () => {
    const open = catnav.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

// close account dropdown when clicking elsewhere
document.addEventListener('click', (e) => {
  document.querySelectorAll('details.dropdown[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});

// flash message auto-hide
const flashEl = document.querySelector('.flash');
if (flashEl) setTimeout(() => { flashEl.classList.add('hide'); setTimeout(() => flashEl.remove(), 400); }, 3500);

// quantity steppers (+ / -); the cart form updates itself after a short pause
document.querySelectorAll('.stepper').forEach((st) => {
  const input = st.querySelector('input');
  const form = st.closest('form[data-autosubmit]');
  let timer;
  const queue = () => { if (form) { clearTimeout(timer); timer = setTimeout(() => form.requestSubmit(), 700); } };
  st.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => {
    const min = input.min !== '' ? +input.min : 0;
    const max = input.max !== '' ? +input.max : Infinity;
    input.value = Math.min(max, Math.max(min, (+input.value || 0) + (+b.dataset.step)));
    queue();
  }));
  input.addEventListener('change', queue);
});
