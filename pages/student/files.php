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
<?php layout_head('Files', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'files'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Files</h1>
        <p class="lead">Upload your drafts so your adviser can read them.</p>
      </div>
      <button type="button" class="btn btn-primary" data-action="upload" hidden>Upload file</button>
    </div>

    <div class="toolbar" data-toolbar hidden>
      <label class="sr-only" for="search">Search files</label>
      <input id="search" class="control search" type="search" placeholder="Search by file name or milestone" autocomplete="off">
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

  <dialog id="upload-dialog" class="dialog" aria-labelledby="upload-dialog-title">
    <form id="upload-form" class="dialog-body" novalidate>
      <h2 id="upload-dialog-title">Upload a file</h2>
      <p class="lead dialog-lead" id="upload-limits"></p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>

      <div class="field">
        <label for="file-input">File</label>
        <input id="file-input" name="file" type="file" accept=".pdf,.docx" aria-describedby="file-error">
        <p class="field-error" id="file-error" data-error-for="file"></p>
      </div>

      <div class="field">
        <label for="file-milestone">Related milestone <span class="meta-inline">(optional)</span></label>
        <select id="file-milestone" name="milestone_id" class="control" data-milestone-options></select>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Upload file</button>
      </div>
    </form>
  </dialog>

  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Delete this file?</h2>
      <p class="lead dialog-lead" id="confirm-message"></p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Keep it</button>
        <button type="button" class="btn btn-danger" data-confirm>Delete file</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/student-files.js')) ?>"></script>
</body>
</html>
