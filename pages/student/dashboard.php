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
<?php layout_head('Student dashboard', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_topbar($user, 'dashboard'); ?>

  <main class="shell-main">
    <h1>Welcome, <?= e($user['full_name']) ?></h1>
    <p class="lead">Your group, research topic, and milestones in one place.</p>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="dash-grid" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="dash-col">
          <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
          <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
        </div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use the dashboard.</p></noscript>
    </div>
  </main>

  <!-- Propose / edit topic -->
  <dialog id="topic-dialog" class="dialog" aria-labelledby="topic-dialog-title">
    <form id="topic-form" class="dialog-body" novalidate>
      <h2 id="topic-dialog-title">Propose a topic</h2>
      <p class="lead dialog-lead">Your adviser reviews the topic after you submit it.</p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>
      <input type="hidden" name="id" value="">

      <div class="field">
        <label for="topic-title">Topic title</label>
        <input id="topic-title" name="title" type="text" maxlength="200" autocomplete="off" aria-describedby="title-count title-error" required>
        <p class="hint counter" id="title-count"><span data-count-for="title">0</span> / 200</p>
        <p class="field-error" id="title-error" data-error-for="title"></p>
      </div>

      <div class="field">
        <label for="topic-description">Description</label>
        <textarea id="topic-description" name="description" rows="7" maxlength="2000" aria-describedby="description-count description-error" required></textarea>
        <p class="hint counter" id="description-count"><span data-count-for="description">0</span> / 2000</p>
        <p class="field-error" id="description-error" data-error-for="description"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Submit for review</button>
      </div>
    </form>
  </dialog>

  <!-- Confirm delete -->
  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Delete this topic?</h2>
      <p class="lead dialog-lead">Your adviser will be told the topic was withdrawn. This cannot be undone.</p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Keep topic</button>
        <button type="button" class="btn btn-danger" data-confirm>Delete topic</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/student-dashboard.js')) ?>"></script>
</body>
</html>
