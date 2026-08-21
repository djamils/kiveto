import './stimulus_bootstrap.js';
import './styles/app.css';
import { initUI } from 'kiveto/ui';
import { createIcons, icons } from 'lucide';

/**
 * Page-module dispatcher.
 * Maps Symfony route names to importmap module paths.
 * Each module exports init() and optionally cleanup().
 */
const PAGE_MODULES = {
  clinic_dashboard:              'pages/home',
  clinic_scheduling_agenda:      'pages/scheduling/agenda',
  clinic_scheduling_planning:    'pages/scheduling/planning',
  clinic_hospitalisations:       'pages/hospitalisations',
  clinic_clients_list:           'pages/clients/list',
  clinic_clients_view:           'pages/clients/view',
  clinic_consultation_details:   'pages/consultation',
  clinic_consultations:          'pages/consultations',
  clinic_select_clinic:          'pages/select-clinic',
};

let currentCleanup = null;

// Initialize UI kit once — document-level listeners survive Turbo navigations
initUI();

function onLoad() {
  // Render Lucide icons in the new DOM with a default 16x16 size
  createIcons({ icons, attrs: { width: 16, height: 16 } });

  // Consume flash toast data attributes (replaces inline <script> tags)
  document.querySelectorAll('[data-flash-toast]').forEach(el => {
    const type = el.dataset.type || 'info';
    const message = el.dataset.message;
    if (message) {
      import('kiveto/toast').then(({ toast }) => {
        if (typeof toast[type] === 'function') toast[type](message);
      });
    }
    el.remove();
  });

  const pageEl = document.querySelector('[data-page]');
  const page = pageEl?.dataset.page;

  if (page && PAGE_MODULES[page]) {
    import(PAGE_MODULES[page]).then(mod => {
      currentCleanup = mod.cleanup || null;
      if (mod.init) mod.init();
    }).catch(err => {
      console.warn(`[kiveto] Failed to load page module for "${page}":`, err);
    });
  }
}

function onBeforeCache() {
  if (currentCleanup) {
    currentCleanup();
    currentCleanup = null;
  }
}

// Preserve JS-rendered content during Turbo body swap.
// Containers marked with [data-turbo-temporary] are populated by JS (renderTable, etc.).
// The server sends them empty, but the cache has them full. Before swapping, copy
// the content from the current DOM into the incoming DOM so the swap is seamless.
document.addEventListener('turbo:before-render', (event) => {
  const newBody = event.detail.newBody;
  newBody.querySelectorAll('[data-turbo-temporary]').forEach(newEl => {
    if (newEl.id && !newEl.children.length) {
      const oldEl = document.getElementById(newEl.id);
      if (oldEl && oldEl.children.length) {
        newEl.innerHTML = oldEl.innerHTML;
      }
    }
  });
});

// Listen for icon refresh requests from page modules that re-render content.
// Pages dispatch `kiveto:icons-refresh` after injecting new HTML containing
// `<i data-lucide="…">` elements that need to become SVGs.
document.addEventListener('kiveto:icons-refresh', () => {
  createIcons({ icons, attrs: { width: 16, height: 16 } });
});

// Turbo fires turbo:load on every navigation including initial page load
document.addEventListener('turbo:load', onLoad);
document.addEventListener('turbo:before-cache', onBeforeCache);
