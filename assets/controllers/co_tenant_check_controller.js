import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — B3 « Identifiant du tenant »: suggested from the
 * displayed name until edited by hand, checked live; and the address block
 * shown when the establishment is not at the head office.
 */
export default class extends Controller {
  static targets = ['name', 'slug', 'hint', 'address'];
  static values = { url: String };

  connect() {
    this.edited = this.slugTarget.value !== '';
  }

  disconnect() {
    clearTimeout(this.timer);
  }

  suggest() {
    if (this.edited) {
      return;
    }
    this.slugTarget.value = this.nameTarget.value
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/\b(clinique|cabinet|veterinaire|veterinaires|de|du|des|la|le|les)\b/g, ' ')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
    this.schedule();
  }

  check() {
    this.edited = true;
    this.schedule();
  }

  schedule() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.ask(), 250);
  }

  async ask() {
    const slug = this.slugTarget.value.trim();
    if (slug === '') {
      this.show('Non modifiable après création', '');
      return;
    }
    const response = await fetch(`${this.urlValue}?what=tenant&q=${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' } });
    const data = response.ok ? await response.json() : {};
    const messages = {
      available: ['Disponible · non modifiable après création', 'is-ok'],
      taken: ['Déjà pris par une autre clinique', 'is-error'],
      invalid: ['Minuscules, chiffres et tirets seulement', 'is-error'],
    };
    const [text, kind] = messages[data.check] || ['Non modifiable après création', ''];
    this.show(text, kind);
  }

  show(text, kind) {
    this.hintTarget.textContent = text;
    this.hintTarget.className = `co-hint ${kind}`.trim();
  }

  address(event) {
    this.addressTarget.hidden = event.currentTarget.checked;
  }
}
