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

/* ---------- Notifications bell ---------- */

const bells = document.querySelectorAll('[data-bell]');
const panel = document.getElementById('notif-panel');
const notifList = document.querySelector('[data-notif-list]');
const notifStatus = document.querySelector('[data-notif-status]');
let lastUnread = null;

const notifRequest = async (path, body) => {
  const response = await fetch(`${baseUrl}/api/${path}`, {
    method: body ? 'POST' : 'GET',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    credentials: 'same-origin',
    body: body ? JSON.stringify(body) : undefined,
  });
  return response.json();
};

const renderNotifications = (data) => {
  bells.forEach((bell) => {
    const badge = bell.querySelector('[data-bell-badge]');
    badge.hidden = data.unread === 0;
    badge.textContent = data.unread > 9 ? '9+' : String(data.unread);
    bell.setAttribute('aria-label', data.unread ? `Notifications, ${data.unread} unread` : 'Notifications');
  });

  if (lastUnread !== null && data.unread > lastUnread && notifStatus) {
    notifStatus.textContent = 'You have a new notification.';
  }
  lastUnread = data.unread;

  if (!notifList) return;
  notifList.textContent = '';

  if (data.items.length === 0) {
    const empty = document.createElement('li');
    empty.className = 'notif-empty';
    empty.textContent = 'No notifications yet.';
    notifList.append(empty);
    return;
  }

  data.items.forEach((item) => {
    const li = document.createElement('li');
    li.className = item.is_read ? 'notif-item' : 'notif-item is-unread';
    const button = document.createElement('button');
    button.type = 'button';
    const message = document.createElement('span');
    message.textContent = item.message;
    const time = document.createElement('small');
    time.textContent = item.ago;
    button.append(message, time);
    button.addEventListener('click', async () => {
      if (!item.is_read) {
        await notifRequest('notification-read.php', { id: item.id });
        refreshNotifications();
      }
    });
    li.append(button);
    notifList.append(li);
  });
};

async function refreshNotifications() {
  try {
    const data = await notifRequest('notifications.php');
    if (data.ok) renderNotifications(data);
  } catch (error) {
    // Network problem: try again on the next refresh
  }
}

const setPanelOpen = (open) => {
  if (!panel) return;
  panel.hidden = !open;
  bells.forEach((bell) => bell.setAttribute('aria-expanded', String(open)));
  if (open) refreshNotifications();
};

if (bells.length && panel) {
  bells.forEach((bell) => bell.addEventListener('click', (event) => {
    event.stopPropagation();
    setPanelOpen(panel.hidden);
  }));
  document.querySelector('[data-notif-close]')?.addEventListener('click', () => setPanelOpen(false));
  document.querySelector('[data-notif-all]')?.addEventListener('click', async () => {
    await notifRequest('notification-read.php', { all: true });
    refreshNotifications();
  });
  panel.addEventListener('click', (event) => event.stopPropagation());
  document.addEventListener('click', () => setPanelOpen(false));
  document.addEventListener('keydown', ({ key }) => {
    if (key === 'Escape') setPanelOpen(false);
  });

  refreshNotifications();
  setInterval(() => { if (!document.hidden) refreshNotifications(); }, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshNotifications(); });
  window.addEventListener('focus', refreshNotifications);
}
