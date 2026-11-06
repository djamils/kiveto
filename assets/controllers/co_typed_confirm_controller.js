import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — an irreversible-looking action is enabled only once the
 * expected word (the clinic's tenant id) is typed.
 */
export default class extends Controller {
  static targets = ['input', 'submit'];
  static values = { expected: String };

  connect() {
    this.check();
  }

  check() {
    this.submitTarget.disabled = this.inputTarget.value.trim() !== this.expectedValue;
  }
}
