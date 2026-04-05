import './stimulus_bootstrap.js';
import './styles/app.css';
import { initUI } from 'kiveto/ui';

/**
 * Page-module dispatcher.
 * Maps Symfony route names to importmap module paths.
 * Each module exports init() and optionally cleanup().
 */
const PAGE_MODULES = {
  clinic_dashboard:              'pages/home',
  clinic_scheduling_dashboard:   'pages/scheduling/agenda',
  clinic_scheduling_planning:    'pages/scheduling/planning',
  clinic_scheduling_waiting_room:'pages/scheduling/waiting-room',
  clinic_hospitalisations:       'pages/hospitalisations',
  clinic_clients_list:           'pages/clients/list',
  clinic_clients_view:           'pages/clients/view',
  clinic_consultation_details:   'pages/consultation',
  clinic_select_clinic:          'pages/select-clinic',
};

let currentCleanup = null;

function onLoad() {
  initUI();

  // Skip page module init on Turbo cache previews to avoid double-render flash
  if (document.documentElement.hasAttribute('data-turbo-preview')) return;

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

// Turbo fires turbo:load on every navigation including initial page load
document.addEventListener('turbo:load', onLoad);
document.addEventListener('turbo:before-cache', onBeforeCache);
