/**
 * assets/js/countdown.js
 * ------------------------------------------------------------------
 * Plays the visual "3...2...1" countdown plus the per-tick beep sound,
 * then resolves so the caller can take the photo. Sound + duration are
 * fully driven by settings (countdown.seconds / countdown.play_sound /
 * audio.countdown_sound) — nothing is hard-coded here.
 * ------------------------------------------------------------------
 */
import { wait } from './utils.js';

export class Countdown {
  constructor({ numberEl, audioEl, playSound = true }) {
    this.numberEl = numberEl;
    this.audioEl = audioEl;
    this.playSound = playSound;
  }

  /** Run the countdown from `seconds` down to 1, calling onTick each step. */
  async run(seconds, onTick = () => {}) {
    for (let n = seconds; n >= 1; n--) {
      this.numberEl.textContent = String(n);
      this.numberEl.classList.remove('is-tick');
      // restart the CSS animation by forcing reflow
      // eslint-disable-next-line no-unused-expressions
      this.numberEl.offsetWidth;
      this.numberEl.classList.add('is-tick');

      if (this.playSound && this.audioEl) {
        this.audioEl.currentTime = 0;
        this.audioEl.play().catch(() => {/* autoplay restrictions: ignore silently */});
      }
      onTick(n);
      await wait(1000);
    }
    this.numberEl.textContent = '';
  }
}
