import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/wizard_choice_controller.js
 * ────────────────────────────────────────────────────────
 * Toggle between mutually exclusive choice panels inside a wizard step.
 *
 * Usage:
 *   <div data-controller="wizard-choice">
 *     <button data-action="click->wizard-choice#pick" data-value="existing" class="is-active">…</button>
 *     <button data-action="click->wizard-choice#pick" data-value="new">…</button>
 *
 *     <div data-wizard-choice-target="panel" data-panel="existing">…</div>
 *     <div data-wizard-choice-target="panel" data-panel="new" class="hidden">…</div>
 *   </div>
 */
export default class extends Controller {
  static targets = ['panel'];

  pick(event) {
    const btn = event.currentTarget;
    const value = btn.dataset.value;

    // Toggle active state on all sibling choice buttons
    this.element.querySelectorAll('[data-action*="wizard-choice#pick"]').forEach(b => {
      b.classList.toggle('is-active', b === btn);
    });

    // Show only the matching panel
    this.panelTargets.forEach(p => {
      p.classList.toggle('hidden', p.dataset.panel !== value);
    });
  }
}
