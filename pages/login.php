<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

// Already signed in: go straight to the dashboard
$user = current_user();
if ($user !== null) {
    header('Location: ' . app_url(dashboard_path($user['role'])));
    exit;
}

$pageTitle  = 'Sign in';
$csrf       = csrf_token();
$registered = isset($_GET['registered']);

require __DIR__ . '/../includes/auth_header.php';
?>
    <section class="auth-card">
      <h1>Sign in</h1>
      <p class="lead">Pick up where your group left off.</p>

      <?php if ($registered): ?>
        <div class="alert alert-success" role="status">Account created. Sign in to continue.</div>
      <?php endif; ?>
      <div class="alert alert-error" data-alert role="alert" hidden></div>

      <form data-form="login" novalidate>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" aria-describedby="email-error" required>
          <p class="field-error" id="email-error" data-error-for="email"></p>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" aria-describedby="password-error" required>
            <button type="button" class="toggle" data-toggle-password aria-controls="password" aria-pressed="false">Show</button>
          </div>
          <p class="field-error" id="password-error" data-error-for="password"></p>
        </div>

        <button class="btn btn-primary" type="submit">Sign in</button>
      </form>

      <p class="switch">New to Track-a-Demic? <a href="register.php">Create an account</a></p>
    </section>
<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
