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
<?php layout_head('Milestones', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'milestones'); ?>

  <main class="shell-main">
    <h1>Milestones</h1>
    <p class="lead">Every deadline for your group, and where each one stands.</p>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="skeleton-stack" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>
  <?php layout_app_close(); ?>

  <dialog id="submit-dialog" class="dialog" aria-labelledby="submit-title">
    <form id="submit-form" class="dialog-body" novalidate>
      <h2 id="submit-title">Submit for review</h2>
      <p class="lead dialog-lead" id="submit-subtitle"></p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>
      <input type="hidden" name="id" value="">

      <div class="field">
        <label for="submit-file">File for your adviser to check</label>
        <input id="submit-file" name="file" type="file" accept=".pdf,.docx" aria-describedby="submit-hint file-error">
        <p class="hint" id="submit-hint"></p>
        <p class="field-error" id="file-error" data-error-for="file"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Submit for review</button>
      </div>
    </form>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(asset_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/student-milestones.js')) ?>"></script>
</body>
</html>
