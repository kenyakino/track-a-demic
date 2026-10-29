/**
 * Student milestones page: see every milestone and deadline, start work,
 * submit for review, and read the adviser's notes.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';
import { milestoneBadge } from './labels.js';

const app = document.getElementById('app');

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

load();
