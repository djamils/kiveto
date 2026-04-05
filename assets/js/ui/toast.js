/**
 * Kiveto — assets/js/ui/toast.js
 * ─────────────────────────────────
 * Lightweight toast notifications.
 *
 * Usage:
 *   import { toast } from '../ui/toast.js';
 *
 *   toast.success('Patient enregistré');
 *   toast.danger('Erreur lors de la sauvegarde');
 *   toast.warning('Données non sauvegardées');
 *   toast.info('Synchronisation en cours...');
 *
 *   // With custom duration (ms)
 *   toast.success('Fait !', { duration: 5000 });
 *
 *   // With action (undo button)
 *   toast.success('Rendez-vous supprimé', {
 *     action: { label: 'Annuler', onClick: () => restoreRdv() }
 *   });
 *
 * Required HTML in the layout:
 *   <div class="toast-container" id="kiveto-toast-container"></div>
 *
 *   → Automatically injected if absent.
 */

const DEFAULTS = {
  duration: 3500,
  maxVisible: 4,
};

/** @type {HTMLElement|null} */
let _container = null;

function _getContainer() {
  if (_container) return _container;

  _container = document.getElementById('kiveto-toast-container');
  if (!_container) {
    _container = document.createElement('div');
    _container.className = 'toast-container';
    _container.id = 'kiveto-toast-container';
    document.body.appendChild(_container);
  }
  return _container;
}

/**
 * Displays a toast.
 *
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'|'default'} [type='default']
 * @param {object} [options]
 * @param {number} [options.duration]
 * @param {{ label: string, onClick: Function }} [options.action]
 * @param {string} [options.icon] - optional SVG string
 */
function show(message, type = 'default', options = {}) {
  const container = _getContainer();
  const duration = options.duration ?? DEFAULTS.duration;

  // Limit the number of simultaneous toasts
  const existing = container.querySelectorAll('.toast');
  if (existing.length >= DEFAULTS.maxVisible) {
    _remove(existing[0]);
  }

  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.setAttribute('role', 'status');
  el.setAttribute('aria-live', 'polite');

  // Icon by type
  const icon = options.icon ?? _defaultIcon(type);

  el.innerHTML = `
    ${icon ? `<span class="toast-icon" style="display:flex;flex-shrink:0;">${icon}</span>` : ''}
    <span>${message}</span>
    ${options.action
      ? `<button class="toast-action" type="button">${options.action.label}</button>`
      : ''}
  `;

  container.appendChild(el);

  if (options.action) {
    el.querySelector('.toast-action').addEventListener('click', () => {
      options.action.onClick?.();
      _remove(el);
    });
  }

  // Enter animation
  requestAnimationFrame(() => {
    requestAnimationFrame(() => el.classList.add('is-visible'));
  });

  // Auto-dismiss after duration
  const timer = setTimeout(() => _remove(el), duration);

  // Click → close immediately
  el.addEventListener('click', (e) => {
    if (!e.target.closest('.toast-action')) {
      clearTimeout(timer);
      _remove(el);
    }
  });

  return el;
}

function _remove(el) {
  el.classList.remove('is-visible');
  el.addEventListener('transitionend', () => el.remove(), { once: true });
}

function _defaultIcon(type) {
  const icons = {
    success: `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`,
    danger:  `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`,
    warning: `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>`,
    info:    `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    default: '',
  };
  return icons[type] ?? '';
}

// ── Semantic shortcuts ───────────────────────

/** @param {string} msg @param {object} [opts] */
const success = (msg, opts) => show(msg, 'success', opts);

/** @param {string} msg @param {object} [opts] */
const danger  = (msg, opts) => show(msg, 'danger', opts);

/** @param {string} msg @param {object} [opts] */
const warning = (msg, opts) => show(msg, 'warning', opts);

/** @param {string} msg @param {object} [opts] */
const info    = (msg, opts) => show(msg, 'info', opts);

export const toast = { show, success, danger, warning, info };
