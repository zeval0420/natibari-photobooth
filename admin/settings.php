<?php
/**
 * admin/settings.php
 * ------------------------------------------------------------------
 * The "hidden settings page" required by the brief. Reached by tapping
 * the welcome screen logo 5 times, or by navigating here directly.
 * Gated by a passcode (sha256 hash stored in settings -> admin section,
 * default passcode is 1234 — change it immediately after first login).
 * All actual reading/writing of settings happens through
 * api/settings_get.php / api/settings_save.php; this file just renders
 * the form shell, identical in spirit to index.php being a thin shell
 * around the kiosk workflow.
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
<title>Settings — Photobooth Admin</title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/animations.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body style="overflow:auto;">

<?php if (!$authenticated): ?>
  <!-- ============================================================ -->
  <!-- Passcode gate                                                  -->
  <!-- ============================================================ -->
  <div class="admin-gate">
    <div class="card admin-gate__card">
      <h2>Admin Access</h2>
      <p class="muted" style="margin-top:8px">Enter the settings passcode.</p>
      <input type="password" inputmode="numeric" maxlength="12" class="passcode-input" id="passcode-input" placeholder="••••">
      <button class="btn btn--primary btn--block" id="btn-unlock">Unlock</button>
      <p class="faint" style="margin-top:16px;font-size:13px">Default passcode is 1234 unless it has been changed.</p>
      <p><a href="../index.php" class="faint" style="font-size:13px">← Back to kiosk</a></p>
    </div>
  </div>
  <script>
    document.getElementById('btn-unlock').addEventListener('click', unlock);
    document.getElementById('passcode-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') unlock(); });
    async function unlock() {
      const passcode = document.getElementById('passcode-input').value;
      const res = await fetch('../api/admin_auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', passcode }),
      });
      const data = await res.json();
      if (data.success) {
        window.location.reload();
      } else {
        const input = document.getElementById('passcode-input');
        input.style.borderColor = 'var(--color-danger)';
        input.value = '';
        input.placeholder = 'Incorrect passcode';
      }
    }
  </script>

<?php else: ?>
  <!-- ============================================================ -->
  <!-- Settings form                                                  -->
  <!-- ============================================================ -->
  <div class="admin-shell">
    <header class="admin-header">
      <div class="admin-header__title">⚙️ Photobooth Settings</div>
      <nav class="admin-header__nav">
        <a class="btn btn--ghost" href="gallery.php">Gallery</a>
        <a class="btn btn--ghost" href="../creator/gallery.php">Template Creator</a>
        <a class="btn btn--ghost" href="../index.php">Kiosk View</a>
        <button class="btn btn--secondary" id="btn-logout">Log out</button>
      </nav>
    </header>

    <main class="admin-main" id="settings-form">
      <div class="center-col" style="padding:64px"><div class="spinner"></div></div>
    </main>
  </div>

  <div id="toast-stack"></div>
  <script type="module" src="../assets/js/admin/settingsPage.js"></script>
<?php endif; ?>

</body>
</html>
