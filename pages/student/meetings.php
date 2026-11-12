<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';

$user = require_role_page('student');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head('Meetings', $csrf); ?>
</head>
<body <?php layout_body_data(); ?> data-role="student">
  <?php layout_app_open($user, 'meetings'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Meetings</h1>
        <p class="lead">Ask your adviser for a meeting, and keep a shared log of what you discussed.</p>
      </div>
      <div class="page-actions">
        <button type="button" class="btn btn-primary" data-action="request" hidden>Request a meeting</button>
        <button type="button" class="btn btn-quiet" data-action="log" hidden>Log a meeting</button>
      </div>
    </div>

    <div class="filters" role="tablist" aria-label="Meetings" id="tabs"></div>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="skeleton-stack" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>
  <?php layout_app_close(); ?>

  <dialog id="dlg" class="dialog" aria-labelledby="dlg-title">
    <form id="dlg-form" class="dialog-body" novalidate></form>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(asset_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/meetings.js')) ?>"></script>
</body>
</html>
