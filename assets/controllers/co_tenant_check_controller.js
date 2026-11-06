import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — B3 « Établissement »:
 * - the company chosen in the registry names the clinic (its legal name,
 *   in readable case), until the name is typed by hand;
 * - the tenant is made from the name and shown as text, checked live;
 *   « Modifier » opens it to type another;
 * - the address block shows when the establishment is not at the head
 *   office.
 * Without JavaScript the tenant is a plain field.
 */
const SMALL_WORDS = new Set(['de', 'du', 'des', 'la', 'le', 'les', 'et', 'd', 'l', 'en', 'sur', 'sous', 'aux', 'au', 'à']);
const ACCENTED = {
  veterinaire: 'vétérinaire', veterinaires: 'vétérinaires', hopital: 'hôpital', etang: 'étang', eglise: 'église',
  ecole: 'école', medical: 'médical', medicale: 'médicale', sante: 'santé', cote: 'côte', vallee: 'vallée',
};
const LEGAL_FORMS = /\b(SELARL|SELAS|SELURL|SELAFA|SAS|SASU|SARL|EURL|SCP|SCM|SNC|SA)\b\.?/gi;

export default class extends Controller {
  static targets = ['name', 'slug', 'slugView', 'slugText', 'hint', 'address'];
  static values = { url: String, open: Boolean };

  connect() {
    this.slugEdited = this.openValue;
    this.nameFromCompany = null;
    if (!this.openValue && this.hasSlugViewTarget) {
      this.slugTarget.hidden = true;
      this.slugViewTarget.hidden = false;
      this.slugTextTarget.textContent = this.slugTarget.value || '—';
    }
    if (this.slugTarget.value !== '') {
      this.schedule();
    }
  }

  disconnect() {
    clearTimeout(this.timer);
  }

  // The company chosen in the registry names the clinic, unless a name was typed.
  prefill(event) {
    const hit = event.detail || {};
    const current = this.nameTarget.value.trim();
    if (current !== '' && current !== this.nameFromCompany) {
      return;
    }
    this.nameFromCompany = this.readable(hit.name || '');
    this.nameTarget.value = this.nameFromCompany;
    this.nameTarget.dispatchEvent(new Event('input', { bubbles: true }));
  }

  suggest() {
    if (this.slugEdited) {
      return;
    }
    this.slugTarget.value = this.nameTarget.value
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/\b(clinique|cabinet|centre|veterinaire|veterinaires|de|du|des|la|le|les|l|d)\b/g, ' ')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
    this.slugTarget.dispatchEvent(new Event('change', { bubbles: true }));
    if (this.hasSlugTextTarget) {
      this.slugTextTarget.textContent = this.slugTarget.value || '—';
    }
    this.schedule();
  }

  edit() {
    this.slugEdited = true;
    this.slugViewTarget.hidden = true;
    this.slugTarget.hidden = false;
    this.slugTarget.focus();
    this.slugTarget.select();
  }

  check() {
    this.slugEdited = true;
    this.schedule();
  }

  schedule() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.ask(), 250);
  }

  async ask() {
    const slug = this.slugTarget.value.trim();
    if (slug === '') {
      this.show('Fabriqué à partir du nom affiché', '');
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
    if (kind === 'is-error' && this.hasSlugViewTarget && this.slugTarget.hidden) {
      this.edit();
    }
  }

  show(text, kind) {
    this.hintTarget.textContent = text;
    this.hintTarget.className = `co-hint ${kind}`.trim();
  }

  address(event) {
    this.addressTarget.hidden = event.currentTarget.checked;
  }

  // « CLINIQUE VETERINAIRE DU VIEUX PORT (CVVP) SELARL » → « Clinique vétérinaire du Vieux Port ».
  readable(raw) {
    let name = raw.replace(/\([^)]*\)/g, ' ').replace(LEGAL_FORMS, ' ').replace(/\s+/g, ' ').trim();
    if (name === '' || name !== name.toUpperCase()) {
      return name;
    }
    let first = true;
    name = name.toLowerCase().replace(/[\p{L}\p{N}]+/gu, (word) => {
      const accented = ACCENTED[word] || word;
      const small = !first && SMALL_WORDS.has(accented);
      first = false;
      return small ? accented : accented.charAt(0).toUpperCase() + accented.slice(1);
    });
    // « de l Étang » (the registry has no apostrophes) reads « de l'Étang ».
    name = name.replace(/(^|\s)([ld]) (?=\p{L})/gu, "$1$2'");
    // « Clinique Vétérinaire » reads « Clinique vétérinaire ».
    return name.replace(/^(Clinique|Cabinet|Centre|Hôpital) (Vétérinaires?)(?=\s|$)/u, (all, head, vet) => `${head} ${vet.toLowerCase()}`);
  }
}
