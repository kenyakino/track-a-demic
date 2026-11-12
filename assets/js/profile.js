/**
 * Profile page: edit name, email, password, and picture.
 * After one saved change the server locks the profile for 30 days.
 */

import { getJson, postForm, goToLogin } from './api.js';
import { html, mount, toast } from './ui.js';

const app = document.getElementById('app');
const MAX_PICTURE = 2 * 1024 * 1024;

const avatar = (profile) =>
  profile.avatar_url
    ? html`<img class="avatar avatar-lg" src="${profile.avatar_url}" alt="Your profile picture" data-avatar-preview>`
    : html`<span class="avatar avatar-lg" data-avatar-preview aria-label="Your initials">${profile.initials}</span>`;

const render = ({ profile, lock }) => {
  const off = lock.locked ? 'disabled' : null;

  mount(app, html`
    ${lock.locked
      ? html`<div class="banner banner-warn" role="status"><strong>Profile locked.</strong>
          You saved changes on ${lock.updated_on}. You can edit again on ${lock.unlocks_on} (${lock.days_left} day${lock.days_left === 1 ? '' : 's'} left).</div>`
      : html`<div class="banner" role="status">Heads up: once you save a change, you cannot edit your profile again for 30 days.</div>`}

    <form class="card profile-card" data-form novalidate>
      <div class="alert alert-error" data-alert role="alert" hidden></div>

      <div class="profile-picture">
        ${avatar(profile)}
        <div class="field">
          <label for="avatar">Profile picture</label>
          <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" ${off} aria-describedby="avatar-hint avatar-error">
          <p class="hint" id="avatar-hint">JPG, PNG, or WebP, up to 2 MB. It is cropped to a square.</p>
          <p class="field-error" id="avatar-error" data-error-for="avatar"></p>
          ${profile.avatar_url
            ? html`<label class="check"><input type="checkbox" name="remove_avatar" value="1" ${off}> Remove my picture</label>`
            : ''}
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="first_name">First name</label>
          <input id="first_name" name="first_name" type="text" maxlength="50" value="${profile.first_name}" ${off} autocomplete="given-name" aria-describedby="first_name-error">
          <p class="field-error" id="first_name-error" data-error-for="first_name"></p>
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input id="last_name" name="last_name" type="text" maxlength="50" value="${profile.last_name}" ${off} autocomplete="family-name" aria-describedby="last_name-error">
          <p class="field-error" id="last_name-error" data-error-for="last_name"></p>
        </div>
      </div>

      ${profile.role === 'adviser'
        ? html`<div class="field">
            <label for="title">Academic title <span class="meta-inline">(optional)</span></label>
            <input id="title" name="title" type="text" maxlength="30" value="${profile.title ?? ''}" ${off} aria-describedby="title-error">
            <p class="field-error" id="title-error" data-error-for="title"></p>
          </div>`
        : ''}

      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="150" value="${profile.email}" ${off} autocomplete="email" aria-describedby="email-error">
        <p class="field-error" id="email-error" data-error-for="email"></p>
      </div>

      <fieldset class="field-group">
        <legend>Change password <span class="meta-inline">(leave blank to keep it)</span></legend>
        <div class="form-row">
          <div class="field">
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" ${off} autocomplete="new-password" aria-describedby="new_password-error">
            <p class="field-error" id="new_password-error" data-error-for="new_password"></p>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" name="confirm_password" type="password" ${off} autocomplete="new-password" aria-describedby="confirm_password-error">
            <p class="field-error" id="confirm_password-error" data-error-for="confirm_password"></p>
          </div>
        </div>
      </fieldset>

      <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" ${off} autocomplete="current-password" aria-describedby="current_password-hint current_password-error">
        <p class="hint" id="current_password-hint">Needed only when you change your email or password.</p>
        <p class="field-error" id="current_password-error" data-error-for="current_password"></p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" ${off}>Save changes</button>
      </div>
    </form>
  `);

  app.setAttribute('aria-busy', 'false');
  wire(profile, lock);
};

const setError = (form, name, message) => {
  const target = form.querySelector(`[data-error-for="${name}"]`);
  const input = form.elements[name];
  if (target) target.textContent = message;
  input?.setAttribute?.('aria-invalid', message ? 'true' : 'false');
};

const wire = (profile, lock) => {
  const form = app.querySelector('[data-form]');
  const alertBox = form.querySelector('[data-alert]');
  if (lock.locked) return;

  // Preview the chosen picture and reject a bad file before uploading
  form.elements.avatar.addEventListener('change', () => {
    const file = form.elements.avatar.files[0];
    setError(form, 'avatar', '');
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError(form, 'avatar', 'Use a JPG, PNG, or WebP picture.');
      form.elements.avatar.value = '';
      return;
    }
    if (file.size > MAX_PICTURE) {
      setError(form, 'avatar', 'The picture is larger than 2 MB.');
      form.elements.avatar.value = '';
      return;
    }

    const preview = app.querySelector('[data-avatar-preview]');
    const img = document.createElement('img');
    img.className = 'avatar avatar-lg';
    img.alt = 'Preview of your new picture';
    img.src = URL.createObjectURL(file);
    img.setAttribute('data-avatar-preview', '');
    preview.replaceWith(img);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    alertBox.hidden = true;
    ['first_name', 'last_name', 'title', 'email', 'new_password', 'confirm_password', 'current_password', 'avatar']
      .forEach((name) => setError(form, name, ''));

    const emailChanged = form.elements.email.value.trim().toLowerCase() !== profile.email;
    const wantsPassword = form.elements.new_password.value !== '';
    let bad = false;

    if (!form.elements.first_name.value.trim()) { setError(form, 'first_name', 'Enter your first name.'); bad = true; }
    if (!form.elements.last_name.value.trim()) { setError(form, 'last_name', 'Enter your last name.'); bad = true; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.elements.email.value.trim())) { setError(form, 'email', 'Enter a valid email address.'); bad = true; }
    if (wantsPassword && form.elements.new_password.value !== form.elements.confirm_password.value) {
      setError(form, 'confirm_password', 'Passwords do not match.'); bad = true;
    }
    if ((emailChanged || wantsPassword) && !form.elements.current_password.value) {
      setError(form, 'current_password', 'Enter your current password to change your email or password.'); bad = true;
    }
    if (bad) {
      alertBox.textContent = 'Please fix the highlighted fields.';
      alertBox.hidden = false;
      return;
    }

    const button = form.querySelector('[type="submit"]');
    button.disabled = true;

    try {
      const { response, data } = await postForm('profile-update.php', new FormData(form));
      if (response.status === 401) return goToLogin();

      if (response.ok && data.ok) {
        toast(data.message);
        if (data.changed) window.location.reload();
        else button.disabled = false;
        return;
      }

      Object.entries(data.errors ?? {}).forEach(([name, message]) => setError(form, name, message));
      alertBox.textContent = data.message ?? 'Could not save your profile.';
      alertBox.hidden = false;
      if (response.status === 423) load();
      else button.disabled = false;
    } catch (error) {
      alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
      alertBox.hidden = false;
      button.disabled = false;
    }
  });
};

async function load() {
  try {
    const { response, data } = await getJson('profile.php');
    if (response.status === 401) return goToLogin();
    if (!data.ok) throw new Error('failed');
    render(data);
  } catch (error) {
    mount(app, html`<article class="card"><p class="alert alert-error">Could not load your profile. Reload the page.</p></article>`);
    app.setAttribute('aria-busy', 'false');
  }
}

load();
