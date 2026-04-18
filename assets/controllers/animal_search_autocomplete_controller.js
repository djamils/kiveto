import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/animal_search_autocomplete_controller.js
 * ─────────────────────────────────────────────────────────────────────
 * Accessible animal autocomplete combobox, filtered by owner.
 *
 * Targets:
 *   input         – text input the user types in
 *   dropdown      – <ul role="listbox"> that holds the result options
 *   liveRegion    – aria-live container that announces result counts
 *   hiddenInput   – hidden form input that receives the selected animal id
 *
 * Values:
 *   url        – API endpoint (default: /api/clinic/animals/search)
 *   ownerId    – current owner UUID (empty = disabled)
 *   minChars   – minimum chars before fetching (default: 2)
 *   debounce   – debounce window in ms (default: 300)
 */
export default class extends Controller {
  static targets = ['input', 'dropdown', 'liveRegion', 'hiddenInput'];
  static values  = {
    url:      { type: String, default: '/api/clinic/animals/search' },
    ownerId:  { type: String, default: '' },
    minChars: { type: Number, default: 2 },
    debounce: { type: Number, default: 300 },
  };

  _debounceTimer = null;
  _activeIndex   = -1;
  _items         = [];

  connect() {
    if (this.hasInputTarget) {
      this.inputTarget.setAttribute('role', 'combobox');
      this.inputTarget.setAttribute('aria-autocomplete', 'list');
      this.inputTarget.setAttribute('aria-expanded', 'false');
    }
    this._updateDisabledState();

    // Listen for owner changes
    this._ownerChangedHandler = (e) => {
      this.ownerIdValue = e.detail?.ownerId || '';
      this._reset();
      this._updateDisabledState();
    };
    document.addEventListener('owner:changed', this._ownerChangedHandler);
  }

  disconnect() {
    if (this._debounceTimer) clearTimeout(this._debounceTimer);
    if (this._ownerChangedHandler) {
      document.removeEventListener('owner:changed', this._ownerChangedHandler);
    }
  }

  _updateDisabledState() {
    if (!this.hasInputTarget) return;
    const disabled = !this.ownerIdValue;
    this.inputTarget.disabled = disabled;
    this.inputTarget.placeholder = disabled
      ? 'Sélectionnez d\'abord un propriétaire'
      : 'Rechercher un animal...';
  }

  _reset() {
    if (this.hasInputTarget) this.inputTarget.value = '';
    if (this.hasHiddenInputTarget) this.hiddenInputTarget.value = '';
    this._closeDropdown();
  }

  onInput(event) {
    const value = (event.target.value || '').trim();

    if (this._debounceTimer) clearTimeout(this._debounceTimer);

    if (!this.ownerIdValue || value.length < this.minCharsValue) {
      this._closeDropdown();
      return;
    }

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
    const params = new URLSearchParams({
      q: query,
      ownerId: this.ownerIdValue,
    });

    let response;
    try {
      response = await fetch(`${this.urlValue}?${params}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
    } catch (_e) {
      return;
    }

    if (response.status === 429 || response.status === 401 || !response.ok) return;

    let payload;
    try {
      payload = await response.json();
    } catch (_e) {
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
      this.dropdownTarget.innerHTML = '<li class="autocomplete-empty" role="option">Aucun résultat</li>';
      this._announce('Aucun résultat');
    } else {
      const html = items.map((item, index) => {
        const id    = `ac-animal-opt-${index}`;
        const label = item.name || '';
        const meta  = [item.species, item.breedName].filter(Boolean).join(' · ');
        return `
          <li id="${id}" role="option" aria-selected="false" data-index="${index}">
            <span class="autocomplete-option-label">${this._escape(label)}</span>
            ${meta ? `<span class="autocomplete-option-meta">${this._escape(meta)}</span>` : ''}
          </li>
        `;
      }).join('');
      this.dropdownTarget.innerHTML = html;

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
      const label = [item.name, item.species, item.breedName].filter(Boolean).join(' — ');
      this.inputTarget.value = label;
    }
    if (this.hasHiddenInputTarget) {
      this.hiddenInputTarget.value = item.id;
    }

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
