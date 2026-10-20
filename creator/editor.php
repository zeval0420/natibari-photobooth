<?php
/**
 * creator/editor.php
 * ------------------------------------------------------------------
 * The WYSIWYG Template Editor. All visual interaction happens in the
 * browser (creator/js/editor.js + SlotManager + CanvasRenderer +
 * PropsPanel); this file only renders the static HTML shell.
 *
 * URL params:
 *   ?edit=<template_id>  — loads an existing template for editing
 *   (no param)           — starts with the frame upload screen
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$authenticated = admin_is_authenticated();
if (!$authenticated) {
    header('Location: gallery.php');
    exit;
}
$editId = sanitize_id($_GET['edit'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Template Editor — Photobooth Creator</title>
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/animations.css">
<link rel="stylesheet" href="creator.css">
</head>
<body>

<div class="creator-app">

  <!-- ============================================================ -->
  <!-- Header bar                                                     -->
  <!-- ============================================================ -->
  <header class="creator-header">
    <a class="header-btn header-btn--ghost" id="btn-back-gallery" href="gallery.php">← Gallery</a>
    <div class="creator-header__sep"></div>
    <div class="creator-header__brand">
      <span class="creator-header__brand-dot"></span>
      Template Editor
    </div>
    <div class="creator-header__sep"></div>
    <span class="creator-header__title" id="header-title">
      <?= $editId ? htmlspecialchars($editId) : 'New Template' ?>
    </span>

    <div style="flex:1"></div>

    <span id="slot-count-badge" class="faint" style="font-size:12px;margin-right:8px;">0 slots</span>
    <button class="header-btn header-btn--outline" id="btn-preview">🖼 Preview</button>
    <button class="header-btn header-btn--primary" id="btn-save">💾 Save Template</button>
  </header>

  <!-- Validation warning banner (shown when slots overlap or overflow) -->
  <div class="validation-banner" id="validation-banner"></div>

  <!-- ============================================================ -->
  <!-- Main body: sidebar + canvas + props                           -->
  <!-- ============================================================ -->
  <div class="creator-body">

    <!-- ---- Left Sidebar: template info + quick actions ---------- -->
    <aside class="creator-sidebar">

      <!-- Template Information -->
      <div class="sidebar-section">
        <h3>Template Info</h3>
        <div class="prop-field">
          <label>Name *</label>
          <input type="text" id="f-tpl-name" placeholder="e.g. Back 2 Skul" value="">
        </div>
        <div class="prop-field">
          <label>Author (optional)</label>
          <input type="text" id="f-tpl-author" placeholder="Your name">
        </div>
        <div class="prop-field">
          <label>Description (optional)</label>
          <textarea id="f-tpl-desc" rows="2" placeholder="A short description…"></textarea>
        </div>
      </div>

      <!-- Output dimensions -->
      <div class="sidebar-section">
        <h3>Output Size</h3>
        <div class="prop-row">
          <div class="prop-field">
            <label>Width (px)</label>
            <input type="number" id="f-out-w" min="100" max="9999" placeholder="600">
          </div>
          <div class="prop-field">
            <label>Height (px)</label>
            <input type="number" id="f-out-h" min="100" max="9999" placeholder="1800">
          </div>
        </div>
        <p class="faint" style="font-size:11px;margin-top:4px;">
          Set automatically when you upload a frame image. Change only if you want to override.
        </p>
      </div>

      <!-- Background -->
      <div class="sidebar-section">
        <h3>Background</h3>
        <div class="prop-field">
          <label>Background colour</label>
          <input type="color" id="f-bg-color" value="#ffffff">
        </div>
      </div>

      <!-- Frame management -->
      <div class="sidebar-section">
        <h3>Frame Image</h3>
        <button class="header-btn header-btn--outline" id="btn-change-frame"
                style="width:100%;justify-content:center">⬆ Change Frame Image</button>
        <input type="file" id="frame-file-input" accept="image/png,image/jpeg,image/webp" style="display:none">
        <p class="faint" style="font-size:11px;margin-top:8px;">PNG, JPG or WEBP accepted. The image becomes the template overlay.</p>
      </div>

    </aside>

    <!-- ---- Canvas area ---------------------------------------------- -->
    <div class="creator-canvas-area">

      <!-- Toolbar -->
      <div class="creator-toolbar" id="editor-toolbar">

        <!-- Add / Remove slot -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-add-slot" title="Add photo slot (A)">
            <span>＋</span>
          </button>
          <button class="tool-btn" id="btn-remove-slot" title="Remove selected slot (Del)">
            <span>🗑</span>
          </button>
        </div>

        <!-- Alignment -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-align-left"   title="Align Left"><span>⬤⬜⬜</span></button>
          <button class="tool-btn" id="btn-align-ch"     title="Align Center H"><span>⬜⬤⬜</span></button>
          <button class="tool-btn" id="btn-align-right"  title="Align Right"><span>⬜⬜⬤</span></button>
          <button class="tool-btn" id="btn-align-top"    title="Align Top"><span>↟</span></button>
          <button class="tool-btn" id="btn-align-cv"     title="Align Center V"><span>↕</span></button>
          <button class="tool-btn" id="btn-align-bottom" title="Align Bottom"><span>↡</span></button>
        </div>

        <!-- Distribute -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-dist-v" title="Distribute vertically"><span>⇕</span></button>
          <button class="tool-btn" id="btn-dist-h" title="Distribute horizontally"><span>⇔</span></button>
        </div>

        <!-- Match size -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-match-w" title="Match width to selection"><span>↔W</span></button>
          <button class="tool-btn" id="btn-match-h" title="Match height to selection"><span>↕H</span></button>
        </div>

        <!-- Z-order + reset rotation -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-forward"   title="Bring forward"><span>⬆</span></button>
          <button class="tool-btn" id="btn-backward"  title="Send backward"><span>⬇</span></button>
          <button class="tool-btn" id="btn-reset-rot" title="Reset rotation on all slots"><span>⟳0°</span></button>
        </div>

        <!-- Zoom -->
        <div class="tool-group">
          <button class="tool-btn" id="btn-zoom-out" title="Zoom out">−</button>
          <span class="zoom-display" id="zoom-level">100%</span>
          <button class="tool-btn" id="btn-zoom-in"  title="Zoom in">+</button>
          <button class="tool-btn" id="btn-zoom-fit" title="Fit to window">⊡</button>
        </div>

        <!-- Snap -->
        <div class="tool-group">
          <button class="tool-btn is-active" id="btn-snap-toggle" title="Toggle snap to guides">
            <span>⊞</span>
          </button>
          <span class="tool-label">Snap</span>
        </div>

      </div><!-- /creator-toolbar -->

      <!-- ---- Upload screen (shown until a frame is chosen) ---------- -->
      <div id="upload-section" class="canvas-scroll-viewport"
           style="flex-direction:column;gap:24px;<?= $editId ? 'display:none;' : '' ?>">
        <div style="max-width:480px;width:100%;text-align:center;">
          <h2 style="font-size:24px;margin-bottom:12px;">Start with a Frame Image</h2>
          <p class="muted" style="margin-bottom:28px;">Upload a PNG, JPG or WEBP — it becomes the decorative overlay that sits on top of the 4 captured photos.</p>
          <div class="upload-zone" id="upload-drop-zone">
            <div class="upload-zone__icon">🖼</div>
            <div class="upload-zone__label">Drop your frame image here</div>
            <div class="upload-zone__hint">or click to browse&nbsp;·&nbsp;PNG preferred&nbsp;·&nbsp;RGBA transparent windows = photo slots</div>
            <p id="upload-progress" style="margin-top:16px;color:var(--color-accent);font-weight:700;"></p>
          </div>
        </div>
      </div>

      <!-- ---- Canvas scroll viewport (shown once frame is loaded) ---- -->
      <div class="canvas-scroll-viewport" id="canvas-viewport"
           style="<?= $editId ? '' : 'display:none;' ?>">
        <div id="canvas-stage">
          <!-- Frame overlay sits above all slots (z-index:10 in CSS) -->
          <img class="stage-frame" src="" alt="" draggable="false">
        </div>
      </div>

    </div><!-- /creator-canvas-area -->

    <!-- ---- Right Properties Panel ----------------------------------- -->
    <aside class="creator-props" id="props-panel">
      <!-- Populated by PropsPanel.js -->
    </aside>

  </div><!-- /creator-body -->

</div><!-- /creator-app -->

<div id="creator-toasts"></div>

<script>
  window.__EDIT_ID__ = <?= json_encode($editId ?: null) ?>;
</script>
<script type="module" src="js/editor.js"></script>

</body>
</html>
