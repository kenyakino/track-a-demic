<?php
/**
 * Opens the split layout used by the login and register pages.
 * Expects: $pageTitle (string) and $csrf (string).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <meta name="color-scheme" content="light dark">
  <title><?= e($pageTitle) ?> | Track-a-Demic</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="auth-page" data-base-url="<?= e(rtrim((string) (app_config()['app']['base_url'] ?? ''), '/')) ?>">
<div class="auth">
  <aside class="auth-aside">
    <a class="brand" href="<?= e(app_url('')) ?>">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <path d="M5 22h8a4 4 0 0 0 4-4V10a4 4 0 0 1 4-4h2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="5" cy="22" r="3" fill="#F2B705"/>
        <circle cx="23" cy="6" r="3" fill="currentColor"/>
      </svg>
      Track-a-Demic
    </a>
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
