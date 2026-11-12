/**
 * Student dashboard: loads the group overview with the Fetch API, renders it,
 * and handles creating, editing, and deleting the group's topic.
 */

import { getJson, postJson, postForm, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, formatBytes, toast } from './ui.js';
import { TOPIC_STATUS, badge } from './labels.js';
import { drawChart, milestoneStatusColors } from './charts.js';

const app = document.getElementById('app');
const topicDialog = document.getElementById('topic-dialog');
const topicForm = document.getElementById('topic-form');
const confirmDialog = document.getElementById('confirm-dialog');

let overview = null;
let limits = { max_size: 5 * 1024 * 1024, allowed_ext: ['pdf', 'docx'] };
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

const fileBlock = (topic) =>
  topic.file
    ? html`<p class="meta">Proposal file: <a href="${baseUrl}/api/file-download.php?id=${topic.file.id}">${topic.file.name}</a> (${formatBytes(topic.file.size)})</p>`
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
    ${topic.similar
      ? html`<div class="banner banner-warn" role="status"><strong>Similar topic found.</strong> Another group has proposed a topic with many of the same words. Your adviser will take a look. You may want to make yours more specific.</div>`
      : ''}
    <h3 class="topic-title">${topic.title}</h3>
    <p class="topic-desc">${topic.description}</p>
    <p class="meta">Submitted ${formatDate(topic.submitted_at)}. Last updated ${formatDate(topic.updated_at)}.</p>
    ${fileBlock(topic)}
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
        ? html`<strong>${group.adviser.display_name ?? group.adviser.full_name}</strong> <a href="mailto:${group.adviser.email}">${group.adviser.email}</a>`
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
    <div class="card-head">
      <h2>Milestones</h2>
      <a class="btn btn-quiet btn-sm" href="${baseUrl}/pages/student/milestones.php">Update progress</a>
    </div>
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

const MS_ORDER = ['Not Started', 'In Progress', 'Submitted', 'Needs Rework', 'Completed'];

const progressCard = ({ milestones }) => {
  if (!milestones || milestones.length === 0) return html``;

  const counts = MS_ORDER.map((name) => milestones.filter((m) => m.status === name).length);
  const done = counts[MS_ORDER.indexOf('Completed')];
  const percent = Math.round((done / milestones.length) * 100);
  const summary = MS_ORDER.map((name, i) => `${name}: ${counts[i]}`).join(', ');

  return html`<article class="card">
    <h2>Your progress</h2>
    <p class="chart-figure"><strong>${percent}%</strong> of milestones completed (${done} of ${milestones.length})</p>
    <div class="chart-box chart-box-sm"><canvas id="chart-student" role="img" aria-label="Doughnut chart of your milestones by status. ${summary}"></canvas></div>
    <p class="meta">${summary}</p>
  </article>`;
};

const drawStudentChart = ({ milestones }) => {
  if (!milestones || milestones.length === 0) return;

  drawChart('chart-student', (c) => {
    const colors = milestoneStatusColors(c);
    const names = MS_ORDER.filter((name) => milestones.some((m) => m.status === name));
    return {
      type: 'doughnut',
      data: {
        labels: names,
        datasets: [{ data: names.map((n) => milestones.filter((m) => m.status === n).length), backgroundColor: names.map((n) => colors[n]), borderColor: c.surface, borderWidth: 2 }],
      },
      options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' } } },
    };
  });
};

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
      <div class="dash-col">${topicCard(overview)}${milestoneCard(overview)}</div>
      <div class="dash-col">${groupCard(overview)}${progressCard(overview)}</div>
    </div>`
  );
  drawStudentChart(overview);
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
    limits = data.limits ?? limits;
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

const typesText = () => limits.allowed_ext.map((ext) => ext.toUpperCase()).join(' or ');

/** Checks the chosen file in the browser. The server checks it again. */
const checkFile = (file, required) => {
  if (!file) return required ? 'Choose the proposal file.' : '';

  const extension = file.name.split('.').pop().toLowerCase();
  if (!limits.allowed_ext.includes(extension)) return `Only ${typesText()} files are allowed.`;
  if (file.size === 0) return 'The file is empty.';
  if (file.size > limits.max_size) return `The file is too large. The limit is ${formatBytes(limits.max_size)}.`;
  return '';
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
  ['title', 'description', 'file'].forEach((name) => setFieldError(name, ''));

  topicForm.elements.id.value = topic?.id ?? '';
  topicForm.elements.title.value = topic?.title ?? '';
  topicForm.elements.description.value = topic?.description ?? '';

  // The proposal file is required for a new topic and after a revision request
  const fileRequired = !topic || topic.status === 'For Revision';
  topicForm.elements.file.accept = limits.allowed_ext.map((ext) => `.${ext}`).join(',');
  document.getElementById('topic-file-label').textContent = fileRequired ? 'Proposal file (required)' : 'Replace the proposal file (optional)';
  topicForm.querySelector('[data-file-hint]').textContent = !topic
    ? `${typesText()}, up to ${formatBytes(limits.max_size)}.`
    : fileRequired
      ? `Send the revised file. It replaces ${topic.file?.name ?? 'the previous file'}.`
      : `Leave this empty to keep ${topic.file?.name ?? 'the current file'}.`;

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

topicForm.elements.file.addEventListener('change', () => {
  const chosen = topicForm.elements.file.files[0];
  setFieldError('file', chosen ? checkFile(chosen, false) : '');
});

topicForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  alertBox().hidden = true;

  const payload = {
    id: topicForm.elements.id.value,
    title: topicForm.elements.title.value,
    description: topicForm.elements.description.value,
  };
  const chosen = topicForm.elements.file.files[0];
  const fileRequired = payload.id === '' || overview.topic?.status === 'For Revision';

  const errors = validateTopic(payload);
  const fileProblem = checkFile(chosen, fileRequired);
  if (fileProblem) errors.file = fileProblem;
  ['title', 'description', 'file'].forEach((name) => setFieldError(name, errors[name] ?? ''));

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
    const body = new FormData();
    if (editing) body.append('id', payload.id);
    body.append('title', payload.title);
    body.append('description', payload.description);
    if (chosen) body.append('file', chosen);

    const { response, data } = await postForm(editing ? 'topic-update.php' : 'topic-create.php', body);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      topicDialog.close();
      toast(data.message);
      if (data.similar) toast('A similar topic already exists. Your adviser was told. Consider making yours more specific.', 'error');
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
