/**
 * Kiveto — assets/js/ui/tabs.js
 * ────────────────────────────────
 * Tab bar management.
 *
 * Two modes:
 *
 * 1. DECLARATIVE (data-attributes, zero JS):
 *    <div data-tabs>
 *      <div class="tab-bar">
 *        <button class="tab active" data-tab-target="panel-a">Onglet A</button>
 *        <button class="tab"        data-tab-target="panel-b">Onglet B</button>
 *      </div>
 *      <div id="panel-a">...</div>
 *      <div id="panel-b" class="hidden">...</div>
 *    </div>
 *    → Call tabs.init() once after the DOM is ready.
 *
 * 2. PROGRAMMATIC:
 *    import { tabs } from '../ui/tabs.js';
 *    const myTabs = tabs.create({
 *      container: document.getElementById('myTabs'),
 *      onChange: (tabId) => console.log('Tab actif :', tabId),
 *    });
 *    myTabs.activate('panel-b');
 */

/**
 * Activates a tab within a declarative group.
 *
 * @param {HTMLElement} tabEl - The clicked .tab button
 * @param {HTMLElement} container - The [data-tabs] ancestor
 */
function _activateTab(tabEl, container) {
  const targetId = tabEl.dataset.tabTarget;
  if (!targetId) return;

  // Deactivate all tabs in the group
  container.querySelectorAll('[data-tab-target]').forEach(t => {
    t.classList.toggle('active', t === tabEl);
  });

  // Show/hide panels
  container.querySelectorAll('[id]').forEach(panel => {
    if (tabEl.dataset.tabTarget === panel.id) {
      panel.classList.remove('hidden');
    } else {
      // Only if this panel is referenced by a tab in the same group
      const isReferenced = [...container.querySelectorAll('[data-tab-target]')]
        .some(t => t.dataset.tabTarget === panel.id);
      if (isReferenced) panel.classList.add('hidden');
    }
  });

  // Dispatch a custom event for React / Stimulus
  container.dispatchEvent(new CustomEvent('vs:tab:change', {
    bubbles: true,
    detail: { tabId: targetId, tab: tabEl },
  }));
}

/**
 * Initializes all [data-tabs] groups in the document.
 * Call once after the DOM has loaded.
 */
let _initDone = false;

function init() {
  if (_initDone) return;
  _initDone = true;

  document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-tab-target]');
    if (!tab) return;

    const container = tab.closest('[data-tabs]');
    if (!container) return;

    _activateTab(tab, container);
  });
}

/**
 * Creates a programmatic tab controller.
 *
 * @param {object} options
 * @param {HTMLElement} options.container - Root element containing the tabs and panels
 * @param {Function} [options.onChange] - Called with the ID of the activated panel
 * @returns {{ activate: Function, getActive: Function }}
 */
function create({ container, onChange } = {}) {
  if (!container) throw new Error('[tabs] container required');

  container.setAttribute('data-tabs', '');

  const activate = (targetId) => {
    const tab = container.querySelector(`[data-tab-target="${targetId}"]`);
    if (tab) _activateTab(tab, container);
    onChange?.(targetId);
  };

  const getActive = () => {
    const activeTab = container.querySelector('[data-tab-target].active');
    return activeTab?.dataset.tabTarget ?? null;
  };

  // Listen for internal changes (via click)
  if (onChange) {
    container.addEventListener('vs:tab:change', (e) => {
      onChange(e.detail.tabId);
    });
  }

  return { activate, getActive };
}

/**
 * Activates a tab by panel ID, without going through the container.
 * Useful from Symfony UX / Stimulus.
 *
 * @param {string} panelId
 */
function activateById(panelId) {
  const tab = document.querySelector(`[data-tab-target="${panelId}"]`);
  if (!tab) return;
  const container = tab.closest('[data-tabs]');
  if (!container) return;
  _activateTab(tab, container);
}

export const tabs = { init, create, activateById };
