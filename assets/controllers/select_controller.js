import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/select_controller.js
 * ──────────────────────────────────────────────────
 * Custom select dropdown with smooth animations, search, and keyboard navigation.
 *
 * Usage (Twig):
 *   {% include 'components/ui/select.html.twig' with {
 *     name: 'status',
 *     placeholder: 'Choisir un statut',
 *     options: [
 *       { value: 'active', label: 'Actif' },
 *       { value: 'inactive', label: 'Inactif' },
 *     ],
 *     value: 'active',
 *     searchable: true,
 *   } %}
 *
 * Targets:
 *   trigger    – the button that opens/closes the dropdown
 *   dropdown   – the dropdown panel
 *   search     – the search input (optional)
 *   option     – each selectable option
 *   label      – display text on the trigger button
 *   hidden     – the hidden input that holds the real form value
 *   clear      – the clear button (optional)
 */
export default class extends Controller {
  static targets = ['trigger', 'dropdown', 'search', 'option', 'label', 'hidden', 'clear'];
  static values  = {
    open:        { type: Boolean, default: false },
    placeholder: { type: String,  default: 'Sélectionner...' },
    direction:   { type: String,  default: 'auto' },
  };

  /** @type {number} Currently focused option index (-1 = none) */
  _focusedIndex = -1;

  /** @type {Function|null} Bound click-outside handler */
  _outsideHandler = null;

  /** @type {number|null} Pending outside-listener timer */
  _outsideTimer = null;

  connect() {
    this._outsideHandler = this._onClickOutside.bind(this);
  }

  disconnect() {
    this._removeOutsideListener();
  }

  // ── Actions ───────────────────────────────────

  toggle(event) {
    event?.preventDefault();
    this.openValue ? this._close() : this._open();
  }

  select(event) {
    const option = event.currentTarget;
    const value = option.dataset.value;
    const label = option.dataset.label || option.textContent.trim();

    this.hiddenTarget.value = value;
    this.labelTarget.textContent = label;
    this.labelTarget.classList.remove('is-placeholder');

    // Update selected state and check icon on all options
    this.optionTargets.forEach(opt => {
      const isSelected = opt.dataset.value === value;
      opt.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      const checkIcon = opt.querySelector('svg');
      if (checkIcon) {
        checkIcon.classList.toggle('hidden', !isSelected);
      }
    });

    // Show clear button if available
    if (this.hasClearTarget) {
      this.clearTarget.classList.remove('hidden');
    }

    this._close();

    // Dispatch change event on the hidden input
    this.hiddenTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  clear(event) {
    event?.preventDefault();
    event?.stopPropagation();

    this.hiddenTarget.value = '';
    this.labelTarget.textContent = this.placeholderValue;
    this.labelTarget.classList.add('is-placeholder');

    this.optionTargets.forEach(opt => {
      opt.setAttribute('aria-selected', 'false');
      const checkIcon = opt.querySelector('svg');
      if (checkIcon) checkIcon.classList.add('hidden');
    });

    if (this.hasClearTarget) {
      this.clearTarget.classList.add('hidden');
    }

    this.hiddenTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  filter() {
    if (!this.hasSearchTarget) return;

    const query = this.searchTarget.value.toLowerCase().trim();

    this.optionTargets.forEach(opt => {
      const label = (opt.dataset.label || opt.textContent).toLowerCase();
      opt.style.display = label.includes(query) ? '' : 'none';
    });

    this._focusedIndex = -1;
  }

  onKeydown(event) {
    if (!this.openValue) {
      if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
        event.preventDefault();
        this._open();
      }
      return;
    }

    const visibleOptions = this._visibleOptions();

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        if (visibleOptions.length === 0) return;
        this._focusedIndex = Math.min(this._focusedIndex + 1, visibleOptions.length - 1);
        this._highlightOption(visibleOptions);
        break;

      case 'ArrowUp':
        event.preventDefault();
        if (visibleOptions.length === 0) return;
        this._focusedIndex = Math.max(this._focusedIndex - 1, 0);
        this._highlightOption(visibleOptions);
        break;

      case 'Enter':
        event.preventDefault();
        if (this._focusedIndex >= 0 && visibleOptions[this._focusedIndex]) {
          visibleOptions[this._focusedIndex].click();
        }
        break;

      case 'Escape':
        event.preventDefault();
        this._close();
        this.triggerTarget.focus();
        break;

      case 'Tab':
        this._close();
        break;
    }
  }

  // ── Private ───────────────────────────────────

  _open() {
    this.openValue = true;
    this._focusedIndex = -1;

    // Show dropdown with enter animation
    this.dropdownTarget.classList.remove('hidden');
    this.dropdownTarget.classList.remove('animate-popover-out');
    this.dropdownTarget.classList.add('animate-popover-in');

    // Direction: forced "up" or auto-flip if no room below
    this.dropdownTarget.classList.remove('ki-select-dropdown--up');
    if (this.directionValue === 'up') {
      this.dropdownTarget.classList.add('ki-select-dropdown--up');
    } else {
      const trigRect = this.triggerTarget.getBoundingClientRect();
      const ddH = this.dropdownTarget.offsetHeight;
      const spaceBelow = window.innerHeight - trigRect.bottom;
      const spaceAbove = trigRect.top;
      if (spaceBelow < ddH + 8 && spaceAbove > spaceBelow) {
        this.dropdownTarget.classList.add('ki-select-dropdown--up');
      }
    }

    // Focus search input if available, otherwise focus dropdown
    requestAnimationFrame(() => {
      if (this.hasSearchTarget) {
        this.searchTarget.value = '';
        this.searchTarget.focus();
        this.filter();
      }
    });

    this.triggerTarget.setAttribute('aria-expanded', 'true');
    this._addOutsideListener();
  }

  _close() {
    if (!this.openValue) return;
    this.openValue = false;
    this.triggerTarget.setAttribute('aria-expanded', 'false');

    // Exit animation
    this.dropdownTarget.classList.remove('animate-popover-in');
    this.dropdownTarget.classList.add('animate-popover-out');

    const onEnd = () => {
      this.dropdownTarget.classList.add('hidden');
      this.dropdownTarget.classList.remove('animate-popover-out');
    };

    this.dropdownTarget.addEventListener('animationend', (e) => {
      if (e.target === this.dropdownTarget) onEnd();
    }, { once: true });
    setTimeout(() => {
      if (!this.dropdownTarget.classList.contains('hidden')) {
        onEnd();
      }
    }, 150);

    this._removeOutsideListener();
  }

  _visibleOptions() {
    return this.optionTargets.filter(opt => opt.style.display !== 'none');
  }

  _highlightOption(visibleOptions) {
    // Remove highlight from all
    this.optionTargets.forEach(opt => opt.classList.remove('is-focused'));

    if (this._focusedIndex >= 0 && visibleOptions[this._focusedIndex]) {
      const opt = visibleOptions[this._focusedIndex];
      opt.classList.add('is-focused');
      opt.scrollIntoView({ block: 'nearest' });
    }
  }

  _onClickOutside(event) {
    if (!this.element.contains(event.target)) {
      this._close();
    }
  }

  _addOutsideListener() {
    // Delay to prevent the opening click from immediately closing
    this._outsideTimer = setTimeout(() => {
      document.addEventListener('click', this._outsideHandler);
    }, 10);
  }

  _removeOutsideListener() {
    clearTimeout(this._outsideTimer);
    document.removeEventListener('click', this._outsideHandler);
  }
}
