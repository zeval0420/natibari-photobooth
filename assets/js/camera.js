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
    const buildConstraints = (deviceId) => ({
      audio: false,
      video: {
        width: toConstraint(opts.width, 1920),
        height: toConstraint(opts.height, 1080),
        aspectRatio: opts.aspectRatio ?? { ideal: 16 / 9 },
        facingMode: deviceId ? undefined : 'user',
        deviceId: deviceId ? { exact: deviceId } : undefined,
      },
    });
    try {
      this.stream = await navigator.mediaDevices.getUserMedia(buildConstraints(did));
    } catch (_) {
      if (!did) throw _;
      this.deviceId = null;
      this.stream = await navigator.mediaDevices.getUserMedia(buildConstraints(null));
    }
    this.video.srcObject = this.stream;
    await this.video.play();
    await this._waitForFrame();
    return this.stream;
  }

  async switchDevice(deviceId) {
    this.deviceId = deviceId;
    const w = this.video.videoWidth || 1280;
    const h = this.video.videoHeight || 720;
    return this.start({ deviceId, width: w, height: h });
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
  captureFrame(quality = 0.92, targetAspect = null) {
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

    if (this.isMirrored) {
      ctx.translate(w, 0);
      ctx.scale(-1, 1);
    }
    ctx.drawImage(video, sx, sy, w, h, 0, 0, w, h);

    return this.captureCanvas.toDataURL('image/jpeg', quality);
  }
}
