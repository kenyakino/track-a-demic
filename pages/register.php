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

$pageTitle = 'Create account';
$csrf      = csrf_token();

require __DIR__ . '/../includes/auth_header.php';
?>
    <section class="auth-card">
      <h1>Create your account</h1>
      <p class="lead">Join your capstone group or start managing yours.</p>

      <div class="alert alert-error" data-alert role="alert" hidden></div>

      <form data-form="register" novalidate>
        <div class="field">
          <label for="full_name">Full name</label>
          <input id="full_name" name="full_name" type="text" autocomplete="name" aria-describedby="full_name-error" required>
          <p class="field-error" id="full_name-error" data-error-for="full_name"></p>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" aria-describedby="email-error" required>
          <p class="field-error" id="email-error" data-error-for="email"></p>
        </div>

        <fieldset class="field">
          <legend>I am a</legend>
          <div class="segmented">
            <label><input type="radio" name="role" value="student" checked><span>Student</span></label>
            <label><input type="radio" name="role" value="adviser"><span>Adviser</span></label>
          </div>
        </fieldset>

        <div class="field" data-adviser-field hidden>
          <label for="adviser_code">Adviser code</label>
          <input id="adviser_code" name="adviser_code" type="text" autocomplete="off" aria-describedby="adviser_code-error adviser_code-hint">
          <p class="hint" id="adviser_code-hint">Ask your program head for the adviser code.</p>
          <p class="field-error" id="adviser_code-error" data-error-for="adviser_code"></p>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-hint password-error" required>
            <button type="button" class="toggle" data-toggle-password aria-controls="password" aria-pressed="false">Show</button>
          </div>
          <div class="meter" data-meter data-level="0" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
          <p class="meter-label" data-meter-label aria-live="polite"></p>
          <p class="hint" id="password-hint">At least 8 characters, with a letter and a number.</p>
          <p class="field-error" id="password-error" data-error-for="password"></p>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm password</label>
          <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" aria-describedby="confirm_password-error" required>
          <p class="field-error" id="confirm_password-error" data-error-for="confirm_password"></p>
        </div>

        <button class="btn btn-primary" type="submit">Create account</button>
      </form>

      <p class="switch">Already have an account? <a href="login.php">Sign in</a></p>
    </section>
<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
