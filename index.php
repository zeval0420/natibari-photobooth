<?php
/**
 * index.php
 * ------------------------------------------------------------------
 * The kiosk entry point. This file only renders the page shell — every
 * screen in the workflow (template gallery -> camera ->
 * review -> final preview -> done) lives as a <section class="screen">
 * below, and assets/js/app.js shows/hides them as the user progresses.
 * All real logic (camera access, compositing, saving, printing) is
 * handled by the JS modules + the PHP api/*.php endpoints; this file
 * stays a thin, readable shell on purpose.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Opening of Classes Photobooth</title>

<link rel="stylesheet" href="assets/css/variables.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/animations.css">
</head>
<body>

<div id="app">

  <!-- ============================================================ -->
  <!-- SCREEN 1 — Template gallery                                   -->
  <!-- ============================================================ -->
  <section id="screen-gallery" class="screen screen--active">
    <div class="topbar">
      <div class="brand" id="brand-logo"><span class="brand__dot"></span></div>
      <h2>Choose a Frame</h2>
      <div style="display: flex; align-items: center; gap: 8px;">
        <button class="btn btn--ghost btn-camera-picker__btn" id="btn-print-picker" title="Printer &amp; paper size">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </button>
        <button class="btn btn--ghost btn-camera-picker__btn" id="btn-camera-picker" title="Preview camera">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </button>
      </div>
    </div>
    <div class="screen__inner">
      <div class="scroll-region">
        <div class="template-grid" id="template-grid"></div>
      </div>
      <button class="btn btn--primary btn--xl btn--block" id="btn-gallery-continue">Continue</button>
    </div>
  </section>

  <!-- ============================================================ -->
  <!-- SCREEN 3 — Camera / capture                                   -->
  <!-- ============================================================ -->
  <section id="screen-camera" class="screen">
    <div class="topbar">
      <button class="btn btn--ghost" id="btn-camera-cancel">Cancel</button>
      <h2 id="camera-title">Get Ready — Photo 1 of 4</h2>
      <span style="width:90px"></span>
    </div>
    <div class="screen__inner">
      <div class="camera-layout">
        <div class="camera-stage" id="camera-stage">
          <video id="camera-video" autoplay playsinline muted></video>
          <div class="camera-stage__flash" id="camera-flash"></div>
          <div class="camera-stage__countdown">
            <div class="camera-stage__countdown-number" id="countdown-number"></div>
          </div>
          <div class="camera-stage__progress" id="camera-progress"></div>
        </div>
        <div class="camera-template-preview" id="camera-template-preview">
          <div class="camera-template-preview__captures" id="camera-slot-captures"></div>
          <div class="camera-template-preview__slot-highlight" id="camera-slot-highlight" style="opacity:0"></div>
          <img id="camera-frame-overlay" class="camera-template-preview__frame" alt="">
        </div>
      </div>
      <div class="camera-controls">
        <button class="btn btn--primary btn--xl" id="btn-camera-start">Start Capturing</button>
      </div>
    </div>
  </section>

  <!-- ============================================================ -->
  <!-- SCREEN 4 — Review + retake                                    -->
  <!-- ============================================================ -->
  <section id="screen-review" class="screen">
    <div class="topbar">
      <button class="btn btn--ghost" id="btn-review-restart">Start Over</button>
      <h2>Review Your Photos</h2>
      <span style="width:120px"></span>
    </div>
    <div class="screen__inner">
      <p class="muted center-col" style="margin-bottom:16px">Not happy with one? Tap ↻ on that photo to retake just that shot.</p>
      <div class="review-stage" id="review-grid"></div>
      <button class="btn btn--primary btn--xl btn--block" id="btn-review-continue">Looks Great — Continue</button>
    </div>
  </section>

  <!-- ============================================================ -->
  <!-- SCREEN 5 — Final strip preview                                -->
  <!-- ============================================================ -->
  <section id="screen-final" class="screen">
    <div class="topbar">
      <button class="btn btn--ghost" id="btn-final-back">← Back</button>
      <h2>Your Strip is Ready!</h2>
      <span style="width:90px"></span>
    </div>
    <div class="screen__inner">
      <div class="final-preview">
        <img class="final-preview__strip" id="final-strip-img" alt="Your final photo strip">
        <div class="final-preview__qr is-hidden" id="final-qr">
          <div class="final-preview__qr-item">
            <div class="final-preview__qr-code" id="qr-strip"></div>
            <div class="final-preview__qr-label">Photo strip</div>
          </div>
          <div class="final-preview__qr-item">
            <div class="final-preview__qr-code" id="qr-raw"></div>
            <div class="final-preview__qr-label">Raw photos (ZIP)</div>
          </div>
          <p class="faint final-preview__qr-note" id="qr-note">Scan with your phone camera to download. Anyone with the link can view the photos.</p>
        </div>
        <div class="final-preview__actions">
          <button class="btn btn--primary btn--xl" id="btn-final-accept">Accept &amp; Finish</button>
          <button class="btn btn--secondary btn--xl" id="btn-final-redo">Retake Everything</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================ -->
  <!-- SCREEN 6 — Done                                                -->
  <!-- ============================================================ -->
  <section id="screen-done" class="screen">
    <div class="welcome">
      <div class="done-check">✓</div>
      <h1 class="welcome__title" style="margin-top:24px">All set!</h1>
      <p class="welcome__subtitle" id="done-message">Your strip has been saved.</p>
      <p class="faint" id="done-countdown" style="margin-top:24px">Returning to start in 5s…</p>
    </div>
  </section>

</div>

<div id="toast-stack"></div>

<!-- ============================================================ -->
<!-- Camera preview modal                                          -->
<!-- ============================================================ -->
<div class="camera-modal is-hidden" id="camera-modal">
  <div class="camera-modal__backdrop" id="camera-modal-backdrop"></div>
  <div class="camera-modal__dialog">
    <button class="camera-modal__close" id="btn-camera-modal-close" aria-label="Close">&times;</button>
    <div class="camera-modal__video-wrap">
      <video id="camera-modal-video" autoplay playsinline muted></video>
    </div>
    <div class="camera-modal__header">Switch Camera</div>
    <div class="camera-modal__list" id="camera-modal-list"></div>
  </div>
</div>

<!-- ============================================================ -->
<!-- Printer & paper modal                                         -->
<!-- ============================================================ -->
<div class="camera-modal is-hidden" id="print-modal">
  <div class="camera-modal__backdrop" id="print-modal-backdrop"></div>
  <div class="camera-modal__dialog">
    <button class="camera-modal__close" id="btn-print-modal-close" aria-label="Close">&times;</button>
    <div class="camera-modal__header">Printer</div>
    <div class="camera-modal__list" id="print-modal-printers"></div>
    <div class="camera-modal__header print-modal__gap">Paper size</div>
    <div class="print-modal__papers" id="print-modal-papers"></div>
    <p class="faint print-modal__note">The strip is scaled to fit the selected paper. The default is 5R (5 &times; 7 in).</p>
  </div>
</div>

<!-- Hidden audio elements; sources are set dynamically from Settings -->
<audio id="audio-countdown" preload="auto"></audio>
<audio id="audio-shutter" preload="auto"></audio>

<script src="assets/js/vendor/qrcode.js"></script>
<script type="module" src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
