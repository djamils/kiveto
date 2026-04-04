/**
 * Page module -- Select Clinic
 * Loaded by app.js dispatcher on turbo:load.
 *
 * Renders the clinic picker list with: colored initials + status dot,
 * clinic name, meta info (vets count, hours, address), open/closed badge,
 * and selection checkmark. Includes search filtering, scroll hints,
 * and toast notifications.
 */

let selectedId = null;
let scrollHandler = null;

/* Fixture data used when no server-side clinics are provided */
const FIXTURE_CLINICS = [
  { id: 'fix-1', name: 'Clinique Montmartre',     city: 'Paris 18e',    vets: 12, color: '#1e1b4b', letter: 'M', address: '14 rue Lepic, Paris 18e',               hours: {1:'8h00\u201319h30',2:'8h00\u201319h30',3:'8h00\u201319h30',4:'8h00\u201319h30',5:'8h00\u201319h30',6:'9h00\u201317h00',0:null} },
  { id: 'fix-2', name: 'Clinique R\u00e9publique', city: 'Paris 11e',    vets: 8,  color: '#0c4a6e', letter: 'R', address: '28 bd Voltaire, Paris 11e',              hours: {1:'8h30\u201318h30',2:'8h30\u201318h30',3:'8h30\u201318h30',4:'8h30\u201318h30',5:'8h30\u201318h30',6:'9h00\u201313h00',0:null} },
  { id: 'fix-3', name: 'Clinique Nation',          city: 'Paris 12e',    vets: 6,  color: '#065f46', letter: 'N', address: '5 av du Tr\u00f4ne, Paris 12e',          hours: {1:'9h00\u201318h00',2:'9h00\u201318h00',3:'9h00\u201318h00',4:'9h00\u201318h00',5:'9h00\u201318h00',6:null,0:null} },
  { id: 'fix-4', name: 'Centre Hospitalier V\u00e9t.', city: 'Vincennes', vets: 22, color: '#581c87', letter: 'C', address: '3 rue de Fontenay, Vincennes',          hours: {1:'0h00\u201324h00',2:'0h00\u201324h00',3:'0h00\u201324h00',4:'0h00\u201324h00',5:'0h00\u201324h00',6:'0h00\u201324h00',0:'0h00\u201324h00'} },
  { id: 'fix-5', name: 'Clinique Bastille',        city: 'Paris 4e',     vets: 4,  color: '#7c2d12', letter: 'B', address: '12 rue de la Bastille, Paris 4e',        hours: {1:'9h00\u201319h00',2:'9h00\u201319h00',3:'9h00\u201319h00',4:'9h00\u201319h00',5:'9h00\u201319h00',6:'10h00\u201316h00',0:null} },
  { id: 'fix-6', name: 'Clinique Belleville',      city: 'Paris 20e',    vets: 5,  color: '#134e4a', letter: 'V', address: '42 rue de Belleville, Paris 20e',        hours: {1:'8h00\u201320h00',2:'8h00\u201320h00',3:'8h00\u201320h00',4:'8h00\u201320h00',5:'8h00\u201320h00',6:'9h00\u201315h00',0:null} },
  { id: 'fix-7', name: 'Clinique Saint-Cloud',     city: 'Saint-Cloud',  vets: 9,  color: '#1e3a5f', letter: 'S', address: '8 bd de la R\u00e9publique, Saint-Cloud', hours: {1:'9h00\u201318h30',2:'9h00\u201318h30',3:'9h00\u201318h30',4:'9h00\u201318h30',5:'9h00\u201318h30',6:null,0:null} },
  { id: 'fix-8', name: 'Clinique Boulogne',        city: 'Boulogne',     vets: 7,  color: '#3b1f6e', letter: 'B', address: '67 av Jean-Baptiste Cl\u00e9ment, Boulogne', hours: {1:'8h30\u201319h00',2:'8h30\u201319h00',3:'8h30\u201319h00',4:'8h30\u201319h00',5:'8h30\u201319h00',6:'9h00\u201313h30',0:null} },
];

/* Palette for generating logo background colors from server-side clinics */
const LOGO_COLORS = ['#1e1b4b','#0c4a6e','#065f46','#581c87','#7c2d12','#134e4a','#1e3a5f','#3b1f6e'];

/**
 * Show a brief toast notification at the bottom of the screen.
 */
function toast(msg, color = '#16a34a', icon = '\u2713') {
  const t = document.getElementById('toast');
  t.innerHTML = `<span aria-hidden="true">${icon}</span><span>${msg}</span>`;
  t.style.cssText = `background:${color};opacity:1;transform:translateX(-50%) translateY(0)`;
  clearTimeout(t._t);
  t._t = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(8px)';
  }, 2500);
}

/**
 * Compute open/closed status from the hours map for the current day/time.
 * Returns { open: boolean, hours: string|null }.
 */
function getOpenStatus(hours) {
  if (!hours) return { open: false, hours: null };
  const now = new Date();
  const day = now.getDay();
  const h = hours[day];
  if (!h) return { open: false, hours: null };
  const toMin = s => { const [hh, mm] = s.replace('h', ':').split(':'); return +hh * 60 + (+mm || 0); };
  const parts = h.split('\u2013'); // en-dash
  if (parts.length < 2) return { open: false, hours: h };
  const cur = now.getHours() * 60 + now.getMinutes();
  return { open: cur >= toMin(parts[0]) && cur < toMin(parts[1]), hours: h };
}

/**
 * Normalize server-side clinic data to the enriched format used for rendering.
 * Server clinics only have id/name/slug/role/engagement, so we generate
 * visual properties (letter, color) and use generic meta info.
 */
function normalizeServerClinics(raw) {
  return raw.map((c, i) => ({
    id: c.id,
    name: c.name,
    city: c.slug || '',
    vets: null,
    color: LOGO_COLORS[i % LOGO_COLORS.length],
    letter: c.name.charAt(0).toUpperCase(),
    address: '',
    hours: null,
    role: c.role,
    engagement: c.engagement,
  }));
}

/**
 * Render a single clinic item HTML string.
 */
function renderItem(c) {
  const s = getOpenStatus(c.hours);
  const sel = c.id === selectedId;

  // Meta items: vets count + hours (when available), or role + engagement (server data)
  let metaHtml;
  if (c.vets !== null && c.vets !== undefined) {
    metaHtml = `
      <span class="meta-item">
        <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="3.5" r="2" stroke="currentColor" stroke-width="1.2"/><path d="M2 10c0-2 1.8-3.5 4-3.5s4 1.5 4 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        ${c.vets} v\u00e9t\u00e9rinaire${c.vets > 1 ? 's' : ''}
      </span>
      <div class="meta-sep" aria-hidden="true"></div>
      <span class="meta-item">
        <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 4v2l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        ${s.hours || 'Ferm\u00e9 aujourd\'hui'}
      </span>`;
  } else {
    metaHtml = `
      <span class="meta-item">${c.role || ''}</span>
      <div class="meta-sep" aria-hidden="true"></div>
      <span class="meta-item">${c.engagement || ''}</span>`;
  }

  // Status badge: only show when hours data is available
  const statusHtml = c.hours
    ? `<span class="clinic-status" style="background:${s.open ? 'var(--color-success-bg)' : 'var(--surface-subtle)'};color:${s.open ? 'var(--color-success-text)' : 'var(--text-subtle)'}">
        ${s.open ? 'Ouvert' : 'Ferm\u00e9'}
      </span>`
    : '';

  // Address line
  const addressHtml = c.address
    ? `<div class="clinic-address">
        <svg width="9" height="9" fill="none" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1a4 4 0 014 4c0 3-4 7-4 7S2 8 2 5a4 4 0 014-4z" stroke="currentColor" stroke-width="1.2"/></svg>
        ${c.address}
      </div>`
    : '';

  return `<div class="clinic-item${sel ? ' is-selected' : ''}" id="ci-${c.id}"
    onclick="pick('${c.id}')" role="option" aria-selected="${sel}" tabindex="0"
    onkeydown="if(event.key==='Enter'||event.key===' '){pick('${c.id}');event.preventDefault()}">
    <div class="clinic-logo" style="background:${c.color}" aria-hidden="true">
      ${c.letter}<span class="status-dot" style="background:${s.open ? '#16a34a' : '#94a3b8'}"></span>
    </div>
    <div class="clinic-info">
      <p class="clinic-name">${c.name}</p>
      <div class="clinic-meta">${metaHtml}</div>
      ${addressHtml}
    </div>
    <div class="clinic-right">
      ${statusHtml}
      <div class="clinic-check" aria-hidden="true">
        <svg width="11" height="11" fill="none" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
    </div>
  </div>`;
}

/**
 * Render the clinic list into the DOM.
 */
function renderList(list) {
  const el = document.getElementById('clinic-list');
  if (!list.length) {
    el.innerHTML = `<div class="list-empty">
      <p style="font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-muted)">Aucune clinique trouv\u00e9e</p>
      <p style="font-size:var(--text-md);color:var(--text-subtle)">Essayez un autre terme.</p>
    </div>`;
    updateHint();
    return;
  }
  el.innerHTML = list.map(renderItem).join('');
  updateHint();
}

/**
 * Update the scroll hint and bottom-fade visibility.
 */
function updateHint() {
  const list = document.getElementById('clinic-list');
  const hint = document.getElementById('scroll-hint');
  const zone = document.getElementById('list-zone');
  const more = list.scrollHeight > list.clientHeight + 8;
  hint.classList.toggle('hidden', !more);
  zone.classList.toggle('at-bottom', !more);
}

export function init() {
  // Reset state for Turbo navigation
  selectedId = null;

  // Build the clinic dataset: use server data if available, otherwise fixture data
  const raw = window.CLINICS || [];
  const clinics = raw.length > 0
    ? normalizeServerClinics(raw)
    : FIXTURE_CLINICS;

  // Expose functions globally for inline onclick handlers
  window.toast = toast;

  window.filterList = function (val) {
    const q = val.toLowerCase().trim();
    renderList(clinics.filter(c =>
      !q || c.name.toLowerCase().includes(q) || c.city.toLowerCase().includes(q)
    ));
  };

  window.pick = function (id) {
    selectedId = id;
    document.querySelectorAll('.clinic-item').forEach(el => {
      const mine = el.id === `ci-${id}`;
      el.classList.toggle('is-selected', mine);
      el.setAttribute('aria-selected', mine);
    });
    const c = clinics.find(x => x.id === id);
    document.getElementById('sel-dot').classList.add('active');
    const lbl = document.getElementById('sel-label');
    lbl.textContent = c.name;
    lbl.classList.add('active');
    document.getElementById('enter-btn').disabled = false;
  };

  window.enter = function () {
    if (!selectedId) return;
    // Submit the hidden form to POST the clinic selection
    const form = document.getElementById('clinic-select-form');
    if (form) {
      document.getElementById('clinic-select-input').value = selectedId;
      form.submit();
    }
  };

  // Attach scroll listener to the clinic list
  const clinicList = document.getElementById('clinic-list');
  scrollHandler = function () {
    const atBottom = clinicList.scrollHeight - clinicList.scrollTop - clinicList.clientHeight < 20;
    document.getElementById('list-zone').classList.toggle('at-bottom', atBottom);
    document.getElementById('scroll-hint').classList.toggle('hidden', atBottom);
  };
  clinicList.addEventListener('scroll', scrollHandler);

  // Initial render
  renderList(clinics);
}

export function cleanup() {
  // Remove scroll listener
  const clinicList = document.getElementById('clinic-list');
  if (clinicList && scrollHandler) {
    clinicList.removeEventListener('scroll', scrollHandler);
  }
  scrollHandler = null;
  selectedId = null;

  // Remove global references
  delete window.toast;
  delete window.filterList;
  delete window.pick;
  delete window.enter;
}
