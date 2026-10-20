/**
 * creator/js/CanvasRenderer.js
 * ------------------------------------------------------------------
 * Manages the visual stage: sets CSS transform scale so the full-res
 * template always fits the viewport, renders the frame <img>,
 * shows/hides snap-guide lines, and provides helpers that translate
 * between screen-space (CSS pixels) and canvas-space (output pixels).
 * ------------------------------------------------------------------
 */
export class CanvasRenderer {
  constructor(stageEl, viewportEl) {
    this.stage    = stageEl;
    this.viewport = viewportEl;
    this.scale    = 1;
    this.frameImg = stageEl.querySelector('.stage-frame');
    this._guides  = [];
  }

  /** Set the stage size to match the template output resolution. */
  setSize(w, h) {
    this.stage.style.width  = w + 'px';
    this.stage.style.height = h + 'px';
    this._outW = w;
    this._outH = h;
    this.fitToViewport();
  }

  /** Load (or replace) the frame image. */
  setFrame(url) {
    this.frameImg.src = url;
    this.frameImg.style.zIndex = '10'; // always on top of slots
  }

  /** Scale the stage so it fits comfortably inside the scroll viewport. */
  fitToViewport() {
    const vr = this.viewport.getBoundingClientRect();
    const pad = 80; // breathing room
    const sx  = (vr.width  - pad) / this._outW;
    const sy  = (vr.height - pad) / this._outH;
    this.scale = Math.min(1, sx, sy);
    this._applyScale();
  }

  zoom(delta) {
    this.scale = Math.max(0.1, Math.min(4, this.scale + delta));
    this._applyScale();
    return Math.round(this.scale * 100);
  }

  _applyScale() {
    this.stage.style.transform       = `scale(${this.scale})`;
    this.stage.style.transformOrigin = 'top left';
    // Update the scroll viewport's logical size so scrollbars appear correctly.
    this.stage.parentElement.style.setProperty('--stage-w', (this._outW * this.scale) + 'px');
    this.stage.parentElement.style.setProperty('--stage-h', (this._outH * this.scale) + 'px');
  }

  getScale() { return this.scale; }

  // ---- Snap guides -------------------------------------------------------

  showGuides(positions) {
    this.clearGuides();
    positions.forEach(({ type, value }) => {
      const el = document.createElement('div');
      el.className = 'snap-guide ' + (type === 'h' ? 'snap-guide--h' : 'snap-guide--v');
      if (type === 'h') el.style.top  = value + 'px';
      else              el.style.left = value + 'px';
      this.stage.appendChild(el);
      this._guides.push(el);
    });
  }

  clearGuides() {
    this._guides.forEach(g => g.remove());
    this._guides = [];
  }
}
