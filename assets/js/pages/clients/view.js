/**
 * Page module — Client sheet (fiche client, new-gen v14 layout).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * Everything is wired through delegated listeners on the document, bound in
 * init() and released in cleanup(), so a Turbo cache restore never doubles them.
 */

let controller = null;
// The control that opened the current dialog: focus goes back to it on close.
let opener = null;

const sheet = () => document.querySelector('[data-fc-sheet]');
const tabs = () => [...document.querySelectorAll('[data-fc-tab]')];

/** Switches pane instantly and mirrors it in the URL so a reload lands on the same tab. */
function showTab(id, { remember = true } = {}) {
  const all = tabs();
  const target = all.find(tab => tab.dataset.fcTab === id) || all[0];
  if (!target) return;

  all.forEach(tab => {
    const selected = tab === target;
    tab.setAttribute('aria-selected', String(selected));
    tab.tabIndex = selected ? 0 : -1;
  });
  document.querySelectorAll('.fc-pane').forEach(pane => {
    pane.classList.toggle('fc-on', pane.id === `fc-p-${target.dataset.fcTab}`);
  });

  const scroller = document.querySelector('[data-fc-scroll]');
  if (scroller) scroller.scrollTop = 0;

  if (remember) {
    const url = new URL(window.location.href);
    if (target === all[0]) url.searchParams.delete('tab');
    else url.searchParams.set('tab', target.dataset.fcTab);
    window.history.replaceState(window.history.state, '', url);
  }
}

function openModal(id, from = null) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  if (from && !from.closest('.fc-ov')) opener = from;

  // A dialog opened from another one replaces it: two stacked overlays would trap clicks.
  document.querySelectorAll('.fc-ov.fc-open').forEach(open => {
    if (open !== overlay) open.classList.remove('fc-open');
  });
  overlay.classList.add('fc-open');

  const first = overlay.querySelector('input.fc-in, textarea.fc-in, select.fc-in, .fc-seg button.fc-on, .fc-row');
  if (first) setTimeout(() => first.focus(), 40);
}

function closeModals() {
  document.querySelectorAll('.fc-ov.fc-open').forEach(overlay => overlay.classList.remove('fc-open'));
  if (opener?.isConnected) opener.focus();
  opener = null;
}

function onClick(event) {
  const target = event.target;
  if (!(target instanceof Element)) return;

  const tab = target.closest('[data-fc-tab]');
  if (tab) return showTab(tab.dataset.fcTab);

  const jump = target.closest('[data-fc-goto]');
  if (jump) {
    closeModals();
    return showTab(jump.dataset.fcGoto);
  }

  const trigger = target.closest('[data-fc-open]');
  if (trigger) return openModal(trigger.dataset.fcOpen, trigger);

  if (target.closest('[data-fc-close]') || target.classList.contains('fc-ov')) return closeModals();

  const segment = target.closest('[data-fc-seg] button');
  if (segment) {
    const group = segment.closest('[data-fc-seg]');
    group.querySelectorAll('button').forEach(button => {
      const on = button === segment;
      button.classList.toggle('fc-on', on);
      button.setAttribute('aria-pressed', String(on));
    });
    group.querySelector('input[type="hidden"]').value = segment.dataset.value;
    return;
  }

  const toggle = target.closest('[data-fc-switch]');
  if (toggle) {
    const on = !toggle.classList.contains('fc-on');
    toggle.classList.toggle('fc-on', on);
    toggle.setAttribute('aria-checked', String(on));
    const input = toggle.closest('form')?.querySelector(`input[name="${toggle.dataset.fcSwitch}"]`);
    if (input) input.value = on ? '1' : '0';
  }
}

function onChange(event) {
  const select = event.target;
  if (!(select instanceof HTMLSelectElement) || !select.matches('[data-fc-invoice]')) return;

  // Picking another invoice proposes its own balance as the amount to cash.
  const amount = select.form?.querySelector('[data-fc-amount]');
  const balance = select.selectedOptions[0]?.dataset.balance;
  if (amount && balance) amount.value = balance;
}

function onKeydown(event) {
  if (event.key === 'Escape') return closeModals();

  const all = tabs();
  const index = all.indexOf(document.activeElement);
  if (index < 0) return;

  const next = {
    ArrowRight: (index + 1) % all.length,
    ArrowLeft: (index - 1 + all.length) % all.length,
    Home: 0,
    End: all.length - 1,
  }[event.key];
  if (next === undefined) return;

  event.preventDefault();
  all[next].focus();
  showTab(all[next].dataset.fcTab);
}

export function init() {
  if (!sheet()) return;

  controller?.abort();
  controller = new AbortController();
  const { signal } = controller;

  document.addEventListener('click', onClick, { signal });
  document.addEventListener('change', onChange, { signal });
  document.addEventListener('keydown', onKeydown, { signal });
}

export function cleanup() {
  controller?.abort();
  controller = null;
  // A cached snapshot must not come back with a dialog left open.
  closeModals();
}
