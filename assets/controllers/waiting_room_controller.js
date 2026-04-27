import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['clockH', 'clockM', 'clockS', 'detailPanel'];

  connect() {
    this._updateClock();
    this._clockTimer = setInterval(() => this._updateClock(), 1000);

    this._keydownHandler = (e) => {
      if (e.key === 'Escape') {
        this.closeDetailSlide();
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
    const d = card.dataset;

    const name       = card.querySelector('.card-pet-name')?.textContent.trim() ?? 'Détail patient';
    const species    = d.species    || '';
    const motif      = d.motif      || '—';
    const channel    = d.channel    || '—';
    const openedAt   = d.openedAt   || '—';
    const triage     = d.triageLabel || (card.classList.contains('urgent') ? 'Urgence' : card.classList.contains('priority') ? 'Prioritaire' : 'Standard');
    const vet        = d.vet        || '— Non assigné';
    const ownerName  = d.ownerName  || '—';
    const ownerPhone = d.ownerPhone || '—';
    const notes      = d.notes      || '';
    const isUrgent   = card.classList.contains('urgent');

    const titleEl = document.getElementById('slide-title');
    titleEl.innerHTML = name
      + (species ? `<span style="display:block;font-size:var(--text-sm);font-weight:400;color:var(--text-subtle);margin-top:1px;">${species}</span>` : '');

    document.getElementById('slide-content').innerHTML = `
      <div class="pd-section">
        <div class="pd-kpis">
          <div class="pd-kpi"><div class="pd-kpi-label">Canal</div><div class="pd-kpi-val">${channel}</div></div>
          <div class="pd-kpi"><div class="pd-kpi-label">Triage</div><div class="pd-kpi-val">${triage}</div></div>
          <div class="pd-kpi"><div class="pd-kpi-label">Arrivée</div><div class="pd-kpi-val">${openedAt}</div></div>
        </div>
      </div>
      <div class="pd-section">
        <div class="pd-row"><span class="pd-label">Motif</span><span class="pd-val">${motif}</span></div>
        <div class="pd-row"><span class="pd-label">Vétérinaire</span><span class="pd-val">${vet}</span></div>
      </div>
      <div class="pd-section">
        <div class="pd-section-l">Propriétaire</div>
        <div class="pd-row"><span class="pd-label">Nom</span><span class="pd-val">${ownerName}</span></div>
        <div class="pd-row"><span class="pd-label">Tél.</span><span class="pd-val" style="color:var(--brand-600);">${ownerPhone}</span></div>
      </div>
      ${notes ? `
      <div class="pd-section">
        <div class="pd-section-l warn">Note ASV · Check-in</div>
        <div class="pd-reassign dashed">${notes}</div>
      </div>` : ''}
      <div class="pd-actions">
        ${isUrgent
          ? '<button class="btn btn-danger btn-full">Affecter un vétérinaire</button>'
          : '<button class="btn btn-primary btn-full">Démarrer la consultation</button>'}
        <button class="btn btn-secondary">Profil animal</button>
        <button class="btn btn-secondary">Profil client</button>
        <button class="btn btn-secondary btn-full">Placer en chirurgie</button>
      </div>`;

    document.getElementById('detail-slide')?.classList.add('open');
    document.getElementById('detail-backdrop')?.classList.add('open');
    if (window.lucide) window.lucide.createIcons();
  }

  closeDetailSlide() {
    document.getElementById('detail-slide')?.classList.remove('open');
    document.getElementById('detail-backdrop')?.classList.remove('open');
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
