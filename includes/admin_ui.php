<?php

function admin_page_start(string $title, string $active = '', string $backHref = '', string $backLabel = ''): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <span class="admin-brand-mark">Admin</span>
      <span class="admin-brand-sub">MCEN2003 Machine Dynamics</span>
    </div>
    <nav class="admin-nav">
      <a href="index.php" class="<?= $active === 'students' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Students
      </a>
      <a href="subjects.php" class="<?= $active === 'subjects' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        Subjects
      </a>
      <a href="formula_sheet.php" class="<?= $active === 'formula' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2h10v12H3z"/><path d="M5.2 5h5.6M5.2 8h5.6M5.2 11h3.2"/></svg>
        Formula Sheet
      </a>
      <a href="messages.php" class="<?= $active === 'messages' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10z"/><polyline points="22,7 12,13 2,7"/></svg>
        Contact Messages
      </a>
    </nav>
    <a class="admin-logout" href="logout.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Logout
    </a>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <?php if ($backHref): ?><a class="admin-back" href="<?= h($backHref) ?>">&larr; <?= h($backLabel ?: 'Back') ?></a><?php endif; ?>
        <h1><?= h($title) ?></h1>
      </div>
    </header>
    <main class="admin-content">
<?php
}

function admin_page_end(): void {
?>
    </main>
  </div>
</div>
</body>
</html>
<?php
}
