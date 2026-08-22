/**
 * Page module — invoice list.
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Filtering, sorting and pagination are plain links handled by the server, so
 * this module only covers what needs a client: live search, opening the
 * dropdowns, and following a row.
 */

const FRAME_ID = 'invoices-results';

/** Idle time before a keystroke turns into a request. */
const SEARCH_DEBOUNCE_MS = 250;

let searchTimer = null;

export function init() {
  document.addEventListener('click', onClick);
  document.addEventListener('change', onChange);
  document.addEventListener('input', onInput);
  // Capture: Turbo stops the propagation of submit before it bubbles here.
  document.addEventListener('submit', onSubmit, true);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('turbo:frame-load', onFrameLoad);
}

export function cleanup() {
  document.removeEventListener('click', onClick);
  document.removeEventListener('change', onChange);
  document.removeEventListener('input', onInput);
  document.removeEventListener('submit', onSubmit, true);
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('turbo:frame-load', onFrameLoad);
  window.clearTimeout(searchTimer);
  searchTimer = null;
}

// ── Live search ──

function onInput(e) {
  const form = e.target.closest?.('[data-live-search]');
  if (!form) return;

  // Entering a search is a step of its own, refining one is not: the first
  // keystroke pushes a history entry, the following ones overwrite it.
  form.dataset.turboAction = new URLSearchParams(window.location.search).has('q') ? 'replace' : 'advance';

  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => form.requestSubmit(), SEARCH_DEBOUNCE_MS);
}

/** Enter beats the timer: without this the form would be sent twice. */
function onSubmit(e) {
  if (!e.target?.matches?.('[data-live-search]')) return;

  window.clearTimeout(searchTimer);
  searchTimer = null;
}

/**
 * Rebuilds the filters the search form carries from the current address bar.
 *
 * The form lives outside the frame so typing never steals the caret, which
 * also means its hidden inputs are never re-rendered.
 */
function syncSearchForm() {
  const form = document.querySelector('[data-live-search]');
  if (!form) return;

  form.querySelectorAll('[data-search-carry]').forEach(input => input.remove());

  const params = new URLSearchParams(window.location.search);

  // Follow the address bar only when the visitor is not typing — a link such as
  // "Tout effacer" has to empty the box, a keystroke must never be overwritten.
  const box = form.querySelector('input[name="q"]');
  if (box && document.activeElement !== box) box.value = params.get('q') ?? '';

  params.delete('q');
  params.delete('page');

  params.forEach((value, name) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    input.setAttribute('data-search-carry', '');
    form.appendChild(input);
  });
}

// ── After the results frame is swapped ──

function onFrameLoad(e) {
  if (e.target?.id !== FRAME_ID) return;

  // The counter sits in the topbar, outside the frame.
  const total = document.querySelector('[data-results-total]')?.textContent;
  const counter = document.querySelector('.tb-title-count');
  if (total != null && counter) counter.textContent = total;

  syncSearchForm();
  document.dispatchEvent(new CustomEvent('kiveto:icons-refresh'));
}

// ── Dropdowns ──

function closeMenus(except) {
  document.querySelectorAll('.filter-menu.is-open, .row-menu.is-open').forEach(menu => {
    if (menu !== except) menu.classList.remove('is-open');
  });
}

function onClick(e) {
  const filterToggle = e.target.closest?.('[data-filter-toggle]');
  if (filterToggle) {
    const menu = filterToggle.parentElement?.querySelector('[data-filter-menu]');
    const wasOpen = menu?.classList.contains('is-open');
    closeMenus();
    if (menu && !wasOpen) menu.classList.add('is-open');
    return;
  }

  const rowToggle = e.target.closest?.('[data-row-menu-toggle]');
  if (rowToggle) {
    e.stopPropagation();
    const menu = rowToggle.parentElement?.querySelector('[data-row-menu]');
    const wasOpen = menu?.classList.contains('is-open');
    closeMenus();
    if (menu && !wasOpen) menu.classList.add('is-open');
    return;
  }

  // A click inside an open menu is a navigation, leave it alone
  if (e.target.closest?.('.filter-menu, .row-menu')) return;

  closeMenus();

  // Anywhere else on a row opens the invoice
  const row = e.target.closest?.('tr[data-url]');
  if (row) window.Turbo ? window.Turbo.visit(row.dataset.url) : (window.location.href = row.dataset.url);
}

// ── Page size selector submits its own form ──

function onChange(e) {
  const select = e.target.closest?.('[data-auto-submit]');
  if (select) select.form?.requestSubmit();
}

function onKeydown(e) {
  if (e.key === 'Escape') closeMenus();
}
