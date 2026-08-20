// assets/js/pages/consultation.js
// Consultation cockpit — vanilla page module.
//
// Everything on the page is driven by one server-owned state object: the
// hydration payload rendered in #consultation-data, replaced by the `consultation`
// object every mutation endpoint returns. There is a single rendering path, so
// the first paint and every post-mutation repaint go through the same code.

import { fmtMoney } from 'kiveto/money';

let _mounted = false;
// Teardown callbacks registered during init() (document-level listeners,
// intervals, observers). Flushed by cleanup() on turbo:before-cache.
const _cleanups = [];

const ICONS = {
  heart:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
  lungs:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.081 20C7.14 20 8 19.14 8 18.081V13.5l-1.5-2.5C5.5 9.5 4 9 3 10c-1 1-1 3 0 5l2 4.1a2 2 0 0 0 1.081.9Z"/><path d="M12 3v10"/><path d="M17.919 20A1.92 1.92 0 0 1 16 18.081V13.5l1.5-2.5c1-1.5 2.5-2 3.5-1 1 1 1 3 0 5l-2 4.1a2 2 0 0 1-1.081.9Z"/></svg>',
  stomach: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v8a6 6 0 0 0 6 6h2a8 8 0 0 0 8-8v-1a3 3 0 0 0-6 0"/><path d="M4 8h4"/></svg>',
  droplet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7Z"/></svg>',
  bone:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 10c.7-.7 1.69 0 2.5 0a2.5 2.5 0 1 0 0-5c-.28 0-.5.11-.7.29A2.5 2.5 0 1 0 14 3.5c0 .81.69 1.8 0 2.5l-8 8c-.7.7-1.69 0-2.5 0a2.5 2.5 0 0 0 0 5c.28 0 .5-.11.7-.29A2.5 2.5 0 1 0 10 20.5c0-.81-.69-1.8 0-2.5Z"/></svg>',
  brain:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/></svg>',
  sparkle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
  eye:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
  tooth:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5.5c-1.5-1-3-1.5-4.5-1.5C5 4 3.5 5.5 3.5 8c0 3 1 5 1.5 8 .4 2.3 1 4 2 4s1.5-1.5 2-4c.3-1.6.6-3 3-3s2.7 1.4 3 3c.5 2.5 1 4 2 4s1.6-1.7 2-4c.5-3 1.5-5 1.5-8 0-2.5-1.5-4-4-4-1.5 0-3 .5-4.5 1.5Z"/></svg>',
  shell:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a9 9 0 1 0-9-9c0 4 3 9 9 9Z"/><path d="M12 3v18"/><path d="M3.5 9h17"/><path d="M4.5 15h15"/></svg>',
  act:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11.5V8a4 4 0 0 0-4-4h-4a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h4"/><path d="M14 4v4"/><path d="M10 4v4"/><path d="M18 18l4-4"/><path d="M18 14l4 4"/></svg>',
  rx:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
  apt:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  adv:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  oth:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
  search:  '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>',
  close:   '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l10 10M13 3L3 13"/></svg>',
  check:   '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8l3 3 7-7"/></svg>',
  starOutline: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  starFilled:  '<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  sparkles:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
};

const KIND_STYLE = {
  PERFORMED_ACT:           { cssKey: 'act', icon: 'act' },
  MEDICATION_PRESCRIPTION: { cssKey: 'rx',  icon: 'rx'  },
  FOLLOW_UP_APPOINTMENT:   { cssKey: 'apt', icon: 'apt' },
  ADVICE:                  { cssKey: 'adv', icon: 'adv' },
  OTHER:                   { cssKey: 'oth', icon: 'oth' },
};

const CERTAINTY_CSS = {
  CERTAIN:  'certain',
  PROBABLE: 'probable',
  POSSIBLE: 'possible',
  EXCLUDED: 'excluded',
};

export function init() {
  if (_mounted) return;
  _mounted = true;

  const root = document.getElementById('consultation-data');
  if (!root) return;

  // ═══════════════════════════════════════════════
  //  HYDRATION & STATE
  // ═══════════════════════════════════════════════

  const HYDRATION = (() => {
    try {
      return JSON.parse(root.textContent || '{}');
    } catch {
      return {};
    }
  })();

  const REF = HYDRATION.reference || {};
  const EP = HYDRATION.endpoints || {};
  const PATIENT = HYDRATION.patient || {};
  const OWNER = HYDRATION.owner || null;
  const HISTORY = HYDRATION.history || [];

  // Replaced wholesale by the `consultation` object of every mutation response.
  let state = HYDRATION.consultation || {};

  function isClosed() {
    return state.isClosed === true;
  }

  // ═══════════════════════════════════════════════
  //  SHARED HELPERS
  // ═══════════════════════════════════════════════

  function $(selector, scope = document) { return scope.querySelector(selector); }
  function $$(selector, scope = document) { return [...scope.querySelectorAll(selector)]; }

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function registerCleanup(fn) {
    _cleanups.push(fn);
  }

  // Document-level listeners MUST go through this helper so cleanup()
  // removes them (raw document.addEventListener leaks across Turbo visits).
  function onDocument(type, handler) {
    document.addEventListener(type, handler);
    registerCleanup(() => document.removeEventListener(type, handler));
  }

  // Timers created here are cancelled once, when the module is torn down —
  // registering a cleanup per invocation would grow the teardown list forever.
  const debounceTimers = new Set();
  registerCleanup(() => {
    debounceTimers.forEach(clearTimeout);
    debounceTimers.clear();
  });

  function debounce(fn, delay) {
    let timer = null;
    return (...args) => {
      clearTimeout(timer);
      debounceTimers.delete(timer);
      timer = setTimeout(() => {
        debounceTimers.delete(timer);
        fn(...args);
      }, delay);
      debounceTimers.add(timer);
    };
  }

  // Parses a server datetime as UTC even when the string carries no zone
  // marker (the read side emits "YYYY-MM-DD HH:MM:SS" in UTC).
  function parseUtcDate(value) {
    if (!value) return null;
    const normalized = /(?:Z|[+-]\d{2}:?\d{2})$/.test(value) ? value : value.replace(' ', 'T') + 'Z';
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  const DATE_FMT = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });

  function fmtDate(value) {
    const date = parseUtcDate(value);
    return date ? DATE_FMT.format(date) : '';
  }

  function fmtNumber(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return String(value ?? '');
    return String(Number(number.toFixed(3))).replace('.', ',');
  }

  // ── Toast system ──
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
      const host = ensure();
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      const icon = {
        success: '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8l3 3 6-6"/></svg>',
        info:    '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11h.01"/></svg>',
        warn:    '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01"/></svg>',
        error:   '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l10 10M13 3L3 13"/></svg>',
      }[type] || '';
      toast.innerHTML = icon + ' ' + esc(message);
      host.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('show'));
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 220);
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
      soon:    () => show('Fonctionnalité à venir', 'info'),
    };
  })();

  // ── Quad annotation (inline mini toast per quad) ──
  function annotate(quad, message) {
    const el = document.getElementById(`q${quad}-annotation`);
    if (!el) return;
    el.textContent = message;
    el.classList.add('is-visible');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('is-visible'), 1600);
  }

  // ── Save-state indicator (topbar chip, fed by api()) ──
  const SaveState = (() => {
    const el = document.getElementById('save-state');
    const LABELS = {
      idle:   'Brouillon',
      saving: 'Enregistrement…',
      saved:  'Enregistré',
      error:  'Erreur de sauvegarde',
    };
    function set(status) {
      if (!el) return;
      el.dataset.state = status;
      const label = el.querySelector('.save-state-label');
      if (label) label.textContent = LABELS[status] || LABELS.idle;
    }
    return { set };
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

    function errorMessage(result) {
      return ERROR_COPY[result.payload?.errorCode] || 'Erreur lors de la sauvegarde';
    }

    // `data` may be a builder: queued mutations that derive their payload from
    // the current state must read it AFTER the previous one landed, or they
    // resend a stale snapshot and silently undo it.
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
            if (result.payload.consultation) state = result.payload.consultation;
            return result.payload;
          }
          SaveState.set('error');
          Toast.error(errorMessage(result));
          return result.payload || { success: false, errorCode: 'HTTP_' + result.status };
        } catch {
          SaveState.set('error');
          Toast.error('Connexion perdue — modification non enregistrée');
          return { success: false, errorCode: 'NETWORK' };
        } finally {
          // Rendering happens outside the request guard: a renderer failure must
          // never be reported to the practitioner as a failed save.
          renderAll();
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

  // Guards every mutation entry point: a closed consultation is read-only and
  // the server rejects it anyway.
  function mutate(url, data) {
    if (isClosed()) {
      Toast.warn('Consultation clôturée — lecture seule');
      return Promise.resolve({ success: false, errorCode: 'CLOSED' });
    }
    return api.mutate(url, data);
  }

  // ═══════════════════════════════════════════════
  //  MODAL SYSTEM
  // ═══════════════════════════════════════════════

  const Modal = (() => {
    let mount = null;

    function close() {
      if (mount) { mount.remove(); mount = null; }
    }

    function open({ title, subtitle = '', body, footer = '', width = '' }) {
      close();
      mount = document.createElement('div');
      mount.className = 'modal-overlay is-open';
      mount.innerHTML = `
        <div class="modal ${width}">
          <header class="modal-head">
            <div>
              <div class="modal-head-title">${esc(title)}</div>
              ${subtitle ? `<div class="modal-head-sub">${esc(subtitle)}</div>` : ''}
            </div>
            <button class="modal-close" data-close aria-label="Fermer">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
            </button>
          </header>
          <div class="modal-body">${body}</div>
          ${footer ? `<footer class="modal-foot">${footer}</footer>` : ''}
        </div>`;
      document.body.appendChild(mount);
      mount.addEventListener('click', event => {
        if (event.target === mount || event.target.closest('[data-close]')) close();
      });
      return mount;
    }

    function el(selector) {
      return mount ? mount.querySelector(selector) : null;
    }

    function isOpen() {
      return mount !== null;
    }

    registerCleanup(close);
    return { open, close, el, isOpen };
  })();

  function confirmModal({ title, message, confirmLabel = 'Confirmer', danger = false, onConfirm }) {
    Modal.open({
      title,
      width: 'modal-narrow',
      body: `<div style="font-size:var(--text-md);color:var(--text-secondary);line-height:var(--lh-loose);padding:var(--space-2) 0">${esc(message)}</div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="confirm-yes">${esc(confirmLabel)}</button>`,
    });
    Modal.el('#confirm-yes')?.addEventListener('click', () => {
      Modal.close();
      onConfirm?.();
    });
  }

  // ═══════════════════════════════════════════════
  //  LIVE TIMER
  // ═══════════════════════════════════════════════

  (() => {
    const timerEl = document.getElementById('consultation-timer');
    const startedAt = parseUtcDate(state.startedAtUtc);
    if (!timerEl || !startedAt) return;
    const closedAt = parseUtcDate(state.closedAtUtc);
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
  //  REFERENCE DATA LOOKUPS
  // ═══════════════════════════════════════════════

  const vitalTypeById = new Map((REF.vitalTypes || []).map(v => [v.id, v]));
  const bodySystemById = new Map((REF.bodySystems || []).map(s => [s.id, s]));
  const certaintyById = new Map((REF.diagnosisCertainties || []).map(c => [c.id, c]));
  const kindById = new Map((REF.planActionKinds || []).map(k => [k.id, k]));

  function speciesTemplateId() {
    const species = (PATIENT.speciesLabel || '').toLowerCase();
    if (species.startsWith('chat')) return 'chat';
    if (species.startsWith('nac')) return 'reptile';
    return 'chien';
  }

  let currentTemplate = speciesTemplateId();

  function currentTemplateDef() {
    const templates = REF.speciesTemplates || [];
    return templates.find(t => t.id === currentTemplate) || templates[0] || { name: '', systems: [] };
  }

  // ═══════════════════════════════════════════════
  //  RENDERERS
  // ═══════════════════════════════════════════════

  function renderAll() {
    document.body.classList.toggle('consultation-readonly', isClosed());
    renderPatientWarnings();
    renderMotifs();
    renderVitals();
    renderTexts();
    renderExam();
    renderDiagnoses();
    renderPlan();
    renderPrescription();
    renderBilling();
    applyReadonly();
  }

  function applyReadonly() {
    if (!isClosed()) return;
    [
      '#motif-strip .chip-add', '#vitals-strip .chip-add', '#qO-ras-all',
      '#qA-add-btn', '#qA-suggest-btn', '#qP-add-btn', '#qP-template-btn',
      '#btn-add-medication', '#btn-close-consultation',
    ].forEach(selector => {
      const el = $(selector);
      if (el) el.setAttribute('disabled', 'disabled');
    });
    ['#q-subjective', '#q-objective', '#team-memo', '#motif-free-text'].forEach(selector => {
      const el = $(selector);
      if (el) el.setAttribute('readonly', 'readonly');
    });
  }

  // ── Patient banner badges ──
  function renderPatientWarnings() {
    const host = document.getElementById('pb-warnings');
    if (!host) return;
    const alerts = state.medicalAlerts || [];
    host.innerHTML = alerts.map(alert => {
      const isAllergy = alert.kind === 'ALLERGY';
      const icon = isAllergy
        ? '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>'
        : '<svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5l2 1.5"/></svg>';
      const label = isAllergy ? `Allergie ${alert.label}` : `Suivi ${alert.label}`;
      return `<span class="pb-warn${isAllergy ? '' : ' is-info'}"${alert.note ? ` title="${esc(alert.note)}"` : ''}>${icon}${esc(label)}</span>`;
    }).join('');
  }

  // ── Motif strip ──
  function renderMotifs() {
    const host = document.getElementById('motif-strip');
    if (!host) return;
    const motifs = state.motifs || [];
    host.innerHTML = `
      ${motifs.map(motif => `
        <button class="chip is-selected" data-motif-id="${esc(motif.id)}" title="Retirer ce motif">
          ${esc(motif.label)}
        </button>`).join('')}
      <button class="chip chip-add" id="motif-add">+ ajouter</button>`;
    setTextarea('#motif-free-text', state.chiefComplaint);
  }

  // ── Constantes strip ──
  function renderVitals() {
    const host = document.getElementById('vitals-strip');
    if (!host) return;

    const weight = state.vitals?.weightKg ?? null;
    const temperature = state.vitals?.temperatureC ?? null;
    const pills = [];

    pills.push(vitalPill({
      key: 'weight',
      label: 'Poids',
      value: weight !== null ? fmtNumber(weight) : '—',
      unit: 'kg',
      delta: weightDelta(weight),
      removable: false,
    }));

    if (temperature !== null) {
      pills.push(vitalPill({
        key: 'temperature',
        label: 'Température',
        value: fmtNumber(temperature),
        unit: '°C',
        removable: false,
      }));
    }

    (state.typedVitals || []).forEach(vital => {
      const def = vitalTypeById.get(vital.type);
      pills.push(vitalPill({
        key: vital.type,
        label: def?.label || vital.type,
        value: vital.value,
        unit: def?.unit || '',
        removable: true,
      }));
    });

    host.innerHTML = `
      ${pills.join('')}
      <button class="chip chip-add" id="vital-add">
        <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 3v10M3 8h10"/></svg>
        ajouter une constante
      </button>
      <span class="strip-text" style="margin-left:auto;color:var(--text-subtle);font-size:var(--text-sm)">
        ${esc(availableVitalLabels())}
      </span>`;
  }

  function vitalPill({ key, label, value, unit, delta = '', removable }) {
    return `
      <div class="vital-pill${removable ? ' has-row-x' : ''}" data-vital="${esc(key)}">
        <div class="vital-pill-label">${esc(label)}</div>
        <div class="vital-pill-value">${esc(value)}${unit ? ` <span class="unit">${esc(unit)}</span>` : ''}</div>
        ${delta}
        ${removable ? `<button class="row-x" data-remove-vital="${esc(key)}" title="Retirer" aria-label="Retirer">
          <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
        </button>` : ''}
      </div>`;
  }

  function weightDelta(currentWeight) {
    if (currentWeight === null) return '';
    const previous = HISTORY.find(row => row.weightKg !== null && row.weightKg !== undefined);
    if (!previous) return '';
    const delta = Number(currentWeight) - Number(previous.weightKg);
    if (!Number.isFinite(delta) || Math.abs(delta) < 0.05) return '';
    const direction = delta < 0 ? 'is-down' : 'is-up';
    const sign = delta < 0 ? '−' : '+';
    return `<span class="vital-pill-delta ${direction}">${sign}${fmtNumber(Math.abs(delta))} kg</span>`;
  }

  function availableVitalLabels() {
    const recorded = new Set((state.typedVitals || []).map(v => v.type));
    const labels = (REF.vitalTypes || []).filter(v => !recorded.has(v.id)).map(v => v.label);
    return labels.length ? labels.join(' · ') : 'Toutes les constantes sont renseignées';
  }

  // ── SOAP free texts ──
  function renderTexts() {
    setTextarea('#q-subjective', state.subjectiveText);
    setTextarea('#q-objective', state.objectiveObservations);
    setTextarea('#team-memo', state.teamMemo);
  }

  function setTextarea(selector, value) {
    const el = $(selector);
    if (!el || el === document.activeElement) return;
    el.value = value || '';
  }

  // ── Quad O — clinical exam ──
  function examBySystem(systemId) {
    return (state.examSystems || []).find(exam => exam.system === systemId) || null;
  }

  /**
   * The active template's systems, plus any system already recorded under
   * another template — switching template must never hide stored data.
   */
  function displayedSystems() {
    const templateSystems = currentTemplateDef().systems;
    const recordedElsewhere = (state.examSystems || [])
      .map(exam => exam.system)
      .filter(systemId => !templateSystems.includes(systemId));

    return [...templateSystems, ...recordedElsewhere];
  }

  function renderExam() {
    const list = document.getElementById('qO-listF');
    if (!list) return;

    const nameEl = document.getElementById('qO-template-btn-name');
    if (nameEl) nameEl.textContent = currentTemplateDef().name;

    list.innerHTML = displayedSystems().map(systemId => {
      const def = bodySystemById.get(systemId);
      if (!def) return '';
      const exam = examBySystem(systemId);
      const status = exam?.status || 'UNTESTED';
      const note = status === 'ANOMALY' && exam?.notes ? exam.notes.split(/[.\n]/)[0].trim() : '';
      return `
        <div class="rowF is-${status.toLowerCase()}" data-system="${esc(systemId)}"${note ? ` title="${esc(exam.notes)}"` : ''}>
          <div class="rowF-icon">${ICONS[def.icon] || ''}</div>
          <div class="rowF-name">${esc(def.shortLabel)}${note ? '<span class="rowF-dot" aria-hidden="true"></span>' : ''}</div>
          <div style="flex:1"></div>
          <div class="state-toggle" data-system="${esc(systemId)}">
            ${(REF.examStatuses || []).map(s => `
              <button class="state-btn${s.id === status ? ' is-active' : ''}" data-status="${esc(s.id)}" type="button">${esc(s.label)}</button>`).join('')}
          </div>
        </div>`;
    }).join('');
  }

  // ── Quad A — diagnoses ──
  function renderDiagnoses() {
    const list = document.getElementById('qA-dx-list');
    const count = document.getElementById('qA-dx-count');
    if (!list) return;

    const diagnoses = [...(state.diagnoses || [])].sort((a, b) => Number(b.isPrimary) - Number(a.isPrimary));
    if (count) count.textContent = diagnoses.length === 0 ? 'vide' : `${diagnoses.length} dx`;

    if (diagnoses.length === 0) {
      list.innerHTML = `
        <div class="dx-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div class="dx-empty-title">Aucun diagnostic posé</div>
          <div class="dx-empty-sub">Ajoute un dx ou demande une suggestion IA</div>
        </div>`;
      return;
    }

    list.innerHTML = diagnoses.map(dx => {
      const certainty = certaintyById.get(dx.certainty) || { shortLabel: dx.certainty };
      const cssKey = CERTAINTY_CSS[dx.certainty] || 'possible';
      const noteHtml = dx.note
        ? `<span class="dx-sep">·</span><span class="dx-note">${esc(dx.note.split(/[.\n]/)[0].trim())}</span>`
        : '<div style="flex:1"></div>';
      const sourceChip = dx.source === 'AI_SUGGESTION'
        ? `<span class="dx-source-chip" title="Suggestion IA acceptée">${ICONS.sparkles} IA</span>`
        : '';
      return `
        <div class="dx-row${dx.isPrimary ? ' is-primary' : ''}${dx.certainty === 'EXCLUDED' ? ' is-excluded' : ''}" data-dx-id="${esc(dx.id)}">
          <button class="dx-star${dx.isPrimary ? ' is-on' : ''}" data-dx-star="${esc(dx.id)}"
                  aria-label="${dx.isPrimary ? 'Retirer principal' : 'Définir comme principal'}"
                  title="${dx.isPrimary ? 'Diagnostic principal' : 'Définir comme principal'}">
            ${dx.isPrimary ? ICONS.starFilled : ICONS.starOutline}
          </button>
          <span class="dx-name-wrap">
            <span class="dx-name">${esc(dx.label)}</span>
            ${dx.code ? `<span class="dx-code-tooltip">${esc(dx.code)}</span>` : ''}
          </span>
          ${noteHtml}
          ${sourceChip}
          <span class="dx-certainty dx-certainty--${cssKey}">${esc(certainty.shortLabel)}</span>
          <button class="dx-x" data-dx-remove="${esc(dx.id)}" aria-label="Retirer">${ICONS.close}</button>
        </div>`;
    }).join('');
  }

  // ── Quad P — plan ──
  function renderPlan() {
    const list = document.getElementById('qP-plan-list');
    const count = document.getElementById('qP-plan-count');
    if (!list) return;

    const actions = state.planActions || [];
    if (count) {
      count.textContent = actions.length === 0
        ? 'vide'
        : `${actions.length} action${actions.length > 1 ? 's' : ''}`;
    }

    if (actions.length === 0) {
      list.innerHTML = `
        <div class="plan-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="14" y2="14"/></svg>
          <div class="plan-empty-title">Aucune action planifiée</div>
          <div class="plan-empty-sub">Clic "Ajouter" pour ouvrir le panel multi-colonnes</div>
        </div>`;
      return;
    }

    list.innerHTML = actions.map(action => {
      const style = KIND_STYLE[action.kind] || KIND_STYLE.OTHER;
      return `
        <div class="plan-item plan-item--${style.cssKey}" data-plan-id="${esc(action.id)}">
          <div class="plan-item-icon">${ICONS[style.icon]}</div>
          <div class="plan-item-desc">${esc(action.description)}</div>
          <span class="plan-item-meta">${esc(planMeta(action))}</span>
          <button class="plan-item-x" data-plan-remove="${esc(action.id)}" aria-label="Retirer">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l10 10M13 3L3 13"/></svg>
          </button>
        </div>`;
    }).join('');
  }

  function planMeta(action) {
    switch (action.kind) {
      case 'PERFORMED_ACT':
        return action.unitPriceMinorUnits !== null && action.unitPriceMinorUnits !== undefined
          ? fmtMoney(action.unitPriceMinorUnits, action.currency || 'EUR')
          : 'non facturé';
      case 'MEDICATION_PRESCRIPTION':
        return action.posology || 'ordonnance';
      case 'FOLLOW_UP_APPOINTMENT':
        return action.followUpDays ? `RDV J+${action.followUpDays}` : 'RDV';
      case 'ADVICE':
        return 'conseil';
      default:
        return 'autre';
    }
  }

  // ── Ordonnance ──
  function renderPrescription() {
    const list = document.getElementById('rx-list');
    const title = document.getElementById('rx-title');
    const warning = document.getElementById('rx-warning');
    if (!list) return;

    const lines = state.prescriptionLines || [];
    if (title) {
      title.textContent = lines.length === 0
        ? 'Ordonnance'
        : `Ordonnance · ${lines.length} médicament${lines.length > 1 ? 's' : ''}`;
    }

    const warnings = state.allergyWarnings || [];
    if (warning) {
      if (warnings.length === 0) {
        warning.hidden = true;
        warning.innerHTML = '';
      } else {
        warning.hidden = false;
        const byAllergy = new Map();
        warnings.forEach(w => {
          const medications = byAllergy.get(w.alertLabel) || new Set();
          medications.add(`${w.medication} (${w.substance})`);
          byAllergy.set(w.alertLabel, medications);
        });
        warning.innerHTML = `
          <svg class="icon-14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5L14.5 13h-13z"/><path d="M8 6v3M8 11h.01" stroke-width="2"/></svg>
          <span>${[...byAllergy].map(([allergy, medications]) =>
            `<strong style="font-weight:var(--weight-medium)">Allergie ${esc(allergy)}</strong> — vérifiez ${esc([...medications].join(', '))}.`
          ).join('<br>')}</span>`;
      }
    }

    if (lines.length === 0) {
      list.innerHTML = '<div class="m-empty" style="padding:var(--space-3) 0">Aucun médicament prescrit.</div>';
      return;
    }

    list.innerHTML = lines.map(line => `
      <div class="rx-item has-row-x" data-rx-id="${esc(line.id)}">
        <div class="rx-item-row">
          <span class="rx-name">${esc(line.label)}</span>
          <span class="rx-price">${esc(fmtMoney(line.unitPriceMinorUnits, line.currency))}</span>
          <button class="row-x" data-rx-remove="${esc(line.id)}" title="Retirer ce médicament" aria-label="Retirer ce médicament">
            <svg class="icon-12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3l10 10M13 3L3 13"/></svg>
          </button>
        </div>
        <div class="rx-poso">${esc(posologyLabel(line)) || '—'}</div>
      </div>`).join('');
  }

  function posologyLabel(line) {
    return [
      line.dose,
      line.frequency,
      line.durationDays ? `${line.durationDays} j` : null,
      line.route,
    ].filter(Boolean).join(' · ');
  }

  // ── Facturation ──
  function renderBilling() {
    const list = document.getElementById('bill-list');
    const detail = document.getElementById('bill-total-detail');
    const amount = document.getElementById('bill-total-amount');
    if (!list) return;

    const lines = state.billingLines || [];
    const totals = state.totals || {};
    const currency = totals.currency || 'EUR';

    list.innerHTML = lines.length === 0
      ? '<div class="m-empty" style="padding:var(--space-3) 0">Aucune ligne à facturer pour l\'instant.</div>'
      : lines.map(line => `
        <div class="bill-row" data-bill-id="${esc(line.id)}">
          <div class="bill-name">
            ${esc(line.label)}
            <span class="bill-qty">${esc(line.code || line.source)}${line.quantity !== 1 ? ` · ×${esc(fmtNumber(line.quantity))}` : ''}</span>
          </div>
          <div class="bill-price">${esc(fmtMoney(line.totalMinorUnits, line.currency))}</div>
        </div>`).join('');

    if (detail) {
      detail.textContent = `HT ${fmtMoney(totals.totalHtMinorUnits || 0, currency)} · TVA ${fmtMoney(totals.totalTvaMinorUnits || 0, currency)}`;
    }
    if (amount) {
      amount.textContent = fmtMoney(totals.totalTtcMinorUnits || 0, currency);
    }
  }

  // ═══════════════════════════════════════════════
  //  MOTIFS
  // ═══════════════════════════════════════════════

  // The whole list is replaced server-side, so the payload is built inside the
  // queued task from the freshest state rather than from a click-time snapshot.
  function toggleMotif(label) {
    return mutate(EP.motifs, () => {
      const labels = (state.motifs || []).map(motif => motif.label);
      const index = labels.findIndex(existing => existing.toLowerCase() === label.toLowerCase());

      if (index >= 0) {
        labels.splice(index, 1);
      } else {
        labels.push(label);
      }

      return { labels };
    });
  }

  function removeMotif(motifId) {
    return mutate(EP.motifs, () => ({
      labels: (state.motifs || []).filter(motif => motif.id !== motifId).map(motif => motif.label),
    }));
  }

  function openMotifModal() {
    const selected = new Set((state.motifs || []).map(m => m.label.toLowerCase()));
    const groups = (REF.presetMotifs || []).map(group => `
      <div class="m-list-section">
        <div class="qa-group-label">${esc(group.category)}</div>
        <div class="dx-quick-pills">
          ${group.items.map(item => `
            <button class="dx-quick-pill${selected.has(item.toLowerCase()) ? ' is-selected' : ''}" type="button" data-motif-label="${esc(item)}">
              ${esc(item)}
            </button>`).join('')}
        </div>
      </div>`).join('');

    Modal.open({
      title: 'Motifs de consultation',
      subtitle: 'Sélectionnez un ou plusieurs motifs',
      width: 'modal-wide',
      body: `
        ${groups}
        <div class="qa-ft-section">
          <div class="qa-ft-label">Motif libre</div>
          <div class="qa-ft-row">
            <input type="text" class="qa-ft-input" id="motif-custom" placeholder="Saisir un motif…" autocomplete="off">
            <button class="btn btn-secondary btn-sm" id="motif-custom-add" type="button">Ajouter</button>
          </div>
        </div>`,
      footer: '<button class="btn btn-primary" data-close>Terminé</button>',
    });

    const refreshPills = () => {
      const current = new Set((state.motifs || []).map(motif => motif.label.toLowerCase()));
      Modal.el('.modal-body')?.querySelectorAll('[data-motif-label]').forEach(pill => {
        pill.classList.toggle('is-selected', current.has(pill.dataset.motifLabel.toLowerCase()));
      });
    };

    const applyLabel = label => toggleMotif(label).then(payload => {
      if (payload?.success) refreshPills();
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      const pill = event.target.closest('[data-motif-label]');
      if (pill) applyLabel(pill.dataset.motifLabel);
    });

    Modal.el('#motif-custom-add')?.addEventListener('click', () => {
      const input = Modal.el('#motif-custom');
      const value = (input?.value || '').trim();
      if (value) applyLabel(value);
    });
  }

  // ═══════════════════════════════════════════════
  //  CONSTANTES
  // ═══════════════════════════════════════════════

  function openWeightModal() {
    Modal.open({
      title: 'Poids & température',
      subtitle: 'Constantes principales',
      width: 'modal-narrow',
      body: `
        <div class="field-block">
          <div class="field-row field-row--2">
            <div>
              <label class="field-label-mini" for="vital-weight">Poids (kg)</label>
              <input class="field-input-m mono" type="number" step="0.01" min="0" id="vital-weight"
                     value="${esc(state.vitals?.weightKg ?? '')}">
            </div>
            <div>
              <label class="field-label-mini" for="vital-temp">Température (°C)</label>
              <input class="field-input-m mono" type="number" step="0.1" min="30" max="45" id="vital-temp"
                     value="${esc(state.vitals?.temperatureC ?? '')}">
            </div>
          </div>
        </div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="vital-save">Enregistrer</button>`,
    });

    Modal.el('#vital-save')?.addEventListener('click', () => {
      const weight = Modal.el('#vital-weight')?.value || '';
      const temperature = Modal.el('#vital-temp')?.value || '';
      if (!weight && !temperature) {
        Toast.warn('Renseignez au moins une constante');
        return;
      }
      Modal.close();
      mutate(EP.vitals, { weightKg: weight, temperatureC: temperature });
    });
  }

  function openTypedVitalModal(typeDef, currentValue) {
    Modal.open({
      title: typeDef.label,
      subtitle: typeDef.range ? `Valeurs usuelles : ${typeDef.range}` : '',
      width: 'modal-narrow',
      body: `
        <div class="field-block">
          <label class="field-label-mini" for="typed-vital-value">Valeur${typeDef.unit ? ` (${esc(typeDef.unit)})` : ''}</label>
          <input class="field-input-m mono" id="typed-vital-value" autocomplete="off"
                 value="${esc(currentValue ?? typeDef.default ?? '')}">
        </div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="typed-vital-save">Enregistrer</button>`,
    });

    const save = () => {
      const value = (Modal.el('#typed-vital-value')?.value || '').trim();
      if (!value) {
        Toast.warn('Valeur obligatoire');
        return;
      }
      Modal.close();
      mutate(EP.typedVitalRecord, { type: typeDef.id, value });
    };

    Modal.el('#typed-vital-save')?.addEventListener('click', save);
    Modal.el('#typed-vital-value')?.addEventListener('keydown', event => {
      if (event.key === 'Enter') save();
    });
    Modal.el('#typed-vital-value')?.focus();
  }

  function openAddVitalModal() {
    const recorded = new Set((state.typedVitals || []).map(v => v.type));
    const available = (REF.vitalTypes || []).filter(v => !recorded.has(v.id));

    Modal.open({
      title: 'Ajouter une constante',
      subtitle: 'Sélectionnez le type de mesure',
      width: 'modal-narrow',
      body: available.length === 0
        ? '<div class="qa-empty">Toutes les constantes sont déjà renseignées.</div>'
        : `<div class="m-list">${available.map(v => `
            <button class="m-item" type="button" data-vital-type="${esc(v.id)}">
              <div class="m-item-content">
                <div class="m-item-name">${esc(v.label)}</div>
                <div class="m-item-meta">${esc(v.range)}${v.unit ? ` · ${esc(v.unit)}` : ''}</div>
              </div>
            </button>`).join('')}</div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      const button = event.target.closest('[data-vital-type]');
      if (!button) return;
      const typeDef = vitalTypeById.get(button.dataset.vitalType);
      Modal.close();
      if (typeDef) openTypedVitalModal(typeDef, null);
    });
  }

  // ═══════════════════════════════════════════════
  //  QUAD O — exam drill-down
  // ═══════════════════════════════════════════════

  function openExamModal(systemId) {
    const def = bodySystemById.get(systemId);
    if (!def) return;
    const exam = examBySystem(systemId);
    const structured = exam?.structuredData || {};

    let structuredHtml = '';
    if (def.drilldown === 'cardio') {
      structuredHtml = `
        <div class="field-block">
          <div class="field-row field-row--3">
            <div>
              <label class="field-label-mini" for="f-fc">Fréquence (bpm)</label>
              <input class="field-input-m mono" type="number" id="f-fc" placeholder="—" value="${esc(structured.fc || '')}">
            </div>
            <div>
              <label class="field-label-mini" for="f-rhythm">Rythme</label>
              <select class="field-select-m" id="f-rhythm">
                ${selectOptions(['', 'Régulier', 'Arythmique'], structured.rhythm)}
              </select>
            </div>
            <div>
              <label class="field-label-mini" for="f-murmur">Souffle (0-6)</label>
              <select class="field-select-m" id="f-murmur">
                ${selectOptions(['', '0', '1', '2', '3', '4', '5', '6'], structured.murmur)}
              </select>
            </div>
          </div>
        </div>`;
    } else if (def.drilldown === 'loco') {
      structuredHtml = `
        <div class="field-block">
          <div class="field-row field-row--3">
            <div>
              <label class="field-label-mini">Membre</label>
              <div class="limb-toggle" id="f-limb">
                ${['AG', 'AD', 'PG', 'PD'].map(limb => `
                  <button type="button" class="limb-btn${structured.limb === limb ? ' is-active' : ''}" data-limb="${limb}">${limb}</button>`).join('')}
              </div>
            </div>
            <div>
              <label class="field-label-mini" for="f-grade">Grade (1-4)</label>
              <select class="field-select-m" id="f-grade">
                ${selectOptions(['', '1', '2', '3', '4'], structured.grade)}
              </select>
            </div>
            <div>
              <label class="field-label-mini" for="f-type">Type</label>
              <select class="field-select-m" id="f-type">
                ${selectOptions(['', 'Mécanique', 'Inflammatoire', 'Neurologique'], structured.type)}
              </select>
            </div>
          </div>
          <div class="field-row">
            <div>
              <label class="field-label-mini" for="f-region">Région suspectée</label>
              <input class="field-input-m" id="f-region" placeholder="ex : grasset, hanche, épaule…" value="${esc(structured.region || '')}">
            </div>
          </div>
        </div>`;
    } else if (def.drilldown === 'derma') {
      structuredHtml = `
        <div class="field-block">
          <div class="field-row field-row--2">
            <div>
              <label class="field-label-mini" for="f-region">Région</label>
              <input class="field-input-m" id="f-region" placeholder="ex : dos, oreilles, abdomen…" value="${esc(structured.region || '')}">
            </div>
            <div>
              <label class="field-label-mini" for="f-lesion">Type de lésion</label>
              <select class="field-select-m" id="f-lesion">
                ${selectOptions(['', 'Érythème', 'Croûte', 'Alopécie', 'Pustule', 'Lichénification'], structured.lesion)}
              </select>
            </div>
          </div>
        </div>`;
    }

    Modal.open({
      title: def.label,
      subtitle: "Détails de l'anomalie",
      width: 'modal-wide',
      body: `
        ${structuredHtml}
        <div class="field-block">
          <label class="field-label-mini" for="f-notes">Observations détaillées</label>
          <textarea class="modal-textarea" id="f-notes" rows="5" placeholder="Décris ce que tu observes…">${esc(exam?.notes || '')}</textarea>
        </div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="exam-save">Enregistrer</button>`,
    });

    const limbToggle = Modal.el('#f-limb');
    let selectedLimb = structured.limb || '';
    limbToggle?.addEventListener('click', event => {
      const button = event.target.closest('.limb-btn');
      if (!button) return;
      limbToggle.querySelectorAll('.limb-btn').forEach(b => b.classList.remove('is-active'));
      button.classList.add('is-active');
      selectedLimb = button.dataset.limb;
    });

    Modal.el('#exam-save')?.addEventListener('click', () => {
      const structuredData = {};
      if (def.drilldown === 'cardio') {
        structuredData.fc = Modal.el('#f-fc')?.value || '';
        structuredData.rhythm = Modal.el('#f-rhythm')?.value || '';
        structuredData.murmur = Modal.el('#f-murmur')?.value || '';
      } else if (def.drilldown === 'loco') {
        structuredData.limb = selectedLimb;
        structuredData.grade = Modal.el('#f-grade')?.value || '';
        structuredData.type = Modal.el('#f-type')?.value || '';
        structuredData.region = Modal.el('#f-region')?.value || '';
      } else if (def.drilldown === 'derma') {
        structuredData.region = Modal.el('#f-region')?.value || '';
        structuredData.lesion = Modal.el('#f-lesion')?.value || '';
      }
      const notes = Modal.el('#f-notes')?.value || '';
      Modal.close();
      mutate(EP.examSystem, {
        system: systemId,
        status: 'ANOMALY',
        notes,
        structuredData,
      }).then(() => annotate('O', `${def.label} — détails enregistrés`));
    });

    Modal.el('#f-notes')?.focus();
  }

  function selectOptions(values, selected) {
    return values.map(value => {
      const label = value === '' ? '—' : value;
      const isSelected = String(selected ?? '') === value ? ' selected' : '';
      return `<option value="${esc(value)}"${isSelected}>${esc(label)}</option>`;
    }).join('');
  }

  function openExamTemplateMenu() {
    const templates = REF.speciesTemplates || [];
    Modal.open({
      title: "Template d'examen",
      subtitle: 'Choisissez la grille de systèmes à afficher',
      width: 'modal-narrow',
      body: `<div class="m-list">${templates.map(tpl => `
        <button class="m-item${tpl.id === currentTemplate ? ' is-selected' : ''}" type="button"
                data-template="${esc(tpl.id)}" data-enabled="${tpl.enabled}">
          <div class="m-item-content">
            <div class="m-item-name">${tpl.emoji} ${esc(tpl.name)}</div>
            <div class="m-item-meta">${tpl.systems.length} systèmes${tpl.enabled ? '' : ' · bientôt'}</div>
          </div>
        </button>`).join('')}</div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });
    Modal.el('.modal-body')?.addEventListener('click', event => {
      const button = event.target.closest('[data-template]');
      if (!button) return;
      if (button.dataset.enabled !== 'true') {
        Toast.info('Template bientôt disponible');
        return;
      }
      currentTemplate = button.dataset.template;
      Modal.close();
      renderExam();
      annotate('O', `Template : ${currentTemplateDef().name}`);
    });
  }

  // ═══════════════════════════════════════════════
  //  QUAD A — diagnoses
  // ═══════════════════════════════════════════════

  function openDiagnosisModal(existing) {
    const isEdit = Boolean(existing);
    const certainties = REF.diagnosisCertainties || [];

    Modal.open({
      title: isEdit ? 'Modifier le diagnostic' : 'Ajouter un diagnostic',
      subtitle: isEdit ? existing.label : 'Recherchez dans la nomenclature ou saisissez librement',
      width: 'modal-wide',
      body: `
        ${isEdit ? '' : `
          <div class="qa-search-wrap">
            <span class="qa-search-icon">${ICONS.search}</span>
            <input type="text" class="qa-search" id="dx-search" placeholder="Rechercher un diagnostic…" autocomplete="off">
          </div>
          <div class="qa-main-list" id="dx-results" style="max-height:220px"></div>`}
        <div class="field-block">
          <label class="field-label-mini" for="dx-label">Libellé</label>
          <input class="field-input-m" id="dx-label" autocomplete="off" value="${esc(existing?.label || '')}">
        </div>
        <div class="field-block">
          <label class="field-label-mini" for="dx-code">Code (optionnel)</label>
          <input class="field-input-m mono" id="dx-code" autocomplete="off" value="${esc(existing?.code || '')}">
        </div>
        <div class="field-block">
          <label class="field-label-mini">Certitude</label>
          <div class="certainty-grid" id="dx-certainty">
            ${certainties.map(c => `
              <button type="button" class="cert-btn${(existing?.certainty || 'PROBABLE') === c.id ? ' is-active' : ''}" data-certainty="${esc(c.id)}">
                ${esc(c.label)}
              </button>`).join('')}
          </div>
        </div>
        <div class="field-block">
          <label class="field-label-mini" for="dx-note">Note</label>
          <textarea class="modal-textarea" id="dx-note" rows="3" placeholder="Arguments, examens à prévoir…">${esc(existing?.note || '')}</textarea>
        </div>
        ${isEdit ? '' : `
          <div class="primary-toggle">
            <label class="primary-toggle-label">
              <input type="checkbox" id="dx-primary"> Diagnostic principal
            </label>
            <span class="primary-toggle-desc">Un seul diagnostic principal par consultation.</span>
          </div>`}`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="dx-save">${isEdit ? 'Enregistrer' : 'Ajouter'}</button>`,
    });

    let certainty = existing?.certainty || 'PROBABLE';
    Modal.el('#dx-certainty')?.addEventListener('click', event => {
      const button = event.target.closest('[data-certainty]');
      if (!button) return;
      certainty = button.dataset.certainty;
      Modal.el('#dx-certainty').querySelectorAll('.cert-btn').forEach(b => b.classList.remove('is-active'));
      button.classList.add('is-active');
    });

    if (!isEdit) {
      const results = Modal.el('#dx-results');
      const renderResults = term => {
        const needle = term.trim().toLowerCase();
        const items = (REF.nomenclature || []).filter(entry =>
          !needle || entry.name.toLowerCase().includes(needle) || entry.code.toLowerCase().includes(needle));
        results.innerHTML = items.length === 0
          ? '<div class="qa-list-empty">Aucun résultat — saisissez un libellé libre ci-dessous.</div>'
          : items.map(entry => `
            <button class="qa-row" type="button" data-nom-code="${esc(entry.code)}" data-nom-name="${esc(entry.name)}">
              <div class="qa-row-content">
                <div class="qa-row-name">${esc(entry.name)}</div>
                <div class="qa-row-meta">${esc(entry.code)} · ${esc(entry.system)}</div>
              </div>
            </button>`).join('');
      };
      renderResults('');
      Modal.el('#dx-search')?.addEventListener('input', event => renderResults(event.target.value));
      results?.addEventListener('click', event => {
        const row = event.target.closest('[data-nom-code]');
        if (!row) return;
        Modal.el('#dx-label').value = row.dataset.nomName;
        Modal.el('#dx-code').value = row.dataset.nomCode;
      });
    }

    Modal.el('#dx-save')?.addEventListener('click', () => {
      const label = (Modal.el('#dx-label')?.value || '').trim();
      if (!label) {
        Toast.warn('Le libellé est obligatoire');
        return;
      }
      const payload = {
        label,
        code: Modal.el('#dx-code')?.value || '',
        certainty,
        note: Modal.el('#dx-note')?.value || '',
      };
      Modal.close();
      if (isEdit) {
        mutate(EP.diagnosisUpdate, { ...payload, diagnosisId: existing.id })
          .then(() => annotate('A', 'Diagnostic mis à jour'));
      } else {
        mutate(EP.diagnosisAdd, {
          ...payload,
          isPrimary: Modal.el('#dx-primary')?.checked ? 1 : 0,
          source: 'MANUAL',
        }).then(() => annotate('A', 'Diagnostic ajouté'));
      }
    });
  }

  // "Suggérer (IA)" stays a mock: the suggestions are canned, but accepting one
  // persists through the real endpoint with source=AI_SUGGESTION.
  function openAiSuggestionModal() {
    const suggestions = (REF.nomenclature || []).slice(0, 4).map((entry, index) => ({
      ...entry,
      score: 92 - index * 13,
      reason: 'Suggestion de démonstration — le moteur IA sera branché ultérieurement.',
    }));

    Modal.open({
      title: 'Suggestions IA',
      subtitle: 'Diagnostics proposés à partir du dossier (démo)',
      width: 'modal-wide',
      body: `<div class="m-list">${suggestions.map(s => `
        <div class="ai-suggestion">
          <div class="ai-suggestion-content">
            <div class="ai-suggestion-name">${esc(s.name)}</div>
            <div class="ai-suggestion-code">${esc(s.code)}</div>
            <div class="ai-suggestion-reason">${esc(s.reason)}</div>
          </div>
          <div class="ai-score">
            <div class="ai-score-bar"><div class="ai-score-bar-fill" style="width:${s.score}%"></div></div>
            <div class="ai-score-value">${s.score}%</div>
          </div>
          <button class="btn btn-secondary btn-sm" type="button" data-accept-code="${esc(s.code)}" data-accept-name="${esc(s.name)}">
            Accepter
          </button>
        </div>`).join('')}</div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      const button = event.target.closest('[data-accept-code]');
      if (!button) return;
      Modal.close();
      mutate(EP.diagnosisAdd, {
        label: button.dataset.acceptName,
        code: button.dataset.acceptCode,
        certainty: 'POSSIBLE',
        note: '',
        isPrimary: 0,
        source: 'AI_SUGGESTION',
      }).then(() => annotate('A', 'Suggestion IA acceptée'));
    });
  }

  // ═══════════════════════════════════════════════
  //  QUAD P — plan
  // ═══════════════════════════════════════════════

  function openPlanModal() {
    const kinds = REF.planActionKinds || [];

    Modal.open({
      title: 'Ajouter au plan',
      subtitle: 'Actes, médicaments, RDV de suivi ou conseils',
      width: 'modal--xwide',
      body: `
        <div class="qa-search-wrap">
          <span class="qa-search-icon">${ICONS.search}</span>
          <input type="text" class="qa-search" id="plan-search" placeholder="Rechercher un acte dans le catalogue…" autocomplete="off">
        </div>
        <div class="qa-main-area">
          <div class="qa-main-header">
            <span>Catalogue actes</span>
            <span class="qa-main-count" id="plan-search-count">0</span>
          </div>
          <div class="qa-main-list" id="plan-search-results"></div>
        </div>
        <div class="qa-ft-section">
          <div class="qa-ft-label">Suggestions</div>
          <div class="qa-grid" id="plan-suggestions"></div>
        </div>
        <div class="qa-ft-section">
          <div class="qa-ft-label">Ajouter un item libre</div>
          <div class="qa-ft-row">
            <input type="text" class="qa-ft-input" id="plan-free-text" placeholder="Tapez la description de l'item libre…" autocomplete="off">
            <div class="qa-ft-chips">
              ${kinds.map(kind => `
                <button class="kind-chip kind-chip--${KIND_STYLE[kind.id]?.cssKey || 'oth'}" type="button" data-free-kind="${esc(kind.id)}">
                  ${ICONS[KIND_STYLE[kind.id]?.icon || 'oth']}<span>${esc(kind.singularLabel)}</span>
                </button>`).join('')}
            </div>
          </div>
        </div>`,
      footer: '<div class="modal-foot-hint">Les actes facturables alimentent la facturation.</div><button class="btn btn-primary" data-close>Terminé</button>',
    });

    const suggestionsHost = Modal.el('#plan-suggestions');
    if (suggestionsHost) {
      const groups = REF.planSuggestions || {};
      suggestionsHost.innerHTML = Object.entries(groups).map(([kindId, items]) => `
        <div class="qa-col">
          <div class="qa-group-label">${esc(kindById.get(kindId)?.label || kindId)}</div>
          ${items.map(item => `
            <button class="qa-item" type="button" data-kind="${esc(kindId)}" data-description="${esc(item.name)}"
                    data-follow-up="${item.followUpDays ?? ''}" data-posology="${esc(item.posology || '')}"
                    data-duration="${item.durationDays ?? ''}">
              <span class="qa-item-name">${esc(item.name)}</span>
              ${item.followUpDays ? `<span class="qa-item-code">J+${item.followUpDays}</span>` : ''}
            </button>`).join('')}
        </div>`).join('');

      suggestionsHost.addEventListener('click', event => {
        const button = event.target.closest('[data-kind]');
        if (!button) return;
        addPlanAction({
          kind: button.dataset.kind,
          description: button.dataset.description,
          followUpDays: button.dataset.followUp,
          posology: button.dataset.posology,
          durationDays: button.dataset.duration,
        });
      });
    }

    const resultsHost = Modal.el('#plan-search-results');
    const countHost = Modal.el('#plan-search-count');
    const runSearch = debounce(async term => {
      if (!term.trim()) {
        resultsHost.innerHTML = '<div class="qa-list-hint">Tapez au moins deux lettres pour chercher un acte.</div>';
        if (countHost) countHost.textContent = '0';
        return;
      }
      const payload = await api.get(EP.catalogSearch, { term, kind: 'ACT' });
      const items = payload?.items || [];
      if (countHost) countHost.textContent = String(items.length);
      resultsHost.innerHTML = items.length === 0
        ? '<div class="qa-list-empty">Aucun acte trouvé.</div>'
        : items.map(item => `
          <button class="qa-row qa-row--act" type="button" data-item-id="${esc(item.itemId)}"
                  data-item-name="${esc(item.name)}" data-item-code="${esc(item.code)}">
            <div class="qa-row-icon">${ICONS.act}</div>
            <div class="qa-row-content">
              <div class="qa-row-name">${esc(item.name)}</div>
              <div class="qa-row-meta">${esc(item.code)} · ${esc(fmtMoney(item.basePriceMinorUnits, item.currency))}</div>
            </div>
            <span class="qa-row-add">${ICONS.check}</span>
          </button>`).join('');
    }, 250);

    runSearch('');
    Modal.el('#plan-search')?.addEventListener('input', event => runSearch(event.target.value));

    resultsHost?.addEventListener('click', event => {
      const row = event.target.closest('[data-item-id]');
      if (!row) return;
      addPlanAction({
        kind: 'PERFORMED_ACT',
        description: row.dataset.itemName,
        catalogItemId: row.dataset.itemId,
        catalogCode: row.dataset.itemCode,
      });
    });

    Modal.el('.qa-ft-chips')?.addEventListener('click', event => {
      const chip = event.target.closest('[data-free-kind]');
      if (!chip) return;
      const description = (Modal.el('#plan-free-text')?.value || '').trim();
      if (!description) {
        Toast.warn('Saisissez une description');
        return;
      }
      Modal.el('#plan-free-text').value = '';
      addPlanAction({ kind: chip.dataset.freeKind, description });
    });
  }

  function addPlanAction(data) {
    return mutate(EP.planActionAdd, {
      kind: data.kind,
      description: data.description,
      catalogItemId: data.catalogItemId || '',
      catalogCode: data.catalogCode || '',
      posology: data.posology || '',
      durationDays: data.durationDays || '',
      followUpDays: data.followUpDays || '',
      quantity: data.quantity || '1',
    }).then(payload => {
      if (payload?.success) annotate('P', 'Action ajoutée au plan');
      return payload;
    });
  }

  function openPlanEditModal(action) {
    Modal.open({
      title: 'Modifier l\'action',
      subtitle: kindById.get(action.kind)?.singularLabel || '',
      width: 'modal-narrow',
      body: `
        <div class="field-block">
          <label class="field-label-mini" for="plan-desc">Description</label>
          <input class="field-input-m" id="plan-desc" value="${esc(action.description)}">
        </div>
        <div class="field-block">
          <label class="field-label-mini" for="plan-poso">Posologie / précisions</label>
          <input class="field-input-m" id="plan-poso" value="${esc(action.posology || '')}">
        </div>
        <div class="field-row field-row--3">
          <div>
            <label class="field-label-mini" for="plan-qty">Quantité</label>
            <input class="field-input-m mono" type="number" step="0.5" min="0.5" id="plan-qty" value="${esc(action.quantity ?? 1)}">
          </div>
          <div>
            <label class="field-label-mini" for="plan-duration">Durée (j)</label>
            <input class="field-input-m mono" type="number" min="1" id="plan-duration" value="${esc(action.durationDays ?? '')}">
          </div>
          <div>
            <label class="field-label-mini" for="plan-followup">RDV J+</label>
            <input class="field-input-m mono" type="number" min="1" id="plan-followup" value="${esc(action.followUpDays ?? '')}">
          </div>
        </div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="plan-save">Enregistrer</button>`,
    });

    Modal.el('#plan-save')?.addEventListener('click', () => {
      const description = (Modal.el('#plan-desc')?.value || '').trim();
      if (!description) {
        Toast.warn('La description est obligatoire');
        return;
      }
      Modal.close();
      mutate(EP.planActionUpdate, {
        planActionId: action.id,
        description,
        posology: Modal.el('#plan-poso')?.value || '',
        durationDays: Modal.el('#plan-duration')?.value || '',
        followUpDays: Modal.el('#plan-followup')?.value || '',
        quantity: Modal.el('#plan-qty')?.value || '1',
      }).then(() => annotate('P', 'Action mise à jour'));
    });
  }

  function openPlanTemplatesModal() {
    const templates = REF.planTemplates || [];
    Modal.open({
      title: 'Templates de plan',
      subtitle: 'Ajoute plusieurs actions d\'un coup',
      width: 'modal-wide',
      body: `<div class="m-list">${templates.map(tpl => `
        <button class="m-item" type="button" data-template-id="${esc(tpl.id)}">
          <div class="m-item-content">
            <div class="m-item-name">${tpl.emoji} ${esc(tpl.name)}</div>
            <div class="m-item-meta">${esc(tpl.description)} · ${tpl.items.length} actions</div>
          </div>
        </button>`).join('')}</div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', async event => {
      const button = event.target.closest('[data-template-id]');
      if (!button) return;
      const template = templates.find(t => t.id === button.dataset.templateId);
      if (!template) return;
      Modal.close();
      // Items go through the shared queue one by one, so the aggregate never
      // sees two concurrent writes.
      let added = 0;

      for (const item of template.items) {
        const payload = await addPlanAction({
          kind: item.kind,
          description: item.description,
          posology: item.posology,
          durationDays: item.durationDays,
          followUpDays: item.followUpDays,
        });

        if (payload?.success) added += 1;
      }

      annotate('P', added === template.items.length
        ? `Template appliqué : ${template.name}`
        : `Template partiellement appliqué : ${added}/${template.items.length} actions`);
    });
  }

  // ═══════════════════════════════════════════════
  //  ORDONNANCE
  // ═══════════════════════════════════════════════

  function openMedicationSearchModal() {
    Modal.open({
      title: 'Ajouter un médicament',
      subtitle: 'Recherche dans le catalogue de la clinique',
      width: 'modal-wide',
      body: `
        <div class="qa-search-wrap">
          <span class="qa-search-icon">${ICONS.search}</span>
          <input type="text" class="qa-search" id="med-search" placeholder="Nom du médicament…" autocomplete="off" autofocus>
        </div>
        <div class="m-list" id="med-results"></div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    const resultsHost = Modal.el('#med-results');
    const allergies = (state.medicalAlerts || []).filter(a => a.kind === 'ALLERGY');

    const runSearch = debounce(async term => {
      if (!term.trim()) {
        resultsHost.innerHTML = '<div class="qa-list-hint">Tapez au moins deux lettres.</div>';
        return;
      }
      const payload = await api.get(EP.catalogSearch, { term, kind: 'ARTICLE' });
      const items = payload?.items || [];
      resultsHost.innerHTML = items.length === 0
        ? '<div class="m-empty">Aucun médicament trouvé.</div>'
        : items.map(item => {
          const suspect = allergies.some(a => item.name.toLowerCase().includes(a.label.toLowerCase()));
          return `
            <button class="m-item" type="button" data-article-id="${esc(item.itemId)}" data-article-name="${esc(item.name)}">
              <div class="m-item-content">
                <div class="m-item-name">${esc(item.name)}</div>
                <div class="m-item-meta">${esc(item.code)}${item.requiresPrescription ? ' · <span class="m-item-tag">ordonnance</span>' : ''}</div>
                ${suspect ? '<div class="m-item-warn">Allergie déclarée — à vérifier</div>' : ''}
              </div>
              <div class="m-item-price">${esc(fmtMoney(item.basePriceMinorUnits, item.currency))}</div>
            </button>`;
        }).join('');
    }, 250);

    runSearch('');
    Modal.el('#med-search')?.addEventListener('input', event => runSearch(event.target.value));

    resultsHost?.addEventListener('click', event => {
      const button = event.target.closest('[data-article-id]');
      if (!button) return;
      Modal.close();
      openPosologyModal(button.dataset.articleId, button.dataset.articleName);
    });
  }

  function openPosologyModal(articleId, articleName) {
    Modal.open({
      title: articleName,
      subtitle: 'Posologie',
      width: 'modal-narrow',
      body: `
        <div class="field-row field-row--2">
          <div>
            <label class="field-label-mini" for="rx-dose">Dose</label>
            <input class="field-input-m" id="rx-dose" placeholder="ex : 0,1 mg/kg" autocomplete="off">
          </div>
          <div>
            <label class="field-label-mini" for="rx-frequency">Fréquence</label>
            <input class="field-input-m" id="rx-frequency" placeholder="ex : 1×/j" autocomplete="off">
          </div>
        </div>
        <div class="field-row field-row--3">
          <div>
            <label class="field-label-mini" for="rx-duration">Durée (j)</label>
            <input class="field-input-m mono" type="number" min="1" id="rx-duration" value="7">
          </div>
          <div>
            <label class="field-label-mini" for="rx-route">Voie</label>
            <input class="field-input-m" id="rx-route" placeholder="per os" autocomplete="off">
          </div>
          <div>
            <label class="field-label-mini" for="rx-quantity">Quantité</label>
            <input class="field-input-m mono" type="number" step="0.5" min="0.5" id="rx-quantity" value="1">
          </div>
        </div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="rx-save">Ajouter à l'ordonnance</button>`,
    });

    Modal.el('#rx-save')?.addEventListener('click', () => {
      Modal.close();
      mutate(EP.prescriptionLineAdd, {
        articleId,
        dose: Modal.el('#rx-dose')?.value || '',
        frequency: Modal.el('#rx-frequency')?.value || '',
        durationDays: Modal.el('#rx-duration')?.value || '',
        route: Modal.el('#rx-route')?.value || '',
        quantity: Modal.el('#rx-quantity')?.value || '1',
      }).then(payload => {
        if (payload?.success) Toast.success('Médicament ajouté à l\'ordonnance');
      });
    });

    Modal.el('#rx-dose')?.focus();
  }

  // ═══════════════════════════════════════════════
  //  TOPBAR — history & printing
  // ═══════════════════════════════════════════════

  function openHistoryModal() {
    Modal.open({
      title: 'Historique du patient',
      subtitle: PATIENT.name || '',
      width: 'modal-wide',
      body: HISTORY.length === 0
        ? '<div class="m-empty">Aucune consultation antérieure pour ce patient.</div>'
        : `<div class="m-list">${HISTORY.map(row => `
            <a class="m-item" href="${esc(row.url)}">
              <div class="m-item-content">
                <div class="m-item-name">${esc(row.motif || 'Consultation')}</div>
                <div class="m-item-meta">${esc(fmtDate(row.startedAtUtc))}${row.weightKg ? ` · ${esc(fmtNumber(row.weightKg))} kg` : ''}</div>
                ${row.summary ? `<div class="m-item-meta">${esc(row.summary)}</div>` : ''}
              </div>
            </a>`).join('')}</div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });
  }

  // One listener for the whole page life: `once: true` per print would leak a
  // closure across Turbo visits whenever the event never fires.
  const restorePrintMode = () => { delete document.body.dataset.printMode; };
  window.addEventListener('afterprint', restorePrintMode);
  registerCleanup(() => {
    window.removeEventListener('afterprint', restorePrintMode);
    restorePrintMode();
  });

  function openPrintModal() {
    Modal.open({
      title: 'Imprimer',
      subtitle: 'Choisissez le document à générer',
      width: 'modal-narrow',
      body: `
        <div class="print-grid">
          <button class="print-card" type="button" data-print="ordonnance">
            <div class="print-card-title">Ordonnance</div>
            <div class="print-card-meta">${(state.prescriptionLines || []).length} médicament(s)</div>
          </button>
          <button class="print-card" type="button" data-print="compte-rendu">
            <div class="print-card-title">Compte-rendu</div>
            <div class="print-card-meta">SOAP complet + facturation</div>
          </button>
        </div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      const card = event.target.closest('[data-print]');
      if (!card) return;
      Modal.close();
      document.body.dataset.printMode = card.dataset.print;
      window.print();
    });
  }

  // ═══════════════════════════════════════════════
  //  PATIENT — profiles & menu
  // ═══════════════════════════════════════════════

  function openPatientProfileModal() {
    if (!PATIENT.isIdentified) {
      Toast.info('Patient non identifié — aucun dossier animal lié');
      return;
    }
    Modal.open({
      title: PATIENT.name,
      subtitle: 'Profil animal',
      width: 'modal-narrow',
      body: `
        ${profileRow('Espèce', PATIENT.speciesLabel)}
        ${profileRow('Race', PATIENT.breed)}
        ${profileRow('Âge', PATIENT.ageLabel)}
        ${profileRow('Sexe', PATIENT.sterilized ? `${PATIENT.sexLabel || ''} stérilisé(e)` : PATIENT.sexLabel)}
        ${profileRow('Robe', PATIENT.color)}
        ${profileRow('Puce', PATIENT.microchip)}
        ${profileRow('Consultations', String(HISTORY.length))}`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });
  }

  function openOwnerProfileModal() {
    if (!OWNER) {
      Toast.info('Aucun propriétaire rattaché');
      return;
    }
    Modal.open({
      title: OWNER.fullName,
      subtitle: 'Profil propriétaire',
      width: 'modal-narrow',
      body: `
        ${profileRow('Téléphone', OWNER.phone)}
        ${profileRow('Email', OWNER.email)}
        ${profileRow('Adresse', OWNER.address)}
        <div class="form-row">
          <span class="form-row-label">Solde</span>
          <span><button class="btn btn-ghost btn-sm" type="button" data-soon>à venir</button></span>
        </div>
        <div class="form-row">
          <span class="form-row-label">Préférences</span>
          <span><button class="btn btn-ghost btn-sm" type="button" data-soon>à venir</button></span>
        </div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      if (event.target.closest('[data-soon]')) Toast.soon();
    });
  }

  function profileRow(label, value) {
    return `<div class="form-row"><span class="form-row-label">${esc(label)}</span><span>${esc(value || '—')}</span></div>`;
  }

  function openPatientMenu() {
    Modal.open({
      title: PATIENT.name || 'Patient',
      subtitle: 'Actions rapides',
      width: 'modal-narrow',
      body: `
        <div class="m-list">
          <button class="m-item" type="button" data-action="owner">Profil propriétaire</button>
          <button class="m-item" type="button" data-soon>Envoyer un SMS</button>
          <button class="m-item" type="button" data-soon>Envoyer un email</button>
          <button class="m-item" type="button" data-soon>Ajouter un tag</button>
          <button class="m-item" type="button" data-soon>Archiver le patient</button>
          <button class="m-item" type="button" data-soon>Transférer le dossier</button>
        </div>`,
      footer: '<button class="btn btn-ghost" data-close>Fermer</button>',
    });

    Modal.el('.modal-body')?.addEventListener('click', event => {
      if (event.target.closest('[data-action="owner"]')) {
        Modal.close();
        openOwnerProfileModal();
        return;
      }
      if (event.target.closest('[data-soon]')) Toast.soon();
    });
  }

  // ═══════════════════════════════════════════════
  //  FOOTER — closing & pausing
  // ═══════════════════════════════════════════════

  function openCloseModal() {
    Modal.open({
      title: 'Clôturer la consultation',
      subtitle: 'La consultation passera en lecture seule.',
      width: 'modal-narrow',
      body: `
        <div class="field-block">
          <label class="field-label-mini" for="close-summary">Compte-rendu de sortie</label>
          <textarea class="modal-textarea" id="close-summary" rows="4" placeholder="Synthèse remise au propriétaire…">${esc(state.summary || '')}</textarea>
        </div>
        <div class="form-hint">Aucune modification ne sera possible après la clôture.</div>`,
      footer: `
        <button class="btn btn-ghost" data-close>Annuler</button>
        <button class="btn btn-primary" id="close-confirm">Clôturer</button>`,
    });

    Modal.el('#close-confirm')?.addEventListener('click', () => {
      const summary = Modal.el('#close-summary')?.value || '';
      const form = document.getElementById('close-consultation-form');
      const field = document.getElementById('close-consultation-summary');
      if (!form || !field) return;
      field.value = summary;
      Modal.close();
      form.submit();
    });
  }

  function leaveTo(url, message) {
    confirmModal({
      title: 'Quitter la consultation',
      message,
      confirmLabel: 'Quitter',
      onConfirm: () => {
        if (window.Turbo) {
          window.Turbo.visit(url);
        } else {
          window.location.assign(url);
        }
      },
    });
  }

  // ═══════════════════════════════════════════════
  //  EVENT WIRING (delegated once, survives re-renders)
  // ═══════════════════════════════════════════════

  function on(selector, type, handler) {
    const el = typeof selector === 'string' ? $(selector) : selector;
    if (!el) return;
    el.addEventListener(type, handler);
    registerCleanup(() => el.removeEventListener(type, handler));
  }

  // Motif strip
  on('#motif-strip', 'click', event => {
    if (event.target.closest('#motif-add')) {
      openMotifModal();
      return;
    }
    const chip = event.target.closest('[data-motif-id]');
    if (!chip) return;
    removeMotif(chip.dataset.motifId);
  });

  const saveChiefComplaint = debounce(value => {
    // The aggregate refuses an empty chief complaint, so clearing the field is
    // simply not persisted — the last saved value stays.
    if (!value.trim()) return;
    mutate(EP.chiefComplaint, { chiefComplaint: value });
  }, 700);

  // Bound directly: the input sits outside #motif-strip so that re-rendering the
  // chips never destroys what is being typed.
  on('#motif-free-text', 'input', event => saveChiefComplaint(event.target.value));

  // Constantes strip
  on('#vitals-strip', 'click', event => {
    if (event.target.closest('#vital-add')) {
      openAddVitalModal();
      return;
    }
    const removeBtn = event.target.closest('[data-remove-vital]');
    if (removeBtn) {
      mutate(EP.typedVitalRemove, { type: removeBtn.dataset.removeVital });
      return;
    }
    const pill = event.target.closest('[data-vital]');
    if (!pill) return;
    const key = pill.dataset.vital;
    if (key === 'weight' || key === 'temperature') {
      openWeightModal();
      return;
    }
    const typeDef = vitalTypeById.get(key);
    const current = (state.typedVitals || []).find(v => v.type === key)?.value;
    if (typeDef) openTypedVitalModal(typeDef, current);
  });

  // SOAP free texts
  const saveSubjective = debounce(value => mutate(EP.subjective, { text: value }), 700);
  const saveObjective = debounce(value => mutate(EP.objective, { text: value }), 700);
  const saveMemo = debounce(value => mutate(EP.teamMemo, { memo: value }), 700);

  on('#q-subjective', 'input', event => saveSubjective(event.target.value));
  on('#q-objective', 'input', event => saveObjective(event.target.value));
  on('#team-memo', 'input', event => saveMemo(event.target.value));

  on('#qS-dictate', 'click', () => Toast.info('Dictée IA — bientôt disponible'));

  // Quad O
  on('#qO-listF', 'click', event => {
    const statusBtn = event.target.closest('[data-status]');
    if (statusBtn) {
      event.stopPropagation();
      const systemId = statusBtn.closest('[data-system]')?.dataset.system;
      const status = statusBtn.dataset.status;
      if (!systemId) return;
      if (status === 'ANOMALY') {
        // Flip first: cancelling the details modal must not leave the click
        // looking like it did nothing.
        if (examBySystem(systemId)?.status !== 'ANOMALY') {
          mutate(EP.examSystem, () => {
            const exam = examBySystem(systemId);

            return {
              system: systemId,
              status: 'ANOMALY',
              notes: exam?.notes || '',
              structuredData: exam?.structuredData || {},
            };
          }).then(() => openExamModal(systemId));

          return;
        }

        openExamModal(systemId);

        return;
      }
      mutate(EP.examSystem, { system: systemId, status, notes: '', structuredData: {} })
        .then(() => annotate('O', `${bodySystemById.get(systemId)?.label} → ${status === 'NORMAL' ? 'RAS' : 'Non testé'}`));
      return;
    }
    const row = event.target.closest('.rowF');
    if (row && examBySystem(row.dataset.system)?.status === 'ANOMALY') {
      openExamModal(row.dataset.system);
    }
  });

  on('#qO-ras-all', 'click', () => {
    mutate(EP.examSystemsAllNormal, () => ({ systems: displayedSystems() }))
      .then(() => annotate('O', 'Tous les systèmes restants marqués RAS'));
  });

  on('#qO-template-btn', 'click', () => openExamTemplateMenu());

  // Quad A
  on('#qA-add-btn', 'click', () => openDiagnosisModal(null));
  on('#qA-suggest-btn', 'click', () => openAiSuggestionModal());
  on('#qA-dx-list', 'click', event => {
    const star = event.target.closest('[data-dx-star]');
    if (star) {
      const id = star.dataset.dxStar;
      const dx = (state.diagnoses || []).find(d => d.id === id);
      mutate(EP.diagnosisPrimary, { diagnosisId: dx?.isPrimary ? '' : id });
      return;
    }
    const remove = event.target.closest('[data-dx-remove]');
    if (remove) {
      mutate(EP.diagnosisRemove, { diagnosisId: remove.dataset.dxRemove })
        .then(() => annotate('A', 'Diagnostic retiré'));
      return;
    }
    const row = event.target.closest('[data-dx-id]');
    if (!row) return;
    const dx = (state.diagnoses || []).find(d => d.id === row.dataset.dxId);
    if (dx) openDiagnosisModal(dx);
  });

  // Quad P
  on('#qP-add-btn', 'click', () => openPlanModal());
  on('#qP-template-btn', 'click', () => openPlanTemplatesModal());
  on('#qP-plan-list', 'click', event => {
    const remove = event.target.closest('[data-plan-remove]');
    if (remove) {
      mutate(EP.planActionRemove, { planActionId: remove.dataset.planRemove })
        .then(() => annotate('P', 'Action retirée'));
      return;
    }
    const row = event.target.closest('[data-plan-id]');
    if (!row) return;
    const action = (state.planActions || []).find(a => a.id === row.dataset.planId);
    if (action) openPlanEditModal(action);
  });

  // Ordonnance
  on('#btn-add-medication', 'click', () => openMedicationSearchModal());
  on('#rx-list', 'click', event => {
    const remove = event.target.closest('[data-rx-remove]');
    if (!remove) return;
    mutate(EP.prescriptionLineRemove, { prescriptionLineId: remove.dataset.rxRemove })
      .then(payload => {
        if (payload?.success) Toast.info('Médicament retiré');
      });
  });
  on('#btn-rx-print', 'click', () => openPrintModal());

  // Topbar
  on('#btn-history', 'click', () => openHistoryModal());
  on('#btn-print', 'click', () => openPrintModal());

  // Patient bar
  on('#btn-patient-file', 'click', () => openPatientProfileModal());
  on('#btn-patient-more', 'click', () => openPatientMenu());

  // Footer
  on('#btn-close-consultation', 'click', () => openCloseModal());
  on('#btn-pause', 'click', () => leaveTo(
    EP.admissionQueue,
    'La consultation reste ouverte et reprendra depuis la file d\'attente.',
  ));
  on('#btn-later', 'click', () => leaveTo(
    EP.consultations,
    'La consultation reste ouverte et reprendra depuis la liste des consultations.',
  ));

  // Sidebar toggle
  (() => {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (!toggle || !sidebar) return;
    if (sidebar.classList.contains('is-collapsed')) document.body.classList.add('sidebar-collapsed');
    on(toggle, 'click', () => {
      const collapsed = sidebar.classList.toggle('is-collapsed');
      document.body.classList.toggle('sidebar-collapsed', collapsed);
    });
  })();

  // Single document-level Escape handler for every modal.
  onDocument('keydown', event => {
    if (event.key === 'Escape' && Modal.isOpen()) Modal.close();
  });

  renderAll();
}

export function cleanup() {
  while (_cleanups.length) {
    const fn = _cleanups.pop();
    try { fn(); } catch { /* teardown must never throw */ }
  }
  _mounted = false;
}
