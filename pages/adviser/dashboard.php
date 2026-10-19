<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

// Only advisers may open this page
$user = require_role_page('adviser');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <meta name="color-scheme" content="light dark">
  <title>Dashboard | Track-a-Demic</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body data-base-url="<?= e(rtrim((string) (app_config()['app']['base_url'] ?? ''), '/')) ?>">
  <header class="topbar">
    <a class="brand" href="<?= e(app_url(dashboard_path($user['role']))) ?>">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <path d="M5 22h8a4 4 0 0 0 4-4V10a4 4 0 0 1 4-4h2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="5" cy="22" r="3" fill="#F2B705"/>
        <circle cx="23" cy="6" r="3" fill="currentColor"/>
      </svg>
      Track-a-Demic
    </a>
    <div class="topbar-user">
      <span class="topbar-name"><?= e($user['full_name']) ?></span>
      <span class="role-tag"><?= e(ucfirst($user['role'])) ?></span>
      <button type="button" class="btn btn-quiet" data-logout>Sign out</button>
    </div>
  </header>

  <main class="shell-main">
    <h1>Welcome, <?= e($user['full_name']) ?></h1>
    <p class="lead">Your groups, topic reviews, and milestones will appear here.</p>
  </main>

  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
