/**
 * assets/js/app.js
 * ------------------------------------------------------------------
 * The conductor. Owns the screen state machine and wires together the
 * single-responsibility modules (CameraController, TemplateGallery,
 * CaptureFlow, ReviewGrid, SessionClient) without containing much
 * logic of its own. If you're adding a new feature (GIF mode, stickers,
 * QR download…) it almost certainly belongs in its own module, with
 * just a few new lines here to slot it into the flow.
 * ------------------------------------------------------------------
 */
import { $, el, toast, wait } from './utils.js?v=1';
import { CameraController } from './camera.js?v=1';
import { TemplateGallery } from './templateGallery.js?v=1';
import { CaptureFlow } from './captureFlow.js?v=1';
import { ReviewGrid } from './review.js?v=1';
import { SessionClient } from './sessionClient.js?v=1';

class PhotoboothApp {
  constructor() {
    this.screens = {
      gallery: $('#screen-gallery'),
      camera: $('#screen-camera'),
      review: $('#screen-review'),
      final: $('#screen-final'),
      done: $('#screen-done'),
    };

    this.config = null;       // from api/public_config.php
    this.sessionId = null;
    this.template = null;
    this.photoDataUrls = [];
    this.idleTimer = null;
    this.logoTapCount = 0;
    this.logoTapTimer = null;

    this.camera = new CameraController($('#camera-video'));
    this.gallery = new TemplateGallery($('#template-grid'));
    this.reviewGrid = new ReviewGrid($('#review-grid'));
  }

  async init() {
    try {
      const { config } = await SessionClient.getPublicConfig();
      this.config = config;
    } catch (e) {
      this.config = {
        countdown_seconds: 3, countdown_play_sound: true, mirror_preview: true,
        countdown_sound: 'assets/sounds/beep.wav', shutter_sound: 'assets/sounds/shutter.wav',
        volume: 0.8, idle_return_seconds: 300,
      };
      console.warn('Falling back to default config:', e.message);
    }

    $('#audio-countdown').src = this.config.countdown_sound;
    $('#audio-shutter').src = this.config.shutter_sound;
    $('#audio-countdown').volume = this.config.volume;
    $('#audio-shutter').volume = this.config.volume;

    this.captureFlow = new CaptureFlow({
      camera: this.camera,
      stageEl: $('#camera-stage'),
      previewEl: $('#camera-template-preview'),
      frameImgEl: $('#camera-frame-overlay'),
      highlightEl: $('#camera-slot-highlight'),
      flashEl: $('#camera-flash'),
      countdownNumberEl: $('#countdown-number'),
      shutterAudioEl: $('#audio-shutter'),
      countdownAudioEl: $('#audio-countdown'),
    });
    this.captureFlow.jpegQuality = this.config.jpeg_quality || 0.92;
    this.captureFlow.countdown.playSound = this.config.countdown_play_sound;

    this.reviewGrid.onRetake = (slotNumber) => this.retakePhoto(slotNumber);

    // List devices and restore camera from saved storage
    const devices = await CameraController.listDevices();
    const saved = localStorage.getItem('photobooth_camera');
    if (saved && devices.some((d) => d.deviceId === saved)) {
      this.camera.deviceId = saved;
    } else if (saved) {
      localStorage.removeItem('photobooth_camera');
    }

    this.bindEvents();
    this.resetIdleTimer();
    this.goToGallery();
  }

  /* ---------------------------------------------------------------- */
  /* Screen switching                                                   */
  /* ---------------------------------------------------------------- */
  showScreen(name) {
    Object.entries(this.screens).forEach(([key, node]) => {
      node.classList.toggle('screen--active', key === name);
    });
  }

  /* ---------------------------------------------------------------- */
  /* Start camera early when gallery loads                             */
  /* ---------------------------------------------------------------- */
  async startCamera() {
    if (this.camera.stream) return;
    try {
      const template = this.gallery.getSelected() || this.gallery.templates[0];
      const w = template?.photos[0]?.width || 1280;
      const h = template?.photos[0]?.height || 720;
      await this.camera.start({ width: { min: w, ideal: 1280 }, height: { min: h, ideal: 720 } });
      this.camera.setMirrored(this.config.mirror_preview);
      this.attachModalVideo();
    } catch (_) { /* Camera will start when user clicks Continue */ }
  }

  attachModalVideo() {
    const modalVideo = $('#camera-modal-video');
    if (this.camera.stream) {
      modalVideo.srcObject = this.camera.stream;
      modalVideo.play().catch(() => {});
    }
  }

  /* ---------------------------------------------------------------- */
  /* Camera preview modal                                               */
  /* ---------------------------------------------------------------- */
  async populateCameraModalList() {
    const devices = await CameraController.listDevices();
    const list = $('#camera-modal-list');
    list.innerHTML = '';
    if (devices.length === 0) {
      list.innerHTML = '<div class="camera-modal__item" style="cursor:default;color:var(--color-text-muted)">No cameras detected</div>';
      return;
    }
    devices.forEach((d) => {
      const label = d.label || `Camera ${d.deviceId.slice(0, 8)}…`;
      const active = d.deviceId === this.camera.deviceId || (!this.camera.deviceId && !d.label && devices.length === 1);
      const btn = el('button', {
        class: 'camera-modal__item' + (active ? ' is-active' : ''),
        onClick: () => this.switchCamera(d.deviceId),
      }, label);
      list.appendChild(btn);
    });
  }

  openCameraModal() {
    $('#camera-modal').classList.remove('is-hidden');
    this.populateCameraModalList();
    this.attachModalVideo();
  }

  closeCameraModal() {
    $('#camera-modal').classList.add('is-hidden');
  }

  async switchCamera(deviceId) {
    this.camera.deviceId = deviceId;
    localStorage.setItem('photobooth_camera', deviceId);
    try {
      await this.camera.switchDevice(deviceId);
      this.camera.setMirrored(this.config.mirror_preview);
      this.attachModalVideo();
      this.populateCameraModalList();
    } catch (e) {
      toast('Could not switch camera: ' + e.message, 'error');
    }
  }

  /* ---------------------------------------------------------------- */
  /* Idle timeout -> auto-return to gallery                            */
  /* ---------------------------------------------------------------- */
  resetIdleTimer() {
    clearTimeout(this.idleTimer);
    if (this.screens.done.classList.contains('screen--active')) return;
    const seconds = this.config?.idle_return_seconds || 300;
    this.idleTimer = setTimeout(() => {
      toast('Session timed out — returning to start.');
      this.hardReset();
    }, seconds * 1000);
  }

  bindEvents() {
    document.addEventListener('pointerdown', () => this.resetIdleTimer());

    // Hidden admin entrance: tap the brand/logo 5 times within 2s.
    $('#brand-logo').addEventListener('click', () => {
      this.logoTapCount++;
      clearTimeout(this.logoTapTimer);
      this.logoTapTimer = setTimeout(() => (this.logoTapCount = 0), 2000);
      if (this.logoTapCount >= 5) {
        window.location.href = 'admin/settings.php';
      }
    });

    $('#btn-gallery-continue').addEventListener('click', () => this.startCameraScreen());

    $('#btn-camera-picker').addEventListener('click', () => this.openCameraModal());
    $('#btn-camera-modal-close').addEventListener('click', () => this.closeCameraModal());
    $('#camera-modal-backdrop').addEventListener('click', () => this.closeCameraModal());

    $('#btn-camera-cancel').addEventListener('click', () => this.hardReset());
    $('#btn-camera-start').addEventListener('click', () => this.runFullCapture());

    $('#btn-review-restart').addEventListener('click', () => this.hardReset());
    $('#btn-review-continue').addEventListener('click', () => this.goToFinal());

    $('#btn-final-back').addEventListener('click', () => this.showScreen('review'));
    $('#btn-final-redo').addEventListener('click', () => this.startCameraScreen());
    $('#btn-final-accept').addEventListener('click', () => this.acceptAndFinish());
  }

  /* ---------------------------------------------------------------- */
  /* Step: Load + show Gallery                                          */
  /* ---------------------------------------------------------------- */
  async goToGallery() {
    this.showScreen('gallery');
    try {
      await this.gallery.load();
      this.startCamera();
    } catch (e) {
      toast('Could not load templates: ' + e.message, 'error');
    }
  }

  /* ---------------------------------------------------------------- */
  /* Step: Gallery -> Camera (creates the session, starts webcam)      */
  /* ---------------------------------------------------------------- */
  async startCameraScreen() {
    const selected = this.gallery.getSelected();
    if (!selected) {
      toast('Please choose a frame first.', 'error');
      return;
    }
    this.template = selected;

    try {
      const session = await SessionClient.createSession(selected.id);
      this.sessionId = session.session_id;
    } catch (e) {
      toast('Could not start a session: ' + e.message, 'error');
      return;
    }

    this.showScreen('camera');
    this.captureFlow.setTemplate(this.template);
    this.camera.setMirrored(this.config.mirror_preview);
    this.renderProgressDots(0);
    $('#camera-title').textContent = `Get Ready — Photo 1 of ${this.template.photos.length}`;
    $('#btn-camera-start').disabled = false;
    $('#btn-camera-start').textContent = 'Start Capturing';

    if (!this.camera.stream) {
      try {
        await this.camera.start({
          width: { min: this.template.photos[0].width, ideal: 1280 },
          height: { min: this.template.photos[0].height, ideal: 720 },
        });
      } catch (e) {
        toast('Camera access was denied or unavailable: ' + e.message, 'error');
      }
    } else {
      this.camera.setMirrored(this.config.mirror_preview);
    }
  }

  renderProgressDots(currentIndex) {
    const wrap = $('#camera-progress');
    wrap.innerHTML = '';
    const total = this.template.photos.length;
    for (let i = 0; i < total; i++) {
      const dot = el('span', { class: 'progress-dot' });
      if (i < currentIndex) dot.classList.add('is-done');
      if (i === currentIndex) dot.classList.add('is-current');
      wrap.appendChild(dot);
    }
  }

  /* ---------------------------------------------------------------- */
  /* Step: run the full 4-photo capture sequence                       */
  /* ---------------------------------------------------------------- */
  async runFullCapture() {
    $('#btn-camera-start').disabled = true;
    $('#btn-camera-start').textContent = 'Capturing…';
    this.photoDataUrls = [];

    try {
      this.photoDataUrls = await this.captureFlow.captureAll(
        this.sessionId,
        this.config.countdown_seconds,
        ({ phase, n, total }) => {
          if (phase === 'starting') {
            $('#camera-title').textContent = `Get Ready — Photo ${n} of ${total}`;
            this.renderProgressDots(n - 1);
          }
          if (phase === 'captured') {
            this.renderProgressDots(n);
          }
        }
      );
    } catch (e) {
      toast('Capture failed: ' + e.message, 'error');
      $('#btn-camera-start').disabled = false;
      $('#btn-camera-start').textContent = 'Start Capturing';
      return;
    }

    this.reviewGrid.render(this.template, this.photoDataUrls);
    this.showScreen('review');
  }

  /* ---------------------------------------------------------------- */
  /* Retake a single photo from the Review screen                      */
  /* ---------------------------------------------------------------- */
  async retakePhoto(slotNumber) {
    this.showScreen('camera');
    $('#camera-title').textContent = `Retaking Photo ${slotNumber}`;
    this.renderProgressDots(slotNumber - 1);

    try {
      this.camera.setMirrored(this.config.mirror_preview);
      const dataUrl = await this.captureFlow.captureOne(this.sessionId, slotNumber, this.config.countdown_seconds);
      this.photoDataUrls[slotNumber - 1] = dataUrl;
      this.showScreen('review');
      return dataUrl;
    } catch (e) {
      toast('Retake failed: ' + e.message, 'error');
      this.showScreen('review');
      return null;
    }
  }

  /* ---------------------------------------------------------------- */
  /* Step: Review -> Final (server composites the strip)               */
  /* ---------------------------------------------------------------- */
  async goToFinal() {
    this.showScreen('final');
    $('#final-strip-img').src = '';
    $('#btn-final-accept').disabled = true;
    try {
      const { strip_url } = await SessionClient.generateStrip(this.sessionId);
      $('#final-strip-img').src = strip_url;
      $('#btn-final-accept').disabled = false;
    } catch (e) {
      toast('Could not generate your strip: ' + e.message, 'error');
    }
  }

  /* ---------------------------------------------------------------- */
  /* Step: Final -> Accept (auto-saves + auto-prints) -> Done           */
  /* ---------------------------------------------------------------- */
  async acceptAndFinish() {
    $('#btn-final-accept').disabled = true;
    let printResult = { status: 'skipped' };
    try {
      const res = await SessionClient.acceptStrip(this.sessionId);
      printResult = res.print_result;
    } catch (e) {
      toast('Could not finish: ' + e.message, 'error');
    }

    const message =
      printResult.status === 'printed' ? 'Your strip is printing — grab it from the printer!' :
      printResult.status === 'failed' ? 'Your strip has been saved, but printing failed. Please ask the staff.' :
      printResult.status === 'simulated' ? 'Your strip has been saved. (No printer is set up yet.)' :
      'Your strip has been saved.';
    $('#done-message').textContent = message;

    this.camera.stop();
    this.showScreen('done');
    this.runDoneCountdown(5);
  }

  runDoneCountdown(seconds) {
    let remaining = seconds;
    const label = $('#done-countdown');
    label.textContent = `Returning to start in ${remaining}s…`;
    clearInterval(this._doneInterval);
    this._doneInterval = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(this._doneInterval);
        this.hardReset();
        return;
      }
      label.textContent = `Returning to start in ${remaining}s…`;
    }, 1000);
  }

  /* ---------------------------------------------------------------- */
  /* Reset everything and go back to Gallery                           */
  /* ---------------------------------------------------------------- */
  hardReset() {
    clearInterval(this._doneInterval);
    this.sessionId = null;
    this.template = null;
    this.photoDataUrls = [];
    this.showScreen('gallery');
    clearTimeout(this.idleTimer);
  }
}

const app = new PhotoboothApp();
app.init();
