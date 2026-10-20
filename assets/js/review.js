/**
 * assets/js/review.js
 * ------------------------------------------------------------------
 * Renders the "Review your photos" screen. Shows the captured photos
 * composited into their slots inside the template frame, with per-slot
 * retake buttons.
 * ------------------------------------------------------------------
 */
import { el } from './utils.js';

export class ReviewGrid {
  constructor(containerEl) {
    this.container = containerEl;
    this.onRetake = null;
  }

  render(template, photoDataUrls) {
    this.container.innerHTML = '';

    const strip = el('div', {
      class: 'review-strip',
      style: `aspect-ratio:${template.output.width}/${template.output.height}`,
    });

    photoDataUrls.forEach((dataUrl, i) => {
      const slot = template.photos[i];
      const slotEl = el('div', {
        class: 'review-strip__slot',
        style: [
          `left:${(slot.x / template.output.width) * 100}%`,
          `top:${(slot.y / template.output.height) * 100}%`,
          `width:${(slot.width / template.output.width) * 100}%`,
          `height:${(slot.height / template.output.height) * 100}%`,
        ].join(';'),
      });

      const img = el('img', {
        class: 'review-strip__slot-img is-fresh',
        src: dataUrl,
        alt: `Photo ${i + 1}`,
      });
      slotEl.appendChild(img);

      const btn = el('button', {
        class: 'btn review-strip__retake',
        title: 'Retake this photo',
      }, '↻');
      btn.addEventListener('click', async () => {
        if (!this.onRetake) return;
        btn.disabled = true;
        try {
          const newDataUrl = await this.onRetake(i + 1);
          if (newDataUrl) {
            img.src = newDataUrl;
            img.classList.remove('is-fresh');
            void img.offsetWidth;
            img.classList.add('is-fresh');
          }
        } finally {
          btn.disabled = false;
        }
      });
      slotEl.appendChild(btn);

      strip.appendChild(slotEl);
    });

    strip.appendChild(el('img', {
      class: 'review-strip__frame',
      src: template.frame_url,
      alt: '',
    }));

    this.container.appendChild(strip);
  }
}
