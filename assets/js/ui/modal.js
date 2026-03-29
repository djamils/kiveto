/**
 * VetSaaS — assets/js/ui/modal.js
 * ─────────────────────────────────
 * Modal and confirmation dialog management.
 *
 * Usage:
 *   import { modal } from '../ui/modal.js';
 *
 *   // Open an existing HTML modal
 *   modal.open('confirmDeleteModal');
 *
 *   // Programmatic confirmation dialog
 *   const confirmed = await modal.confirm({
 *     title: 'Supprimer le patient ?',
 *     message: 'Cette action est irréversible.',
 *     confirmLabel: 'Supprimer',
 *     variant: 'danger',
 *   });
 *   if (confirmed) { ... }
 *
 * HTML convention for declarative modals:
 *   <div class="modal-overlay hidden" id="myModal">
 *     <div class="modal">...</div>
 *   </div>
 */

/** @type {Set<string>} */
const _openModals = new Set();

/**
 * Opens a declarative modal by its ID.
 * @param {string} id
 * @param {object} [options]
 * @param {Function} [options.onClose]
 */
function open(id, options = {}) {
  const overlay = document.getElementById(id);
  if (!overlay) {
    console.warn(`[modal] Élément introuvable : #${id}`);
    return;
  }

  overlay.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  _openModals.add(id);

  requestAnimationFrame(() => {
    const focusable = overlay.querySelector(
      'input:not([type="hidden"]), select, textarea, button:not([data-modal-close])'
    );
    focusable?.focus();
  });

  // Click overlay → close
  const handleClick = (e) => {
    if (e.target === overlay) {
      close(id);
      overlay.removeEventListener('click', handleClick);
    }
  };
  overlay.addEventListener('click', handleClick);

  if (options.onClose) overlay._vsOnClose = options.onClose;
}

/**
 * Closes a declarative modal.
 * @param {string} id
 */
function close(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;

  overlay.classList.add('hidden');
  _openModals.delete(id);

  if (_openModals.size === 0) {
    document.body.style.overflow = '';
  }

  overlay._vsOnClose?.();
  delete overlay._vsOnClose;
}

/**
 * Programmatic confirmation dialog.
 * Returns a Promise<boolean>.
 *
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.message]
 * @param {string} [options.confirmLabel='Confirmer']
 * @param {string} [options.cancelLabel='Annuler']
 * @param {'danger'|'warning'|'default'} [options.variant='default']
 * @returns {Promise<boolean>}
 */
function confirm({
  title,
  message = '',
  confirmLabel = 'Confirmer',
  cancelLabel = 'Annuler',
  variant = 'default',
} = {}) {
  return new Promise((resolve) => {
    // Clean up any previous dialog
    document.getElementById('vs-confirm-dialog')?.remove();

    const iconMap = {
      danger:  { color: 'confirm-icon-danger',  icon: _iconAlert() },
      warning: { color: 'confirm-icon-warning', icon: _iconAlert() },
      default: { color: '',                     icon: '' },
    };

    const { color, icon } = iconMap[variant] ?? iconMap.default;

    const confirmBtnClass = variant === 'danger'
      ? 'btn btn-danger-solid btn-sm'
      : variant === 'warning'
        ? 'btn btn-warning btn-sm'
        : 'btn btn-primary btn-sm';

    const el = document.createElement('div');
    el.className = 'modal-overlay';
    el.id = 'vs-confirm-dialog';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'vs-confirm-title');

    el.innerHTML = `
      <div class="modal modal-narrow confirm-dialog" style="animation: slide-up .18s ease;">
        <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
          ${icon ? `<div class="confirm-icon ${color}">${icon}</div>` : ''}
          <div>
            <p id="vs-confirm-title" style="font-size:var(--text-lg);font-weight:var(--weight-medium);color:var(--text-primary);margin-bottom:6px;">${_escapeHtml(title)}</p>
            ${message ? `<p style="font-size:var(--text-base);color:var(--text-secondary);line-height:var(--lh-normal);">${_escapeHtml(message)}</p>` : ''}
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary btn-sm" id="vs-confirm-cancel">${_escapeHtml(cancelLabel)}</button>
          <button class="${confirmBtnClass}" id="vs-confirm-ok">${_escapeHtml(confirmLabel)}</button>
        </div>
      </div>
    `;

    document.body.appendChild(el);
    document.body.style.overflow = 'hidden';

    const cleanup = (result) => {
      el.remove();
      document.body.style.overflow = '';
      resolve(result);
    };

    el.querySelector('#vs-confirm-ok').addEventListener('click', () => cleanup(true));
    el.querySelector('#vs-confirm-cancel').addEventListener('click', () => cleanup(false));

    // Click overlay → cancel
    el.addEventListener('click', (e) => { if (e.target === el) cleanup(false); });

    // Focus on the confirm button
    requestAnimationFrame(() => el.querySelector('#vs-confirm-ok')?.focus());

    // Escape → cancel
    const onKey = (e) => {
      if (e.key === 'Escape') { cleanup(false); document.removeEventListener('keydown', onKey); }
      if (e.key === 'Enter')  { cleanup(true);  document.removeEventListener('keydown', onKey); }
    };
    document.addEventListener('keydown', onKey);
  });
}

// ── Internal helpers ─────────────────────────

function _escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function _iconAlert() {
  return `<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
  </svg>`;
}

// Escape closes declarative modals
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && _openModals.size > 0) {
    const lastId = [..._openModals].at(-1);
    close(lastId);
  }
});

// Support for declarative [data-modal-close] buttons
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-modal-close]');
  if (btn) {
    const overlay = btn.closest('.modal-overlay');
    if (overlay?.id) close(overlay.id);
  }
});

export const modal = { open, close, confirm };
