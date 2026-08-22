/**
 * Kiveto — assets/js/pages/catalog.js
 * ──────────────────────────────────
 * The catalogue page: filter menus, row menus, the create and edit modals, and
 * the picker that finds an act or an article to put in a package or a price
 * list.
 *
 * Filtering, sorting and pagination are server-side links; nothing here rebuilds
 * a URL. The module only opens things and fills forms.
 */

import { modal } from 'kiveto/modal';

const listeners = [];

function on(target, type, handler) {
  target.addEventListener(type, handler);
  listeners.push(() => target.removeEventListener(type, handler));
}

/** Closes every open filter and row menu. */
function closeMenus() {
  document.querySelectorAll('.filter-menu.is-open, .row-menu.is-open')
    .forEach(menu => menu.classList.remove('is-open'));
}

// ── Menus ──

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

// ── Modals ──

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

/** A "per page" select submits its form as soon as it changes. */
function initAutoSubmit() {
  on(document, 'change', e => {
    const select = e.target.closest('[data-auto-submit]');
    if (select) select.form?.submit();
  });
}

/**
 * A drug carries prescription flags and nothing else does, so the field only
 * shows for that kind.
 */
function initKindToggles() {
  const sync = () => {
    const kind = document.querySelector('[data-article-kind]');
    const drugFields = document.querySelectorAll('#article-create-modal [data-drug-only]');
    drugFields.forEach(field => { field.hidden = kind ? kind.value !== 'DRUG' : true; });

    const mode = document.querySelector('[data-package-mode]');
    document.querySelectorAll('[data-fixed-price-only]').forEach(field => {
      const fixed = mode ? mode.value === 'FIXED_PRICE' : false;
      field.hidden = !fixed;
      field.querySelector('input')?.toggleAttribute('required', fixed);
    });
  };

  on(document, 'change', e => {
    if (e.target.closest('[data-article-kind], [data-package-mode]')) sync();
  });

  sync();
}

// ── Edit modals ──

/** Copies what the row already holds into the edit form, then opens it. */
function fillAndOpen({ row, modalId, formId, action, fields }) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.setAttribute('action', action);

  Object.entries(fields).forEach(([selector, value]) => {
    const input = form.querySelector(selector);
    if (input && value !== undefined && value !== null) input.value = value;
  });

  modal.open(modalId);
  return row;
}

function initEditors() {
  on(document, 'click', e => {
    const actBtn = e.target.closest('[data-edit-act]');
    if (actBtn) {
      const row = actBtn.closest('tr');
      closeMenus();
      fillAndOpen({
        row,
        modalId: 'act-edit-modal',
        formId: 'act-edit-form',
        action: `/catalogue/actes/${actBtn.dataset.editAct}`,
        fields: {
          '[name="name"]': row.dataset.name,
          '[name="base_price"]': row.dataset.price,
          '[name="tax_category"]': row.dataset.tax,
        },
      });
      return;
    }

    const articleBtn = e.target.closest('[data-edit-article]');
    if (articleBtn) {
      const row = articleBtn.closest('tr');
      closeMenus();
      fillAndOpen({
        row,
        modalId: 'article-edit-modal',
        formId: 'article-edit-form',
        action: `/catalogue/articles/${articleBtn.dataset.editArticle}`,
        fields: {
          '[name="name"]': row.dataset.name,
          '[name="base_price"]': row.dataset.price,
          '[name="tax_category"]': row.dataset.tax,
          '[name="prescription_class"]': row.dataset.rx,
        },
      });

      // Only a drug can carry prescription flags.
      const drugField = document.querySelector('#article-edit-modal [data-drug-only]');
      if (drugField) drugField.hidden = row.dataset.kind !== 'DRUG';
      return;
    }

    const packageBtn = e.target.closest('[data-edit-package]');
    if (packageBtn) {
      const row = packageBtn.closest('tr');
      closeMenus();
      fillAndOpen({
        row,
        modalId: 'package-edit-modal',
        formId: 'package-edit-form',
        action: `/catalogue/forfaits/${packageBtn.dataset.editPackage}`,
        fields: { '[name="fixed_price"]': row.dataset.price },
      });
      return;
    }

    const componentBtn = e.target.closest('[data-add-component]');
    if (componentBtn) {
      closeMenus();
      const form = document.getElementById('component-add-form');
      if (!form) return;

      form.setAttribute('action', `/catalogue/forfaits/${componentBtn.dataset.addComponent}/prestations`);
      resetPicker(form.querySelector('[data-item-picker]'));

      const target = form.querySelector('[data-component-target]');
      if (target) target.textContent = `Forfait « ${componentBtn.dataset.packageName} »`;

      modal.open('component-add-modal');
      return;
    }

    const entryBtn = e.target.closest('[data-edit-entry]');
    if (entryBtn) {
      const row = entryBtn.closest('tr');
      closeMenus();
      openPriceItemModal({
        entryId: entryBtn.dataset.editEntry,
        price: row.dataset.price,
        label: row.dataset.label,
        tax: row.dataset.tax,
      });
      return;
    }

    if (e.target.closest('[data-price-item-new]')) {
      openPriceItemModal({ entryId: '', price: '', label: '', tax: '' });
    }
  });
}

/**
 * The same modal adds an override and corrects one: with an entry the picker
 * steps aside, since the prestation is already decided.
 */
function openPriceItemModal({ entryId, price, label, tax }) {
  const overlay = document.getElementById('price-item-modal');
  if (!overlay) return;

  const isEdit = Boolean(entryId);

  overlay.querySelector('[data-price-entry-id]').value = entryId || '';
  overlay.querySelector('[name="net_price"]').value = price || '';
  overlay.querySelector('[name="tax_category_override"]').value = tax || '';
  overlay.querySelector('[data-price-item-title]').textContent =
    isEdit ? 'Modifier le tarif' : 'Tarifer une prestation';

  const picker = overlay.querySelector('[data-price-item-picker]');
  const labelEl = overlay.querySelector('[data-price-item-label]');

  picker.hidden = isEdit;
  labelEl.hidden = !isEdit;
  labelEl.textContent = isEdit ? label : '';

  if (!isEdit) resetPicker(picker.querySelector('[data-item-picker]'));

  modal.open('price-item-modal');
}

// ── Item picker ──

function resetPicker(picker) {
  if (!picker) return;
  picker.querySelector('[data-picker-id]').value = '';
  picker.querySelector('[data-picker-type]').value = 'ACT';
  picker.querySelector('[data-picker-search]').value = '';
  picker.querySelector('[data-picker-results]').innerHTML = '';
  picker.querySelector('[data-picker-results]').classList.remove('is-open');
  picker.querySelector('[data-picker-chosen]').classList.remove('is-set');
}

function money(minorUnits, currency) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency || 'EUR' })
    .format((minorUnits || 0) / 100);
}

function initPicker() {
  let debounce = null;

  const search = (picker, term) => {
    const results = picker.querySelector('[data-picker-results]');

    fetch(`/catalogue/recherche?term=${encodeURIComponent(term)}`, {
      headers: { Accept: 'application/json' },
    })
      .then(response => response.json())
      .then(payload => {
        const items = payload.items || [];
        results.innerHTML = items.length
          ? items.map(item => `
              <button class="item-picker-row" type="button"
                      data-pick-type="${item.itemType}" data-pick-id="${item.itemId}"
                      data-pick-label="${item.name.replace(/"/g, '&quot;')}">
                <span class="ip-code">${item.code}</span>
                <span class="ip-name">${item.name}</span>
                <span class="ip-price">${money(item.basePriceMinorUnits, item.currency)}</span>
              </button>
            `).join('')
          : '<div class="item-picker-row" style="cursor:default">Aucune prestation trouvée</div>';
        results.classList.add('is-open');
      })
      .catch(() => {
        results.innerHTML = '<div class="item-picker-row" style="cursor:default">Recherche indisponible</div>';
        results.classList.add('is-open');
      });
  };

  on(document, 'input', e => {
    const input = e.target.closest('[data-picker-search]');
    if (!input) return;

    const picker = input.closest('[data-item-picker]');
    clearTimeout(debounce);
    debounce = setTimeout(() => search(picker, input.value.trim()), 180);
  });

  on(document, 'focusin', e => {
    const input = e.target.closest('[data-picker-search]');
    if (!input) return;

    const picker = input.closest('[data-item-picker]');
    if (!picker.querySelector('[data-picker-results]').innerHTML) {
      search(picker, '');
    }
  });

  on(document, 'click', e => {
    const row = e.target.closest('[data-pick-id]');
    if (row) {
      const picker = row.closest('[data-item-picker]');
      picker.querySelector('[data-picker-id]').value = row.dataset.pickId;
      picker.querySelector('[data-picker-type]').value = row.dataset.pickType;
      picker.querySelector('[data-picker-chosen-label]').textContent = row.dataset.pickLabel;
      picker.querySelector('[data-picker-chosen]').classList.add('is-set');
      picker.querySelector('[data-picker-results]').classList.remove('is-open');
      picker.querySelector('[data-picker-search]').value = '';
      return;
    }

    const clear = e.target.closest('[data-picker-clear]');
    if (clear) resetPicker(clear.closest('[data-item-picker]'));
  });
}

export function init() {
  initMenus();
  initModals();
  initAutoSubmit();
  initKindToggles();
  initEditors();
  initPicker();
}

export function cleanup() {
  listeners.forEach(off => off());
  listeners.length = 0;
}
