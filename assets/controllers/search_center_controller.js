import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/search_center_controller.js
 * ────────────────────────────────────────────────────────
 * Filters the global search center results as the user types
 * and supports filter-by-type pills.
 *
 * Targets:
 *   input    — the big search input
 *   result   — each individual search result row
 *   section  — each section (Récents / Suggestions / Type column)
 *   pill     — filter pills (Tout / Clients / Animaux / etc.)
 *   empty    — empty state shown when no result matches
 *   count    — pill count badges (optional, by data-type)
 */
export default class extends Controller {
  static targets = ['input', 'result', 'section', 'pill', 'empty'];
  static values  = { activeType: { type: String, default: 'all' } };

  connect() {
    // Reset state when the modal opens (focus is restored each time)
    this._applyFilter();
  }

  // ── Actions ───────────────────────────────────────────────

  filter() {
    this._applyFilter();
  }

  selectType(event) {
    const pill = event.currentTarget;
    const type = pill.dataset.type || 'all';
    this.activeTypeValue = type;
    this.pillTargets.forEach(p => p.classList.toggle('is-active', p === pill));
    this._applyFilter();
  }

  // ── Private ───────────────────────────────────────────────

  _applyFilter() {
    const query = (this.hasInputTarget ? this.inputTarget.value : '').toLowerCase().trim();
    const type = this.activeTypeValue;
    let totalVisible = 0;

    this.resultTargets.forEach(el => {
      const text = (el.dataset.search || el.textContent).toLowerCase();
      const elType = el.dataset.type || '';
      const matchesQuery = !query || text.includes(query);
      const matchesType = type === 'all' || elType === type;
      const visible = matchesQuery && matchesType;
      el.style.display = visible ? '' : 'none';
      if (visible) totalVisible++;
    });

    // Hide sections that have no visible results
    this.sectionTargets.forEach(section => {
      const hasVisible = section.querySelectorAll('[data-search-center-target="result"]:not([style*="display: none"])').length > 0;
      section.style.display = hasVisible ? '' : 'none';
    });

    // Toggle empty state
    if (this.hasEmptyTarget) {
      this.emptyTarget.style.display = totalVisible === 0 ? '' : 'none';
    }
  }
}
