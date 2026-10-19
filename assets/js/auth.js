/**
 * Login and register forms:
 * client-side validation, password strength meter, and Fetch API calls.
 */

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const csrfToken = $('meta[name="csrf-token"]')?.content ?? '';
const baseUrl = document.body.dataset.baseUrl ?? '';
const apiUrl = (file) => `${baseUrl}/api/${file}`;

/* ---------- Validation ---------- */

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const validators = {
  full_name: (value) => (value.trim().length >= 2 ? '' : 'Enter your full name.'),
  email: (value) => (emailPattern.test(value.trim()) ? '' : 'Enter a valid email address.'),
  password: (value, form) => {
    if (form.dataset.form === 'login') {
      return value ? '' : 'Enter your password.';
    }
    if (value.length < 8) return 'Use at least 8 characters.';
    if (!/[A-Za-z]/.test(value) || !/\d/.test(value)) return 'Include at least one letter and one number.';
    return '';
  },
  confirm_password: (value, form) => (value === form.elements.password.value ? '' : 'Passwords do not match.'),
  adviser_code: (value, form) =>
    form.elements.role?.value === 'adviser' && !value.trim() ? 'Enter the adviser code.' : '',
};

const fieldsByForm = {
  login: ['email', 'password'],
  register: ['full_name', 'email', 'adviser_code', 'password', 'confirm_password'],
};

const setError = (form, name, message) => {
  const input = form.elements[name];
  const target = $(`[data-error-for="${name}"]`, form);
  if (!(input instanceof HTMLElement) || !target) return;

  target.textContent = message;
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const validateField = (form, name) => {
  const input = form.elements[name];
  if (!(input instanceof HTMLElement) || input.closest('[hidden]')) return true;

  const message = validators[name](input.value, form);
  setError(form, name, message);
  return message === '';
};

/* ---------- Page feedback ---------- */

const showAlert = (message) => {
  const box = $('[data-alert]');
  if (!box) return;
  box.textContent = message;
  box.hidden = false;
};

const hideAlert = () => {
  const box = $('[data-alert]');
  if (box) box.hidden = true;
};

const setBusy = (form, busy) => {
  const button = $('button[type="submit"]', form);
  if (!button) return;

  button.dataset.label ??= button.textContent;
  button.disabled = busy;
  button.textContent = busy
    ? (form.dataset.form === 'login' ? 'Signing in…' : 'Creating account…')
    : button.dataset.label;
};

/* ---------- Password strength ---------- */

const strengthLabels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];

const scorePassword = (value) => {
  if (value.length < 8) return 0;

  let score = 1;
  if (value.length >= 12) score++;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
  if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
  return score;
};

const updateMeter = (form) => {
  const meter = $('[data-meter]', form);
  const label = $('[data-meter-label]', form);
  if (!meter || !label) return;

  const { value } = form.elements.password;
  const score = scorePassword(value);

  meter.dataset.level = value ? String(score) : '0';
  label.textContent = value ? `Password strength: ${strengthLabels[score]}` : '';
};

/* ---------- Fetch API ---------- */

const postJson = async (url, payload) => {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });
  const data = await response.json().catch(() => ({}));
  return { response, data };
};

/* ---------- Form handling ---------- */

const handleSubmit = async (event) => {
  event.preventDefault();

  const form = event.currentTarget;
  const type = form.dataset.form;
  hideAlert();

  // Check every field so all problems show at once
  const results = fieldsByForm[type].map((name) => [name, validateField(form, name)]);
  const firstInvalid = results.find(([, ok]) => !ok);

  if (firstInvalid) {
    showAlert('Please fix the highlighted fields.');
    form.elements[firstInvalid[0]].focus();
    return;
  }

  const payload = Object.fromEntries(new FormData(form));
  let redirecting = false;
  setBusy(form, true);

  try {
    const { response, data } = await postJson(apiUrl(`${type}.php`), payload);

    if (response.ok && data.ok) {
      redirecting = true;
      window.location.href = data.redirect;
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([name, message]) => setError(form, name, message));
    showAlert(data.message ?? 'Something went wrong. Try again.');
  } catch (error) {
    showAlert('Could not reach the server. Check your connection and try again.');
  } finally {
    if (!redirecting) setBusy(form, false);
  }
};

const initForm = (form) => {
  form.addEventListener('submit', handleSubmit);

  // Validate a field when the user leaves it (only if they typed something)
  form.addEventListener('focusout', ({ target }) => {
    const { name, value } = target;
    if (name in validators && value !== '') validateField(form, name);
  });

  // Clear a field's error as soon as the user edits it
  form.addEventListener('input', ({ target }) => {
    const { name } = target;
    if (name in validators) setError(form, name, '');
    if (name === 'password') updateMeter(form);
  });

  // Student / adviser choice shows or hides the adviser code field
  const adviserField = $('[data-adviser-field]', form);
  $$('input[name="role"]', form).forEach((radio) =>
    radio.addEventListener('change', () => {
      if (!adviserField) return;
      adviserField.hidden = form.elements.role.value !== 'adviser';
      setError(form, 'adviser_code', '');
    })
  );

  // Show / hide password
  $$('[data-toggle-password]', form).forEach((button) =>
    button.addEventListener('click', () => {
      const input = document.getElementById(button.getAttribute('aria-controls'));
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', String(show));
    })
  );
};

$$('form[data-form]').forEach(initForm);
