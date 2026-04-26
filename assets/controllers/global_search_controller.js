import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/global_search_controller.js
 *
 * Supports two modes:
 *   navigate — Cmd+K global search, results are links
 *   pick     — picker modal, results dispatch vetsaas:search:pick CustomEvent
 *
 * Values:
 *   url      – search endpoint
 *   mode     – 'navigate' | 'pick'
 *   pickerId – unique ID scoping pick events to the right caller
 *   minChars – minimum chars before fetching (default: 2)
 *   debounce – debounce window in ms (default: 250)
 */
export default class extends Controller {
  static targets = ['input', 'results', 'liveRegion'];
  static values  = {
    url:      { type: String },
    mode:     { type: String, default: 'navigate' },
    pickerId: { type: String, default: '' },
    minChars: { type: Number, default: 2 },
    debounce: { type: Number, default: 250 },
  };

  _debounceTimer    = null;
  _abortController  = null;

  connect() {
    this._onKeydown = this._handleKeydown.bind(this);
    document.addEventListener('keydown', this._onKeydown);
  }

  disconnect() {
    document.removeEventListener('keydown', this._onKeydown);

    if (this._abortController) {
      this._abortController.abort();
    }

    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
    }
  }

  search() {
    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
    }

    this._debounceTimer = setTimeout(() => {
      this._fetch();
    }, this.debounceValue);
  }

  pick(event) {
    const button   = event.currentTarget;
    const rawHit   = button.dataset.hit;
    const pickerId = button.dataset.pickerId;

    let hit;

    try {
      hit = JSON.parse(rawHit);
    } catch {
      return;
    }

    this.element.dispatchEvent(new CustomEvent('vetsaas:search:pick', {
      detail:  { hit, pickerId },
      bubbles: true,
    }));
  }

  async _fetch() {
    if (!this.hasInputTarget) return;

    const q = this.inputTarget.value.trim();

    if (q.length < this.minCharsValue) {
      if (this.hasResultsTarget) {
        this.resultsTarget.innerHTML = '';
      }
      return;
    }

    if (this._abortController) {
      this._abortController.abort();
    }

    this._abortController = new AbortController();

    const params = new URLSearchParams({ q, mode: this.modeValue });

    if (this.pickerIdValue) {
      params.set('pickerId', this.pickerIdValue);
    }

    const url = `${this.urlValue}?${params.toString()}`;

    try {
      const response = await fetch(url, {
        headers:     { Accept: 'text/html' },
        credentials: 'same-origin',
        signal:      this._abortController.signal,
      });

      if (!response.ok) return;

      const html = await response.text();

      if (this.hasResultsTarget) {
        this.resultsTarget.innerHTML = html;
      }

      this._announceResults();
    } catch (error) {
      if (error.name !== 'AbortError') {
        // eslint-disable-next-line no-console
        console.debug('Search fetch failed', error);
      }
    }
  }

  _handleKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && 'k' === event.key) {
      event.preventDefault();
      // Use modal.js mechanism: dispatch synthetic click on the search trigger
      document.querySelector('[data-modal-open="search-modal"]')?.click();

      if (this.hasInputTarget) {
        this.inputTarget.focus();
      }
    }

    if ('Escape' === event.key) {
      // Delegate to modal.js close mechanism via trigger element
      document.querySelector('[data-modal-close="search-modal"]')?.click();
    }

    if ('ArrowDown' === event.key) {
      event.preventDefault();
      this._moveFocus(1);
    }

    if ('ArrowUp' === event.key) {
      event.preventDefault();
      this._moveFocus(-1);
    }
  }

  _moveFocus(delta) {
    if (!this.hasResultsTarget) return;

    const items   = [...this.resultsTarget.querySelectorAll('.search-result')];
    const focused = document.activeElement;
    const current = items.indexOf(focused);
    const next    = current + delta;

    if (next >= 0 && next < items.length) {
      items[next].focus();
    } else if (next < 0 && this.hasInputTarget) {
      this.inputTarget.focus();
    }
  }

  _announceResults() {
    if (!this.hasLiveRegionTarget || !this.hasResultsTarget) return;

    const count = this.resultsTarget.querySelectorAll('.search-result').length;
    this.liveRegionTarget.textContent = count > 0
      ? `${count} résultat${count > 1 ? 's' : ''}`
      : 'Aucun résultat';
  }
}
