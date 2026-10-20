<?php
/**
 * creator/gallery.php
 * ------------------------------------------------------------------
 * The Template Creator's home screen: an administrator gallery that
 * lists every installed template, with Edit / Preview / Duplicate /
 * Rename / Export / Delete actions on each card.  Gated by the same
 * admin passcode used by admin/settings.php — one auth covers both.
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
<title>Template Creator — Gallery</title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/animations.css">
<link rel="stylesheet" href="creator.css">
</head>
<body style="overflow:auto;">

<?php if (!$authenticated): ?>
<!-- ============================================================ -->
<!-- Passcode gate                                                  -->
<!-- ============================================================ -->
<div class="admin-gate">
  <div class="card admin-gate__card">
    <h2>Template Creator</h2>
    <p class="muted" style="margin-top:8px">Enter the admin passcode to continue.</p>
    <input type="password" inputmode="numeric" maxlength="12"
           class="passcode-input" id="passcode-input" placeholder="••••">
    <button class="btn btn--primary btn--block" id="btn-unlock">Unlock</button>
    <p><a href="../index.php" class="faint" style="font-size:13px">← Back to kiosk</a></p>
  </div>
</div>
<script>
  document.getElementById('btn-unlock').addEventListener('click', unlock);
  document.getElementById('passcode-input').addEventListener('keydown', e => { if (e.key==='Enter') unlock(); });
  async function unlock() {
    const passcode = document.getElementById('passcode-input').value;
    const res  = await fetch('../api/admin_auth.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'login', passcode}),
    });
    const data = await res.json();
    if (data.success) { window.location.reload(); }
    else {
      const inp = document.getElementById('passcode-input');
      inp.style.borderColor = 'var(--color-danger)';
      inp.value = ''; inp.placeholder = 'Incorrect passcode';
    }
  }
</script>

<?php else: ?>
<!-- ============================================================ -->
<!-- Gallery shell                                                  -->
<!-- ============================================================ -->
<div class="gallery-page">

  <header class="gallery-header">
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="creator-header__brand">
        <span class="creator-header__brand-dot"></span>
        Template Creator
      </div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <button class="header-btn header-btn--outline" id="btn-import">⬆ Import ZIP</button>
      <input type="file" id="import-zip-input" accept=".zip" style="display:none">
      <a class="header-btn header-btn--primary" id="btn-new-template" href="editor.php">+ New Template</a>
      <a class="header-btn header-btn--ghost" href="../admin/settings.php">⚙ Settings</a>
      <a class="header-btn header-btn--ghost" href="../index.php">Kiosk View</a>
      <button class="header-btn header-btn--ghost" id="btn-logout">Log out</button>
    </div>
  </header>

  <main class="gallery-main">
    <div class="gallery-controls">
      <h2 style="flex:1;font-size:22px;">Installed Templates</h2>
    </div>
    <div class="gallery-grid" id="gallery-grid">
      <div class="center-col" style="grid-column:1/-1;padding:64px">
        <div class="spinner"></div>
      </div>
    </div>
  </main>

</div><!-- /gallery-page -->

<div id="creator-toasts"></div>

<script>
  document.getElementById('btn-logout').addEventListener('click', async () => {
    await fetch('../api/admin_auth.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'logout'}),
    });
    window.location.href = '../index.php';
  });
</script>
<script type="module" src="js/gallery.js"></script>

<?php endif; ?>
</body>
</html>
