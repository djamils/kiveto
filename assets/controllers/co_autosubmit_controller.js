import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — submits its form when one of its filters changes
 * (the selects of a list's filter bar).
 */
export default class extends Controller {
  submit() {
    this.element.requestSubmit();
  }
}
