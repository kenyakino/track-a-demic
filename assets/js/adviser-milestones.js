/**
 * Adviser milestones page: set milestones and deadlines, choose which groups
 * get them, and review what each group submits.
 */

import { getJson, postJson, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';
import { milestoneBadge } from './labels.js';

const app = document.getElementById('app');
const dialog = document.getElementById('milestone-dialog');
const form = document.getElementById('milestone-form');
const reviewDialog = document.getElementById('review-dialog');
const reviewForm = document.getElementById('review-form');
const confirmDialog = document.getElementById('confirm-dialog');
const addButton = document.querySelector('[data-action="add"]');

let state = { groups: [], milestones: [] };
let confirmCallback = null;

/* ---------- Rendering ---------- */

const REVIEW_BUTTONS = {
  complete: { label: 'Mark complete', cls: 'btn-primary btn-sm' },
  rework: { label: 'Needs rework', cls: 'btn-quiet btn-sm' },
  reopen: { label: 'Reopen', cls: 'btn-quiet btn-sm' },
};

const assignmentRow = (assignment) => html`<li>
  <div class="assignment-head">
    <div>
      <strong>${assignment.group_name}</strong>
      ${assignment.is_overdue ? html` <strong class="overdue">Overdue</strong>` : ''}
    </div>
    ${milestoneBadge(assignment.status)}
  </div>
  ${assignment.comments.length
    ? html`<ul class="note-list">${assignment.comments.map((note) => html`<li><span class="meta-inline">${formatDate(note.created_at)}:</span> ${note.text}</li>`)}</ul>`
    : ''}
  ${assignment.actions.length
    ? html`<div class="assignment-actions">
        ${assignment.actions.map(
          (action) => html`<button type="button" class="btn ${REVIEW_BUTTONS[action].cls}" data-action="review" data-do="${action}" data-id="${assignment.id}"
            data-group="${assignment.group_name}" data-name="${assignment.name}">${REVIEW_BUTTONS[action].label}</button>`
        )}
      </div>`
    : ''}
</li>`;

const milestoneCard = (milestone) => html`<article class="card">
  <div class="card-head">
    <div>
      <h2>${milestone.name}</h2>
      <p class="meta">
        Due ${formatDate(milestone.due_date)}${milestone.term ? html`. ${milestone.term}` : ''}.
        ${milestone.total ? html`${milestone.done} of ${milestone.total} groups completed.` : 'Not assigned to any group.'}
      </p>
    </div>
    <div class="card-head-actions">
      <button type="button" class="btn btn-quiet btn-sm" data-action="edit" data-id="${milestone.id}">Edit</button>
      <button type="button" class="btn btn-quiet btn-sm" data-action="delete" data-id="${milestone.id}" data-name="${milestone.name}">Delete</button>
    </div>
  </div>
  ${milestone.assignments.length ? html`<ul class="assignments">${milestone.assignments.map(assignmentRow)}</ul>` : ''}
</article>`;

const message = (title, text, button = '') => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${button}
</div></article>`;

const render = () => {
  addButton.hidden = state.groups.length === 0;

  if (state.groups.length === 0) {
    mount(
      app,
      message('Create a group first', 'Milestones are given to your groups, so you need at least one group.',
        html`<a class="btn btn-primary" href="${baseUrl}/pages/adviser/groups.php">Go to groups</a>`)
    );
    return;
  }

  if (state.milestones.length === 0) {
    mount(
      app,
      message('No milestones yet', 'Add milestones such as the topic proposal, chapters, and final defense, each with a deadline.',
        html`<button type="button" class="btn btn-primary" data-action="add">Add a milestone</button>`)
    );
    return;
  }

  mount(app, html`<div class="stack">${state.milestones.map(milestoneCard)}</div>`);
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-milestones.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load milestones', data.message ?? 'Something went wrong. Try again.', html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
      return;
    }

    state = { groups: data.groups, milestones: data.milestones };
    render();
  } catch (error) {
    mount(app, message('Could not load milestones', 'Could not reach the server. Check your connection and try again.', html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/** Sends one change, shows the result, and refreshes the page data. */
const act = async (file, payload) => {
  try {
    const { response, data } = await postJson(file, payload);

    if (response.status === 401) return goToLogin();

    const ok = response.ok && data.ok;
    toast(data.message ?? 'Something went wrong.', ok ? 'success' : 'error');
    if (ok || response.status === 409) await load();
    return ok;
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
    return false;
  }
};

/* ---------- Add / edit milestone ---------- */

const setError = (target, name, text) => {
  const input = target.elements[name];
  const holder = target.querySelector(`[data-error-for="${name}"]`);
  if (holder) holder.textContent = text;
  if (input instanceof HTMLElement) input.setAttribute('aria-invalid', text ? 'true' : 'false');
};

const openForm = (milestone = null) => {
  form.reset();
  form.querySelector('[data-alert]').hidden = true;
  ['name', 'due_date', 'term', 'group_ids'].forEach((name) => setError(form, name, ''));

  const assigned = new Map((milestone?.assignments ?? []).map((item) => [item.group_id, item]));

  form.elements.id.value = milestone?.id ?? '';
  form.elements.name.value = milestone?.name ?? '';
  form.elements.due_date.value = milestone?.due_date ?? '';
  form.elements.term.value = milestone?.term ?? '';
  document.getElementById('milestone-dialog-title').textContent = milestone ? 'Edit milestone' : 'Add a milestone';

  mount(
    form.querySelector('[data-group-list]'),
    html`${state.groups.map((group) => {
      const item = assigned.get(group.id);
      const locked = Boolean(item) && item.status !== 'Not Started';
      return html`<label class="check">
        <input type="checkbox" name="group" value="${group.id}" ${!milestone || item ? 'checked' : ''} ${locked ? 'disabled' : ''}>
        <span><strong>${group.name}</strong>${locked ? html` <span class="meta-inline">Already started, so it stays assigned</span>` : ''}</span>
      </label>`;
    })}`
  );

  dialog.showModal();
  form.elements.name.focus();
};

form.addEventListener('input', ({ target }) => {
  if (target.name && target.name !== 'group') setError(form, target.name, '');
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const alertBox = form.querySelector('[data-alert]');
  alertBox.hidden = true;

  const name = form.elements.name.value.trim();
  const due = form.elements.due_date.value;
  const errors = {};
  if (name.length < 2 || name.length > 150) errors.name = 'Use 2 to 150 characters for the name.';
  if (!due) errors.due_date = 'Choose a due date.';
  ['name', 'due_date'].forEach((field) => setError(form, field, errors[field] ?? ''));

  if (Object.keys(errors).length > 0) {
    form.elements[Object.keys(errors)[0]].focus();
    return;
  }

  const id = Number(form.elements.id.value) || 0;
  const payload = {
    id,
    name,
    due_date: due,
    term: form.elements.term.value.trim(),
    group_ids: [...form.querySelectorAll('input[name="group"]:checked')].map((box) => Number(box.value)),
  };

  const submit = form.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson(id ? 'milestone-update.php' : 'milestone-create.php', payload);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      dialog.close();
      toast(data.message);
      await load();
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([field, text]) => setError(form, field, text));
    alertBox.textContent = data.message ?? 'Something went wrong. Try again.';
    alertBox.hidden = false;
  } catch (error) {
    alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox.hidden = false;
  } finally {
    submit.disabled = false;
  }
});

/* ---------- Review dialog (complete, rework, reopen) ---------- */

const REVIEW_COPY = {
  complete: { title: 'Mark as completed', note: 'Optional note for the group', submit: 'Mark complete', required: false },
  rework: { title: 'Send back for rework', note: 'What needs to change (required)', submit: 'Send back', required: true },
};

const openReview = ({ action, id, group, name }) => {
  const copy = REVIEW_COPY[action];
  reviewForm.reset();
  setError(reviewForm, 'note', '');
  reviewForm.elements.id.value = id;
  reviewForm.elements.action.value = action;
  document.getElementById('review-dialog-title').textContent = copy.title;
  document.getElementById('review-dialog-subtitle').textContent = `${group}: ${name}`;
  document.getElementById('review-note-label').textContent = copy.note;
  reviewForm.querySelector('[data-submit]').textContent = copy.submit;
  reviewDialog.showModal();
  reviewForm.elements.note.focus();
};

reviewForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const action = reviewForm.elements.action.value;
  const note = reviewForm.elements.note.value.trim();

  if (REVIEW_COPY[action].required && note === '') {
    setError(reviewForm, 'note', 'Write a note so the group knows what to fix.');
    reviewForm.elements.note.focus();
    return;
  }

  const submit = reviewForm.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson('milestone-review.php', { id: Number(reviewForm.elements.id.value), action, note });

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      reviewDialog.close();
      toast(data.message);
      await load();
      return;
    }

    setError(reviewForm, 'note', data.errors?.note ?? data.message ?? 'Something went wrong.');
    if (response.status === 409) await load();
  } catch (error) {
    setError(reviewForm, 'note', 'Could not reach the server. Try again.');
  } finally {
    submit.disabled = false;
  }
});

reviewForm.addEventListener('input', () => setError(reviewForm, 'note', ''));

/* ---------- Clicks ---------- */

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async () => {
  confirmDialog.close();
  await confirmCallback?.();
});

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const id = Number(button.dataset.id);
  const actions = {
    retry: load,
    add: () => openForm(),
    edit: () => openForm(state.milestones.find((milestone) => milestone.id === id)),
    delete: () => {
      document.getElementById('confirm-message').textContent = `"${button.dataset.name}" will be removed from every group. This only works if no group has started it.`;
      confirmCallback = () => act('milestone-delete.php', { id });
      confirmDialog.showModal();
    },
    review: () => {
      const action = button.dataset.do;
      if (action === 'reopen') act('milestone-review.php', { id, action, note: '' });
      else openReview({ action, id, group: button.dataset.group, name: button.dataset.name });
    },
  };

  actions[button.dataset.action]?.();
});

addButton.addEventListener('click', () => openForm());

[dialog, reviewDialog, confirmDialog].forEach((element) => {
  element.addEventListener('click', ({ target }) => {
    if (target === element || target.closest('[data-close]')) element.close();
  });
});

load();
