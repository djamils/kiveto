// assets/js/pages/consultation.js
// Consultation cockpit — full vanilla JS ported from
// docs/frontend-theme/vetsaas-layouts/vetos-consultation.html
//
// All state, modals, list rendering, and interactions are scoped
// inside init() so Stimulus / Turbo can mount + cleanup safely.

let _mounted = false;
// Teardown callbacks registered during init() (document-level listeners,
// intervals, observers). Flushed by cleanup() on turbo:before-cache.
const _cleanups = [];

export function init() {
  if (_mounted) return;
  _mounted = true;

  // ═══════════════════════════════════════════════
  //  SHARED PAGE INFRASTRUCTURE
  //  Visible from every IIFE below (closure over init scope).
  // ═══════════════════════════════════════════════

  // Server → JS hydration payload (see #consultation-data in index.html.twig)
  const HYDRATION = (() => {
    try {
      return JSON.parse(document.getElementById('consultation-data')?.textContent || '{}');
    } catch {
      return {};
    }
  })();

  function registerCleanup(fn) {
    _cleanups.push(fn);
  }

  // Document-level listeners MUST go through this helper so cleanup()
  // removes them (raw document.addEventListener leaks across Turbo visits).
  function onDocument(type, handler) {
    document.addEventListener(type, handler);
    registerCleanup(() => document.removeEventListener(type, handler));
  }

  // ── Toast system (shared by all blocks) ──
  const Toast = (() => {
    let container = null;
    function ensure() {
      if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
      }
      return container;
    }
    function show(message, type = 'success') {
      const c = ensure();
      const t = document.createElement('div');
      t.className = `toast toast-${type}`;
      const icon = {
        success: '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8l3 3 6-6"/></svg>',
        info:    '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11h.01"/></svg>',
        warn:    '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01"/></svg>',
        error:   '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l10 10M13 3L3 13"/></svg>',
      }[type] || '';
      t.innerHTML = icon + ' ' + message;
      c.appendChild(t);
      requestAnimationFrame(() => t.classList.add('show'));
      setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 220);
      }, 2400);
    }
    registerCleanup(() => {
      if (container) { container.remove(); container = null; }
    });
    return {
      success: m => show(m, 'success'),
      info:    m => show(m, 'info'),
      warn:    m => show(m, 'warn'),
      error:   m => show(m, 'error'),
    };
  })();

  // ── Save-state indicator (topbar chip, fed by api()) ──
  const SaveState = (() => {
    const el = document.getElementById('save-state');
    const LABELS = {
      idle:   'Brouillon',
      saving: 'Enregistrement…',
      saved:  'Enregistré',
      error:  'Erreur de sauvegarde',
    };
    let lastSavedAt = null;
    function set(state) {
      if (!el) return;
      el.dataset.state = state;
      const label = el.querySelector('.save-state-label');
      if (label) label.textContent = LABELS[state] || LABELS.idle;
      if (state === 'saved') lastSavedAt = new Date();
    }
    return { set, get lastSavedAt() { return lastSavedAt; } };
  })();

  // ── api() — JSON mutation helper with per-consultation FIFO queue ──
  // Mutations are serialized (one in-flight request at a time) so debounced
  // auto-saves can never race a line-add against the aggregate's optimistic
  // lock. A 409 CONFLICT response is retried once transparently.
  const api = (() => {
    let chain = Promise.resolve();
    function csrfToken() {
      return document.getElementById('consultation-csrf')?.value || '';
    }
    async function send(url, data) {
      const body = new URLSearchParams();
      Object.entries(data || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null) body.append(key, value);
      });
      body.append('_token', csrfToken());
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
      });
      let payload = null;
      try { payload = await response.json(); } catch { /* non-JSON response */ }
      return { status: response.status, payload };
    }
    function mutate(url, data) {
      const run = async () => {
        SaveState.set('saving');
        try {
          let result = await send(url, data);
          if (result.status === 409 && result.payload?.errorCode === 'CONFLICT') {
            result = await send(url, data);
          }
          if (result.payload?.success) {
            SaveState.set('saved');
            return result.payload;
          }
          SaveState.set('error');
          const message = result.payload?.errors?.global?.[0] || 'Erreur lors de la sauvegarde';
          Toast.error(message);
          return result.payload || { success: false, errorCode: 'HTTP_' + result.status };
        } catch {
          SaveState.set('error');
          Toast.error('Connexion perdue — modification non enregistrée');
          return { success: false, errorCode: 'NETWORK' };
        }
      };
      chain = chain.then(run, run);
      return chain;
    }
    return { mutate };
  })();
  void api; // wired to real endpoints by the Phase 2 vertical slices

  // Parses a server datetime as UTC even when the string carries no zone
  // marker (the read side emits "YYYY-MM-DD HH:MM:SS" in UTC).
  function parseUtcDate(value) {
    if (!value) return null;
    const normalized = /(?:Z|[+-]\d{2}:?\d{2})$/.test(value) ? value : value.replace(' ', 'T') + 'Z';
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  // ── Live consultation timer (topbar, from startedAtUtc) ──
  (() => {
    const timerEl = document.getElementById('consultation-timer');
    const startedAt = parseUtcDate(HYDRATION.startedAtUtc);
    if (!timerEl || !startedAt) return;
    const closedAt = parseUtcDate(HYDRATION.closedAtUtc);
    // Compensate client clock skew against the server's notion of "now"
    const serverNow = parseUtcDate(HYDRATION.serverNowUtc);
    const clockOffsetMs = serverNow ? serverNow.getTime() - Date.now() : 0;
    const render = () => {
      const end = closedAt || new Date(Date.now() + clockOffsetMs);
      const totalSeconds = Math.max(0, Math.floor((end - startedAt) / 1000));
      const h = Math.floor(totalSeconds / 3600);
      const m = Math.floor((totalSeconds % 3600) / 60);
      const s = totalSeconds % 60;
      const pad = n => String(n).padStart(2, '0');
      timerEl.textContent = h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
    };
    render();
    if (!closedAt) {
      const interval = setInterval(render, 1000);
      registerCleanup(() => clearInterval(interval));
    }
  })();

(() => {
  'use strict';

  // ═══════════════════════════════════════════════
  //  STATE
  // ═══════════════════════════════════════════════
  const state = {
    patient: {
      name: 'Luna', species: 'Chien', breed: 'Beauceron',
      age: 4, sex: 'F', weight: 28.5, lastWeight: 28.8,
      allergies: ['Pénicilline'],
    },
    consultation: {
      startedAt: new Date(),
    },
  };

  // ═══════════════════════════════════════════════
  //  DATA — catalogues mockés
  // ═══════════════════════════════════════════════
  const MEDICATIONS = [
    { code: 'MED-001', name: 'Méloxicam 1,5 mg/mL', cls: 'AINS', form: 'sol. buvable', dose: 0.1, doseUnit: 'mg/kg', route: 'per os', freq: '1×/j', days: 7, price: 14.80 },
    { code: 'MED-002', name: 'Carprofène 50 mg', cls: 'AINS', form: 'comprimés', dose: 2, doseUnit: 'mg/kg', route: 'per os', freq: '1×/j', days: 7, price: 18.50 },
    { code: 'MED-003', name: 'Cartrophen Vet 100 mg/mL', cls: 'Chondroprotecteur', form: 'injectable', dose: 3, doseUnit: 'mg/kg', route: 'SC', freq: '1×/sem', days: 28, price: 38.50 },
    { code: 'MED-004', name: 'Amoxicilline 500 mg', cls: 'Antibiotique', form: 'comprimés', dose: 20, doseUnit: 'mg/kg', route: 'per os', freq: '2×/j', days: 7, price: 22.00, contra: ['Pénicilline'] },
    { code: 'MED-005', name: 'Marbofloxacine 50 mg', cls: 'Antibiotique', form: 'comprimés', dose: 2, doseUnit: 'mg/kg', route: 'per os', freq: '1×/j', days: 7, price: 28.50 },
    { code: 'MED-006', name: 'Doxycycline 100 mg', cls: 'Antibiotique', form: 'comprimés', dose: 5, doseUnit: 'mg/kg', route: 'per os', freq: '1×/j', days: 14, price: 16.00 },
    { code: 'MED-007', name: 'Tramadol 50 mg', cls: 'Antalgique', form: 'comprimés', dose: 4, doseUnit: 'mg/kg', route: 'per os', freq: '3×/j', days: 5, price: 12.00 },
    { code: 'MED-008', name: 'Gabapentine 100 mg', cls: 'Antalgique', form: 'gélules', dose: 10, doseUnit: 'mg/kg', route: 'per os', freq: '2×/j', days: 14, price: 19.50 },
    { code: 'MED-009', name: 'Furosémide 40 mg', cls: 'Diurétique', form: 'comprimés', dose: 2, doseUnit: 'mg/kg', route: 'per os', freq: '2×/j', days: 30, price: 9.80 },
    { code: 'MED-010', name: 'Apoquel 16 mg', cls: 'Antiprurigineux', form: 'comprimés', dose: 0.5, doseUnit: 'mg/kg', route: 'per os', freq: '2×/j', days: 14, price: 56.00 },
    { code: 'MED-011', name: 'Frontline Combo Spot-On', cls: 'Antiparasitaire', form: 'pipettes', dose: 1, doseUnit: 'pipette', route: 'cutané', freq: '1×/mois', days: 30, price: 11.50 },
    { code: 'MED-012', name: 'Vetmedin 5 mg', cls: 'Cardiologie', form: 'comprimés', dose: 0.25, doseUnit: 'mg/kg', route: 'per os', freq: '2×/j', days: 30, price: 42.00 },
  ];

  const VITAL_TYPES = [
    { id: 'temperature', label: 'Température', unit: '°C', range: '38,0–39,2', def: '38,5' },
    { id: 'fc', label: 'Fréq. cardiaque', unit: 'bpm', range: '70–120', def: '90' },
    { id: 'fr', label: 'Fréq. respiratoire', unit: 'rpm', range: '15–30', def: '20' },
    { id: 'sc', label: 'Score corporel', unit: '/9', range: 'Idéal 4–5', def: '5' },
    { id: 'douleur', label: 'Score douleur', unit: '/4', range: '0 = aucune', def: '0' },
    { id: 'trc', label: 'TRC', unit: 's', range: '< 2 s', def: '<2' },
    { id: 'muqueuses', label: 'Muqueuses', unit: '', range: 'Roses', def: 'Roses' },
    { id: 'tension', label: 'Tension artérielle', unit: 'mmHg', range: '120/80', def: '125/80' },
    { id: 'glycemie', label: 'Glycémie', unit: 'mmol/L', range: '4–7', def: '5,2' },
  ];

  const PRESET_MOTIFS = [
    { category: 'Médecine préventive', items: ['Vaccination', 'Vermifugation', 'Bilan annuel', 'Identification (puce)', 'Stérilisation'] },
    { category: 'Pathologie', items: ['Boiterie', 'Vomissements', 'Diarrhée', 'Toux', 'Prurit', 'Otite'] },
    { category: 'Soins', items: ['Toilettage', 'Détartrage', 'Pansement', 'Retrait de fil'] },
    { category: 'Suivi', items: ['Suivi post-op', 'Recontrôle', 'Suivi chronique'] },
    { category: 'Urgence', items: ['Urgence vitale', 'Traumatisme', 'Intoxication'] },
  ];

  const PAST_CONSULTATIONS = [
    { date: '24 janv. 2026', motif: 'Bilan annuel + vaccin', summary: 'Vaccination CHPLR. Poids stable 28,8 kg. RAS clinique.' },
    { date: '12 sept. 2025', motif: 'Boiterie postérieure', summary: 'Première suspicion arthrose. AINS 5 j. À surveiller.' },
    { date: '03 mars 2025', motif: 'Vermifugation', summary: 'Drontal Plus. Pas de symptôme rapporté.' },
    { date: '10 août 2024', motif: 'Plaie patte avant', summary: 'Plaie superficielle. Désinfection + antibio 5 j. Collerette.' },
    { date: '20 mai 2024', motif: 'Stérilisation', summary: 'Ovariohystérectomie. Suites simples. Retrait points J+10.' },
  ];

  // ═══════════════════════════════════════════════
  //  MODAL SYSTEM
  // ═══════════════════════════════════════════════
  const Modal = (() => {
    let mount = null;
    let escHandler = null;
    function open({ title, body, footer, width = '' }) {
      close();
      mount = document.createElement('div');
      mount.className = 'modal-overlay';
      const wClass = width === 'wide' ? 'modal-wide' : (width === 'narrow' ? 'modal-narrow' : '');
      mount.innerHTML = `
        <div class="modal ${wClass}">
          <div class="modal-head">
            <span class="modal-title">${title}</span>
            <button class="btn btn-ghost btn-icon btn-sm" data-close title="Fermer">
              <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
            </button>
          </div>
          <div class="modal-body">${body}</div>
          ${footer ? `<div class="modal-foot">${footer}</div>` : ''}
        </div>
      `;
      document.body.appendChild(mount);
      mount.addEventListener('click', e => {
        if (e.target === mount || e.target.closest('[data-close]')) close();
      });
      escHandler = e => { if (e.key === 'Escape') close(); };
      document.addEventListener('keydown', escHandler);
      setTimeout(() => {
        const f = mount.querySelector('input,textarea');
        if (f) f.focus();
      }, 60);
      return mount;
    }
    function close() {
      if (mount) { mount.remove(); mount = null; }
      if (escHandler) { document.removeEventListener('keydown', escHandler); escHandler = null; }
    }
    function getEl(sel) { return mount ? mount.querySelector(sel) : null; }
    return { open, close, getEl, get el() { return mount; } };
  })();

  // ═══════════════════════════════════════════════
  //  DROPDOWN SYSTEM
  // ═══════════════════════════════════════════════
  const Dropdown = (() => {
    let menu = null;
    let docHandler = null;
    function open(anchor, items, opts = {}) {
      close();
      menu = document.createElement('div');
      menu.className = 'dropdown-menu';
      menu.innerHTML = items.map(it => {
        if (it.divider) return '<div class="dropdown-divider"></div>';
        const cls = 'dropdown-item' + (it.danger ? ' is-danger' : '');
        return `<div class="${cls}" data-action="${it.action}">
          ${it.icon ? `<svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">${it.icon}</svg>` : ''}
          <span>${it.label}</span>
          ${it.shortcut ? `<span class="dropdown-shortcut">${it.shortcut}</span>` : ''}
        </div>`;
      }).join('');
      document.body.appendChild(menu);
      const r = anchor.getBoundingClientRect();
      const align = opts.align || 'right';
      menu.style.top = (r.bottom + 4) + 'px';
      if (align === 'right') {
        menu.style.right = (window.innerWidth - r.right) + 'px';
      } else {
        menu.style.left = r.left + 'px';
      }
      menu.addEventListener('click', e => {
        const it = e.target.closest('.dropdown-item');
        if (!it) return;
        const action = it.dataset.action;
        close();
        if (opts.onSelect) opts.onSelect(action);
      });
      // close when click elsewhere
      docHandler = e => { if (menu && !menu.contains(e.target) && e.target !== anchor && !anchor.contains(e.target)) close(); };
      setTimeout(() => document.addEventListener('click', docHandler), 0);
    }
    function close() {
      if (menu) { menu.remove(); menu = null; }
      if (docHandler) { document.removeEventListener('click', docHandler); docHandler = null; }
    }
    return { open, close };
  })();

  // ═══════════════════════════════════════════════
  //  HELPERS
  // ═══════════════════════════════════════════════
  function fmtPrice(n) {
    return n.toFixed(2).replace('.', ',') + ' €';
  }
  function recomputeBillTotal() {
    const rows = [...document.querySelectorAll('.bill-row .bill-price')];
    const total = rows.reduce((s, el) => s + parseFloat(el.textContent.replace(',', '.').replace('€', '').trim()), 0);
    const ht = total / 1.2;
    const tva = total - ht;
    const totalEl = document.querySelector('.bill-total-amount');
    const subEl = document.querySelector('.bill-total div div:nth-child(2)');
    if (totalEl) totalEl.textContent = fmtPrice(total);
    if (subEl) subEl.textContent = `HT ${fmtPrice(ht).replace(' €','')} · TVA ${fmtPrice(tva).replace(' €','')}`;
  }
  function highlightNew(el) {
    el.classList.add('is-just-added');
    setTimeout(() => el.classList.remove('is-just-added'), 1500);
  }

  // ═══════════════════════════════════════════════
  //  ACTIONS — domain operations
  // ═══════════════════════════════════════════════

  // Add a medication: updates ordonnance + facturation
  function addMedication(med, posology) {
    // 1. Ajouter à l'ordonnance
    const list = document.querySelector('.rp-block .rx-list');
    const item = document.createElement('div');
    item.className = 'rx-item has-row-x';
    item.dataset.code = med.code;
    item.dataset.price = med.price;
    item.innerHTML = `
      <div class="rx-item-row">
        <span class="rx-name">${med.name}</span>
        <span class="rx-price">${fmtPrice(med.price)}</span>
        <button class="row-x" title="Retirer ce médicament" aria-label="Retirer ce médicament">
          <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
        </button>
      </div>
      <div class="rx-poso">${posology.dose} ${med.doseUnit} · ${posology.freq} · ${posology.days} ${posology.days > 1 ? 'jours' : 'jour'} · ${posology.route}</div>
    `;
    list.appendChild(item);
    // wire l'edit click sur le nouvel item
    wireRxItem(item, med);

    // 2. Mettre à jour le compteur d'ordonnance
    const titleEl = document.querySelector('.rp-block .rp-block-title');
    const countMatch = titleEl.textContent.match(/(\d+)/);
    const newCount = (countMatch ? parseInt(countMatch[1]) : 0) + 1;
    titleEl.textContent = `Ordonnance · ${newCount} médicaments`;

    // 3. Ajouter à la facturation (la ligne est en lecture seule, dérivée du rx)
    const billList = document.querySelector('.bill-list');
    const billRow = document.createElement('div');
    billRow.className = 'bill-row';
    billRow.dataset.code = med.code;
    billRow.innerHTML = `
      <div class="bill-name">
        ${med.name.split(' ').slice(0, 2).join(' ')}
        <span class="bill-qty">MED · ${posology.days} ${posology.days > 1 ? 'jours' : 'jour'}</span>
      </div>
      <div class="bill-price">${fmtPrice(med.price)}</div>
    `;
    billList.appendChild(billRow);
    recomputeBillTotal();
    highlightNew(item);
    highlightNew(billRow);

    Toast.success(`${med.name} ajouté à l'ordonnance et à la facture`);
  }

  function removeMedication(code) {
    document.querySelectorAll(`.rx-item[data-code="${code}"], .bill-row[data-code="${code}"]`).forEach(el => el.remove());
    const list = document.querySelector('.rp-block .rx-list');
    const titleEl = document.querySelector('.rp-block .rp-block-title');
    const newCount = list.children.length;
    titleEl.textContent = `Ordonnance · ${newCount} médicament${newCount > 1 ? 's' : ''}`;
    recomputeBillTotal();
    Toast.info('Médicament retiré');
  }

  // Add a constante (vital pill)
  function addVitalPill(vt, value) {
    const strip = document.querySelector('.vitals-strip');
    const pill = document.createElement('div');
    pill.className = 'vital-pill';
    pill.dataset.vital = vt.id;
    const valueDisplay = vt.unit ? `${value} <span class="unit">${vt.unit}</span>` : `<span style="font-size:var(--text-sm)">${value}</span>`;
    pill.innerHTML = `
      <div class="vital-pill-label">${vt.label}</div>
      <div class="vital-pill-value">${valueDisplay}</div>
      <button class="row-x" title="Retirer cette constante" aria-label="Retirer ${vt.label}">
        <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
      </button>
    `;
    // insert before the "+ ajouter" chip
    const addChip = strip.querySelector('.chip-add');
    strip.insertBefore(pill, addChip);
    wireVitalPill(pill);
    highlightNew(pill);
    Toast.success(`${vt.label} ajoutée : ${value} ${vt.unit}`);
  }

  // ═══════════════════════════════════════════════
  //  MODAL TEMPLATES
  // ═══════════════════════════════════════════════

  // — Add Motif —
  function openAddMotifModal() {
    const html = PRESET_MOTIFS.map(cat => `
      <div class="m-list-section">${cat.category}</div>
      ${cat.items.map(item => `<div class="m-item" data-motif="${item}"><div class="m-item-content"><span class="m-item-name">${item}</span></div></div>`).join('')}
    `).join('');
    const body = `
      <div class="modal-search">
        <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher un motif…" id="motif-search">
      </div>
      <div class="m-list" id="motif-list">${html}</div>
    `;
    Modal.open({ title: 'Ajouter un motif de consultation', body });

    // search filter
    Modal.getEl('#motif-search').addEventListener('input', e => {
      const q = e.target.value.toLowerCase();
      Modal.getEl('#motif-list').querySelectorAll('.m-item').forEach(it => {
        it.style.display = it.dataset.motif.toLowerCase().includes(q) ? '' : 'none';
      });
    });
    // select
    Modal.getEl('#motif-list').addEventListener('click', e => {
      const it = e.target.closest('.m-item');
      if (!it) return;
      const motif = it.dataset.motif;
      // already exists?
      const existing = [...document.querySelectorAll('.strip:first-child .chip:not(.chip-add)')]
        .find(c => c.textContent.trim() === motif);
      if (existing) {
        existing.classList.add('is-selected');
        Toast.info(`"${motif}" déjà présent`);
      } else {
        const chip = document.createElement('button');
        chip.className = 'chip is-selected';
        chip.textContent = motif;
        wireMotifChip(chip);
        const addChip = document.querySelector('.strip-content .chip-add');
        addChip.parentNode.insertBefore(chip, addChip);
        Toast.success(`Motif ajouté : ${motif}`);
      }
      Modal.close();
    });
  }

  // — Edit Weight —
  function openEditWeightModal() {
    const body = `
      <div class="form-row">
        <span class="form-row-label">Poids actuel</span>
        <input type="text" class="inline-num-input" id="weight-input" value="${state.patient.weight}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Unité</span>
        <span class="form-hint">kg</span>
      </div>
      <div class="form-row">
        <span class="form-row-label">Dernière pesée</span>
        <span class="form-hint">${state.patient.lastWeight} kg · 24 janv. 2026</span>
      </div>
      <div style="padding:var(--space-3);background:var(--brand-50);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--brand-700);margin-top:var(--space-3)">
        ℹ Le nouveau poids sert au recalcul automatique des dosages dans l'ordonnance.
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="weight-save">Enregistrer</button>
    `;
    Modal.open({ title: 'Modifier le poids', body, footer, width: 'narrow' });
    Modal.getEl('#weight-save').addEventListener('click', () => {
      const v = Modal.getEl('#weight-input').value.replace(',', '.');
      const newW = parseFloat(v);
      if (isNaN(newW) || newW <= 0) { Toast.error('Poids invalide'); return; }
      const oldW = state.patient.weight;
      state.patient.weight = newW;
      const delta = newW - state.patient.lastWeight;
      // update vital pill
      const pill = document.querySelector('.vital-pill[data-vital]');
      const pillValue = document.querySelector('.vital-pill .vital-pill-value');
      const pillDelta = document.querySelector('.vital-pill .vital-pill-delta');
      if (pillValue) pillValue.innerHTML = `${newW.toString().replace('.', ',')} <span class="unit">kg</span>`;
      if (pillDelta) {
        pillDelta.textContent = (delta >= 0 ? '+' : '') + delta.toFixed(1).replace('.', ',') + ' kg';
        pillDelta.classList.toggle('is-down', delta < 0);
        pillDelta.classList.toggle('is-up', delta > 0);
      }
      // update patient bar weight
      const meta = document.querySelector('.pb-meta');
      // (le poids n'est pas dans le meta dans cette version, mais on peut l'ajouter)
      Modal.close();
      Toast.success(`Poids enregistré : ${newW.toString().replace('.', ',')} kg (${delta >= 0 ? '+' : ''}${delta.toFixed(1).replace('.', ',')})`);
    });
  }

  // — Add Constante —
  function openAddConstanteModal() {
    const existing = [...document.querySelectorAll('.vital-pill[data-vital]')].map(p => p.dataset.vital);
    const available = VITAL_TYPES.filter(v => !existing.includes(v.id));
    if (available.length === 0) {
      Modal.open({
        title: 'Ajouter une constante',
        body: '<div class="m-empty">Toutes les constantes disponibles ont été ajoutées.</div>',
        footer: '<button class="btn btn-secondary" data-close>OK</button>',
      });
      return;
    }
    const html = available.map(v => `
      <div class="m-item" data-vital="${v.id}">
        <div class="m-item-content">
          <span class="m-item-name">${v.label}</span>
          <span class="m-item-meta">Plage normale : ${v.range}${v.unit ? ' ' + v.unit : ''}</span>
        </div>
        <span class="m-item-tag">${v.unit || '—'}</span>
      </div>
    `).join('');
    Modal.open({
      title: 'Ajouter une constante',
      body: `<div class="m-list">${html}</div>`,
    });
    Modal.getEl('.m-list').addEventListener('click', e => {
      const it = e.target.closest('.m-item');
      if (!it) return;
      const vt = VITAL_TYPES.find(v => v.id === it.dataset.vital);
      Modal.close();
      // ouvrir un input pour la valeur
      openVitalValueModal(vt);
    });
  }

  function openVitalValueModal(vt) {
    const body = `
      <div class="form-row">
        <span class="form-row-label">${vt.label}</span>
        <input type="text" class="inline-num-input" id="vital-input" value="${vt.def}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Unité</span>
        <span class="form-hint">${vt.unit || '—'}</span>
      </div>
      <div class="form-row">
        <span class="form-row-label">Normale</span>
        <span class="form-hint">${vt.range}</span>
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="vital-save">Ajouter</button>
    `;
    Modal.open({ title: vt.label, body, footer, width: 'narrow' });
    Modal.getEl('#vital-save').addEventListener('click', () => {
      const value = Modal.getEl('#vital-input').value.trim();
      if (!value) { Toast.error('Valeur requise'); return; }
      Modal.close();
      addVitalPill(vt, value);
    });
  }

  // — Add Medication —
  function openAddMedicationModal() {
    const grouped = MEDICATIONS.reduce((acc, m) => {
      (acc[m.cls] = acc[m.cls] || []).push(m);
      return acc;
    }, {});
    const html = Object.entries(grouped).map(([cls, items]) => `
      <div class="m-list-section">${cls}</div>
      ${items.map(m => {
        const blocked = m.contra && m.contra.some(c => state.patient.allergies.includes(c));
        return `
          <div class="m-item ${blocked ? 'is-disabled' : ''}" data-code="${m.code}" ${blocked ? 'data-blocked="1"' : ''}>
            <div class="m-item-content">
              <span class="m-item-name">${m.name}</span>
              <span class="m-item-meta">${m.form} · ${m.route} · ${m.dose} ${m.doseUnit}</span>
            </div>
            ${blocked ? `<span class="m-item-warn">filtré : ${m.contra.join(', ')}</span>` : ''}
            <span class="m-item-price">${fmtPrice(m.price)}</span>
          </div>
        `;
      }).join('')}
    `).join('');
    const body = `
      <div class="modal-search">
        <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher dans le catalogue…" id="med-search">
      </div>
      <div style="padding:var(--space-2) var(--space-3);background:var(--color-warning-bg);border:1px solid var(--color-warning-border);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--color-warning-text);margin-bottom:var(--space-3);display:flex;align-items:center;gap:var(--space-2)">
        <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
        Allergie déclarée : <strong>${state.patient.allergies.join(', ')}</strong> — molécules incompatibles filtrées.
      </div>
      <div class="m-list" id="med-list">${html}</div>
    `;
    Modal.open({ title: 'Ajouter un médicament', body, width: 'wide' });
    Modal.getEl('#med-search').addEventListener('input', e => {
      const q = e.target.value.toLowerCase();
      Modal.getEl('#med-list').querySelectorAll('.m-item').forEach(it => {
        const name = it.querySelector('.m-item-name').textContent.toLowerCase();
        it.style.display = name.includes(q) ? '' : 'none';
      });
    });
    Modal.getEl('#med-list').addEventListener('click', e => {
      const it = e.target.closest('.m-item');
      if (!it) return;
      if (it.dataset.blocked) {
        Toast.warn('Médicament incompatible avec les allergies du patient');
        return;
      }
      const med = MEDICATIONS.find(m => m.code === it.dataset.code);
      if (document.querySelector(`.rx-item[data-code="${med.code}"]`)) {
        Toast.warn('Ce médicament est déjà dans l\'ordonnance');
        return;
      }
      Modal.close();
      openMedicationPosologyModal(med);
    });
  }

  function openMedicationPosologyModal(med) {
    const totalDose = (state.patient.weight * med.dose).toFixed(2).replace('.', ',');
    const body = `
      <div style="padding:var(--space-3);background:var(--brand-50);border:1px solid var(--brand-100);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="font-weight:var(--weight-medium);font-size:var(--text-md)">${med.name}</div>
        <div style="font-size:var(--text-sm);color:var(--text-muted);margin-top:2px">${med.cls} · ${med.form}</div>
      </div>
      <div class="form-row">
        <span class="form-row-label">Dose</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-dose" value="${med.dose}" style="width:80px">
          <span class="form-hint">${med.doseUnit}</span>
          <span class="form-hint" style="margin-left:var(--space-2)">→ ${totalDose} mg / prise pour ${state.patient.weight} kg</span>
        </div>
      </div>
      <div class="form-row">
        <span class="form-row-label">Voie</span>
        <input type="text" class="inline-num-input" id="poso-route" value="${med.route}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Fréquence</span>
        <input type="text" class="inline-num-input" id="poso-freq" value="${med.freq}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Durée</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-days" value="${med.days}" style="width:80px">
          <span class="form-hint">jours</span>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:var(--space-3);border-top:1px solid var(--border-light)">
        <span style="font-size:var(--text-sm);color:var(--text-muted)">Coût catalogue</span>
        <span style="font-family:'DM Mono',monospace;font-size:var(--text-lg);font-weight:var(--weight-medium)">${fmtPrice(med.price)}</span>
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="poso-save">Ajouter à l'ordonnance</button>
    `;
    Modal.open({ title: 'Posologie', body, footer });
    Modal.getEl('#poso-save').addEventListener('click', () => {
      const posology = {
        dose: Modal.getEl('#poso-dose').value,
        route: Modal.getEl('#poso-route').value,
        freq: Modal.getEl('#poso-freq').value,
        days: parseInt(Modal.getEl('#poso-days').value) || med.days,
      };
      Modal.close();
      addMedication(med, posology);
    });
  }

  // — Print —
  function openPrintModal() {
    const body = `
      <div style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-3)">
        Sélectionnez les documents à imprimer ou télécharger.
      </div>
      <div class="print-grid">
        <button class="print-card" data-doc="cr">
          <span class="print-card-title">📋 Compte-rendu de consultation</span>
          <span class="print-card-meta">Synthèse SOAP complète · 1 page</span>
        </button>
        <button class="print-card" data-doc="ordo">
          <span class="print-card-title">💊 Ordonnance</span>
          <span class="print-card-meta">2 médicaments · format légal FR</span>
        </button>
        <button class="print-card" data-doc="facture">
          <span class="print-card-title">🧾 Facture</span>
          <span class="print-card-meta">Total 201,30 € TTC · NF525</span>
        </button>
        <button class="print-card" data-doc="all">
          <span class="print-card-title">📦 Tout</span>
          <span class="print-card-meta">CR + ordonnance + facture</span>
        </button>
      </div>
    `;
    Modal.open({ title: 'Imprimer / Télécharger', body });
    Modal.getEl('.print-grid').addEventListener('click', e => {
      const card = e.target.closest('.print-card');
      if (!card) return;
      const labels = { cr: 'Compte-rendu', ordo: 'Ordonnance', facture: 'Facture', all: '3 documents' };
      Modal.close();
      Toast.info(`${labels[card.dataset.doc]} envoyé à l'impression`);
    });
  }

  // — Historique —
  function openHistoryModal() {
    const html = PAST_CONSULTATIONS.map(c => `
      <div class="tl-item">
        <span class="tl-date">${c.date}</span>
        <div class="tl-content">
          <span class="tl-motif">${c.motif}</span>
          <span class="tl-summary">${c.summary}</span>
        </div>
      </div>
    `).join('');
    Modal.open({
      title: `Historique — ${state.patient.name}`,
      body: `<div class="timeline">${html}</div>`,
      width: 'wide',
    });
    Modal.getEl('.timeline').addEventListener('click', e => {
      const item = e.target.closest('.tl-item');
      if (!item) return;
      Toast.info('Ouverture du dossier historique (mock)');
    });
  }

  // — Patient File (Dossier complet) —
  function openPatientFileModal() {
    const body = `
      <div style="display:flex;align-items:center;gap:var(--space-4);padding-bottom:var(--space-4);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-4)">
        <div class="pb-photo" style="width:72px;height:72px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-label="Chien">
            <path d="M10 5.172A4 4 0 0 1 14 5l3 1.5"/>
            <path d="M5.5 9.172a3 3 0 0 1 0-4 3 3 0 0 1 4.5 0L11 6.5"/>
            <path d="M19 9.172a3 3 0 0 0 0-4 3 3 0 0 0-4.5 0L13 6.5"/>
            <path d="M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309"/>
            <path d="M8 14v.5"/>
            <path d="M16 14v.5"/>
            <path d="M11.25 16.25h1.5L12 17z"/>
          </svg>
        </div>
        <div>
          <div style="font-size:var(--text-xl);font-weight:var(--weight-medium);letter-spacing:-.3px">${state.patient.name}</div>
          <div style="color:var(--text-muted);margin-top:2px">${state.patient.breed} · ${state.patient.age} ans · ${state.patient.weight} kg</div>
          <div style="font-family:'DM Mono',monospace;font-size:var(--text-sm);color:var(--text-subtle);margin-top:2px">250268500412739</div>
        </div>
      </div>

      <div class="m-list-section" style="padding-left:0">Identité</div>
      <div class="form-row"><span class="form-row-label">Nom</span><span>${state.patient.name}</span></div>
      <div class="form-row"><span class="form-row-label">Espèce</span><span>${state.patient.species}</span></div>
      <div class="form-row"><span class="form-row-label">Race</span><span>${state.patient.breed}</span></div>
      <div class="form-row"><span class="form-row-label">Naissance</span><span>15 mars 2022 (${state.patient.age} ans)</span></div>
      <div class="form-row"><span class="form-row-label">Sexe</span><span>Femelle stérilisée</span></div>
      <div class="form-row"><span class="form-row-label">Couleur</span><span>Noir et feu</span></div>
      <div class="form-row"><span class="form-row-label">Propriétaire</span><span style="color:var(--brand-600);cursor:pointer">Marie Lambert →</span></div>

      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Médical</div>
      <div class="form-row"><span class="form-row-label">Allergies</span><span style="color:var(--color-danger-text)">Pénicilline</span></div>
      <div class="form-row"><span class="form-row-label">Conditions</span><span>Suivi arthrose précoce</span></div>
      <div class="form-row"><span class="form-row-label">Vaccinations</span><span>CHPLR à jour (24/01/2026)</span></div>
      <div class="form-row"><span class="form-row-label">Vermifuge</span><span>Drontal · 03/03/2025</span></div>

      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Activité</div>
      <div class="form-row"><span class="form-row-label">Consultations</span><span>${PAST_CONSULTATIONS.length} antérieures</span></div>
      <div class="form-row"><span class="form-row-label">Dernière visite</span><span>24 janv. 2026 (3 mois)</span></div>
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-primary">Modifier le dossier</button>
    `;
    Modal.open({ title: 'Dossier patient', body, footer, width: 'wide' });
  }

  // — Owner —
  function openOwnerModal() {
    const body = `
      <div style="display:flex;align-items:center;gap:var(--space-3);padding-bottom:var(--space-3);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-3)">
        <span class="avatar avatar-lg">ML</span>
        <div>
          <div style="font-size:var(--text-lg);font-weight:var(--weight-medium)">Marie Lambert</div>
          <div style="color:var(--text-muted);font-size:var(--text-sm)">Propriétaire depuis 2022 · 1 animal</div>
        </div>
      </div>
      <div class="form-row"><span class="form-row-label">Téléphone</span><span style="color:var(--brand-600);cursor:pointer">+33 6 12 34 56 78 →</span></div>
      <div class="form-row"><span class="form-row-label">Email</span><span style="color:var(--brand-600);cursor:pointer">marie.lambert@example.fr →</span></div>
      <div class="form-row"><span class="form-row-label">Adresse</span><span>15 rue Paradis, 13001 Marseille</span></div>
      <div class="form-row"><span class="form-row-label">Solde</span><span style="color:var(--color-success-600)">+0,00 €</span></div>
      <div class="form-row"><span class="form-row-label">Préférences</span><span>SMS rappel J-1 · Email facture</span></div>
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-primary">Voir fiche complète</button>
    `;
    Modal.open({ title: 'Propriétaire', body, footer });
  }

  // — Allergie info —
  function openAllergieInfoModal() {
    const body = `
      <div style="padding:var(--space-3);background:var(--color-danger-bg);border:1px solid var(--color-danger-border);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="display:flex;align-items:center;gap:var(--space-2);color:var(--color-danger-text);font-weight:var(--weight-medium);margin-bottom:var(--space-1)">
          <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
          Allergie pénicilline
        </div>
        <div style="font-size:var(--text-sm);color:var(--color-danger-text)">Réaction cutanée sévère observée le 12/09/2025. Toutes les pénicillines (amoxicilline, ampicilline, etc.) sont à proscrire.</div>
      </div>
      <div style="font-size:var(--text-sm);color:var(--text-muted);line-height:var(--lh-loose)">
        Cette allergie est <strong style="font-weight:var(--weight-medium);color:var(--text-primary)">automatiquement appliquée</strong> au catalogue de médicaments dans la modale d'ajout d'ordonnance. Les molécules incompatibles apparaissent grisées et non sélectionnables.
      </div>
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-ghost">Modifier</button>
    `;
    Modal.open({ title: 'Détails de l\'allergie', body, footer, width: 'narrow' });
  }

  // — Clôturer la consultation —
  function openCloseConsultationModal() {
    // validation: on regarde les éléments de la page
    const motifs = document.querySelectorAll('.strip-content .chip:not(.chip-add).is-selected').length;
    const dxCount = document.querySelectorAll('.dx-item').length;
    const planCount = document.querySelectorAll('.plan-item').length;
    const planDone = document.querySelectorAll('.plan-item.is-done').length;
    const rxCount = document.querySelectorAll('.rx-item').length;
    const total = document.querySelector('.bill-total-amount').textContent;

    function check(label, ok, extra) {
      return `<div class="check-item ${ok ? '' : 'is-fail'}">
        <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">${ok ? '<path d="M3.5 8l3 3 6-6"/>' : '<circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11h.01"/>'}</svg>
        <span>${label}</span>
        ${extra ? `<span class="check-item-extra">${extra}</span>` : ''}
      </div>`;
    }

    const body = `
      <div class="checklist">
        ${check('Motif renseigné', motifs >= 1, motifs + ' chip(s)')}
        ${check('Au moins une constante', true, '1 mesure')}
        ${check('Examen objectif renseigné', true, '8 systèmes')}
        ${check('Au moins un diagnostic', dxCount >= 1, dxCount + ' dx')}
        ${check('Plan défini', planCount >= 1, planDone + '/' + planCount + ' réalisés')}
        ${check('Facturation établie', rxCount >= 0, total)}
      </div>
      <div class="m-list-section" style="padding-left:0">Finalisation</div>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked> <span>Envoyer la facture par email à Marie Lambert</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked> <span>Envoyer l'ordonnance par email</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox"> <span>Programmer le RDV de recontrôle (J+14)</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked> <span>Programmer rappel SMS J+13</span>
      </label>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="close-confirm">Clôturer et envoyer</button>
    `;
    Modal.open({ title: 'Clôture de la consultation', body, footer });
    Modal.getEl('#close-confirm').addEventListener('click', () => {
      Modal.close();
      openConsultationClosedModal();
    });
  }

  function openConsultationClosedModal() {
    const body = `
      <div style="text-align:center;padding:var(--space-6) var(--space-4)">
        <div style="width:64px;height:64px;border-radius:var(--radius-full);background:var(--color-success-bg);display:inline-flex;align-items:center;justify-content:center;margin-bottom:var(--space-3)">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="none" stroke="var(--color-success-600)" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
        </div>
        <div style="font-size:var(--text-lg);font-weight:var(--weight-medium);margin-bottom:var(--space-2)">Consultation clôturée</div>
        <div style="font-size:var(--text-md);color:var(--text-muted);line-height:var(--lh-loose)">
          La facture et l'ordonnance ont été envoyées à Marie Lambert.<br>
          Le rappel SMS sera envoyé le <strong style="color:var(--text-primary);font-weight:var(--weight-medium)">22 mai 2026</strong>.
        </div>
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Rester ici</button>
      <button class="btn btn-primary" id="next-patient">Patient suivant →</button>
    `;
    Modal.open({ title: '', body, footer, width: 'narrow' });
    Modal.getEl('#next-patient').addEventListener('click', () => {
      Modal.close();
      Toast.success('Navigation vers le prochain patient en attente');
    });
  }

  function openConfirmModal({ title, message, confirmLabel = 'Confirmer', danger = false, onConfirm }) {
    const body = `<div style="font-size:var(--text-md);color:var(--text-secondary);line-height:var(--lh-loose);padding:var(--space-2) 0">${message}</div>`;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="confirm-yes">${confirmLabel}</button>
    `;
    Modal.open({ title, body, footer, width: 'narrow' });
    Modal.getEl('#confirm-yes').addEventListener('click', () => {
      Modal.close();
      if (onConfirm) onConfirm();
    });
  }

  // ═══════════════════════════════════════════════
  //  WIRING — handlers attachés au DOM
  // ═══════════════════════════════════════════════

  // Existing simple toggles (chips, exam cells, plan items)
  function wireMotifChip(chip) {
    chip.addEventListener('click', () => chip.classList.toggle('is-selected'));
  }
  document.querySelectorAll('.strip:first-child .chip:not(.chip-add)').forEach(wireMotifChip);

  function wireVitalPill(pill) {
    pill.addEventListener('click', e => {
      // ignore click sur la croix
      if (e.target.closest('.row-x')) return;
      // pour le poids, ouvrir l'éditeur dédié
      if (!pill.dataset.vital || pill.dataset.vital === 'weight') {
        // pas de data-vital sur le poids initial → considérer comme weight
        openEditWeightModal();
      } else {
        const vt = VITAL_TYPES.find(v => v.id === pill.dataset.vital);
        if (vt) openVitalValueModal(vt);
      }
    });

    const xBtn = pill.querySelector('.row-x');
    if (xBtn) {
      xBtn.addEventListener('click', e => {
        e.stopPropagation();
        const label = pill.querySelector('.vital-pill-label').textContent;
        pill.remove();
        Toast.info(`${label} retirée`);
      });
    }
  }
  document.querySelectorAll('.vital-pill').forEach(wireVitalPill);

  function wireRxItem(item, med) {
    item.addEventListener('click', e => {
      // ignore les clics sur la croix
      if (e.target.closest('.row-x')) return;
      const code = item.dataset.code;
      const m = med || MEDICATIONS.find(x => x.code === code);
      if (m) openMedicationPosologyModal(m);
    });

    const xBtn = item.querySelector('.row-x');
    if (xBtn) {
      xBtn.addEventListener('click', e => {
        e.stopPropagation();
        const code = item.dataset.code;
        const name = item.querySelector('.rx-name').textContent;
        item.remove();
        // remove la bill-row liée
        const billRow = document.querySelector(`.bill-row[data-code="${code}"]`);
        if (billRow) billRow.remove();
        // update counter
        const list = document.querySelector('.rp-block .rx-list');
        const titleEl = document.querySelector('.rp-block .rp-block-title');
        const newCount = list.children.length;
        titleEl.textContent = `Ordonnance · ${newCount} médicament${newCount > 1 ? 's' : ''}`;
        recomputeBillTotal();
        Toast.info(`${name} retiré de l'ordonnance`);
      });
    }
  }
  document.querySelectorAll('.rx-item').forEach(item => wireRxItem(item, null));

  // ─── Sidebar toggle (focus mode) ───
  const sidebarToggleBtn = document.getElementById('sidebar-toggle');
  const sidebarEl = document.querySelector('.sidebar');
  if (sidebarToggleBtn && sidebarEl) {
    // état initial : le HTML naît avec is-collapsed (focus mode par défaut sur la consultation)
    if (sidebarEl.classList.contains('is-collapsed')) {
      document.body.classList.add('sidebar-collapsed');
    }
    sidebarToggleBtn.addEventListener('click', () => {
      const collapsed = sidebarEl.classList.toggle('is-collapsed');
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      // ferme tout dropdown ouvert (les positions absolues sinon ne suivent pas)
      Dropdown.close();
    });
  }

  // ─── Topbar ───
  document.querySelector('.crumb a')?.addEventListener('click', e => {
    e.preventDefault();
    const href = e.currentTarget.href;
    openConfirmModal({
      title: 'Quitter la consultation',
      message: 'La consultation est en brouillon. Vous pouvez la reprendre depuis l\'agenda.',
      confirmLabel: 'Quitter',
      onConfirm: () => { window.Turbo ? window.Turbo.visit(href) : window.location.assign(href); },
    });
  });

  // Topbar buttons
  document.getElementById('btn-history')?.addEventListener('click', openHistoryModal);
  document.getElementById('btn-print')?.addEventListener('click', openPrintModal);

  // ─── Patient bar ───
  document.querySelector('.pb-photo')?.addEventListener('click', () => Toast.info('Téléversement de photo (à implémenter)'));
  document.querySelector('.pb-owner')?.addEventListener('click', openOwnerModal);
  // warnings
  document.querySelectorAll('.pb-warn').forEach(warn => {
    warn.addEventListener('click', () => {
      if (warn.textContent.includes('pénicilline')) openAllergieInfoModal();
      else Toast.info('Détails de la condition (à implémenter)');
    });
    warn.style.cursor = 'pointer';
  });
  // dossier complet
  document.getElementById('btn-patient-file')?.addEventListener('click', openPatientFileModal);
  // ⋯ menu
  const moreBtn = document.getElementById('btn-patient-more');
  if (moreBtn) moreBtn.addEventListener('click', e => {
    Dropdown.open(e.currentTarget, [
      { action: 'sms', icon: '<rect x="2" y="3" width="12" height="9" rx="1"/><path d="M5 12l-1 2 4-2"/>', label: 'Envoyer un SMS au propriétaire' },
      { action: 'email', icon: '<rect x="2" y="3" width="12" height="9" rx="1"/><path d="M2 4l6 5 6-5"/>', label: 'Envoyer un email' },
      { action: 'tag', icon: '<path d="M3 3h6l5 5-5 5-6-6z"/><circle cx="6" cy="6" r="1"/>', label: 'Ajouter un tag' },
      { divider: true },
      { action: 'archive', icon: '<rect x="2" y="3" width="12" height="3"/><path d="M3 6v8h10V6M6 9h4"/>', label: 'Archiver le patient' },
      { action: 'transfer', icon: '<path d="M2 8h12M9 4l5 4-5 4"/>', label: 'Transférer vers une autre clinique', danger: true },
    ], { onSelect: a => Toast.info(`Action : ${a}`) });
  });

  // ─── Save state click → details ───
  document.getElementById('save-state')?.addEventListener('click', () => {
    const savedAt = SaveState.lastSavedAt;
    Toast.info(savedAt
      ? `Dernière sauvegarde à ${savedAt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
      : 'Aucune modification enregistrée pour le moment');
  });

  // ─── Motif strip ───
  document.querySelector('.strip:first-child .chip-add')?.addEventListener('click', openAddMotifModal);

  function wireStripTextEdit(span) {
    span.addEventListener('click', () => {
      const current = span.textContent;
      const input = document.createElement('textarea');
      input.value = current;
      input.className = 'quad-textarea';
      input.style.minHeight = '40px';
      input.style.fontSize = 'var(--text-md)';
      input.style.border = '1px solid var(--brand-400)';
      input.style.background = 'var(--surface-card)';
      input.style.padding = 'var(--space-2)';
      span.replaceWith(input);
      input.focus();
      input.select();
      const finish = (cancelled = false) => {
        const span2 = document.createElement('span');
        span2.className = 'strip-text';
        span2.style.marginLeft = 'var(--space-2)';
        span2.textContent = cancelled ? current : input.value;
        input.replaceWith(span2);
        wireStripTextEdit(span2);
        if (!cancelled && input.value !== current) Toast.success('Motif mis à jour');
      };
      input.addEventListener('blur', () => finish(false));
      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); finish(false); }
        if (e.key === 'Escape') { e.preventDefault(); finish(true); }
      });
    });
  }
  document.querySelectorAll('.strip:first-child .strip-text').forEach(wireStripTextEdit);

  // ─── Constantes strip ───
  document.querySelector('.strip:last-child .chip-add')?.addEventListener('click', openAddConstanteModal);

  // ─── SOAP S — dicter ───
  const sQuad = document.querySelector('.quad[data-letter="S"]');
  sQuad?.querySelector('.quad-action')?.addEventListener('click', () => {
    Toast.warn('Dictée vocale activée (mock) — parlez maintenant');
    setTimeout(() => Toast.success('Dictée transcrite'), 2000);
  });

  // ─── Right panel ───
  // Print/view ordonnance
  document.getElementById('btn-rx-print')?.addEventListener('click', openPrintModal);

  // Add medication button
  document.getElementById('btn-add-medication')?.addEventListener('click', openAddMedicationModal);

  // Bill draft badge → status menu
  const draftBadge = document.getElementById('bill-status-badge');
  if (draftBadge) {
    draftBadge.style.cursor = 'pointer';
    draftBadge.addEventListener('click', e => {
      Dropdown.open(e.currentTarget, [
        { action: 'draft', label: '✏ Brouillon' },
        { action: 'pending', label: '⏳ À facturer' },
        { action: 'paid', label: '✓ Payée' },
      ], { onSelect: a => Toast.info(`Statut: ${a}`) });
    });
  }

  // Marquer les rx-items initiaux avec leur data-code
  document.querySelectorAll('.rp-block .rx-item').forEach((item, i) => {
    if (!item.dataset.code) {
      const name = item.querySelector('.rx-name').textContent;
      const med = MEDICATIONS.find(m => name.includes(m.name.split(' ')[0]));
      if (med) item.dataset.code = med.code;
    }
  });

  // ─── Footer ───
  // Pause
  const pauseBtn = document.getElementById('btn-pause');
  if (pauseBtn) pauseBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Mettre la consultation en pause',
      message: 'La consultation reste accessible depuis l\'agenda. Vous pouvez la reprendre à tout moment.',
      confirmLabel: 'Mettre en pause',
      onConfirm: () => Toast.success('Consultation mise en pause'),
    });
  });

  // Plus tard
  const laterBtn = document.getElementById('btn-later');
  if (laterBtn) laterBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Reporter la consultation',
      message: 'Le brouillon sera conservé. Vous pourrez la reprendre depuis l\'agenda quand vous le souhaitez.',
      confirmLabel: 'Reporter',
      onConfirm: () => Toast.success('Consultation reportée — brouillon sauvegardé'),
    });
  });

  // Clôturer
  document.getElementById('btn-close-consultation')?.addEventListener('click', openCloseConsultationModal);

  // ─── Keyboard shortcuts ───
  onDocument('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      openCloseConsultationModal();
    }
  });

  // Close any floating UI left open when Turbo caches the page
  registerCleanup(() => { Modal.close(); Dropdown.close(); });

})();


/* ====================================================================
 * QUAD O — Objectif (Examen clinique)
 * Source : objectif-redesign-final.html — IDs préfixés qO-
 * ==================================================================== */
(function() {
'use strict';

const ICONS = {
  heart:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
  wind:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>',
  gi:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18"/><path d="M5 4v10a5 5 0 0 0 10 0V8a3 3 0 0 1 6 0v6"/></svg>',
  droplet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>',
  bone:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 10c.7-.7 1.69 0 2.5 0a2.5 2.5 0 1 0 0-5 .5.5 0 0 1-.5-.5 2.5 2.5 0 1 0-5 0c0 .81.7 1.8 0 2.5l-7 7c-.7.7-1.69 0-2.5 0a2.5 2.5 0 0 0 0 5c.28 0 .5.22.5.5a2.5 2.5 0 1 0 5 0c0-.81-.7-1.8 0-2.5z"/></svg>',
  brain:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 0 0 12 18z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 0 1 12 18z"/></svg>',
  sparkle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.94 14.06 2 22"/><path d="m12 7 3 3"/><path d="M19 2v8h-8"/><path d="m15 5-2.5 2.5"/><circle cx="6" cy="18" r="2"/></svg>',
  eye:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
  tooth:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5.5C12 4 14 2 16.5 2c2.8 0 4.5 2 4.5 5 0 3-1.5 5.5-2.5 9-.8 3-1 6-3 6-2 0-2-3-3.5-3s-1.5 3-3.5 3c-2 0-2.2-3-3-6-1-3.5-2.5-6-2.5-9 0-3 1.7-5 4.5-5C9.5 2 11.5 4 11.5 5.5"/></svg>',
  shell:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="13" rx="9" ry="7"/><path d="M12 6v14M6 9l1 8M18 9l-1 8M9 7l-1 12M15 7l1 12"/></svg>',
};

const SYSTEM_DEFS = {
  cardio:    { name:'Cardiovasculaire',     icon:'heart',   drilldown:'cardio' },
  resp:      { name:'Respiratoire',         icon:'wind',    drilldown:null },
  gi:        { name:'Digestif',             icon:'gi',      drilldown:null },
  uro:       { name:'Urogénital',           icon:'droplet', drilldown:null },
  loco:      { name:'Locomoteur',           icon:'bone',    drilldown:'loco' },
  neuro:     { name:'Neurologique',         icon:'brain',   drilldown:null },
  derma:     { name:'Cutané',               icon:'sparkle', drilldown:'derma' },
  ophta:     { name:'Ophtalmologique',      icon:'eye',     drilldown:null },
  dentaire:  { name:'Dentaire',             icon:'tooth',   drilldown:null },
  tegument:  { name:'Tégument / Carapace',  icon:'shell',   drilldown:'derma' },
};

const TEMPLATES = {
  'chien':   { name:'Chien standard', emoji:'🐕', systems:['cardio','resp','gi','uro','loco','neuro','derma','ophta'], enabled:true },
  'chat':    { name:'Chat standard',  emoji:'🐈', systems:['cardio','resp','gi','uro','loco','neuro','derma','ophta'], enabled:true },
  'lapin':   { name:'Lapin',          emoji:'🐰', systems:['dentaire','gi','resp','cardio','uro','loco','derma'],      enabled:true },
  'reptile': { name:'NAC reptile',    emoji:'🦎', systems:['tegument','resp','cardio','gi','uro','ophta'],             enabled:true },
  'oiseau':  { name:'NAC oiseau',     emoji:'🦜', systems:['resp','cardio','derma','gi','loco','ophta'],               enabled:false },
  'equin':   { name:'Équin',          emoji:'🐴', systems:['loco','cardio','resp','gi','dentaire','derma','ophta','neuro'], enabled:false },
};

let currentTemplate = 'chien';

const state = {
  cardio:   { status:'normal',  notes:'', structured:{}, media:[] },
  resp:     { status:'normal',  notes:'', structured:{}, media:[] },
  gi:       { status:'normal',  notes:'', structured:{}, media:[] },
  uro:      { status:'normal',  notes:'', structured:{}, media:[] },
  loco:     { status:'anomaly', notes:'Boiterie grade 2/4 postérieur droit. Douleur à la flexion forcée du grasset. Tiroir antérieur négatif. Amyotrophie modérée du quadriceps droit.',
              structured:{ limb:'PD', grade:2, type:'mechanical', region:'grasset' },
              media:[{ type:'video', name:'Boiterie_12s.mp4' }] },
  neuro:    { status:'normal',  notes:'', structured:{}, media:[] },
  derma:    { status:'normal',  notes:'', structured:{}, media:[] },
  ophta:    { status:'untested',notes:'', structured:{}, media:[] },
  dentaire: { status:'untested',notes:'', structured:{}, media:[] },
  tegument: { status:'untested',notes:'', structured:{}, media:[] },
};

const STATUS_LABEL = { normal:'RAS', anomaly:'Anomalie', untested:'Non testé' };

function $(s, r=document) { return r.querySelector(s); }
function $$(s, r=document) { return [...r.querySelectorAll(s)]; }

function toast(msg) {
  const el = $('#qO-annotation');
  el.textContent = msg;
  el.classList.add('is-visible');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('is-visible'), 1700);
}

function isFilled(s) {
  const hasStruct = s.structured && Object.values(s.structured).some(v => v !== '' && v !== null && v !== undefined);
  return hasStruct || s.notes || (s.media && s.media.length > 0);
}

function currentSystems() {
  return TEMPLATES[currentTemplate].systems.map(id => ({ id, ...SYSTEM_DEFS[id] }));
}

function stateToggleHTML(sysId, currentStatus) {
  return `
    <div class="state-toggle" data-sys="${sysId}">
      <button class="state-btn ${currentStatus==='normal'?'is-active':''}" data-state="normal" type="button">RAS</button>
      <button class="state-btn ${currentStatus==='anomaly'?'is-active':''}" data-state="anomaly" type="button">Anomalie</button>
      <button class="state-btn ${currentStatus==='untested'?'is-active':''}" data-state="untested" type="button">Non testé</button>
    </div>`;
}

// ════════════════════════════════════════════════════════════
//  TEMPLATE BUTTON
// ════════════════════════════════════════════════════════════
function renderTemplateLabel() {
  $('#qO-template-btn-name').textContent = TEMPLATES[currentTemplate].name;
}
function closeTemplateMenu() {
  const m = document.querySelector('.template-menu');
  if (m) m.remove();
  $('#qO-template-btn').classList.remove('is-open');
}
function openTemplateMenu() {
  const btn = $('#qO-template-btn');
  if (btn.classList.contains('is-open')) { closeTemplateMenu(); return; }
  btn.classList.add('is-open');
  const rect = btn.getBoundingClientRect();
  const menu = document.createElement('div');
  menu.className = 'template-menu';
  menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
  menu.style.right = (window.innerWidth - rect.right) + 'px';
  const itemsHTML = Object.entries(TEMPLATES).map(([id, tpl]) => {
    const active = id === currentTemplate;
    const dimmed = !tpl.enabled ? 'tm-item--secondary' : '';
    return `
      <div class="tm-item ${active?'is-active':''} ${dimmed}" data-tpl="${id}" data-enabled="${tpl.enabled}">
        <span class="tm-emoji">${tpl.emoji}</span>
        <div class="tm-content">
          <div class="tm-name">${tpl.name}</div>
          <div class="tm-meta">${tpl.systems.length} systèmes${!tpl.enabled?' · bientôt':''}</div>
        </div>
        ${active ? '<svg class="tm-check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>' : ''}
      </div>`;
  }).join('');
  menu.innerHTML = `
    <div class="tm-hint">Template d'examen</div>
    ${itemsHTML}
    <div class="tm-divider"></div>
    <div class="tm-item tm-item--secondary" data-action="customize">
      <span class="tm-emoji">⚙</span>
      <div class="tm-content"><div class="tm-name">Personnaliser…</div></div>
    </div>`;
  document.body.appendChild(menu);
  menu.addEventListener('click', e => {
    const item = e.target.closest('.tm-item');
    if (!item) return;
    if (item.dataset.action === 'customize') {
      closeTemplateMenu();
      toast('Éditeur de template personnalisé (à implémenter)');
      return;
    }
    const tplId = item.dataset.tpl;
    if (item.dataset.enabled !== 'true') {
      toast(`${TEMPLATES[tplId].name} — démo non implémentée`);
      return;
    }
    closeTemplateMenu();
    if (tplId === currentTemplate) return;
    currentTemplate = tplId;
    renderTemplateLabel();
    renderList();
    toast(`Template : ${TEMPLATES[tplId].name} (${TEMPLATES[tplId].systems.length} systèmes)`);
  });
}
$('#qO-template-btn').addEventListener('click', e => {
  e.stopPropagation();
  openTemplateMenu();
});
onDocument('click', e => {
  if (!e.target.closest('.template-menu') && !e.target.closest('#qO-template-btn')) {
    closeTemplateMenu();
  }
});
onDocument('keydown', e => {
  if (e.key === 'Escape') closeTemplateMenu();
});

// ════════════════════════════════════════════════════════════
//  RENDER LIST — synthèse = note uniquement
// ════════════════════════════════════════════════════════════
function renderList() {
  const list = $('#qO-listF');
  const systems = currentSystems();
  list.innerHTML = systems.map(sys => {
    const s = state[sys.id];
    const cls = ['rowF', `is-${s.status}`];
    const note = (s.status === 'anomaly' && s.notes) ? s.notes.split(/[.\n]/)[0].trim() : '';
    const dotHTML = note ? '<span class="rowF-dot" aria-hidden="true"></span>' : '';
    const titleAttr = note ? ` title="${note.replace(/"/g, '&quot;')}"` : '';
    return `
      <div class="${cls.join(' ')}" data-sys="${sys.id}"${titleAttr}>
        <div class="rowF-icon">${ICONS[sys.icon]}</div>
        <div class="rowF-name">${sys.shortName || sys.name}${dotHTML}</div>
        <div style="flex:1"></div>
        ${stateToggleHTML(sys.id, s.status)}
      </div>`;
  }).join('');

  $$('.state-toggle').forEach(toggle => {
    const sysId = toggle.dataset.sys;
    toggle.querySelectorAll('.state-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const newState = btn.dataset.state;
        const sysName = SYSTEM_DEFS[sysId].name;
        const wasAnomaly = state[sysId].status === 'anomaly';
        if (newState === 'anomaly') {
          if (!wasAnomaly) {
            state[sysId].status = 'anomaly';
            renderList();
            toast(`${sysName} → Anomalie`);
          }
          setTimeout(() => openModal(sysId), wasAnomaly ? 0 : 100);
          return;
        }
        if (newState === state[sysId].status) return;
        state[sysId].status = newState;
        renderList();
        toast(`${sysName} → ${STATUS_LABEL[newState]}`);
      });
    });
  });

  $$('.rowF').forEach(row => {
    const sysId = row.dataset.sys;
    if (state[sysId].status !== 'anomaly') return;
    row.addEventListener('click', () => openModal(sysId));
  });
}

// ════════════════════════════════════════════════════════════
//  MODAL — drill-down complet, c'est ICI que tout vit
// ════════════════════════════════════════════════════════════
let editingSysId = null;
let workingCopy = null;

function openModal(sysId) {
  const sys = { id: sysId, ...SYSTEM_DEFS[sysId] };
  const s = state[sysId];
  editingSysId = sysId;
  workingCopy = {
    notes: s.notes || '',
    structured: { ...(s.structured || {}) },
    media: [...(s.media || [])],
  };
  $('#qO-modal-icon').innerHTML = ICONS[sys.icon];
  $('#qO-modal-title').textContent = sys.name;

  let structuredHTML = '';
  if (sys.drilldown === 'cardio') {
    structuredHTML = `
      <div class="field-block">
        <div class="field-row field-row--3">
          <div>
            <label class="field-label-mini">Fréquence (bpm)</label>
            <input class="field-input-m mono" type="number" id="f-fc" placeholder="—" value="${workingCopy.structured.fc || ''}">
          </div>
          <div>
            <label class="field-label-mini">Rythme</label>
            <select class="field-select-m" id="f-rhythm">
              <option value="">—</option>
              <option value="Régulier" ${workingCopy.structured.rhythm==='Régulier'?'selected':''}>Régulier</option>
              <option value="Arythmique" ${workingCopy.structured.rhythm==='Arythmique'?'selected':''}>Arythmique</option>
            </select>
          </div>
          <div>
            <label class="field-label-mini">Souffle (0-6)</label>
            <select class="field-select-m" id="f-murmur">
              <option value="">—</option>
              ${[0,1,2,3,4,5,6].map(n => `<option value="${n}" ${workingCopy.structured.murmur==n?'selected':''}>${n}${n===0?' / absent':n===1?' / discret':n===2?' / modéré':n===3?' / fort':n===4?' / palpable':n===5?' / +pause':' / sans stétho'}</option>`).join('')}
            </select>
          </div>
        </div>
      </div>`;
  } else if (sys.drilldown === 'loco') {
    const sd = workingCopy.structured;
    structuredHTML = `
      <div class="field-block">
        <div class="field-row field-row--3">
          <div>
            <label class="field-label-mini">Membre</label>
            <div class="limb-toggle" id="f-limb">
              ${['AG','AD','PG','PD'].map(l => `<button type="button" class="limb-btn ${sd.limb===l?'is-active':''}" data-limb="${l}">${l}</button>`).join('')}
            </div>
          </div>
          <div>
            <label class="field-label-mini">Grade (1-4)</label>
            <select class="field-select-m" id="f-grade">
              <option value="">—</option>
              <option value="1" ${sd.grade==1?'selected':''}>1 — légère, à la course</option>
              <option value="2" ${sd.grade==2?'selected':''}>2 — visible au pas</option>
              <option value="3" ${sd.grade==3?'selected':''}>3 — marquée en charge</option>
              <option value="4" ${sd.grade==4?'selected':''}>4 — refus d'appui</option>
            </select>
          </div>
          <div>
            <label class="field-label-mini">Type</label>
            <select class="field-select-m" id="f-type">
              <option value="">—</option>
              <option value="mechanical" ${sd.type==='mechanical'?'selected':''}>Mécanique</option>
              <option value="inflammatory" ${sd.type==='inflammatory'?'selected':''}>Inflammatoire</option>
              <option value="neurological" ${sd.type==='neurological'?'selected':''}>Neurologique</option>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div>
            <label class="field-label-mini">Région suspectée</label>
            <input class="field-input-m" id="f-region" placeholder="ex : grasset, hanche, épaule…" value="${sd.region || ''}">
          </div>
        </div>
      </div>`;
  } else if (sys.drilldown === 'derma') {
    const sd = workingCopy.structured;
    structuredHTML = `
      <div class="field-block">
        <div class="field-row field-row--2">
          <div>
            <label class="field-label-mini">Région</label>
            <input class="field-input-m" id="f-region" placeholder="ex : dos, oreilles, abdomen…" value="${sd.region || ''}">
          </div>
          <div>
            <label class="field-label-mini">Type de lésion</label>
            <select class="field-select-m" id="f-lesion">
              <option value="">—</option>
              ${['Érythème','Croûte','Alopécie','Pustule','Lichénification'].map(v => `<option value="${v}" ${sd.lesion===v?'selected':''}>${v}</option>`).join('')}
            </select>
          </div>
        </div>
      </div>`;
  }

  const mediaHTML = workingCopy.media.map((m, i) => `
    <span class="media-thumb">
      <svg viewBox="0 0 16 16" fill="currentColor"><polygon points="3,2 13,8 3,14"/></svg>
      ${m.name}
      <button class="media-thumb-close" type="button" data-media-i="${i}" aria-label="Retirer">
        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7"/></svg>
      </button>
    </span>`).join('');

  $('#qO-modal-body').innerHTML = `
    ${structuredHTML}
    <div class="field-block">
      <label class="field-label-mini">Observations détaillées</label>
      <textarea class="modal-textarea" id="f-notes" rows="5" placeholder="Décris ce que tu observes…">${workingCopy.notes}</textarea>
    </div>
    <div class="field-block">
      <label class="field-label-mini">Média</label>
      <div class="media-bar" id="media-bar">
        ${mediaHTML}
        <button class="media-add" type="button" id="media-add">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
          Joindre photo / vidéo
        </button>
      </div>
    </div>
  `;

  const limbToggle = $('#f-limb');
  if (limbToggle) {
    limbToggle.querySelectorAll('.limb-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        limbToggle.querySelectorAll('.limb-btn').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        workingCopy.structured.limb = btn.dataset.limb;
      });
    });
  }

  $('#media-bar').addEventListener('click', e => {
    const closeBtn = e.target.closest('.media-thumb-close');
    if (closeBtn) {
      const i = parseInt(closeBtn.dataset.mediaI);
      workingCopy.media.splice(i, 1);
      reRenderMedia();
    }
  });
  $('#media-add').addEventListener('click', () => {
    const n = workingCopy.media.length + 1;
    workingCopy.media.push({ type: 'photo', name: `IMG_${n}.jpg` });
    reRenderMedia();
  });

  function reRenderMedia() {
    const bar = $('#media-bar');
    const html = workingCopy.media.map((m, i) => `
      <span class="media-thumb">
        <svg viewBox="0 0 16 16" fill="currentColor"><polygon points="3,2 13,8 3,14"/></svg>
        ${m.name}
        <button class="media-thumb-close" type="button" data-media-i="${i}" aria-label="Retirer">
          <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 2.5l7 7M9.5 2.5l-7 7"/></svg>
        </button>
      </span>`).join('');
    bar.innerHTML = html + `
      <button class="media-add" type="button" id="media-add">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
        Joindre photo / vidéo
      </button>`;
    $('#media-add').addEventListener('click', () => {
      const n = workingCopy.media.length + 1;
      workingCopy.media.push({ type: 'photo', name: `IMG_${n}.jpg` });
      reRenderMedia();
    });
  }

  $('#qO-modal-overlay').classList.add('is-open');
  $('#f-notes').focus();
}

function saveModal() {
  if (!editingSysId) return;
  const sys = { id: editingSysId, ...SYSTEM_DEFS[editingSysId] };
  const s = state[editingSysId];
  const struct = {};
  if (sys.drilldown === 'cardio') {
    struct.fc = $('#f-fc')?.value || '';
    struct.rhythm = $('#f-rhythm')?.value || '';
    const m = $('#f-murmur')?.value;
    struct.murmur = m === '' ? null : parseInt(m);
  } else if (sys.drilldown === 'loco') {
    struct.limb = workingCopy.structured.limb || '';
    struct.grade = $('#f-grade')?.value ? parseInt($('#f-grade').value) : null;
    struct.type = $('#f-type')?.value || '';
    struct.region = $('#f-region')?.value || '';
  } else if (sys.drilldown === 'derma') {
    struct.region = $('#f-region')?.value || '';
    struct.lesion = $('#f-lesion')?.value || '';
  }
  s.structured = struct;
  s.notes = $('#f-notes')?.value || '';
  s.media = [...workingCopy.media];
  closeModal();
  renderList();
  toast(`${sys.name} — détails enregistrés`);
}

function closeModal() {
  $('#qO-modal-overlay').classList.remove('is-open');
  editingSysId = null;
  workingCopy = null;
}

$('#qO-modal-close').addEventListener('click', closeModal);
$('#qO-modal-cancel').addEventListener('click', closeModal);
$('#qO-modal-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});
$('#qO-modal-save').addEventListener('click', saveModal);
onDocument('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

$('#qO-ras-all').addEventListener('click', () => {
  currentSystems().forEach(sy => { if (state[sy.id].status !== 'anomaly') state[sy.id].status = 'normal'; });
  renderList();
  toast('Tous les systèmes restants marqués RAS');
});

renderTemplateLabel();
renderList();
// toast au démarrage retiré


})();



/* ====================================================================
 * QUAD A — Diagnostic (Quick-Add v3)
 * Source : diagnostic-redesign-v3.html — IDs préfixés qA-
 * ==================================================================== */
(function() {
'use strict';

const ICONS = {
  starOutline: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  starFilled:  '<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  sparkles:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
  stethoscope: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>',
  search:      '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>',
  close:       '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l10 10M13 3L3 13"/></svg>',
  check:       '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8l3 3 7-7"/></svg>',
};

const CERTAINTY_DEFS = {
  CERTAIN:  { label: 'Certain',  short: 'Cert.',  cssKey: 'certain' },
  PROBABLE: { label: 'Probable', short: 'Prob.', cssKey: 'probable' },
  POSSIBLE: { label: 'Possible', short: 'Poss.', cssKey: 'possible' },
  EXCLUDED: { label: 'Exclu',    short: 'Exclu',  cssKey: 'excluded' },
};
const CERTAINTY_ORDER = ['CERTAIN', 'PROBABLE', 'POSSIBLE', 'EXCLUDED'];

const SOURCE_DEFS = {
  MANUAL: 'Saisi manuellement',
  AI_SUGGESTION: 'Suggestion IA acceptée',
  TEMPLATE: 'Appliqué via template',
};

const NOMENCLATURE = [
  { code: 'M.LOC.21', name: 'Arthrose grasset, stade précoce', system: 'Locomoteur' },
  { code: 'M.LOC.18', name: 'Rupture partielle ligament croisé', system: 'Locomoteur' },
  { code: 'M.LOC.05', name: 'Dysplasie de la hanche', system: 'Locomoteur' },
  { code: 'M.LOC.30', name: 'Tendinite calcanéenne', system: 'Locomoteur' },
  { code: 'M.LOC.12', name: 'Hernie discale lombaire', system: 'Locomoteur' },
  { code: 'M.CARDIO.05', name: 'Souffle cardiaque grade 2', system: 'Cardiovasculaire' },
  { code: 'M.CARDIO.01', name: 'Insuffisance cardiaque congestive', system: 'Cardiovasculaire' },
  { code: 'M.CARDIO.14', name: 'Fibrillation atriale', system: 'Cardiovasculaire' },
  { code: 'M.DERMA.20', name: 'Dermatite par allergie aux puces (DAPP)', system: 'Cutané' },
  { code: 'M.DERMA.05', name: 'Pyodermite superficielle', system: 'Cutané' },
  { code: 'M.DERMA.11', name: 'Atopie canine', system: 'Cutané' },
  { code: 'M.GI.10', name: 'Gastro-entérite alimentaire', system: 'Digestif' },
  { code: 'M.GI.04', name: 'Pancréatite aiguë', system: 'Digestif' },
  { code: 'M.URO.04', name: 'Cystite bactérienne', system: 'Urinaire' },
  { code: 'M.URO.09', name: 'Insuffisance rénale chronique', system: 'Urinaire' },
];

let nextDxId = 1;
function uid() { return 'dx-' + (nextDxId++); }

let diagnoses = [
  { id: uid(), code: 'M.LOC.21', label: 'Arthrose grasset droit, stade précoce',
    certainty: 'PROBABLE', note: 'Race prédisposée (Beauceron) + âge 4 ans + boiterie chronique intermittente + amyotrophie quadriceps droit. À confirmer par radio.',
    isPrimary: true, source: 'MANUAL' },
  { id: uid(), code: 'M.LOC.18', label: 'Rupture partielle ligament croisé crânial',
    certainty: 'EXCLUDED', note: 'Tiroir antérieur négatif à l\'examen. Pas de signes inflammatoires aigus.',
    isPrimary: false, source: 'AI_SUGGESTION' },
  { id: uid(), code: 'M.CARDIO.05', label: 'Souffle cardiaque grade 2',
    certainty: 'POSSIBLE', note: 'Découverte fortuite à l\'auscultation. Demande échographie cardiaque pour caractériser.',
    isPrimary: false, source: 'MANUAL' },
];

function $(s, r=document) { return r.querySelector(s); }
function $$(s, r=document) { return [...r.querySelectorAll(s)]; }

function toast(msg) {
  const el = $('#qA-annotation');
  el.textContent = msg;
  el.classList.add('is-visible');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('is-visible'), 1500);
}

function sortedDx() {
  return [...diagnoses].sort((a, b) => {
    if (a.isPrimary && !b.isPrimary) return -1;
    if (!a.isPrimary && b.isPrimary) return 1;
    return 0;
  });
}

function getDxByCode(code) { return diagnoses.find(d => d.code === code); }

// ════════════════════════════════════════════════════════════
//  RENDER MAIN LIST
// ════════════════════════════════════════════════════════════
function renderList() {
  const list = $('#qA-dx-list');
  $('#qA-dx-count').textContent = diagnoses.length === 0 ? 'vide' : (diagnoses.length + ' dx');

  if (diagnoses.length === 0) {
    list.innerHTML = `
      <div class="dx-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div class="dx-empty-title">Aucun diagnostic posé</div>
        <div class="dx-empty-sub">Ajoute un dx ou demande une suggestion IA</div>
      </div>`;
    return;
  }

  list.innerHTML = sortedDx().map(dx => {
    const cert = CERTAINTY_DEFS[dx.certainty];
    const cls = ['dx-row'];
    if (dx.isPrimary) cls.push('is-primary');
    if (dx.certainty === 'EXCLUDED') cls.push('is-excluded');

    const noteHTML = dx.note
      ? `<span class="dx-sep">·</span><span class="dx-note">${dx.note.split(/[.\n]/)[0].trim()}</span>`
      : `<div style="flex:1"></div>`;

    const sourceChip = dx.source === 'AI_SUGGESTION'
      ? `<span class="dx-source-chip" title="${SOURCE_DEFS.AI_SUGGESTION}">${ICONS.sparkles} IA</span>`
      : '';

    return `
      <div class="${cls.join(' ')}" data-id="${dx.id}">
        <button class="dx-star ${dx.isPrimary?'is-on':''}" data-star="${dx.id}" aria-label="${dx.isPrimary?'Retirer principal':'Définir comme principal'}" title="${dx.isPrimary?'Diagnostic principal':'Définir comme principal'}">
          ${dx.isPrimary ? ICONS.starFilled : ICONS.starOutline}
        </button>
        <span class="dx-name-wrap">
          <span class="dx-name">${dx.label}</span>
          <span class="dx-code-tooltip">${dx.code || ''}</span>
        </span>
        ${noteHTML}
        ${sourceChip}
        <span class="dx-certainty dx-certainty--${cert.cssKey}">${cert.short}</span>
        <button class="dx-x" data-remove="${dx.id}" aria-label="Retirer">${ICONS.close}</button>
      </div>`;
  }).join('');

  $$('.dx-row').forEach(el => {
    el.addEventListener('click', e => {
      if (e.target.closest('[data-remove]') || e.target.closest('[data-star]')) return;
      openEditModal(el.dataset.id);
    });
  });
  $$('[data-star]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = btn.dataset.star;
      const dx = diagnoses.find(x => x.id === id);
      if (dx.isPrimary) {
        dx.isPrimary = false;
        toast(`${dx.label.slice(0, 30)}… retiré comme principal`);
      } else {
        diagnoses.forEach(d => d.isPrimary = false);
        dx.isPrimary = true;
        toast(`${dx.label.slice(0, 30)}… défini comme principal`);
      }
      renderList();
    });
  });
  $$('[data-remove]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = btn.dataset.remove;
      const dx = diagnoses.find(x => x.id === id);
      diagnoses = diagnoses.filter(x => x.id !== id);
      renderList();
      toast(`Retiré : ${dx.label.slice(0, 40)}${dx.label.length > 40 ? '…' : ''}`);
    });
  });
}

// ════════════════════════════════════════════════════════════
//  QUICK-ADD MODAL
// ════════════════════════════════════════════════════════════
let quickAddInitialCount = 0;

function openQuickAddModal() {
  quickAddInitialCount = diagnoses.length;

  $('#qA-modal').classList.add('modal--wide');
  $('#qA-modal-icon').className = 'modal-head-icon';
  $('#qA-modal-icon').innerHTML = ICONS.search;
  $('#qA-modal-title').textContent = 'Ajouter des diagnostics';
  $('#qA-modal-sub').textContent = 'Clic sur une certitude = ajout immédiat';
  updateQuickAddCount();

  $('#qA-modal-body').innerHTML = `
    <div class="qa-search-wrap">
      <span class="qa-search-icon">${ICONS.search}</span>
      <input type="text" class="qa-search" id="qa-search-input" placeholder="Tapez un nom ou un code (ex : arthrose, M.LOC)…" autocomplete="off">
    </div>
    <div class="qa-list" id="qa-list"></div>
  `;
  $('#qA-modal-foot').innerHTML = `
    <div class="modal-foot-hint">
      Cliquez sur le <strong>nom du dx</strong> pour ouvrir l'édition complète (note, principal). <kbd>Échap</kbd> pour fermer.
    </div>
    <button class="btn btn-primary btn-sm" data-modal-close type="button">Terminer</button>
  `;
  $('[data-modal-close]').addEventListener('click', closeModal);

  const searchInput = $('#qa-search-input');
  searchInput.addEventListener('input', renderQuickAddList);
  renderQuickAddList();

  openModal();
  setTimeout(() => searchInput.focus(), 100);
}

function updateQuickAddCount() {
  const delta = diagnoses.length - quickAddInitialCount;
  const headEl = $('#qA-modal-head-count');
  if (delta > 0) {
    headEl.style.display = 'inline-flex';
    headEl.innerHTML = `${ICONS.check} ${delta} ajouté${delta > 1 ? 's' : ''}`;
  } else {
    headEl.style.display = 'none';
  }
}

function renderQuickAddList() {
  const q = $('#qa-search-input').value.toLowerCase().trim();
  const filtered = NOMENCLATURE.filter(d =>
    d.name.toLowerCase().includes(q) || d.code.toLowerCase().includes(q)
  );

  const listEl = $('#qa-list');
  if (filtered.length === 0) {
    listEl.innerHTML = '<div class="qa-empty">Aucun résultat. Essaie un autre terme.</div>';
    return;
  }

  const grouped = filtered.reduce((acc, d) => {
    (acc[d.system] = acc[d.system] || []).push(d);
    return acc;
  }, {});

  listEl.innerHTML = Object.entries(grouped).map(([sys, items]) => `
    <div class="qa-group-label">${sys}</div>
    ${items.map(d => {
      const existing = getDxByCode(d.code);
      const isAdded = !!existing;
      const currentCert = existing?.certainty;
      const certButtons = CERTAINTY_ORDER.map(c => {
        const def = CERTAINTY_DEFS[c];
        const active = (currentCert === c) ? 'is-active' : '';
        return `<button class="cert-mini ${active}" data-cert="${c}" data-code="${d.code}" type="button">${def.short}</button>`;
      }).join('');
      return `
        <div class="qa-item ${isAdded?'is-added':''}" data-code="${d.code}">
          <span class="qa-item-code">${d.code}</span>
          <span class="qa-item-name" data-edit-code="${d.code}">${d.name}</span>
          <div class="qa-item-actions">
            ${certButtons}
            ${isAdded ? `<button class="qa-item-x" data-remove-code="${d.code}" type="button" aria-label="Retirer">${ICONS.close}</button>` : ''}
          </div>
        </div>`;
    }).join('')}
  `).join('');

  // Wire certainty mini buttons
  listEl.querySelectorAll('.cert-mini').forEach(btn => {
    btn.addEventListener('click', () => {
      const code = btn.dataset.code;
      const cert = btn.dataset.cert;
      const existing = getDxByCode(code);
      const nomEntry = NOMENCLATURE.find(d => d.code === code);

      if (existing) {
        if (existing.certainty === cert) {
          toast(`Déjà en ${CERTAINTY_DEFS[cert].label}`);
          return;
        }
        existing.certainty = cert;
        toast(`${nomEntry.name.slice(0, 35)}${nomEntry.name.length > 35 ? '…' : ''} → ${CERTAINTY_DEFS[cert].label}`);
      } else {
        diagnoses.push({
          id: uid(), code: nomEntry.code, label: nomEntry.name,
          certainty: cert, note: '', isPrimary: false, source: 'MANUAL',
        });
        toast(`+ ${nomEntry.name.slice(0, 35)}${nomEntry.name.length > 35 ? '…' : ''} en ${CERTAINTY_DEFS[cert].label}`);
      }
      renderList();
      renderQuickAddList();
      updateQuickAddCount();
    });
  });

  // Wire remove buttons
  listEl.querySelectorAll('[data-remove-code]').forEach(btn => {
    btn.addEventListener('click', () => {
      const code = btn.dataset.removeCode;
      const dx = getDxByCode(code);
      if (!dx) return;
      diagnoses = diagnoses.filter(d => d.code !== code);
      toast(`Retiré : ${dx.label.slice(0, 35)}${dx.label.length > 35 ? '…' : ''}`);
      renderList();
      renderQuickAddList();
      updateQuickAddCount();
    });
  });

  // Wire dx name click → open edit modal
  listEl.querySelectorAll('[data-edit-code]').forEach(name => {
    name.addEventListener('click', () => {
      const code = name.dataset.editCode;
      const existing = getDxByCode(code);
      const nomEntry = NOMENCLATURE.find(d => d.code === code);
      closeModal();
      setTimeout(() => {
        if (existing) {
          openEditModal(existing.id);
        } else {
          // Le dx n'est pas encore ajouté — l'ajouter en Probable puis ouvrir édition
          const newDx = {
            id: uid(), code: nomEntry.code, label: nomEntry.name,
            certainty: 'PROBABLE', note: '', isPrimary: false, source: 'MANUAL',
          };
          diagnoses.push(newDx);
          renderList();
          openEditModal(newDx.id);
        }
      }, 150);
    });
  });
}

// ════════════════════════════════════════════════════════════
//  EDIT MODAL — détailler un dx (note + primary)
// ════════════════════════════════════════════════════════════
let editingId = null;
let workingCopy = null;

function openEditModal(dxId) {
  const dx = diagnoses.find(x => x.id === dxId);
  if (!dx) return;
  workingCopy = { ...dx };
  editingId = dxId;

  $('#qA-modal').classList.remove('modal--wide');
  $('#qA-modal-icon').className = workingCopy.source === 'AI_SUGGESTION' ? 'modal-head-icon is-ai' : 'modal-head-icon';
  $('#qA-modal-icon').innerHTML = workingCopy.source === 'AI_SUGGESTION' ? ICONS.sparkles : ICONS.stethoscope;
  $('#qA-modal-title').textContent = 'Détailler le diagnostic';
  $('#qA-modal-sub').textContent = workingCopy.source === 'AI_SUGGESTION' ? SOURCE_DEFS.AI_SUGGESTION : SOURCE_DEFS.MANUAL;
  $('#qA-modal-head-count').style.display = 'none';

  $('#qA-modal-body').innerHTML = `
    <div class="field-block">
      <label class="field-label-mini">Diagnostic</label>
      <div class="dx-selected-card">
        <div class="dx-selected-content">
          <div class="dx-selected-name">${workingCopy.label}</div>
          <div class="dx-selected-code">${workingCopy.code}</div>
        </div>
      </div>
    </div>
    <div class="field-block">
      <label class="field-label-mini">Niveau de certitude</label>
      <div class="certainty-grid">
        ${CERTAINTY_ORDER.map(c => `<button class="cert-btn ${workingCopy.certainty === c ? 'is-active' : ''}" data-cert="${c}" type="button">${CERTAINTY_DEFS[c].label}</button>`).join('')}
      </div>
    </div>
    <div class="field-block">
      <label class="field-label-mini">Diagnostic principal ?</label>
      <div class="primary-toggle ${workingCopy.isPrimary ? 'is-on' : ''}" id="primary-toggle">
        <div class="primary-toggle-star">${workingCopy.isPrimary ? ICONS.starFilled : ICONS.starOutline}</div>
        <div style="flex:1">
          <div class="primary-toggle-label">${workingCopy.isPrimary ? 'Marqué comme principal' : 'Définir comme principal'}</div>
          <div class="primary-toggle-desc">Le diagnostic principal apparaît en tête du compte-rendu propriétaire.</div>
        </div>
      </div>
    </div>
    <div class="field-block">
      <label class="field-label-mini">Rationnel clinique</label>
      <textarea class="field-textarea-m" id="f-note" rows="4" placeholder="Justification, éléments en faveur / contre, examens à mener…">${workingCopy.note || ''}</textarea>
    </div>
  `;

  $$('.cert-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      workingCopy.certainty = btn.dataset.cert;
      $$('.cert-btn').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
    });
  });
  $('#primary-toggle').addEventListener('click', () => {
    workingCopy.isPrimary = !workingCopy.isPrimary;
    const t = $('#primary-toggle');
    t.classList.toggle('is-on', workingCopy.isPrimary);
    t.querySelector('.primary-toggle-star').innerHTML = workingCopy.isPrimary ? ICONS.starFilled : ICONS.starOutline;
    t.querySelector('.primary-toggle-label').textContent = workingCopy.isPrimary ? 'Marqué comme principal' : 'Définir comme principal';
  });

  $('#qA-modal-foot').innerHTML = `
    <button class="btn btn-ghost btn-sm btn-danger" data-action="delete" type="button">Supprimer</button>
    <div style="flex:1"></div>
    <button class="btn btn-ghost btn-sm" data-modal-close type="button">Annuler</button>
    <button class="btn btn-primary btn-sm" data-action="save" type="button">Enregistrer</button>
  `;
  $('[data-modal-close]').addEventListener('click', closeModal);
  $('[data-action="save"]').addEventListener('click', saveDx);
  $('[data-action="delete"]').addEventListener('click', () => {
    diagnoses = diagnoses.filter(x => x.id !== editingId);
    closeModal();
    renderList();
    toast('Diagnostic supprimé');
  });

  openModal();
  setTimeout(() => $('#f-note')?.focus(), 100);
}

function saveDx() {
  workingCopy.note = $('#f-note')?.value?.trim() || '';
  const idx = diagnoses.findIndex(x => x.id === editingId);
  diagnoses[idx] = { ...diagnoses[idx], ...workingCopy };
  if (workingCopy.isPrimary) {
    diagnoses.forEach(d => { if (d.id !== editingId) d.isPrimary = false; });
  }
  toast(`Modifié : ${workingCopy.label.slice(0, 40)}${workingCopy.label.length > 40 ? '…' : ''}`);
  closeModal();
  renderList();
}

// ════════════════════════════════════════════════════════════
//  AI MODAL
// ════════════════════════════════════════════════════════════
function openSuggestModal() {
  $('#qA-modal').classList.remove('modal--wide');
  $('#qA-modal-icon').className = 'modal-head-icon is-ai';
  $('#qA-modal-icon').innerHTML = ICONS.sparkles;
  $('#qA-modal-title').textContent = 'Suggestions diagnostiques (IA)';
  $('#qA-modal-sub').textContent = 'Basé sur le SOAP et l\'historique patient';
  $('#qA-modal-head-count').style.display = 'none';

  $('#qA-modal-body').innerHTML = `<div class="ai-loading"><span class="ai-loading-dot"></span>Analyse en cours…</div>`;
  $('#qA-modal-foot').innerHTML = `<button class="btn btn-ghost btn-sm" data-modal-close type="button">Fermer</button>`;
  $('[data-modal-close]').addEventListener('click', closeModal);
  openModal();

  setTimeout(() => {
    const suggestions = [
      { code: 'M.LOC.05', name: 'Dysplasie de la hanche', score: 78,
        reason: 'Race Beauceron prédisposée + âge + boiterie postérieure chronique + amyotrophie quadriceps.' },
      { code: 'M.LOC.30', name: 'Tendinite calcanéenne', score: 64,
        reason: 'Boiterie après effort + raideur matinale. À explorer.' },
      { code: 'M.LOC.18', name: 'Rupture partielle ligament croisé', score: 52,
        reason: 'Boiterie postérieure progressive. Tiroir antérieur à recontrôler.' },
    ];
    $('#qA-modal-body').innerHTML = suggestions.map(s => `
      <div class="ai-suggestion" data-code="${s.code}">
        <div class="ai-suggestion-content">
          <div class="ai-suggestion-name">
            <span>${s.name}</span>
            <span class="ai-suggestion-code">${s.code}</span>
          </div>
          <div class="ai-suggestion-reason">${s.reason}</div>
        </div>
        <div class="ai-score">
          <span class="ai-score-value">${s.score}%</span>
          <div class="ai-score-bar"><div class="ai-score-bar-fill" style="width:${s.score}%"></div></div>
        </div>
      </div>
    `).join('');

    $$('.ai-suggestion').forEach(el => {
      el.addEventListener('click', () => {
        const code = el.dataset.code;
        const s = suggestions.find(x => x.code === code);
        if (diagnoses.find(d => d.code === code)) {
          toast('Ce diagnostic est déjà dans la liste');
          return;
        }
        diagnoses.push({
          id: uid(), code: s.code, label: s.name,
          certainty: 'PROBABLE', note: s.reason,
          isPrimary: false, source: 'AI_SUGGESTION',
        });
        closeModal();
        renderList();
        toast(`✨ ${s.name} ajouté en Probable`);
      });
    });
  }, 700);
}

// ════════════════════════════════════════════════════════════
//  MODAL helpers
// ════════════════════════════════════════════════════════════
function openModal() { $('#qA-modal-overlay').classList.add('is-open'); }
function closeModal() {
  $('#qA-modal-overlay').classList.remove('is-open');
  $('#qA-modal').classList.remove('modal--wide');
  $('#qA-modal-head-count').style.display = 'none';
  editingId = null;
  workingCopy = null;
}

$('#qA-modal-close').addEventListener('click', closeModal);
$('#qA-modal-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});
onDocument('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

$('#qA-add-btn').addEventListener('click', openQuickAddModal);
$('#qA-suggest-btn').addEventListener('click', openSuggestModal);

renderList();
// toast au démarrage retiré


})();



/* ====================================================================
 * QUAD P — Plan (Proposition B-v2 : 2 zones asymétriques)
 * Source : plan-proposition-B-v2.html — IDs préfixés qP-
 * ==================================================================== */
(function() {
'use strict';

const ICONS = {
  act:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11.5V8a4 4 0 0 0-4-4h-4a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h4"/><path d="M14 4v4"/><path d="M10 4v4"/><path d="M18 18l4-4"/><path d="M18 14l4 4"/></svg>',
  rx:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
  apt:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  adv:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  oth:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
  add:  '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>',
  search: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>',
  check:  '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8l3 3 7-7"/></svg>',
  chevron: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>',
};

const KIND_DEFS = {
  PERFORMED_ACT: {
    cssKey: 'act', icon: 'act', label: 'Actes', singular: 'Acte',
    metaTemplate: () => 'facturé',
  },
  MEDICATION_PRESCRIPTION: {
    cssKey: 'rx', icon: 'rx', label: 'Médicaments', singular: 'Médicament',
    metaTemplate: () => 'ordonnance',
  },
  FOLLOW_UP_APPOINTMENT: {
    cssKey: 'apt', icon: 'apt', label: 'RDV', singular: 'RDV',
    metaTemplate: (item) => item.followUpDays ? `RDV J+${item.followUpDays}` : 'RDV',
  },
  ADVICE: {
    cssKey: 'adv', icon: 'adv', label: 'Conseils', singular: 'Conseil',
    metaTemplate: () => 'conseil',
  },
  OTHER: {
    cssKey: 'oth', icon: 'oth', label: 'Autre', singular: 'Autre',
    metaTemplate: () => 'autre',
  },
};

// Suggestions par kind
const SUGGESTIONS = {
  PERFORMED_ACT: [
    { code: 'A001', name: 'Vaccination CHPLR sous-cutanée', price: 28 },
    { code: 'A002', name: 'Examen général', price: 15 },
    { code: 'A003', name: 'Radio articulation (face + profil)', price: 45 },
    { code: 'A004', name: 'Castration mâle chien', price: 180 },
    { code: 'A005', name: 'Stérilisation femelle chien', price: 280 },
    { code: 'A006', name: 'Échographie abdominale', price: 80 },
    { code: 'A007', name: 'Bilan sanguin biochimie + NFS', price: 65 },
    { code: 'A008', name: 'Détartrage', price: 120 },
    { code: 'A009', name: 'Parage de plaie + antiseptique', price: 35 },
  ],
  MEDICATION_PRESCRIPTION: [
    { code: 'RX001', name: 'AINS — Méloxicam',          posology: '0,1 mg/kg · 1×/j · PO',   durationDays: 7 },
    { code: 'RX002', name: 'Antibio — Clavaseptin',     posology: '12,5 mg/kg · 2×/j · PO',  durationDays: 7 },
    { code: 'RX003', name: 'Antalgique — Tramadol',     posology: '2 mg/kg · 2×/j · PO',     durationDays: 5 },
    { code: 'RX004', name: 'Chondroprotecteur — cure',  posology: '3 mg/kg · 1×/sem · SC',   durationDays: 28 },
    { code: 'RX005', name: 'Antiparasitaire externe',   posology: '1 pipette · 1×/mois',     durationDays: 30 },
    { code: 'RX006', name: 'Vermifuge — spectre large', posology: '1 cp / 10 kg · unique',   durationDays: 1 },
  ],
  FOLLOW_UP_APPOINTMENT: [
    { code: 'APT001', name: 'Recontrôle clinique', followUpDays: 7 },
    { code: 'APT002', name: 'Recontrôle clinique', followUpDays: 14 },
    { code: 'APT003', name: 'Recontrôle clinique', followUpDays: 30 },
    { code: 'APT004', name: 'Contrôle post-op', followUpDays: 10 },
    { code: 'APT005', name: 'Retrait des points', followUpDays: 10 },
    { code: 'APT006', name: 'Recontrôle à 3 mois', followUpDays: 90 },
    { code: 'APT007', name: 'Rappel vaccin annuel', followUpDays: 365 },
  ],
  ADVICE: [
    { code: 'ADV001', name: 'Repos forcé / activité réduite' },
    { code: 'ADV002', name: 'Promenades calmes en laisse uniquement' },
    { code: 'ADV003', name: 'Pansement à changer tous les 2 jours' },
    { code: 'ADV004', name: 'Surveiller température 2×/j' },
    { code: 'ADV005', name: 'Transition alimentaire sur 7 j' },
    { code: 'ADV006', name: 'Médication à jeun ou avec repas' },
    { code: 'ADV007', name: 'Hydratation contrôlée, eau à volonté' },
    { code: 'ADV008', name: 'Collerette obligatoire 10 j' },
  ],
  OTHER: [
    { code: 'OTH001', name: 'Référer chez un spécialiste' },
    { code: 'OTH002', name: 'Demander avis confrère' },
    { code: 'OTH003', name: 'Programmer hospitalisation' },
    { code: 'OTH004', name: 'Devis chirurgie à établir' },
  ],
};

const PLAN_TEMPLATES = {
  'vacc-annuelle': {
    name: 'Vaccination annuelle (chien)', emoji: '💉', desc: 'CHPLR + examen + rappel',
    items: [
      { kind: 'PERFORMED_ACT', description: 'Vaccination CHPLR sous-cutanée' },
      { kind: 'PERFORMED_ACT', description: 'Examen général' },
      { kind: 'FOLLOW_UP_APPOINTMENT', description: 'Rappel vaccin', followUpDays: 365 },
    ],
  },
  'suivi-arthrose': {
    name: 'Suivi arthrose', emoji: '🦴', desc: 'AINS + chondroprotecteur + recontrôle',
    items: [
      { kind: 'MEDICATION_PRESCRIPTION', description: 'AINS 7 j + repos forcé 10 j', posology: 'Méloxicam 0,1 mg/kg · 1×/j · 7 j · per os', durationDays: 7 },
      { kind: 'MEDICATION_PRESCRIPTION', description: 'Chondroprotecteur cure 4 sem.', posology: 'Cartrophen 3 mg/kg · SC · 1×/sem · 4 sem', durationDays: 28 },
      { kind: 'FOLLOW_UP_APPOINTMENT', description: 'Recontrôle clinique', followUpDays: 14 },
    ],
  },
  'plaie-infectee': {
    name: 'Plaie infectée', emoji: '🩹', desc: 'Parage + ATB + antalgique',
    items: [
      { kind: 'PERFORMED_ACT', description: 'Parage de plaie + antiseptique' },
      { kind: 'MEDICATION_PRESCRIPTION', description: 'Antibiotique 7 j', posology: 'Clavaseptin · 12,5 mg/kg · 2×/j · 7 j', durationDays: 7 },
      { kind: 'MEDICATION_PRESCRIPTION', description: 'Antalgique 5 j', posology: 'Tramadol · 2 mg/kg · 2×/j · 5 j', durationDays: 5 },
      { kind: 'FOLLOW_UP_APPOINTMENT', description: 'Retrait des points', followUpDays: 10 },
    ],
  },
};

// ════════════════════════════════════════════════════════════
//  STATE
// ════════════════════════════════════════════════════════════
let nextItemId = 1;
function uid() { return 'item-' + (nextItemId++); }

let planItems = [
  { id: uid(), kind: 'PERFORMED_ACT', description: 'Vaccination CHPLR sous-cutanée', catalogCode: 'A001' },
  { id: uid(), kind: 'PERFORMED_ACT', description: 'Radio grasset D (face + profil)', catalogCode: 'A003' },
  { id: uid(), kind: 'MEDICATION_PRESCRIPTION', description: 'AINS 7 j + repos forcé 10 j', posology: 'Méloxicam 0,1 mg/kg · 1×/j · 7 j · per os', durationDays: 7 },
  { id: uid(), kind: 'FOLLOW_UP_APPOINTMENT', description: 'Recontrôle si persistance', followUpDays: 14 },
  { id: uid(), kind: 'ADVICE', description: 'Conseil nutritionnel : transition joints care' },
];

function $(s, r=document) { return r.querySelector(s); }
function $$(s, r=document) { return [...r.querySelectorAll(s)]; }

function toast(msg) {
  const el = $('#qP-annotation');
  el.textContent = msg;
  el.classList.add('is-visible');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('is-visible'), 1500);
}

function metaFor(item) { return KIND_DEFS[item.kind].metaTemplate(item); }

// Combien de fois ce catalogCode est déjà dans le plan ?
function getAddedCount(catalogCode) {
  if (!catalogCode) return 0;
  return planItems.filter(it => it.catalogCode === catalogCode).length;
}

// ════════════════════════════════════════════════════════════
//  RENDER MAIN LIST
// ════════════════════════════════════════════════════════════
function renderList() {
  const list = $('#qP-plan-list');
  $('#qP-plan-count').textContent = planItems.length === 0 ? 'vide' : (planItems.length + ' action' + (planItems.length > 1 ? 's' : ''));

  if (planItems.length === 0) {
    list.innerHTML = `
      <div class="plan-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="14" y2="14"/></svg>
        <div class="plan-empty-title">Aucune action planifiée</div>
        <div class="plan-empty-sub">Clic "Ajouter" pour ouvrir le panel multi-colonnes</div>
      </div>`;
    return;
  }

  list.innerHTML = planItems.map(item => {
    const def = KIND_DEFS[item.kind];
    return `
      <div class="plan-item plan-item--${def.cssKey}" data-id="${item.id}">
        <div class="plan-item-icon">${ICONS[def.icon]}</div>
        <div class="plan-item-desc">${item.description}</div>
        <span class="plan-item-meta">${metaFor(item)}</span>
        <button class="plan-item-x" data-remove="${item.id}" aria-label="Retirer">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l10 10M13 3L3 13"/></svg>
        </button>
      </div>`;
  }).join('');

  $$('.plan-item').forEach(el => {
    el.addEventListener('click', e => {
      if (e.target.closest('[data-remove]')) return;
      openEditModal(el.dataset.id);
    });
  });
  $$('[data-remove]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = btn.dataset.remove;
      const item = planItems.find(x => x.id === id);
      planItems = planItems.filter(x => x.id !== id);
      renderList();
      toast(`Retiré : ${item.description.slice(0, 40)}${item.description.length > 40 ? '…' : ''}`);
    });
  });
}

// ════════════════════════════════════════════════════════════
//  QUICK-ADD MODAL MULTICOL
// ════════════════════════════════════════════════════════════
let quickAddInitialCount = 0;
const KIND_ORDER = ['PERFORMED_ACT', 'MEDICATION_PRESCRIPTION', 'FOLLOW_UP_APPOINTMENT', 'ADVICE', 'OTHER'];

function openQuickAddModal() {
  quickAddInitialCount = planItems.length;

  $('#qP-modal').classList.add('modal--xwide');
  $('#qP-modal-icon').className = 'modal-head-icon is-quickadd';
  $('#qP-modal-icon').innerHTML = ICONS.search;
  $('#qP-modal-title').textContent = 'Ajouter au plan';
  $('#qP-modal-sub').textContent = 'Actes, médicaments, RDV de suivi ou conseils à ajouter au plan';
  updateQuickAddCount();

  $('#qP-modal-body').className = 'modal-body modal-body--quickadd';
  $('#qP-modal-body').innerHTML = `
    <div class="qa-search-wrap">
      <span class="qa-search-icon">${ICONS.search}</span>
      <input type="text" class="qa-search" id="qa-search-input" placeholder="Rechercher dans le catalogue (1234 actes, 567 médicaments)…" autocomplete="off">
    </div>
    <div class="qa-split">
      <div class="qa-main-area">
        <div class="qa-main-header">
          <span>Catalogue actes & médicaments</span>
          <span class="qa-main-count" id="qa-main-count">0</span>
        </div>
        <div class="qa-main-list" id="qa-main-list"></div>
      </div>
      <div class="qa-side-area" id="qa-side-area"></div>
    </div>
    <div class="qa-ft-section">
      <div class="qa-ft-label">Ajouter un item libre</div>
      <div class="qa-ft-row">
        <input type="text" class="qa-ft-input" id="qa-ft-input" placeholder="Tapez la description de l'item libre…" autocomplete="off">
        <div class="qa-ft-chips">
          <button class="kind-chip kind-chip--act" data-kind="PERFORMED_ACT" disabled type="button">${ICONS.act}<span>Acte</span></button>
          <button class="kind-chip kind-chip--rx" data-kind="MEDICATION_PRESCRIPTION" disabled type="button">${ICONS.rx}<span>Médicament</span></button>
          <div class="kind-chip kind-chip--apt kind-chip--compound is-disabled" data-kind="FOLLOW_UP_APPOINTMENT" role="button" tabindex="0" id="qa-ft-chip-apt">${ICONS.apt}<span>RDV J+</span><input type="number" class="kind-chip-num" id="qa-ft-apt-days" value="14" min="1" max="999" aria-label="Délai en jours"></div>
          <button class="kind-chip kind-chip--adv" data-kind="ADVICE" disabled type="button">${ICONS.adv}<span>Conseil</span></button>
          <button class="kind-chip kind-chip--oth" data-kind="OTHER" disabled type="button">${ICONS.oth}<span>Autre</span></button>
        </div>
      </div>
    </div>
  `;

  $('#qP-modal-foot').innerHTML = `
    <div class="modal-foot-hint">
      Clic sur ligne catalogue ou option dans un selector = ajout immédiat. Pour libre : tape + clic chip kind. <kbd>Échap</kbd> ferme.
    </div>
    <button class="btn btn-primary btn-sm" data-modal-close type="button">Terminer</button>
  `;
  $('[data-modal-close]').addEventListener('click', closeModal);

  // Search wiring
  const searchInput = $('#qa-search-input');
  searchInput.addEventListener('input', () => { renderMainList(); renderSidePresets(); });

  // Free-text wiring
  const ftInput = $('#qa-ft-input');
  const ftChips = $$('.qa-ft-chips .kind-chip');
  const aptChip = $('#qa-ft-chip-apt');
  const aptDaysInput = $('#qa-ft-apt-days');

  const updateFtState = () => {
    const hasText = !!ftInput.value.trim();
    ftChips.forEach(c => {
      if (c.classList.contains('kind-chip--compound')) {
        c.classList.toggle('is-disabled', !hasText);
      } else {
        c.disabled = !hasText;
      }
    });
  };
  ftInput.addEventListener('input', updateFtState);

  const triggerAddLibre = (kind) => {
    const text = ftInput.value.trim();
    if (!text) return;
    const item = { id: uid(), kind, description: text };
    if (kind === 'FOLLOW_UP_APPOINTMENT') {
      const days = parseInt(aptDaysInput.value) || 14;
      item.followUpDays = days;
    }
    planItems.push(item);
    ftInput.value = '';
    updateFtState();
    ftInput.focus();
    renderList();
    renderMainList();
    renderSidePresets();
    updateQuickAddCount();
    const metaLabel = item.followUpDays ? ` (J+${item.followUpDays})` : '';
    toast(`+ ${text.slice(0, 35)}${text.length > 35 ? '…' : ''} en ${KIND_DEFS[kind].label}${metaLabel}`);
  };

  ftChips.forEach(chip => {
    if (chip.classList.contains('kind-chip--compound')) return;
    chip.addEventListener('click', () => {
      if (chip.disabled) return;
      triggerAddLibre(chip.dataset.kind);
    });
  });

  aptChip.addEventListener('click', (e) => {
    if (e.target.tagName === 'INPUT') return;
    if (aptChip.classList.contains('is-disabled')) return;
    triggerAddLibre('FOLLOW_UP_APPOINTMENT');
  });
  aptDaysInput.addEventListener('click', e => e.stopPropagation());

  renderMainList();
  renderSidePresets();
  openModal();
  setTimeout(() => searchInput.focus(), 100);
}

function updateQuickAddCount() {
  const delta = planItems.length - quickAddInitialCount;
  const headEl = $('#qP-modal-head-count');
  if (delta > 0) {
    headEl.style.display = 'inline-flex';
    headEl.innerHTML = `${ICONS.check} ${delta} ajouté${delta > 1 ? 's' : ''}`;
  } else {
    headEl.style.display = 'none';
  }
}

function renderMainList() {
  const listEl = $('#qa-main-list');
  const countEl = $('#qa-main-count');
  const q = $('#qa-search-input').value.toLowerCase().trim();

  const all = [
    ...SUGGESTIONS.PERFORMED_ACT.map(s => ({ ...s, _kind: 'PERFORMED_ACT' })),
    ...SUGGESTIONS.MEDICATION_PRESCRIPTION.map(s => ({ ...s, _kind: 'MEDICATION_PRESCRIPTION' })),
  ];
  const filteredAll = !q ? all : all.filter(s =>
    s.name.toLowerCase().includes(q) ||
    (s.posology && s.posology.toLowerCase().includes(q))
  );

  // Séparer ajoutés vs non ajoutés : les ajoutés remontent toujours en haut
  const addedItems = filteredAll.filter(s => getAddedCount(s.code) > 0);
  const notAddedItems = filteredAll.filter(s => getAddedCount(s.code) === 0);

  // Adaptatif : toujours afficher minimum 5 items au total
  // Si X ajoutés < 5 → ajoutés + (5 - X) favoris
  // Si X ajoutés ≥ 5 → tous les ajoutés (pas de favoris en complément)
  const TOTAL_DISPLAY = 5;
  const remainingSlots = Math.max(0, TOTAL_DISPLAY - addedItems.length);
  const filtered = !q
    ? [...addedItems, ...notAddedItems.slice(0, remainingSlots)]
    : [...addedItems, ...notAddedItems];

  if (q) {
    countEl.innerHTML = `<strong>${filteredAll.length}</strong> résultat${filteredAll.length > 1 ? 's' : ''}`;
  } else {
    const parts = [];
    if (addedItems.length > 0) {
      parts.push(`<strong>${addedItems.length}</strong> ajouté${addedItems.length > 1 ? 's' : ''}`);
    }
    if (remainingSlots > 0) {
      parts.push(`<strong>${remainingSlots}</strong> favoris affichés`);
    }
    countEl.innerHTML = parts.join(' · ') || 'Catalogue vide';
  }

  if (filteredAll.length === 0) {
    listEl.innerHTML = `<div class="qa-list-empty">Aucun résultat dans le catalogue.<br>↓ Tape ta description et clique le chip Acte ou Médicament pour créer un item libre.</div>`;
    return;
  }

  // Render rows
  const rowsHtml = filtered.map((s, idx) => {
    const def = KIND_DEFS[s._kind];
    let meta = '';
    if (s.price != null) meta = `${s.price.toFixed(2).replace('.', ',')} €`;
    else if (s.posology) {
      meta = s.posology;
      if (s.durationDays) meta += `  ·  ${s.durationDays} j`;
    }

    const addedCount = getAddedCount(s.code);
    const isAdded = addedCount > 0;
    const addedClass = isAdded ? 'is-added' : '';

    // Séparateur visuel : juste après le dernier item ajouté (si y'a des non ajoutés en dessous)
    const isLastAdded = isAdded && idx === addedItems.length - 1 && addedItems.length < filtered.length;
    const sepClass = isLastAdded ? 'qa-row--last-added' : '';

    // Zone droite : selon état
    let rightHtml;
    if (isAdded) {
      rightHtml = `
        <span class="qa-row-add" title="Cliquer la ligne pour ajouter encore">${addedCount > 1 ? `<strong>×${addedCount}</strong>` : ICONS.check}</span>
        <button class="qa-row-remove" data-remove-code="${s.code}" type="button" title="Retirer le dernier ajout" aria-label="Retirer">×</button>
      `;
    } else {
      rightHtml = `<span class="qa-row-add">${ICONS.add}</span>`;
    }

    return `
      <div class="qa-row qa-row--${def.cssKey} ${addedClass} ${sepClass}" data-suggestion="${s.code}" data-kind="${s._kind}">
        <div class="qa-row-icon">${ICONS[def.icon]}</div>
        <div class="qa-row-content">
          <div class="qa-row-name">${s.name}</div>
          ${meta ? `<div class="qa-row-meta">${meta}</div>` : ''}
        </div>
        ${rightHtml}
      </div>`;
  }).join('');

  listEl.innerHTML = rowsHtml;

  // Hint si on n'affiche pas tout le catalogue
  const hiddenFavorites = notAddedItems.length - remainingSlots;
  if (!q && hiddenFavorites > 0) {
    listEl.innerHTML += `<div class="qa-list-hint">↑ Recherchez pour accéder à vos <strong>${hiddenFavorites}</strong> autres favoris et à tout le catalogue</div>`;
  }

  listEl.querySelectorAll('.qa-row').forEach(row => {
    row.addEventListener('click', () => {
      addFromSuggestion(row.dataset.kind, row.dataset.suggestion);
    });
  });

  // Wire remove buttons sur les rows ajoutées du catalogue
  listEl.querySelectorAll('.qa-row-remove').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const code = btn.dataset.removeCode;
      // Retire le dernier item ajouté avec ce code
      for (let i = planItems.length - 1; i >= 0; i--) {
        if (planItems[i].catalogCode === code) {
          const removed = planItems.splice(i, 1)[0];
          renderList();
          renderMainList();
          renderSidePresets();
          updateQuickAddCount();
          toast(`Retiré : ${removed.description.slice(0, 40)}${removed.description.length > 40 ? '…' : ''}`);
          return;
        }
      }
    });
  });
}

function renderSidePresets() {
  const sideEl = $('#qa-side-area');
  const sideKinds = ['FOLLOW_UP_APPOINTMENT', 'ADVICE', 'OTHER'];

  // Préserver l'état ouvert
  const openKinds = new Set();
  sideEl.querySelectorAll('.qa-selector.is-open').forEach(s => openKinds.add(s.dataset.kind));

  sideEl.innerHTML = `
    <div class="qa-side-label">Suivi &amp; recommandations</div>
    ${sideKinds.map(kind => {
      const def = KIND_DEFS[kind];
      const all = SUGGESTIONS[kind] || [];
      const isOpen = openKinds.has(kind);
      const addedItemsForKind = planItems.filter(item => item.kind === kind);
      const addedCount = addedItemsForKind.length;

      const baseLabel = kind === 'FOLLOW_UP_APPOINTMENT' ? 'Choisir un RDV de suivi'
                       : kind === 'ADVICE' ? 'Choisir un conseil'
                       : 'Autre action';

      const chipsContainerClass = kind === 'FOLLOW_UP_APPOINTMENT' ? 'qa-selected-chips qa-selected-chips--cols-2' : 'qa-selected-chips';
      const chipsHtml = addedCount === 0 ? '' : `
        <div class="${chipsContainerClass}">
          ${addedItemsForKind.map(item => {
            let label = item.description;
            if (kind === 'FOLLOW_UP_APPOINTMENT' && item.followUpDays) {
              const isStandardRecontrole = item.description.toLowerCase().includes('recontrôle');
              label = isStandardRecontrole
                ? `J+${item.followUpDays}`
                : `${item.description}  ·  J+${item.followUpDays}`;
            }
            return `
              <div class="qa-selected-chip qa-selected-chip--${def.cssKey}">
                <span class="qa-selected-chip-label" title="${item.description}${item.followUpDays ? ' · J+' + item.followUpDays : ''}">${label}</span>
                <button class="qa-selected-chip-remove" data-remove-id="${item.id}" type="button" title="Retirer">×</button>
              </div>`;
          }).join('')}
        </div>
      `;

      return `
        <div class="qa-selector-block">
          <div class="qa-selector qa-selector--${def.cssKey} ${isOpen ? 'is-open' : ''}" data-kind="${kind}">
            <button class="qa-selector-trigger" type="button">
              <span class="qa-selector-trigger-left">
                <span class="qa-selector-trigger-icon">${ICONS[def.icon]}</span>
                <span class="qa-selector-trigger-label">${baseLabel}</span>
              </span>
              <span class="qa-selector-trigger-meta">${addedCount > 0 ? `<strong>${addedCount}</strong> ajouté${addedCount > 1 ? 's' : ''} · ${all.length}` : `<strong>${all.length}</strong> preset${all.length > 1 ? 's' : ''}`}</span>
              <span class="qa-selector-chevron">${ICONS.chevron}</span>
            </button>
            <div class="qa-selector-menu">
              ${all.length === 0
                ? `<div class="qa-selector-empty">Aucun preset</div>`
                : all.map(s => {
                    const isAdded = getAddedCount(s.code) > 0;
                    let label = s.name;
                    if (s.followUpDays) label = `${s.name}  ·  J+${s.followUpDays}`;
                    return `
                      <div class="qa-selector-option ${isAdded ? 'is-added' : ''}" data-suggestion="${s.code}" data-kind="${kind}">
                        <span>${label}</span>
                        <span class="qa-selector-option-check">${ICONS.check}</span>
                      </div>`;
                  }).join('')}
            </div>
          </div>
          ${chipsHtml}
        </div>`;
    }).join('')}
  `;

  // Wire selectors
  sideEl.querySelectorAll('.qa-selector').forEach(sel => {
    const trigger = sel.querySelector('.qa-selector-trigger');
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      sideEl.querySelectorAll('.qa-selector.is-open').forEach(s => {
        if (s !== sel) s.classList.remove('is-open');
      });
      sel.classList.toggle('is-open');
    });

    sel.querySelectorAll('.qa-selector-option').forEach(opt => {
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        addFromSuggestion(opt.dataset.kind, opt.dataset.suggestion);
      });
    });
  });

  // Wire remove sur chips ajoutés
  sideEl.querySelectorAll('.qa-selected-chip-remove').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const itemId = btn.dataset.removeId;
      const idx = planItems.findIndex(i => i.id === itemId);
      if (idx >= 0) {
        const removed = planItems[idx];
        planItems.splice(idx, 1);
        renderList();
        renderMainList();
        renderSidePresets();
        updateQuickAddCount();
        toast(`Retiré : ${removed.description.slice(0, 40)}${removed.description.length > 40 ? '…' : ''}`);
      }
    });
  });
}

// Close any open selector on outside click (removed again by cleanup())
onDocument('click', (e) => {
  if (!e.target.closest('.qa-selector')) {
    document.querySelectorAll('.qa-selector.is-open').forEach(s => s.classList.remove('is-open'));
  }
});

function addFromSuggestion(kind, code) {
  const s = (SUGGESTIONS[kind] || []).find(x => x.code === code);
  if (!s) return;

  const item = { id: uid(), kind, description: s.name, catalogCode: s.code };
  if (s.posology) item.posology = s.posology;
  if (s.durationDays) item.durationDays = s.durationDays;
  if (s.followUpDays) item.followUpDays = s.followUpDays;
  if (s.price != null) item.price = s.price;

  planItems.push(item);
  renderList();
  if ($('#qa-main-list')) renderMainList();
  if ($('#qa-side-area')) renderSidePresets();
  updateQuickAddCount();
  const metaLabel = item.followUpDays ? ` (J+${item.followUpDays})` : '';
  toast(`+ ${s.name.slice(0, 35)}${s.name.length > 35 ? '…' : ''}${metaLabel}`);
}

// ════════════════════════════════════════════════════════════
//  EDIT MODAL
// ════════════════════════════════════════════════════════════
let editingId = null;
let workingCopy = null;

function openEditModal(itemId) {
  const current = planItems.find(x => x.id === itemId);
  if (!current) return;
  workingCopy = { ...current };
  editingId = itemId;

  const def = KIND_DEFS[workingCopy.kind];
  $('#qP-modal').classList.remove('modal--wide');
  $('#qP-modal').classList.remove('modal--xwide');
  $('#qP-modal-icon').className = `modal-head-icon is-${def.cssKey}`;
  $('#qP-modal-icon').innerHTML = ICONS[def.icon];
  $('#qP-modal-title').textContent = `Modifier · ${def.singular}`;
  $('#qP-modal-sub').textContent = 'Détail de l\'action';
  $('#qP-modal-head-count').style.display = 'none';
  $('#qP-modal-body').className = 'modal-body';

  let bodyHTML = '';
  if (workingCopy.kind === 'PERFORMED_ACT') {
    bodyHTML = `
      <div class="field-block">
        <label class="field-label-mini">Description de l'acte</label>
        <input class="field-input-m" id="f-desc" value="${workingCopy.description || ''}" autocomplete="off">
      </div>
      <div style="padding:var(--space-3);background:var(--color-success-bg);border:1px solid var(--color-success-border);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--color-success-text);line-height:1.5">
        ℹ Cet acte génèrera une ligne sur la facture à la clôture.
      </div>`;
  } else if (workingCopy.kind === 'MEDICATION_PRESCRIPTION') {
    bodyHTML = `
      <div class="field-block">
        <label class="field-label-mini">Description / molécule</label>
        <input class="field-input-m" id="f-desc" value="${workingCopy.description || ''}" autocomplete="off">
      </div>
      <div class="field-block">
        <label class="field-label-mini">Posologie</label>
        <input class="field-input-m mono" id="f-posology" placeholder="ex : 0,1 mg/kg · 1×/j · per os" value="${workingCopy.posology || ''}" autocomplete="off">
      </div>
      <div class="field-block">
        <div class="field-row field-row--2">
          <div>
            <label class="field-label-mini">Durée (jours)</label>
            <input class="field-input-m mono" type="number" id="f-duration" value="${workingCopy.durationDays || ''}">
          </div>
          <div>
            <label class="field-label-mini">Renouvellements</label>
            <input class="field-input-m mono" type="number" id="f-refills" value="${workingCopy.refills || ''}">
          </div>
        </div>
      </div>`;
  } else if (workingCopy.kind === 'FOLLOW_UP_APPOINTMENT') {
    bodyHTML = `
      <div class="field-block">
        <label class="field-label-mini">Motif du recontrôle</label>
        <input class="field-input-m" id="f-desc" value="${workingCopy.description || ''}" autocomplete="off">
      </div>
      <div class="field-block">
        <label class="field-label-mini">Délai</label>
        <div class="field-row field-row--2">
          <select class="field-select-m" id="f-followup-preset">
            <option value="">— préréglage —</option>
            <option value="7">7 jours</option>
            <option value="14">14 jours (2 sem.)</option>
            <option value="21">21 jours (3 sem.)</option>
            <option value="30">30 jours (1 mois)</option>
            <option value="90">90 jours (3 mois)</option>
            <option value="180">180 jours (6 mois)</option>
            <option value="365">365 jours (1 an)</option>
          </select>
          <div style="display:flex;align-items:center;gap:var(--space-2)">
            <span class="mono" style="font-size:var(--text-sm);color:var(--text-muted)">J +</span>
            <input class="field-input-m mono" type="number" id="f-followup-days" value="${workingCopy.followUpDays || ''}" style="flex:1">
          </div>
        </div>
      </div>`;
  } else if (workingCopy.kind === 'ADVICE') {
    bodyHTML = `
      <div class="field-block">
        <label class="field-label-mini">Conseil au propriétaire</label>
        <textarea class="field-textarea-m" id="f-desc" rows="3">${workingCopy.description || ''}</textarea>
      </div>`;
  } else {
    bodyHTML = `
      <div class="field-block">
        <label class="field-label-mini">Description</label>
        <textarea class="field-textarea-m" id="f-desc" rows="3">${workingCopy.description || ''}</textarea>
      </div>`;
  }

  $('#qP-modal-body').innerHTML = bodyHTML;

  $('#qP-modal-foot').innerHTML = `
    <button class="btn btn-ghost btn-sm btn-danger" data-action="delete" type="button">Supprimer</button>
    <div style="flex:1"></div>
    <button class="btn btn-ghost btn-sm" data-modal-close type="button">Annuler</button>
    <button class="btn btn-primary btn-sm" data-action="save" type="button">Enregistrer</button>
  `;
  $('[data-modal-close]').addEventListener('click', closeModal);
  $('[data-action="save"]').addEventListener('click', saveItem);
  $('[data-action="delete"]').addEventListener('click', () => {
    planItems = planItems.filter(x => x.id !== editingId);
    closeModal();
    renderList();
    toast('Item supprimé');
  });

  if (workingCopy.kind === 'FOLLOW_UP_APPOINTMENT') {
    $('#f-followup-preset').addEventListener('change', e => {
      if (e.target.value) $('#f-followup-days').value = e.target.value;
    });
  }

  openModal();
  setTimeout(() => $('#f-desc')?.focus(), 100);
}

function saveItem() {
  const desc = $('#f-desc')?.value?.trim();
  if (!desc) { toast('La description est requise'); return; }
  const item = { ...workingCopy, description: desc };
  if (workingCopy.kind === 'MEDICATION_PRESCRIPTION') {
    item.posology = $('#f-posology')?.value?.trim() || '';
    item.durationDays = parseInt($('#f-duration')?.value) || null;
    item.refills = parseInt($('#f-refills')?.value) || null;
  }
  if (workingCopy.kind === 'FOLLOW_UP_APPOINTMENT') {
    item.followUpDays = parseInt($('#f-followup-days')?.value) || null;
  }
  const idx = planItems.findIndex(x => x.id === editingId);
  planItems[idx] = item;
  toast(`Modifié : ${desc.slice(0, 40)}${desc.length > 40 ? '…' : ''}`);
  closeModal();
  renderList();
}

// ════════════════════════════════════════════════════════════
//  MODAL helpers
// ════════════════════════════════════════════════════════════
function openModal() { $('#qP-modal-overlay').classList.add('is-open'); }
function closeModal() {
  $('#qP-modal-overlay').classList.remove('is-open');
  $('#qP-modal').classList.remove('modal--wide');
  $('#qP-modal').classList.remove('modal--xwide');
  $('#qP-modal-head-count').style.display = 'none';
  $('#qP-modal-body').className = 'modal-body';
  editingId = null;
  workingCopy = null;
}

$('#qP-modal-close').addEventListener('click', closeModal);
$('#qP-modal-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});
onDocument('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeTemplateMenu(); }
});

// ════════════════════════════════════════════════════════════
//  TEMPLATE menu
// ════════════════════════════════════════════════════════════
function closeTemplateMenu() {
  const m = document.querySelector('.template-menu');
  if (m) m.remove();
  $('#qP-template-btn').classList.remove('is-open');
}
function openTemplateMenu() {
  const btn = $('#qP-template-btn');
  if (btn.classList.contains('is-open')) { closeTemplateMenu(); return; }
  btn.classList.add('is-open');
  const rect = btn.getBoundingClientRect();
  const menu = document.createElement('div');
  menu.className = 'template-menu';
  menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
  menu.style.right = (window.innerWidth - rect.right) + 'px';
  menu.innerHTML = `
    <div class="tm-hint">Appliquer un template</div>
    ${Object.entries(PLAN_TEMPLATES).map(([id, tpl]) => `
      <div class="tm-item" data-tpl="${id}">
        <span class="tm-emoji">${tpl.emoji}</span>
        <div class="tm-content">
          <div class="tm-name">${tpl.name}</div>
          <div class="tm-desc">${tpl.desc}</div>
          <div class="tm-meta">${tpl.items.length} actions</div>
        </div>
      </div>`).join('')}
  `;
  document.body.appendChild(menu);
  menu.addEventListener('click', e => {
    const item = e.target.closest('[data-tpl]');
    if (!item) return;
    const tplId = item.dataset.tpl;
    const tpl = PLAN_TEMPLATES[tplId];
    closeTemplateMenu();
    tpl.items.forEach(it => planItems.push({ id: uid(), ...it }));
    renderList();
    toast(`Template "${tpl.name}" appliqué (+${tpl.items.length} actions)`);
  });
}

$('#qP-template-btn').addEventListener('click', e => { e.stopPropagation(); openTemplateMenu(); });
onDocument('click', e => {
  if (!e.target.closest('.template-menu') && !e.target.closest('#qP-template-btn')) closeTemplateMenu();
});

$('#qP-add-btn').addEventListener('click', openQuickAddModal);

renderList();
// toast au démarrage retiré


})();



/* ====================================================================
 * Modal template auto-open : le Modal du template original crée des
 * .modal-overlay dynamiquement SANS ajouter `is-open`. Avec notre CSS
 * design system qui requiert `is-open` pour la visibilité, ces modales
 * resteraient invisibles. On observe les ajouts au body pour les activer.
 * ==================================================================== */
(function() {
  'use strict';
  if (typeof MutationObserver === 'undefined') return;
  const observer = new MutationObserver(mutations => {
    for (const m of mutations) {
      for (const node of m.addedNodes) {
        if (node.nodeType !== 1) continue;
        if (node.classList && node.classList.contains('modal-overlay') && !node.id) {
          requestAnimationFrame(() => node.classList.add('is-open'));
        }
      }
    }
  });
  observer.observe(document.body, { childList: true });
  registerCleanup(() => observer.disconnect());
})();








}

export function cleanup() {
  _mounted = false;
  // Remove document-level listeners, intervals and observers registered
  // during init(); element-bound listeners die with the body swap.
  _cleanups.splice(0).forEach(fn => {
    try { fn(); } catch { /* never block teardown */ }
  });
}
