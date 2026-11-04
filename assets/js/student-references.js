/**
 * Student references page: keep the group's sources, search them,
 * sort them, and add, edit, or delete them.
 */

import { getJson, postJson, goToLogin } from './api.js';
import { html, mount, toast } from './ui.js';

const app = document.getElementById('app');
const toolbar = document.querySelector('[data-toolbar]');
const searchBox = document.getElementById('search');
const sortBox = document.getElementById('sort');
const addButton = document.querySelector('[data-action="add"]');
const dialog = document.getElementById('reference-dialog');
const form = document.getElementById('reference-form');
const confirmDialog = document.getElementById('confirm-dialog');

let references = [];
let hasGroup = false;
let pendingDeleteId = null;

/* ---------- Search and sort ---------- */

const matches = (reference, query) =>
  [reference.title, reference.author, reference.pub_year ?? '', reference.link ?? '']
    .join(' ')
    .toLowerCase()
    .includes(query);

const SORTERS = {
  new: (a, b) => b.id - a.id,
  title: (a, b) => a.title.localeCompare(b.title),
  year: (a, b) => (b.pub_year ?? 0) - (a.pub_year ?? 0) || a.title.localeCompare(b.title),
};

const visibleReferences = () => {
  const query = searchBox.value.trim().toLowerCase();
  return references.filter((reference) => matches(reference, query)).sort(SORTERS[sortBox.value] ?? SORTERS.new);
};

/* ---------- Rendering ---------- */

const safeLink = (link) => (/^https?:\/\//i.test(link ?? '') ? link : null);

const referenceCard = (reference) => {
  const link = safeLink(reference.link);

  return html`<article class="card reference-card">
    <h2 class="reference-title">${reference.title}</h2>
    <p class="meta">${reference.author}${reference.pub_year ? html`, ${reference.pub_year}` : ''}</p>
    ${link ? html`<p class="meta"><a href="${link}" target="_blank" rel="noopener noreferrer">${link}</a></p>` : ''}
    <div class="card-actions">
      <button type="button" class="btn btn-quiet btn-sm" data-action="edit" data-id="${reference.id}">Edit</button>
      <button type="button" class="btn btn-quiet btn-sm" data-action="delete" data-id="${reference.id}">Delete</button>
    </div>
  </article>`;
};

const message = (title, text, button = '') => html`<article class="card"><div class="empty">
  <p class="empty-title">${title}</p>
  <p>${text}</p>
  ${button}
</div></article>`;

const render = () => {
  addButton.hidden = !hasGroup;
  toolbar.hidden = !hasGroup || references.length === 0;

  if (!hasGroup) {
    mount(app, message('You are not in a group yet', 'Your adviser creates the group and adds its students. You can save references once you are added.'));
    return;
  }

  if (references.length === 0) {
    mount(
      app,
      message('No references yet', 'Save the books, papers, and websites your group uses so your adviser can see them.',
        html`<button type="button" class="btn btn-primary" data-action="add">Add a reference</button>`)
    );
    return;
  }

  const shown = visibleReferences();
  const query = searchBox.value.trim();

  mount(
    app,
    shown.length
      ? html`<p class="result-count" role="status">${shown.length} of ${references.length} references</p>
          <div class="reference-list">${shown.map(referenceCard)}</div>`
      : message('Nothing found', `No reference matches "${query}".`)
  );
};

const load = async () => {
  app.setAttribute('aria-busy', 'true');

  try {
    const { response, data } = await getJson('student-references.php');

    if (response.status === 401) return goToLogin();

    if (!response.ok || !data.ok) {
      mount(app, message('Could not load your references', data.message ?? 'Something went wrong. Try again.',
        html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
      return;
    }

    hasGroup = Boolean(data.group);
    references = data.references;
    render();
  } catch (error) {
    mount(app, message('Could not load your references', 'Could not reach the server. Check your connection and try again.',
      html`<button type="button" class="btn btn-primary" data-action="retry">Try again</button>`));
  } finally {
    app.setAttribute('aria-busy', 'false');
  }
};

/* ---------- Reference form ---------- */

const FIELDS = ['title', 'author', 'pub_year', 'link'];

const setError = (name, text) => {
  const input = form.elements[name];
  const target = form.querySelector(`[data-error-for="${name}"]`);
  if (target) target.textContent = text;
  if (input) input.setAttribute('aria-invalid', text ? 'true' : 'false');
};

const openForm = (reference = null) => {
  form.reset();
  form.querySelector('[data-alert]').hidden = true;
  FIELDS.forEach((name) => setError(name, ''));

  form.elements.id.value = reference?.id ?? '';
  form.elements.title.value = reference?.title ?? '';
  form.elements.author.value = reference?.author ?? '';
  form.elements.pub_year.value = reference?.pub_year ?? '';
  form.elements.link.value = reference?.link ?? '';

  document.getElementById('reference-dialog-title').textContent = reference ? 'Edit reference' : 'Add a reference';
  dialog.showModal();
  form.elements.title.focus();
};

const validate = ({ title, author, pub_year: year, link }) => {
  const errors = {};
  const nextYear = new Date().getFullYear() + 1;

  if (title.trim().length < 3 || title.trim().length > 255) errors.title = 'Use 3 to 255 characters for the title.';
  if (author.trim().length < 2 || author.trim().length > 150) errors.author = 'Use 2 to 150 characters for the author.';
  if (year.trim() !== '' && (!/^\d{4}$/.test(year.trim()) || Number(year) < 1900 || Number(year) > nextYear)) {
    errors.pub_year = `Enter a year between 1900 and ${nextYear}, or leave it empty.`;
  }
  if (link.trim() !== '' && !/^https?:\/\/\S+$/i.test(link.trim())) {
    errors.link = 'Enter a full web address that starts with http:// or https://, or leave it empty.';
  }
  return errors;
};

form.addEventListener('input', ({ target }) => {
  if (FIELDS.includes(target.name)) setError(target.name, '');
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const alertBox = form.querySelector('[data-alert]');
  alertBox.hidden = true;

  const payload = Object.fromEntries(new FormData(form));
  const errors = validate(payload);
  FIELDS.forEach((name) => setError(name, errors[name] ?? ''));

  if (Object.keys(errors).length > 0) {
    form.elements[Object.keys(errors)[0]].focus();
    return;
  }

  const submit = form.querySelector('[data-submit]');
  submit.disabled = true;

  try {
    const { response, data } = await postJson('reference-save.php', { ...payload, id: Number(payload.id) || 0 });

    if (response.status === 401) return goToLogin();

    if (response.ok && data.ok) {
      dialog.close();
      toast(data.message);
      await load();
      return;
    }

    Object.entries(data.errors ?? {}).forEach(([name, text]) => setError(name, text));
    alertBox.textContent = data.message ?? 'Something went wrong. Try again.';
    alertBox.hidden = false;
  } catch (error) {
    alertBox.textContent = 'Could not reach the server. Check your connection and try again.';
    alertBox.hidden = false;
  } finally {
    submit.disabled = false;
  }
});

/* ---------- Delete ---------- */

confirmDialog.querySelector('[data-confirm]').addEventListener('click', async () => {
  confirmDialog.close();

  try {
    const { response, data } = await postJson('reference-delete.php', { id: pendingDeleteId });

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

  const id = Number(button.dataset.id);
  const actions = {
    retry: load,
    add: () => openForm(),
    edit: () => openForm(references.find((reference) => reference.id === id)),
    delete: () => {
      pendingDeleteId = id;
      confirmDialog.showModal();
    },
  };

  actions[button.dataset.action]?.();
});

addButton.addEventListener('click', () => openForm());
searchBox.addEventListener('input', render);
sortBox.addEventListener('change', render);

[dialog, confirmDialog].forEach((element) => {
  element.addEventListener('click', ({ target }) => {
    if (target === element || target.closest('[data-close]')) element.close();
  });
});

load();
