<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

// A signed-in visitor sees a dashboard button instead of sign-in links
$user = current_user();
$loginUrl     = app_url('pages/login.php');
$registerUrl  = app_url('pages/register.php');
$dashboardUrl = $user ? app_url(dashboard_path($user['role'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php layout_head('Capstone tracking for students and advisers'); ?>
  <meta name="description" content="Track-a-Demic helps advisers and student groups manage capstone topics, milestones, meetings, and drafts in one place.">
</head>
<body <?php layout_body_data(); ?>>

<header class="site-header">
  <div class="container nav-bar">
    <?php layout_brand(app_url('')); ?>

    <nav id="site-menu" class="site-nav" aria-label="Main" data-open="false">
      <ul class="nav-links">
        <li><a href="#how">How it works</a></li>
        <li><a href="#features">Features</a></li>
      </ul>
      <div class="nav-actions">
        <?php if ($user): ?>
          <a class="btn btn-primary btn-sm" href="<?= e($dashboardUrl) ?>">Go to dashboard</a>
        <?php else: ?>
          <a class="btn btn-quiet btn-sm" href="<?= e($loginUrl) ?>">Sign in</a>
          <a class="btn btn-primary btn-sm" href="<?= e($registerUrl) ?>">Create account</a>
        <?php endif; ?>
      </div>
    </nav>

    <div class="nav-tools">
      <?php layout_theme_toggle(); ?>
      <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-menu" aria-label="Menu">
        <svg class="icon-open" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <svg class="icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
  </div>
</header>

<main>
  <section class="hero">
    <div class="container hero-grid">
      <div>
        <h1>Capstone work, on one clear track.</h1>
        <p class="hero-lead">Advisers set the milestones and deadlines. Student groups propose topics, log their progress, and always see what comes next.</p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-primary" href="<?= e($dashboardUrl) ?>">Go to dashboard</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e($registerUrl) ?>">Create an account</a>
            <a class="btn btn-quiet" href="<?= e($loginUrl) ?>">Sign in</a>
          <?php endif; ?>
        </div>
      </div>

      <figure class="track-card">
        <figcaption class="track-head">
          <strong>Group Alpha</strong>
          <span>Adviser: Dr. Maria Santos</span>
        </figcaption>
        <ol class="track">
          <li class="is-done"><span class="track-name">Topic proposal</span><span class="track-meta">Approved</span></li>
          <li class="is-current"><span class="track-name">Chapters 1 to 3</span><span class="track-meta">In progress, due Dec 4</span></li>
          <li><span class="track-name">System prototype</span><span class="track-meta">Due Feb 12</span></li>
          <li><span class="track-name">Final manuscript</span><span class="track-meta">Due Apr 16</span></li>
          <li><span class="track-name">Final defense</span><span class="track-meta">Due May 14</span></li>
        </ol>
        <p class="track-note">Sample view of a group's milestone track</p>
      </figure>
    </div>
  </section>

  <section class="section section-alt" id="how">
    <div class="container">
      <div class="section-head">
        <h2>How it works</h2>
        <p>One shared track replaces scattered chats, spreadsheets, and paper forms.</p>
      </div>
      <ol class="steps">
        <li>
          <h3>Advisers set up the track</h3>
          <p>Create a group, add its students, and set a deadline for every milestone.</p>
        </li>
        <li>
          <h3>Groups work through it</h3>
          <p>Students propose a topic, log meetings, save references, and upload drafts.</p>
        </li>
        <li>
          <h3>Advisers review and respond</h3>
          <p>Approve topics, comment on drafts, and mark each milestone complete.</p>
        </li>
      </ol>
    </div>
  </section>

  <section class="section" id="features">
    <div class="container">
      <div class="section-head">
        <h2>What each role gets</h2>
        <p>Students see their own group. Advisers see only the groups they handle.</p>
      </div>
      <div class="roles">
        <div class="role">
          <h3>For students</h3>
          <ul>
            <li>Propose a topic and follow its status from proposal to defense.</li>
            <li>See every milestone and deadline in one place.</li>
            <li>Log adviser meetings and the action items that came out of them.</li>
            <li>Keep references and draft files together with the group.</li>
          </ul>
        </div>
        <div class="role">
          <h3>For advisers</h3>
          <ul>
            <li>Create groups and set the milestones and deadlines for each.</li>
            <li>Review topic proposals and leave comments.</li>
            <li>Spot the groups that are falling behind.</li>
            <li>Get notified when a group submits something new.</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="cta">
    <div class="container cta-inner">
      <h2>Start tracking your capstone.</h2>
      <p>Create an account as a student or an adviser and get your group on the track.</p>
      <div class="hero-actions">
        <?php if ($user): ?>
          <a class="btn btn-light" href="<?= e($dashboardUrl) ?>">Go to dashboard</a>
        <?php else: ?>
          <a class="btn btn-light" href="<?= e($registerUrl) ?>">Create an account</a>
          <a class="btn btn-outline-light" href="<?= e($loginUrl) ?>">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <?php layout_brand(app_url('')); ?>
      <p class="footer-note">A final project for Web Systems Technology. &copy; <?= date('Y') ?> Ken Justin T. Abellaneda.</p>
    </div>
    <ul class="footer-links">
      <li><a href="#how">How it works</a></li>
      <li><a href="#features">Features</a></li>
      <?php if ($user): ?>
        <li><a href="<?= e($dashboardUrl) ?>">Dashboard</a></li>
      <?php else: ?>
        <li><a href="<?= e($loginUrl) ?>">Sign in</a></li>
        <li><a href="<?= e($registerUrl) ?>">Create account</a></li>
      <?php endif; ?>
    </ul>
  </div>
</footer>

<script type="module" src="<?= e(app_url('assets/js/theme.js')) ?>"></script>
<script type="module" src="<?= e(app_url('assets/js/site.js')) ?>"></script>
</body>
</html>
