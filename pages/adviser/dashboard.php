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
<?php layout_head('Adviser overview', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_topbar($user, 'overview'); ?>

  <main class="shell-main">
    <h1>Welcome, <?= e($user['full_name']) ?></h1>
    <p class="lead">Your groups, topics, and deadlines at a glance.</p>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="skeleton-stack" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/adviser-dashboard.js')) ?>"></script>
</body>
</html>
