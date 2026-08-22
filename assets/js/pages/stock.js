/**
 * Kiveto — assets/js/pages/stock.js
 * ────────────────────────────────
 * The stock pages: filter menus, row menus, and the five forms that record a
 * movement.
 *
 * Filtering and pagination are server-side links, and every action URL comes off
 * a data attribute the template wrote with path(): the module opens things and
 * fills forms, it never builds a route.
 */

import { modal } from 'kiveto/modal';

const listeners = [];

function on(target, type, handler) {
  target.addEventListener(type, handler);
  listeners.push(() => target.removeEventListener(type, handler));
}

function closeMenus() {
  document.querySelectorAll('.filter-menu.is-open, .row-menu.is-open')
    .forEach(menu => menu.classList.remove('is-open'));
}

function initMenus() {
  on(document, 'click', e => {
    const filterToggle = e.target.closest('[data-filter-toggle]');
    const rowToggle = e.target.closest('[data-row-menu-toggle]');

    if (!filterToggle && !rowToggle) {
      if (!e.target.closest('.filter-menu, .row-menu')) closeMenus();
      return;
    }

    e.preventDefault();
    e.stopPropagation();

    const menu = filterToggle
      ? filterToggle.parentElement.querySelector('.filter-menu')
      : rowToggle.parentElement.querySelector('.row-menu');

    if (!menu) return;

    const wasOpen = menu.classList.contains('is-open');
    closeMenus();
    if (!wasOpen) menu.classList.add('is-open');
  });

  on(document, 'keydown', e => {
    if (e.key === 'Escape') closeMenus();
  });
}

function initModals() {
  on(document, 'click', e => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      modal.open(opener.dataset.modalOpen);
      return;
    }

    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
      const overlay = closer.closest('.modal-overlay');
      if (overlay) modal.close(overlay.id);
    }
  });
}

/** The lots of one article, folded under its row. */
function initLotToggles() {
  on(document, 'click', e => {
    const toggle = e.target.closest('[data-lots-toggle]');
    if (!toggle) return;

    const lotsRow = toggle.closest('tr').nextElementSibling;
    if (!lotsRow || !lotsRow.classList.contains('lots-row')) return;

    const open = lotsRow.classList.toggle('is-open');
    toggle.classList.toggle('is-open', open);
    toggle.setAttribute('aria-label', open ? 'Masquer les lots' : 'Afficher les lots');
  });
}

function initAutoSubmit() {
  on(document, 'change', e => {
    const select = e.target.closest('[data-auto-submit]');
    if (select) select.form?.submit();
  });
}

/**
 * Points a form at the row that was acted on and tells the user which article
 * they are about to move.
 */
function prepare(formId, modalId, action, { title, values = {} }) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.setAttribute('action', action);

  const name = form.querySelector('[data-target-name]');
  if (name) name.textContent = title;

  Object.entries(values).forEach(([selector, value]) => {
    const field = form.querySelector(selector);
    if (field && value !== undefined && value !== null) field.value = value;
  });

  modal.open(modalId);
}

function initActions() {
  on(document, 'click', e => {
    const receive = e.target.closest('[data-receive]');
    if (receive) {
      const row = receive.closest('tr');
      closeMenus();
      prepare('receive-form', 'receive-modal', receive.dataset.action, {
        title: row.dataset.articleName,
        values: { '[name="quantity"]': '', '[name="lot_number"]': '', '[name="reference"]': '' },
      });
      return;
    }

    const consume = e.target.closest('[data-consume]');
    if (consume) {
      const row = consume.closest('tr');
      closeMenus();
      prepare('consume-form', 'consume-modal', consume.dataset.action, {
        title: row.dataset.articleName,
        values: { '[name="quantity"]': '', '[name="note"]': '' },
      });
      return;
    }

    const threshold = e.target.closest('[data-threshold]');
    if (threshold) {
      const row = threshold.closest('tr');
      closeMenus();
      prepare('threshold-form', 'threshold-modal', threshold.dataset.action, {
        title: row.dataset.articleName,
        values: { '[name="threshold"]': row.dataset.currentThreshold || '0' },
      });
      return;
    }

    const count = e.target.closest('[data-count]');
    if (count) {
      closeMenus();
      const form = document.getElementById('count-form');
      const lotField = form?.querySelector('[data-lot-field]');
      if (lotField) lotField.value = count.dataset.lotId;

      prepare('count-form', 'count-modal', count.dataset.action, {
        title: `${count.dataset.articleName} — lot ${count.dataset.lotNumber}`,
        values: { '[name="quantity"]': count.dataset.lotQuantity },
      });
      return;
    }

    const recall = e.target.closest('[data-recall]');
    if (recall) {
      closeMenus();
      prepare('recall-form', 'recall-modal', recall.dataset.action, {
        title: `${recall.dataset.articleName} — lot ${recall.dataset.lotNumber}`,
        values: { '[name="reason"]': '' },
      });
    }
  });
}

export function init() {
  initMenus();
  initModals();
  initLotToggles();
  initAutoSubmit();
  initActions();
}

export function cleanup() {
  listeners.forEach(off => off());
  listeners.length = 0;
}
