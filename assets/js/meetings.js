/**
 * Meetings page (students and advisers).
 *
 * Requests: a student group asks for a meeting (date, time, purpose, optional
 * link). The adviser accepts or rejects it. Advisers cannot propose meetings.
 * Logs: the group and the adviser both write notes and action items, and
 * everyone in the group can read them.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, formatDate, toast } from './ui.js';

const role = document.body.dataset.role;
const isAdviser = role === 'adviser';

const app = document.getElementById('app');
const tabsBar = document.getElementById('tabs');
const dlg = document.getElementById('dlg');
const form = document.getElementById('dlg-form');
const requestButton = document.querySelector('[data-action="request"]');
const logButton = document.querySelector('[data-action="log"]');

let state = { groups: [], requests: [], logs: [] };
let tab = 'requests';
let submitHandler = null;

const STATUS_BADGE = {
  Pending: 'badge-proposed',
  Accepted: 'badge-approved',
  Rejected: 'badge-rejected',
  Cancelled: 'badge-revision',
};

const pad = (n) => String(n).padStart(2, '0');
const toDateString = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const todayString = () => toDateString(new Date());
const tomorrowString = () => toDateString(new Date(Date.now() + 86400000));

/* ---------- Rendering ---------- */

const message = (title, text, retry = false) => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${retry ? html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>` : ''}
</div></article>`;

const requestCard = (r) => {
  const upcoming = !r.is_past;
  const actions = [];

  if (isAdviser && r.status === 'Pending') {
    actions.push(html`<button type="button" class="btn btn-primary btn-sm" data-action="accept" data-id="${r.id}">Accept</button>`);
    actions.push(html`<button type="button" class="btn btn-quiet btn-sm" data-action="reject" data-id="${r.id}">Reject</button>`);
  } else if (isAdviser && r.status === 'Accepted' && upcoming) {
    actions.push(html`<button type="button" class="btn btn-quiet btn-sm" data-action="adviser-cancel" data-id="${r.id}">Cancel meeting</button>`);
  } else if (!isAdviser && (r.status === 'Pending' || (r.status === 'Accepted' && upcoming))) {
    actions.push(html`<button type="button" class="btn btn-quiet btn-sm" data-action="student-cancel" data-id="${r.id}">${r.status === 'Pending' ? 'Cancel request' : 'Cancel meeting'}</button>`);
  }

  return html`<article class="card meeting-card">
    <div class="card-head">
      <div>
        ${isAdviser ? html`<p class="meta-inline group-label">${r.group_name}</p>` : ''}
        <h2>${r.when}</h2>
      </div>
      <span class="badge ${STATUS_BADGE[r.status]}">${r.status}${r.status === 'Accepted' && r.is_past ? ' (done)' : ''}</span>
    </div>
    <p class="topic-desc">${r.purpose}</p>
    <p class="meta">Requested by ${r.requested_by ?? 'a group member'} for ${r.duration} minutes.</p>
    ${r.link ? html`<p class="meta">Meeting link: <a href="${r.link}" target="_blank" rel="noopener noreferrer">${r.link}</a></p>` : ''}
    ${r.decision_note
      ? html`<div class="remarks ${r.status === 'Accepted' ? '' : 'remarks-warn'}"><strong>${isAdviser ? 'Your note' : 'Adviser note'}</strong><p>${r.decision_note}</p></div>`
      : ''}
    ${r.suggested ? html`<p class="meta"><strong>Suggested time:</strong> ${r.suggested}</p>` : ''}
    ${actions.length ? html`<div class="card-actions">${actions}</div>` : ''}
  </article>`;
};

const logCard = (l) => html`<article class="card meeting-card">
  <div class="card-head">
    <div>
      ${isAdviser ? html`<p class="meta-inline group-label">${l.group_name}</p>` : ''}
      <h2>${formatDate(l.meeting_date)}</h2>
    </div>
    ${l.author_role === 'adviser'
      ? html`<span class="badge badge-approved">Written by adviser</span>`
      : l.confirmed
        ? html`<span class="badge badge-approved">Confirmed by adviser</span>`
        : html`<span class="badge badge-proposed">Waiting for confirmation</span>`}
  </div>
  <p class="meta">Logged by <strong>${l.author_name}</strong>${l.author_role === 'adviser' ? ' (adviser)' : ''}.${l.request_label ? html` Meeting: ${l.request_label}.` : ''}</p>
  <h3 class="sub-title">Notes</h3>
  <p class="topic-desc">${l.notes}</p>
  ${l.action_items ? html`<h3 class="sub-title">Action items</h3><p class="topic-desc">${l.action_items}</p>` : ''}
  <div class="card-actions">
    ${l.can_confirm ? html`<button type="button" class="btn btn-primary btn-sm" data-action="confirm" data-id="${l.id}">Confirm log</button>` : ''}
    ${l.can_reopen ? html`<button type="button" class="btn btn-quiet btn-sm" data-action="reopen" data-id="${l.id}">Reopen for editing</button>` : ''}
    ${l.can_edit ? html`<button type="button" class="btn btn-quiet btn-sm" data-action="edit-log" data-id="${l.id}">Edit</button>
      <button type="button" class="btn btn-quiet btn-sm" data-action="delete-log" data-id="${l.id}">Delete</button>` : ''}
  </div>
</article>`;

const renderTabs = () => {
  const pending = state.requests.filter((r) => r.status === 'Pending').length;
  const needsConfirm = state.logs.filter((l) => l.can_confirm).length;
  const tabs = [
    ['requests', 'Requests', isAdviser ? pending : state.requests.length],
    ['logs', 'Meeting logs', isAdviser ? needsConfirm : state.logs.length],
  ];

  mount(
    tabsBar,
    html`${tabs.map(
      ([value, label, count]) => html`<button type="button" role="tab" class="filter-btn" data-tab="${value}" aria-selected="${tab === value}" aria-pressed="${tab === value}">${label} <span class="filter-count">${count}</span></button>`
    )}`
  );
};

const render = () => {
  const hasGroups = state.groups.length > 0;
  if (requestButton) requestButton.hidden = !hasGroups;
  logButton.hidden = !hasGroups;
  renderTabs();

  if (!hasGroups) {
    mount(
      app,
      isAdviser
        ? message('No groups yet', 'Create a group first. Meeting requests and logs appear here once you have one.')
        : message('You are not in a group yet', 'Your adviser creates the group and adds its students. You can request and log meetings once you are added.')
    );
    return;
  }

  if (tab === 'requests') {
    mount(
      app,
      state.requests.length
        ? html`<div class="meeting-list">${state.requests.map(requestCard)}</div>`
        : message('No meeting requests yet', isAdviser ? 'When a group asks for a meeting, it shows up here for you to accept or reject.' : 'Pick a date and time that works for your group, and say what the meeting is for.')
    );
  } else {
    mount(
      app,
      state.logs.length
        ? html`<div class="meeting-list">${state.logs.map(logCard)}</div>`
        : message('No meeting logs yet', 'After a meeting, write what was discussed and what each person needs to do.')
    );
  }
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('meetings.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load meetings', data.message ?? 'Something went wrong. Try again.', true));
      return;
    }

    state = data;
    render();
  } catch (error) {
    mount(app, message('Could not load meetings', 'Could not reach the server. Check your connection and try again.', true));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/* ---------- Dialog plumbing ---------- */

const alertBox = () => form.querySelector('[data-alert]');

const setError = (name, text) => {
  const target = form.querySelector(`[data-error-for="${name}"]`);
  if (target) target.textContent = text;
  form.elements[name]?.setAttribute?.('aria-invalid', text ? 'true' : 'false');
};

const openDialog = (content, onSubmit, focusName) => {
  mount(form, content);
  submitHandler = onSubmit;
  dlg.showModal();
  if (focusName) form.elements[focusName]?.focus();
};

form.addEventListener('input', ({ target }) => {
  if (target.name) setError(target.name, '');
  form.querySelectorAll('[data-count-for]').forEach((counter) => {
    counter.textContent = form.elements[counter.dataset.countFor].value.length;
  });
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!submitHandler) return;

  const alert = alertBox();
  if (alert) alert.hidden = true;
  form.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));

  const payload = Object.fromEntries(new FormData(form));
  const clientErrors = submitHandler.validate?.(payload) ?? {};
  if (Object.keys(clientErrors).length) {
    Object.entries(clientErrors).forEach(([name, text]) => setError(name, text));
    form.elements[Object.keys(clientErrors)[0]]?.focus();
    return;
  }

  const button = form.querySelector('[data-submit]');
  button.disabled = true;

  try {
    const { response, data } = await postJson(submitHandler.endpoint, submitHandler.build ? submitHandler.build(payload) : payload);

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      dlg.close();
      toast(data.message);
      await load();
      return;
    }

    // The adviser can still accept when two meetings overlap, after a warning
    if (data.warning && submitHandler.onWarning) {
      dlg.close();
      submitHandler.onWarning(data);
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([name, text]) => setError(name, text));
    alert.textContent = data.message ?? 'Something went wrong. Try again.';
    alert.hidden = false;
    if (response.status === 409) await load();
  } catch (error) {
    alert.textContent = 'Could not reach the server. Check your connection and try again.';
    alert.hidden = false;
  } finally {
    button.disabled = false;
  }
});

const header = (title, lead) => html`<h2 id="dlg-title">${title}</h2>
  ${lead ? html`<p class="lead dialog-lead">${lead}</p>` : ''}
  <div class="alert alert-error" data-alert role="alert" hidden></div>`;

const actions = (label, danger = false) => html`<div class="dialog-actions">
  <button type="button" class="btn btn-quiet" data-close>Cancel</button>
  <button type="submit" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-submit>${label}</button>
</div>`;

/* ---------- Request a meeting (students) ---------- */

const openRequestForm = () => {
  openDialog(
    html`${header('Request a meeting', 'Your adviser will accept or reject it. You will get a notification either way.')}
      <div class="form-row">
        <div class="field">
          <label for="f-date">Date</label>
          <input id="f-date" name="date" type="date" min="${todayString()}" value="${tomorrowString()}" aria-describedby="f-date-error" required>
          <p class="field-error" id="f-date-error" data-error-for="date"></p>
        </div>
        <div class="field">
          <label for="f-time">Start time</label>
          <input id="f-time" name="time" type="time" value="09:00" aria-describedby="f-time-error" required>
          <p class="field-error" id="f-time-error" data-error-for="time"></p>
        </div>
      </div>
      <div class="field">
        <label for="f-duration">How long?</label>
        <select id="f-duration" name="duration" class="control">
          ${[15, 30, 45, 60, 90, 120, 180].map((m) => html`<option value="${m}" ${m === 60 ? 'selected' : ''}>${m >= 60 ? `${m / 60} hour${m > 60 ? 's' : ''}` : `${m} minutes`}</option>`)}
        </select>
      </div>
      <div class="field">
        <label for="f-purpose">What is the meeting for?</label>
        <textarea id="f-purpose" name="purpose" rows="3" maxlength="255" aria-describedby="f-purpose-count f-purpose-error" required></textarea>
        <p class="hint counter" id="f-purpose-count"><span data-count-for="purpose">0</span> / 255</p>
        <p class="field-error" id="f-purpose-error" data-error-for="purpose"></p>
      </div>
      <div class="field">
        <label for="f-link">Meeting link <span class="meta-inline">(optional, for an online meeting)</span></label>
        <input id="f-link" name="link" type="url" maxlength="255" placeholder="https://" aria-describedby="f-link-error">
        <p class="field-error" id="f-link-error" data-error-for="link"></p>
      </div>
      ${actions('Send request')}`,
    Object.assign(() => {}, {
      endpoint: 'meeting-request-create.php',
      validate: (p) => {
        const errors = {};
        if (!p.date) errors.date = 'Choose a date.';
        else if (!p.time) errors.time = 'Choose a start time.';
        else if (new Date(`${p.date}T${p.time}`) <= new Date()) errors.date = 'Choose a date and time in the future.';
        if (p.purpose.trim().length < 5) errors.purpose = 'Say what the meeting is for (at least 5 characters).';
        if (p.link.trim() && !/^https?:\/\//i.test(p.link.trim())) errors.link = 'Enter a full link that starts with http:// or https://.';
        return errors;
      },
    }),
    'date'
  );
};

/* ---------- Accept / reject / cancel (advisers) ---------- */

const accept = async (id, force = false) => {
  try {
    const { response, data } = await postJson('meeting-request-decide.php', { id, decision: 'accept', force });
    if (response.status === 401) return goToLogin();

    if (data.warning) {
      openDialog(
        html`${header('This overlaps with another meeting', 'You already accepted a meeting at the same time. You can still accept this one.')}
          <ul class="conflict-list">${data.conflicts.map((c) => html`<li><strong>${c.group_name}</strong>: ${c.when}</li>`)}</ul>
          ${actions('Accept anyway')}`,
        Object.assign(() => {}, { endpoint: 'meeting-request-decide.php', build: () => ({ id, decision: 'accept', force: true }) })
      );
      return;
    }

    toast(data.message ?? 'Something went wrong.', response.ok && data.ok ? 'success' : 'error');
    await load();
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
  }
};

const openRejectForm = (request) => {
  openDialog(
    html`${header('Reject this request', `${request.group_name}: ${request.when}`)}
      <div class="field">
        <label for="f-note">Reason</label>
        <textarea id="f-note" name="note" rows="3" maxlength="500" aria-describedby="f-note-error" required></textarea>
        <p class="field-error" id="f-note-error" data-error-for="note"></p>
      </div>
      <fieldset class="field-group">
        <legend>Suggest another time <span class="meta-inline">(optional)</span></legend>
        <div class="form-row">
          <div class="field">
            <label for="f-sdate">Date</label>
            <input id="f-sdate" name="suggested_date" type="date" min="${todayString()}" aria-describedby="f-sdate-error">
            <p class="field-error" id="f-sdate-error" data-error-for="suggested_date"></p>
          </div>
          <div class="field">
            <label for="f-stime">Start time</label>
            <input id="f-stime" name="suggested_time" type="time">
          </div>
        </div>
      </fieldset>
      ${actions('Reject request', true)}`,
    Object.assign(() => {}, {
      endpoint: 'meeting-request-decide.php',
      build: (p) => ({ ...p, id: request.id, decision: 'reject' }),
      validate: (p) => {
        const errors = {};
        if (!p.note.trim()) errors.note = 'Write a reason so the group knows why.';
        if ((p.suggested_date && !p.suggested_time) || (!p.suggested_date && p.suggested_time)) {
          errors.suggested_date = 'Choose both a date and a time, or leave both empty.';
        }
        return errors;
      },
    }),
    'note'
  );
};

const openAdviserCancel = (request) => {
  openDialog(
    html`${header('Cancel this meeting', `${request.group_name}: ${request.when}`)}
      <div class="field">
        <label for="f-note">Reason</label>
        <textarea id="f-note" name="note" rows="3" maxlength="500" aria-describedby="f-note-error" required></textarea>
        <p class="field-error" id="f-note-error" data-error-for="note"></p>
      </div>
      ${actions('Cancel meeting', true)}`,
    Object.assign(() => {}, {
      endpoint: 'meeting-request-cancel.php',
      build: (p) => ({ id: request.id, note: p.note }),
      validate: (p) => (p.note.trim() ? {} : { note: 'Write a reason so the group knows why.' }),
    }),
    'note'
  );
};

const openConfirmDialog = (title, text, label, endpoint, payload) => {
  openDialog(
    html`${header(title, text)}${actions(label, true)}`,
    Object.assign(() => {}, { endpoint, build: () => payload })
  );
};

/* ---------- Meeting logs (both roles) ---------- */

const openLogForm = (log = null) => {
  const groupId = log?.group_id ?? state.groups[0]?.id;
  const acceptedFor = (gid) => state.requests.filter((r) => r.status === 'Accepted' && r.group_id === Number(gid));

  const requestOptions = (gid, selected) => html`<option value="">Not linked to a request</option>
    ${acceptedFor(gid).map((r) => html`<option value="${r.id}" ${selected === r.id ? 'selected' : ''}>${r.when}: ${r.purpose.slice(0, 50)}</option>`)}`;

  openDialog(
    html`${header(log ? 'Edit meeting log' : 'Log a meeting', isAdviser ? 'Your group can read this log. Logs you write need no confirmation.' : 'Your adviser can read this log and confirms the ones the group writes. Once confirmed, it can no longer be edited.')}
      ${isAdviser
        ? html`<div class="field">
            <label for="f-group">Group</label>
            <select id="f-group" name="group_id" class="control" ${log ? 'disabled' : ''}>
              ${state.groups.map((g) => html`<option value="${g.id}" ${g.id === groupId ? 'selected' : ''}>${g.name}</option>`)}
            </select>
            <p class="field-error" data-error-for="group_id"></p>
          </div>`
        : ''}
      <div class="field">
        <label for="f-meeting-date">Meeting date</label>
        <input id="f-meeting-date" name="meeting_date" type="date" max="${todayString()}" value="${log?.meeting_date ?? todayString()}" aria-describedby="f-meeting_date-error" required>
        <p class="field-error" id="f-meeting_date-error" data-error-for="meeting_date"></p>
      </div>
      <div class="field">
        <label for="f-request">Accepted meeting <span class="meta-inline">(optional)</span></label>
        <select id="f-request" name="request_id" class="control">${requestOptions(groupId, log?.request_id)}</select>
        <p class="field-error" data-error-for="request_id"></p>
      </div>
      <div class="field">
        <label for="f-notes">What was discussed?</label>
        <textarea id="f-notes" name="notes" rows="5" maxlength="2000" aria-describedby="f-notes-count f-notes-error" required>${log?.notes ?? ''}</textarea>
        <p class="hint counter" id="f-notes-count"><span data-count-for="notes">${(log?.notes ?? '').length}</span> / 2000</p>
        <p class="field-error" id="f-notes-error" data-error-for="notes"></p>
      </div>
      <div class="field">
        <label for="f-actions">Action items <span class="meta-inline">(optional, one per line)</span></label>
        <textarea id="f-actions" name="action_items" rows="3" maxlength="1000" aria-describedby="f-actions-count f-actions-error">${log?.action_items ?? ''}</textarea>
        <p class="hint counter" id="f-actions-count"><span data-count-for="action_items">${(log?.action_items ?? '').length}</span> / 1000</p>
        <p class="field-error" id="f-actions-error" data-error-for="action_items"></p>
      </div>
      ${actions('Save log')}`,
    Object.assign(() => {}, {
      endpoint: 'meeting-save.php',
      build: (p) => ({ ...p, id: log?.id ?? 0, group_id: Number(log?.group_id ?? p.group_id) || undefined, request_id: Number(p.request_id) || 0 }),
      validate: (p) => {
        const errors = {};
        if (!p.meeting_date) errors.meeting_date = 'Choose the meeting date.';
        else if (p.meeting_date > todayString()) errors.meeting_date = 'The meeting date cannot be in the future.';
        if (p.notes.trim().length < 10 || p.notes.trim().length > 2000) errors.notes = 'Use 10 to 2000 characters for the notes.';
        if (p.action_items.trim().length > 1000) errors.action_items = 'Use 1000 characters or fewer.';
        return errors;
      },
    }),
    'meeting_date'
  );

  // The list of accepted meetings follows the chosen group
  form.elements.group_id?.addEventListener('change', () => {
    mount(form.elements.request_id.closest('.field').querySelector('select'), requestOptions(form.elements.group_id.value, null));
  });
};

/* ---------- Clicks ---------- */

app.addEventListener('click', async ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const id = Number(button.dataset.id);
  const request = state.requests.find((r) => r.id === id);
  const log = state.logs.find((l) => l.id === id);

  switch (button.dataset.action) {
    case 'retry': return load();
    case 'accept': return accept(id);
    case 'reject': return openRejectForm(request);
    case 'adviser-cancel': return openAdviserCancel(request);
    case 'student-cancel':
      return openConfirmDialog(
        request.status === 'Pending' ? 'Cancel this request?' : 'Cancel this meeting?',
        `${request.when}. Your adviser will be told.`, 'Yes, cancel', 'meeting-request-cancel.php', { id });
    case 'edit-log': return openLogForm(log);
    case 'delete-log':
      return openConfirmDialog('Delete this meeting log?', 'This cannot be undone.', 'Delete log', 'meeting-delete.php', { id });
    case 'confirm':
    case 'reopen': {
      button.disabled = true;
      try {
        const { response, data } = await postJson('meeting-confirm.php', { id, confirmed: button.dataset.action === 'confirm' });
        if (response.status === 401) return goToLogin();
        toast(data.message ?? 'Something went wrong.', response.ok && data.ok ? 'success' : 'error');
        await load();
      } catch (error) {
        toast('Could not reach the server. Try again.', 'error');
        button.disabled = false;
      }
      return;
    }
    default:
  }
});

requestButton?.addEventListener('click', openRequestForm);
logButton.addEventListener('click', () => openLogForm());

tabsBar.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-tab]');
  if (!button) return;
  tab = button.dataset.tab;
  render();
});

dlg.addEventListener('click', ({ target }) => {
  if (target === dlg || target.closest('[data-close]')) dlg.close();
});

load();
