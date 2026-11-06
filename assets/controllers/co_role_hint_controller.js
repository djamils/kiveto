import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — in « Inviter un utilisateur », the billing note shows
 * only while the Veterinarian role is chosen.
 */
export default class extends Controller {
  static targets = ['hint'];

  connect() {
    this.update();
  }

  update() {
    const role = this.element.querySelector('input[name=role]:checked');
    this.hintTarget.hidden = !role || role.value !== 'VETERINARY';
  }
}
