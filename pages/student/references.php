<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';

// Only students may open this page
$user = require_role_page('student');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head('References', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'references'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>References</h1>
        <p class="lead">The books, papers, and websites your group uses.</p>
      </div>
      <button type="button" class="btn btn-primary" data-action="add" hidden>Add reference</button>
    </div>

    <div class="toolbar" data-toolbar hidden>
      <label class="sr-only" for="search">Search references</label>
      <input id="search" class="control search" type="search" placeholder="Search by title, author, or year" autocomplete="off">
      <label class="sr-only" for="sort">Sort references</label>
      <select id="sort" class="control">
        <option value="new">Newest added</option>
        <option value="title">Title, A to Z</option>
        <option value="year">Year, newest first</option>
      </select>
    </div>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="skeleton-stack" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>
  <?php layout_app_close(); ?>

  <dialog id="reference-dialog" class="dialog" aria-labelledby="reference-dialog-title">
    <form id="reference-form" class="dialog-body" novalidate>
      <h2 id="reference-dialog-title">Add a reference</h2>
      <p class="lead dialog-lead">Anyone in your group can add, edit, or delete references.</p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>
      <input type="hidden" name="id" value="">

      <div class="field">
        <label for="reference-title">Title</label>
        <input id="reference-title" name="title" type="text" maxlength="255" autocomplete="off" aria-describedby="title-error" required>
        <p class="field-error" id="title-error" data-error-for="title"></p>
      </div>

      <div class="field">
        <label for="reference-author">Author</label>
        <input id="reference-author" name="author" type="text" maxlength="150" autocomplete="off" aria-describedby="author-error" required>
        <p class="field-error" id="author-error" data-error-for="author"></p>
      </div>

      <div class="field">
        <label for="reference-year">Year <span class="meta-inline">(optional)</span></label>
        <input id="reference-year" name="pub_year" type="text" inputmode="numeric" maxlength="4" autocomplete="off" aria-describedby="year-error">
        <p class="field-error" id="year-error" data-error-for="pub_year"></p>
      </div>

      <div class="field">
        <label for="reference-link">Web address <span class="meta-inline">(optional)</span></label>
        <input id="reference-link" name="link" type="text" inputmode="url" maxlength="500" autocomplete="off" aria-describedby="link-error">
        <p class="field-error" id="link-error" data-error-for="link"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Save reference</button>
      </div>
    </form>
  </dialog>

  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Delete this reference?</h2>
      <p class="lead dialog-lead">This cannot be undone.</p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Keep it</button>
        <button type="button" class="btn btn-danger" data-confirm>Delete reference</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/student-references.js')) ?>"></script>
</body>
</html>
