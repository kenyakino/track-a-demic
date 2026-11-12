<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/layout.php';

// Only advisers may open this page
$user = require_role_page('adviser');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head('Files and references', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'files'); ?>

  <main class="shell-main">
    <h1>Files and references</h1>
    <p class="lead">What your groups have saved and uploaded.</p>

    <div class="toolbar" data-toolbar hidden>
      <label class="sr-only" for="group-filter">Show group</label>
      <select id="group-filter" class="control"><option value="all">All groups</option></select>
      <label class="sr-only" for="search">Search files and references</label>
      <input id="search" class="control search" type="search" placeholder="Search files and references" autocomplete="off">
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

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(asset_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/adviser-files.js')) ?>"></script>
</body>
</html>
