import { Controller } from '@hotwired/stimulus';

/**
 * Same palette and same derivation as the directory's client rows, so one
 * client keeps one colour wherever their avatar appears.
 */
const AVATAR_COLORS = [
  '#4338ca', '#0891b2', '#ea580c', '#16a34a', '#dc2626',
  '#7c3aed', '#0284c7', '#c2410c', '#059669', '#b91c1c',
];

function avatarColor(name) {
  let sum = 0;
  for (const char of String(name)) sum += char.charCodeAt(0);

  return AVATAR_COLORS[sum % AVATAR_COLORS.length];
}

/**
 * Kiveto — assets/controllers/client_search_autocomplete_controller.js
 * ─────────────────────────────────────────────────────────────────────
 * Accessible client autocomplete combobox.
 *
 * Targets:
 *   input         – text input the user types in
 *   dropdown      – <ul role="listbox"> that holds the result options
 *   liveRegion    – aria-live container that announces result counts
 *   hiddenInput   – hidden form input that receives the selected client id
 *
 * Values:
 *   url         – API endpoint (default: /api/clinic/clients/search)
 *   minChars    – minimum chars before fetching (default: 2, 0 opens on focus)
 *   debounce    – debounce window in ms (default: 300)
 *   createLabel – when set, appends a row offering to create a client; picking
 *                 it fires a `client-search-autocomplete:create` event instead
 *                 of selecting anything
 */
export default class extends Controller {
  static targets = ['input', 'dropdown', 'liveRegion', 'hiddenInput'];
  static values  = {
    url:         { type: String, default: '/api/clinic/clients/search' },
    minChars:    { type: Number, default: 2 },
    debounce:    { type: Number, default: 300 },
    createLabel: { type: String, default: '' },
  };

  _debounceTimer   = null;
  _activeIndex     = -1;
  _items           = [];
  _onDocumentClick = null;

  connect() {
    if (this.hasInputTarget) {
      this.inputTarget.setAttribute('role', 'combobox');
      this.inputTarget.setAttribute('aria-autocomplete', 'list');
      this.inputTarget.setAttribute('aria-expanded', 'false');
    }

    // Clicking anywhere else dismisses the list, selection or not.
    this._onDocumentClick = (event) => {
      if (!this.element.contains(event.target)) this._closeDropdown();
    };
    document.addEventListener('click', this._onDocumentClick);
  }

  disconnect() {
    document.removeEventListener('click', this._onDocumentClick);

    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
    }
  }

  /** Opens the list straight away when no minimum is required. */
  onFocus() {
    if (this.minCharsValue > 0) return;

    this._fetch((this.inputTarget.value || '').trim());
  }

  onInput(event) {
    const value = (event.target.value || '').trim();

    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
    }

    if (value.length < this.minCharsValue) {
      this._closeDropdown();
      return;
    }

    // A fresh keystroke invalidates whatever was picked before.
    const ownerInput = document.getElementById('animal_form_primaryOwnerClientId');
    if (ownerInput) ownerInput.value = '';

    this._debounceTimer = setTimeout(() => {
      this._fetch(value);
    }, this.debounceValue);
  }

  onKeydown(event) {
    if (this._items.length === 0) return;

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        this._move(1);
        break;
      case 'ArrowUp':
        event.preventDefault();
        this._move(-1);
        break;
      case 'Enter':
        if (this._activeIndex >= 0) {
          event.preventDefault();
          this._selectIndex(this._activeIndex);
        }
        break;
      case 'Escape':
        event.preventDefault();
        this._closeDropdown();
        if (this.hasHiddenInputTarget) this.hiddenInputTarget.value = '';
        break;
      case 'Tab':
        this._closeDropdown();
        break;
    }
  }

  async _fetch(query) {
    let response;
    try {
      response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
    } catch (e) {
      // Network error: keep dropdown silent
      return;
    }

    if (response.status === 429) {
      // Rate-limited: silent skip per spec.
      // eslint-disable-next-line no-console
      console.debug('Autocomplete rate limited');
      return;
    }

    if (response.status === 401) {
      this._renderAuthError();
      return;
    }

    if (!response.ok) {
      return;
    }

    let payload;
    try {
      payload = await response.json();
    } catch (e) {
      return;
    }

    const items = Array.isArray(payload?.data) ? payload.data : [];
    this._renderItems(items);
  }

  _renderItems(items) {
    this._items       = items;
    this._activeIndex = -1;

    if (!this.hasDropdownTarget) return;

    if (items.length === 0) {
      this.dropdownTarget.innerHTML = '<li class="autocomplete-empty">Aucun résultat</li>' + this._createRow();
      this._announce('Aucun résultat');
      this._wireCreateRow();
    } else {
      const html = items.map((item, index) => {
        const id    = `ac-opt-${index}`;
        const label = item.fullName || `${item.firstName || ''} ${item.lastName || ''}`.trim();
        const meta  = item.primaryEmail || item.primaryPhone || '';
        const initials = `${(item.firstName || label)[0] || ''}${(item.lastName || '')[0] || ''}`.toUpperCase();
        return `
          <li id="${id}" role="option" aria-selected="false" data-index="${index}">
            <span class="avatar-mini" style="background:${avatarColor(label)}">${this._escape(initials)}</span>
            <span class="autocomplete-option-label">${this._escape(label)}</span>
            ${meta ? `<span class="autocomplete-option-meta">${this._escape(meta)}</span>` : ''}
          </li>
        `;
      }).join('');
      this.dropdownTarget.innerHTML = html + this._createRow();
      this._wireCreateRow();

      this.dropdownTarget.querySelectorAll('li[role="option"]').forEach((li) => {
        li.addEventListener('mousedown', (e) => {
          e.preventDefault();
          this._selectIndex(parseInt(li.dataset.index, 10));
        });
      });

      this._announce(`${items.length} résultat${items.length > 1 ? 's' : ''}`);
    }

    this.dropdownTarget.classList.remove('hidden');
    this.inputTarget.setAttribute('aria-expanded', 'true');
  }

  /**
   * The "create a client" row of the layout, shown under the results and under
   * "Aucun résultat" alike.
   */
  _createRow() {
    if ('' === this.createLabelValue) return '';

    return `
      <li class="autocomplete-new" data-autocomplete-create>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        ${this._escape(this.createLabelValue)}
      </li>
    `;
  }

  _wireCreateRow() {
    const row = this.dropdownTarget.querySelector('[data-autocomplete-create]');
    if (!row) return;

    row.addEventListener('mousedown', (e) => {
      e.preventDefault();
      this._closeDropdown();
      this.dispatch('create', { detail: { query: (this.inputTarget.value || '').trim() } });
    });
  }

  _renderAuthError() {
    if (!this.hasDropdownTarget) return;
    this.dropdownTarget.innerHTML = '<li class="autocomplete-empty" role="option">Session expirée, rechargez la page</li>';
    this.dropdownTarget.classList.remove('hidden');
  }

  _move(delta) {
    const max = this._items.length - 1;
    let next  = this._activeIndex + delta;
    if (next < 0) next = max;
    if (next > max) next = 0;
    this._activeIndex = next;

    const options = this.dropdownTarget.querySelectorAll('li[role="option"]');
    options.forEach((opt, idx) => {
      const isActive = idx === next;
      opt.setAttribute('aria-selected', isActive ? 'true' : 'false');
      if (isActive) {
        this.inputTarget.setAttribute('aria-activedescendant', opt.id);
        opt.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  _selectIndex(index) {
    const item = this._items[index];
    if (!item) return;

    if (this.hasInputTarget) {
      this.inputTarget.value = item.fullName || `${item.firstName || ''} ${item.lastName || ''}`.trim();
    }
    if (this.hasHiddenInputTarget) {
      this.hiddenInputTarget.value = item.id;
    }

    // Also try to fill an external hidden input by id (animal form's primaryOwnerClientId).
    const ownerInput = document.getElementById('animal_form_primaryOwnerClientId');
    if (ownerInput) ownerInput.value = item.id;

    this._closeDropdown();
  }

  _closeDropdown() {
    this._items       = [];
    this._activeIndex = -1;
    if (this.hasDropdownTarget) {
      this.dropdownTarget.innerHTML = '';
      this.dropdownTarget.classList.add('hidden');
    }
    if (this.hasInputTarget) {
      this.inputTarget.setAttribute('aria-expanded', 'false');
      this.inputTarget.removeAttribute('aria-activedescendant');
    }
  }

  _announce(message) {
    if (this.hasLiveRegionTarget) {
      this.liveRegionTarget.textContent = message;
    }
  }

  _escape(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }
}
