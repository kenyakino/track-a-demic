/**
 * Small Fetch API helpers shared by the dashboards.
 * Every call sends the session cookie, and POST calls send the CSRF token.
 */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const baseUrl = document.body.dataset.baseUrl ?? '';
export const apiUrl = (file) => `${baseUrl}/api/${file}`;

const parse = async (response) => ({
  response,
  data: await response.json().catch(() => ({})),
});

/** GET an API endpoint. Returns { response, data }. */
export const getJson = async (file) =>
  parse(await fetch(apiUrl(file), { credentials: 'same-origin', headers: { Accept: 'application/json' } }));

/** POST JSON to an API endpoint. Returns { response, data }. */
export const postJson = async (file, payload = {}) =>
  parse(
    await fetch(apiUrl(file), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(payload),
    })
  );

/** Send the visitor to the login page (used when the session has ended). */
export const goToLogin = () => {
  window.location.href = `${baseUrl}/pages/login.php`;
};
