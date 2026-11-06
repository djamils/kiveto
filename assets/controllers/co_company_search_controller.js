import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — B3 « Rechercher par SIRET, SIREN ou raison sociale »:
 * asks the company registry as one types, fills the hidden fields of the
 * chosen company and checks its VAT number with VIES.
 */
export default class extends Controller {
  static targets = ['query', 'hits', 'status', 'card', 'check', 'siren', 'siret', 'name', 'naf', 'street', 'postalcode', 'city', 'vat', 'vatcheck', 'showName', 'showSiret', 'showVat', 'showAddress'];
  static values = { url: String };

  connect() {
    this.found = [];
  }

  disconnect() {
    clearTimeout(this.timer);
  }

  search() {
    clearTimeout(this.timer);
    const query = this.queryTarget.value.trim();
    if (query.length < 3) {
      this.hitsTarget.hidden = true;
      return;
    }
    this.timer = setTimeout(() => this.fetch(query), 300);
  }

  async fetch(query) {
    const response = await fetch(`${this.urlValue}?what=company&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      return;
    }
    const data = await response.json();
    this.found = data.hits || [];
    this.statusTarget.hidden = data.available !== false;
    this.statusTarget.textContent = 'Le registre des entreprises ne répond pas. Réessaie dans un instant.';
    this.hitsTarget.replaceChildren(...this.found.map((hit, index) => this.hitItem(hit, index)));
    this.hitsTarget.hidden = this.found.length === 0;
  }

  hitItem(hit, index) {
    const item = document.createElement('li');
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.index = String(index);
    button.dataset.action = 'co-company-search#choose';
    button.className = hit.active ? '' : 'is-closed';
    const name = document.createElement('b');
    name.textContent = hit.name;
    const meta = document.createElement('span');
    meta.textContent = [hit.city, hit.naf ? `NAF ${hit.naf}` : null, hit.active ? 'active' : 'fermée'].filter(Boolean).join(' · ');
    const siret = document.createElement('code');
    siret.textContent = hit.siret ? `SIRET ${hit.siret}` : `SIREN ${hit.siren}`;
    button.append(name, meta, siret);
    item.append(button);
    return item;
  }

  async choose(event) {
    const hit = this.found[Number(event.currentTarget.dataset.index)];
    if (!hit) {
      return;
    }
    this.sirenTarget.value = hit.siren || '';
    this.siretTarget.value = hit.siret || '';
    this.nameTarget.value = hit.name || '';
    this.nafTarget.value = hit.naf || '';
    this.streetTarget.value = hit.street || '';
    this.postalcodeTarget.value = hit.postalCode || '';
    this.cityTarget.value = hit.city || '';
    this.vatTarget.value = hit.vat || '';
    this.vatcheckTarget.value = '';
    this.showNameTarget.textContent = hit.name || '';
    this.showSiretTarget.textContent = hit.siret || '—';
    this.showVatTarget.textContent = hit.vat || '—';
    this.showAddressTarget.textContent = [hit.street, [hit.postalCode, hit.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    this.checkTarget.querySelector('span').textContent = 'Entreprise vérifiée au registre · vérification de la TVA…';
    this.cardTarget.hidden = false;
    this.hitsTarget.hidden = true;
    this.queryTarget.value = hit.name || '';
    this.dispatch('chosen', { detail: hit });

    if (hit.vat) {
      const response = await fetch(`${this.urlValue}?what=vat&q=${encodeURIComponent(hit.vat)}`, { headers: { Accept: 'application/json' } });
      const data = response.ok ? await response.json() : { check: 'unavailable' };
      this.vatcheckTarget.value = data.check;
      const labels = { valid: ' · TVA intracommunautaire valide (VIES)', invalid: ' · TVA non reconnue par VIES', unavailable: ' · VIES indisponible, TVA non vérifiée' };
      this.checkTarget.querySelector('span').textContent = `Entreprise vérifiée au registre${labels[data.check] || ''}`;
    }
  }
}
