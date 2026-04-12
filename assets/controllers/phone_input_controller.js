import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/phone_input_controller.js
 * ──────────────────────────────────────────────────────
 * International phone input with country selector, mask formatting,
 * paste handling, and E.164 hidden value assembly.
 *
 * Targets:
 *   hidden         – hidden input holding E.164 value
 *   input          – visible tel input the user types into
 *   countryTrigger – button that opens country dropdown
 *   dropdown       – the dropdown panel
 *   options        – container for country option elements
 *   flagIcon       – flag <span> in the trigger
 *   dialText       – dial code text in the trigger
 */
export default class extends Controller {
  static targets = ['hidden', 'input', 'countryTrigger', 'dropdown', 'options', 'flagIcon', 'dialText'];
  static values  = { defaultCountry: { type: String, default: 'FR' } };

  connect() {
    this._country      = null;
    this._focusedIndex = -1;

    this._renderOptions();

    // Set initial country
    const initial = this._findCountry(this.defaultCountryValue) || COUNTRIES[0];
    this._selectCountry(initial, false);

    // Hydrate from hidden value if present
    if (this.hiddenTarget.value) {
      this._hydrateFromHidden();
    }

    // Close dropdown on outside click
    this._onOutsideClick = (e) => {
      if (!this.element.contains(e.target)) {
        this._closeDropdown();
      }
    };
    document.addEventListener('click', this._onOutsideClick);
  }

  disconnect() {
    document.removeEventListener('click', this._onOutsideClick);
  }

  // ── Actions ──

  toggleDropdown(e) {
    e.preventDefault();
    e.stopPropagation();
    const isOpen = !this.dropdownTarget.classList.contains('hidden');
    if (isOpen) {
      this._closeDropdown();
    } else {
      this._openDropdown();
    }
  }

  onInput() {
    const raw    = this.inputTarget.value;
    let digits = raw.replace(/\D/g, '');

    // Limit digits to the mask capacity
    if (digits.length > this._country.maxDigits) {
      digits = digits.slice(0, this._country.maxDigits);
    }

    const masked = this._applyMask(digits, this._country.mask);

    this.inputTarget.value  = masked;
    this.hiddenTarget.value = this._assembleE164(digits, this._country);
  }

  onKeydown(e) {
    // Dropdown keyboard navigation
    if (!this.dropdownTarget.classList.contains('hidden')) {
      const opts = this.optionsTarget.querySelectorAll('[data-country-code]');

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        this._focusedIndex = Math.min(this._focusedIndex + 1, opts.length - 1);
        this._highlightOption(opts);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this._focusedIndex = Math.max(this._focusedIndex - 1, 0);
        this._highlightOption(opts);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (this._focusedIndex >= 0 && opts[this._focusedIndex]) {
          const code = opts[this._focusedIndex].dataset.countryCode;
          const country = this._findCountry(code);
          if (country) this._selectCountry(country, true);
        }
        this._closeDropdown();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        this._closeDropdown();
      }
    }
  }

  onPaste(e) {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text');
    const cleaned = pasted.replace(/[\s.\-()/]/g, '');

    let e164;
    if (cleaned.startsWith('+')) {
      e164 = cleaned;
    } else if (cleaned.startsWith('00')) {
      e164 = '+' + cleaned.slice(2);
    } else {
      // Local format — keep current country
      let digits = cleaned.replace(/\D/g, '');
      if (digits.length > this._country.maxDigits) {
        digits = digits.slice(0, this._country.maxDigits);
      }
      const masked = this._applyMask(digits, this._country.mask);
      this.inputTarget.value  = masked;
      this.hiddenTarget.value = this._assembleE164(digits, this._country);
      return;
    }

    // Detect country from E.164
    const detected = this._detectCountry(e164);
    if (detected) {
      this._selectCountry(detected, false);
      let national = e164.slice(detected.dialCode.length).replace(/\D/g, '');
      // Re-add trunk prefix for display
      const display = detected.trunkPrefix ? detected.trunkPrefix + national : national;
      // Limit to mask capacity
      const limited = display.length > detected.maxDigits
        ? display.slice(0, detected.maxDigits)
        : display;
      const masked = this._applyMask(limited, detected.mask);
      this.inputTarget.value  = masked;
      // For hidden, strip trunk from limited digits
      const hiddenDigits = limited.replace(/\D/g, '');
      this.hiddenTarget.value = this._assembleE164(hiddenDigits, detected);
    } else {
      // Unknown — show raw
      this.inputTarget.value  = e164.replace(/\D/g, '');
      this.hiddenTarget.value = e164;
    }
  }

  // ── Private ──

  _renderOptions() {
    const html = COUNTRIES.map(c => {
      return `<button type="button" class="ki-phone-option" data-country-code="${c.code}" data-action="click->phone-input#_onOptionClick">
        <span class="fi fi-${c.code.toLowerCase()} ki-phone-flag"></span>
        <span class="ki-phone-option-dial">${c.dialCode}</span>
        <span>${c.name}</span>
      </button>`;
    }).join('');
    this.optionsTarget.innerHTML = html;
  }

  _onOptionClick(e) {
    const btn  = e.currentTarget;
    const code = btn.dataset.countryCode;
    const country = this._findCountry(code);
    if (country) {
      this._selectCountry(country, true);
    }
    this._closeDropdown();
  }

  _selectCountry(country, clearInput) {
    this._country = country;

    this.flagIconTarget.className   = `fi fi-${country.code.toLowerCase()} ki-phone-flag`;
    this.dialTextTarget.textContent = country.dialCode;

    if (clearInput) {
      this.inputTarget.value  = '';
      this.hiddenTarget.value = '';
    }

    this.inputTarget.placeholder = country.example || '';
    this.inputTarget.focus();
  }

  _hydrateFromHidden() {
    const val = this.hiddenTarget.value;
    if (!val || !val.startsWith('+')) return;

    const detected = this._detectCountry(val);
    if (detected) {
      this._selectCountry(detected, false);
      const national = val.slice(detected.dialCode.length);
      // Re-add trunk prefix for local display
      const display = detected.trunkPrefix ? detected.trunkPrefix + national : national;
      const masked  = this._applyMask(display, detected.mask);
      this.inputTarget.value     = masked;
      this.inputTarget.placeholder = detected.example || '';
    } else {
      // Cannot detect — show raw digits
      this.inputTarget.value = val;
    }
  }

  _detectCountry(e164) {
    for (const c of COUNTRIES) {
      if (e164.startsWith(c.dialCode)) return c;
    }
    return null;
  }

  _findCountry(code) {
    return COUNTRIES.find(c => c.code === code) || null;
  }

  _applyMask(digits, mask) {
    if (!mask) return digits;
    let result = '';
    let di = 0;
    for (let i = 0; i < mask.length && di < digits.length; i++) {
      if (mask[i] === '#') {
        result += digits[di++];
      } else {
        result += mask[i];
      }
    }
    return result;
  }

  _assembleE164(rawDigits, country) {
    if (!rawDigits) return '';
    let digits = rawDigits;
    if (country.trunkPrefix && digits.startsWith(country.trunkPrefix)) {
      digits = digits.slice(country.trunkPrefix.length);
    }
    return country.dialCode + digits;
  }

  _openDropdown() {
    const rect = this.element.getBoundingClientRect();
    const dd   = this.dropdownTarget;

    dd.style.left  = `${rect.left}px`;
    dd.style.width = `${rect.width}px`;

    // Show below the trigger; if it overflows the viewport, show above
    const spaceBelow = window.innerHeight - rect.bottom;
    const dropHeight = 260; // max-height 240 + padding + border
    if (spaceBelow < dropHeight && rect.top > dropHeight) {
      dd.style.top = `${rect.top - dropHeight}px`;
    } else {
      dd.style.top = `${rect.bottom + 4}px`;
    }

    dd.classList.remove('hidden');
    this._focusedIndex = -1;
  }

  _closeDropdown() {
    this.dropdownTarget.classList.add('hidden');
    this._focusedIndex = -1;
    // Remove highlights
    this.optionsTarget.querySelectorAll('.is-focused').forEach(el => el.classList.remove('is-focused'));
  }

  _highlightOption(opts) {
    opts.forEach((o, i) => {
      o.classList.toggle('is-focused', i === this._focusedIndex);
    });
    if (opts[this._focusedIndex]) {
      opts[this._focusedIndex].scrollIntoView({ block: 'nearest' });
    }
  }
}

// Country data sorted by dialCode.length descending for longest-match-first detection.
// maxDigits = number of # in the mask = max digits the user can type (includes trunk prefix).
const COUNTRIES = [
  { code: 'LU',  dialCode: '+352', trunkPrefix: '',  mask: '### ### ###',        maxDigits: 9,  example: '621 123 456',     name: 'Luxembourg' },
  { code: 'MA',  dialCode: '+212', trunkPrefix: '0', mask: '## ## ## ## ##',     maxDigits: 10, example: '06 12 34 56 78', name: 'Maroc' },
  { code: 'TN',  dialCode: '+216', trunkPrefix: '',  mask: '## ### ###',         maxDigits: 8,  example: '20 123 456',     name: 'Tunisie' },
  { code: 'DZ',  dialCode: '+213', trunkPrefix: '0', mask: '### ## ## ##',       maxDigits: 9,  example: '055 12 34 56',   name: 'Algérie' },
  { code: 'FR',  dialCode: '+33',  trunkPrefix: '0', mask: '## ## ## ## ##',     maxDigits: 10, example: '06 12 34 56 78', name: 'France' },
  { code: 'BE',  dialCode: '+32',  trunkPrefix: '0', mask: '### ## ## ##',       maxDigits: 10, example: '047 12 34 56',   name: 'Belgique' },
  { code: 'CH',  dialCode: '+41',  trunkPrefix: '0', mask: '## ### ## ##',       maxDigits: 10, example: '07 123 45 67',   name: 'Suisse' },
  { code: 'ES',  dialCode: '+34',  trunkPrefix: '',  mask: '### ## ## ##',       maxDigits: 9,  example: '612 34 56 78',   name: 'Espagne' },
  { code: 'IT',  dialCode: '+39',  trunkPrefix: '',  mask: '### ### ####',       maxDigits: 10, example: '312 345 6789',   name: 'Italie' },
  { code: 'DE',  dialCode: '+49',  trunkPrefix: '0', mask: '### #######',        maxDigits: 11, example: '015 1234567',    name: 'Allemagne' },
  { code: 'GB',  dialCode: '+44',  trunkPrefix: '0', mask: '#### ### ####',      maxDigits: 11, example: '07911 123 456',  name: 'Royaume-Uni' },
  { code: 'US',  dialCode: '+1',   trunkPrefix: '',  mask: '(###) ###-####',     maxDigits: 10, example: '(201) 555-0123', name: 'États-Unis' },
  { code: 'CA',  dialCode: '+1',   trunkPrefix: '',  mask: '(###) ###-####',     maxDigits: 10, example: '(514) 555-0123', name: 'Canada' },
];
