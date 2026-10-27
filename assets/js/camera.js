/**
 * assets/js/camera.js
 * ------------------------------------------------------------------
 * Owns everything related to the webcam: requesting permission,
 * listing devices, starting/stopping the stream, and grabbing a still
 * frame as a JPEG data URL. Knows nothing about templates, countdowns
 * or sessions — single responsibility, per the architecture brief.
 * ------------------------------------------------------------------
 */
export class CameraController {
  constructor(videoEl) {
    this.video = videoEl;
    this.stream = null;
    this.captureCanvas = document.createElement('canvas');
  }

  /** List available video input devices (for the Settings page). */
  static async listDevices() {
    if (!navigator.mediaDevices?.enumerateDevices) return [];
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.filter((d) => d.kind === 'videoinput');
  }

  /**
   * Start (or restart) the webcam stream.
   * @param {object} opts { deviceId, width, height }
   *   width/height can be a number (ideal) or a constraint object ({ min, ideal, max }).
   */
  async start(opts = {}) {
    this.stop();
    const did = opts.deviceId || this.deviceId;
    const toConstraint = (v, fallback) =>
      v == null ? { ideal: fallback } : typeof v === 'number' ? { ideal: v } : v;
    // Ask for 4K; the browser picks the best mode the camera really offers.
    // `min` keeps it at HD or better, and the last attempt drops it so a
    // sub-HD camera still works.
    const buildConstraints = (deviceId, requireHd) => ({
      audio: false,
      video: {
        width: { ...toConstraint(opts.width, 3840), ...(requireHd ? { min: 1280 } : {}) },
        height: { ...toConstraint(opts.height, 2160), ...(requireHd ? { min: 720 } : {}) },
        aspectRatio: opts.aspectRatio ?? { ideal: 16 / 9 },
        facingMode: deviceId ? undefined : 'user',
        deviceId: deviceId ? { exact: deviceId } : undefined,
      },
    });
    const attempts = did
      ? [[did, true], [did, false], [null, true], [null, false]]
      : [[null, true], [null, false]];
    let lastErr;
    for (const [deviceId, requireHd] of attempts) {
      try {
        this.stream = await navigator.mediaDevices.getUserMedia(buildConstraints(deviceId, requireHd));
        if (did && !deviceId) this.deviceId = null;
        lastErr = null;
        break;
      } catch (e) {
        lastErr = e;
        if (e?.name === 'NotAllowedError' || e?.name === 'SecurityError') break;
      }
    }
    if (lastErr) throw lastErr;
    this.video.srcObject = this.stream;
    await this.video.play();
    await this._waitForFrame();
    return this.stream;
  }

  async switchDevice(deviceId) {
    this.deviceId = deviceId;
    return this.start({ deviceId });
  }

  stop() {
    if (this.stream) {
      this.stream.getTracks().forEach((track) => track.stop());
      this.stream = null;
    }
  }

  /** Wait until the video element has actual frame dimensions (3s timeout). */
  async _waitForFrame() {
    const deadline = Date.now() + 3000;
    while (!this.video.videoWidth || !this.video.videoHeight) {
      if (Date.now() > deadline) throw new Error('Camera stream timed out waiting for frame data.');
      await new Promise((r) => requestAnimationFrame(r));
    }
  }

  get isMirrored() {
    return this.video.classList.contains('is-mirrored');
  }

  setMirrored(mirrored) {
    this.video.classList.toggle('is-mirrored', !!mirrored);
  }

  /**
   * Grab the current video frame as a JPEG data URL.
   * If the preview is mirrored on screen, the capture is mirrored too
   * so the saved photo matches exactly what the user saw of themselves.
   */
  captureFrame(quality = 0.95, targetAspect = null) {
    const video = this.video;
    const vw = video.videoWidth;
    const vh = video.videoHeight;
    if (!vw || !vh) throw new Error('Camera is not ready yet.');

    // Center-crop to the slot's aspect ratio here so the saved photo already
    // matches the slot and the server's cover-fit has nothing left to crop.
    let sx = 0, sy = 0, w = vw, h = vh;
    if (targetAspect && targetAspect > 0) {
      if (vw / vh > targetAspect) {
        w = Math.round(vh * targetAspect);
        sx = Math.round((vw - w) / 2);
      } else {
        h = Math.round(vw / targetAspect);
        sy = Math.round((vh - h) / 2);
      }
    }

    this.captureCanvas.width = w;
    this.captureCanvas.height = h;
    const ctx = this.captureCanvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';

    if (this.isMirrored) {
      ctx.translate(w, 0);
      ctx.scale(-1, 1);
    }
    ctx.drawImage(video, sx, sy, w, h, 0, 0, w, h);

    return this.captureCanvas.toDataURL('image/jpeg', quality);
  }
}
