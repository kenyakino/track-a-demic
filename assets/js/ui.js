/**
 * UI helpers: safe HTML templates, date formatting, and toast messages.
 */

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ESCAPES[char]);

/** Marks a string as trusted HTML (built by the html tag below). */
class SafeHtml {
  constructor(value) {
    this.value = value;
  }
}

const toHtml = (value) => {
  if (value instanceof SafeHtml) return value.value;
  if (Array.isArray(value)) return value.map(toHtml).join('');
  return escapeHtml(value);
};

/**
 * Tagged template that escapes every inserted value, so text typed by users
 * can never become HTML. Nest html`` results freely; arrays are joined.
 */
export const html = (strings, ...values) =>
  new SafeHtml(strings.reduce((out, part, i) => out + part + (i < values.length ? toHtml(values[i]) : ''), ''));

/** Puts a template result into an element. */
export const mount = (element, safe) => {
  element.innerHTML = safe.value;
};

const dateFormat = new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });

/** "2026-11-06" or "2026-11-06 08:30:00" becomes "Nov 6, 2026". */
export const formatDate = (value) => {
  if (!value) return '';
  const [year, month, day] = String(value).split(' ')[0].split('-').map(Number);
  return dateFormat.format(new Date(year, month - 1, day));
};

/** Shows a short message in the corner. type is "success" or "error". */
export const toast = (message, type = 'success') => {
  const host = document.querySelector('[data-toasts]');
  if (!host) return;

  const item = document.createElement('p');
  item.className = `toast toast-${type}`;
  item.textContent = message;
  host.append(item);

  setTimeout(() => item.remove(), 4500);
};
