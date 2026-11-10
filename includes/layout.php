<?php
declare(strict_types=1);

/**
 * Shared page pieces: <head> contents, brand link, theme toggle.
 * Every page uses these so the look and behavior stay consistent.
 */

require_once __DIR__ . '/auth.php';

/**
 * URL of a file under the project (CSS, JavaScript). The file's last-changed
 * time is added so browsers load the new version right after you update it,
 * instead of showing a stale copy from their cache.
 */
function asset_url(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return app_url($path) . '?v=' . $version;
}

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
      try {
        if (localStorage.getItem('sidebar') === 'collapsed') document.documentElement.setAttribute('data-sidebar', 'collapsed');
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
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
  <span class="brand-name">Track-a-Demic</span>
</a>
    <?php
}

/** Prints the dark mode switch (behavior lives in assets/js/theme.js). */
function layout_theme_toggle(): void
{
    ?>
<button type="button" class="theme-switch" role="switch" aria-checked="false" aria-label="Dark mode" title="Dark mode" data-theme-toggle>
  <span class="theme-switch-track" aria-hidden="true">
    <span class="theme-switch-thumb">
      <svg class="switch-sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="switch-moon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/></svg>
    </span>
  </span>
</button>
    <?php
}

/**
 * Links shown in the side navigation of signed-in pages, by role.
 * Each entry is key => [label, path, icon]. New pages are added here.
 */
function nav_items_for(string $role): array
{
    if ($role === 'adviser') {
        return [
            'overview'   => ['Overview', 'pages/adviser/dashboard.php', 'grid'],
            'groups'     => ['Groups', 'pages/adviser/groups.php', 'users'],
            'topics'     => ['Topic reviews', 'pages/adviser/topics.php', 'file'],
            'milestones' => ['Milestones', 'pages/adviser/milestones.php', 'flag'],
            'meetings'   => ['Meetings', 'pages/adviser/meetings.php', 'calendar'],
            'files'      => ['Files and references', 'pages/adviser/files.php', 'folder'],
        ];
    }

    return [
        'dashboard'  => ['Dashboard', 'pages/student/dashboard.php', 'grid'],
        'milestones' => ['Milestones', 'pages/student/milestones.php', 'flag'],
        'meetings'   => ['Meetings', 'pages/student/meetings.php', 'calendar'],
        'references' => ['References', 'pages/student/references.php', 'book'],
        'files'      => ['Files', 'pages/student/files.php', 'folder'],
    ];
}

/** Prints a small icon for the side navigation. */
function nav_icon(string $name): void
{
    $paths = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.4c2 .7 3.5 2.5 3.5 5.6"/>',
        'file'     => '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/>',
        'flag'     => '<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'book'     => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h11"/>',
        'folder'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    ];
    echo '<svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
       . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Opens the layout of signed-in pages: the side navigation, and a top bar
 * with a menu button on small screens. $active is the key of the current page.
 * Call layout_app_close() after the page's <main> element.
 */
function layout_app_open(array $user, string $active): void
{
    ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <?php layout_brand(app_url(dashboard_path($user['role']))); ?>
      <button type="button" class="icon-btn sidebar-collapse" data-sidebar-collapse aria-expanded="true" aria-controls="sidebar" aria-label="Collapse menu" title="Collapse menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
      </button>
    </div>
    <nav class="sidebar-nav" aria-label="Main">
      <ul>
        <?php foreach (nav_items_for($user['role']) as $key => [$label, $path, $icon]): ?>
          <li>
            <a href="<?= e(app_url($path)) ?>" title="<?= e($label) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
              <?php nav_icon($icon); ?><span><?= e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="sidebar-user">
      <p class="sidebar-name"><?= e($user['display_name'] ?? $user['full_name']) ?></p>
      <p class="sidebar-role"><?= e(ucfirst($user['role'])) ?></p>
      <div class="sidebar-actions">
        <?php layout_theme_toggle(); ?>
        <button type="button" class="btn btn-quiet btn-sm sidebar-signout" data-logout aria-label="Sign out" title="Sign out">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="M16 8l4 4-4 4M20 12H9"/></svg>
          <span>Sign out</span>
        </button>
      </div>
    </div>
  </aside>
  <div class="sidebar-backdrop" data-sidebar-close hidden></div>

  <div class="app-main">
    <header class="mobile-bar">
      <button type="button" class="icon-btn" data-sidebar-toggle aria-expanded="false" aria-controls="sidebar" aria-label="Open menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
      <?php layout_brand(app_url(dashboard_path($user['role']))); ?>
      <?php layout_theme_toggle(); ?>
    </header>
    <?php
}

/** Closes the layout opened by layout_app_open(). */
function layout_app_close(): void
{
    echo "  </div>\n</div>\n";
}
