import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — asks before a form with a lasting effect is sent
 * (deleting a translation). The irreversible console actions use a dialog
 * of their own instead.
 */
export default class extends Controller {
  static values = { message: String };

  ask(event) {
    if (!window.confirm(this.messageValue)) {
      event.preventDefault();
    }
  }
}
