/**
 * creator/js/PropsPanel.js
 * ------------------------------------------------------------------
 * Keeps the right-hand "Properties" panel in sync with the currently
 * selected slot. When the user types new values, it fires onChange()
 * so SlotManager can apply them to the real slot object.
 * ------------------------------------------------------------------
 */
export class PropsPanel {
  constructor(containerEl) {
    this.container = containerEl;
    this.onChange  = () => {};
    this._build();
    this._bind();
  }

  _build() {
    this.container.innerHTML = `
      <div class="props-section">
        <h3>Position</h3>
        <div class="prop-row">
          <div class="prop-field"><label>X</label><input type="number" id="pp-x" step="1"></div>
          <div class="prop-field"><label>Y</label><input type="number" id="pp-y" step="1"></div>
        </div>
      </div>
      <div class="props-section">
        <h3>Size</h3>
        <div class="prop-row">
          <div class="prop-field"><label>W</label><input type="number" id="pp-w" step="1" min="1"></div>
          <div class="prop-field"><label>H</label><input type="number" id="pp-h" step="1" min="1"></div>
        </div>
        <div class="prop-switch">
          <label>Lock aspect ratio</label>
          <div class="switch" id="pp-lock"><div class="switch__knob"></div></div>
        </div>
      </div>
      <div class="props-section">
        <h3>Rotation</h3>
        <div class="prop-field">
          <label>Degrees</label>
          <input type="number" id="pp-rot" step="0.1" min="-360" max="360">
        </div>
      </div>
      <div class="props-section" id="pp-empty-hint" style="color:var(--color-text-faint);font-size:13px">
        Select a photo slot to edit its properties.
      </div>`;
  }

  _bind() {
    const fire = () => {
      this.onChange({
        x:        parseFloat(document.getElementById('pp-x').value) || 0,
        y:        parseFloat(document.getElementById('pp-y').value) || 0,
        width:    parseFloat(document.getElementById('pp-w').value) || 1,
        height:   parseFloat(document.getElementById('pp-h').value) || 1,
        rotation: parseFloat(document.getElementById('pp-rot').value) || 0,
      });
    };
    ['pp-x','pp-y','pp-w','pp-h','pp-rot'].forEach(id => {
      document.getElementById(id).addEventListener('change', fire);
    });
    document.getElementById('pp-lock').addEventListener('click', e => {
      e.currentTarget.classList.toggle('is-on');
      if (this._slot) this._slot.lockAspect = e.currentTarget.classList.contains('is-on');
    });
  }

  /** Populate the panel with the values of `slot`, or blank it out if null. */
  update(slot) {
    this._slot = slot;
    const hint = document.getElementById('pp-empty-hint');
    hint.style.display = slot ? 'none' : 'block';
    if (!slot) return;
    document.getElementById('pp-x').value   = Math.round(slot.x);
    document.getElementById('pp-y').value   = Math.round(slot.y);
    document.getElementById('pp-w').value   = Math.round(slot.width);
    document.getElementById('pp-h').value   = Math.round(slot.height);
    document.getElementById('pp-rot').value = parseFloat(slot.rotation.toFixed(1));
    document.getElementById('pp-lock').classList.toggle('is-on', !!slot.lockAspect);
  }
}
