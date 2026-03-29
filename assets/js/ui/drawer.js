/**
 * VetSaaS — assets/js/ui/drawer.js
 * ──────────────────────────────────
 * Drawer (slide panels) and bottom sheet management.
 *
 * Usage in Twig:
 *   import { drawer } from '../js/ui/drawer.js';
 *   drawer.open('myDrawer');
 *   drawer.close('myDrawer');
 *   drawer.closeAll();
 *
 * Usage in a Stimulus controller:
 *   import { drawer } from '../ui/drawer.js';
 *   openDrawer() { drawer.open(this.idValue); }
 *
 * HTML conventions:
 *   <div class="drawer-overlay" id="myDrawer" data-drawer>
 *     <aside class="drawer">
 *       <div class="drawer-head">...</div>
 *       <div class="drawer-body">...</div>
 *     </aside>
 *   </div>
 *
 *   Clicking the overlay (outside the drawer) closes it automatically.
 *   Escape closes the last opened drawer.
 */

/** @type {Set<string>} IDs of open drawers */
const _openDrawers = new Set();

/**
 * Opens a drawer by its ID.
 * @param {string} id - ID of the .drawer-overlay element
 * @param {object} [options]
 * @param {Function} [options.onOpen] - callback after opening
 * @param {Function} [options.onClose] - callback on close
 */
function open(id, options = {}) {
  const overlay = document.getElementById(id);
  if (!overlay) {
    console.warn(`[drawer] Élément introuvable : #${id}`);
    return;
  }

  overlay.classList.add('is-open');
  document.body.style.overflow = 'hidden';
  _openDrawers.add(id);

  // Lightweight focus trap: focus on the first focusable element
  requestAnimationFrame(() => {
    const focusable = overlay.querySelector(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    focusable?.focus();
  });

  // Click overlay → close
  const handleOverlayClick = (e) => {
    if (e.target === overlay) {
      close(id);
      overlay.removeEventListener('click', handleOverlayClick);
    }
  };
  overlay.addEventListener('click', handleOverlayClick);

  // Store the onClose callback if provided
  if (options.onClose) {
    overlay._vsOnClose = options.onClose;
  }

  options.onOpen?.();
}

/**
 * Closes a drawer by its ID.
 * @param {string} id
 */
function close(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;

  overlay.classList.remove('is-open');
  _openDrawers.delete(id);

  // Restore scrolling if no more drawers are open
  if (_openDrawers.size === 0) {
    document.body.style.overflow = '';
  }

  overlay._vsOnClose?.();
  delete overlay._vsOnClose;
}

/**
 * Closes all open drawers.
 */
function closeAll() {
  [..._openDrawers].forEach(id => close(id));
}

/**
 * Toggle a drawer (opens if closed, closes if open).
 * @param {string} id
 * @param {object} [options]
 */
function toggle(id, options = {}) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.classList.contains('is-open') ? close(id) : open(id, options);
}

/**
 * Returns true if the drawer is open.
 * @param {string} id
 * @returns {boolean}
 */
function isOpen(id) {
  return _openDrawers.has(id);
}

// Escape closes the last opened drawer
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && _openDrawers.size > 0) {
    const lastId = [..._openDrawers].at(-1);
    close(lastId);
  }
});

export const drawer = { open, close, closeAll, toggle, isOpen };
