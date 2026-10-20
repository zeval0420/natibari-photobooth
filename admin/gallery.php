<?php
/**
 * admin/gallery.php
 * ------------------------------------------------------------------
 * The administrator gallery required by the brief: lists previous
 * sessions, previews their generated strips, and allows reprinting.
 * Shares the same passcode gate as admin/settings.php (one admin
 * session covers both pages).
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$authenticated = admin_is_authenticated();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gallery — Photobooth Admin</title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/animations.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body style="overflow:auto;">

<?php if (!$authenticated): ?>
  <div class="admin-gate">
    <div class="card admin-gate__card">
      <h2>Admin Access</h2>
      <p class="muted" style="margin-top:8px">Enter the settings passcode to view the gallery.</p>
      <input type="password" inputmode="numeric" maxlength="12" class="passcode-input" id="passcode-input" placeholder="••••">
      <button class="btn btn--primary btn--block" id="btn-unlock">Unlock</button>
      <p><a href="../index.php" class="faint" style="font-size:13px">← Back to kiosk</a></p>
    </div>
  </div>
  <script>
    document.getElementById('btn-unlock').addEventListener('click', unlock);
    document.getElementById('passcode-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') unlock(); });
    async function unlock() {
      const passcode = document.getElementById('passcode-input').value;
      const res = await fetch('../api/admin_auth.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', passcode }),
      });
      const data = await res.json();
      if (data.success) window.location.reload();
      else {
        const input = document.getElementById('passcode-input');
        input.style.borderColor = 'var(--color-danger)';
        input.value = '';
      }
    }
  </script>

<?php else: ?>
  <div class="admin-shell">
    <header class="admin-header">
      <div class="admin-header__title">🖼️ Session Gallery</div>
      <nav class="admin-header__nav">
        <a class="btn btn--ghost" href="settings.php">Settings</a>
        <a class="btn btn--ghost" href="../creator/gallery.php">Template Creator</a>
        <a class="btn btn--ghost" href="../index.php">Kiosk View</a>
        <button class="btn btn--secondary" id="btn-logout">Log out</button>
      </nav>
    </header>
    <main class="admin-main">
      <div id="gallery-content">
        <div class="center-col" style="padding:64px"><div class="spinner"></div></div>
      </div>
    </main>
  </div>
  <div id="toast-stack"></div>
  <script type="module" src="../assets/js/admin/galleryPage.js"></script>
<?php endif; ?>

</body>
</html>
