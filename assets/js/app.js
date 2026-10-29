/**
 * Behavior shared by signed-in pages: the Sign out button and the side
 * navigation drawer used on tablets and phones.
 */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const baseUrl = document.body.dataset.baseUrl ?? '';

/* ---------- Sign out ---------- */

document.querySelectorAll('[data-logout]').forEach((button) => {
  button.addEventListener('click', async () => {
    button.disabled = true;

    try {
      const response = await fetch(`${baseUrl}/api/logout.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: '{}',
      });
      const data = await response.json();
      window.location.href = data.redirect ?? `${baseUrl}/pages/login.php`;
    } catch (error) {
      button.disabled = false;
    }
  });
});

/* ---------- Side navigation drawer (small screens) ---------- */

const toggle = document.querySelector('[data-sidebar-toggle]');
const backdrop = document.querySelector('.sidebar-backdrop');

const setSidebarOpen = (open) => {
  document.body.classList.toggle('sidebar-open', open);
  toggle?.setAttribute('aria-expanded', String(open));
  toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  if (backdrop) backdrop.hidden = !open;
};

toggle?.addEventListener('click', () => setSidebarOpen(!document.body.classList.contains('sidebar-open')));
backdrop?.addEventListener('click', () => setSidebarOpen(false));
document.querySelectorAll('.sidebar a').forEach((link) => link.addEventListener('click', () => setSidebarOpen(false)));
document.addEventListener('keydown', ({ key }) => {
  if (key === 'Escape') setSidebarOpen(false);
});
window.matchMedia('(min-width: 1024px)').addEventListener('change', ({ matches }) => {
  if (matches) setSidebarOpen(false);
});
