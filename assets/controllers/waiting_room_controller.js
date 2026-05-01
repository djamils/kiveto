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

  toggleCollapse(event) {
    event.currentTarget.closest('.scard')?.classList.toggle('is-collapsed');
  }

  // ── Walk-in modal ─ fidèle à openCheckinModal + ciRender du layout ──

  openCheckinWithPatient(event) {
    // Check-in depuis un RDV planifié : toggle CACHÉ, patient pré-sélectionné
    const d = event.currentTarget.dataset;
    this._ciOpenModal(false);
    if (d.animalName) {
      // Store appointment ID in modal hidden field
      const apptIdEl = document.getElementById('ci-appointment-id');
      if (apptIdEl) apptIdEl.value = d.apptId || '';

      this._ciSelectPatient({
        animalId:  d.animalId  || '',
        name:      d.animalName || '',
        species:   d.animalSpecies || '🐾',
        subtitle:  d.animalSubtitle || '',
        owner:     d.ownerLabel || '',
        phone:     d.ownerPhone || '',
        vet:       d.vet || '',
        apptTime:  d.apptTime || '',
        apptTs:    d.apptTs || '',
        motif:     d.motif || '',
        isRdv:     true,
      });
    }
  }

  _ciOpenModal(allowToggle) {
    // Reset complet (équivalent openCheckinModal du layout)
    this._ciClearPatient();
    document.getElementById('ci-note').value = '';
    ['ci-new-animal','ci-new-race','ci-new-proprio','ci-new-tel'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    const espece = document.getElementById('ci-new-espece');
    if (espece) espece.selectedIndex = 0;

    // Toggle row : visible si walk-in, caché si RDV pré-sélectionné
    document.getElementById('ci-toggle-row').style.display   = allowToggle ? 'block' : 'none';
    document.getElementById('ci-search-zone').style.display  = '';
    document.getElementById('ci-create-form').style.display  = 'none';
    document.getElementById('ci-toggle-existing')?.classList.add('active');
    document.getElementById('ci-toggle-new')?.classList.remove('active');

    this._ciUpdateTitle(false, allowToggle);

    document.getElementById('modal-checkin')?.classList.remove('hidden');
    if (allowToggle) {
      setTimeout(() => document.getElementById('ci-search')?.focus(), 50);
    }
  }

  _ciUpdateTitle(hasPatient, isRdv) {
    const title = document.getElementById('ci-modal-title');
    const noteLbl = document.getElementById('ci-note-label');
    const note = document.getElementById('ci-note');
    const lbl = document.getElementById('ci-validate-label');

    if (isRdv && hasPatient) {
      if (title) title.textContent = 'Check-in patient';
      if (noteLbl) noteLbl.textContent = "Note d'arrivée (optionnelle)";
      if (note) note.placeholder = 'Ex : animal stressé, retard dû aux transports…';
      if (lbl) lbl.textContent = "Valider l'arrivée";
    } else {
      if (title) title.textContent = 'Enregistrer un walk-in';
      if (noteLbl) noteLbl.innerHTML = 'Motif de visite <span class="ci-required">*</span>';
      if (note) note.placeholder = 'Décrivez la raison de la visite…';
      if (lbl) lbl.textContent = "Valider l'arrivée";
    }
  }

  _ciFormatPhone(raw) {
    if (!raw) return '—';
    const digits = raw.replace(/[^\d+]/g, '');
    if (digits.startsWith('+33') && digits.length === 12) {
      const local = '0' + digits.slice(3);
      return local.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
    }
    if (/^\d{10}$/.test(digits)) {
      return digits.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
    }
    return raw;
  }

  _ciRdvBadgeClass(motif) {
    const m = (motif || '').toLowerCase();
    if (/(chirurgi|stérilis|castrat|opérat|bloc)/.test(m))           return 'surgery';
    if (/(vaccin|primo|rappel)/.test(m))                              return 'vaccine';
    if (/(urgence|vital|hémorrag|convuls)/.test(m))                   return 'urgent';
    if (/(consultation|suivi|post.op|bilan|contrôle|détartrage)/.test(m)) return 'consult';
    return 'checkup';
  }

  _ciSelectPatient(p) {
    // p = { animalId, name, species, subtitle, owner, phone, vet, apptTime, motif, isRdv }
    const { animalId, name, species, subtitle, owner, phone, vet, apptTime, apptTs, motif, isRdv } = p;

    // Hidden fields
    const idEl = document.getElementById('ci-animal-id');   if (idEl) idEl.value = animalId;
    const nmEl = document.getElementById('ci-animal-name'); if (nmEl) nmEl.value = name;
    const parts = [name];
    if (subtitle) parts.push(`(${subtitle})`);
    if (owner)    parts.push(`— ${owner}`);
    const dcEl = document.getElementById('ci-description'); if (dcEl) dcEl.value = parts.join(' ');

    // Chip (species icon + name · owner)
    const chipSlot = document.getElementById('ci-tag-chip-slot');
    const search   = document.getElementById('ci-search');
    if (chipSlot) {
      chipSlot.innerHTML = `<span class="ci-tag-chip">
        <span class="ci-tag-chip-icon">${species || '🐾'}</span>
        ${name}${owner ? ' · ' + owner : ''}
      </span>`;
    }
    if (search) { search.placeholder = ''; search.style.minWidth = '60px'; search.readOnly = true; }
    const tagInput = document.getElementById('ci-tag-input');
    if (tagInput) tagInput.style.cursor = 'default';

    // Patient card — fidèle à ciRenderPatientCard du layout
    const badgeClass = isRdv ? this._ciRdvBadgeClass(motif) : 'checkup';
    const badgeLabel = isRdv && motif ? motif : 'Walk-in';
    const badge = `<span class="rdv-badge ${badgeClass}">${badgeLabel}</span>`;

    let lateStr = '';
    if (isRdv && apptTs) {
      const nowTs = Math.floor(Date.now() / 1000);
      const late  = Math.round((nowTs - parseInt(apptTs, 10)) / 60);
      if (late > 0) {
        lateStr = late >= 60
          ? ` · +${Math.floor(late / 60)}h${late % 60 > 0 ? (late % 60) : ''}`
          : ` · +${late} min`;
      }
    }

    const extraRows = isRdv ? `
      <div class="ci-pc-row">
        <span class="ci-pc-key">RDV prévu</span>
        <span class="ci-pc-val">${apptTime}${lateStr ? `<span style="color:var(--color-danger-text);font-weight:var(--weight-medium);">${lateStr}</span>` : ''}</span>
      </div>
      <div class="ci-pc-row">
        <span class="ci-pc-key">Vétérinaire</span>
        <span class="ci-pc-val">${vet || '—'}</span>
      </div>` : `
      <div class="ci-pc-row">
        <span class="ci-pc-key">Dernière visite</span>
        <span class="ci-pc-val">—</span>
      </div>
      <div class="ci-pc-row">
        <span class="ci-pc-key">Vétérinaire référent</span>
        <span class="ci-pc-val">à attribuer</span>
      </div>`;

    const zone = document.getElementById('ci-patient-zone');
    if (zone) {
      zone.innerHTML = `
        <div class="ci-patient-card">
          <div class="ci-pc-head">
            <div class="ci-pc-avatar">${species || '🐾'}</div>
            <div style="flex:1;min-width:0;">
              <div class="ci-pc-name">${name}</div>
              ${subtitle ? `<div class="ci-pc-sub">${subtitle}</div>` : ''}
            </div>
            ${badge}
          </div>
          <div class="ci-pc-grid">
            <div class="ci-pc-row">
              <span class="ci-pc-key">Propriétaire</span>
              <span class="ci-pc-val">${owner || '—'}</span>
            </div>
            <div class="ci-pc-row">
              <span class="ci-pc-key">Téléphone</span>
              <span class="ci-pc-val tel">${this._ciFormatPhone(phone)}</span>
            </div>
            ${extraRows}
          </div>
        </div>`;
    }

    this._ciUpdateTitle(true, isRdv);
    const btn = document.getElementById('ci-validate');
    if (btn) btn.disabled = false;
    if (window.lucide) window.lucide.createIcons();
  }

  ciSetMode(event) {
    const m = event.params.ciMode;
    document.getElementById('ci-toggle-existing')?.classList.toggle('active', m === 'existing');
    document.getElementById('ci-toggle-new')?.classList.toggle('active', m === 'new');
    document.getElementById('ci-search-zone').style.display = m === 'existing' ? '' : 'none';
    document.getElementById('ci-create-form').style.display = m === 'new' ? '' : 'none';
    this._ciUpdateTitle(false, false);
    if (m === 'new') {
      setTimeout(() => document.getElementById('ci-new-animal')?.focus(), 30);
      this.ciNewValidate();
    } else {
      setTimeout(() => document.getElementById('ci-search')?.focus(), 30);
    }
  }

  ciShowToggle() {
    // Triggered from "créer nouveau client" in dropdown footer
    document.getElementById('ci-toggle-row').style.display = 'block';
    this.ciSetMode({ params: { ciMode: 'new' } });
  }

  ciFocusSearch() {
    document.getElementById('ci-search')?.focus();
  }

  ciOnFocus() {
    document.getElementById('ci-tag-input')?.classList.add('focused');
  }

  ciOnBlur() {
    setTimeout(() => document.getElementById('ci-tag-input')?.classList.remove('focused'), 150);
  }

  ciNewValidate() {
    const animal  = (document.getElementById('ci-new-animal')?.value ?? '').trim();
    const proprio = (document.getElementById('ci-new-proprio')?.value ?? '').trim();
    const tel     = (document.getElementById('ci-new-tel')?.value ?? '').trim();
    const btn     = document.getElementById('ci-validate');
    if (btn) btn.disabled = !(animal && proprio && tel);
  }

  ciPrepareNew(event) {
    const form = document.getElementById('ci-main-form');
    const apptId = document.getElementById('ci-appointment-id')?.value || '';
    if (form) {
      form.action = apptId
        ? (form.dataset.checkinUrl || form.action)
        : (form.dataset.walkinUrl  || form.action);
    }

    const isNewMode = document.getElementById('ci-create-form')?.style.display !== 'none';
    if (!isNewMode) return;
    const animal  = (document.getElementById('ci-new-animal')?.value ?? '').trim();
    const espece  = document.getElementById('ci-new-espece')?.value ?? '';
    const race    = (document.getElementById('ci-new-race')?.value ?? '').trim();
    const proprio = (document.getElementById('ci-new-proprio')?.value ?? '').trim();
    const parts   = [animal];
    if (espece) parts.push(`(${espece}${race ? ' · ' + race : ''})`);
    if (proprio) parts.push(`— ${proprio}`);
    const descEl = document.getElementById('ci-description');
    if (descEl) descEl.value = parts.join(' ');
  }

  ciClearChip(event) {
    event.stopPropagation();
    this._ciClearPatient();
    setTimeout(() => document.getElementById('ci-search')?.focus(), 30);
  }

  _ciClearPatient() {
    document.getElementById('ci-patient-zone').innerHTML = '';
    const idEl   = document.getElementById('ci-animal-id');       if (idEl)   idEl.value   = '';
    const nmEl   = document.getElementById('ci-animal-name');     if (nmEl)   nmEl.value   = '';
    const dcEl   = document.getElementById('ci-description');     if (dcEl)   dcEl.value   = '';
    const apptEl = document.getElementById('ci-appointment-id'); if (apptEl) apptEl.value = '';
    const slot = document.getElementById('ci-tag-chip-slot'); if (slot)  slot.innerHTML = '';
    const srch = document.getElementById('ci-search');
    if (srch) { srch.placeholder = 'Nom animal, propriétaire, puce, téléphone…'; srch.style.minWidth = '100%'; srch.readOnly = false; }
    const tagInput = document.getElementById('ci-tag-input');
    if (tagInput) tagInput.style.cursor = '';
    const btn = document.getElementById('ci-validate');
    if (btn) btn.disabled = true;
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
    if (modalId === 'modal-checkin') {
      // Walk-in depuis topbar ou FAB : toggle VISIBLE (ciAllowToggle = true)
      this._ciOpenModal(true);
      return; // _ciOpenModal ouvre déjà la modal
    }
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
      // Clear dropdown
      const dropdown = document.getElementById('ci-dropdown-menu');
      if (dropdown) dropdown.innerHTML = '';

      this._ciSelectPatient({
        animalId: hit.resourceId,
        name:     hit.title,
        species:  '🐾',
        subtitle: hit.subtitle || '',
        owner:    (hit.context && hit.context !== 'Sans propriétaire') ? hit.context : '',
        phone:    '',
        vet:      '',
        apptTime: '',
        motif:    '',
        isRdv:    false,
      });
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
