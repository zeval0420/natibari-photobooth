/**
 * assets/js/captureFlow.js
 * ------------------------------------------------------------------
 * Orchestrates the actual photo-taking: positions the template's frame
 * overlay + the current slot's highlight box on top of the live video,
 * runs the countdown, fires the flash + shutter sound, grabs the frame,
 * and uploads it. Used both for the initial 4-photo run and for single
 * re-takes triggered from the Review screen.
 * ------------------------------------------------------------------
 */
import { wait } from './utils.js';
import { Countdown } from './countdown.js';
import { SessionClient } from './sessionClient.js';

export class CaptureFlow {
  /**
   * @param {object} deps
   *  camera: CameraController
   *  stageEl: .camera-stage element
   *  frameImgEl: <img> overlay element
   *  highlightEl: slot highlight <div>
   *  flashEl: flash <div>
   *  countdownNumberEl, shutterAudioEl, countdownAudioEl
   */
  constructor(deps) {
    Object.assign(this, deps);
    this.countdown = new Countdown({
      numberEl: this.countdownNumberEl,
      audioEl: this.countdownAudioEl,
      playSound: true,
    });
  }

  /** Set the template currently framing the shot; positions the overlay image. */
  setTemplate(template) {
    this.template = template;
    this.frameImgEl.src = template.frame_url;
    this.frameImgEl.style.aspectRatio = `${template.output.width} / ${template.output.height}`;
    this.clearSlotPhotos();
    this.fitVideoToSlot(0);
    if (!this._resizeBound) {
      this._resizeBound = true;
      window.addEventListener('resize', () => this.fitVideoToSlot(this._fitSlotIndex || 0));
    }
  }

  /** Compute where the template's frame image actually renders inside the preview panel (object-fit:contain math). */
  _overlayBox() {
    const panelRect = this.previewEl.getBoundingClientRect();
    const imgRatio = this.template.output.width / this.template.output.height;
    const panelRatio = panelRect.width / panelRect.height;
    let w, h;
    if (imgRatio > panelRatio) {
      w = panelRect.width;
      h = w / imgRatio;
    } else {
      h = panelRect.height;
      w = h * imgRatio;
    }
    return {
      x: (panelRect.width - w) / 2,
      y: (panelRect.height - h) / 2,
      w, h,
    };
  }

  /** Move the dashed highlight box onto the slot the user is about to fill. */
  highlightSlot(index) {
    const slot = this.template.photos[index];
    const box = this._overlayBox();
    const scaleX = box.w / this.template.output.width;
    const scaleY = box.h / this.template.output.height;

    this.highlightEl.style.left = `${box.x + slot.x * scaleX}px`;
    this.highlightEl.style.top = `${box.y + slot.y * scaleY}px`;
    this.highlightEl.style.width = `${slot.width * scaleX}px`;
    this.highlightEl.style.height = `${slot.height * scaleY}px`;
    this.highlightEl.style.transform = `rotate(${slot.rotation || 0}deg)`;
    this.highlightEl.style.opacity = '1';
  }

  /**
   * Show the live feed cropped to the slot's aspect ratio (centred in the
   * stage) so the preview matches exactly what captureFrame() will save —
   * e.g. a near-square window shows a near-square feed, not a 16:9 one.
   */
  fitVideoToSlot(index) {
    const slot = this.template?.photos[index];
    const video = this.camera.video;
    const stage = this.stageEl;
    if (!slot || !stage) return;
    this._fitSlotIndex = index;
    const sw = stage.clientWidth;
    const sh = stage.clientHeight;
    if (!sw || !sh) return;
    const aspect = slot.width / slot.height;
    let w = sw;
    let h = w / aspect;
    if (h > sh) { h = sh; w = h * aspect; }
    Object.assign(video.style, {
      inset: 'auto',
      left: `${(sw - w) / 2}px`,
      top: `${(sh - h) / 2}px`,
      width: `${w}px`,
      height: `${h}px`,
      objectFit: 'cover',
    });
  }

  hideHighlight() {
    this.highlightEl.style.opacity = '0';
  }

  /** Fill a slot in the template preview with the captured photo. */
  _showSlotPhoto(index, dataUrl) {
    const slot = this.template.photos[index];
    const box = this._overlayBox();
    const scaleX = box.w / this.template.output.width;
    const scaleY = box.h / this.template.output.height;

    let img = this.slotCapturesEl.children[index];
    if (!img) {
      img = document.createElement('img');
      this.slotCapturesEl.appendChild(img);
    }

    img.src = dataUrl;
    img.style.left = `${box.x + slot.x * scaleX}px`;
    img.style.top = `${box.y + slot.y * scaleY}px`;
    img.style.width = `${slot.width * scaleX}px`;
    img.style.height = `${slot.height * scaleY}px`;
    img.style.transform = `rotate(${slot.rotation || 0}deg)`;
  }

  /** Remove all captured slot photos from the template preview. */
  clearSlotPhotos() {
    this.slotCapturesEl = this.slotCapturesEl || document.getElementById('camera-slot-captures');
    if (this.slotCapturesEl) this.slotCapturesEl.innerHTML = '';
  }

  /** Run the countdown, flash, shutter, capture, and upload for ONE slot (1-based index). */
  async captureOne(sessionId, slotNumber, countdownSeconds, onProgress = () => {}) {
    this.highlightSlot(slotNumber - 1);
    this.fitVideoToSlot(slotNumber - 1);
    await this.countdown.run(countdownSeconds, (n) => onProgress({ phase: 'countdown', n }));

    // Flash + shutter sound fire together at the instant of capture.
    this.flashEl.classList.remove('is-firing');
    this.stageEl.classList.remove('is-capturing');
    void this.flashEl.offsetWidth; // restart animation
    this.flashEl.classList.add('is-firing');
    this.stageEl.classList.add('is-capturing');
    if (this.shutterAudioEl) {
      this.shutterAudioEl.currentTime = 0;
      this.shutterAudioEl.play().catch(() => {});
    }

    const slot = this.template.photos[slotNumber - 1];
    const dataUrl = this.camera.captureFrame(this.jpegQuality || 0.95, slot.width / slot.height);
    onProgress({ phase: 'captured', n: slotNumber });

    await SessionClient.savePhoto(sessionId, slotNumber, dataUrl);
    this._showSlotPhoto(slotNumber - 1, dataUrl);
    await wait(120); // let the flash visually register before moving on
    this.hideHighlight();

    return dataUrl;
  }

  /** Run all 4 captures back-to-back, calling onProgress for UI updates between each. */
  async captureAll(sessionId, countdownSeconds, onProgress = () => {}) {
    const results = [];
    for (let i = 1; i <= this.template.photos.length; i++) {
      onProgress({ phase: 'starting', n: i, total: this.template.photos.length });
      const dataUrl = await this.captureOne(sessionId, i, countdownSeconds, onProgress);
      results.push(dataUrl);
      if (i < this.template.photos.length) await wait(650); // brief breather between shots
    }
    return results;
  }
}
