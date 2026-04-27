import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['clockH', 'clockM', 'clockS', 'detailPanel'];

  connect() {
    this._updateClock();
    this._clockTimer = setInterval(() => this._updateClock(), 1000);

    this._keydownHandler = (e) => {
      if (e.key === 'Escape') {
        this.closeDetailPanel();
        this.closeAllModals();
      }
    };
    document.addEventListener('keydown', this._keydownHandler);

    this._pickHandler = this._handleSearchPick.bind(this);
    document.addEventListener('vetsaas:search:pick', this._pickHandler);

    const detailFrame = document.getElementById('patient-detail');
    if (detailFrame) {
      this._frameLoadHandler = () => this.showDetailPanel();
      detailFrame.addEventListener('turbo:frame-load', this._frameLoadHandler);
    }
  }

  disconnect() {
    clearInterval(this._clockTimer);
    document.removeEventListener('keydown', this._keydownHandler);
    document.removeEventListener('vetsaas:search:pick', this._pickHandler);
    const detailFrame = document.getElementById('patient-detail');
    if (detailFrame && this._frameLoadHandler) {
      detailFrame.removeEventListener('turbo:frame-load', this._frameLoadHandler);
    }
  }

  switchTab(event) {
    const tab = event.params.tab;

    this.element.querySelectorAll('.wr-tab').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.waitingRoomTabParam === tab);
    });

    const liste  = document.getElementById('view-liste');
    const kanban = document.getElementById('view-kanban');

    if (liste)  liste.classList.toggle('hidden', tab !== 'liste');
    if (kanban) kanban.classList.toggle('hidden', tab !== 'kanban');
  }

  setTab(event) {
    const tab = event.params.tab;
    this.element.querySelectorAll('.tab-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    this.element.querySelectorAll('.tab-content').forEach((content) => {
      content.classList.toggle('active', content.id === `content-${tab}`);
    });
  }

  openDetailSlide(event) {
    const card = event.currentTarget;
    const nameEl = card.querySelector('.card-pet-name');
    const title = nameEl ? nameEl.textContent.trim() : 'Détail patient';
    const whenEl = card.querySelector('.card-when, .card-wait');
    const whenText = whenEl ? whenEl.textContent.trim() : '—';
    const triageClass = card.classList.contains('urgent') ? 'Urgence'
      : card.classList.contains('priority') ? 'Prioritaire'
      : 'Standard';

    const slideTitleEl = document.getElementById('slide-title');
    const slideContentEl = document.getElementById('slide-content');
    const slideEl = document.getElementById('detail-slide');

    if (slideTitleEl) slideTitleEl.textContent = title;
    if (slideContentEl) {
      slideContentEl.innerHTML = `
        <div style="padding:var(--space-4);">
          <div class="pd-section">
            <div class="pd-kpis">
              <div class="pd-kpi"><div class="pd-kpi-label">Canal</div><div class="pd-kpi-val">Walk-in</div></div>
              <div class="pd-kpi"><div class="pd-kpi-label">Triage</div><div class="pd-kpi-val">${triageClass}</div></div>
              <div class="pd-kpi"><div class="pd-kpi-label">Arrivée</div><div class="pd-kpi-val">${whenText}</div></div>
            </div>
          </div>
          <div class="pd-actions">
            <button class="btn btn-primary btn-full">Appeler en consult</button>
            <button class="btn btn-secondary">Ouvrir dossier</button>
          </div>
        </div>`;
    }
    if (slideEl) slideEl.classList.add('open');
    if (window.lucide) window.lucide.createIcons();
  }

  openModal(event) {
    const modalId = event.params.modalId;
    document.getElementById(modalId)?.classList.remove('hidden');
  }

  closeModal(event) {
    const modalId = event.params.modalId;
    document.getElementById(modalId)?.classList.add('hidden');
  }

  closeOnOverlayClick(event) {
    if (event.target === event.currentTarget) {
      event.currentTarget.classList.add('hidden');
    }
  }

  closeAllModals() {
    document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((m) => {
      m.classList.add('hidden');
    });
  }

  showDetailPanel() {
    this.detailPanelTarget.classList.remove('is-hidden');
  }

  closeDetailPanel() {
    if (this.hasDetailPanelTarget) {
      this.detailPanelTarget.classList.add('is-hidden');
    }
  }

  _handleSearchPick(event) {
    const { hit, pickerId } = event.detail;

    const parts = [hit.title];
    if (hit.subtitle) parts.push(`(${hit.subtitle})`);
    if (hit.context && hit.context !== 'Sans propriétaire') parts.push(`— ${hit.context}`);
    const description = parts.join(' ');

    const clearSearch = (modal) => {
      const resultsEl = modal.querySelector('[data-global-search-target="results"]');
      if (resultsEl) resultsEl.innerHTML = '';
      const searchInput = modal.querySelector('[data-global-search-target="input"]');
      if (searchInput) searchInput.value = '';
    };

    if ('walkin-checkin' === pickerId) {
      const modal = document.getElementById('modal-checkin');
      if (!modal) return;
      const descInput = modal.querySelector('input[name="foundAnimalDescription"]');
      if (descInput) descInput.value = description;
      const animalIdInput = document.getElementById('checkin-animal-id');
      if (animalIdInput) animalIdInput.value = hit.resourceId;
      const animalNameInput = document.getElementById('checkin-animal-name');
      if (animalNameInput) animalNameInput.value = hit.title;
      clearSearch(modal);
    } else if ('walkin-urgence' === pickerId) {
      const modal = document.getElementById('modal-urgence');
      if (!modal) return;
      clearSearch(modal);
      const aid2a = document.getElementById('urg-2a-animal-id');
      if (aid2a) aid2a.value = hit.resourceId;
      const aname2a = document.getElementById('urg-2a-animal-name');
      if (aname2a) aname2a.value = hit.title;
      const lbl2a = document.getElementById('urg-2a-label');
      if (lbl2a) lbl2a.value = description;
      if (typeof window.urgSetFromPick === 'function') {
        window.urgSetFromPick(hit.resourceId, description);
      }
    }
  }

  _updateClock() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    if (this.hasClockHTarget) this.clockHTarget.textContent = pad(now.getHours());
    if (this.hasClockMTarget) this.clockMTarget.textContent = pad(now.getMinutes());
    if (this.hasClockSTarget) this.clockSTarget.textContent = pad(now.getSeconds());
  }
}
