<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';

// Students and advisers both have a profile
$user = require_user_page();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head('Profile', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'profile'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Profile</h1>
        <p class="lead">Update your name, email, password, or picture. After you save a change, your profile is locked for 30 days.</p>
      </div>
    </div>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>
  <?php layout_app_close(); ?>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(asset_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(asset_url('assets/js/profile.js')) ?>"></script>
</body>
</html>
