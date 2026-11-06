import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — « Copier »: puts the value of its source in the
 * clipboard (selected, for a manual copy, where the clipboard is refused).
 */
export default class extends Controller {
  static targets = ['source', 'button'];

  async copy() {
    this.sourceTarget.select();
    try {
      await navigator.clipboard.writeText(this.sourceTarget.value);
      this.buttonTarget.textContent = 'Copié';
    } catch {
      this.buttonTarget.textContent = 'Sélectionné';
    }
    clearTimeout(this.timer);
    this.timer = setTimeout(() => { this.buttonTarget.textContent = 'Copier'; }, 2000);
  }

  disconnect() {
    clearTimeout(this.timer);
  }
}
