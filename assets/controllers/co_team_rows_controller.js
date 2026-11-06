import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — B4 « Équipe à inviter »: adds and removes people; the
 * first veterinarian of the list carries the « Admin » badge.
 */
export default class extends Controller {
  static targets = ['body', 'template', 'row', 'admin', 'noAdmin'];

  connect() {
    this.next = this.rowTargets.length;
    this.refresh();
  }

  add() {
    const html = this.templateTarget.innerHTML.replaceAll('__INDEX__', String(this.next++));
    this.bodyTarget.insertAdjacentHTML('beforeend', html);
    this.rowTargets[this.rowTargets.length - 1].querySelector('input')?.focus();
    this.refresh();
  }

  remove(event) {
    const row = event.currentTarget.closest('tr');
    if (this.rowTargets.length > 1) {
      row.remove();
    } else {
      row.querySelectorAll('input').forEach((input) => { input.value = ''; });
    }
    this.refresh();
  }

  refresh() {
    let adminGiven = false;
    this.rowTargets.forEach((row) => {
      const isVet = row.querySelector('select')?.value === 'VETERINARY';
      const isAdmin = isVet && !adminGiven;
      adminGiven = adminGiven || isAdmin;
      row.querySelector('[data-co-team-rows-target="admin"]').hidden = !isAdmin;
      row.querySelector('[data-co-team-rows-target="noAdmin"]').hidden = isAdmin;
    });
  }
}
