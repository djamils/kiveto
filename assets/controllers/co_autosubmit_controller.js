import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — submits its form when one of its fields changes: at
 * once for a select, a switch or a box (`submit`), once the typing pauses
 * for a search field (`submitLater`).
 */
export default class extends Controller {
  disconnect() {
    clearTimeout(this.timer);
  }

  submit() {
    clearTimeout(this.timer);
    this.element.requestSubmit();
  }

  submitLater() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.element.requestSubmit(), 400);
  }
}
