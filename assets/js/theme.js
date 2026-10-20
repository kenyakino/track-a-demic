/**
 * Light / dark mode.
 * The inline script in <head> sets the starting theme; this file handles the
 * toggle button and remembers the choice with localStorage.
 */

const root = document.documentElement;
const buttons = [...document.querySelectorAll('[data-theme-toggle]')];

const currentTheme = () => (root.dataset.theme === 'dark' ? 'dark' : 'light');
const otherTheme = () => (currentTheme() === 'dark' ? 'light' : 'dark');

const saveTheme = (theme) => {
  try {
    localStorage.setItem('theme', theme);
  } catch (error) {
    // Storage can be blocked (private mode); the theme still changes for this visit
  }
};

const syncButtons = () => {
  buttons.forEach((button) => button.setAttribute('aria-label', `Switch to ${otherTheme()} mode`));
};

buttons.forEach((button) =>
  button.addEventListener('click', () => {
    const next = otherTheme();
    root.dataset.theme = next;
    saveTheme(next);
    syncButtons();
  })
);

// Keep other open tabs in step with the choice
window.addEventListener('storage', ({ key, newValue }) => {
  if (key === 'theme' && (newValue === 'light' || newValue === 'dark')) {
    root.dataset.theme = newValue;
    syncButtons();
  }
});

syncButtons();
