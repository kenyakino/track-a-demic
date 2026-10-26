/**
 * Student dashboard: loads the group overview with the Fetch API, renders it,
 * and handles creating, editing, and deleting the group's topic.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';
import { TOPIC_STATUS, badge } from './labels.js';

const app = document.getElementById('app');
const topicDialog = document.getElementById('topic-dialog');
const topicForm = document.getElementById('topic-form');
const confirmDialog = document.getElementById('confirm-dialog');

let overview = null;
let pendingDeleteId = null;

/* ---------- Labels ---------- */

const MILESTONE_STATUS = {
  Completed: { cls: 'is-done', label: 'Completed' },
  Submitted: { cls: 'is-current', label: 'Submitted, waiting for review' },
  'In Progress': { cls: 'is-current', label: 'In progress' },
  'Needs Rework': { cls: 'is-rework', label: 'Needs rework' },
  'Not Started': { cls: '', label: 'Not started' },
};

/* ---------- Rendering ---------- */

const remarksBlock = (topic) =>
  topic.adviser_remarks
    ? html`<div class="remarks ${topic.status === 'For Revision' ? 'remarks-warn' : ''}">
        <strong>Adviser remarks</strong>
        <p>${topic.adviser_remarks}</p>
      </div>`
    : '';

const commentsBlock = (topic) =>
  topic.comments?.length
    ? html`<section class="comments">
        <h3 class="sub-title">Adviser comments</h3>
        <ul class="comment-list">
          ${topic.comments.map(
            (comment) => html`<li>
              <div class="comment-head"><strong>${comment.author}</strong><span class="meta-inline">${formatDate(comment.created_at)}</span></div>
              <p>${comment.text}</p>
            </li>`
          )}
        </ul>
      </section>`
    : '';

const topicCard = ({ topic, previous_topics: previous }) => {
  const previousBlock = previous.length
    ? html`<details class="previous">
        <summary>Previous topics (${previous.length})</summary>
        <ul>
          ${previous.map(
            (item) => html`<li>
              <div class="previous-head"><strong>${item.title}</strong>${badge(item.status)}</div>
              ${item.adviser_remarks ? html`<p class="meta">Adviser remarks: ${item.adviser_remarks}</p>` : ''}
            </li>`
          )}
        </ul>
      </details>`
    : '';

  if (!topic) {
    return html`<article class="card">
      <div class="card-head"><h2>Research topic</h2></div>
      <div class="empty">
        <p class="empty-title">No topic yet</p>
        <p>Your group has not proposed a research topic. Propose one so your adviser can review it.</p>
        <button type="button" class="btn btn-primary" data-action="propose">Propose a topic</button>
      </div>
      ${previousBlock}
    </article>`;
  }

  const editLabel = topic.status === 'For Revision' ? 'Revise and resubmit' : 'Edit topic';

  return html`<article class="card">
    <div class="card-head">
      <h2>Research topic</h2>
      ${badge(topic.status)}
    </div>
    <p class="status-help">${TOPIC_STATUS[topic.status]?.help ?? ''}</p>
    <h3 class="topic-title">${topic.title}</h3>
    <p class="topic-desc">${topic.description}</p>
    <p class="meta">Submitted ${formatDate(topic.submitted_at)}. Last updated ${formatDate(topic.updated_at)}.</p>
    ${remarksBlock(topic)}
    ${commentsBlock(topic)}
    ${topic.can_edit || topic.can_delete
      ? html`<div class="card-actions">
          ${topic.can_edit ? html`<button type="button" class="btn btn-primary btn-sm" data-action="edit">${editLabel}</button>` : ''}
          ${topic.can_delete
            ? html`<button type="button" class="btn btn-quiet btn-sm" data-action="delete" data-id="${topic.id}">Delete topic</button>`
            : ''}
        </div>`
      : html`<p class="meta">This topic is locked while it is ${topic.status}. Talk to your adviser if it needs to change.</p>`}
    ${topic.can_edit && !topic.can_delete ? html`<p class="meta">Only the group leader can delete the topic.</p>` : ''}
    ${previousBlock}
  </article>`;
};

const groupCard = ({ group }) =>
  html`<article class="card">
    <div class="card-head"><h2>${group.name}</h2></div>
    <p class="meta">
      Adviser:
      ${group.adviser
        ? html`<strong>${group.adviser.full_name}</strong> <a href="mailto:${group.adviser.email}">${group.adviser.email}</a>`
        : 'Not assigned'}
    </p>
    <h3 class="sub-title">Members</h3>
    <ul class="members">
      ${group.members.map(
        (member) => html`<li>
          <span>${member.full_name}</span>
          ${member.is_leader ? html`<span class="role-tag">Leader</span>` : ''}
        </li>`
      )}
    </ul>
  </article>`;

const milestoneCard = ({ milestones }) =>
  html`<article class="card">
    <div class="card-head"><h2>Milestones</h2></div>
    ${milestones.length
      ? html`<ol class="track">
          ${milestones.map((item) => {
            const status = MILESTONE_STATUS[item.status] ?? MILESTONE_STATUS['Not Started'];
            return html`<li class="${status.cls}">
              <span class="track-name">${item.name}</span>
              <span class="track-meta">
                ${status.label}, due ${formatDate(item.due_date)}
                ${item.is_overdue ? html` <strong class="overdue">Overdue</strong>` : ''}
              </span>
            </li>`;
          })}
        </ol>`
      : html`<p class="meta">Your adviser has not set milestones yet.</p>`}
  </article>`;

const noGroupCard = () =>
  html`<article class="card">
    <div class="empty">
      <p class="empty-title">You are not in a group yet</p>
      <p>Your adviser creates the group and adds its students. Once you are added, your group, topic, and milestones will appear here.</p>
    </div>
  </article>`;

const errorCard = (message) =>
  html`<article class="card">
    <div class="empty">
      <p class="empty-title">Could not load your dashboard</p>
      <p>${message}</p>
      <button type="button" class="btn btn-primary" data-action="retry">Try again</button>
    </div>
  </article>`;

const render = () => {
  if (!overview.group) {
    mount(app, noGroupCard());
    return;
  }

  mount(
    app,
    html`<div class="dash-grid">
      ${topicCard(overview)}
      <div class="dash-col">${groupCard(overview)}${milestoneCard(overview)}</div>
    </div>`
  );
};

/* ---------- Loading ---------- */

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('student-overview.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, errorCard(data.message ?? 'Something went wrong. Try again.'));
      return;
    }

    overview = data;
    render();
  } catch (error) {
    mount(app, errorCard('Could not reach the server. Check your connection and try again.'));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/* ---------- Topic form ---------- */

const alertBox = () => topicForm.querySelector('[data-alert]');

const setFieldError = (name, message) => {
  const input = topicForm.elements[name];
  const target = topicForm.querySelector(`[data-error-for="${name}"]`);
  if (!target) return;

  target.textContent = message;
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const updateCounters = () => {
  topicForm.querySelectorAll('[data-count-for]').forEach((counter) => {
    counter.textContent = topicForm.elements[counter.dataset.countFor].value.length;
  });
};

const validateTopic = ({ title, description }) => {
  const errors = {};
  const cleanTitle = title.trim();
  const cleanDescription = description.trim();

  if (cleanTitle.length < 5 || cleanTitle.length > 200) errors.title = 'Use 5 to 200 characters for the title.';
  if (cleanDescription.length < 20 || cleanDescription.length > 2000) {
    errors.description = 'Use 20 to 2000 characters for the description.';
  }
  return errors;
};

const openTopicDialog = (topic = null) => {
  topicForm.reset();
  alertBox().hidden = true;
  ['title', 'description'].forEach((name) => setFieldError(name, ''));

  topicForm.elements.id.value = topic?.id ?? '';
  topicForm.elements.title.value = topic?.title ?? '';
  topicForm.elements.description.value = topic?.description ?? '';

  document.getElementById('topic-dialog-title').textContent = topic ? 'Edit your topic' : 'Propose a topic';
  topicForm.querySelector('[data-submit]').textContent = topic ? 'Save and resubmit' : 'Submit for review';

  updateCounters();
  topicDialog.showModal();
  topicForm.elements.title.focus();
};

topicForm.addEventListener('input', ({ target }) => {
  if (target.name === 'title' || target.name === 'description') setFieldError(target.name, '');
  updateCounters();
});

topicForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  alertBox().hidden = true;

  const payload = Object.fromEntries(new FormData(topicForm));
  const errors = validateTopic(payload);
  ['title', 'description'].forEach((name) => setFieldError(name, errors[name] ?? ''));

  if (Object.keys(errors).length > 0) {
    topicForm.elements[Object.keys(errors)[0]].focus();
    return;
  }

  const editing = payload.id !== '';
  const submit = topicForm.querySelector('[data-submit]');
  const label = submit.textContent;
  submit.disabled = true;
  submit.textContent = 'Saving…';

  try {
    const { response, data } = await postJson(editing ? 'topic-update.php' : 'topic-create.php', payload);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      topicDialog.close();
      toast(data.message);
      await load();
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([name, message]) => setFieldError(name, message));
    alertBox().textContent = data.message ?? 'Something went wrong. Try again.';
    alertBox().hidden = false;
  } catch (error) {
    alertBox().textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox().hidden = false;
  } finally {
    submit.disabled = false;
    submit.textContent = label;
  }
});

/* ---------- Delete ---------- */

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async (event) => {
  const button = event.currentTarget;
  button.disabled = true;

  try {
    const { response, data } = await postJson('topic-delete.php', { id: pendingDeleteId });

    if (response.status === 401) return goToLogin();

    confirmDialog.close();
    toast(data.message ?? 'Something went wrong.', response.ok && data.ok ? 'success' : 'error');
    await load();
  } catch (error) {
    confirmDialog.close();
    toast('Could not reach the server. Try again.', 'error');
  } finally {
    button.disabled = false;
  }
});

/* ---------- Clicks ---------- */

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const actions = {
    propose: () => openTopicDialog(),
    edit: () => openTopicDialog(overview.topic),
    delete: () => {
      pendingDeleteId = Number(button.dataset.id);
      confirmDialog.showModal();
    },
    retry: () => load(),
  };

  actions[button.dataset.action]?.();
});

// Close buttons, and a click on the dark backdrop, close a dialog
[topicDialog, confirmDialog].forEach((dialog) => {
  dialog.addEventListener('click', ({ target }) => {
    if (target === dialog || target.closest('[data-close]')) dialog.close();
  });
});

load();
