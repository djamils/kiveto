/**
 * Page module — Répertoire (repertoire-v-topbar_13 mock-up).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * Filtering, sorting and pagination are server-side: the filter bar is a GET
 * form aimed at the results frame, the pager and the sort are links into it.
 * This module brings the rest of the mock-up to life:
 * - live search (debounced) and the multi-choice filter pills, whose count
 *   badge follows the ticked boxes while their menu stays open;
 * - the column picker, remembered per view in this browser;
 * - row selection and its bulk bar (CSV export of the selected rows);
 * - a click on a row opens its sheet;
 * - the two creation modals, and the « Ajouter un premier animal juste
 *   après » handover from the client modal to the animal one.
 * Listeners are bound in init() and released in cleanup().
 */
import { showToast } from 'kiveto/shell';

const FRAME_ID = 'rep-results';
const SEARCH_DEBOUNCE_MS = 250;
const COLS_KEY = 'kiveto.repertoire.cols.';

let ac = null;
let root = null;
let searchTimer = null;
const selected = new Set();

const $ = (sel, scope = document) => scope.querySelector(sel);
const $$ = (sel, scope = document) => [...scope.querySelectorAll(sel)];

export function init() {
  root = $('.rep');
  if (!root) return;
  ac = new AbortController();
  const { signal } = ac;

  selected.clear();
  applyColumns();
  renderSelection();
  resumeAnimalCreation();

  document.addEventListener('click', onClick, { signal });
  document.addEventListener('change', onChange, { signal });
  document.addEventListener('input', onInput, { signal });
  // Capture: Turbo stops the propagation of submit before it bubbles here.
  document.addEventListener('submit', onSubmit, { signal, capture: true });
  document.addEventListener('keydown', onKeydown, { signal });
  document.addEventListener('turbo:frame-load', onFrameLoad, { signal });
  document.addEventListener('turbo:before-cache', closeAll, { signal });
  document.addEventListener('client-search-autocomplete:create', onCreateClientFromOwnerPicker, { signal });
}

export function cleanup() {
  ac?.abort();
  ac = null;
  root = null;
  window.clearTimeout(searchTimer);
  searchTimer = null;
  selected.clear();
}

// ═════════ Filter bar ═════════

const form = () => $('#repFiltres');

/** The pill's count badge and tint follow its ticked boxes. */
function syncPill(dd) {
  const n = $$('input[name]:checked', dd).length;
  dd.classList.toggle('actif', n > 0);
  const btn = $('.dd-btn', dd);
  let badge = $('.ddn', btn);
  if (n && !badge) {
    badge = document.createElement('span');
    badge.className = 'ddn';
    $('span', btn).after(badge);
  }
  if (badge) {
    if (n) badge.textContent = String(n);
    else badge.remove();
  }
}

function submitFilters() {
  window.clearTimeout(searchTimer);
  searchTimer = null;
  form()?.requestSubmit();
}

function onInput(e) {
  if (!(e.target instanceof Element) || e.target.id !== 'q' || !e.target.closest('.rep')) return;

  // Entering a search is a step of its own, refining one is not: the first
  // keystroke pushes a history entry, the next ones overwrite it.
  const f = form();
  f.dataset.turboAction = new URLSearchParams(window.location.search).has('q') ? 'replace' : 'advance';

  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => f.requestSubmit(), SEARCH_DEBOUNCE_MS);
}

/**
 * The sort and the page size are chosen inside the frame, by links: carry the
 * current ones, and let a new filter start again from the first page.
 */
function onSubmit(e) {
  if (e.target?.id !== 'repFiltres') return;
  window.clearTimeout(searchTimer);
  searchTimer = null;

  const params = new URLSearchParams(window.location.search);
  $$('[data-carry]', e.target).forEach((input) => {
    if (params.has(input.name)) input.value = params.get(input.name);
    input.disabled = !params.has(input.name);
  });
}

// ═════════ Columns ═════════

function readCols() {
  const fallback = (root?.dataset.colsDefault || '').split(',').filter(Boolean);
  try {
    const raw = window.localStorage.getItem(COLS_KEY + root.dataset.mode);
    return raw === null ? fallback : JSON.parse(raw);
  } catch {
    return fallback;
  }
}

function saveCols(cols) {
  try {
    window.localStorage.setItem(COLS_KEY + root.dataset.mode, JSON.stringify(cols));
  } catch {
    // Private mode or blocked storage: the choice lasts for this page only.
  }
}

function applyColumns(cols = readCols()) {
  if (!root) return;
  const on = new Set(cols);
  $$('[data-col]', root).forEach((cell) => { cell.hidden = !on.has(cell.dataset.col); });

  const dd = $('#ddCols');
  if (!dd) return;
  $$('.ck[data-k]', dd).forEach((ck) => { ck.checked = on.has(ck.dataset.k); });

  const defaults = (root.dataset.colsDefault || '').split(',').filter(Boolean);
  const differs = defaults.length !== on.size || defaults.some((k) => !on.has(k));
  dd.classList.toggle('actif', differs);
  const badge = $('.ddn', dd);
  badge.hidden = !differs;
  badge.textContent = String(on.size);
}

// ═════════ Clicks ═════════

function closeMenus(except) {
  $$('.rep .dd.open').forEach((d) => { if (d !== except) d.classList.remove('open'); });
}

function onClick(e) {
  if (!(e.target instanceof Element) || !root?.isConnected) return;
  const t = e.target;

  // Pills, column picker and the bulk export menu
  const ddBtn = t.closest('.rep .dd > .dd-btn, #bulkExport');
  if (ddBtn) {
    const dd = ddBtn.closest('.dd');
    closeMenus(dd);
    dd.classList.toggle('open');
    return;
  }

  const raz = t.closest('.rep .razBtn');
  if (raz) {
    const dd = raz.closest('.dd');
    $$('input[name]:checked', dd).forEach((ck) => { ck.checked = false; });
    syncPill(dd);
    dd.classList.remove('open');
    submitFilters();
    return;
  }

  const fmt = t.closest('#bulkDd .dd-it[data-fmt]');
  if (fmt) {
    $('#bulkDd').classList.remove('open');
    if (fmt.dataset.fmt === 'CSV') exportCsv();
    return;
  }

  if (t.closest('#bulkClear')) {
    $('#bulkDd')?.classList.remove('open');
    selected.clear();
    renderSelection();
    return;
  }

  // Modals
  const opener = t.closest('.rep [data-open]');
  if (opener) {
    openModal(opener.dataset.open);
    return;
  }
  const veil = t.closest('.rep .mveil');
  if (veil && (t === veil || t.closest('[data-close]'))) {
    closeModal(veil);
    return;
  }

  // A row opens its sheet; its own controls keep their job.
  const row = t.closest('.rep tbody tr[data-url]');
  if (row && !t.closest('a, button, input, label')) {
    window.Turbo ? window.Turbo.visit(row.dataset.url) : (window.location.href = row.dataset.url);
  }
}

function onChange(e) {
  if (!(e.target instanceof Element) || !root?.isConnected) return;
  const t = e.target;

  // A filter box: the menu stays open while the table follows.
  if (t.matches('#repFiltres .fpill input[name]')) {
    syncPill(t.closest('.dd'));
    submitFilters();
    return;
  }

  if (t.matches('#ddCols .ck[data-k]')) {
    const cols = $$('#ddCols .ck[data-k]').filter((ck) => ck.checked).map((ck) => ck.dataset.k);
    saveCols(cols);
    applyColumns(cols);
    return;
  }

  if (t.id === 'ckAll') {
    rowIds().forEach((id) => (t.checked ? selected.add(id) : selected.delete(id)));
    renderSelection();
    return;
  }

  if (t.matches('.rep .rowck')) {
    const id = t.closest('tr').dataset.id;
    t.checked ? selected.add(id) : selected.delete(id);
    renderSelection();
  }
}

function onKeydown(e) {
  if (e.key !== 'Escape') return;
  const open = $('.rep .mveil.open');
  if (open) {
    closeModal(open);
    return;
  }
  $('#bulkDd')?.classList.remove('open');
}

function closeAll() {
  closeMenus();
  $$('.rep .mveil.open').forEach((v) => v.classList.remove('open'));
}

// ═════════ Selection & bulk bar ═════════

function rowIds() {
  return $$('.rep tbody tr[data-id]').map((row) => row.dataset.id);
}

function renderSelection() {
  const ids = rowIds();
  $$('.rep tbody tr[data-id]').forEach((row) => {
    const on = selected.has(row.dataset.id);
    row.classList.toggle('sel', on);
    const ck = $('.rowck', row);
    if (ck) ck.checked = on;
  });

  const all = $('#ckAll');
  if (all) all.checked = ids.length > 0 && ids.every((id) => selected.has(id));

  const n = $('#bulkN');
  if (n) n.textContent = String(selected.size);
  $('#bulkBar')?.classList.toggle('on', selected.size > 0);
}

/** The selected rows as they are shown: visible columns, one line per row. */
function exportCsv() {
  const table = $('.rep table');
  if (!table || selected.size === 0) return;

  const visible = (cells) => cells.filter((c, i, list) => i > 0 && i < list.length - 1 && !c.hidden);
  const text = (cell) => cell.innerText.replace(/\s*\n\s*/g, ' · ').trim();
  const quote = (s) => `"${s.replace(/"/g, '""')}"`;

  const head = visible($$('thead th', table)).map((th) => quote(text(th)));
  const lines = $$('tbody tr[data-id]', table)
    .filter((row) => selected.has(row.dataset.id))
    .map((row) => visible($$('td', row)).map((td) => quote(text(td))).join(';'));

  const blob = new Blob(['﻿' + [head.join(';'), ...lines].join('\r\n')], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `repertoire-${root.dataset.mode === 'animals' ? 'animaux' : 'clients'}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);

  showToast(`Export CSV généré — ${selected.size} fiche·s sélectionnée·s`);
}

// ═════════ After the results frame is swapped ═════════

function onFrameLoad(e) {
  if (e.target?.id !== FRAME_ID) return;
  applyColumns();
  // Rows the new page no longer holds cannot stay selected.
  const ids = new Set(rowIds());
  [...selected].forEach((id) => { if (!ids.has(id)) selected.delete(id); });
  renderSelection();
}

// ═════════ Modals ═════════

function openModal(id, focus = 'input:not([type=hidden]):not([type=radio]):not([type=checkbox])') {
  const veil = document.getElementById(id);
  if (!veil) return;
  closeMenus();
  veil.classList.add('open');
  window.setTimeout(() => $(focus, veil)?.focus(), 40);
}

function closeModal(veil) {
  veil.classList.remove('open');
}

/**
 * Picks up where « Ajouter un premier animal juste après » left off: the
 * client was created on the server, so reopen the animal modal on them.
 */
function resumeAnimalCreation() {
  const payload = $('[data-chain-animal]');
  if (!payload) return;

  let owner;
  try {
    owner = JSON.parse(payload.textContent);
  } catch {
    return;
  }
  payload.remove();
  if (!owner?.id) return;

  const search = $('#na-owner-search');
  const hidden = $('#modal-new-animal input[name$="[primaryOwnerClientId]"]');
  if (search) search.value = owner.name ?? '';
  if (hidden) hidden.value = owner.id;
  // The owner is settled: start on the animal's name.
  openModal('modal-new-animal', 'input[name$="[name]"]');
}

/**
 * Owner picker « Créer un nouveau client »: hands over to the client modal,
 * carrying what was typed so the name is not typed twice.
 */
function onCreateClientFromOwnerPicker(e) {
  const animal = $('#modal-new-animal');
  if (animal) closeModal(animal);

  const typed = (e.detail?.query || '').trim();
  const first = $('#modal-new-client input[name$="[firstName]"]');
  const last = $('#modal-new-client input[name$="[lastName]"]');
  if (typed && first && last) {
    const parts = typed.split(/\s+/);
    first.value = parts.shift() ?? '';
    last.value = parts.join(' ');
  }
  const chain = $('#modal-new-client input[name$="[_thenAddAnimal]"]');
  if (chain) chain.checked = true;

  openModal('modal-new-client');
}
