/**
 * creator/js/SlotManager.js
 * ------------------------------------------------------------------
 * Owns every photo-slot DOM node on the canvas stage.  A "slot" is a
 * plain JS object + a corresponding absolutely-positioned <div> that
 * carries drag, resize and rotation handles.
 *
 * Co-ordinates are in canvas-space (pixels at 1:1 scale with the
 * full-resolution output).  The canvas stage is scaled by CSS
 * transform:scale() to fit the viewport; all mouse events are
 * converted back to canvas-space before being stored.
 *
 * Publishes:
 *   onSelect(slot | null)   — whenever selection changes
 *   onChange()              — after any move/resize/rotate
 * ------------------------------------------------------------------
 */
export class SlotManager {
  constructor(stageEl, getScale) {
    this.stage    = stageEl;   // the #canvas-stage div
    this.getScale = getScale;  // () => current CSS scale factor
    this.slots    = [];        // [{ id, el, x, y, width, height, rotation, lockAspect }]
    this.selected = null;
    this.onSelect = () => {};
    this.onChange = () => {};
    this._nextId  = 1;
    this._drag    = null;      // active drag/resize/rotate state
    this._snapEnabled = true;
    this._stageW  = 0;
    this._stageH  = 0;

    this._bindStageEvents();
  }

  setDimensions(w, h) { this._stageW = w; this._stageH = h; }
  setSnapEnabled(v)   { this._snapEnabled = v; }

  // ---- Public API -------------------------------------------------------

  /** Add a new slot at the given canvas-space co-ordinates. */
  add(x = 60, y = 60, width = 300, height = 200, rotation = 0) {
    const id  = this._nextId++;
    const slot = { id, x, y, width, height, rotation, lockAspect: false };
    const el  = this._buildEl(slot);
    this.stage.appendChild(el);
    slot.el = el;
    this.slots.push(slot);
    this._positionEl(slot);
    this.select(slot);
    this.onChange();
    return slot;
  }

  /** Load slots from saved config (e.g. when opening an existing template). */
  loadAll(photos) {
    this.clear();
    photos.forEach(p => this.add(p.x, p.y, p.width, p.height, p.rotation ?? 0));
    this.select(null);
  }

  select(slot) {
    this.slots.forEach(s => s.el.classList.toggle('is-selected', s === slot));
    this.selected = slot;
    this.onSelect(slot);
  }

  removeSelected() {
    if (!this.selected) return;
    this.selected.el.remove();
    this.slots = this.slots.filter(s => s !== this.selected);
    // Re-number labels.
    this.slots.forEach((s, i) => {
      s.el.querySelector('.photo-slot__label').textContent = `Photo ${i + 1}`;
      s.el.querySelector('.slot-badge').textContent        = String(i + 1);
    });
    this.selected = null;
    this.onSelect(null);
    this.onChange();
  }

  clear() {
    this.slots.forEach(s => s.el.remove());
    this.slots    = [];
    this.selected = null;
    this._nextId  = 1;
    this.onSelect(null);
  }

  /** Export slots as a plain JSON-serialisable array. */
  export() {
    return this.slots.map(s => ({
      x: Math.round(s.x), y: Math.round(s.y),
      width: Math.round(s.width), height: Math.round(s.height),
      rotation: parseFloat(s.rotation.toFixed(2)),
    }));
  }

  /** Update the selected slot from the properties panel inputs. */
  applyProps({ x, y, width, height, rotation }) {
    if (!this.selected) return;
    const s = this.selected;
    if (x        !== undefined) s.x        = x;
    if (y        !== undefined) s.y        = y;
    if (width    !== undefined) s.width    = Math.max(10, width);
    if (height   !== undefined) s.height   = Math.max(10, height);
    if (rotation !== undefined) s.rotation = rotation;
    this._positionEl(s);
    this.onChange();
  }

  // ---- Alignment helpers ------------------------------------------------

  alignLeft()    { this._transformAll(s => ({ x: 0 })); }
  alignCenterH() { this._transformAll(s => ({ x: Math.round((this._stageW - s.width) / 2) })); }
  alignRight()   { this._transformAll(s => ({ x: this._stageW - s.width })); }
  alignTop()     { this._transformAll(s => ({ y: 0 })); }
  alignCenterV() { this._transformAll(s => ({ y: Math.round((this._stageH - s.height) / 2) })); }
  alignBottom()  { this._transformAll(s => ({ y: this._stageH - s.height })); }

  distributeVertically() {
    if (this.slots.length < 2) return;
    const sorted = [...this.slots].sort((a, b) => a.y - b.y);
    const totalH = sorted.reduce((sum, s) => sum + s.height, 0);
    const gap    = Math.round((this._stageH - totalH) / (sorted.length + 1));
    let cur = gap;
    sorted.forEach(s => { s.y = cur; cur += s.height + gap; this._positionEl(s); });
    this.onChange();
  }

  distributeHorizontally() {
    if (this.slots.length < 2) return;
    const sorted = [...this.slots].sort((a, b) => a.x - b.x);
    const totalW = sorted.reduce((sum, s) => sum + s.width, 0);
    const gap    = Math.round((this._stageW - totalW) / (sorted.length + 1));
    let cur = gap;
    sorted.forEach(s => { s.x = cur; cur += s.width + gap; this._positionEl(s); });
    this.onChange();
  }

  matchWidth() {
    if (!this.selected || this.slots.length < 2) return;
    const w = this.selected.width;
    this.slots.forEach(s => { s.width = w; this._positionEl(s); });
    this.onChange();
  }

  matchHeight() {
    if (!this.selected || this.slots.length < 2) return;
    const h = this.selected.height;
    this.slots.forEach(s => { s.height = h; this._positionEl(s); });
    this.onChange();
  }

  bringForward() {
    if (!this.selected) return;
    const idx = this.slots.indexOf(this.selected);
    if (idx < this.slots.length - 1) {
      [this.slots[idx], this.slots[idx+1]] = [this.slots[idx+1], this.slots[idx]];
      this.stage.appendChild(this.selected.el);
      this.onChange();
    }
  }

  sendBackward() {
    if (!this.selected) return;
    const idx = this.slots.indexOf(this.selected);
    if (idx > 0) {
      [this.slots[idx], this.slots[idx-1]] = [this.slots[idx-1], this.slots[idx]];
      this.stage.insertBefore(this.selected.el, this.slots[idx-1].el);
      this.onChange();
    }
  }

  resetAll() {
    this.slots.forEach(s => { s.rotation = 0; this._positionEl(s); });
    this.onChange();
    if (this.selected) this.onSelect(this.selected);
  }

  // ---- Validation -------------------------------------------------------

  validate() {
    const warnings = [];
    const W = this._stageW, H = this._stageH;
    this.slots.forEach((s, i) => {
      if (s.x < 0 || s.y < 0 || s.x + s.width > W || s.y + s.height > H) {
        warnings.push(`Photo ${i+1} extends outside the frame.`);
      }
    });
    for (let i = 0; i < this.slots.length; i++) {
      for (let j = i+1; j < this.slots.length; j++) {
        if (this._overlaps(this.slots[i], this.slots[j])) {
          warnings.push(`Photo ${i+1} overlaps Photo ${j+1}.`);
        }
      }
    }
    return warnings;
  }

  _overlaps(a, b) {
    return !(a.x + a.width  <= b.x || b.x + b.width  <= a.x ||
             a.y + a.height <= b.y || b.y + b.height <= a.y);
  }

  // ---- DOM building -----------------------------------------------------

  _buildEl(slot) {
    const el = document.createElement('div');
    el.className = 'photo-slot';
    el.innerHTML = `
      <span class="slot-badge">${this.slots.length + 1}</span>
      <span class="photo-slot__label">Photo ${this.slots.length + 1}</span>
      <div class="rotation-handle" title="Drag to rotate">↻</div>
      <div class="resize-handle" data-dir="nw"></div>
      <div class="resize-handle" data-dir="n"></div>
      <div class="resize-handle" data-dir="ne"></div>
      <div class="resize-handle" data-dir="e"></div>
      <div class="resize-handle" data-dir="se"></div>
      <div class="resize-handle" data-dir="s"></div>
      <div class="resize-handle" data-dir="sw"></div>
      <div class="resize-handle" data-dir="w"></div>`;

    el.addEventListener('pointerdown', e => this._onSlotPointerDown(e, slot, 'move'));
    el.querySelector('.rotation-handle').addEventListener('pointerdown', e => {
      e.stopPropagation();
      this._onSlotPointerDown(e, slot, 'rotate');
    });
    el.querySelectorAll('.resize-handle').forEach(h => {
      h.addEventListener('pointerdown', e => {
        e.stopPropagation();
        this._onSlotPointerDown(e, slot, 'resize', h.dataset.dir);
      });
    });
    return el;
  }

  _positionEl(slot) {
    const s = slot.el.style;
    s.left      = slot.x + 'px';
    s.top       = slot.y + 'px';
    s.width     = slot.width  + 'px';
    s.height    = slot.height + 'px';
    s.transform = `rotate(${slot.rotation}deg)`;
  }

  _transformAll(fn) {
    this.slots.forEach(s => {
      const patch = fn(s);
      Object.assign(s, patch);
      this._positionEl(s);
    });
    this.onChange();
  }

  // ---- Pointer events ---------------------------------------------------

  _onSlotPointerDown(e, slot, mode, dir = null) {
    e.preventDefault();
    this.select(slot);
    const scale  = this.getScale();
    const stageR = this.stage.getBoundingClientRect();

    this._drag = {
      mode, slot, dir, scale, stageR,
      startX: e.clientX, startY: e.clientY,
      origX: slot.x, origY: slot.y,
      origW: slot.width, origH: slot.height,
      origRot: slot.rotation,
      aspectRatio: slot.width / slot.height,
      // For rotation: centre of the slot in screen space.
      cx: stageR.left + (slot.x + slot.width/2)  * scale,
      cy: stageR.top  + (slot.y + slot.height/2) * scale,
    };

    const onMove = e => this._onPointerMove(e);
    const onUp   = () => {
      this._drag = null;
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      this.onChange();
    };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
  }

  _onPointerMove(e) {
    if (!this._drag) return;
    const d = this._drag;
    const sc = d.scale;
    const dx = (e.clientX - d.startX) / sc;
    const dy = (e.clientY - d.startY) / sc;

    if (d.mode === 'move') {
      let nx = d.origX + dx;
      let ny = d.origY + dy;
      if (this._snapEnabled) [nx, ny] = this._snapPosition(d.slot, nx, ny);
      d.slot.x = Math.round(nx);
      d.slot.y = Math.round(ny);
    } else if (d.mode === 'rotate') {
      const angle = Math.atan2(e.clientY - d.cy, e.clientX - d.cx) * 180 / Math.PI + 90;
      d.slot.rotation = parseFloat(angle.toFixed(1));
    } else if (d.mode === 'resize') {
      this._applyResize(d, dx, dy, e);
    }

    this._positionEl(d.slot);
    this.onSelect(d.slot); // refresh props panel
  }

  _applyResize(d, dx, dy, e) {
    const dir = d.dir;
    let nx = d.origX, ny = d.origY, nw = d.origW, nh = d.origH;
    const lock = d.slot.lockAspect;

    if (dir.includes('e'))  nw = Math.max(10, d.origW + dx);
    if (dir.includes('s'))  nh = Math.max(10, d.origH + dy);
    if (dir.includes('w')) { nw = Math.max(10, d.origW - dx); nx = d.origX + (d.origW - nw); }
    if (dir.includes('n')) { nh = Math.max(10, d.origH - dy); ny = d.origY + (d.origH - nh); }

    if (lock) {
      const ar = d.aspectRatio;
      if (dir === 'se' || dir === 'e' || dir === 's') {
        nh = Math.round(nw / ar);
      } else if (dir === 'nw') {
        const bigger = Math.max(d.origW - dx, d.origH - dy);
        nw = bigger; nh = Math.round(bigger / ar);
        nx = d.origX + d.origW - nw;
        ny = d.origY + d.origH - nh;
      } else {
        nw = Math.round(nh * ar);
      }
    }

    d.slot.x = Math.round(nx); d.slot.y = Math.round(ny);
    d.slot.width = Math.round(nw); d.slot.height = Math.round(nh);
  }

  _snapPosition(slot, nx, ny, threshold = 10) {
    const W = this._stageW, H = this._stageH;
    const cx = nx + slot.width/2, cy = ny + slot.height/2;
    const re = nx + slot.width,   be = ny + slot.height;

    const snapX = [0, W/2, W, cx, re, nx];
    const snapY = [0, H/2, H, cy, be, ny];

    let sx = nx, sy = ny;
    for (const anchor of [0, W/2 - slot.width/2, W - slot.width]) {
      if (Math.abs(nx - anchor) < threshold) { sx = anchor; break; }
    }
    for (const anchor of [0, H/2 - slot.height/2, H - slot.height]) {
      if (Math.abs(ny - anchor) < threshold) { sy = anchor; break; }
    }
    return [sx, sy];
  }

  _bindStageEvents() {
    this.stage.addEventListener('pointerdown', e => {
      if (e.target === this.stage || e.target.classList.contains('stage-frame')) {
        this.select(null);
      }
    });
  }
}
