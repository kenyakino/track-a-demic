<?php
declare(strict_types=1);

/**
 * Shared page pieces: <head> contents, brand link, theme toggle.
 * Every page uses these so the look and behavior stay consistent.
 */

require_once __DIR__ . '/auth.php';

/**
 * Prints the contents of <head>.
 * The small inline script sets the saved (or system) theme before the page
 * paints, so there is no flash of the wrong colors.
 */
function layout_head(string $title, ?string $csrf = null): void
{
    ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($csrf !== null): ?>
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <?php endif; ?>
  <title><?= e($title) ?> | Track-a-Demic</title>
  <script>
    (function () {
      var theme = null;
      try { theme = localStorage.getItem('theme'); } catch (e) {}
      if (theme !== 'light' && theme !== 'dark') {
        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    <?php
}

/** Prints the attribute that tells JavaScript where the site lives. */
function layout_body_data(): void
{
    echo 'data-base-url="' . e(rtrim((string) (app_config()['app']['base_url'] ?? ''), '/')) . '"';
}

/** Prints the logo and site name as a link. */
function layout_brand(string $href): void
{
    ?>
<a class="brand" href="<?= e($href) ?>">
  <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
    <path d="M5 22h8a4 4 0 0 0 4-4V10a4 4 0 0 1 4-4h2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
    <circle class="brand-dot" cx="5" cy="22" r="3"/>
    <circle cx="23" cy="6" r="3" fill="currentColor"/>
  </svg>
  Track-a-Demic
</a>
    <?php
}

/** Prints the light/dark mode button (behavior lives in assets/js/theme.js). */
function layout_theme_toggle(): void
{
    ?>
<button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Switch to dark mode">
  <svg class="icon-moon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
  </svg>
  <svg class="icon-sun" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
</button>
    <?php
}
