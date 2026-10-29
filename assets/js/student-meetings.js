/**
 * Student meetings page: log adviser meetings, edit or delete them until
 * the adviser confirms, and see which ones are confirmed.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';

const app = document.getElementById('app');
const dialog = document.getElementById('meeting-dialog');
const form = document.getElementById('meeting-form');
const confirmDialog = document.getElementById('confirm-dialog');
const logButton = document.querySelector('[data-action="log"]');

let meetings = [];
let hasGroup = false;
let pendingDeleteId = null;

/* ---------- Rendering ---------- */

const meetingCard = (meeting) => html`<article class="card meeting-card">
  <div class="card-head">
    <h2>${formatDate(meeting.meeting_date)}</h2>
    ${meeting.confirmed
      ? html`<span class="badge badge-approved">Confirmed by adviser</span>`
      : html`<span class="badge badge-proposed">Waiting for confirmation</span>`}
  </div>
  <h3 class="sub-title">Notes</h3>
  <p class="topic-desc">${meeting.notes}</p>
  ${meeting.action_items ? html`<h3 class="sub-title">Action items</h3><p class="topic-desc">${meeting.action_items}</p>` : ''}
  ${meeting.can_edit
    ? html`<div class="card-actions">
        <button type="button" class="btn btn-quiet btn-sm" data-action="edit" data-id="${meeting.id}">Edit</button>
        <button type="button" class="btn btn-quiet btn-sm" data-action="delete" data-id="${meeting.id}">Delete</button>
      </div>`
    : html`<p class="meta">Confirmed meetings are locked.</p>`}
</article>`;

const message = (title, text, retry = false) => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${retry ? html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>` : ''}
</div></article>`;

const render = () => {
  logButton.hidden = !hasGroup;

  if (!hasGroup) {
    mount(app, message('You are not in a group yet', 'Your adviser creates the group and adds its students. You can log meetings once you are added.'));
    return;
  }

  mount(
    app,
    meetings.length
      ? html`<div class="meeting-list">${meetings.map(meetingCard)}</div>`
      : message('No meetings logged yet', 'After you meet your adviser, log what you discussed and what each person needs to do.')
  );
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('student-meetings.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load your meetings', data.message ?? 'Something went wrong. Try again.', true));
      return;
    }

    hasGroup = Boolean(data.group);
    meetings = data.meetings;
    render();
  } catch (error) {
    mount(app, message('Could not load your meetings', 'Could not reach the server. Check your connection and try again.', true));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/* ---------- Meeting form ---------- */

const alertBox = () => form.querySelector('[data-alert]');

const setError = (name, text) => {
  const input = form.elements[name];
  const target = form.querySelector(`[data-error-for="${name}"]`);
  if (target) target.textContent = text;
  input.setAttribute('aria-invalid', text ? 'true' : 'false');
};

const updateCounters = () => {
  form.querySelectorAll('[data-count-for]').forEach((counter) => {
    counter.textContent = form.elements[counter.dataset.countFor].value.length;
  });
};

const todayString = () => {
  const now = new Date();
  const pad = (value) => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

const openForm = (meeting = null) => {
  form.reset();
  alertBox().hidden = true;
  ['meeting_date', 'notes', 'action_items'].forEach((name) => setError(name, ''));

  form.elements.meeting_date.max = todayString();
  form.elements.id.value = meeting?.id ?? '';
  form.elements.meeting_date.value = meeting?.meeting_date ?? todayString();
  form.elements.notes.value = meeting?.notes ?? '';
  form.elements.action_items.value = meeting?.action_items ?? '';

  document.getElementById('meeting-dialog-title').textContent = meeting ? 'Edit meeting' : 'Log a meeting';
  updateCounters();
  dialog.showModal();
  form.elements.meeting_date.focus();
};

const validate = ({ meeting_date: date, notes, action_items: actions }) => {
  const errors = {};
  if (!date) errors.meeting_date = 'Choose the meeting date.';
  else if (date > todayString()) errors.meeting_date = 'The meeting date cannot be in the future.';
  if (notes.trim().length < 10 || notes.trim().length > 2000) errors.notes = 'Use 10 to 2000 characters for the notes.';
  if (actions.trim().length > 1000) errors.action_items = 'Use 1000 characters or fewer.';
  return errors;
};

form.addEventListener('input', ({ target }) => {
  if (target.name) setError(target.name, '');
  updateCounters();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  alertBox().hidden = true;

  const payload = Object.fromEntries(new FormData(form));
  const errors = validate(payload);
  ['meeting_date', 'notes', 'action_items'].forEach((name) => setError(name, errors[name] ?? ''));

  if (Object.keys(errors).length > 0) {
    form.elements[Object.keys(errors)[0]].focus();
    return;
  }

  const submit = form.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson('meeting-save.php', { ...payload, id: Number(payload.id) || 0 });

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      dialog.close();
      toast(data.message);
      await load();
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([name, text]) => setError(name, text));
    alertBox().textContent = data.message ?? 'Something went wrong. Try again.';
    alertBox().hidden = false;
    if (response.status === 409) await load();
  } catch (error) {
    alertBox().textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox().hidden = false;
  } finally {
    submit.disabled = false;
  }
});

/* ---------- Delete ---------- */

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async () => {
  confirmDialog.close();

  try {
    const { response, data } = await postJson('meeting-delete.php', { id: pendingDeleteId });

    if (response.status === 401) return goToLogin();

    toast(data.message ?? 'Something went wrong.', response.ok && data.ok ? 'success' : 'error');
    await load();
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
  }
});

/* ---------- Clicks ---------- */

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const id = Number(button.dataset.id);
  const actions = {
    retry: load,
    edit: () => openForm(meetings.find((meeting) => meeting.id === id)),
    delete: () => {
      pendingDeleteId = id;
      confirmDialog.showModal();
    },
  };

  actions[button.dataset.action]?.();
});

logButton.addEventListener('click', () => openForm());

[dialog, confirmDialog].forEach((element) => {
  element.addEventListener('click', ({ target }) => {
    if (target === element || target.closest('[data-close]')) element.close();
  });
});

load();
