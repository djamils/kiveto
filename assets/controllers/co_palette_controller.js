import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — ⌘K / Ctrl+K: a search over clinics, staff, owners,
 * features and pages. Opens a native <dialog>, asks the console's search
 * as one types (JSON), moves with the arrows and opens with Enter.
 */
export default class extends Controller {
  static values = { url: String };

  connect() {
    this.items = [];
    this.active = -1;
  }

  disconnect() {
    clearTimeout(this.timer);
    this.dialog?.remove();
  }

  shortcut(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.open();
    }
  }

  open() {
    if (!this.dialog) {
      this.build();
    }
    this.dialog.showModal();
    this.input.select();
  }

  build() {
    this.dialog = document.createElement('dialog');
    this.dialog.className = 'co-palette';
    this.dialog.setAttribute('aria-label', 'Recherche');

    const field = document.createElement('div');
    field.className = 'co-palette-field';
    this.input = document.createElement('input');
    this.input.type = 'search';
    this.input.placeholder = 'Clinique, utilisateur, SIRET, e-mail, téléphone…';
    this.input.setAttribute('aria-label', 'Rechercher');
    this.input.addEventListener('input', () => this.search());
    this.input.addEventListener('keydown', (event) => this.navigate(event));
    const hint = document.createElement('kbd');
    hint.textContent = 'Échap';
    field.append(this.input, hint);

    this.list = document.createElement('div');
    this.list.className = 'co-palette-list';
    this.list.setAttribute('role', 'listbox');
    this.status = document.createElement('p');
    this.status.className = 'co-palette-status';
    this.status.textContent = 'Tape au moins deux caractères.';

    this.dialog.append(field, this.list, this.status);
    this.dialog.addEventListener('click', (event) => {
      if (event.target === this.dialog) {
        this.dialog.close();
      }
    });
    (this.element.closest('.co') ?? document.body).append(this.dialog);
  }

  search() {
    clearTimeout(this.timer);
    const query = this.input.value.trim();
    if (query.length < 2) {
      this.render([], 'Tape au moins deux caractères.');
      return;
    }
    this.timer = setTimeout(() => this.fetch(query), 200);
  }

  async fetch(query) {
    const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok || query !== this.input.value.trim()) {
      return;
    }
    const data = await response.json();
    this.render(data.groups || [], 'Aucun résultat.');
  }

  render(groups, empty) {
    this.items = [];
    this.active = -1;
    const nodes = [];
    for (const group of groups) {
      const title = document.createElement('p');
      title.className = 'co-palette-group';
      title.textContent = group.label;
      nodes.push(title);
      for (const item of group.items) {
        const link = document.createElement('a');
        link.href = item.url;
        link.setAttribute('role', 'option');
        const label = document.createElement('b');
        label.textContent = item.label;
        const sub = document.createElement('span');
        sub.textContent = item.sub;
        link.append(label, sub);
        link.addEventListener('mousemove', () => this.highlight(this.items.indexOf(link)));
        this.items.push(link);
        nodes.push(link);
      }
    }
    this.list.replaceChildren(...nodes);
    this.status.hidden = this.items.length > 0;
    this.status.textContent = empty;
    this.highlight(this.items.length > 0 ? 0 : -1);
  }

  navigate(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const step = event.key === 'ArrowDown' ? 1 : -1;
      this.highlight((this.active + step + this.items.length) % Math.max(1, this.items.length));
    } else if (event.key === 'Enter' && this.active >= 0) {
      event.preventDefault();
      window.location.assign(this.items[this.active].href);
    }
  }

  highlight(index) {
    this.items.forEach((item, i) => item.classList.toggle('is-active', i === index));
    this.active = index;
    this.items[index]?.scrollIntoView({ block: 'nearest' });
  }
}
