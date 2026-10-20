/**
 * Landing page navigation: the menu button on small screens.
 */

const toggle = document.querySelector('[data-nav-toggle]');
const menu = document.getElementById('site-menu');

if (toggle && menu) {
  const setOpen = (open) => {
    menu.dataset.open = String(open);
    toggle.setAttribute('aria-expanded', String(open));
  };

  toggle.addEventListener('click', () => setOpen(menu.dataset.open !== 'true'));

  // Close after choosing a link
  menu.addEventListener('click', ({ target }) => {
    if (target.closest('a')) setOpen(false);
  });

  document.addEventListener('keydown', ({ key }) => {
    if (key === 'Escape') setOpen(false);
  });

  // Reset when the window grows to the desktop layout
  window.matchMedia('(min-width: 860px)').addEventListener('change', ({ matches }) => {
    if (matches) setOpen(false);
  });
}
