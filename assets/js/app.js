/**
 * Behavior shared by signed-in pages: the Sign out button, the side
 * navigation drawer used on tablets and phones, and the collapsed (icon
 * only) side navigation on larger screens.
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

/* ---------- Collapsed side navigation (large screens) ---------- */

const root = document.documentElement;
const collapseButton = document.querySelector('[data-sidebar-collapse]');

const showCollapseState = () => {
  const collapsed = root.dataset.sidebar === 'collapsed';
  const label = collapsed ? 'Expand menu' : 'Collapse menu';

  collapseButton?.setAttribute('aria-expanded', String(!collapsed));
  collapseButton?.setAttribute('aria-label', label);
  collapseButton?.setAttribute('title', label);
};

collapseButton?.addEventListener('click', () => {
  const collapsed = root.dataset.sidebar !== 'collapsed';

  if (collapsed) root.dataset.sidebar = 'collapsed';
  else delete root.dataset.sidebar;

  try {
    localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'expanded');
  } catch (error) {
    // Storage can be blocked; the menu still collapses for this visit
  }

  showCollapseState();
});

showCollapseState();
