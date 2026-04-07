import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/calendar_controller.js
 * ───────────────────────────────────────────────────
 * Reusable inline calendar component (month view).
 * Pure vanilla JS, fr-FR locale, weeks start Monday.
 *
 * Targets: title, dow, grid
 * Values:
 *   selected       (String) ISO yyyy-mm-dd of the selected day
 *   month          (String) ISO yyyy-mm-dd of any day inside the visible month
 *   weekHighlight  (Boolean) highlight the whole week of the selected day
 *   dowShort       (Boolean) single-letter day-of-week labels
 *
 * Emits: calendar:select on root element with { detail: { date } }
 */
export default class extends Controller {
  static targets = ['title', 'dow', 'grid'];
  static values  = {
    selected:      { type: String,  default: '' },
    month:         { type: String,  default: '' },
    weekHighlight: { type: Boolean, default: false },
    dowShort:      { type: Boolean, default: false },
  };

  connect() {
    // Default selected to today if missing
    if (!this.selectedValue) this.selectedValue = this._fmt(new Date());
    if (!this.monthValue)    this.monthValue    = this.selectedValue;
    this._renderDow();
    this.render();
  }

  // ── Public API ────────────────────────────────────
  /** Programmatically set the selected date (does not emit). */
  setSelected(iso, { focusMonth = true } = {}) {
    this.selectedValue = iso;
    if (focusMonth) this.monthValue = iso;
    this.render();
  }

  prevMonth() { this._shiftMonth(-1); }
  nextMonth() { this._shiftMonth(1); }

  // ── Rendering ─────────────────────────────────────
  render() {
    const cursor = this._parse(this.monthValue);
    const y = cursor.getFullYear();
    const m = cursor.getMonth();

    // Title
    const titleFmt = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
    if (this.hasTitleTarget) {
      const t = titleFmt.format(cursor);
      this.titleTarget.textContent = t.charAt(0).toUpperCase() + t.slice(1);
    }

    const grid = this.gridTarget;
    grid.innerHTML = '';

    // First Monday on or before the 1st
    const first = new Date(y, m, 1);
    const startDow = (first.getDay() + 6) % 7; // Mon=0
    const start = new Date(y, m, 1 - startDow);

    const today    = this._fmt(new Date());
    const selected = this.selectedValue;
    const selWeek  = this._weekKey(this._parse(selected));

    // Always render 6 rows × 7 columns = 42 cells
    for (let i = 0; i < 42; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      const iso = this._fmt(d);
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'ki-calendar-day';
      cell.textContent = d.getDate();

      if (d.getMonth() !== m)         cell.classList.add('is-outside-month');
      if (d.getDay() === 0 || d.getDay() === 6) cell.classList.add('is-weekend');
      if (iso === today)              cell.classList.add('is-today');
      if (iso === selected)           cell.classList.add('is-selected');
      if (this.weekHighlightValue && this._weekKey(d) === selWeek) {
        cell.classList.add('is-week-selected');
      }

      cell.dataset.date = iso;
      cell.addEventListener('click', () => this._onPick(iso));
      grid.appendChild(cell);
    }
  }

  // ── Internals ─────────────────────────────────────
  _renderDow() {
    if (!this.hasDowTarget) return;
    const labels = this.dowShortValue
      ? ['L','M','M','J','V','S','D']
      : ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
    this.dowTarget.innerHTML = '';
    labels.forEach((lbl, idx) => {
      const el = document.createElement('div');
      el.className = 'ki-calendar-dow-cell';
      if (idx >= 5) el.classList.add('is-weekend');
      el.textContent = lbl;
      this.dowTarget.appendChild(el);
    });
  }

  _shiftMonth(delta) {
    const cur = this._parse(this.monthValue);
    const next = new Date(cur.getFullYear(), cur.getMonth() + delta, 1);
    this.monthValue = this._fmt(next);
    this.render();
  }

  _onPick(iso) {
    this.selectedValue = iso;
    this.monthValue    = iso;
    this.render();
    this.element.dispatchEvent(new CustomEvent('calendar:select', {
      bubbles: true,
      detail: { date: iso },
    }));
  }

  _fmt(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  _parse(iso) {
    if (!iso) return new Date();
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  /** Week key = ISO date of Monday in the week containing d */
  _weekKey(d) {
    const r = new Date(d);
    const day = (r.getDay() + 6) % 7;
    r.setDate(r.getDate() - day);
    return this._fmt(r);
  }

  // Re-render whenever selected/month change externally
  selectedValueChanged() { if (this.hasGridTarget) this.render(); }
}
