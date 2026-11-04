/**
 * Adviser files and references page: see what each group has saved and
 * uploaded, and download the files. Advisers read here; students edit.
 */

import { getJson, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, formatBytes } from './ui.js';

const app = document.getElementById('app');
const toolbar = document.querySelector('[data-toolbar]');
const groupSelect = document.getElementById('group-filter');
const searchBox = document.getElementById('search');

let groups = [];

const safeLink = (link) => (/^https?:\/\//i.test(link ?? '') ? link : null);
const includes = (text, query) => text.toLowerCase().includes(query);

const referenceItem = (reference) => {
  const link = safeLink(reference.link);

  return html`<li>
    <strong>${reference.title}</strong>
    <span class="meta-inline">${reference.author}${reference.pub_year ? html`, ${reference.pub_year}` : ''}</span>
    ${link ? html`<a href="${link}" target="_blank" rel="noopener noreferrer">${link}</a>` : ''}
  </li>`;
};

const fileRow = (file) => html`<tr>
  <td data-label="File"><span class="file-name">${file.name}</span></td>
  <td data-label="Milestone">${file.milestone_name ?? html`<span class="meta-inline">None</span>`}</td>
  <td data-label="Uploaded by">${file.uploader}</td>
  <td data-label="Date">${formatDate(file.uploaded_at)}</td>
  <td data-label="Size">${formatBytes(file.size)}</td>
  <td data-label="Actions"><a class="btn btn-quiet btn-sm" href="${baseUrl}/api/file-download.php?id=${file.id}">Download</a></td>
</tr>`;

const groupCard = (group, query) => {
  const references = group.references.filter((item) => includes(`${item.title} ${item.author} ${item.pub_year ?? ''}`, query));
  const files = group.files.filter((item) => includes(`${item.name} ${item.milestone_name ?? ''} ${item.uploader}`, query));

  return html`<article class="card">
    <div class="card-head"><h2>${group.name}</h2></div>

    <h3 class="sub-title">References (${references.length})</h3>
    ${references.length
      ? html`<ul class="source-list">${references.map(referenceItem)}</ul>`
      : html`<p class="meta">${query ? 'No references match.' : 'No references saved yet.'}</p>`}

    <h3 class="sub-title">Files (${files.length})</h3>
    ${files.length
      ? html`<table class="table">
          <thead>
            <tr><th scope="col">File</th><th scope="col">Milestone</th><th scope="col">Uploaded by</th><th scope="col">Date</th><th scope="col">Size</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
          </thead>
          <tbody>${files.map(fileRow)}</tbody>
        </table>`
      : html`<p class="meta">${query ? 'No files match.' : 'No files uploaded yet.'}</p>`}
  </article>`;
};

const message = (title, text, button = '') => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${button}
</div></article>`;

const render = () => {
  toolbar.hidden = groups.length === 0;

  if (groups.length === 0) {
    mount(
      app,
      message('No groups yet', 'Create a group and add students. Their references and files appear here.',
        html`<a class="btn btn-primary" href="${baseUrl}/pages/adviser/groups.php">Go to groups</a>`)
    );
    return;
  }

  const query = searchBox.value.trim().toLowerCase();
  const chosen = groupSelect.value;
  const shown = groups.filter((group) => chosen === 'all' || String(group.id) === chosen);

  mount(app, html`<div class="stack">${shown.map((group) => groupCard(group, query))}</div>`);
};

const fillGroupSelect = () => {
  const previous = groupSelect.value || 'all';
  mount(
    groupSelect,
    html`<option value="all">All groups</option>${groups.map((group) => html`<option value="${group.id}">${group.name}</option>`)}`
  );
  groupSelect.value = [...groupSelect.options].some((option) => option.value === previous) ? previous : 'all';
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('adviser-library.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load files', data.message ?? 'Something went wrong. Try again.',
        html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
      return;
    }

    groups = data.groups;
    fillGroupSelect();
    render();
  } catch (error) {
    mount(app, message('Could not load files', 'Could not reach the server. Check your connection and try again.',
      html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

app.addEventListener('click', ({ target }) => {
  if (target.closest('[data-action="retry"]')) load();
});

groupSelect.addEventListener('change', render);
searchBox.addEventListener('input', render);

load();
