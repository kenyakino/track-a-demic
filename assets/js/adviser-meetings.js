/**
 * Adviser meetings page: read the meeting logs from your groups and confirm
 * them. A confirmed meeting is locked for the students.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';

const app = document.getElementById('app');
const filterBar = document.getElementById('filters');

const FILTERS = [
  ['all', 'All'],
  ['pending', 'Needs confirmation'],
  ['confirmed', 'Confirmed'],
];

let meetings = [];
let activeFilter = 'pending';

const matches = (meeting) =>
  activeFilter === 'all' || (activeFilter === 'pending' ? !meeting.confirmed : meeting.confirmed);

const renderFilters = () => {
  const count = (value) => meetings.filter((meeting) => (value === 'all' ? true : value === 'pending' ? !meeting.confirmed : meeting.confirmed)).length;

  mount(
    filterBar,
    html`${FILTERS.map(
      ([value, label]) => html`<button type="button" class="filter-btn" data-filter="${value}" aria-pressed="${activeFilter === value}">${label} <span class="filter-count">${count(value)}</span></button>`
    )}`
  );
};

const meetingCard = (meeting) => html`<article class="card meeting-card">
  <div class="card-head">
    <div>
      <p class="meta-inline group-label">${meeting.group_name}</p>
      <h2>${formatDate(meeting.meeting_date)}</h2>
    </div>
    ${meeting.confirmed
      ? html`<span class="badge badge-approved">Confirmed</span>`
      : html`<span class="badge badge-proposed">Waiting for confirmation</span>`}
  </div>
  <h3 class="sub-title">Notes</h3>
  <p class="topic-desc">${meeting.notes}</p>
  ${meeting.action_items ? html`<h3 class="sub-title">Action items</h3><p class="topic-desc">${meeting.action_items}</p>` : ''}
  <div class="card-actions">
    ${meeting.confirmed
      ? html`<button type="button" class="btn btn-quiet btn-sm" data-action="reopen" data-id="${meeting.id}">Reopen for editing</button>`
      : html`<button type="button" class="btn btn-primary btn-sm" data-action="confirm" data-id="${meeting.id}">Confirm meeting</button>`}
  </div>
</article>`;

const message = (title, text, retry = false) => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${retry ? html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>` : ''}
</div></article>`;

const render = () => {
  renderFilters();

  if (meetings.length === 0) {
    mount(app, message('No meeting logs yet', 'When your groups log a meeting with you, it shows up here for confirmation.'));
    return;
  }

  const shown = meetings.filter(matches);
  mount(
    app,
    shown.length
      ? html`<div class="meeting-list">${shown.map(meetingCard)}</div>`
      : html`<article class="card"><p class="meta">Nothing in this list.</p></article>`
  );
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-meetings.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load meetings', data.message ?? 'Something went wrong. Try again.', true));
      return;
    }

    meetings = data.meetings;
    render();
  } catch (error) {
    mount(app, message('Could not load meetings', 'Could not reach the server. Check your connection and try again.', true));
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
    const { response, data } = await postJson('meeting-confirm.php', {
      id: Number(button.dataset.id),
      confirmed: button.dataset.action === 'confirm',
    });

    if (response.status === 401) return goToLogin();

    const ok = response.ok && data.ok;
    toast(data.message ?? 'Something went wrong.', ok ? 'success' : 'error');
    await load();
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
    button.disabled = false;
  }
});

filterBar.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-filter]');
  if (!button) return;

  activeFilter = button.dataset.filter;
  render();
});

load();
