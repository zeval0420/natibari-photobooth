/**
 * assets/js/templateGallery.js
 * ------------------------------------------------------------------
 * Renders the template picker screen. Templates are fetched fresh
 * from api/templates.php every time this screen is shown, so any
 * folder dropped into templates/ on the server appears automatically
 * with zero frontend changes.
 * ------------------------------------------------------------------
 */
import { el, $ } from './utils.js';
import { SessionClient } from './sessionClient.js';

export class TemplateGallery {
  constructor(containerEl) {
    this.container = containerEl;
    this.templates = [];
    this.selectedId = null;
  }

  async load() {
    this.container.innerHTML = '<div class="center-col" style="padding:48px"><div class="spinner"></div></div>';
    const { templates, default_template } = await SessionClient.getTemplates();
    this.templates = templates;
    this.selectedId = default_template || (templates[0] && templates[0].id) || null;
    this.render();
    return templates;
  }

  render() {
    this.container.innerHTML = '';
    if (this.templates.length === 0) {
      this.container.appendChild(
        el('div', { class: 'empty-state' }, 'No templates found. Add a folder under templates/ with config.json, frame.png and thumbnail.png.')
      );
      return;
    }
    this.templates.forEach((tpl, i) => {
      const card = el('div', {
        class: `template-card${tpl.id === this.selectedId ? ' is-selected' : ''}`,
        style: `animation-delay:${i * 45}ms`,
        onClick: () => this.select(tpl.id),
        dataset: { templateId: tpl.id },
      }, [
        el('div', { class: 'template-card__thumb' }, el('img', { src: tpl.thumbnail_url, alt: tpl.name, loading: 'lazy' })),
        el('div', { class: 'template-card__label' }, tpl.name),
      ]);
      this.container.appendChild(card);
    });
  }

  select(id) {
    this.selectedId = id;
    Array.from(this.container.children).forEach((card) => {
      card.classList.toggle('is-selected', card.dataset.templateId === id);
    });
    if (this.onSelect) this.onSelect(id);
  }

  getSelected() {
    return this.templates.find((t) => t.id === this.selectedId) || null;
  }
}
