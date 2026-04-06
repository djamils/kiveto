import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/wizard_controller.js
 * ─────────────────────────────────────────────────
 * Multi-step wizard controller for forms split into panes.
 *
 * Targets:
 *   pane           — each step body (must have one per step)
 *   stepIndicator  — each visual step indicator (top stepper)
 *   prev / next    — navigation buttons
 *   finish         — final submit button (shown only on last step)
 *   currentLabel   — span showing current step number
 *   totalLabel     — span showing total step count
 *
 * Usage:
 *   <div data-controller="wizard">
 *     <div data-wizard-target="pane">…step 1…</div>
 *     <div data-wizard-target="pane" class="hidden">…step 2…</div>
 *     <button data-wizard-target="prev"   data-action="click->wizard#prev">…</button>
 *     <button data-wizard-target="next"   data-action="click->wizard#next">…</button>
 *     <button data-wizard-target="finish" data-action="click->wizard#finish" class="hidden">…</button>
 *   </div>
 */
export default class extends Controller {
  static targets = ['pane', 'stepIndicator', 'prev', 'next', 'finish', 'currentLabel', 'totalLabel'];

  connect() {
    this.current = 0;
    this._update();
    if (this.hasTotalLabelTarget) this.totalLabelTarget.textContent = this.paneTargets.length;
  }

  next() {
    if (this.current < this.paneTargets.length - 1) {
      this.current++;
      this._update();
    }
  }

  prev() {
    if (this.current > 0) {
      this.current--;
      this._update();
    }
  }

  finish() {
    // Default behavior: dispatch a custom event so pages can hook in
    this.dispatch('finish');
    // Optionally close the modal
    const overlay = this.element.closest('.modal-overlay');
    if (overlay && overlay.id && window.modal && typeof window.modal.close === 'function') {
      window.modal.close(overlay.id);
    } else if (overlay) {
      overlay.classList.add('hidden');
    }
  }

  _update() {
    // Show/hide panes
    this.paneTargets.forEach((p, i) => p.classList.toggle('hidden', i !== this.current));

    // Update step indicators
    this.stepIndicatorTargets.forEach((el, i) => {
      el.classList.toggle('is-active', i === this.current);
      el.classList.toggle('is-done',   i <  this.current);
    });

    // Buttons state
    if (this.hasPrevTarget) this.prevTarget.disabled = this.current === 0;
    const isLast = this.current === this.paneTargets.length - 1;
    if (this.hasNextTarget)   this.nextTarget.classList.toggle('hidden', isLast);
    if (this.hasFinishTarget) this.finishTarget.classList.toggle('hidden', !isLast);

    // Step label
    if (this.hasCurrentLabelTarget) this.currentLabelTarget.textContent = this.current + 1;
  }
}
