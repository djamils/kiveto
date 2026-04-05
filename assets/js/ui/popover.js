/**
 * Kiveto — assets/js/ui/popover.js
 * ────────────────────────────────────
 * Position:fixed popovers that escape overflow:hidden containers.
 * Automatic positioning (below/above based on available space).
 *
 * Usage:
 *   import { popover } from '../ui/popover.js';
 *
 *   // Open an action menu linked to a button
 *   popover.open({
 *     anchor: buttonElement,
 *     items: [
 *       { label: 'Modifier',   icon: editIcon, onClick: () => {} },
 *       { label: 'Supprimer', icon: trashIcon, onClick: () => {}, danger: true },
 *     ],
 *   });
 *
 *   // Open a popover with custom HTML content
 *   popover.openHtml({
 *     anchor: buttonElement,
 *     html: '<div class="popover-pad">...</div>',
 *     width: 260,
 *   });
 *
 *   popover.close();
 *
 * HTML convention for data-attribute triggers:
 *   <button data-popover-trigger data-popover-target="myPopoverMenu">
 *   <div class="popover hidden" id="myPopoverMenu">...</div>
 *   → The init() JS handles the binding automatically.
 */

/** @type {HTMLElement|null} Active popover */
let _active = null;

/** @type {Function|null} global close handler */
let _globalHandler = null;

/**
 * Opens a popover menu from items.
 *
 * @param {object} options
 * @param {HTMLElement} options.anchor - Anchor element
 * @param {Array<{label:string, icon?:string, onClick:Function, danger?:boolean, divider?:boolean}>} options.items
 * @param {'left'|'right'} [options.align='right'] - Horizontal alignment
 * @param {number} [options.width=180]
 * @param {Function} [options.onClose]
 */
function open({ anchor, items = [], align = 'right', width = 180, onClose } = {}) {
  close();

  const el = document.createElement('div');
  el.className = 'popover';
  el.style.width = `${width}px`;
  el.style.padding = '4px 0';

  el.innerHTML = items.map(item => {
    if (item.divider) return `<div class="popover-divider"></div>`;

    return `
      <button
        class="popover-item${item.danger ? ' is-danger' : ''}"
        type="button"
        ${item.disabled ? 'disabled' : ''}
      >
        ${item.icon ? `<span style="display:flex;flex-shrink:0;width:16px;height:16px;">${item.icon}</span>` : ''}
        <span>${item.label}</span>
      </button>
    `;
  }).join('');

  document.body.appendChild(el);
  _active = el;

  // Bind click handlers on items
  const buttons = el.querySelectorAll('.popover-item:not([disabled])');
  buttons.forEach((btn, i) => {
    // Skip dividers
    const realItem = items.filter(it => !it.divider)[i];
    if (!realItem) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      close();
      realItem.onClick?.();
    });
  });

  _position(el, anchor, align);

  if (onClose) el._kivetoOnClose = onClose;
  _registerGlobal(anchor);

  return el;
}

/**
 * Opens a popover with custom HTML content.
 *
 * @param {object} options
 * @param {HTMLElement} options.anchor
 * @param {string} options.html
 * @param {'left'|'right'} [options.align='right']
 * @param {number} [options.width=240]
 * @param {Function} [options.onClose]
 * @returns {HTMLElement} The popover element (to attach events to)
 */
function openHtml({ anchor, html, align = 'right', width = 240, onClose } = {}) {
  close();

  const el = document.createElement('div');
  el.className = 'popover';
  el.style.width = `${width}px`;
  el.innerHTML = html;

  document.body.appendChild(el);
  _active = el;

  _position(el, anchor, align);
  if (onClose) el._kivetoOnClose = onClose;
  _registerGlobal(anchor);

  return el;
}

/**
 * Closes the active popover.
 */
function close() {
  if (!_active) return;

  const el = _active;
  _active = null; // Null immediately to prevent re-entry

  if (_globalHandler) {
    document.removeEventListener('click', _globalHandler);
    document.removeEventListener('keydown', _globalHandler);
    _globalHandler = null;
  }

  el.classList.add('is-closing');

  const onEnd = () => {
    el._kivetoOnClose?.();
    el.remove();
  };

  el.addEventListener('animationend', onEnd, { once: true });
  setTimeout(() => { if (document.body.contains(el)) onEnd(); }, 150);
}

/**
 * Toggle: closes if already open on this anchor, opens otherwise.
 * Returns true if opened, false if closed.
 */
function toggle(options) {
  if (_active) { close(); return false; }
  open(options);
  return true;
}

// ── Positioning ──────────────────────────────

function _position(el, anchor, align) {
  const GAP = 4;
  const rect = anchor.getBoundingClientRect();
  const vh = window.innerHeight;
  const vw = window.innerWidth;
  const elW = parseInt(el.style.width) || 180;

  // Vertical: below the anchor by default, above if not enough space
  const spaceBelow = vh - rect.bottom;
  const spaceAbove = rect.top;

  let top, left;

  if (spaceBelow >= 200 || spaceBelow >= spaceAbove) {
    top = rect.bottom + GAP;
  } else {
    // Position above anchor — add directional animation class
    el.classList.add('from-below');
    top = rect.top - GAP - 200; // conservative estimate
    el.style.top = `${top}px`;
    // Recalculate after render
    requestAnimationFrame(() => {
      const elH = el.offsetHeight;
      el.style.top = `${rect.top - GAP - elH}px`;
    });
  }

  // Horizontal alignment
  if (align === 'right') {
    left = rect.right - elW;
  } else {
    left = rect.left;
  }

  // Keep within screen bounds
  left = Math.max(8, Math.min(left, vw - elW - 8));

  el.style.top  = `${top}px`;
  el.style.left = `${left}px`;
}

// ── Global click handler ─────────────────────

function _registerGlobal(anchor) {
  // Delay to prevent the current click from triggering the close
  setTimeout(() => {
    _globalHandler = (e) => {
      if (e.type === 'keydown' && e.key !== 'Escape') return;
      if (e.type === 'click' && (_active?.contains(e.target) || anchor.contains(e.target))) return;
      close();
    };

    document.addEventListener('click', _globalHandler);
    document.addEventListener('keydown', _globalHandler);
  }, 10);
}

// ── Declarative init (data-attributes) ───────
// Allows usage from Twig without custom JS:
//   <button data-popover-anchor="myMenu">...</button>
//   <div class="popover hidden" id="myMenu">...</div>

function init() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-popover-anchor]');
    if (!trigger) return;

    e.stopPropagation();
    const targetId = trigger.dataset.popoverAnchor;
    const menu = document.getElementById(targetId);
    if (!menu) return;

    if (_active === menu) { close(); return; }

    close();
    menu.classList.remove('hidden');
    document.body.appendChild(menu); // Move up in the DOM for position:fixed
    _active = menu;
    _position(menu, trigger, trigger.dataset.popoverAlign || 'right');
    _registerGlobal(trigger);
  });
}

export const popover = { open, openHtml, close, toggle, init };
