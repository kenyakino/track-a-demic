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
<?php layout_head('Dashboard', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <header class="topbar">
    <?php layout_brand(app_url(dashboard_path($user['role']))); ?>
    <div class="topbar-user">
      <span class="topbar-name"><?= e($user['full_name']) ?></span>
      <span class="role-tag"><?= e(ucfirst($user['role'])) ?></span>
      <?php layout_theme_toggle(); ?>
      <button type="button" class="btn btn-quiet btn-sm" data-logout>Sign out</button>
    </div>
  </header>

  <main class="shell-main">
    <h1>Welcome, <?= e($user['full_name']) ?></h1>
    <p class="lead">Your group, topic, and milestones will appear here.</p>
  </main>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
