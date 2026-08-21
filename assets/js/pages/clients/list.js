/**
 * Page module — Répertoire (clients & animals).
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Filtering, sorting, the mode toggle and pagination are plain links handled
 * by the server, so this module only covers what needs a client: live search,
 * opening the dropdowns, row selection and its bulk bar, and Escape.
 */

const FRAME_ID = 'directory-results';

/** Idle time before a keystroke turns into a request. */
const SEARCH_DEBOUNCE_MS = 250;

const selected = new Set();

let searchTimer = null;

export function init() {
  selected.clear();
  resumeAnimalCreation();
  document.addEventListener('click', onClick);
  document.addEventListener('change', onChange);
  document.addEventListener('input', onInput);
  // Capture: Turbo stops the propagation of submit before it bubbles here.
  document.addEventListener('submit', onSubmit, true);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('turbo:frame-load', onFrameLoad);
  document.addEventListener('client-search-autocomplete:create', onCreateClientFromOwnerPicker);
  renderSelection();
}

export function cleanup() {
  document.removeEventListener('click', onClick);
  document.removeEventListener('change', onChange);
  document.removeEventListener('input', onInput);
  document.removeEventListener('submit', onSubmit, true);
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('turbo:frame-load', onFrameLoad);
  document.removeEventListener('client-search-autocomplete:create', onCreateClientFromOwnerPicker);
  window.clearTimeout(searchTimer);
  searchTimer = null;
  selected.clear();
}

// ── Chained creation ──

/**
 * Picks up where "Ajouter un premier animal juste après" left off: the client
 * was created on the server, so reopen the animal modal already pointed at it.
 */
function resumeAnimalCreation() {
  const payload = document.querySelector('[data-chain-animal]');
  if (!payload) return;

  let owner;
  try {
    owner = JSON.parse(payload.textContent);
  } catch {
    return;
  }
  payload.remove();

  if (!owner?.id) return;

  const search = document.getElementById('na-owner-search');
  const hidden = document.querySelector('input[name$="[primaryOwnerClientId]"]');
  if (search) search.value = owner.name ?? '';
  if (hidden) hidden.value = owner.id;

  import('kiveto/modal').then(({ modal }) => modal.open('modal-new-animal'));
}

// ── Owner picker: "Créer un nouveau client" ──

/**
 * Hands the visitor over to the client modal, carrying what they had typed
 * into the owner box so they do not retype the name.
 */
function onCreateClientFromOwnerPicker(e) {
  import('kiveto/modal').then(({ modal }) => {
    modal.close('modal-new-animal');

    const typed = (e.detail?.query || '').trim();
    const first = document.querySelector('#modal-new-client input[name$="[firstName]"]');
    const last = document.querySelector('#modal-new-client input[name$="[lastName]"]');

    if (typed && first && last) {
      const parts = typed.split(/\s+/);
      first.value = parts.shift() ?? '';
      last.value = parts.join(' ');
    }

    const chain = document.querySelector('#modal-new-client input[name$="[_thenAddAnimal]"]');
    if (chain) chain.checked = true;

    // Let the closing animation finish before the next one opens.
    window.setTimeout(() => modal.open('modal-new-client'), 180);
  });
}

// ── Live search ──

function onInput(e) {
  const form = e.target.closest?.('[data-live-search]');
  if (!form) return;

  // Entering a search is a step of its own, refining one is not: the first
  // keystroke pushes a history entry, the following ones overwrite it. The
  // back button then leaves the search instead of unspelling it letter by
  // letter, without swallowing the sort or filter the visitor came from.
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
 * also means its hidden inputs are never re-rendered: without this the next
 * search would submit the filters of the first page load.
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

  // The mode toggle sits in the topbar, outside the frame.
  const counts = document.querySelector('[data-results-clients]');
  if (counts) {
    const clients = document.querySelector('[data-count-clients]');
    const animals = document.querySelector('[data-count-animals]');
    if (clients) clients.textContent = counts.dataset.resultsClients;
    if (animals) animals.textContent = counts.dataset.resultsAnimals;
  }

  // Rows the new page no longer holds cannot stay selected.
  const ids = new Set(rowIds());
  selected.forEach(id => {
    if (!ids.has(id)) selected.delete(id);
  });
  renderSelection();
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

  const checkAll = e.target.closest?.('#check-all');
  if (checkAll) {
    toggleAll();
    return;
  }

  const check = e.target.closest?.('[data-check]');
  if (check) {
    e.stopPropagation();
    toggle(check.dataset.check);
    return;
  }

  if (e.target.closest?.('#bulk-clear')) {
    selected.clear();
    renderSelection();
    return;
  }

  // Anywhere else on a row opens the consultation
  const row = e.target.closest?.('tr[data-url]');
  if (row) window.Turbo ? window.Turbo.visit(row.dataset.url) : (window.location.href = row.dataset.url);
}

// ── Page size selector submits its own form ──

function onChange(e) {
  const select = e.target.closest?.('[data-auto-submit]');
  if (select) select.form?.requestSubmit();
}

// ── Selection ──

function rowIds() {
  return [...document.querySelectorAll('tr[data-id]')].map(row => row.dataset.id);
}

function toggle(id) {
  if (selected.has(id)) selected.delete(id);
  else selected.add(id);
  renderSelection();
}

function toggleAll() {
  const ids = rowIds();
  const allSelected = ids.length > 0 && ids.every(id => selected.has(id));
  ids.forEach(id => (allSelected ? selected.delete(id) : selected.add(id)));
  renderSelection();
}

function renderSelection() {
  const ids = rowIds();

  document.querySelectorAll('tr[data-id]').forEach(row => {
    const isSelected = selected.has(row.dataset.id);
    row.classList.toggle('is-selected', isSelected);
    const check = row.querySelector('[data-check]');
    if (check) {
      check.classList.toggle('is-checked', isSelected);
      check.setAttribute('aria-checked', isSelected ? 'true' : 'false');
    }
  });

  const inPage = ids.filter(id => selected.has(id)).length;
  const checkAll = document.getElementById('check-all');
  if (checkAll) {
    checkAll.classList.toggle('is-checked', ids.length > 0 && inPage === ids.length);
    checkAll.classList.toggle('is-partial', inPage > 0 && inPage < ids.length);
    checkAll.setAttribute('aria-checked', inPage === ids.length && ids.length > 0 ? 'true' : 'false');
  }

  const bar = document.getElementById('bulk-bar');
  const count = document.getElementById('bulk-count');
  if (count) count.textContent = String(selected.size);
  if (bar) bar.classList.toggle('is-visible', selected.size > 0);
}

// ── Keyboard ──

// Ctrl/Cmd+K is the global search modal's shortcut — this page must not take it.
function onKeydown(e) {
  if (e.key === 'Escape') {
    closeMenus();
    if (selected.size > 0) {
      selected.clear();
      renderSelection();
    }
  }
}
