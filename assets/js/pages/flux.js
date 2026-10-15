/**
 * Page module — Ambulatory flow board (flux-ambulatoire-v-topbar-busy_8 mock-up,
 * with its normal _3 and calme_4 variants). Loaded by the app.js dispatcher on
 * turbo:load.
 *
 * The board is rendered by the server; this module brings it to life the way
 * the mock-up does:
 * - live clock in the clinic's time zone, waiting chronos, « En retard · X min »
 *   on the bookings whose time has passed, day figures;
 * - banner of the emergencies nobody has taken for more than 5 minutes;
 * - the fold of today's passages and no-shows at the head of « Planifié »;
 * - one-click step on each card: a booking opens its arrival window (weighing,
 *   contact, what to flag to the vet, balance due, note) before the waiting
 *   room; « Prendre en charge », « Passer en sortie », « Encaisser » — an
 *   unidentified patient is identified first;
 * - « Enregistrer une arrivée » (search first, creation follows) and
 *   « Admission d'urgence » (motif first, chip reader, who brought the animal),
 *   both with the motif picker (a badge, « Autre », a separate precision);
 * - « Identifier le patient »: chip, existing file, or a new file;
 * - the card action menu and the patient peek; keys: U emergency, A arrival,
 *   Esc closes.
 *
 * Every action posts to its endpoint (admission, consultation, scheduling);
 * the server redirects back here with a flash shown as a toast. Listeners are
 * bound in init() and released in cleanup().
 */
import { showToast } from 'kiveto/shell';

let ac = null;
let ticker = null;
let data = null;
let offset = 0;

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const url = (name, id) => data.urls[name].replace('__ID__', encodeURIComponent(id));

const SEUIL_URGENCE = 5 * 60; // the banner only escalates after 5 min without anyone
const LONG_WAIT = 15 * 60;
const KPAS_KEY = 'kiveto.flux.passages';

const COLS = {
  planifie: { titre: 'Planifié', action: 'Enregistrer l\'arrivée' },
  attente: { titre: 'Salle d\'attente', action: 'Prendre en charge' },
  charge: { titre: 'Pris en charge', action: 'Passer en sortie' },
  sortie: { titre: 'Sortie', action: 'Encaisser' },
};

const ESPECES = [
  { v: '🐕', l: 'Chien', s: 'dog' }, { v: '🐈', l: 'Chat', s: 'cat' }, { v: '🐇', l: 'Lapin', s: 'nac' },
  { v: '🐦', l: 'Oiseau', s: 'other' }, { v: '🐾', l: 'NAC / autre', s: 'other' },
];
const CI_MOTIFS = ['Vaccin', 'Contrôle', 'Vomissements', 'Boiterie', 'Plaie', 'Détartrage'];
const URG_MOTIFS = ['Convulsions', 'Hémorragie', 'Trauma / AVP', 'Dyspnée', 'Intoxication', 'Abattement sévère'];
const PAR = { proprio: 'propriétaire sur place', tiers: 'un tiers', secours: 'les secours' };
const EUROPETNET = 'https://www.europetnet.com/';

const SVG = {
  oeil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>',
  crayon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.31a2 2 0 0 0 .83-.5Z"/></svg>',
  pers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c.9-3.6 3.8-6 8-6s7.1 2.4 8 6"/></svg>',
  croix: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  fleche: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>',
  retour: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5m6-6-6 6 6 6"/></svg>',
  print: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>',
  mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
  steto: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>',
  alerte: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>',
  prio: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
  eclair: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
  x12: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  x14: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  ok: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>',
  scan: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>',
  ident: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
};
const BIENTOT = 'Bientôt disponible';

// ═════════ Time ═════════

const nowSec = () => Math.floor(Date.now() / 1000 + offset);

function clockLabel(withSeconds, epoch = nowSec()) {
  const opts = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
  if (withSeconds) opts.second = '2-digit';
  try {
    return new Intl.DateTimeFormat('fr-FR', { ...opts, timeZone: data.tz }).format(new Date(epoch * 1000));
  } catch {
    return new Intl.DateTimeFormat('fr-FR', opts).format(new Date(epoch * 1000));
  }
}

function minutesDepuis(dep) { return Math.max(0, Math.floor((nowSec() - dep) / 60)); }

function fmtChrono(dep) {
  const m = minutesDepuis(dep);
  return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${String(m % 60).padStart(2, '0')}`;
}

// ═════════ Data ═════════

const entry = (key) => data.patients[key] || null;
const patient = (key) => entry(key)?.p || null;
const all = () => Object.values(data.patients).map((e) => e.p);
const card = (key) => $(`.pat[data-key="${CSS.escape(key)}"]`);

const fmtPuce = (d) => d.replace(/(\d{3})(?=\d)/g, '$1 ');
const fmtTel = (d) => d.replace(/(\d{2})(?=\d)/g, '$1 ');
const chiffres = (s, n) => String(s || '').replace(/\D/g, '').slice(0, n);

/** Posts a hidden form — every action is a regular POST answered by a redirect + flash. */
function post(action, fields) {
  const form = document.createElement('form');
  form.method = 'post';
  form.action = action;
  form.dataset.turbo = 'false';
  form.hidden = true;
  const add = (k, v) => {
    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = k;
    i.value = String(v);
    form.appendChild(i);
  };
  Object.entries(fields).forEach(([k, v]) => {
    if (v === null || v === undefined) return;
    if (Array.isArray(v)) v.forEach((x) => add(k + '[]', x));
    else add(k, v);
  });
  document.body.appendChild(form);
  form.submit();
}

function go(href) {
  if (window.Turbo && typeof window.Turbo.visit === 'function') window.Turbo.visit(href);
  else window.location.href = href;
}

/** The card slides out (mock-up's `.sortie`), then the action runs. */
function leave(key, fn) {
  card(key)?.classList.add('sortie');
  setTimeout(fn, 200);
}

// ═════════ Card steps ═════════

function startConsultation(p) {
  post(url('start', p.admissionId), { _token: data.tokens.start });
}

function avancer(key) {
  const p = patient(key);
  if (!p) return;

  // A booking arrives through its arrival window, not straight to the waiting room.
  if (p.column === 'planifie') return ouvrirArriveeRdv(p);
  if (p.column === 'attente') return leave(key, () => startConsultation(p));
  if (p.column === 'charge') {
    return leave(key, () => post(url('toCheckout', p.admissionId), {
      _token: data.tokens.flux, consultationId: p.consultationId, name: p.name,
    }));
  }

  // Billing needs a client: an unknown patient is identified before it is cashed.
  if (p.isUnidentified) return ouvrirIdent(p, 'fact');

  go(url('checkout', p.admissionId));
}

// ═════════ Banner: emergencies nobody has taken ═════════

function urgencesEnAttente() {
  return all()
    .filter((p) => p.column === 'attente' && p.isEmergency && (nowSec() - p.sinceEpoch) > SEUIL_URGENCE)
    .sort((a, b) => a.sinceEpoch - b.sinceEpoch);
}

let bannerKeys = '';

function majBanner() {
  const urgAlert = $('#urgAlert');
  if (!urgAlert) return;
  const urgs = urgencesEnAttente();
  const keys = urgs.map((p) => p.key).join('|');

  if (keys === bannerKeys) {
    $$('.ua-min', urgAlert).forEach((el) => { el.textContent = minutesDepuis(+el.dataset.dep) + ' min'; });
    return;
  }

  bannerKeys = keys;
  urgAlert.hidden = !urgs.length;
  if (!urgs.length) return;

  const libres = data.free;
  $('#uaHead').textContent =
    (urgs.length === 1 ? 'Urgence non prise en charge depuis plus de 5 min' : `${urgs.length} urgences non prises en charge depuis plus de 5 min`)
    + ` · ${libres} praticien${libres > 1 ? 's' : ''} libre${libres > 1 ? 's' : ''}`;
  $('#uaRows').innerHTML = urgs.map((p) => `
    <div class="ua-row">
      <span class="ua-txt"><b>${esc(p.name)}</b> · ${esc(p.motif)}</span>
      <span class="ua-min" data-dep="${p.sinceEpoch}">${minutesDepuis(p.sinceEpoch)} min</span>
      <button class="ua-btn" type="button" data-key="${esc(p.key)}">${SVG.eclair} Prendre en charge</button>
    </div>`).join('');
}

// ═════════ Clock, chronos, late bookings, figures ═════════

function majStats() {
  const waiting = all().filter((p) => p.column === 'attente');
  const moy = waiting.length
    ? Math.round(waiting.reduce((s, p) => s + (nowSec() - p.sinceEpoch), 0) / waiting.length / 60)
    : 0;
  const st = $('#stAttente');
  if (!st) return;
  st.textContent = moy + ' min';
  st.classList.toggle('warn', moy > 15);
}

function majRetards() {
  $$('.flx .puce.retard[data-rdv]').forEach((el) => {
    const late = Math.floor((nowSec() - +el.dataset.rdv) / 60);
    el.hidden = late < 1;
    if (late >= 1) el.textContent = `En retard · ${late} min`;
  });
}

function tick() {
  const h = $('#horloge');
  if (h) h.textContent = clockLabel(true);
  $$('.flx .chrono[data-dep]').forEach((c) => {
    c.textContent = fmtChrono(+c.dataset.dep);
    c.classList.toggle('long', (nowSec() - +c.dataset.dep) > LONG_WAIT);
  });
  majBanner();
  if (nowSec() % 30 === 0) { majStats(); majRetards(); }
}

// ═════════ Planifié: the fold of today's passages ═════════

function majPassages(open) {
  const btn = $('.flx .kpas');
  const list = $('.flx .kpas-l');
  if (!btn || !list) return;
  list.classList.toggle('on', open);
  btn.setAttribute('aria-expanded', String(open));
  $('.tog', btn).textContent = open ? 'Masquer' : 'Afficher';
}

function bindPassages(signal) {
  const btn = $('.flx .kpas');
  if (!btn) return;
  let open = false;
  try { open = window.sessionStorage.getItem(KPAS_KEY) === '1'; } catch { /* private window */ }
  // Restored without the entrance animation: only a click plays it.
  $('.flx .kpas-l').style.animation = 'none';
  majPassages(open);
  btn.addEventListener('click', () => {
    open = !open;
    $('.flx .kpas-l').style.animation = '';
    majPassages(open);
    try { window.sessionStorage.setItem(KPAS_KEY, open ? '1' : '0'); } catch { /* private window */ }
  }, { signal });
}

// ═════════ Reusable bits of the modals ═════════

/**
 * The motif of the arrival and emergency windows: a badge (or « Autre ») and
 * a separate precision field. The hidden input receives « Badge · précision »,
 * or the precision alone when no badge was chosen.
 */
function motifPicker(cont, hidden, prec, motifs, rouge, opts, signal) {
  const wrap = prec.closest('.m-prec');
  const cpt = $('small', wrap);
  let choix = null; // null = nothing, '' = « Autre », else the badge
  cont.innerHTML = motifs.map((m) => `<button type="button" class="mchip${rouge ? ' rouge' : ''}" data-m="${esc(m)}">${esc(m)}</button>`).join('')
    + '<button type="button" class="mchip autre" data-m="">Autre</button>';
  function maj() {
    const t = prec.value.replace(/\s+/g, ' ').trim();
    hidden.value = choix ? choix + (t ? ' · ' + t : '') : t;
    $$('.mchip', cont).forEach((c) => c.classList.toggle('on', choix !== null && c.dataset.m === choix));
    prec.placeholder = choix === '' ? opts.phAutre : choix ? opts.phPrec : opts.phVide;
    wrap.classList.toggle('req', choix === '' && !t);
    cpt.textContent = prec.value.length + '/80';
    opts.onChange();
  }
  cont.addEventListener('click', (e) => {
    const c = e.target instanceof Element ? e.target.closest('.mchip') : null;
    if (!c) return;
    choix = choix === c.dataset.m ? null : c.dataset.m;
    maj();
    if (choix === '') prec.focus();
  }, { signal });
  prec.addEventListener('input', () => { if (choix === null && prec.value.trim()) choix = ''; maj(); }, { signal });
  maj();
  return { reset: () => { choix = null; prec.value = ''; maj(); } };
}

/** The mock-up's custom dropdown (the species list carries an emoji per option). */
function creerDropdown(cont, options, defaut) {
  let val = defaut;
  const CHEV = '<svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
  const OK = '<svg class="ok" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>';
  function rendreDd() {
    const cur = options.find((o) => o.v === val) || options[0];
    cont.innerHTML = `<button type="button" class="dd-btn" aria-haspopup="listbox">
        <span>${esc(cur.l)}</span>${CHEV}
      </button>
      <div class="dd-menu" role="listbox">${options.map((o) =>
        `<button type="button" class="dd-it${o.v === cur.v ? ' on' : ''}" data-v="${o.v}" role="option">
           <span class="ic">${o.v}</span>${esc(o.l)}${o.v === cur.v ? OK : ''}
         </button>`).join('')}</div>`;
    $('.dd-btn', cont).addEventListener('click', (e) => { e.stopPropagation(); cont.classList.toggle('open'); });
    $$('.dd-it', cont).forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      val = b.dataset.v; cont.classList.remove('open'); rendreDd();
    }));
  }
  rendreDd();
  return {
    get: () => options.find((o) => o.v === val) || options[0],
    set: (v) => { val = v; cont.classList.remove('open'); rendreDd(); },
  };
}

/** Fetches the flux-format fragment of the clinic search for a picker. */
async function rechercher(q, signal) {
  try {
    const r = await fetch(data.urls.search + '&q=' + encodeURIComponent(q), { signal, headers: { Accept: 'text/html' } });
    return r.ok ? await r.text() : '';
  } catch (err) {
    if (err.name === 'AbortError') return null;
    return '';
  }
}

/**
 * Live patient search of a picker: hands the hits to `render`.
 */
function brancherRecherche(input, menu, render, signal) {
  let timer = null;
  let ctrl = null;
  input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(timer);
    if (!q) { menu.classList.remove('open'); return; }
    timer = setTimeout(async () => {
      ctrl?.abort();
      ctrl = new AbortController();
      const html = await rechercher(q, ctrl.signal);
      if (html === null || input.value.trim() !== q) return;
      render(q, html);
      menu.classList.add('open');
    }, 180);
  }, { signal });
}

/** The clinic's file of an animal, found by its chip (null when unknown). */
async function chercherParPuce(chip) {
  try {
    const r = await fetch(data.urls.chip + '?chip=' + encodeURIComponent(chip), { headers: { Accept: 'application/json' } });
    const json = await r.json();
    return json.found ? { ...json.animal, rdv: data.rdv[json.animal.id] || null } : null;
  } catch {
    return null;
  }
}

function hitFromOpt(b) {
  const id = b.dataset.id;
  // A patient booked today carries more than the search knows: phone, alerts.
  const known = all().find((p) => p.animalId === id);
  return {
    id,
    nom: b.dataset.name,
    esp: b.dataset.emoji,
    race: known?.breedAndAge || b.dataset.breed,
    cli: b.dataset.owner,
    tel: known?.phone || null,
    vig: known?.alerts || [],
    rdv: data.rdv[id] || null,
  };
}

function ficheHtml(sel, retrait) {
  return `<div class="ci-fiche">
    <span class="oesp">${esc(sel.esp)}</span>
    <span class="ftxt"><b>${esc(sel.nom)} · ${esc(sel.race)}</b><span>${esc(sel.cli)}${sel.tel ? ' · ' + esc(sel.tel) : ''}</span>
      ${sel.vig.length ? `<span class="fiche-vigs">${sel.vig.map((v) => `<span class="puce vig">${esc(v)}</span>`).join('')}</span>` : ''}
    </span>
    <button type="button" class="fx" aria-label="${esc(retrait)}">${SVG.x12}</button>
  </div>`;
}

/**
 * A chip reader types the 15 digits like a keyboard: « Lire » puts the field
 * in reading state until they arrive (or 10 s pass).
 */
function lecteur(btn, input, label, idle) {
  let timer = null;
  const stop = () => {
    clearTimeout(timer);
    btn.classList.remove('lit');
    label.textContent = idle;
  };
  return {
    start: () => {
      btn.classList.add('lit');
      label.textContent = 'Lecture…';
      input.value = '';
      input.focus();
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (!btn.classList.contains('lit')) return;
        stop();
        showToast('Aucune puce lue — approchez le lecteur de l\'animal ou saisissez les 15 chiffres');
      }, 10000);
    },
    stop,
  };
}

// ═════════ Enregistrer une arrivée — search first, creation follows ═════════

const ci = { mode: 'recherche', sel: null, prio: 0, rdv: 'sans', dd: null, motif: null };

function ouvrirArrivee() {
  ci.mode = 'recherche'; ci.sel = null; ci.prio = 0; ci.rdv = 'sans';
  ['ciSearch', 'ciNewAnimal', 'ciNewProprio', 'ciNewTel'].forEach((id) => { $('#' + id).value = ''; });
  ci.dd.set('🐕');
  ci.motif.reset();
  $('#ciMenu').classList.remove('open');
  ciMajZones(); ciMajFiche(); ciMajPrio(); ciMajCta();
  $('#ciVeil').classList.add('open');
  setTimeout(() => $('#ciSearch').focus(), 30);
}
function fermerCi() { $('#ciVeil')?.classList.remove('open'); }
function ciMajZones() {
  $('#ciZoneExist').style.display = ci.mode === 'recherche' ? '' : 'none';
  $('#ciZoneNew').style.display = ci.mode === 'nouveau' ? '' : 'none';
}
function ciMajFiche() {
  const fiche = $('#ciFiche');
  const rdvZone = $('#ciRdvZone');
  if (!ci.sel) {
    fiche.innerHTML = '<div class="ci-vide">Recherchez un patient — s\'il n\'existe pas encore, la création se propose toute seule.</div>';
    rdvZone.innerHTML = '';
    return;
  }
  fiche.innerHTML = ficheHtml(ci.sel, 'Retirer la sélection');
  $('.fx', fiche).addEventListener('click', () => { ci.sel = null; ci.rdv = 'sans'; ciMajFiche(); ciMajCta(); $('#ciSearch').focus(); });
  if (ci.sel.rdv) {
    ci.rdv = 'rdv';
    rdvZone.innerHTML = `<div class="frow" style="margin:10px 0 0">
      <label class="flbl">Un rendez-vous est prévu aujourd'hui</label>
      <div class="seg" id="ciRdvSeg">
        <button type="button" class="on" data-r="rdv"><b>RDV de ${esc(ci.sel.rdv.time)}</b>&nbsp;<span class="sub">· ${esc(ci.sel.rdv.motif)}</span></button>
        <button type="button" data-r="sans">Sans rendez-vous</button>
      </div>
    </div>`;
    $$('#ciRdvSeg button', rdvZone).forEach((b) => b.addEventListener('click', () => {
      ci.rdv = b.dataset.r;
      $$('#ciRdvSeg button', rdvZone).forEach((x) => x.classList.toggle('on', x === b));
      ciMajCta();
    }));
  } else { ci.rdv = 'sans'; rdvZone.innerHTML = ''; }
}
function ciMajPrio() {
  $$('#ciPrioSeg button').forEach((b) => b.classList.toggle('on', +b.dataset.p === ci.prio));
}
function ciNomCourant() {
  if (ci.mode === 'recherche') return ci.sel ? ci.sel.nom : null;
  return $('#ciNewAnimal').value.trim() || null;
}
function ciFormValide() {
  if (ci.mode === 'recherche') return !!ci.sel;
  return ['ciNewAnimal', 'ciNewProprio', 'ciNewTel'].every((id) => $('#' + id).value.trim());
}
function ciMajCta() {
  const btn = $('#ciValider');
  if (!btn) return;
  btn.disabled = !ciFormValide();
  const nom = ciNomCourant();
  $('#ciValiderTxt').textContent = nom ? `Enregistrer l'arrivée — ${nom}` : 'Enregistrer l\'arrivée';
}
function ciValider() {
  if ($('#ciValider').disabled) return;
  const motif = $('#ciMotif').value.trim();
  const base = { _token: data.tokens.checkin, priority: ci.prio, triageNotes: motif };

  if (ci.mode === 'recherche') {
    const lieRdv = ci.sel.rdv && ci.rdv === 'rdv';
    post(data.urls.checkin, {
      ...base,
      appointmentId: lieRdv ? ci.sel.rdv.appointmentId : null,
      animalId: ci.sel.id,
      animalName: ci.sel.nom,
      triageNotes: motif || (lieRdv ? ci.sel.rdv.motif : ''),
    });
  } else {
    post(data.urls.checkin, {
      ...base,
      newAnimalName: $('#ciNewAnimal').value.trim(),
      newAnimalSpecies: ci.dd.get().s,
      newOwnerName: $('#ciNewProprio').value.trim(),
      newOwnerPhone: $('#ciNewTel').value.trim(),
    });
  }
  $('#ciValider').disabled = true;
}

function bindArrivee(signal) {
  ci.dd = creerDropdown($('#ddCiEspece'), ESPECES.filter((o) => o.v !== '🐦'), '🐕');
  ci.motif = motifPicker($('#ciChips'), $('#ciMotif'), $('#ciPrec'), CI_MOTIFS, false, {
    onChange: () => ciMajCta(),
    phVide: 'Choisissez ci-dessus, ou décrivez directement ici — facultatif',
    phPrec: 'Précisions — facultatif',
    phAutre: 'Décrivez le motif',
  }, signal);

  const veil = $('#ciVeil');
  $('#btnArrivee').addEventListener('click', ouvrirArrivee, { signal });
  $('#ciClose').addEventListener('click', fermerCi, { signal });
  $('#ciAnnuler').addEventListener('click', fermerCi, { signal });
  veil.addEventListener('click', (e) => { if (e.target === veil) fermerCi(); }, { signal });
  $('#ciTagInput').addEventListener('click', () => $('#ciSearch').focus(), { signal });
  $('#ciRetourRecherche').addEventListener('click', () => {
    ci.mode = 'recherche'; ciMajZones(); ciMajCta();
    setTimeout(() => $('#ciSearch').focus(), 20);
  }, { signal });
  $$('#ciPrioSeg button').forEach((b) => b.addEventListener('click', () => { ci.prio = +b.dataset.p; ciMajPrio(); }, { signal }));
  $$('.ci-req').forEach((el) => el.addEventListener('input', ciMajCta, { signal }));
  $('#ciValider').addEventListener('click', ciValider, { signal });

  const input = $('#ciSearch');
  const menu = $('#ciMenu');
  brancherRecherche(input, menu, (q, html) => {
    menu.innerHTML = html + `<button type="button" class="ci-opt creer" id="ciOptCreer">
      <span class="oesp">＋</span>
      <span class="otxt"><b>Créer « ${esc(q)} »</b><span>Nouveau client — fiche complétée plus tard</span></span>
    </button>`;
    $$('.ci-opt:not(.creer)', menu).forEach((b) => b.addEventListener('click', () => {
      ci.sel = hitFromOpt(b);
      menu.classList.remove('open');
      input.value = '';
      ciMajFiche(); ciMajCta();
    }));
    $('#ciOptCreer', menu).addEventListener('click', () => {
      ci.mode = 'nouveau';
      menu.classList.remove('open');
      $('#ciNewAnimal').value = q.charAt(0).toUpperCase() + q.slice(1);
      ciMajZones(); ciMajCta();
      setTimeout(() => $('#ciNewProprio').focus(), 20);
    });
  }, signal);

  veil.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (menu.classList.contains('open') && $('.ci-opt', menu)) { e.preventDefault(); $('.ci-opt', menu).click(); return; }
    if (!$('#ciValider').disabled) { e.preventDefault(); ciValider(); }
  }, { signal });
}

// ═════════ Arrivée d'un RDV — accueil rapide avant la salle d'attente ═════════

let ar = null;

function ouvrirArriveeRdv(p) {
  const veil = $('#veilA');
  const box = $('#boxA');
  if (!veil || !box) return;
  ar = { p, checks: new Set(p.isSurgical ? ['jeun'] : []), solde: p.balanceLabel ? 'plus' : null, tel: null };
  const ecart = Math.round((nowSec() - p.sinceEpoch) / 60);
  const pr = p.practitioner || 'le vétérinaire';
  const vigs = p.alerts.map((v) => v.replace(/^⚠\s*/, ''));
  box.innerHTML = `<div class="mhead"><b>Arrivée de ${esc(p.name)}</b><button class="mclose" type="button" data-arx aria-label="Fermer">${SVG.x14}</button></div>
    <div class="mbody" style="display:flex;flex-direction:column;gap:14px">
      <div class="ar-rec"><span class="e">${esc(p.emoji)}</span><div><b>${esc(p.name)}</b><small>${esc([p.breedAndAge, p.owner].filter(Boolean).join(' · '))}</small><small>${esc(p.motif)} · ${esc(p.practitioner || 'Sans praticien')}</small></div>
        <div class="rdv"><small>Rendez-vous</small><b>${esc(p.slotLabel)}</b>${ecart > 5 ? `<span class="ec ret">${ecart} min de retard</span>` : ecart < -5 ? `<span class="ec av">${-ecart} min d'avance</span>` : '<span class="ec ok">À l\'heure</span>'}</div></div>
      ${vigs.length ? `<div class="ar-vig">${vigs.map((v) => `<span>${esc(v)}</span>`).join('')}</div>` : ''}
      <div class="ar-g">
        <div class="ar-sec"><label class="flbl" for="arKg">Pesée à l'accueil</label><div class="ar-kg" id="arKgW"><input id="arKg" inputmode="decimal" maxlength="5" placeholder="—" autocomplete="off"><span>kg</span></div><small class="h">Dernier : ${esc(p.lastWeight || 'aucun')}</small></div>
        <div class="ar-sec"><span class="flbl">Contact</span><div class="ar-tel" id="arTel"><b>${esc(p.phone || 'Non renseigné')}</b>${p.ownerId ? `<button type="button" data-artel>${p.phone ? 'Modifier' : 'Ajouter'}</button>` : ''}</div><small class="h" id="arTelH">Confirmé auprès du client</small></div>
      </div>
      <div class="ar-sec"><span class="flbl">À signaler au vétérinaire</span><div class="ar-chk">
        ${p.isSurgical ? '<button type="button" data-c="jeun" class="on">À jeun depuis la veille</button>' : ''}
        <button type="button" data-c="sympt">Nouveau symptôme depuis la prise de RDV</button>
        <button type="button" data-c="docs">Apporte des documents</button>
        <button type="button" data-c="trait">Traitement en cours</button></div></div>
      ${p.balanceLabel ? `<div class="ar-du">Reste dû <b>${esc(p.balanceLabel)}</b> sur une visite précédente<span class="bt"><button type="button" data-ds="mtn">Encaisser maintenant</button><button type="button" data-ds="plus" class="on">Avec cette visite</button></span></div>` : ''}
      <div class="ar-sec"><label class="flbl" for="arNote">Note pour ${esc(pr)} <span style="font-weight:400;color:var(--encre-35)">· facultatif</span></label><textarea class="ar-note" id="arNote" maxlength="140" placeholder="Ex. boite depuis hier soir, mange moins"></textarea></div>
    </div>
    <div class="mfoot"><span class="ar-kbd">Entrée pour valider</span><button class="btn" type="button" data-arx>Annuler</button><button class="btn primaire" type="button" data-arok>${SVG.ok}Placer en salle d'attente</button></div>`;
  veil.classList.add('open');
  setTimeout(() => $('#arKg')?.focus(), 60);
}
function fermerArriveeRdv() { $('#veilA')?.classList.remove('open'); ar = null; }

function arTelEditer() {
  const zone = $('#arTel');
  if (!zone || !ar) return;
  const digits = chiffres(ar.p.phone, 10);
  zone.innerHTML = `<input class="id-tel" id="arTelIn" inputmode="numeric" placeholder="06 00 00 00 00" value="${esc(fmtTel(digits))}" autocomplete="off">`;
  $('#arTelH').textContent = 'Nouveau numéro enregistré sur la fiche du client';
  ar.tel = '';
  const i = $('#arTelIn');
  i.focus();
  i.select();
}

function arValider() {
  if (!ar) return;
  const p = ar.p;
  const kgv = $('#arKg').value;
  const kg = parseFloat(kgv.replace(',', '.'));
  if (kgv && (Number.isNaN(kg) || kg <= 0 || kg > 120)) {
    $('#arKgW').classList.add('ko');
    $('#arKg').focus();
    return;
  }
  const telIn = $('#arTelIn');
  let phone = null;
  if (telIn) {
    const d = chiffres(telIn.value, 10);
    if (d.length !== 10 || !d.startsWith('0')) { telIn.classList.add('ko'); telIn.focus(); return; }
    if (d !== chiffres(p.phone, 10)) phone = d;
  }
  const note = $('#arNote').value.replace(/\s+/g, ' ').trim();
  const settle = ar.solde === 'mtn';
  post(data.urls.checkin, {
    _token: data.tokens.checkin,
    appointmentId: p.appointmentId,
    animalId: p.animalId,
    animalName: p.name,
    weight: kgv || null,
    checks: [...ar.checks],
    note,
    phone,
    ownerId: phone ? p.ownerId : null,
    settle: settle ? 'now' : null,
    settleInvoiceId: settle ? p.settleInvoiceId : null,
    settleOwner: settle ? p.owner : null,
  });
  $('[data-arok]', $('#boxA')).disabled = true;
}

function bindArriveeRdv(signal) {
  const veil = $('#veilA');
  if (!veil) return;
  veil.addEventListener('mousedown', (e) => { if (e.target === veil) fermerArriveeRdv(); }, { signal });
  veil.addEventListener('input', (e) => {
    const t = e.target;
    if (t.id === 'arKg') {
      // « 4,2 »: digits and one decimal, the dot typed as a comma.
      let v = t.value.replace(/[^\d,.]/g, '').replace('.', ',');
      const i = v.indexOf(',');
      if (i > -1) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/,/g, '').slice(0, 1);
      t.value = v;
      $('#arKgW').classList.remove('ko');
    }
    if (t.id === 'arTelIn') { t.value = fmtTel(chiffres(t.value, 10)); t.classList.remove('ko'); }
  }, { signal });
  veil.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t || !ar) return;
    if (t.closest('[data-arx]')) { fermerArriveeRdv(); return; }
    const c = t.closest('[data-c]');
    if (c) { const k = c.dataset.c; if (ar.checks.has(k)) ar.checks.delete(k); else ar.checks.add(k); c.classList.toggle('on'); return; }
    const d = t.closest('[data-ds]');
    if (d) { ar.solde = d.dataset.ds; $$('[data-ds]', veil).forEach((x) => x.classList.toggle('on', x === d)); return; }
    if (t.closest('[data-artel]')) { arTelEditer(); return; }
    if (t.closest('[data-arok]')) arValider();
  }, { signal });
  veil.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { e.stopPropagation(); fermerArriveeRdv(); }
    if (e.key === 'Enter' && e.target.id !== 'arNote') { e.preventDefault(); arValider(); }
  }, { signal, capture: true });
}

// ═════════ Urgence — one screen: the motif first, identity can wait ═════════

const urg = { sel: null, dd: null, par: 'proprio', chip: null, motif: null, reader: null };

function ouvrirUrgence() {
  urg.sel = null;
  urg.chip = null;
  urg.par = 'proprio';
  $('#urgSearch').value = '';
  $('#urgMenu').classList.remove('open');
  urg.motif.reset();
  urg.dd.set('🐾');
  urg.reader.stop();
  $$('#uPar button').forEach((x) => x.classList.toggle('on', x.dataset.v === 'proprio'));
  $('#uTiers').hidden = true;
  ['uDec', 'uDecTel'].forEach((i) => { $('#' + i).value = ''; });
  majAide();
  urgMajIdent(); urgMajCta();
  $('#urgVeil').classList.add('open');
  setTimeout(() => $('#urgPrec').focus(), 30);
}
function fermerUrgence() { urg.reader?.stop(); $('#urgVeil')?.classList.remove('open'); }
function majAide() {
  $('#uAide').textContent = urg.chip
    ? `Puce ${fmtPuce(urg.chip)} inconnue de la clinique : elle est notée pour identifier le patient, qui entre comme « Inconnu ».`
    : 'Sans fiche, le patient entre comme « Inconnu » : la carte invite à l\'identifier dès que possible.';
}
function urgMajIdent() {
  $('#urgIdent').style.display = urg.sel ? 'none' : '';
  const fiche = $('#urgFiche');
  if (!urg.sel) { fiche.innerHTML = ''; return; }
  fiche.innerHTML = ficheHtml(urg.sel, 'Retirer l\'identification');
  $('.fx', fiche).addEventListener('click', () => { urg.sel = null; urgMajIdent(); urgMajCta(); });
}
function urgMajCta() {
  const btn = $('#urgEnregistrer');
  if (!btn) return;
  btn.disabled = !$('#urgMotif').value.trim();
  $('#urgEnregistrerTxt').textContent = urg.sel ? `Admettre ${urg.sel.nom} en urgence` : 'Admettre en urgence';
}
function urgValider() {
  if ($('#urgEnregistrer').disabled) return;
  const espece = urg.dd.get();
  const tiers = !urg.sel && urg.par === 'tiers';
  const tel = chiffres($('#uDecTel').value, 10);
  if (tiers && tel && tel.length !== 10) { $('#uDecTel').classList.add('ko'); $('#uDecTel').focus(); return; }
  post(data.urls.emergency, {
    _token: data.tokens.emergency,
    triage_level: 'emergency',
    triage_notes: $('#urgMotif').value.trim(),
    known_animal_id: urg.sel ? urg.sel.id : null,
    animal_name: urg.sel ? urg.sel.nom : null,
    observed_species: !urg.sel && espece.v !== '🐾' ? espece.l : null,
    brought_by: urg.sel ? null : urg.par,
    declarant_name: tiers ? $('#uDec').value.trim() : null,
    declarant_phone: tiers && tel ? tel : null,
    scanned_microchip: urg.sel ? null : urg.chip,
    time_zone: data.tz,
  });
  $('#urgEnregistrer').disabled = true;
}

/** A chip read (or 15 digits typed) in the emergency search: the clinic's file, or noted as unknown. */
async function urgPuce(chip) {
  urg.reader.stop();
  const found = await chercherParPuce(chip);
  $('#urgSearch').value = '';
  $('#urgMenu').classList.remove('open');
  if (found) {
    urg.sel = found;
    urg.chip = null;
    urgMajIdent(); urgMajCta();
    showToast(`Puce lue · ${fmtPuce(chip)} → ${found.nom} (fiche clinique)`);
    return;
  }
  urg.chip = chip;
  majAide();
  showToast(`Puce ${fmtPuce(chip)} inconnue de la clinique — notée pour l'identification`);
}

function bindUrgence(signal) {
  urg.dd = creerDropdown($('#ddUrgEspece'), ESPECES, '🐾');
  urg.motif = motifPicker($('#urgChips'), $('#urgMotif'), $('#urgPrec'), URG_MOTIFS, true, {
    onChange: () => urgMajCta(),
    phVide: 'Choisissez ci-dessus, ou décrivez directement ici',
    phPrec: 'Précisions — facultatif (ex. : depuis 20 min, patte arrière)',
    phAutre: 'Décrivez la situation *',
  }, signal);
  urg.reader = lecteur($('#uScan'), $('#urgSearch'), $('#uScan span'), 'Lire la puce');

  const veil = $('#urgVeil');
  $('#btnUrgence').addEventListener('click', ouvrirUrgence, { signal });
  $('#urgClose').addEventListener('click', fermerUrgence, { signal });
  $('#urgAnnuler').addEventListener('click', fermerUrgence, { signal });
  veil.addEventListener('click', (e) => { if (e.target === veil) fermerUrgence(); }, { signal });
  $('#urgEnregistrer').addEventListener('click', urgValider, { signal });
  $('#uScan').addEventListener('click', (e) => { e.stopPropagation(); urg.reader.start(); }, { signal });
  $('#uPar').addEventListener('click', (e) => {
    const b = e.target instanceof Element ? e.target.closest('button') : null;
    if (!b) return;
    urg.par = b.dataset.v;
    $$('#uPar button').forEach((x) => x.classList.toggle('on', x === b));
    $('#uTiers').hidden = urg.par !== 'tiers';
  }, { signal });
  $('#uDecTel').addEventListener('input', (e) => { e.target.value = fmtTel(chiffres(e.target.value, 10)); e.target.classList.remove('ko'); }, { signal });

  const input = $('#urgSearch');
  const menu = $('#urgMenu');
  // 15 digits are a chip, whether the reader typed them or the desk did.
  input.addEventListener('input', () => {
    const d = input.value.replace(/[\s-]/g, '');
    if (/^\d{15}$/.test(d)) urgPuce(d);
  }, { signal });
  brancherRecherche(input, menu, (q, html) => {
    if (/^\d{15}$/.test(q.replace(/[\s-]/g, ''))) return;
    menu.innerHTML = html.trim() || '<div class="ci-nores">Aucune fiche — admettez sans identification, régularisation après.</div>';
    $$('.ci-opt', menu).forEach((b) => b.addEventListener('click', () => {
      urg.sel = hitFromOpt(b);
      urg.chip = null;
      menu.classList.remove('open');
      input.value = '';
      urgMajIdent(); urgMajCta();
    }));
  }, signal);

  veil.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (menu.classList.contains('open') && $('.ci-opt', menu)) { e.preventDefault(); $('.ci-opt', menu).click(); return; }
    if (!$('#urgEnregistrer').disabled) { e.preventDefault(); urgValider(); }
  }, { signal });
}

// ═════════ Modifier le motif ═════════

let motifKey = null;

function ouvrirMotif(p) {
  motifKey = p.key;
  $('#motifTitre').textContent = `Modifier le motif — ${p.name}`;
  $('#motifInput').value = p.motif === 'À préciser' ? '' : p.motif;
  $('#motifVeil').classList.add('open');
  setTimeout(() => $('#motifInput').select(), 30);
}
function fermerMotif() { $('#motifVeil')?.classList.remove('open'); }
function motifValider() {
  const p = patient(motifKey);
  const motif = $('#motifInput').value.trim();
  if (!p || !motif) { $('#motifInput').focus(); return; }
  post(url('motif', p.admissionId), { _token: data.tokens.flux, motif });
}

function bindMotif(signal) {
  const mv = $('#motifVeil');
  $('#motifClose').addEventListener('click', fermerMotif, { signal });
  $('#motifAnnuler').addEventListener('click', fermerMotif, { signal });
  $('#motifValider').addEventListener('click', motifValider, { signal });
  mv.addEventListener('click', (e) => { if (e.target === mv) fermerMotif(); }, { signal });
  $('#motifInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); motifValider(); } }, { signal });
}

// ═════════ Identifier le patient — puce, fiche existante, nouvelle fiche ═════════

let id = null;

function ouvrirIdent(p, ctx) {
  const veil = $('#veilI');
  if (!veil || !p?.intake) return;
  const a = p.intake;
  id = {
    p, tab: 'puce', sel: null, chip: null, pro: a.broughtBy === 'proprio' ? 'connu' : 'trouve',
    civ: 'Mme', sexe: 'F', age: 'adulte', dd: null, reader: null,
  };
  const dec = a.declarant ? ` (${a.declarant}${a.declarantPhone ? ' · ' + a.declarantPhone : ''})` : '';
  $('#iBody').innerHTML = `
    ${ctx === 'fact' ? `<div class="id-fact">${SVG.ident}Identification requise avant d'encaisser : la facture doit être au nom d'un client.</div>` : ''}
    <div class="ar-rec"><span class="e">${esc(p.emoji)}</span><div><b>Inconnu · ${esc(p.motif)}</b><small>Admis à ${esc(a.time)} · amené par ${esc(PAR[a.broughtBy])}${esc(dec)}${p.practitioner ? ' · ' + esc(p.practitioner) : ''}</small></div>
      <div class="rdv"><small>${p.column === 'attente' ? 'Admis depuis' : 'En soins depuis'}</small><b>${fmtChrono(p.sinceEpoch)}</b></div></div>
    <div class="seg id-seg" id="iSeg"><button type="button" data-t="puce" class="on">Lire la puce</button><button type="button" data-t="fiche">Fiche existante</button><button type="button" data-t="nouv">Nouvelle fiche</button></div>
    <div class="id-pane on" data-p="puce">
      <div class="id-lect"><button type="button" class="btn" id="iScan">${SVG.scan}<span>Lire avec le lecteur</span></button><span class="ou">ou</span>
        <label class="id-puce"><input id="iPuce" inputmode="numeric" placeholder="Saisir les 15 chiffres" autocomplete="off"><small id="iPuceN">0/15</small></label></div>
      <div class="id-res" id="iPuceRes"><div class="id-msg">Pas de puce ? Tatouage illisible ? Cherchez une fiche existante ou créez-en une.</div></div>
    </div>
    <div class="id-pane" data-p="fiche">
      <div class="ci-taginput"><input type="search" id="iQ" placeholder="Nom de l'animal, propriétaire, téléphone…" autocomplete="off"></div>
      <div class="id-list" id="iList"></div>
    </div>
    <div class="id-pane" data-p="nouv" id="iNouv"></div>`;
  id.reader = lecteur($('#iScan'), $('#iPuce'), $('#iScan span'), 'Lire avec le lecteur');
  rendreNouv();
  idMaj();
  veil.classList.add('open');
  // The chip read at the desk when the emergency came in is the first lead.
  if (a.scannedMicrochip) {
    $('#iPuce').value = fmtPuce(a.scannedMicrochip);
    $('#iPuceN').textContent = '15/15';
    chercherPuce(a.scannedMicrochip);
  }
  setTimeout(() => $('#iPuce')?.focus(), 40);
}
function fermerIdent() { id?.reader?.stop(); $('#veilI')?.classList.remove('open'); }

function idFicheHtml(f) {
  return `<div class="id-fiche"><span class="oesp">${esc(f.esp)}</span><div><span class="id-src">Fiche de la clinique</span>
    <b>${esc(f.nom)} · ${esc(f.race)}</b><span class="s">${esc(f.cli)}${f.tel ? ' · ' + esc(f.tel) : ''}</span>
    ${f.vig && f.vig.length ? `<div class="vg">${f.vig.map((v) => `<i>${esc(v)}</i>`).join('')}</div>` : ''}</div>
    <button type="button" class="fx" data-fx aria-label="Changer">${SVG.x12}</button></div>`;
}

async function chercherPuce(d) {
  const r = $('#iPuceRes');
  id.sel = null;
  id.chip = null;
  if (d.length < 15) {
    r.innerHTML = '<div class="id-msg">Pas de puce ? Tatouage illisible ? Cherchez une fiche existante ou créez-en une.</div>';
    return idMaj();
  }
  id.reader.stop();
  const f = await chercherParPuce(d);
  if (!id || chiffres($('#iPuce')?.value, 15) !== d) return;
  if (f) { id.sel = f; r.innerHTML = idFicheHtml(f); return idMaj(); }
  // Not in the clinic's files: the chip goes on the new file.
  id.chip = d;
  if (!d.startsWith('250')) {
    r.innerHTML = `<div class="id-msg ko"><div><b>Puce étrangère (code pays ${esc(d.slice(0, 3))})</b> — absente du fichier I-CAD. Créez la fiche et notez la puce, la recherche se fait via <a href="${EUROPETNET}" target="_blank" rel="noopener">Europetnet</a>.</div></div>`;
    return idMaj();
  }
  r.innerHTML = `<div class="id-msg"><div><b>Puce inconnue de la clinique.</b><br>L'animal est peut-être déclaré au fichier national.</div><button type="button" class="btn" id="iIcad" disabled data-tip="${BIENTOT} — l'interrogation de l'I-CAD n'est pas encore branchée">Interroger l'I-CAD</button></div>
    <div class="u-aide">Créez la fiche depuis « Nouvelle fiche » : la puce y sera reportée.</div>`;
  return idMaj();
}

function rendreNouv() {
  const a = id.p.intake;
  const n = $('#iNouv');
  const split = splitNom(a.declarant || '');
  // A found animal is filed under whoever declared it; the declarant is completed if needed.
  const decOk = id.pro === 'trouve' && split.prenom && split.nom && a.declarantPhone;
  const keep = { a: $('#iNomA')?.value || '', e: id.dd ? id.dd.get().v : id.p.emoji };
  const ownerForm = (titre) => `${titre}<div class="id-g" style="margin-top:8px"><div class="seg" id="iCiv"><button type="button" data-v="M." class="${id.civ === 'M.' ? 'on' : ''}">M.</button><button type="button" data-v="Mme" class="${id.civ === 'Mme' ? 'on' : ''}">Mme</button></div>
        <input class="finput" id="iNomP" placeholder="Nom *" maxlength="40"><input class="finput" id="iPreP" placeholder="Prénom *" maxlength="40"></div>
      <div class="id-g2" style="margin-top:8px"><input class="finput id-tel" id="iTel" placeholder="Téléphone * · 06 00 00 00 00" inputmode="numeric"><span></span></div>`;
  n.innerHTML = `<div class="id-blk">Propriétaire</div>
    <div class="seg" id="iPro"><button type="button" data-v="connu" class="${id.pro === 'connu' ? 'on' : ''}">Connu</button><button type="button" data-v="trouve" class="${id.pro === 'trouve' ? 'on' : ''}">Inconnu · animal trouvé</button></div>
    ${id.pro === 'connu' ? ownerForm('')
      : decOk ? `<div class="id-msg" style="margin-top:8px"><div>Fiche créée sans propriétaire, déclarant <b>${esc(a.declarant)}</b> · ${esc(a.declarantPhone)}. Elle se rattachera au propriétaire s'il se manifeste.</div></div>`
        : ownerForm(`<div class="id-msg" style="margin-top:8px"><div>Fiche créée sans propriétaire, rattachée à la personne qui a trouvé l'animal${a.declarant ? ` (<b>${esc(a.declarant)}</b>)` : ''} : complétez ses coordonnées.</div></div>`)}
    <div class="id-blk">Animal</div>
    <div class="id-g2"><input class="finput" id="iNomA" placeholder="Nom (si connu)" maxlength="30"><div class="dd id-dd" id="iEsp"></div></div>
    <div class="id-g2" style="margin-top:8px"><div class="seg" id="iSexe"><button type="button" data-v="M" class="${id.sexe === 'M' ? 'on' : ''}">Mâle</button><button type="button" data-v="F" class="${id.sexe === 'F' ? 'on' : ''}">Femelle</button></div>
      <div class="seg" id="iAge"><button type="button" data-v="jeune" class="${id.age === 'jeune' ? 'on' : ''}">Jeune</button><button type="button" data-v="adulte" class="${id.age === 'adulte' ? 'on' : ''}">Adulte</button><button type="button" data-v="senior" class="${id.age === 'senior' ? 'on' : ''}">Senior</button></div></div>`;
  id.dd = creerDropdown($('#iEsp'), ESPECES, keep.e);
  $('#iNomA').value = keep.a;
  if ($('#iNomP') && id.pro === 'trouve') {
    // Prefilled with what the desk took down at intake.
    $('#iNomP').value = split.nom.toUpperCase();
    $('#iPreP').value = split.prenom;
    $('#iTel').value = a.declarantPhone || '';
    if (split.civ) { id.civ = split.civ; $$('#iCiv button').forEach((x) => x.classList.toggle('on', x.dataset.v === id.civ)); }
  }
}

/** « M. Paul Garcia » → civility, first name, last name. */
function splitNom(s) {
  const w = s.trim().split(/\s+/).filter(Boolean);
  let civ = null;
  if (w.length && /^(m\.?|mr\.?|monsieur)$/i.test(w[0])) { civ = 'M.'; w.shift(); }
  else if (w.length && /^(mme\.?|madame)$/i.test(w[0])) { civ = 'Mme'; w.shift(); }
  const nom = w.pop() || '';
  return { civ, prenom: w.join(' '), nom };
}

function telOk() { const t = $('#iTel'); return t && chiffres(t.value, 10).length === 10 && t.value.startsWith('0'); }
function proprioOk() {
  if (!$('#iNomP')) return true; // a found animal filed under a complete declarant
  return $('#iNomP').value.trim().length > 1 && $('#iPreP').value.trim().length > 0 && telOk();
}

function idMaj() {
  const ok = $('#iOk');
  if (!ok || !id) return;
  let txt = 'Rattacher';
  let on = false;
  if ((id.tab === 'puce' || id.tab === 'fiche') && id.sel) { txt = `Rattacher à ${id.sel.nom}`; on = true; }
  if (id.tab === 'nouv') { txt = 'Créer la fiche'; on = proprioOk(); }
  ok.textContent = txt;
  ok.disabled = !on;
}

function idValider() {
  if (!id || $('#iOk').disabled) return;
  const p = id.p;
  if (id.sel) {
    post(data.urls.identify, { _token: data.tokens.identify, admissionId: p.admissionId, tab: 'owner', animalId: id.sel.id, animalName: id.sel.nom });
  } else {
    const a = p.intake;
    const split = splitNom(a.declarant || '');
    const form = !!$('#iNomP');
    const nomA = $('#iNomA').value.trim();
    post(data.urls.identify, {
      _token: data.tokens.identify,
      admissionId: p.admissionId,
      tab: 'new',
      owner: id.pro,
      civility: form ? id.civ : (split.civ || ''),
      lastName: form ? $('#iNomP').value.trim() : split.nom,
      firstName: form ? $('#iPreP').value.trim() : split.prenom,
      phone: form ? chiffres($('#iTel').value, 10) : chiffres(a.declarantPhone, 10),
      animalName: nomA || (id.pro === 'trouve' ? 'Trouvé ' + a.time : ''),
      species: id.dd.get().s,
      sex: id.sexe,
      age: id.age,
      microchip: id.chip || a.scannedMicrochip || '',
    });
  }
  $('#iOk').disabled = true;
}

function bindIdent(signal) {
  const veil = $('#veilI');
  if (!veil) return;
  $('#iClose').addEventListener('click', fermerIdent, { signal });
  $('#iPlus').addEventListener('click', fermerIdent, { signal });
  $('#iOk').addEventListener('click', idValider, { signal });
  veil.addEventListener('click', (e) => { if (e.target === veil) fermerIdent(); }, { signal });

  let ctrl = null;
  let timer = null;
  veil.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t || !id) return;
    const tb = t.closest('#iSeg button');
    if (tb) {
      id.tab = tb.dataset.t; id.sel = null;
      $$('#iSeg button', veil).forEach((x) => x.classList.toggle('on', x === tb));
      $$('.id-pane', veil).forEach((x) => x.classList.toggle('on', x.dataset.p === id.tab));
      if (id.tab === 'puce') chercherPuce(chiffres($('#iPuce').value, 15));
      if (id.tab === 'fiche') { $('#iList').innerHTML = ''; $('#iQ').value = ''; setTimeout(() => $('#iQ').focus(), 30); }
      if (id.tab === 'nouv') setTimeout(() => { const f = $(id.pro === 'connu' || $('#iNomP') ? '#iNomP' : '#iNomA'); if (f) f.focus(); }, 30);
      return idMaj();
    }
    if (t.closest('#iScan')) { id.reader.start(); return; }
    if (t.closest('[data-fx]')) {
      id.sel = null;
      if (id.tab === 'puce') { $('#iPuce').value = ''; $('#iPuceN').textContent = '0/15'; chercherPuce(''); $('#iPuce').focus(); }
      else { $('#iList').innerHTML = ''; $('#iQ').focus(); }
      return idMaj();
    }
    const o = t.closest('#iList .ci-opt');
    if (o) { id.sel = hitFromOpt(o); $('#iList').innerHTML = idFicheHtml(id.sel); return idMaj(); }
    const sg = t.closest('#iPro button, #iCiv button, #iSexe button, #iAge button');
    if (sg) {
      const k = { iPro: 'pro', iCiv: 'civ', iSexe: 'sexe', iAge: 'age' }[sg.parentElement.id];
      id[k] = sg.dataset.v;
      if (k === 'pro') rendreNouv();
      else $$('button', sg.parentElement).forEach((x) => x.classList.toggle('on', x === sg));
      return idMaj();
    }
  }, { signal });
  veil.addEventListener('input', (e) => {
    const t = e.target;
    if (!id) return;
    if (t.id === 'iPuce') { const d = chiffres(t.value, 15); t.value = fmtPuce(d); $('#iPuceN').textContent = d.length + '/15'; chercherPuce(d); }
    if (t.id === 'iTel') { t.value = fmtTel(chiffres(t.value, 10)); t.classList.remove('ko'); }
    if (t.id === 'iNomP') t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').toUpperCase();
    if (t.id === 'iPreP' || t.id === 'iNomA') t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').replace(/(^|[\s'-])\p{Ll}/gu, (m) => m.toUpperCase());
    if (t.id === 'iQ') {
      const q = t.value.trim();
      id.sel = null;
      clearTimeout(timer);
      if (!q) { $('#iList').innerHTML = ''; return idMaj(); }
      timer = setTimeout(async () => {
        ctrl?.abort();
        ctrl = new AbortController();
        const html = await rechercher(q, ctrl.signal);
        if (html === null || !id || $('#iQ').value.trim() !== q) return;
        $('#iList').innerHTML = html.trim() || '<div class="ci-nores">Aucune fiche — créez-la depuis « Nouvelle fiche ».</div>';
      }, 180);
    }
    idMaj();
  }, { signal });
  veil.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { e.stopPropagation(); fermerIdent(); return; }
    if (e.key === 'Enter' && !$('#iOk').disabled && e.target.id !== 'iQ') { e.preventDefault(); idValider(); }
  }, { signal, capture: true });
}

// ═════════ Card action menu ═════════

let actKey = null;

function menuItems(p) {
  // « Identifier le patient » leads the menu while the file is unknown.
  const ident = p.isUnidentified ? [{ a: 'ident', ico: 'pers', l: 'Identifier le patient' }] : [];
  if (p.column === 'planifie') {
    return [
      { a: 'avancer', ico: 'fleche', l: 'Enregistrer l\'arrivée' },
      { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
      { a: 'modifrdv', ico: 'crayon', l: 'Modifier le rendez-vous' },
      'sep',
      { a: 'annulerdv', ico: 'croix', l: 'Annuler le rendez-vous', cls: 'rouge' },
    ];
  }
  if (p.column === 'attente') {
    return [
      ...ident,
      { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
      { a: 'motif', ico: 'crayon', l: 'Modifier le motif' },
      ...(p.triageLevel === 'standard' ? [{ a: 'prioritaire', ico: 'prio', l: 'Passer en prioritaire' }] : []),
      ...(!p.isEmergency ? [{ a: 'urgence', ico: 'alerte', l: 'Passer en urgence' }] : []),
      'sep',
      { a: 'annuler', ico: 'croix', l: 'Annuler l\'arrivée', cls: 'rouge' },
    ];
  }
  if (p.column === 'charge') {
    return [
      ...ident,
      { a: 'consult', ico: 'steto', l: 'Ouvrir la consultation', off: !p.consultationId && 'Aucune consultation ouverte' },
      { a: 'avancer', ico: 'fleche', l: 'Passer en sortie' },
      { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
      { a: 'reassigner', ico: 'pers', l: 'Réassigner le praticien', off: BIENTOT },
      'sep',
      { a: 'retour', ico: 'retour', l: 'Renvoyer en salle d\'attente' },
    ];
  }
  return [
    ...ident,
    { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
    { a: 'facture', ico: 'print', l: 'Imprimer la facture', off: 'La facture est émise à l\'encaissement' },
    { a: 'email', ico: 'mail', l: 'Envoyer la facture par e-mail', off: 'La facture est émise à l\'encaissement' },
    'sep',
    { a: 'retour', ico: 'retour', l: 'Renvoyer en prise en charge' },
  ];
}

function ouvrirActMenu(btn, key) {
  const p = patient(key);
  const pop = $('#actPop');
  if (!p || !pop) return;
  actKey = key;
  pop.innerHTML = menuItems(p).map((it) => it === 'sep'
    ? '<div class="act-sep"></div>'
    : `<button type="button" class="act-it ${it.cls || ''}" data-a="${it.a}"${it.off ? ` disabled data-tip="${esc(it.off)}"` : ''}>${SVG[it.ico]}${esc(it.l)}</button>`).join('');
  const r = btn.getBoundingClientRect();
  pop.classList.add('open');
  const w = pop.offsetWidth, hh = pop.offsetHeight;
  const x = Math.min(r.right - w, window.innerWidth - w - 8);
  let y = r.bottom + 6;
  if (y + hh > window.innerHeight - 8) y = r.top - hh - 6;
  pop.style.left = Math.max(8, x) + 'px';
  pop.style.top = y + 'px';
  $$('.act-it:not([disabled])', pop).forEach((b) => b.addEventListener('click', () => actionCarte(b.dataset.a)));
}
function fermerActMenu() { $('#actPop')?.classList.remove('open'); actKey = null; }

async function annulerRdv(p) {
  const body = new URLSearchParams({ _token: data.tokens.rdv });
  try {
    const r = await fetch(url('cancelRdv', p.appointmentId), { method: 'POST', body, headers: { Accept: 'application/json' } });
    const json = await r.json().catch(() => ({}));
    if (!r.ok || !json.success) {
      showToast(json.errors?.global?.[0] || 'Le rendez-vous n\'a pas pu être annulé.', 'danger');
      return;
    }
  } catch {
    showToast('Le rendez-vous n\'a pas pu être annulé.', 'danger');
    return;
  }
  card(p.key)?.classList.add('sortie');
  showToast(`Rendez-vous annulé — ${p.name} · le créneau de ${p.slotLabel} est libéré`);
  setTimeout(() => go(window.location.pathname), 900);
}

function actionCarte(a) {
  const p = patient(actKey);
  const e = entry(actKey);
  fermerActMenu();
  if (!p) return;
  if (a === 'avancer') avancer(p.key);
  if (a === 'detail') ouvrirPeek(p.key);
  if (a === 'consult' && p.consultationId) go(url('consultation', p.consultationId));
  if (a === 'motif') ouvrirMotif(p);
  if (a === 'ident') ouvrirIdent(p);
  if (a === 'modifrdv') go(data.urls.agenda);
  if (a === 'annulerdv') annulerRdv(p);
  if (a === 'prioritaire' || a === 'urgence') {
    post(url('triage', p.admissionId), { _token: e.t.triage, triage_level: a === 'urgence' ? 'emergency' : 'priority' });
  }
  if (a === 'annuler') {
    leave(p.key, () => post(url('close', p.admissionId), { _token: e.t.close, closure_reason: 'administrative_error' }));
  }
  if (a === 'retour') {
    post(url('move', p.admissionId), {
      _token: data.tokens.flux,
      to: p.column === 'sortie' ? 'in_consultation_room' : 'in_waiting_room',
      name: p.name,
    });
  }
}

// ═════════ Patient peek — popover anchored to the card ═════════

function fermerPeek() { $('#peek')?.classList.remove('open'); }

function ouvrirPeek(key) {
  const p = patient(key);
  const peek = $('#peek');
  const el = card(key);
  if (!p || !peek || !el) return;
  const colDef = COLS[p.column];
  const rows = [
    ['Motif', p.motif],
    ['Propriétaire', p.owner || '—'],
    ['Téléphone', p.phone || '—', 'mono'],
    ...(p.column === 'planifie'
      ? [['Rendez-vous', p.slotLabel, 'mono']]
      : [['Arrivée', p.sinceLabel, 'mono'], ['Attente ici', fmtChrono(p.sinceEpoch), 'mono']]),
    ['Praticien', p.practitioner || 'Non assigné'],
    ...(p.amountLabel ? [['À encaisser', p.amountLabel, 'mono']] : []),
  ];
  const FOOTS = {
    planifie: [['avancer', 'Enregistrer l\'arrivée', 'primaire'], ['modifrdv', 'Modifier le RDV', '']],
    attente: [['avancer', 'Prendre en charge', 'primaire'], ['profil', 'Profil complet', '']],
    charge: [['consult', 'Ouvrir la consultation', 'primaire', !p.consultationId && 'Aucune consultation ouverte'], ['avancer', 'Passer en sortie', '']],
    sortie: [['avancer', p.amountLabel ? 'Encaisser ' + p.amountLabel : 'Encaisser', 'primaire'], ['facture', 'Imprimer la facture', '', 'La facture est émise à l\'encaissement']],
  };
  const foot = [...FOOTS[p.column]];
  // An unknown patient: the second button identifies it.
  if (p.isUnidentified) foot[1] = ['identifier', 'Identifier', ''];
  peek.innerHTML = `
    <div class="pk-head">
      <span class="oesp">${esc(p.emoji)}</span>
      <span class="pk-id"><b>${esc(p.name)}</b><span class="sous">${esc(p.breedAndAge)}${p.breedAndAge && p.owner ? ' · ' : ''}${esc(p.owner)}</span></span>
      <button class="pk-close" type="button" aria-label="Fermer"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="pk-badges">
      ${p.isUnidentified ? `<span class="puce aid" style="cursor:default">${SVG.ident}À identifier</span>` : ''}
      ${p.isEmergency ? '<span class="puce urgp">Urgence</span>' : ''}
      ${p.isPriority && !p.isEmergency ? '<span class="puce rappel">Prioritaire</span>' : ''}
      <span class="puce motif">${esc(colDef.titre)}</span>
      ${p.channel && p.column !== 'planifie' ? `<span class="puce">${esc(p.channel)}</span>` : ''}
      ${[...p.alerts, ...p.arrivalChips].map((v) => `<span class="puce vig">${esc(v)}</span>`).join('')}
    </div>
    <div class="pk-rows">${rows.map(([l, v, m]) => `<div class="pk-row"><span class="l">${esc(l)}</span><span class="v${m ? ' ' + m : ''}">${esc(v)}</span></div>`).join('')}</div>
    <div class="pk-foot">${foot.map(([a2, l2, c2, off]) => `<button type="button" class="btn ${c2}" data-pk="${a2}"${off ? ` disabled data-tip="${esc(off)}"` : ''}>${esc(l2)}</button>`).join('')}</div>`;
  $('.pk-close', peek).addEventListener('click', fermerPeek);
  $$('[data-pk]:not([disabled])', peek).forEach((b2) => b2.addEventListener('click', () => {
    const a2 = b2.dataset.pk;
    fermerPeek();
    if (a2 === 'avancer') avancer(p.key);
    if (a2 === 'consult' && p.consultationId) go(url('consultation', p.consultationId));
    if (a2 === 'profil' && p.animalId) go(url('animal', p.animalId));
    if (a2 === 'identifier') ouvrirIdent(p);
    if (a2 === 'modifrdv') go(data.urls.agenda);
  }));
  // Anchoring: right of the card, else left, clamped to the screen.
  peek.classList.add('open');
  const W = peek.offsetWidth, H = peek.offsetHeight, r = el.getBoundingClientRect();
  let x = r.right + 10;
  if (x + W > window.innerWidth - 8) x = r.left - W - 10;
  if (x < 8) x = Math.min(Math.max(8, r.left), window.innerWidth - W - 8);
  const y = Math.min(Math.max(8, r.top), window.innerHeight - H - 8);
  peek.style.left = x + 'px';
  peek.style.top = y + 'px';
}

// ═════════ Keyboard ═════════

function onKeydown(e) {
  if (!$('.flx')) return;
  if (e.key === 'Escape') {
    if ($('.flx .dd.open')) { $$('.flx .dd.open').forEach((d) => d.classList.remove('open')); return; }
    if ($('#actPop')?.classList.contains('open')) return fermerActMenu();
    if ($('#peek')?.classList.contains('open')) return fermerPeek();
    if ($('#veilI')?.classList.contains('open')) return fermerIdent();
    if ($('#veilA')?.classList.contains('open')) return fermerArriveeRdv();
    if ($('#motifVeil')?.classList.contains('open')) return fermerMotif();
    if ($('#urgVeil')?.classList.contains('open')) return fermerUrgence();
    if ($('#ciVeil')?.classList.contains('open')) return fermerCi();
  }
  if (e.metaKey || e.ctrlKey || e.altKey) return;
  if (e.target instanceof Element && e.target.matches('input,select,textarea')) return;
  if (document.querySelector('.veil.open,.mveil.open,.racc-veil.open')) return;
  const k = e.key.toLowerCase();
  // U and A open this page's own modals; the shell's A would only reload it.
  if (k === 'u' || k === 'a') {
    e.preventDefault();
    e.stopImmediatePropagation();
    if (k === 'u') ouvrirUrgence(); else ouvrirArrivee();
  }
}

// ═════════ Init ═════════

export function init() {
  const root = $('.flx');
  const raw = $('#fluxData');
  if (!root || !raw) return;

  data = JSON.parse(raw.textContent);
  offset = data.now - Date.now() / 1000;
  bannerKeys = '';

  ac = new AbortController();
  const { signal } = ac;

  bindArrivee(signal);
  bindArriveeRdv(signal);
  bindUrgence(signal);
  bindMotif(signal);
  bindIdent(signal);
  bindPassages(signal);

  const board = $('#board');
  board.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    const aid = t.closest('.puce.aid');
    if (aid) { e.stopPropagation(); ouvrirIdent(patient(aid.dataset.aid)); return; }
    const av = t.closest('.avancer');
    if (av) { e.stopPropagation(); avancer(av.dataset.key); return; }
    const pm = t.closest('.pmenu');
    if (pm) { e.stopPropagation(); ouvrirActMenu(pm, pm.dataset.key); return; }
    if (t.closest('button,a')) return;
    const c = t.closest('.pat');
    if (c) ouvrirPeek(c.dataset.key);
  }, { signal });

  $('#urgAlert').addEventListener('click', (e) => {
    const b = e.target instanceof Element ? e.target.closest('.ua-btn') : null;
    if (b) avancer(b.dataset.key);
  }, { signal });

  document.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    if (!t.closest('.dd')) $$('.flx .dd.open').forEach((d) => d.classList.remove('open'));
    if (!t.closest('.act-pop') && !t.closest('.pmenu')) fermerActMenu();
    if (!t.closest('.peek') && !t.closest('.pat') && !t.closest('.act-pop')) fermerPeek();
    if (!t.closest('.ci-tagwrap')) $$('.flx .ci-menu.open').forEach((m) => m.classList.remove('open'));
  }, { signal });
  window.addEventListener('scroll', () => { fermerActMenu(); fermerPeek(); }, { capture: true, signal });
  window.addEventListener('keydown', onKeydown, { capture: true, signal });

  tick();
  majStats();
  majRetards();
  ticker = setInterval(tick, 1000);

  // « Arrivée » from the dashboard or the A key elsewhere opens the modal;
  // « Nouvelle consultation » on an animal sheet also names the patient to search.
  const params = new URLSearchParams(window.location.search);
  if (params.has('arrivee')) {
    const nom = params.get('patient');
    params.delete('arrivee');
    params.delete('patient');
    const q = params.toString();
    window.history.replaceState(window.history.state, '', window.location.pathname + (q ? '?' + q : ''));
    ouvrirArrivee();
    if (nom) {
      const input = $('#ciSearch');
      input.value = nom;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearInterval(ticker);
  ticker = null;
  urg.reader?.stop();
  id?.reader?.stop();
  data = null;
  ar = null;
  id = null;
}
