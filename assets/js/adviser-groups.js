/**
 * Adviser groups page: create groups, rename them, add and remove students,
 * choose the group leader, and delete empty groups.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, toast } from './ui.js';
import { badge } from './labels.js';

const app = document.getElementById('app');
const createDialog = document.getElementById('create-dialog');
const createForm = document.getElementById('create-form');
const renameDialog = document.getElementById('rename-dialog');
const renameForm = document.getElementById('rename-form');
const confirmDialog = document.getElementById('confirm-dialog');

let state = { groups: [], available: [] };
let confirmCallback = null;

/* ---------- Rendering ---------- */

const memberRow = (group, member) => html`<li>
  <div class="member-info">
    <span class="member-name">${member.full_name}</span>
    <span class="meta-inline">${member.email}</span>
  </div>
  <div class="member-actions">
    ${member.is_leader
      ? html`<span class="role-tag">Leader</span>`
      : html`<button type="button" class="btn-text" data-action="make-leader" data-group-id="${group.id}" data-user-id="${member.id}"
          aria-label="Make ${member.full_name} the leader of ${group.name}">Make leader</button>`}
    <button type="button" class="btn-text btn-text-danger" data-action="remove-member" data-group-id="${group.id}" data-user-id="${member.id}"
      data-name="${member.full_name}" aria-label="Remove ${member.full_name} from ${group.name}">Remove</button>
  </div>
</li>`;

const addRow = (group, available) =>
  available.length
    ? html`<div class="add-row">
        <label class="sr-only" for="add-${group.id}">Add a student to ${group.name}</label>
        <select class="select" id="add-${group.id}">
          <option value="">Choose a student</option>
          ${available.map((student) => html`<option value="${student.id}">${student.full_name} (${student.email})</option>`)}
        </select>
        <button type="button" class="btn btn-primary btn-sm" data-action="add-member" data-group-id="${group.id}">Add</button>
      </div>`
    : html`<p class="meta">No students are waiting for a group.</p>`;

const groupCard = (group, available) => html`<article class="card group-card">
  <div class="card-head">
    <h2>${group.name}</h2>
    <div class="card-head-actions">
      <button type="button" class="btn btn-quiet btn-sm" data-action="rename" data-group-id="${group.id}" data-name="${group.name}">Rename</button>
      ${group.has_work
        ? ''
        : html`<button type="button" class="btn btn-quiet btn-sm" data-action="delete-group" data-group-id="${group.id}" data-name="${group.name}">Delete</button>`}
    </div>
  </div>
  <p class="meta">Topic: ${group.topic_status ? badge(group.topic_status) : 'none yet'}</p>
  <h3 class="sub-title">Students (${group.members.length})</h3>
  ${group.members.length
    ? html`<ul class="members members-edit">${group.members.map((member) => memberRow(group, member))}</ul>`
    : html`<p class="meta">No students yet.</p>`}
  ${addRow(group, available)}
  ${group.has_work ? html`<p class="meta">This group has work, so it cannot be deleted.</p>` : ''}
</article>`;

const render = () => {
  if (state.groups.length === 0) {
    mount(
      app,
      html`<article class="card"><div class="empty">
        <p class="empty-title">You have no groups yet</p>
        <p>Create a group, then add the students who registered. The first student you add becomes the leader.</p>
        <button type="button" class="btn btn-primary" data-action="create">Create a group</button>
      </div></article>`
    );
    return;
  }

  mount(app, html`<div class="group-grid">${state.groups.map((group) => groupCard(group, state.available))}</div>`);
};

const showError = (message) =>
  mount(
    app,
    html`<article class="card"><div class="empty">
      <p class="empty-title">Could not load your groups</p>
      <p>${message}</p>
      <button type="button" class="btn btn-primary" data-action="retry">Try again</button>
    </div></article>`
  );

/* ---------- Loading and actions ---------- */

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-groups.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      showError(data.message ?? 'Something went wrong. Try again.');
      return;
    }

    state = { groups: data.groups, available: data.available_students };
    render();
  } catch (error) {
    showError('Could not reach the server. Check your connection and try again.');
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

const askConfirm = ({ title, message, confirmLabel, onConfirm }) => {
  document.getElementById('confirm-title').textContent = title;
  document.getElementById('confirm-message').textContent = message;
  confirmDialog.querySelector('[data-confirm]').textContent = confirmLabel;
  confirmCallback = onConfirm;
  confirmDialog.showModal();
};

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async () => {
  confirmDialog.close();
  await confirmCallback?.();
});

/* ---------- Create group dialog ---------- */

const setError = (form, name, message) => {
  const input = form.elements[name];
  const target = form.querySelector(`[data-error-for="${name}"]`);
  if (target) target.textContent = message;
  if (input instanceof HTMLElement) input.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const openCreate = () => {
  createForm.reset();
  createForm.querySelector('[data-alert]').hidden = true;
  ['group_name', 'member_ids'].forEach((name) => setError(createForm, name, ''));

  mount(
    createForm.querySelector('[data-student-list]'),
    state.available.length
      ? html`${state.available.map(
          (student) => html`<label class="check">
            <input type="checkbox" name="member" value="${student.id}">
            <span><strong>${student.full_name}</strong> <span class="meta-inline">${student.email}</span></span>
          </label>`
        )}`
      : html`<p class="meta pad">No students are waiting for a group. Students appear here after they register.</p>`
  );

  createDialog.showModal();
  createForm.elements.group_name.focus();
};

createForm.querySelector('[data-student-filter]').addEventListener('input', ({ target }) => {
  const query = target.value.trim().toLowerCase();
  createForm.querySelectorAll('.check').forEach((label) => {
    label.hidden = query !== '' && !label.textContent.toLowerCase().includes(query);
  });
});

createForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const alertBox = createForm.querySelector('[data-alert]');
  alertBox.hidden = true;

  const name = createForm.elements.group_name.value.trim();
  const memberIds = [...createForm.querySelectorAll('input[name="member"]:checked')].map((box) => Number(box.value));

  if (name.length < 2 || name.length > 100) {
    setError(createForm, 'group_name', 'Use 2 to 100 characters for the group name.');
    createForm.elements.group_name.focus();
    return;
  }
  setError(createForm, 'group_name', '');

  const submit = createForm.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson('group-create.php', { group_name: name, member_ids: memberIds });

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      createDialog.close();
      toast(data.message);
      await load();
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([field, message]) => setError(createForm, field, message));
    alertBox.textContent = data.message ?? 'Something went wrong. Try again.';
    alertBox.hidden = false;
  } catch (error) {
    alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox.hidden = false;
  } finally {
    submit.disabled = false;
  }
});

/* ---------- Rename dialog ---------- */

renameForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const name = renameForm.elements.group_name.value.trim();

  if (name.length < 2 || name.length > 100) {
    setError(renameForm, 'group_name', 'Use 2 to 100 characters for the group name.');
    return;
  }

  const submit = renameForm.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson('group-update.php', { id: Number(renameForm.elements.id.value), group_name: name });

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      renameDialog.close();
      toast(data.message);
      await load();
      return;
    }

    setError(renameForm, 'group_name', data.errors?.group_name ?? data.message ?? 'Something went wrong.');
  } catch (error) {
    setError(renameForm, 'group_name', 'Could not reach the server. Try again.');
  } finally {
    submit.disabled = false;
  }
});

renameForm.addEventListener('input', () => setError(renameForm, 'group_name', ''));

/* ---------- Clicks ---------- */

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const groupId = Number(button.dataset.groupId);
  const userId = Number(button.dataset.userId);

  const actions = {
    create: openCreate,
    retry: load,
    rename: () => {
      renameForm.elements.id.value = groupId;
      renameForm.elements.group_name.value = button.dataset.name;
      setError(renameForm, 'group_name', '');
      renameDialog.showModal();
      renameForm.elements.group_name.select();
    },
    'delete-group': () =>
      askConfirm({
        title: `Delete ${button.dataset.name}?`,
        message: 'The group and its student list will be removed. The students stay registered and can be added to a group again.',
        confirmLabel: 'Delete group',
        onConfirm: () => act('group-delete.php', { id: groupId }),
      }),
    'make-leader': () => act('group-leader-set.php', { group_id: groupId, user_id: userId }),
    'remove-member': () =>
      askConfirm({
        title: `Remove ${button.dataset.name}?`,
        message: 'The student leaves this group and can be added to a group again. If they are the leader, the longest-standing member becomes the leader.',
        confirmLabel: 'Remove student',
        onConfirm: () => act('group-member-remove.php', { group_id: groupId, user_id: userId }),
      }),
    'add-member': () => {
      const select = document.getElementById(`add-${groupId}`);
      if (!select.value) {
        toast('Choose a student first.', 'error');
        select.focus();
        return;
      }
      act('group-member-add.php', { group_id: groupId, user_id: Number(select.value) });
    },
  };

  actions[button.dataset.action]?.();
});

document.querySelector('[data-action="create"]')?.addEventListener('click', openCreate);

[createDialog, renameDialog, confirmDialog].forEach((dialog) => {
  dialog.addEventListener('click', ({ target }) => {
    if (target === dialog || target.closest('[data-close]')) dialog.close();
  });
});

load();
