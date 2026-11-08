/**
 * Student milestones page: see every milestone and deadline, start work,
 * submit for review, and read the adviser's notes.
 */

import { getJson, postJson, postForm, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, formatBytes, toast } from './ui.js';
import { milestoneBadge } from './labels.js';

const app = document.getElementById('app');
const submitDialog = document.getElementById('submit-dialog');
const submitForm = document.getElementById('submit-form');

let limits = { max_size: 5 * 1024 * 1024, allowed_ext: ['pdf', 'docx'] };
let current = [];

const ACTIONS = {
  start: { label: 'Start working', cls: 'btn-primary' },
  submit: { label: 'Submit for review', cls: 'btn-primary' },
  withdraw: { label: 'Withdraw submission', cls: 'btn-quiet' },
};

const HELP = {
  'Not Started': 'Your group has not started this yet.',
  'In Progress': 'Your group is working on this. Submit it when it is ready for review.',
  Submitted: "Waiting for your adviser's review.",
  Completed: 'Your adviser marked this as completed.',
  'Needs Rework': 'Your adviser asked for changes. Fix them and submit again.',
};

const notesBlock = (milestone) =>
  milestone.comments.length
    ? html`<section class="comments">
        <h3 class="sub-title">Adviser notes</h3>
        <ul class="comment-list">
          ${milestone.comments.map(
            (note) => html`<li>
              <div class="comment-head"><strong>${note.author}</strong><span class="meta-inline">${formatDate(note.created_at)}</span></div>
              <p>${note.text}</p>
            </li>`
          )}
        </ul>
      </section>`
    : '';

const milestoneCard = (milestone) => html`<li class="card milestone-card ${milestone.is_overdue ? 'is-overdue' : ''}">
  <div class="card-head">
    <h2>${milestone.name}</h2>
    ${milestoneBadge(milestone.status)}
  </div>
  <p class="meta">
    Due ${formatDate(milestone.due_date)}${milestone.term ? html`. ${milestone.term}` : ''}
    ${milestone.is_overdue ? html` <strong class="overdue">Overdue</strong>` : ''}
  </p>
  <p class="status-help">${HELP[milestone.status] ?? ''}</p>
  ${milestone.submission_file
    ? html`<p class="meta">Submitted file: <a href="${baseUrl}/api/file-download.php?id=${milestone.submission_file.id}">${milestone.submission_file.name}</a> (${formatBytes(milestone.submission_file.size)})</p>`
    : ''}
  ${notesBlock(milestone)}
  ${milestone.actions.length
    ? html`<div class="card-actions">
        ${milestone.actions.map(
          (action) => html`<button type="button" class="btn ${ACTIONS[action].cls} btn-sm" data-action="${action}" data-id="${milestone.id}">${ACTIONS[action].label}</button>`
        )}
      </div>`
    : ''}
</li>`;

const message = (title, text, retry = false) => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${retry ? html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>` : ''}
</div></article>`;

const render = ({ group, milestones, progress }) => {
  if (!group) {
    mount(app, message('You are not in a group yet', 'Your adviser creates the group and adds its students. Milestones appear here once you are added.'));
    return;
  }

  if (milestones.length === 0) {
    mount(app, message('No milestones yet', 'Your adviser has not set any milestones for your group.'));
    return;
  }

  const percent = Math.round((progress.done / progress.total) * 100);

  mount(
    app,
    html`<article class="card progress-card">
        <div class="card-head">
          <h2>${group.name}</h2>
          <p class="meta-inline"><strong>${progress.done} of ${progress.total}</strong> milestones completed</p>
        </div>
        <div class="progress" role="progressbar" aria-label="Milestones completed" aria-valuemin="0" aria-valuemax="${progress.total}" aria-valuenow="${progress.done}">
          <span style="width: ${percent}%"></span>
        </div>
      </article>
      <ol class="milestone-list">${milestones.map(milestoneCard)}</ol>`
  );
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('student-milestones.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load your milestones', data.message ?? 'Something went wrong. Try again.', true));
      return;
    }

    limits = data.limits ?? limits;
    current = data.milestones;
    render(data);
  } catch (error) {
    mount(app, message('Could not load your milestones', 'Could not reach the server. Check your connection and try again.', true));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

app.addEventListener('click', async ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  if (button.dataset.action === 'retry') return load();
  if (button.dataset.action === 'submit') return openSubmit(Number(button.dataset.id));

  button.disabled = true;

  try {
    const { response, data } = await postJson('milestone-progress.php', { id: Number(button.dataset.id), action: button.dataset.action });

    if (response.status === 401) return goToLogin();

    const ok = response.ok && data.ok;
    toast(data.message ?? 'Something went wrong.', ok ? 'success' : 'error');
    if (ok || response.status === 409) await load();
    else button.disabled = false;
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
    button.disabled = false;
  }
});

/* ---------- Submit with a file ---------- */

const typesText = () => limits.allowed_ext.map((ext) => ext.toUpperCase()).join(' or ');

const setFileError = (text) => {
  submitForm.querySelector('[data-error-for="file"]').textContent = text;
  submitForm.elements.file.setAttribute('aria-invalid', text ? 'true' : 'false');
};

const checkFile = (file) => {
  if (!file) return 'Choose the file your adviser should check.';

  const extension = file.name.split('.').pop().toLowerCase();
  if (!limits.allowed_ext.includes(extension)) return `Only ${typesText()} files are allowed.`;
  if (file.size === 0) return 'The file is empty.';
  if (file.size > limits.max_size) return `The file is too large. The limit is ${formatBytes(limits.max_size)}.`;
  return '';
};

const openSubmit = (id) => {
  const milestone = current.find((item) => item.id === id);

  submitForm.reset();
  submitForm.querySelector('[data-alert]').hidden = true;
  setFileError('');
  submitForm.elements.id.value = id;
  submitForm.elements.file.accept = limits.allowed_ext.map((ext) => `.${ext}`).join(',');

  document.getElementById('submit-subtitle').textContent = milestone ? milestone.name : '';
  document.getElementById('submit-hint').textContent =
    `${typesText()}, up to ${formatBytes(limits.max_size)}.` +
    (milestone?.submission_file ? ` It replaces ${milestone.submission_file.name}.` : '');

  submitDialog.showModal();
  submitForm.elements.file.focus();
};

submitForm.elements.file.addEventListener('change', () => {
  const chosen = submitForm.elements.file.files[0];
  setFileError(chosen ? checkFile(chosen) : '');
});

submitForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const alertBox = submitForm.querySelector('[data-alert]');
  alertBox.hidden = true;

  const chosen = submitForm.elements.file.files[0];
  const problem = checkFile(chosen);
  setFileError(problem);
  if (problem) {
    submitForm.elements.file.focus();
    return;
  }

  const body = new FormData();
  body.append('id', submitForm.elements.id.value);
  body.append('file', chosen);

  const submit = submitForm.querySelector('[data-submit]');
  submit.disabled = true;
  submit.textContent = 'Submitting…';

  try {
    const { response, data } = await postForm('milestone-submit.php', body);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      submitDialog.close();
      toast(data.message);
      await load();
      return;
    }

    setFileError(data.errors?.file ?? '');
    alertBox.textContent = data.errors?.file ? 'The milestone was not submitted.' : data.message ?? 'Something went wrong. Try again.';
    alertBox.hidden = false;
    if (response.status === 409) await load();
  } catch (error) {
    alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox.hidden = false;
  } finally {
    submit.disabled = false;
    submit.textContent = 'Submit for review';
  }
});

submitDialog.addEventListener('click', ({ target }) => {
  if (target === submitDialog || target.closest('[data-close]')) submitDialog.close();
});


load();
