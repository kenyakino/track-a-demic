/**
 * Student files page: upload drafts (PDF or DOCX), search them, download
 * them, and delete them. The server checks every file before keeping it.
 */

import { getJson, postJson, postForm, goToLogin, baseUrl } from './api.js';
import { html, mount, formatDate, formatBytes, toast } from './ui.js';

const app = document.getElementById('app');
const toolbar = document.querySelector('[data-toolbar]');
const searchBox = document.getElementById('search');
const uploadButton = document.querySelector('[data-action="upload"]');
const dialog = document.getElementById('upload-dialog');
const form = document.getElementById('upload-form');
const confirmDialog = document.getElementById('confirm-dialog');

let files = [];
let milestones = [];
let limits = { max_size: 5 * 1024 * 1024, allowed_ext: ['pdf', 'docx'] };
let hasGroup = false;
let pendingDeleteId = null;

/* ---------- Rendering ---------- */

const message = (title, text, button = '') => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${button}
</div></article>`;

const fileRow = (file) => html`<tr>
  <td data-label="File"><span class="file-name">${file.name}</span></td>
  <td data-label="Milestone">${file.milestone_name ?? html`<span class="meta-inline">None</span>`}</td>
  <td data-label="Uploaded by">${file.uploader}</td>
  <td data-label="Date">${formatDate(file.uploaded_at)}</td>
  <td data-label="Size">${formatBytes(file.size)}</td>
  <td data-label="Actions">
    <div class="row-actions">
      <a class="btn btn-quiet btn-sm" href="${baseUrl}/api/file-download.php?id=${file.id}">Download</a>
      ${file.can_delete ? html`<button type="button" class="btn btn-quiet btn-sm" data-action="delete" data-id="${file.id}" data-name="${file.name}">Delete</button>` : ''}
    </div>
  </td>
</tr>`;

const render = () => {
  uploadButton.hidden = !hasGroup;
  toolbar.hidden = !hasGroup || files.length === 0;

  if (!hasGroup) {
    mount(app, message('You are not in a group yet', 'Your adviser creates the group and adds its students. You can upload files once you are added.'));
    return;
  }

  if (files.length === 0) {
    mount(
      app,
      message('No files yet', `Upload your drafts as ${limits.allowed_ext.map((ext) => ext.toUpperCase()).join(' or ')} files, up to ${formatBytes(limits.max_size)} each.`,
        html`<button type="button" class="btn btn-primary" data-action="upload">Upload a file</button>`)
    );
    return;
  }

  const query = searchBox.value.trim().toLowerCase();
  const shown = files.filter((file) => [file.name, file.milestone_name ?? '', file.uploader].join(' ').toLowerCase().includes(query));

  mount(
    app,
    shown.length
      ? html`<article class="card card-flush">
          <p class="result-count" role="status">${shown.length} of ${files.length} files</p>
          <table class="table">
            <thead>
              <tr><th scope="col">File</th><th scope="col">Milestone</th><th scope="col">Uploaded by</th><th scope="col">Date</th><th scope="col">Size</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>${shown.map(fileRow)}</tbody>
          </table>
        </article>`
      : message('Nothing found', `No file matches "${searchBox.value.trim()}".`)
  );
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('student-files.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load your files', data.message ?? 'Something went wrong. Try again.',
        html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
      return;
    }

    hasGroup = Boolean(data.group);
    files = data.files;
    milestones = data.milestones;
    limits = data.limits;
    render();
  } catch (error) {
    mount(app, message('Could not load your files', 'Could not reach the server. Check your connection and try again.',
      html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/* ---------- Upload form ---------- */

const setError = (text) => {
  form.querySelector('[data-error-for="file"]').textContent = text;
  form.elements.file.setAttribute('aria-invalid', text ? 'true' : 'false');
};

const checkFile = (file) => {
  if (!file) return 'Choose a file to upload.';

  const extension = file.name.split('.').pop().toLowerCase();
  if (!limits.allowed_ext.includes(extension)) {
    return `Only ${limits.allowed_ext.map((ext) => ext.toUpperCase()).join(' and ')} files are allowed.`;
  }
  if (file.size === 0) return 'The file is empty.';
  if (file.size > limits.max_size) return `The file is too large. The limit is ${formatBytes(limits.max_size)}.`;
  return '';
};

const openUpload = () => {
  form.reset();
  form.querySelector('[data-alert]').hidden = true;
  setError('');

  document.getElementById('upload-limits').textContent =
    `${limits.allowed_ext.map((ext) => ext.toUpperCase()).join(' or ')} files, up to ${formatBytes(limits.max_size)}.`;
  form.elements.file.accept = limits.allowed_ext.map((ext) => `.${ext}`).join(',');

  mount(
    form.querySelector('[data-milestone-options]'),
    html`<option value="">None</option>${milestones.map((milestone) => html`<option value="${milestone.id}">${milestone.name}</option>`)}`
  );

  dialog.showModal();
  form.elements.file.focus();
};

form.elements.file.addEventListener('change', () => {
  const chosen = form.elements.file.files[0];
  setError(chosen ? checkFile(chosen) : '');
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const alertBox = form.querySelector('[data-alert]');
  alertBox.hidden = true;

  const chosen = form.elements.file.files[0];
  const problem = checkFile(chosen);
  setError(problem);
  if (problem) {
    form.elements.file.focus();
    return;
  }

  const data = new FormData();
  data.append('file', chosen);
  if (form.elements.milestone_id.value) data.append('milestone_id', form.elements.milestone_id.value);

  const submit = form.querySelector('[data-submit]');
  submit.disabled = true;
  submit.textContent = 'Uploading…';

  try {
    const result = await postForm('file-upload.php', data);

    if (result.response.status === 401) return goToLogin();

    if (result.response.ok && result.data.ok) {
      dialog.close();
      toast(result.data.message);
      await load();
      return;
    }

    setError(result.data.errors?.file ?? '');
    alertBox.textContent = result.data.errors?.file ? 'The file was not uploaded.' : result.data.message ?? 'Something went wrong. Try again.';
    alertBox.hidden = false;
  } catch (error) {
    alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox.hidden = false;
  } finally {
    submit.disabled = false;
    submit.textContent = 'Upload file';
  }
});

/* ---------- Delete ---------- */

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async () => {
  confirmDialog.close();

  try {
    const { response, data } = await postJson('file-delete.php', { id: pendingDeleteId });

    if (response.status === 401) return goToLogin();

    toast(data.message ?? 'Something went wrong.', response.ok && data.ok ? 'success' : 'error');
    await load();
  } catch (error) {
    toast('Could not reach the server. Try again.', 'error');
  }
});

/* ---------- Events ---------- */

app.addEventListener('click', ({ target }) => {
  const button = target.closest('[data-action]');
  if (!button) return;

  const actions = {
    retry: load,
    upload: openUpload,
    delete: () => {
      pendingDeleteId = Number(button.dataset.id);
      document.getElementById('confirm-message').textContent = `"${button.dataset.name}" will be deleted for the whole group. This cannot be undone.`;
      confirmDialog.showModal();
    },
  };

  actions[button.dataset.action]?.();
});

uploadButton.addEventListener('click', openUpload);
searchBox.addEventListener('input', render);

[dialog, confirmDialog].forEach((element) => {
  element.addEventListener('click', ({ target }) => {
    if (target === element || target.closest('[data-close]')) element.close();
  });
});

load();
