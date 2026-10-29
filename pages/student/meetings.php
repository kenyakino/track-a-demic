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
<?php layout_head('Meetings', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'meetings'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Meetings</h1>
        <p class="lead">Log what you discussed with your adviser and what happens next.</p>
      </div>
      <button type="button" class="btn btn-primary" data-action="log" hidden>Log a meeting</button>
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

  <dialog id="meeting-dialog" class="dialog" aria-labelledby="meeting-dialog-title">
    <form id="meeting-form" class="dialog-body" novalidate>
      <h2 id="meeting-dialog-title">Log a meeting</h2>
      <p class="lead dialog-lead">Your adviser confirms the log. Once confirmed, it can no longer be edited.</p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>
      <input type="hidden" name="id" value="">

      <div class="field">
        <label for="meeting-date">Meeting date</label>
        <input id="meeting-date" name="meeting_date" type="date" aria-describedby="meeting_date-error" required>
        <p class="field-error" id="meeting_date-error" data-error-for="meeting_date"></p>
      </div>

      <div class="field">
        <label for="meeting-notes">What did you discuss?</label>
        <textarea id="meeting-notes" name="notes" rows="5" maxlength="2000" aria-describedby="notes-count notes-error" required></textarea>
        <p class="hint counter" id="notes-count"><span data-count-for="notes">0</span> / 2000</p>
        <p class="field-error" id="notes-error" data-error-for="notes"></p>
      </div>

      <div class="field">
        <label for="meeting-actions">Action items <span class="meta-inline">(optional, one per line)</span></label>
        <textarea id="meeting-actions" name="action_items" rows="3" maxlength="1000" aria-describedby="actions-count actions-error"></textarea>
        <p class="hint counter" id="actions-count"><span data-count-for="action_items">0</span> / 1000</p>
        <p class="field-error" id="actions-error" data-error-for="action_items"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Save meeting</button>
      </div>
    </form>
  </dialog>

  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Delete this meeting log?</h2>
      <p class="lead dialog-lead">This cannot be undone.</p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Keep it</button>
        <button type="button" class="btn btn-danger" data-confirm>Delete meeting</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/student-meetings.js')) ?>"></script>
</body>
</html>
