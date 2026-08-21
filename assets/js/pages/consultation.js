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
        if (value === undefined || value === null) return;
        if (Array.isArray(value)) {
          value.forEach(entry => body.append(`${key}[]`, entry));
          return;
        }
        if (typeof value === 'object') {
          Object.entries(value).forEach(([subKey, subValue]) => {
            if (subValue !== undefined && subValue !== null) body.append(`${key}[${subKey}]`, subValue);
          });
          return;
        }
        body.append(key, value);
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
    // Domain messages are English by project convention, so the UI maps the
    // error code to French copy instead of echoing them.
    const ERROR_COPY = {
      CSRF_INVALID: 'Session expirée — rechargez la page.',
      CONFLICT:     'Modification concurrente — réessayez.',
      DOMAIN_ERROR: 'Modification refusée dans l\'état actuel de la consultation.',
      VALIDATION:   'Valeur invalide — vérifiez la saisie.',
    };

    // `data` may be a builder: a queued mutation deriving its payload from the
    // current state must read it AFTER the previous one landed.
    function mutate(url, data) {
      const run = async () => {
        if (!url) return { success: false, errorCode: 'NO_ENDPOINT' };
        const payload = typeof data === 'function' ? data() : data;
        SaveState.set('saving');
        try {
          let result = await send(url, payload);
          if (result.status === 409 && result.payload?.errorCode === 'CONFLICT') {
            result = await send(url, payload);
          }
          if (result.payload?.success) {
            SaveState.set('saved');
            return result.payload;
          }
          SaveState.set('error');
          Toast.error(ERROR_COPY[result.payload?.errorCode] || 'Erreur lors de la sauvegarde');
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
    async function get(url, params) {
      try {
        const query = new URLSearchParams(params || {});
        const response = await fetch(`${url}?${query.toString()}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        return await response.json();
      } catch {
        return null;
      }
    }

    return { mutate, get };
  })();

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
    const consultation = HYDRATION.consultation || {};
    const startedAt = parseUtcDate(consultation.startedAtUtc);
    if (!timerEl || !startedAt) return;
    const closedAt = parseUtcDate(consultation.closedAtUtc);
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

  // ═══════════════════════════════════════════════
  //  SERVER STATE
  //  Single source of truth for the whole page: the hydration payload,
  //  replaced wholesale by the `consultation` object every mutation returns.
  //  Each block registers a renderer so one save repaints everything.
  // ═══════════════════════════════════════════════
  const REF = HYDRATION.reference || {};
  const EP = HYDRATION.endpoints || {};
  const PATIENT = HYDRATION.patient || {};
  const OWNER = HYDRATION.owner || null;
  const HISTORY = HYDRATION.history || [];

  let SERVER = HYDRATION.consultation || {};
  const serverRenderers = [];

  function onServerState(fn) {
    serverRenderers.push(fn);
  }

  function renderFromServer() {
    serverRenderers.forEach(fn => fn());
  }

  function isClosed() {
    return SERVER.isClosed === true;
  }

  /**
   * Every mutation goes through here: refused on a closed consultation (the
   * server refuses it too), queued, then the refreshed state is applied.
   */
  function save(url, data) {
    if (isClosed()) {
      Toast.warn('Consultation clôturée — lecture seule');
      return Promise.resolve({ success: false, errorCode: 'CLOSED' });
    }
    return api.mutate(url, data).then(payload => {
      if (payload?.success && payload.consultation) {
        SERVER = payload.consultation;
        renderFromServer();
      }
      return payload;
    });
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function money(minorUnits, currency) {
    const symbol = { EUR: '€', CHF: 'CHF', USD: '$', GBP: '£' }[currency || 'EUR'] || (currency || '');
    const negative = minorUnits < 0;
    const absolute = Math.abs(Number(minorUnits) || 0);
    const int = String(Math.trunc(absolute / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f');
    return `${negative ? '-' : ''}${int},${String(absolute % 100).padStart(2, '0')}\u00a0${symbol}`;
  }

  function frNumber(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value ?? '');
    return String(Number(n.toFixed(3))).replace('.', ',');
  }

  // Catalogue rows are fetched once per modal opening and filtered client-side,
  // so the search feels instant exactly as the prototype did.
  const Catalog = (() => {
    let acts = [];
    let articles = [];

    async function load(term = '') {
      const payload = await api.get(EP.catalogSearch, { term });
      const items = payload?.items || [];
      acts = items.filter(i => i.itemType === 'ACT');
      articles = items.filter(i => i.itemType === 'ARTICLE');
      return { acts, articles };
    }

    return {
      load,
      get acts() { return acts; },
      get articles() { return articles; },
      find(code) { return [...acts, ...articles].find(i => i.code === code) || null; },
    };
  })();

(() => {
  'use strict';

  // ═══════════════════════════════════════════════
  //  STATE — patient identity, from the server
  // ═══════════════════════════════════════════════
  const state = {
    patient: {
      name: PATIENT.name || 'Patient',
      species: PATIENT.speciesLabel || '',
      breed: PATIENT.breed || '',
      age: PATIENT.ageLabel || '',
      sex: PATIENT.sexLabel || '',
      microchip: PATIENT.microchip || '',
      color: PATIENT.color || '',
      isIdentified: PATIENT.isIdentified === true,
      get weight() {
        return SERVER.vitals?.weightKg !== undefined && SERVER.vitals?.weightKg !== null
          ? Number(SERVER.vitals.weightKg)
          : null;
      },
      get lastWeight() {
        const previous = HISTORY.find(row => row.weightKg !== null && row.weightKg !== undefined);
        return previous ? Number(previous.weightKg) : null;
      },
      get allergies() {
        return (SERVER.medicalAlerts || [])
          .filter(alert => alert.kind === 'ALLERGY')
          .map(alert => alert.label);
      },
      get conditions() {
        return (SERVER.medicalAlerts || [])
          .filter(alert => alert.kind === 'CHRONIC_CONDITION')
          .map(alert => alert.label);
      },
    },
  };

  // ═══════════════════════════════════════════════
  //  DATA — reference lists served by the server
  // ═══════════════════════════════════════════════

  // Same shape as the former mock so every modal body below is unchanged.
  const VITAL_TYPES = (REF.vitalTypes || []).map(v => ({
    id: v.id, label: v.label, unit: v.unit, range: v.range, def: v.default,
  }));

  const PRESET_MOTIFS = REF.presetMotifs || [];

  const HISTORY_DATE_FMT = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });

  function fmtHistoryDate(value) {
    const date = parseUtcDate(value);
    return date ? HISTORY_DATE_FMT.format(date) : '';
  }

  const PAST_CONSULTATIONS = HISTORY.map(row => ({
    date: fmtHistoryDate(row.startedAtUtc),
    motif: row.motif || 'Consultation',
    summary: row.summary || '',
    url: row.url,
    startedAtUtc: row.startedAtUtc,
    weightKg: row.weightKg,
  }));

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
      // The modal may already be closed when this fires.
      setTimeout(() => {
        const f = mount?.querySelector('input,textarea');
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
  // HT / TVA / TTC come from the Taxation-resolved totals computed server-side.
  function recomputeBillTotal() {
    const totals = SERVER.totals || {};
    const currency = totals.currency || 'EUR';
    const totalEl = document.querySelector('.bill-total-amount');
    const subEl = document.querySelector('.bill-total div div:nth-child(2)');
    if (totalEl) totalEl.textContent = money(totals.totalTtcMinorUnits || 0, currency);
    if (subEl) {
      subEl.textContent = `HT ${money(totals.totalHtMinorUnits || 0, currency)} · TVA ${money(totals.totalTvaMinorUnits || 0, currency)}`;
    }
  }
  function highlightNew(el) {
    el.classList.add('is-just-added');
    setTimeout(() => el.classList.remove('is-just-added'), 1500);
  }

  // ═══════════════════════════════════════════════
  //  RENDERERS — same markup as the layout, fed by the server
  // ═══════════════════════════════════════════════

  // Chips are inserted before the "+ ajouter" chip so they stay direct flex
  // children of .strip-content, exactly as in the layout.
  function renderMotifStrip() {
    const strip = document.querySelector('.strip:first-child .strip-content');
    const addChip = strip?.querySelector('.chip-add');
    if (!strip || !addChip) return;

    strip.querySelectorAll('.chip:not(.chip-add)').forEach(chip => chip.remove());
    addChip.insertAdjacentHTML('beforebegin', (SERVER.motifs || []).map(motif =>
      `<button class="chip is-selected" data-motif-id="${escapeHtml(motif.id)}">${escapeHtml(motif.label)}</button>`).join(''));
    strip.querySelectorAll('.chip:not(.chip-add)').forEach(wireMotifChip);
  }

  function renderVitalsStrip() {
    const strip = document.querySelector('.vitals-strip');
    const addChip = strip?.querySelector('.chip-add');
    if (!strip || !addChip) return;

    strip.querySelectorAll('.vital-pill').forEach(pill => pill.remove());

    const pills = [];
    const weight = SERVER.vitals?.weightKg;
    const temperature = SERVER.vitals?.temperatureC;

    pills.push(weightPillHtml(weight));

    if (temperature !== null && temperature !== undefined) {
      pills.push(vitalPillHtml('temperature', 'Température', frNumber(temperature), '°C'));
    }

    (SERVER.typedVitals || []).forEach(vital => {
      const def = VITAL_TYPES.find(v => v.id === vital.type);
      pills.push(vitalPillHtml(vital.type, def?.label || vital.type, vital.value, def?.unit || ''));
    });

    addChip.insertAdjacentHTML('beforebegin', pills.join(''));
    strip.querySelectorAll('.vital-pill').forEach(wireVitalPill);

    const hint = strip.querySelector('.strip-text');
    if (hint) {
      const recorded = new Set((SERVER.typedVitals || []).map(v => v.type));
      const remaining = VITAL_TYPES.filter(v => !recorded.has(v.id)).map(v => v.label);
      hint.textContent = remaining.length ? remaining.join(' · ') : 'Toutes les constantes sont renseignées';
    }
  }

  function weightPillHtml(weight) {
    const value = (weight === null || weight === undefined) ? '—' : frNumber(weight);
    const last = state.patient.lastWeight;
    let delta = '';
    if (weight !== null && weight !== undefined && last !== null) {
      const diff = Number(weight) - last;
      if (Math.abs(diff) >= 0.05) {
        delta = `<span class="vital-pill-delta ${diff < 0 ? 'is-down' : 'is-up'}">${diff < 0 ? '−' : '+'}${frNumber(Math.abs(diff))} kg</span>`;
      }
    }
    return `
      <div class="vital-pill" data-vital="weight">
        <div class="vital-pill-label">Poids</div>
        <div class="vital-pill-value">${escapeHtml(value)} <span class="unit">kg</span></div>
        ${delta}
      </div>`;
  }

  function vitalPillHtml(id, label, value, unit) {
    const removable = id !== 'weight' && id !== 'temperature';
    const valueDisplay = unit
      ? `${escapeHtml(value)} <span class="unit">${escapeHtml(unit)}</span>`
      : `<span style="font-size:var(--text-sm)">${escapeHtml(value)}</span>`;
    return `
      <div class="vital-pill" data-vital="${escapeHtml(id)}">
        <div class="vital-pill-label">${escapeHtml(label)}</div>
        <div class="vital-pill-value">${valueDisplay}</div>
        ${removable ? `<button class="row-x" title="Retirer cette constante" aria-label="Retirer ${escapeHtml(label)}">
          <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
        </button>` : ''}
      </div>`;
  }

  function renderPrescription() {
    const list = document.querySelector('.rp-block .rx-list');
    if (!list) return;
    const lines = SERVER.prescriptionLines || [];

    list.innerHTML = lines.map(line => `
        <div class="rx-item has-row-x" data-line-id="${escapeHtml(line.id)}" data-code="${escapeHtml(line.code || '')}">
          <div class="rx-item-row">
            <span class="rx-name">${escapeHtml(line.label)}</span>
            <span class="rx-price">${escapeHtml(money(line.unitPriceMinorUnits, line.currency))}</span>
            <button class="row-x" title="Retirer ce médicament" aria-label="Retirer ce médicament">
              <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
            </button>
          </div>
          <div class="rx-poso">${escapeHtml(posologyLabel(line))}</div>
        </div>`).join('');

    list.querySelectorAll('.rx-item').forEach(item => wireRxItem(item));

    const titleEl = document.querySelector('.rp-block .rp-block-title');
    if (titleEl) {
      titleEl.textContent = lines.length === 0
        ? 'Ordonnance'
        : `Ordonnance · ${lines.length} médicament${lines.length > 1 ? 's' : ''}`;
    }

    renderAllergyBanner();
  }

  function posologyLabel(line) {
    return [
      line.dose,
      line.frequency,
      line.durationDays ? `${line.durationDays} ${line.durationDays > 1 ? 'jours' : 'jour'}` : null,
      line.route,
    ].filter(Boolean).join(' · ') || '—';
  }

  function renderAllergyBanner() {
    const banner = document.querySelector('.rx-warning');
    if (!banner) return;
    const allergies = state.patient.allergies;
    const conflicts = SERVER.allergyWarnings || [];

    if (allergies.length === 0) {
      banner.style.display = 'none';
      return;
    }

    banner.style.display = '';
    const icon = '<svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>';

    if (conflicts.length === 0) {
      banner.innerHTML = `${icon}
        <span><strong style="font-weight:var(--weight-medium)">${escapeHtml(allergies.join(', '))} allergique</strong> — molécules incompatibles filtrées du catalogue.</span>`;
      return;
    }

    const detail = [...new Set(conflicts.map(c => `${c.medication} (${c.substance})`))].join(', ');
    banner.innerHTML = `${icon}
      <span><strong style="font-weight:var(--weight-medium)">${escapeHtml(conflicts[0].alertLabel)} allergique</strong> — vérifiez ${escapeHtml(detail)}.</span>`;
  }

  function renderBill() {
    const billList = document.querySelector('.bill-list');
    if (!billList) return;

    billList.innerHTML = (SERVER.billingLines || []).map(line => `
        <div class="bill-row" data-code="${escapeHtml(line.code || '')}">
          <div class="bill-name">
            ${escapeHtml(line.label)}
            <span class="bill-qty">${escapeHtml(line.code || line.source)}${Number(line.quantity) !== 1 ? ` · ×${frNumber(line.quantity)}` : ''}</span>
          </div>
          <div class="bill-price">${escapeHtml(money(line.totalMinorUnits, line.currency))}</div>
        </div>`).join('');

    recomputeBillTotal();
  }

  function renderTeamMemo() {
    const memo = document.getElementById('team-memo');
    if (memo && memo !== document.activeElement) memo.value = SERVER.teamMemo || '';
  }

  function renderChiefComplaint() {
    const span = document.querySelector('.strip:first-child .strip-text');
    if (span && span.tagName === 'SPAN') span.textContent = SERVER.chiefComplaint || '';
  }

  onServerState(renderMotifStrip);
  onServerState(renderVitalsStrip);
  onServerState(renderPrescription);
  onServerState(renderBill);
  onServerState(renderTeamMemo);
  onServerState(renderChiefComplaint);

  // ═══════════════════════════════════════════════
  //  ACTIONS — domain operations
  // ═══════════════════════════════════════════════

  // Add a medication: persists the prescription line, the billing draft is
  // re-derived server-side.
  function addMedication(item, posology) {
    return save(EP.prescriptionLineAdd, {
      articleId: item.itemId,
      dose: posology.dose,
      frequency: posology.freq,
      durationDays: posology.days,
      route: posology.route,
      quantity: 1,
    }).then(payload => {
      if (!payload?.success) return payload;
      const added = document.querySelector('.rx-item:last-child');
      const billRow = document.querySelector('.bill-list .bill-row:last-child');
      if (added) highlightNew(added);
      if (billRow) highlightNew(billRow);
      Toast.success(`${item.name} ajouté à l'ordonnance et à la facture`);
      return payload;
    });
  }

  function removeMedication(lineId) {
    return save(EP.prescriptionLineRemove, { prescriptionLineId: lineId }).then(payload => {
      if (payload?.success) Toast.info('Médicament retiré');
      return payload;
    });
  }

  // Add a constante (vital pill)
  function addVitalPill(vt, value) {
    return save(EP.typedVitalRecord, { type: vt.id, value }).then(payload => {
      if (!payload?.success) return payload;
      const pill = document.querySelector(`.vital-pill[data-vital="${vt.id}"]`);
      if (pill) highlightNew(pill);
      Toast.success(`${vt.label} ajoutée : ${value} ${vt.unit}`);
      return payload;
    });
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
      const already = (SERVER.motifs || []).some(m => m.label.toLowerCase() === motif.toLowerCase());
      Modal.close();
      if (already) {
        Toast.info(`"${motif}" déjà présent`);
        return;
      }
      // The payload is built inside the queue so two quick picks never
      // overwrite each other with a stale list.
      save(EP.motifs, () => ({ labels: [...(SERVER.motifs || []).map(m => m.label), motif] }))
        .then(payload => { if (payload?.success) Toast.success(`Motif ajouté : ${motif}`); });
    });
  }

  function lastWeighingLabel() {
    const previous = HISTORY.find(row => row.weightKg !== null && row.weightKg !== undefined);
    return previous
      ? `${frNumber(previous.weightKg)} kg · ${fmtHistoryDate(previous.startedAtUtc)}`
      : 'aucune pesée antérieure';
  }

  // — Edit Weight —
  function openEditWeightModal() {
    const body = `
      <div class="form-row">
        <span class="form-row-label">Poids actuel</span>
        <input type="text" class="inline-num-input" id="weight-input" value="${state.patient.weight ?? ''}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Unité</span>
        <span class="form-hint">kg</span>
      </div>
      <div class="form-row">
        <span class="form-row-label">Dernière pesée</span>
        <span class="form-hint">${lastWeighingLabel()}</span>
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
      const last = state.patient.lastWeight;
      Modal.close();
      save(EP.vitals, {
        weightKg: newW,
        temperatureC: SERVER.vitals?.temperatureC ?? '',
      }).then(payload => {
        if (!payload?.success) return;
        const delta = last !== null ? newW - last : null;
        Toast.success(delta === null
          ? `Poids enregistré : ${frNumber(newW)} kg`
          : `Poids enregistré : ${frNumber(newW)} kg (${delta >= 0 ? '+' : ''}${frNumber(Number(delta.toFixed(1)))})`);
      });
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
    const allergies = state.patient.allergies;
    const body = `
      <div class="modal-search">
        <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
        <input type="text" placeholder="Rechercher dans le catalogue…" id="med-search">
      </div>
      ${allergies.length ? `<div style="padding:var(--space-2) var(--space-3);background:var(--color-warning-bg);border:1px solid var(--color-warning-border);border-radius:var(--radius-md);font-size:var(--text-sm);color:var(--color-warning-text);margin-bottom:var(--space-3);display:flex;align-items:center;gap:var(--space-2)">
        <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
        Allergie déclarée : <strong>${escapeHtml(allergies.join(', '))}</strong> — molécules incompatibles filtrées.
      </div>` : ''}
      <div class="m-list" id="med-list"><div class="m-empty">Chargement du catalogue…</div></div>
    `;
    Modal.open({ title: 'Ajouter un médicament', body, width: 'wide' });

    const renderList = () => {
      const listEl = Modal.getEl('#med-list');
      if (!listEl) return;
      const groups = [
        ['Sur ordonnance', Catalog.articles.filter(a => a.requiresPrescription)],
        ['Sans ordonnance', Catalog.articles.filter(a => !a.requiresPrescription)],
      ].filter(([, items]) => items.length > 0);

      if (groups.length === 0) {
        listEl.innerHTML = '<div class="m-empty">Aucun médicament dans le catalogue de la clinique.</div>';
        return;
      }

      listEl.innerHTML = groups.map(([label, items]) => `
        <div class="m-list-section">${escapeHtml(label)}</div>
        ${items.map(m => {
          const blocked = (m.allergyConflicts || []).length > 0;
          return `
          <div class="m-item ${blocked ? 'is-disabled' : ''}" data-code="${escapeHtml(m.code)}" ${blocked ? 'data-blocked="1"' : ''}>
            <div class="m-item-content">
              <span class="m-item-name">${escapeHtml(m.name)}</span>
              <span class="m-item-meta">${escapeHtml(m.code)}</span>
            </div>
            ${blocked ? `<span class="m-item-warn">filtré : ${escapeHtml(m.allergyConflicts.join(', '))}</span>` : ''}
            <span class="m-item-price">${escapeHtml(money(m.basePriceMinorUnits, m.currency))}</span>
          </div>
        `;
        }).join('')}
      `).join('');

      listEl.querySelectorAll('.m-item').forEach(it => {
        it.addEventListener('click', () => {
          if (it.dataset.blocked) {
            Toast.warn('Médicament incompatible avec les allergies du patient');
            return;
          }
          const med = Catalog.find(it.dataset.code);
          if (!med) return;
          Modal.close();
          openMedicationPosologyModal(med);
        });
      });
    };

    Catalog.load('').then(renderList);

    Modal.getEl('#med-search').addEventListener('input', e => {
      const q = e.target.value.toLowerCase();
      const listEl = Modal.getEl('#med-list');
      if (!listEl) return;
      let visible = 0;
      listEl.querySelectorAll('.m-item').forEach(it => {
        const name = it.querySelector('.m-item-name').textContent.toLowerCase();
        const shown = name.includes(q);
        it.style.display = shown ? '' : 'none';
        if (shown) visible += 1;
      });
      // Beyond the first page the catalogue is re-queried server-side.
      if (visible === 0 && q.length >= 2) Catalog.load(e.target.value).then(renderList);
    });
  }

  function openMedicationPosologyModal(med, existingLine = null) {
    const weight = state.patient.weight;
    const dose = existingLine?.dose ?? '';
    const body = `
      <div style="padding:var(--space-3);background:var(--brand-50);border:1px solid var(--brand-100);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="font-weight:var(--weight-medium);font-size:var(--text-md)">${escapeHtml(med.name)}</div>
        <div style="font-size:var(--text-sm);color:var(--text-muted);margin-top:2px">${escapeHtml(med.code)}${med.requiresPrescription ? ' · sur ordonnance' : ''}</div>
      </div>
      <div class="form-row">
        <span class="form-row-label">Dose</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-dose" value="${escapeHtml(dose)}" style="width:120px" placeholder="0,1 mg/kg">
          <span class="form-hint" id="poso-dose-hint">${weight !== null ? `poids actuel ${frNumber(weight)} kg` : 'poids non renseigné'}</span>
        </div>
      </div>
      <div class="form-row">
        <span class="form-row-label">Voie</span>
        <input type="text" class="inline-num-input" id="poso-route" value="${escapeHtml(existingLine?.route ?? 'per os')}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Fréquence</span>
        <input type="text" class="inline-num-input" id="poso-freq" value="${escapeHtml(existingLine?.frequency ?? '1×/j')}">
      </div>
      <div class="form-row">
        <span class="form-row-label">Durée</span>
        <div style="display:flex;gap:var(--space-2);align-items:center">
          <input type="text" class="inline-num-input" id="poso-days" value="${escapeHtml(existingLine?.durationDays ?? 7)}" style="width:80px">
          <span class="form-hint">jours</span>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:var(--space-3);border-top:1px solid var(--border-light)">
        <span style="font-size:var(--text-sm);color:var(--text-muted)">Coût catalogue</span>
        <span style="font-family:'DM Mono',monospace;font-size:var(--text-lg);font-weight:var(--weight-medium)">${escapeHtml(money(med.basePriceMinorUnits, med.currency))}</span>
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="poso-save">${existingLine ? 'Enregistrer' : 'Ajouter à l\'ordonnance'}</button>
    `;
    Modal.open({ title: 'Posologie', body, footer });
    Modal.getEl('#poso-save').addEventListener('click', () => {
      const posology = {
        dose: Modal.getEl('#poso-dose').value,
        route: Modal.getEl('#poso-route').value,
        freq: Modal.getEl('#poso-freq').value,
        days: parseInt(Modal.getEl('#poso-days').value, 10) || '',
      };
      Modal.close();
      if (existingLine) {
        // No update endpoint for a prescription line: re-issuing it also
        // re-snapshots the price, which is what an edit means here.
        removeMedication(existingLine.id).then(() => addMedication(med, posology));
        return;
      }
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
          <span class="print-card-meta">${(SERVER.prescriptionLines || []).length} médicament(s) · format légal FR</span>
        </button>
        <button class="print-card" data-doc="facture">
          <span class="print-card-title">🧾 Facture</span>
          <span class="print-card-meta">Total ${money((SERVER.totals || {}).totalTtcMinorUnits || 0, (SERVER.totals || {}).currency)} TTC · NF525</span>
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
      // The print stylesheet keys off body[data-print-mode].
      const modes = { cr: 'compte-rendu', ordo: 'ordonnance', facture: 'facture', all: 'tout' };
      Modal.close();
      document.body.dataset.printMode = modes[card.dataset.doc];
      window.print();
    });
  }

  const restorePrintMode = () => { delete document.body.dataset.printMode; };
  window.addEventListener('afterprint', restorePrintMode);
  registerCleanup(() => {
    window.removeEventListener('afterprint', restorePrintMode);
    restorePrintMode();
  });

  // — Historique —
  function openHistoryModal() {
    const html = PAST_CONSULTATIONS.map((c, index) => `
      <div class="tl-item" data-index="${index}">
        <span class="tl-date">${escapeHtml(c.date)}</span>
        <div class="tl-content">
          <span class="tl-motif">${escapeHtml(c.motif)}</span>
          <span class="tl-summary">${escapeHtml(c.summary)}</span>
        </div>
      </div>
    `).join('');
    Modal.open({
      title: `Historique — ${state.patient.name}`,
      body: PAST_CONSULTATIONS.length
        ? `<div class="timeline">${html}</div>`
        : '<div class="m-empty">Aucune consultation antérieure pour ce patient.</div>',
      width: 'wide',
    });
    Modal.getEl('.timeline')?.addEventListener('click', e => {
      const item = e.target.closest('.tl-item');
      if (!item) return;
      const target = PAST_CONSULTATIONS[Number(item.dataset.index)];
      if (!target?.url) return;
      Modal.close();
      if (window.Turbo) window.Turbo.visit(target.url); else window.location.assign(target.url);
    });
  }

  // — Patient File (Dossier complet) —
  function openPatientFileModal() {
    const p = state.patient;
    const row = (label, value) => `<div class="form-row"><span class="form-row-label">${escapeHtml(label)}</span><span>${escapeHtml(value || '—')}</span></div>`;
    const body = `
      <div style="display:flex;align-items:center;gap:var(--space-4);padding-bottom:var(--space-4);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-4)">
        <div class="pb-photo" style="width:72px;height:72px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-label="${escapeHtml(p.species || 'Patient')}">
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
          <div style="font-size:var(--text-xl);font-weight:var(--weight-medium);letter-spacing:-.3px">${escapeHtml(p.name)}</div>
          <div style="color:var(--text-muted);margin-top:2px">${escapeHtml([p.breed, p.age, p.weight !== null ? `${frNumber(p.weight)} kg` : null].filter(Boolean).join(' · '))}</div>
          <div style="font-family:'DM Mono',monospace;font-size:var(--text-sm);color:var(--text-subtle);margin-top:2px">${escapeHtml(p.microchip || '')}</div>
        </div>
      </div>

      <div class="m-list-section" style="padding-left:0">Identité</div>
      ${row('Nom', p.name)}
      ${row('Espèce', p.species)}
      ${row('Race', p.breed)}
      ${row('Naissance', PATIENT.birthDate ? `${fmtHistoryDate(PATIENT.birthDate)}${p.age ? ` (${p.age})` : ''}` : null)}
      ${row('Sexe', p.sex ? `${p.sex}${PATIENT.sterilized ? ' stérilisé(e)' : ''}` : null)}
      ${row('Couleur', p.color)}
      <div class="form-row"><span class="form-row-label">Propriétaire</span><span style="color:var(--brand-600);cursor:pointer" id="patient-file-owner">${escapeHtml(OWNER ? OWNER.fullName : '—')}${OWNER ? ' →' : ''}</span></div>

      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Médical</div>
      <div class="form-row"><span class="form-row-label">Allergies</span><span style="color:var(--color-danger-text)">${escapeHtml(p.allergies.join(', ') || 'aucune')}</span></div>
      ${row('Conditions', p.conditions.join(', ') || 'aucune')}
      <div class="form-row"><span class="form-row-label">Vaccinations</span><span data-soon style="cursor:pointer;color:var(--text-muted)">à venir</span></div>
      <div class="form-row"><span class="form-row-label">Vermifuge</span><span data-soon style="cursor:pointer;color:var(--text-muted)">à venir</span></div>

      <div class="m-list-section" style="padding-left:0;margin-top:var(--space-3)">Activité</div>
      ${row('Consultations', `${PAST_CONSULTATIONS.length} antérieure${PAST_CONSULTATIONS.length > 1 ? 's' : ''}`)}
      ${row('Dernière visite', PAST_CONSULTATIONS[0]?.date)}
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-primary" id="patient-file-edit">Modifier le dossier</button>
    `;
    Modal.open({ title: 'Dossier patient', body, footer, width: 'wide' });
    Modal.getEl('.modal-body')?.addEventListener('click', e => {
      if (e.target.closest('[data-soon]')) Toast.info('Fonctionnalité à venir');
    });
    Modal.getEl('#patient-file-owner')?.addEventListener('click', () => { Modal.close(); openOwnerModal(); });
    Modal.getEl('#patient-file-edit')?.addEventListener('click', () => Toast.info('Fonctionnalité à venir'));
  }

  // — Owner —
  function openOwnerModal() {
    if (!OWNER) {
      Toast.info('Aucun propriétaire rattaché à ce patient');
      return;
    }
    const initials = OWNER.fullName.split(/\s+/).map(part => part.charAt(0).toUpperCase()).slice(0, 2).join('');
    const body = `
      <div style="display:flex;align-items:center;gap:var(--space-3);padding-bottom:var(--space-3);border-bottom:1px solid var(--border-light);margin-bottom:var(--space-3)">
        <span class="avatar avatar-lg">${escapeHtml(initials)}</span>
        <div>
          <div style="font-size:var(--text-lg);font-weight:var(--weight-medium)">${escapeHtml(OWNER.fullName)}</div>
          <div style="color:var(--text-muted);font-size:var(--text-sm)">Propriétaire de ${escapeHtml(state.patient.name)}</div>
        </div>
      </div>
      <div class="form-row"><span class="form-row-label">Téléphone</span><span style="color:var(--brand-600)">${escapeHtml(OWNER.phone || '—')}</span></div>
      <div class="form-row"><span class="form-row-label">Email</span><span style="color:var(--brand-600)">${escapeHtml(OWNER.email || '—')}</span></div>
      <div class="form-row"><span class="form-row-label">Adresse</span><span>${escapeHtml(OWNER.address || '—')}</span></div>
      <div class="form-row"><span class="form-row-label">Solde</span><span data-soon style="cursor:pointer;color:var(--text-muted)">à venir</span></div>
      <div class="form-row"><span class="form-row-label">Préférences</span><span data-soon style="cursor:pointer;color:var(--text-muted)">à venir</span></div>
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-primary" id="owner-full">Voir fiche complète</button>
    `;
    Modal.open({ title: 'Propriétaire', body, footer });
    Modal.getEl('.modal-body')?.addEventListener('click', e => {
      if (e.target.closest('[data-soon]')) Toast.info('Fonctionnalité à venir');
    });
    Modal.getEl('#owner-full')?.addEventListener('click', () => Toast.info('Fonctionnalité à venir'));
  }

  // — Allergie info —
  function openAllergieInfoModal(alert) {
    const body = `
      <div style="padding:var(--space-3);background:var(--color-danger-bg);border:1px solid var(--color-danger-border);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="display:flex;align-items:center;gap:var(--space-2);color:var(--color-danger-text);font-weight:var(--weight-medium);margin-bottom:var(--space-1)">
          <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
          Allergie ${escapeHtml(alert.label)}
        </div>
        <div style="font-size:var(--text-sm);color:var(--color-danger-text)">${escapeHtml(alert.note || 'Aucune précision enregistrée.')}</div>
      </div>
      <div style="font-size:var(--text-sm);color:var(--text-muted);line-height:var(--lh-loose)">
        Cette allergie est <strong style="font-weight:var(--weight-medium);color:var(--text-primary)">automatiquement appliquée</strong> au catalogue de médicaments dans la modale d'ajout d'ordonnance. Les molécules incompatibles apparaissent grisées et non sélectionnables.
      </div>
    `;
    const footer = `
      <button class="btn btn-secondary" data-close>Fermer</button>
      <button class="btn btn-ghost" id="allergy-edit">Modifier</button>
    `;
    Modal.open({ title: 'Détails de l\'allergie', body, footer, width: 'narrow' });
    Modal.getEl('#allergy-edit')?.addEventListener('click', () => Toast.info('Fonctionnalité à venir'));
  }

  function openConditionInfoModal(alert) {
    const body = `
      <div style="padding:var(--space-3);background:var(--color-info-bg);border:1px solid var(--color-info-border);border-radius:var(--radius-md);margin-bottom:var(--space-3)">
        <div style="display:flex;align-items:center;gap:var(--space-2);color:var(--color-info-text);font-weight:var(--weight-medium);margin-bottom:var(--space-1)">
          <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5l2 1.5"/></svg>
          ${escapeHtml(alert.label)}
        </div>
        <div style="font-size:var(--text-sm);color:var(--color-info-text)">${escapeHtml(alert.note || 'Aucune précision enregistrée.')}</div>
      </div>
    `;
    Modal.open({
      title: 'Suivi en cours',
      body,
      footer: '<button class="btn btn-secondary" data-close>Fermer</button>',
      width: 'narrow',
    });
  }

  // — Clôturer la consultation —
  function openCloseConsultationModal() {
    if (isClosed()) {
      Toast.warn('Consultation déjà clôturée');
      return;
    }

    const motifs = (SERVER.motifs || []).length;
    const vitals = (SERVER.typedVitals || []).length + (SERVER.vitals ? 1 : 0);
    const exams = (SERVER.examSystems || []).length;
    const dxCount = (SERVER.diagnoses || []).length;
    const planCount = (SERVER.planActions || []).length;
    const rxCount = (SERVER.prescriptionLines || []).length;
    const total = money((SERVER.totals || {}).totalTtcMinorUnits || 0, (SERVER.totals || {}).currency);

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
        ${check('Au moins une constante', vitals >= 1, vitals + ' mesure(s)')}
        ${check('Examen objectif renseigné', exams >= 1, exams + ' système(s)')}
        ${check('Au moins un diagnostic', dxCount >= 1, dxCount + ' dx')}
        ${check('Plan défini', planCount >= 1, planCount + ' action(s)')}
        ${check('Facturation établie', true, total)}
      </div>
      <div class="m-list-section" style="padding-left:0">Finalisation</div>
      <div class="field-block">
        <label class="field-label-mini" for="close-summary">Compte-rendu de sortie</label>
        <textarea class="modal-textarea" id="close-summary" rows="3" placeholder="Synthèse remise au propriétaire…">${escapeHtml(SERVER.summary || '')}</textarea>
      </div>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked data-soon> <span>Envoyer la facture par email${OWNER ? ` à ${escapeHtml(OWNER.fullName)}` : ''}</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked data-soon> <span>Envoyer l'ordonnance par email</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" data-soon> <span>Programmer le RDV de recontrôle</span>
      </label>
      <label style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);cursor:pointer">
        <input type="checkbox" checked data-soon> <span>Programmer rappel SMS</span>
      </label>
    `;
    const footer = `
      <button class="btn btn-ghost" data-close>Annuler</button>
      <button class="btn btn-primary" id="close-confirm">Clôturer et envoyer</button>
    `;
    Modal.open({ title: 'Clôture de la consultation', body, footer });
    Modal.getEl('#close-confirm').addEventListener('click', () => {
      const summary = Modal.getEl('#close-summary')?.value || '';
      Modal.close();
      closeConsultation(summary);
    });
  }

  function closeConsultation(summary) {
    SaveState.set('saving');
    const body = new URLSearchParams();
    body.append('summary', summary);
    body.append('_token', document.getElementById('consultation-csrf')?.value || '');
    fetch(EP.close, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
      .then(response => {
        if (!response.ok && response.status >= 400) throw new Error('close failed');
        SaveState.set('saved');
        openConsultationClosedModal();
      })
      .catch(() => {
        SaveState.set('error');
        Toast.error('La clôture a échoué — réessayez');
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
          Le dossier passe en lecture seule.${OWNER ? `<br>Les documents seront transmis à <strong style="color:var(--text-primary);font-weight:var(--weight-medium)">${escapeHtml(OWNER.fullName)}</strong>.` : ''}
        </div>
      </div>
    `;
    const footer = `
      <button class="btn btn-ghost" id="stay-here">Rester ici</button>
      <button class="btn btn-primary" id="next-patient">Patient suivant →</button>
    `;
    Modal.open({ title: '', body, footer, width: 'narrow' });
    Modal.getEl('#stay-here').addEventListener('click', () => {
      Modal.close();
      window.location.reload();
    });
    Modal.getEl('#next-patient').addEventListener('click', () => {
      Modal.close();
      if (window.Turbo) window.Turbo.visit(EP.admissionQueue); else window.location.assign(EP.admissionQueue);
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

  // Motif chip: clicking it drops the motif from the consultation.
  function wireMotifChip(chip) {
    chip.addEventListener('click', () => {
      const id = chip.dataset.motifId;
      if (!id) return;
      chip.classList.remove('is-selected');
      save(EP.motifs, () => ({
        labels: (SERVER.motifs || []).filter(m => m.id !== id).map(m => m.label),
      }));
    });
  }

  function wireVitalPill(pill) {
    pill.addEventListener('click', e => {
      // ignore click sur la croix
      if (e.target.closest('.row-x')) return;
      // une consultation clôturée est en lecture seule
      if (isClosed()) return;
      // pour le poids et la température, ouvrir l'éditeur dédié
      if (!pill.dataset.vital || pill.dataset.vital === 'weight' || pill.dataset.vital === 'temperature') {
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
        save(EP.typedVitalRemove, { type: pill.dataset.vital }).then(payload => {
          if (payload?.success) Toast.info(`${label} retirée`);
        });
      });
    }
  }

  function wireRxItem(item) {
    item.addEventListener('click', e => {
      // ignore les clics sur la croix
      if (e.target.closest('.row-x')) return;
      const line = (SERVER.prescriptionLines || []).find(l => l.id === item.dataset.lineId);
      if (!line || !line.articleId) return;
      const cached = Catalog.find(line.code);
      const med = cached || {
        itemId: line.articleId,
        name: line.label,
        code: line.code || '',
        basePriceMinorUnits: line.unitPriceMinorUnits,
        currency: line.currency,
        requiresPrescription: true,
      };
      openMedicationPosologyModal(med, line);
    });

    const xBtn = item.querySelector('.row-x');
    if (xBtn) {
      xBtn.addEventListener('click', e => {
        e.stopPropagation();
        removeMedication(item.dataset.lineId);
      });
    }
  }

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
  // warnings — rendered from the animal's medical alerts
  function renderPatientWarnings() {
    const host = document.querySelector('.pb-warnings');
    if (!host) return;
    host.innerHTML = (SERVER.medicalAlerts || []).map(alert => {
      const isAllergy = alert.kind === 'ALLERGY';
      const icon = isAllergy
        ? '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>'
        : '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5l2 1.5"/></svg>';
      return `<span class="pb-warn${isAllergy ? '' : ' is-info'}" data-alert-id="${escapeHtml(alert.id)}" style="cursor:pointer">${icon}${escapeHtml(isAllergy ? `Allergie ${alert.label}` : `Suivi ${alert.label}`)}</span>`;
    }).join('');
  }

  onServerState(renderPatientWarnings);

  document.querySelector('.pb-warnings')?.addEventListener('click', e => {
    const warn = e.target.closest('[data-alert-id]');
    if (!warn) return;
    const alert = (SERVER.medicalAlerts || []).find(a => a.id === warn.dataset.alertId);
    if (!alert) return;
    if (alert.kind === 'ALLERGY') openAllergieInfoModal(alert);
    else openConditionInfoModal(alert);
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
    ], { onSelect: () => Toast.info('Fonctionnalité à venir') });
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
        if (!cancelled && input.value !== current && input.value.trim() !== '') {
          save(EP.chiefComplaint, { chiefComplaint: input.value }).then(payload => {
            if (payload?.success) Toast.success('Motif mis à jour');
          });
        }
      };
      input.addEventListener('blur', () => finish(false));
      input.dataset.persist = '1';
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
    // There is no invoice state in the domain yet, so the badge can only
    // report what is known: a closed consultation is no longer a draft.
    onServerState(() => {
      draftBadge.textContent = isClosed() ? 'clôturée' : 'brouillon';
      draftBadge.style.cursor = isClosed() ? 'default' : 'pointer';
    });
    draftBadge.addEventListener('click', e => {
      if (isClosed()) return;
      Dropdown.open(e.currentTarget, [
        { action: 'draft', label: '✏ Brouillon' },
        { action: 'pending', label: '⏳ À facturer' },
        { action: 'paid', label: '✓ Payée' },
      ], { onSelect: () => Toast.info('Fonctionnalité à venir') });
    });
  }

  // ─── Footer ───
  // Pause
  const pauseBtn = document.getElementById('btn-pause');
  if (pauseBtn) pauseBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Mettre la consultation en pause',
      message: 'La consultation reste accessible depuis l\'agenda. Vous pouvez la reprendre à tout moment.',
      confirmLabel: 'Mettre en pause',
      onConfirm: () => {
        if (window.Turbo) window.Turbo.visit(EP.admissionQueue); else window.location.assign(EP.admissionQueue);
      },
    });
  });

  // Plus tard
  const laterBtn = document.getElementById('btn-later');
  if (laterBtn) laterBtn.addEventListener('click', () => {
    openConfirmModal({
      title: 'Reporter la consultation',
      message: 'Le brouillon sera conservé. Vous pourrez la reprendre depuis l\'agenda quand vous le souhaitez.',
      confirmLabel: 'Reporter',
      onConfirm: () => {
        if (window.Turbo) window.Turbo.visit(EP.consultations); else window.location.assign(EP.consultations);
      },
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

  // ─── SOAP free texts (auto-save) ───
  function wireAutoSave(selector, endpoint, field) {
    const el = document.querySelector(selector);
    if (!el) return;
    let timer = null;
    el.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => save(endpoint, { [field]: el.value }), 700);
    });
    registerCleanup(() => clearTimeout(timer));
    onServerState(() => {
      if (el !== document.activeElement) el.value = SERVER[field === 'text' ? textField(endpoint) : field] || '';
    });
  }

  function textField(endpoint) {
    return endpoint === EP.subjective ? 'subjectiveText' : 'objectiveObservations';
  }

  wireAutoSave('#q-subjective', EP.subjective, 'text');
  wireAutoSave('#q-objective', EP.objective, 'text');

  // ─── Read-only mode ───
  onServerState(() => {
    document.body.classList.toggle('consultation-readonly', isClosed());
    if (!isClosed()) return;
    ['#q-subjective', '#q-objective', '#team-memo'].forEach(sel => {
      document.querySelector(sel)?.setAttribute('readonly', 'readonly');
    });
    document.getElementById('btn-close-consultation')?.setAttribute('disabled', 'disabled');
  });

  // ─── Mémo équipe ───
  const memoEl = document.getElementById('team-memo');
  if (memoEl) {
    let memoTimer = null;
    memoEl.addEventListener('input', () => {
      clearTimeout(memoTimer);
      memoTimer = setTimeout(() => save(EP.teamMemo, { memo: memoEl.value }), 700);
    });
    registerCleanup(() => clearTimeout(memoTimer));
  }

  // Close any floating UI left open when Turbo caches the page
  registerCleanup(() => { Modal.close(); Dropdown.close(); });

  // First paint from the hydration payload.
  renderFromServer();

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

// Body systems and species templates are served by the server; the shapes below
// are the ones the render and modal code already expected.
const SYSTEM_DEFS = (REF.bodySystems || []).reduce((acc, sys) => {
  acc[sys.id] = { name: sys.label, icon: sys.icon, drilldown: sys.drilldown };
  return acc;
}, {});

const TEMPLATES = (REF.speciesTemplates || []).reduce((acc, tpl) => {
  acc[tpl.id] = { name: tpl.name, emoji: tpl.emoji, systems: tpl.systems, enabled: tpl.enabled };
  return acc;
}, {});

let currentTemplate = (() => {
  const species = (PATIENT.speciesLabel || '').toLowerCase();
  if (species.startsWith('chat') && TEMPLATES.chat) return 'chat';
  if (species.startsWith('nac') && TEMPLATES.reptile) return 'reptile';
  return TEMPLATES.chien ? 'chien' : Object.keys(TEMPLATES)[0];
})();

const STATUS_FROM_SERVER = { NORMAL: 'normal', ANOMALY: 'anomaly', UNTESTED: 'untested' };
const STATUS_TO_SERVER = { normal: 'NORMAL', anomaly: 'ANOMALY', untested: 'UNTESTED' };

// Local mirror of the persisted exam, keyed like the layout expects.
const state = {};

function syncStateFromServer() {
  Object.keys(SYSTEM_DEFS).forEach(id => {
    state[id] = state[id] || { status: 'untested', notes: '', structured: {} };
    state[id].status = 'untested';
    state[id].notes = '';
    state[id].structured = {};
  });
  (SERVER.examSystems || []).forEach(exam => {
    state[exam.system] = {
      status: STATUS_FROM_SERVER[exam.status] || 'untested',
      notes: exam.notes || '',
      structured: exam.structuredData || {},
    };
  });
}

syncStateFromServer();

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
  return hasStruct || s.notes;
}

function currentSystems() {
  const templateSystems = TEMPLATES[currentTemplate]?.systems || [];
  // A system recorded under another template stays visible: hiding stored data
  // would be worse than showing one extra row.
  const recordedElsewhere = (SERVER.examSystems || [])
    .map(exam => exam.system)
    .filter(id => !templateSystems.includes(id));
  return [...templateSystems, ...recordedElsewhere]
    .filter(id => SYSTEM_DEFS[id])
    .map(id => ({ id, ...SYSTEM_DEFS[id] }));
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
            persistSystem(sysId, 'anomaly').then(() => {
              toast(`${sysName} → Anomalie`);
              setTimeout(() => openModal(sysId), 100);
            });
            return;
          }
          openModal(sysId);
          return;
        }
        if (newState === state[sysId].status) return;
        persistSystem(sysId, newState).then(payload => {
          if (payload?.success) toast(`${sysName} → ${STATUS_LABEL[newState]}`);
        });
      });
    });
  });

  $$('.rowF').forEach(row => {
    const sysId = row.dataset.sys;
    if (state[sysId].status !== 'anomaly') return;
    row.addEventListener('click', () => openModal(sysId));
  });
}

function persistSystem(sysId, status, notes, structured) {
  const current = state[sysId] || {};
  return save(EP.examSystem, {
    system: sysId,
    status: STATUS_TO_SERVER[status] || 'UNTESTED',
    notes: notes !== undefined ? notes : (current.notes || ''),
    structuredData: structured !== undefined ? structured : (current.structured || {}),
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

  $('#qO-modal-body').innerHTML = `
    ${structuredHTML}
    <div class="field-block">
      <label class="field-label-mini">Observations détaillées</label>
      <textarea class="modal-textarea" id="f-notes" rows="5" placeholder="Décris ce que tu observes…">${workingCopy.notes}</textarea>
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
  const notes = $('#f-notes')?.value || '';
  closeModal();
  persistSystem(sys.id, 'anomaly', notes, struct).then(payload => {
    if (payload?.success) toast(`${sys.name} — détails enregistrés`);
  });
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
  save(EP.examSystemsAllNormal, () => ({ systems: currentSystems().map(sy => sy.id) })).then(payload => {
    if (payload?.success) toast('Tous les systèmes restants marqués RAS');
  });
});

onServerState(() => {
  syncStateFromServer();
  renderTemplateLabel();
  renderList();
});

renderTemplateLabel();
renderList();


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

const NOMENCLATURE = REF.nomenclature || [];

// The diagnosis list is the server's; the fields already match the layout's.
let diagnoses = [];

function syncDiagnosesFromServer() {
  diagnoses = (SERVER.diagnoses || []).map(dx => ({ ...dx }));
}

syncDiagnosesFromServer();

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
      if (!dx) return;
      const wasPrimary = dx.isPrimary;
      save(EP.diagnosisPrimary, { diagnosisId: wasPrimary ? '' : id }).then(payload => {
        if (!payload?.success) return;
        toast(wasPrimary
          ? `${dx.label.slice(0, 30)}… retiré comme principal`
          : `${dx.label.slice(0, 30)}… défini comme principal`);
      });
    });
  });
  $$('[data-remove]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = btn.dataset.remove;
      const dx = diagnoses.find(x => x.id === id);
      if (!dx) return;
      save(EP.diagnosisRemove, { diagnosisId: id }).then(payload => {
        if (payload?.success) toast(`Retiré : ${dx.label.slice(0, 40)}${dx.label.length > 40 ? '…' : ''}`);
      });
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
        save(EP.diagnosisUpdate, {
          diagnosisId: existing.id,
          code: existing.code || '',
          label: existing.label,
          certainty: cert,
          note: existing.note || '',
        }).then(payload => {
          if (!payload?.success) return;
          renderQuickAddList();
          updateQuickAddCount();
          toast(`${nomEntry.name.slice(0, 35)}${nomEntry.name.length > 35 ? '…' : ''} → ${CERTAINTY_DEFS[cert].label}`);
        });
        return;
      }
      save(EP.diagnosisAdd, {
        code: nomEntry.code,
        label: nomEntry.name,
        certainty: cert,
        note: '',
        isPrimary: 0,
        source: 'MANUAL',
      }).then(payload => {
        if (!payload?.success) return;
        renderQuickAddList();
        updateQuickAddCount();
        toast(`+ ${nomEntry.name.slice(0, 35)}${nomEntry.name.length > 35 ? '…' : ''} en ${CERTAINTY_DEFS[cert].label}`);
      });
    });
  });

  // Wire remove buttons
  listEl.querySelectorAll('[data-remove-code]').forEach(btn => {
    btn.addEventListener('click', () => {
      const code = btn.dataset.removeCode;
      const dx = getDxByCode(code);
      if (!dx) return;
      save(EP.diagnosisRemove, { diagnosisId: dx.id }).then(payload => {
        if (!payload?.success) return;
        renderQuickAddList();
        updateQuickAddCount();
        toast(`Retiré : ${dx.label.slice(0, 35)}${dx.label.length > 35 ? '…' : ''}`);
      });
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
          save(EP.diagnosisAdd, {
            code: nomEntry.code,
            label: nomEntry.name,
            certainty: 'PROBABLE',
            note: '',
            isPrimary: 0,
            source: 'MANUAL',
          }).then(payload => {
            if (!payload?.success) return;
            const created = diagnoses.find(d => d.code === nomEntry.code);
            if (created) openEditModal(created.id);
          });
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
  const id = editingId;
  const wasPrimary = diagnoses.find(x => x.id === id)?.isPrimary === true;
  const label = workingCopy.label;
  closeModal();

  save(EP.diagnosisUpdate, {
    diagnosisId: id,
    code: workingCopy.code || '',
    label,
    certainty: workingCopy.certainty,
    note: workingCopy.note,
  }).then(payload => {
    if (!payload?.success) return;
    if (workingCopy.isPrimary === wasPrimary) {
      toast(`Modifié : ${label.slice(0, 40)}${label.length > 40 ? '…' : ''}`);
      return;
    }
    save(EP.diagnosisPrimary, { diagnosisId: workingCopy.isPrimary ? id : '' }).then(() => {
      toast(`Modifié : ${label.slice(0, 40)}${label.length > 40 ? '…' : ''}`);
    });
  });
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
        closeModal();
        save(EP.diagnosisAdd, {
          code: s.code,
          label: s.name,
          certainty: 'PROBABLE',
          note: s.reason,
          isPrimary: 0,
          source: 'AI_SUGGESTION',
        }).then(payload => {
          if (payload?.success) toast(`✨ ${s.name} ajouté en Probable`);
        });
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

onServerState(() => {
  syncDiagnosesFromServer();
  renderList();
});

renderList();


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

// Suggestions par kind — acts and medications come from the clinic's catalogue,
// the follow-up / advice / other lists are server-side reference data.
const SUGGESTIONS = {
  PERFORMED_ACT: [],
  MEDICATION_PRESCRIPTION: [],
  FOLLOW_UP_APPOINTMENT: (REF.planSuggestions || {}).FOLLOW_UP_APPOINTMENT || [],
  ADVICE: (REF.planSuggestions || {}).ADVICE || [],
  OTHER: (REF.planSuggestions || {}).OTHER || [],
};

function syncCatalogSuggestions() {
  SUGGESTIONS.PERFORMED_ACT = Catalog.acts.map(a => ({
    code: a.code, name: a.name, price: a.basePriceMinorUnits / 100, itemId: a.itemId,
  }));
  SUGGESTIONS.MEDICATION_PRESCRIPTION = Catalog.articles.map(a => ({
    code: a.code, name: a.name, itemId: a.itemId, posology: a.requiresPrescription ? 'sur ordonnance' : null,
  }));
}

const PLAN_TEMPLATES = (REF.planTemplates || []).reduce((acc, tpl) => {
  acc[tpl.id] = { name: tpl.name, emoji: tpl.emoji, desc: tpl.description, items: tpl.items };
  return acc;
}, {});

// ════════════════════════════════════════════════════════════
//  STATE
// ════════════════════════════════════════════════════════════
// The plan is the server's; the field names already match the layout's.
let planItems = [];

function syncPlanFromServer() {
  planItems = (SERVER.planActions || []).map(item => ({ ...item }));
}

syncPlanFromServer();

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
      if (!item) return;
      save(EP.planActionRemove, { planActionId: id }).then(payload => {
        if (payload?.success) toast(`Retiré : ${item.description.slice(0, 40)}${item.description.length > 40 ? '…' : ''}`);
      });
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

  // The catalogue is fetched once per opening, then filtered client-side so the
  // search stays instant.
  Catalog.load('').then(() => {
    syncCatalogSuggestions();
    if ($('#qa-main-list')) renderMainList();
  });

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
      <input type="text" class="qa-search" id="qa-search-input" placeholder="Rechercher dans le catalogue de la clinique…" autocomplete="off">
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

  // Search wiring — filters the loaded page instantly, and re-queries the
  // catalogue server-side once the local page runs out of matches.
  const searchInput = $('#qa-search-input');
  let catalogTimer = null;
  searchInput.addEventListener('input', () => {
    renderMainList();
    renderSidePresets();
    clearTimeout(catalogTimer);
    const term = searchInput.value.trim();
    if (term.length < 2) return;
    catalogTimer = setTimeout(() => {
      Catalog.load(term).then(() => {
        syncCatalogSuggestions();
        if ($('#qa-main-list')) renderMainList();
      });
    }, 250);
  });
  registerCleanup(() => clearTimeout(catalogTimer));

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
    const followUpDays = kind === 'FOLLOW_UP_APPOINTMENT' ? (parseInt(aptDaysInput.value, 10) || 14) : '';
    ftInput.value = '';
    updateFtState();
    ftInput.focus();
    persistPlanAction({ kind, description: text, followUpDays }).then(payload => {
      if (!payload?.success) return;
      if ($('#qa-main-list')) renderMainList();
      if ($('#qa-side-area')) renderSidePresets();
      updateQuickAddCount();
      const metaLabel = followUpDays ? ` (J+${followUpDays})` : '';
      toast(`+ ${text.slice(0, 35)}${text.length > 35 ? '…' : ''} en ${KIND_DEFS[kind].label}${metaLabel}`);
    });
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
          const removed = planItems[i];
          save(EP.planActionRemove, { planActionId: removed.id }).then(payload => {
            if (!payload?.success) return;
            if ($('#qa-main-list')) renderMainList();
            if ($('#qa-side-area')) renderSidePresets();
            updateQuickAddCount();
            toast(`Retiré : ${removed.description.slice(0, 40)}${removed.description.length > 40 ? '…' : ''}`);
          });
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

function persistPlanAction(item) {
  return save(EP.planActionAdd, {
    kind: item.kind,
    description: item.description,
    catalogItemId: item.catalogItemId || '',
    catalogCode: item.catalogCode || '',
    posology: item.posology || '',
    durationDays: item.durationDays || '',
    followUpDays: item.followUpDays || '',
    quantity: item.quantity || 1,
  });
}

function addFromSuggestion(kind, code) {
  const s = (SUGGESTIONS[kind] || []).find(x => x.code === code);
  if (!s) return;

  persistPlanAction({
    kind,
    description: s.name,
    catalogItemId: s.itemId,
    catalogCode: s.code,
    posology: s.posology,
    durationDays: s.durationDays,
    followUpDays: s.followUpDays,
  }).then(payload => {
    if (!payload?.success) return;
    if ($('#qa-main-list')) renderMainList();
    if ($('#qa-side-area')) renderSidePresets();
    updateQuickAddCount();
    const metaLabel = s.followUpDays ? ` (J+${s.followUpDays})` : '';
    toast(`+ ${s.name.slice(0, 35)}${s.name.length > 35 ? '…' : ''}${metaLabel}`);
  });
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
  const id = editingId;
  closeModal();
  save(EP.planActionUpdate, {
    planActionId: id,
    description: desc,
    posology: item.posology || '',
    durationDays: item.durationDays || '',
    followUpDays: item.followUpDays || '',
    quantity: item.quantity || 1,
  }).then(payload => {
    if (payload?.success) toast(`Modifié : ${desc.slice(0, 40)}${desc.length > 40 ? '…' : ''}`);
  });
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
    let added = 0;
    tpl.items
      .reduce(
        (chain, it) => chain.then(() => persistPlanAction(it).then(payload => { if (payload?.success) added += 1; })),
        Promise.resolve(),
      )
      .then(() => {
        toast(added === tpl.items.length
          ? `Template "${tpl.name}" appliqué (+${added} actions)`
          : `Template "${tpl.name}" partiellement appliqué (${added}/${tpl.items.length})`);
      });
  });
}

$('#qP-template-btn').addEventListener('click', e => { e.stopPropagation(); openTemplateMenu(); });
onDocument('click', e => {
  if (!e.target.closest('.template-menu') && !e.target.closest('#qP-template-btn')) closeTemplateMenu();
});

$('#qP-add-btn').addEventListener('click', openQuickAddModal);

onServerState(() => {
  syncPlanFromServer();
  renderList();
});

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
