/**
 * Page module -- Select Clinic
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Renders the list of accessible clinics from server-injected window.CLINICS.
 * On submit, POSTs the selected clinic ID via the hidden form.
 *
 * Some display metadata (address, hours, vet count) is not yet served by the
 * backend. Until those fields are available, mock values are generated so that
 * the UI keeps the same visual density as the original design.
 */

// Deterministic color palette for clinic logos
const COLORS = [
  '#1e1b4b', '#0c4a6e', '#065f46', '#581c87',
  '#7c2d12', '#134e4a', '#1e3a5f', '#3b1f6e',
  '#4a1d96', '#0e7490', '#166534', '#9f1239',
];

// Mock addresses used when no real address is available
const MOCK_ADDRESSES = [
  '14 rue Lepic, Paris 18e',
  '28 bd Voltaire, Paris 11e',
  '5 av du Tr\u00f4ne, Paris 12e',
  '3 rue de Fontenay, Vincennes',
  '12 rue de la Bastille, Paris 4e',
  '42 rue de Belleville, Paris 20e',
  '8 bd de la R\u00e9publique, Saint-Cloud',
  '67 av Jean-Baptiste Cl\u00e9ment, Boulogne',
];

// Mock hours patterns (weekday ranges) used when no real hours are available
const MOCK_HOURS = [
  { 1:'8h00\u201319h30', 2:'8h00\u201319h30', 3:'8h00\u201319h30', 4:'8h00\u201319h30', 5:'8h00\u201319h30', 6:'9h00\u201317h00', 0:null },
  { 1:'8h30\u201318h30', 2:'8h30\u201318h30', 3:'8h30\u201318h30', 4:'8h30\u201318h30', 5:'8h30\u201318h30', 6:'9h00\u201313h00', 0:null },
  { 1:'9h00\u201318h00', 2:'9h00\u201318h00', 3:'9h00\u201318h00', 4:'9h00\u201318h00', 5:'9h00\u201318h00', 6:null, 0:null },
  { 1:'0h00\u201324h00', 2:'0h00\u201324h00', 3:'0h00\u201324h00', 4:'0h00\u201324h00', 5:'0h00\u201324h00', 6:'0h00\u201324h00', 0:'0h00\u201324h00' },
  { 1:'9h00\u201319h00', 2:'9h00\u201319h00', 3:'9h00\u201319h00', 4:'9h00\u201319h00', 5:'9h00\u201319h00', 6:'10h00\u201316h00', 0:null },
  { 1:'8h00\u201320h00', 2:'8h00\u201320h00', 3:'8h00\u201320h00', 4:'8h00\u201320h00', 5:'8h00\u201320h00', 6:'9h00\u201315h00', 0:null },
  { 1:'9h00\u201318h30', 2:'9h00\u201318h30', 3:'9h00\u201318h30', 4:'9h00\u201318h30', 5:'9h00\u201318h30', 6:null, 0:null },
  { 1:'8h30\u201319h00', 2:'8h30\u201319h00', 3:'8h30\u201319h00', 4:'8h30\u201319h00', 5:'8h30\u201319h00', 6:'9h00\u201313h30', 0:null },
];

// Mock vet counts
const MOCK_VETS = [12, 8, 6, 22, 4, 5, 9, 7, 10, 15, 3, 11];

function getColor(index) {
  return COLORS[index % COLORS.length];
}

function getLetter(name) {
  return (name || '?').charAt(0).toUpperCase();
}

function getMockAddress(index) {
  return MOCK_ADDRESSES[index % MOCK_ADDRESSES.length];
}

function getMockHours(index) {
  return MOCK_HOURS[index % MOCK_HOURS.length];
}

function getMockVets(index) {
  return MOCK_VETS[index % MOCK_VETS.length];
}

let selectedId = null;
let scrollHandler = null;

function toast(msg, color = '#16a34a', icon = '\u2713') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.innerHTML = `<span aria-hidden="true">${icon}</span><span>${msg}</span>`;
  t.style.cssText = `background:${color};opacity:1;transform:translateX(-50%) translateY(0)`;
  clearTimeout(t._t);
  t._t = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2500);
}

function getOpenStatus(hours) {
  if (!hours) return { open: false, hours: null };
  const now = new Date(), day = now.getDay();
  const h = hours[day];
  if (!h) return { open: false, hours: null };
  const toMin = s => { const [hh, mm] = s.replace('h', ':').split(':'); return +hh * 60 + (+mm || 0); };
  const parts = h.split('\u2013');
  if (parts.length < 2) return { open: false, hours: h };
  const cur = now.getHours() * 60 + now.getMinutes();
  return { open: cur >= toMin(parts[0]) && cur < toMin(parts[1]), hours: h };
}

function getClinics() {
  return window.CLINICS || [];
}

function formatRole(role) {
  const map = {
    veterinary: 'V\u00e9t\u00e9rinaire',
    veterinary_assistant: 'ASV',
    manager: 'G\u00e9rant',
    receptionist: 'Secr\u00e9taire',
  };
  return map[role] || role;
}

function renderList(list) {
  const el = document.getElementById('clinic-list');
  if (!el) return;

  if (!list.length) {
    el.innerHTML = `<div class="list-empty">
      <p style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-muted)">Aucune clinique trouv\u00e9e</p>
      <p style="font-size:var(--text-md);color:var(--text-subtle)">Essayez un autre terme.</p>
    </div>`;
    updateHint();
    return;
  }

  el.innerHTML = list.map((c, idx) => {
    const color = c.color || getColor(idx);
    const letter = getLetter(c.name);
    const sel = c.id === selectedId;
    const address = c.address || getMockAddress(idx);
    const hours = c.hours || getMockHours(idx);
    const vets = c.vets || getMockVets(idx);
    const s = getOpenStatus(hours);

    return `<div class="clinic-item${sel ? ' is-selected' : ''}" id="ci-${c.id}"
      data-clinic-id="${c.id}" role="option" aria-selected="${sel}" tabindex="0"
      onclick="pick('${c.id}')" onkeydown="if(event.key==='Enter'||event.key===' '){pick('${c.id}');event.preventDefault()}">
      <div class="clinic-logo" style="background:${color}" aria-hidden="true">
        ${letter}<span class="status-dot" style="background:${s.open ? '#16a34a' : '#94a3b8'}"></span>
      </div>
      <div class="clinic-info">
        <p class="clinic-name">${c.name}</p>
        <div class="clinic-meta">
          <span class="meta-item">
            <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="3.5" r="2" stroke="currentColor" stroke-width="1.2"/><path d="M2 10c0-2 1.8-3.5 4-3.5s4 1.5 4 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            ${vets} v\u00e9t\u00e9rinaire${vets > 1 ? 's' : ''}
          </span>
          <div class="meta-sep" aria-hidden="true"></div>
          <span class="meta-item">
            <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 4v2l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            ${s.hours || 'Ferm\u00e9 aujourd\'hui'}
          </span>
          ${c.isDefault ? `<div class="meta-sep" aria-hidden="true"></div><span class="meta-item" style="color:var(--brand-600);font-weight:var(--weight-medium)">clinique par d\u00e9faut</span>` : ''}
        </div>
        <div class="clinic-address">
          <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1a4 4 0 014 4c0 3-4 7-4 7S2 8 2 5a4 4 0 014-4z" stroke="currentColor" stroke-width="1.2"/></svg>
          ${address}
        </div>
      </div>
      <div class="clinic-right">
        <span class="clinic-status" style="background:${s.open ? 'var(--color-success-bg)' : 'var(--surface-subtle)'};color:${s.open ? 'var(--color-success-text)' : 'var(--text-subtle)'}">
          ${s.open ? 'Ouvert' : 'Ferm\u00e9'}
        </span>
        <div class="clinic-check" aria-hidden="true">
          <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>`;
  }).join('');

  updateHint();
}

function updateHint() {
  const list = document.getElementById('clinic-list');
  const hint = document.getElementById('scroll-hint');
  const zone = document.getElementById('list-zone');
  if (!list || !hint || !zone) return;
  const more = list.scrollHeight > list.clientHeight + 8;
  hint.classList.toggle('hidden', !more);
  zone.classList.toggle('at-bottom', !more);
}

function pick(id) {
  selectedId = id;
  document.querySelectorAll('.clinic-item').forEach(el => {
    const mine = el.dataset.clinicId === id;
    el.classList.toggle('is-selected', mine);
    el.setAttribute('aria-selected', mine);
  });
  const c = getClinics().find(x => x.id === id);
  if (!c) return;

  document.getElementById('sel-dot').classList.add('active');
  const lbl = document.getElementById('sel-label');
  lbl.textContent = c.name;
  lbl.classList.add('active');
  document.getElementById('enter-btn').disabled = false;
}

function enter() {
  if (!selectedId) return;
  const form = document.getElementById('clinic-select-form');
  const input = document.getElementById('clinic-select-input');
  if (form && input) {
    input.value = selectedId;
    form.submit();
  }
}

export function init() {
  selectedId = null;

  window.toast = toast;

  window.filterList = function (val) {
    const q = val.toLowerCase().trim();
    const allClinics = getClinics();
    renderList(allClinics.filter(c => {
      if (!q) return true;
      const address = c.address || getMockAddress(allClinics.indexOf(c));
      return c.name.toLowerCase().includes(q) || address.toLowerCase().includes(q);
    }));
  };

  window.pick = pick;
  window.enter = enter;

  const clinicList = document.getElementById('clinic-list');
  if (!clinicList) return;

  scrollHandler = function () {
    const atBottom = clinicList.scrollHeight - clinicList.scrollTop - clinicList.clientHeight < 20;
    document.getElementById('list-zone').classList.toggle('at-bottom', atBottom);
    document.getElementById('scroll-hint').classList.toggle('hidden', atBottom);
  };
  clinicList.addEventListener('scroll', scrollHandler);

  // Only render if the list is empty (skip when restored from Turbo cache)
  if (!clinicList.children.length) {
    renderList(getClinics());
  }
}

export function cleanup() {
  const clinicList = document.getElementById('clinic-list');
  if (clinicList && scrollHandler) clinicList.removeEventListener('scroll', scrollHandler);
  scrollHandler = null;
  selectedId = null;
  delete window.toast;
  delete window.filterList;
  delete window.pick;
  delete window.enter;
}
