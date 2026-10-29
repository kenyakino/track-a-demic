/**
 * Adviser topic reviews: filter topics, then approve, ask for changes, or
 * reject a proposal, move it along, and comment for the group.
 */

import { getJson, postJson, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';
import { badge } from './labels.js';

const app = document.getElementById('app');
const filterBar = document.getElementById('filters');
const dialog = document.getElementById('review-dialog');
const body = document.getElementById('review-body');

const FILTERS = [
  ['all', 'All'],
  ['Proposed', 'Needs review'],
  ['For Revision', 'For revision'],
  ['Approved', 'Approved'],
  ['In Progress', 'In progress'],
  ['Defended', 'Defended'],
  ['Rejected', 'Rejected'],
];

const ACTIONS = {
  approve: { label: 'Approve topic', cls: 'btn-primary' },
  revise: { label: 'Request revision', cls: 'btn-quiet' },
  reject: { label: 'Reject topic', cls: 'btn-danger' },
  start: { label: 'Mark as In Progress', cls: 'btn-primary' },
  defend: { label: 'Mark as Defended', cls: 'btn-primary' },
};

const REMARKS_REQUIRED = ['revise', 'reject'];

let topics = [];
let activeFilter = 'all';
let openId = null;

const shorten = (text, max = 150) => (text.length > max ? `${text.slice(0, max).trimEnd()}...` : text);
const findTopic = (id) => topics.find((topic) => topic.id === id);

/* ---------- Topic list ---------- */

const renderFilters = () => {
  mount(
    filterBar,
    html`${FILTERS.map(([value, label]) => {
      const count = value === 'all' ? topics.length : topics.filter((topic) => topic.status === value).length;
      return html`<button type="button" class="filter-btn" data-filter="${value}" aria-pressed="${activeFilter === value}">${label} <span class="filter-count">${count}</span></button>`;
    })}`
  );
};

const topicRow = (topic) => html`<article class="card topic-row">
  <div class="card-head">
    <p class="meta-inline group-label">${topic.group_name}</p>
    ${badge(topic.status)}
  </div>
  <h2 class="topic-row-title">${topic.title}</h2>
  <p class="topic-excerpt">${shorten(topic.description)}</p>
  <p class="meta">Submitted ${formatDate(topic.submitted_at)}. ${topic.comments.length} ${topic.comments.length === 1 ? 'comment' : 'comments'}.</p>
  <div class="card-actions">
    <button type="button" class="btn ${topic.actions.length ? 'btn-primary' : 'btn-quiet'} btn-sm" data-action="open" data-id="${topic.id}">
      ${topic.actions.length ? 'Review' : 'View'}
    </button>
  </div>
</article>`;

const renderList = () => {
  renderFilters();

  if (topics.length === 0) {
    mount(
      app,
      html`<article class="card"><div class="empty">
        <p class="empty-title">No topics yet</p>
        <p>Topics show up here when your groups propose them.</p>
        <a class="btn btn-primary" href="${baseUrl}/pages/adviser/groups.php">Manage groups</a>
      </div></article>`
    );
    return;
  }

  const shown = activeFilter === 'all' ? topics : topics.filter((topic) => topic.status === activeFilter);

  mount(
    app,
    shown.length
      ? html`<div class="topic-list">${shown.map(topicRow)}</div>`
      : html`<article class="card"><p class="meta">No topics with this status.</p></article>`
  );
};

/* ---------- Review dialog ---------- */

const renderDialog = () => {
  const topic = findTopic(openId);
  if (!topic) {
    dialog.close();
    return;
  }

  const waiting = topic.status === 'For Revision';

  mount(
    body,
    html`<p class="meta-inline group-label">${topic.group_name}</p>
      <div class="card-head dialog-head">
        <h2 id="review-title">${topic.title}</h2>
        ${badge(topic.status)}
      </div>
      <p class="topic-desc">${topic.description}</p>
      <p class="meta">Submitted ${formatDate(topic.submitted_at)}. Last updated ${formatDate(topic.updated_at)}.</p>
      ${topic.adviser_remarks
        ? html`<div class="remarks ${waiting ? 'remarks-warn' : ''}"><strong>Your remarks</strong><p>${topic.adviser_remarks}</p></div>`
        : ''}

      <section class="dialog-section">
        <h3 class="sub-title">Decision</h3>
        ${topic.actions.length
          ? html`<div class="alert alert-error" data-alert role="alert" hidden></div>
              <div class="field">
                <label for="remarks">Remarks for the group ${topic.actions.some((a) => REMARKS_REQUIRED.includes(a)) ? html`<span class="meta-inline">(required to request a revision or reject)</span>` : html`<span class="meta-inline">(optional)</span>`}</label>
                <textarea id="remarks" name="remarks" rows="4" maxlength="2000" aria-describedby="remarks-error"></textarea>
                <p class="field-error" id="remarks-error" data-error-for="remarks"></p>
              </div>
              <div class="decision-buttons">
                ${topic.actions.map((action) => html`<button type="button" class="btn ${ACTIONS[action].cls}" data-decision="${action}">${ACTIONS[action].label}</button>`)}
              </div>`
          : html`<p class="meta">${waiting ? 'Waiting for the group to revise and resubmit this topic.' : 'No further decisions are needed for this topic.'}</p>`}
      </section>

      <section class="dialog-section">
        <h3 class="sub-title">Comments (${topic.comments.length})</h3>
        ${topic.comments.length
          ? html`<ul class="comment-list">${topic.comments.map(
              (comment) => html`<li>
                <div class="comment-head"><strong>${comment.author}</strong><span class="meta-inline">${formatDate(comment.created_at)}</span></div>
                <p>${comment.text}</p>
              </li>`
            )}</ul>`
          : html`<p class="meta">No comments yet.</p>`}
        <div class="field">
          <label for="comment-text">Add a comment for the group</label>
          <textarea id="comment-text" name="comment_text" rows="3" maxlength="1000" aria-describedby="comment-error"></textarea>
          <p class="field-error" id="comment-error" data-error-for="comment_text"></p>
        </div>
        <button type="button" class="btn btn-quiet btn-sm" data-post-comment>Post comment</button>
      </section>

      <div class="dialog-actions"><button type="button" class="btn btn-quiet" data-close>Close</button></div>`
  );
};

const openDialog = (id) => {
  openId = id;
  renderDialog();
  if (!dialog.open) dialog.showModal();
};

const fieldError = (name, message) => {
  const target = body.querySelector(`[data-error-for="${name}"]`);
  if (target) target.textContent = message;
};

/** Sends a review or comment request and refreshes the data. */
const send = async (file, payload, { closeOnSuccess = false } = {}) => {
  try {
    const { response, data } = await postJson(file, payload);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      toast(data.message);
      await load();
      if (closeOnSuccess) dialog.close();
      return true;
    }

    Object.entries(data.errors ?? {}).forEach(([name, message]) => fieldError(name, message));
    if (!data.errors) toast(data.message ?? 'Something went wrong.', 'error');
    if (response.status === 409) await load();
    return false;
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
    return false;
  }
};

body.addEventListener('click', async ({ target }) => {
  const decisionButton = target.closest('[data-decision]');
  const commentButton = target.closest('[data-post-comment]');

  if (decisionButton) {
    const action = decisionButton.dataset.decision;
    const remarks = body.querySelector('#remarks').value.trim();

    fieldError('remarks', '');
    if (REMARKS_REQUIRED.includes(action) && remarks === '') {
      fieldError('remarks', 'Write remarks so the group knows why.');
      body.querySelector('#remarks').focus();
      return;
    }

    body.querySelectorAll('[data-decision]').forEach((button) => (button.disabled = true));
    const ok = await send('topic-review.php', { id: openId, action, remarks }, { closeOnSuccess: true });
    if (!ok) body.querySelectorAll('[data-decision]').forEach((button) => (button.disabled = false));
  }

  if (commentButton) {
    const text = body.querySelector('#comment-text').value.trim();

    fieldError('comment_text', '');
    if (text === '') {
      fieldError('comment_text', 'Write a comment first.');
      body.querySelector('#comment-text').focus();
      return;
    }

    commentButton.disabled = true;
    const ok = await send('topic-comment-add.php', { topic_id: openId, comment_text: text });
    if (ok) renderDialog();
    else commentButton.disabled = false;
  }
});

dialog.addEventListener('click', ({ target }) => {
  if (target === dialog || target.closest('[data-close]')) dialog.close();
});

/* ---------- Loading and clicks ---------- */

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-topics.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(
        app,
        html`<article class="card"><div class="empty">
          <p class="empty-title">Could not load topics</p><p>${data.message ?? 'Something went wrong. Try again.'}</p>
          <button type="button" class="btn btn-primary" data-action="retry">Try again</button>
        </div></article>`
      );
      return;
    }

    topics = data.topics;
    renderList();
    if (dialog.open) renderDialog();
  } catch (error) {
    mount(
      app,
      html`<article class="card"><div class="empty">
        <p class="empty-title">Could not load topics</p><p>Could not reach the server. Check your connection and try again.</p>
        <button type="button" class="btn btn-primary" data-action="retry">Try again</button>
      </div></article>`
    );
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  if (button.dataset.action === 'open') openDialog(Number(button.dataset.id));
  if (button.dataset.action === 'retry') load();
});

filterBar.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-filter]');
  if (!button) return;

  activeFilter = button.dataset.filter;
  renderList();
});

load();
