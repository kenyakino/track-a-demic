/**
 * Adviser overview: totals, things that need attention, and a table of groups.
 */

import { getJson, goToLogin, baseUrl } from './api.js';
import { html, mount } from './ui.js';
import { badge } from './labels.js';

const app = document.getElementById('app');
const pageUrl = (file) => `${baseUrl}/pages/adviser/${file}`;

const ATTENTION_LINKS = {
  review: { file: 'topics.php', label: 'Review topic' },
  members: { file: 'groups.php', label: 'Add students' },
};

const statsBlock = ({ stats }) => html`<dl class="stats">
  <div class="stat"><dt>Groups</dt><dd>${stats.groups}</dd></div>
  <div class="stat"><dt>Students</dt><dd>${stats.students}</dd></div>
  <div class="stat ${stats.pending_review > 0 ? 'stat-alert' : ''}"><dt>Topics to review</dt><dd>${stats.pending_review}</dd></div>
  <div class="stat ${stats.overdue_milestones > 0 ? 'stat-alert' : ''}"><dt>Overdue milestones</dt><dd>${stats.overdue_milestones}</dd></div>
</dl>`;

const attentionCard = ({ attention }) => html`<article class="card">
  <div class="card-head"><h2>Needs your attention</h2></div>
  ${attention.length
    ? html`<ul class="attention">
        ${attention.map((item) => {
          const link = ATTENTION_LINKS[item.type];
          return html`<li>
            <div><strong>${item.group}</strong> <span class="meta-inline">${item.message}</span></div>
            ${link ? html`<a href="${pageUrl(link.file)}">${link.label}</a>` : ''}
          </li>`;
        })}
      </ul>`
    : html`<p class="meta">Nothing needs your attention right now.</p>`}
</article>`;

const milestoneCell = (group) => {
  if (group.ms_total === 0) return html`<span class="meta-inline">Not set</span>`;
  return html`${group.ms_done} of ${group.ms_total} done${group.ms_overdue > 0 ? html` <strong class="overdue">${group.ms_overdue} overdue</strong>` : ''}`;
};

const groupsCard = ({ groups }) => html`<article class="card">
  <div class="card-head">
    <h2>Your groups</h2>
    <a class="btn btn-quiet btn-sm" href="${pageUrl('groups.php')}">Manage groups</a>
  </div>
  ${groups.length
    ? html`<table class="table">
        <thead>
          <tr><th scope="col">Group</th><th scope="col">Students</th><th scope="col">Topic</th><th scope="col">Milestones</th></tr>
        </thead>
        <tbody>
          ${groups.map(
            (group) => html`<tr>
              <td data-label="Group"><strong>${group.name}</strong></td>
              <td data-label="Students">${group.member_count}</td>
              <td data-label="Topic">
                ${group.topic_status
                  ? html`${badge(group.topic_status)}<span class="cell-note">${group.topic_title}</span>`
                  : html`<span class="meta-inline">No topic yet</span>`}
              </td>
              <td data-label="Milestones">${milestoneCell(group)}</td>
            </tr>`
          )}
        </tbody>
      </table>`
    : html`<div class="empty">
        <p class="empty-title">You have no groups yet</p>
        <p>Create a group and add your students to get started.</p>
        <a class="btn btn-primary" href="${pageUrl('groups.php')}">Create a group</a>
      </div>`}
</article>`;

const errorCard = (message) => html`<article class="card">
  <div class="empty">
    <p class="empty-title">Could not load your overview</p>
    <p>${message}</p>
    <button type="button" class="btn btn-primary" data-action="retry">Try again</button>
  </div>
</article>`;

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-overview.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, errorCard(data.message ?? 'Something went wrong. Try again.'));
      return;
    }

    mount(app, html`${statsBlock(data)}<div class="stack">${attentionCard(data)}${groupsCard(data)}</div>`);
  } catch (error) {
    mount(app, errorCard('Could not reach the server. Check your connection and try again.'));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

app.addEventListener('click', ({ target }) => {
  if (target.closest('[data-action="retry"]')) load();
});

load();
