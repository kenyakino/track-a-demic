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
<?php layout_head('Milestones', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_open($user, 'milestones'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Milestones</h1>
        <p class="lead">Set deadlines for your groups and review what they submit.</p>
      </div>
      <button type="button" class="btn btn-primary" data-action="add" hidden>Add milestone</button>
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

  <dialog id="milestone-dialog" class="dialog" aria-labelledby="milestone-dialog-title">
    <form id="milestone-form" class="dialog-body" novalidate>
      <h2 id="milestone-dialog-title">Add a milestone</h2>
      <p class="lead dialog-lead">Pick the groups that get this milestone. New groups you create later get your upcoming milestones automatically.</p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>
      <input type="hidden" name="id" value="">

      <div class="field">
        <label for="milestone-name">Name</label>
        <input id="milestone-name" name="name" type="text" maxlength="150" autocomplete="off" aria-describedby="name-error" required>
        <p class="field-error" id="name-error" data-error-for="name"></p>
      </div>

      <div class="field">
        <label for="milestone-due">Due date</label>
        <input id="milestone-due" name="due_date" type="date" aria-describedby="due_date-error" required>
        <p class="field-error" id="due_date-error" data-error-for="due_date"></p>
      </div>

      <div class="field">
        <label for="milestone-term">Term <span class="meta-inline">(optional, for example AY 2026-2027)</span></label>
        <input id="milestone-term" name="term" type="text" maxlength="50" autocomplete="off" aria-describedby="term-error">
        <p class="field-error" id="term-error" data-error-for="term"></p>
      </div>

      <div class="field">
        <span class="label-text" id="groups-label">Groups</span>
        <div class="checklist" data-group-list aria-labelledby="groups-label"></div>
        <p class="field-error" id="group_ids-error" data-error-for="group_ids"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Save milestone</button>
      </div>
    </form>
  </dialog>

  <dialog id="review-dialog" class="dialog dialog-narrow" aria-labelledby="review-dialog-title">
    <form id="review-form" class="dialog-body" novalidate>
      <h2 id="review-dialog-title">Review milestone</h2>
      <p class="lead dialog-lead" id="review-dialog-subtitle"></p>
      <input type="hidden" name="id" value="">
      <input type="hidden" name="action" value="">
      <div class="field">
        <label for="review-note" id="review-note-label">Note for the group</label>
        <textarea id="review-note" name="note" rows="4" maxlength="1000" aria-describedby="note-error"></textarea>
        <p class="field-error" id="note-error" data-error-for="note"></p>
      </div>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Save</button>
      </div>
    </form>
  </dialog>

  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Delete this milestone?</h2>
      <p class="lead dialog-lead" id="confirm-message"></p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="button" class="btn btn-danger" data-confirm>Delete milestone</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/adviser-milestones.js')) ?>"></script>
</body>
</html>
