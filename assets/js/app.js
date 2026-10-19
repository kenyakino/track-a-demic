/**
 * Behavior shared by signed-in pages.
 * For now: the Sign out button.
 */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const baseUrl = document.body.dataset.baseUrl ?? '';

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
