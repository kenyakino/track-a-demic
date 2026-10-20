<?php
/**
 * Opens the split layout used by the login and register pages.
 * Expects: $pageTitle (string) and $csrf (string).
 */
require_once __DIR__ . '/layout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head($pageTitle, $csrf); ?>
</head>
<body class="auth-page" <?php layout_body_data(); ?>>
<div class="auth">
  <aside class="auth-aside">
    <div class="aside-top">
      <?php layout_brand(app_url('')); ?>
      <?php layout_theme_toggle(); ?>
    </div>
    <div class="auth-pitch">
      <p class="pitch">From proposal to defense, on one track.</p>
      <ol class="route">
        <li><strong>Topic proposal</strong><span>Pitch your topic and get your adviser's approval.</span></li>
        <li><strong>Chapters 1 to 3</strong><span>Draft the introduction, literature review, and methods.</span></li>
        <li><strong>System prototype</strong><span>Build and demonstrate a working version.</span></li>
        <li><strong>Final manuscript</strong><span>Complete the paper with your adviser's feedback.</span></li>
        <li><strong>Final defense</strong><span>Present your work to the panel.</span></li>
      </ol>
    </div>
  </aside>
  <main class="auth-main">
