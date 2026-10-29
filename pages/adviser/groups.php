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
<?php layout_head('Groups', $csrf); ?>
</head>
<body <?php layout_body_data(); ?>>
  <?php layout_app_topbar($user, 'groups'); ?>

  <main class="shell-main">
    <div class="page-head">
      <div>
        <h1>Groups</h1>
        <p class="lead">Create groups and manage who is in them.</p>
      </div>
      <button type="button" class="btn btn-primary" data-action="create">Create group</button>
    </div>

    <div id="app" aria-live="polite" aria-busy="true">
      <div class="skeleton-stack" aria-hidden="true">
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton skeleton-short"></div></div>
        <div class="card"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div></div>
      </div>
      <noscript><p class="alert alert-error">JavaScript is required to use this page.</p></noscript>
    </div>
  </main>

  <!-- Create group -->
  <dialog id="create-dialog" class="dialog" aria-labelledby="create-title">
    <form id="create-form" class="dialog-body" novalidate>
      <h2 id="create-title">Create a group</h2>
      <p class="lead dialog-lead">Name the group and pick the students who belong to it. The first student you pick becomes the leader.</p>
      <div class="alert alert-error" data-alert role="alert" hidden></div>

      <div class="field">
        <label for="group-name">Group name</label>
        <input id="group-name" name="group_name" type="text" maxlength="100" autocomplete="off" aria-describedby="group_name-error" required>
        <p class="field-error" id="group_name-error" data-error-for="group_name"></p>
      </div>

      <div class="field">
        <label for="student-filter">Students without a group</label>
        <input id="student-filter" type="search" data-student-filter placeholder="Search by name or email" autocomplete="off">
        <div class="checklist" data-student-list></div>
        <p class="field-error" id="member_ids-error" data-error-for="member_ids"></p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Create group</button>
      </div>
    </form>
  </dialog>

  <!-- Rename group -->
  <dialog id="rename-dialog" class="dialog dialog-narrow" aria-labelledby="rename-title">
    <form id="rename-form" class="dialog-body" novalidate>
      <h2 id="rename-title">Rename group</h2>
      <input type="hidden" name="id" value="">
      <div class="field">
        <label for="rename-name">Group name</label>
        <input id="rename-name" name="group_name" type="text" maxlength="100" autocomplete="off" aria-describedby="rename-error" required>
        <p class="field-error" id="rename-error" data-error-for="group_name"></p>
      </div>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="submit" class="btn btn-primary" data-submit>Save name</button>
      </div>
    </form>
  </dialog>

  <!-- Confirm -->
  <dialog id="confirm-dialog" class="dialog dialog-narrow" aria-labelledby="confirm-title">
    <div class="dialog-body">
      <h2 id="confirm-title">Are you sure?</h2>
      <p class="lead dialog-lead" id="confirm-message"></p>
      <div class="dialog-actions">
        <button type="button" class="btn btn-quiet" data-close>Cancel</button>
        <button type="button" class="btn btn-danger" data-confirm>Confirm</button>
      </div>
    </div>
  </dialog>

  <div class="toasts" data-toasts role="status" aria-live="polite"></div>

  <script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/app.js')) ?>"></script>
  <script type="module" src="<?= e(app_url('assets/js/adviser-groups.js')) ?>"></script>
</body>
</html>
