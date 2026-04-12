/**
 * Page module — Clients & Animaux
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Tabs, search bar and counts live outside the turbo-frame and never swap.
 * Only the table + pagination inside <turbo-frame id="clients-tab-content">
 * gets replaced, with a directional slide-fade transition.
 */

const FRAME_ID    = 'clients-tab-content';
const OUT_MS      = 80;
const IN_MS       = 140;
const SHIFT_PX    = 24;
const MIN_OPACITY = '0.55';

let _direction = null; // 'next' (→ animals) | 'prev' (→ clients) | null

export function init() {
  document.addEventListener('click', onTabClick);
  document.addEventListener('turbo:before-frame-render', onBeforeFrameRender);
  document.addEventListener('turbo:frame-render', onFrameRender);
  document.addEventListener('turbo:frame-load', onFrameLoad);
}

export function cleanup() {
  document.removeEventListener('click', onTabClick);
  document.removeEventListener('turbo:before-frame-render', onBeforeFrameRender);
  document.removeEventListener('turbo:frame-render', onFrameRender);
  document.removeEventListener('turbo:frame-load', onFrameLoad);
}

// ── Tab click: update active state + search bar immediately ──

function onTabClick(e) {
  const a = e.target.closest?.('#ca-tabs a.tab-line');
  if (!a) return;

  const tab = a.dataset.tab;
  if (!tab) return;

  // Direction for animation
  _direction = tab === 'animals' ? 'next' : 'prev';

  // Switch active tab immediately (no waiting for server)
  document.querySelectorAll('#ca-tabs .tab-line').forEach(el => {
    el.classList.toggle('is-active', el.dataset.tab === tab);
  });

  // Update search bar
  const hiddenTab = document.getElementById('ca-search-tab');
  if (hiddenTab) hiddenTab.value = tab;

  const searchInput = document.getElementById('ca-search-input');
  if (searchInput) {
    searchInput.placeholder = tab === 'clients'
      ? 'Rechercher un client…'
      : 'Rechercher un animal…';
  }
}

// ── Slide-fade out before Turbo swaps the frame ──

function onBeforeFrameRender(e) {
  const frame = e.target;
  if (!frame || frame.id !== FRAME_ID) return;

  e.preventDefault();

  const outX = _direction === 'next' ? `-${SHIFT_PX}px`
    : _direction === 'prev' ? `${SHIFT_PX}px`
    : '0px';

  frame.style.willChange = 'opacity, transform';
  frame.style.transition = `opacity ${OUT_MS}ms ease-out, transform ${OUT_MS}ms ease-out`;
  frame.style.opacity    = MIN_OPACITY;
  frame.style.transform  = `translateX(${outX})`;

  window.setTimeout(() => {
    if (e.detail && typeof e.detail.resume === 'function') e.detail.resume();
  }, OUT_MS + 10);
}

// ── Slide-fade in after Turbo mounts new DOM ──

function onFrameRender(e) {
  const frame = e.target;
  if (!frame || frame.id !== FRAME_ID) return;

  const inX = _direction === 'next' ? `${SHIFT_PX}px`
    : _direction === 'prev' ? `-${SHIFT_PX}px`
    : '0px';

  // Jump to entering position without transition
  frame.style.transition = 'none';
  frame.style.opacity    = MIN_OPACITY;
  frame.style.transform  = `translateX(${inX})`;
  // Force reflow
  // eslint-disable-next-line no-unused-expressions
  frame.offsetHeight;

  // Animate to resting position
  frame.style.transition = `opacity ${IN_MS}ms ease-out, transform ${IN_MS}ms ease-out`;
  frame.style.opacity    = '1';
  frame.style.transform  = 'translateX(0)';

  const onEnd = () => {
    frame.style.transition = '';
    frame.style.transform  = '';
    frame.style.opacity    = '';
    frame.style.willChange = '';
    _direction = null;
    frame.removeEventListener('transitionend', onEnd);
  };
  frame.addEventListener('transitionend', onEnd);
}

// ── After frame loads: sync counts + tab state from embedded JSON ──

function onFrameLoad(e) {
  const frame = e.target;
  if (!frame || frame.id !== FRAME_ID) return;

  const script = document.getElementById('ca-tab-meta');
  if (!script) return;

  let meta;
  try { meta = JSON.parse(script.textContent); } catch { return; }

  const { activeTab, clientCount, animalCount } = meta;

  if (clientCount != null) {
    const el = document.querySelector('[data-tab-count="clients"]');
    if (el) el.textContent = clientCount;
  }

  if (animalCount != null) {
    const el = document.querySelector('[data-tab-count="animals"]');
    if (el) el.textContent = animalCount;
  }

  // Sync tab active state (back/forward navigation)
  if (activeTab) {
    document.querySelectorAll('#ca-tabs .tab-line').forEach(el => {
      el.classList.toggle('is-active', el.dataset.tab === activeTab);
    });

    const hiddenTab = document.getElementById('ca-search-tab');
    if (hiddenTab) hiddenTab.value = activeTab;

    const searchInput = document.getElementById('ca-search-input');
    if (searchInput) {
      searchInput.placeholder = activeTab === 'clients'
        ? 'Rechercher un client…'
        : 'Rechercher un animal…';
    }
  }
}
