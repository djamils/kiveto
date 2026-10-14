/**
 * Page module — Ambulatory flow board (flux-ambulatoire-v-topbar-busy_6 mock-up).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * The board is rendered by the server; this module brings it to life the way
 * the mock-up does:
 * - live clock in the clinic's time zone, waiting chronos, day figures;
 * - banner of the emergencies nobody has taken for more than 5 minutes;
 * - one-click step on each card (« Enregistrer l'arrivée », « Prendre en
 *   charge », « Passer en sortie », « Encaisser »), the card action menu and
 *   the patient peek;
 * - « Enregistrer une arrivée » (search first, creation follows) and
 *   « Admission d'urgence » (the motif first, identity can wait) modals, plus
 *   « Modifier le motif » and « Identifier le patient »;
 * - keys: U emergency, A arrival, Esc closes.
 *
 * Every action posts to its existing endpoint (admission, consultation,
 * scheduling); the server redirects back here with a flash shown as a toast.
 * Listeners are bound in init() and released in cleanup().
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
  loupe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
  eclair: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
  x12: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
};
const BIENTOT = 'Bientôt disponible';

// ═════════ Time ═════════

const nowSec = () => Math.floor(Date.now() / 1000 + offset);

function clockLabel(withSeconds) {
  const opts = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
  if (withSeconds) opts.second = '2-digit';
  try {
    return new Intl.DateTimeFormat('fr-FR', { ...opts, timeZone: data.tz }).format(new Date(nowSec() * 1000));
  } catch {
    return new Intl.DateTimeFormat('fr-FR', opts).format(new Date(nowSec() * 1000));
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

/** Posts a hidden form — every action is a regular POST answered by a redirect + flash. */
function post(action, fields) {
  const form = document.createElement('form');
  form.method = 'post';
  form.action = action;
  form.dataset.turbo = 'false';
  form.hidden = true;
  Object.entries(fields).forEach(([k, v]) => {
    if (v === null || v === undefined) return;
    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = k;
    i.value = String(v);
    form.appendChild(i);
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

function checkInFromPlanned(p) {
  post(data.urls.checkin, {
    _token: data.tokens.checkin,
    appointmentId: p.appointmentId,
    animalId: p.animalId,
    animalName: p.name,
  });
}

function startConsultation(p) {
  post(url('start', p.admissionId), { _token: data.tokens.start });
}

function avancer(key) {
  const p = patient(key);
  if (!p) return;

  if (p.column === 'planifie') return leave(key, () => checkInFromPlanned(p));
  if (p.column === 'attente') return leave(key, () => startConsultation(p));
  if (p.column === 'charge') {
    return leave(key, () => post(url('toCheckout', p.admissionId), {
      _token: data.tokens.flux, consultationId: p.consultationId, name: p.name,
    }));
  }

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

// ═════════ Clock, chronos, figures ═════════

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

function tick() {
  const h = $('#horloge');
  if (h) h.textContent = clockLabel(true);
  $$('.flx .chrono[data-dep]').forEach((c) => {
    c.textContent = fmtChrono(+c.dataset.dep);
    c.classList.toggle('long', (nowSec() - +c.dataset.dep) > LONG_WAIT);
  });
  majBanner();
  if (nowSec() % 30 === 0) majStats();
}

// ═════════ Reusable bits of the modals ═════════

function chipsHtml(motifs, rouge) {
  return motifs.map((m) => `<button type="button" class="mchip${rouge ? ' rouge' : ''}" data-m="${esc(m)}">${esc(m)}</button>`).join('');
}

function brancherChips(cont, input, signal) {
  $$('.mchip', cont).forEach((c) => c.addEventListener('click', () => {
    const deja = c.classList.contains('on');
    $$('.mchip', cont).forEach((x) => x.classList.remove('on'));
    input.value = deja ? '' : c.dataset.m;
    if (!deja) c.classList.add('on');
    input.dispatchEvent(new Event('input'));
  }, { signal }));
  input.addEventListener('input', () => {
    $$('.mchip', cont).forEach((x) => x.classList.toggle('on', x.dataset.m === input.value));
  }, { signal });
}

/** The mock-up's custom dropdown (the species list carries an emoji per option). */
function creerDropdown(cont, options, defaut) {
  let val = defaut;
  const CHEV = '<svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
  const OK = '<svg class="ok" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>';
  function rendreDd() {
    const cur = options.find((o) => o.v === val);
    cont.innerHTML = `<button type="button" class="dd-btn" aria-haspopup="listbox">
        <span>${esc(cur.l)}</span>${CHEV}
      </button>
      <div class="dd-menu" role="listbox">${options.map((o) =>
        `<button type="button" class="dd-it${o.v === val ? ' on' : ''}" data-v="${o.v}" role="option">
           <span class="ic">${o.v}</span>${esc(o.l)}${o.v === val ? OK : ''}
         </button>`).join('')}</div>`;
    $('.dd-btn', cont).addEventListener('click', (e) => { e.stopPropagation(); cont.classList.toggle('open'); });
    $$('.dd-it', cont).forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      val = b.dataset.v; cont.classList.remove('open'); rendreDd();
    }));
  }
  rendreDd();
  return {
    get: () => options.find((o) => o.v === val),
    set: (v) => { val = v; cont.classList.remove('open'); rendreDd(); },
  };
}

/**
 * Live patient search of a picker: fetches the flux-format fragment of the
 * clinic search and hands the hits to `render`.
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
      let html = '';
      try {
        const r = await fetch(data.urls.search + '&q=' + encodeURIComponent(q), { signal: ctrl.signal, headers: { Accept: 'text/html' } });
        html = r.ok ? await r.text() : '';
      } catch (err) {
        if (err.name === 'AbortError') return;
      }
      if (input.value.trim() !== q) return;
      render(q, html);
      menu.classList.add('open');
    }, 180);
  }, { signal });
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

// ═════════ Enregistrer une arrivée — search first, creation follows ═════════

const ci = { mode: 'recherche', sel: null, prio: 0, rdv: 'sans', dd: null };

function ouvrirArrivee() {
  ci.mode = 'recherche'; ci.sel = null; ci.prio = 0; ci.rdv = 'sans';
  ['ciSearch', 'ciNewAnimal', 'ciNewProprio', 'ciNewTel', 'ciMotif'].forEach((id) => { $('#' + id).value = ''; });
  ci.dd.set('🐕');
  $('#ciMenu').classList.remove('open');
  $$('#ciChips .mchip').forEach((x) => x.classList.remove('on'));
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
  $('#ciValider').disabled = !ciFormValide();
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
  $('#ciChips').innerHTML = chipsHtml(CI_MOTIFS);
  brancherChips($('#ciChips'), $('#ciMotif'), signal);

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

// ═════════ Urgence — one screen: the motif first, identity can wait ═════════

const urg = { sel: null, dd: null };

function ouvrirUrgence() {
  urg.sel = null;
  ['urgSearch', 'urgMotif'].forEach((id) => { $('#' + id).value = ''; });
  $('#urgMenu').classList.remove('open');
  $$('#urgChips .mchip').forEach((x) => x.classList.remove('on'));
  urg.dd.set('🐾');
  urgMajIdent(); urgMajCta();
  $('#urgVeil').classList.add('open');
  setTimeout(() => $('#urgMotif').focus(), 30);
}
function fermerUrgence() { $('#urgVeil')?.classList.remove('open'); }
function urgMajIdent() {
  $('#urgIdent').style.display = urg.sel ? 'none' : '';
  const fiche = $('#urgFiche');
  if (!urg.sel) { fiche.innerHTML = ''; return; }
  fiche.innerHTML = ficheHtml(urg.sel, 'Retirer l\'identification');
  $('.fx', fiche).addEventListener('click', () => { urg.sel = null; urgMajIdent(); urgMajCta(); });
}
function urgMajCta() {
  $('#urgEnregistrer').disabled = !$('#urgMotif').value.trim();
  $('#urgEnregistrerTxt').textContent = urg.sel ? `Admettre ${urg.sel.nom} en urgence` : 'Admettre en urgence';
}
function urgValider() {
  if ($('#urgEnregistrer').disabled) return;
  const espece = urg.dd.get();
  post(data.urls.emergency, {
    _token: data.tokens.emergency,
    intake_channel: 'emergency',
    triage_level: 'emergency',
    triage_notes: $('#urgMotif').value.trim(),
    known_animal_id: urg.sel ? urg.sel.id : null,
    animal_name: urg.sel ? urg.sel.nom : null,
    observed_species: !urg.sel && espece.v !== '🐾' ? espece.l : null,
    time_zone: data.tz,
  });
  $('#urgEnregistrer').disabled = true;
}

function bindUrgence(signal) {
  urg.dd = creerDropdown($('#ddUrgEspece'), ESPECES, '🐾');
  $('#urgChips').innerHTML = chipsHtml(URG_MOTIFS, true);
  brancherChips($('#urgChips'), $('#urgMotif'), signal);

  const veil = $('#urgVeil');
  $('#btnUrgence').addEventListener('click', ouvrirUrgence, { signal });
  $('#urgClose').addEventListener('click', fermerUrgence, { signal });
  $('#urgAnnuler').addEventListener('click', fermerUrgence, { signal });
  veil.addEventListener('click', (e) => { if (e.target === veil) fermerUrgence(); }, { signal });
  $('#urgMotif').addEventListener('input', urgMajCta, { signal });
  $('#urgEnregistrer').addEventListener('click', urgValider, { signal });

  const input = $('#urgSearch');
  const menu = $('#urgMenu');
  brancherRecherche(input, menu, (q, html) => {
    menu.innerHTML = html.trim() || '<div class="ci-nores">Aucune fiche — admettez sans identification, régularisation après.</div>';
    $$('.ci-opt', menu).forEach((b) => b.addEventListener('click', () => {
      urg.sel = hitFromOpt(b);
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

// ═════════ Modifier le motif · Identifier le patient ═════════

let motifKey = null;
let idState = { key: null, sel: null };

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

function ouvrirIdentifier(p) {
  idState = { key: p.key, sel: null };
  $('#idTitre').textContent = `Identifier le patient — ${p.name}`;
  $('#idSearch').value = '';
  $('#idMenu').classList.remove('open');
  idMajFiche();
  $('#idVeil').classList.add('open');
  setTimeout(() => $('#idSearch').focus(), 30);
}
function fermerIdentifier() { $('#idVeil')?.classList.remove('open'); }
function idMajFiche() {
  const fiche = $('#idFiche');
  $('#idValider').disabled = !idState.sel;
  $('#idValiderTxt').textContent = idState.sel ? `Lier au dossier de ${idState.sel.nom}` : 'Lier au dossier';
  if (!idState.sel) {
    fiche.innerHTML = '<div class="ci-vide">Recherchez la fiche de l\'animal pour rattacher cette admission à son dossier.</div>';
    return;
  }
  fiche.innerHTML = ficheHtml(idState.sel, 'Retirer la sélection');
  $('.fx', fiche).addEventListener('click', () => { idState.sel = null; idMajFiche(); $('#idSearch').focus(); });
}
function idValider() {
  const p = patient(idState.key);
  if (!p || !idState.sel) return;
  post(data.urls.identify, {
    _token: data.tokens.identify,
    admissionId: p.admissionId,
    tab: 'owner',
    animalId: idState.sel.id,
    animalName: idState.sel.nom,
  });
}

function bindPetitesModales(signal) {
  const mv = $('#motifVeil');
  $('#motifClose').addEventListener('click', fermerMotif, { signal });
  $('#motifAnnuler').addEventListener('click', fermerMotif, { signal });
  $('#motifValider').addEventListener('click', motifValider, { signal });
  mv.addEventListener('click', (e) => { if (e.target === mv) fermerMotif(); }, { signal });
  $('#motifInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); motifValider(); } }, { signal });

  const iv = $('#idVeil');
  $('#idClose').addEventListener('click', fermerIdentifier, { signal });
  $('#idAnnuler').addEventListener('click', fermerIdentifier, { signal });
  $('#idValider').addEventListener('click', idValider, { signal });
  iv.addEventListener('click', (e) => { if (e.target === iv) fermerIdentifier(); }, { signal });
  const input = $('#idSearch');
  const menu = $('#idMenu');
  brancherRecherche(input, menu, (q, html) => {
    menu.innerHTML = html.trim() || '<div class="ci-nores">Aucune fiche trouvée.</div>';
    $$('.ci-opt', menu).forEach((b) => b.addEventListener('click', () => {
      idState.sel = hitFromOpt(b);
      menu.classList.remove('open');
      input.value = '';
      idMajFiche();
    }));
  }, signal);
}

// ═════════ Card action menu ═════════

let actKey = null;

function menuItems(p) {
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
      { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
      { a: 'motif', ico: 'crayon', l: 'Modifier le motif' },
      ...(p.isUnidentified ? [{ a: 'identifier', ico: 'loupe', l: 'Identifier le patient' }] : []),
      ...(p.triageLevel === 'standard' ? [{ a: 'prioritaire', ico: 'prio', l: 'Passer en prioritaire' }] : []),
      ...(!p.isEmergency ? [{ a: 'urgence', ico: 'alerte', l: 'Passer en urgence' }] : []),
      'sep',
      { a: 'annuler', ico: 'croix', l: 'Annuler l\'arrivée', cls: 'rouge' },
    ];
  }
  if (p.column === 'charge') {
    return [
      { a: 'consult', ico: 'steto', l: 'Ouvrir la consultation', off: !p.consultationId && 'Aucune consultation ouverte' },
      { a: 'avancer', ico: 'fleche', l: 'Passer en sortie' },
      { a: 'detail', ico: 'oeil', l: 'Voir le détail' },
      { a: 'reassigner', ico: 'pers', l: 'Réassigner le praticien', off: BIENTOT },
      'sep',
      { a: 'retour', ico: 'retour', l: 'Renvoyer en salle d\'attente' },
    ];
  }
  return [
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
  if (a === 'identifier') ouvrirIdentifier(p);
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
  const second = p.animalId
    ? ['profil', 'Profil complet', '']
    : ['identifier', 'Identifier', ''];
  const FOOTS = {
    planifie: [['avancer', 'Enregistrer l\'arrivée', 'primaire'], ['modifrdv', 'Modifier le RDV', '']],
    attente: [['avancer', 'Prendre en charge', 'primaire'], second],
    charge: [['consult', 'Ouvrir la consultation', 'primaire', !p.consultationId && 'Aucune consultation ouverte'], ['avancer', 'Passer en sortie', '']],
    sortie: [['avancer', p.amountLabel ? 'Encaisser ' + p.amountLabel : 'Encaisser', 'primaire'], ['facture', 'Imprimer la facture', '', 'La facture est émise à l\'encaissement']],
  };
  peek.innerHTML = `
    <div class="pk-head">
      <span class="oesp">${esc(p.emoji)}</span>
      <span class="pk-id"><b>${esc(p.name)}</b><span class="sous">${esc(p.breedAndAge)}${p.breedAndAge && p.owner ? ' · ' : ''}${esc(p.owner)}</span></span>
      <button class="pk-close" type="button" aria-label="Fermer"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="pk-badges">
      ${p.isEmergency ? '<span class="puce urgp">Urgence</span>' : ''}
      ${p.isPriority && !p.isEmergency ? '<span class="puce rappel">Prioritaire</span>' : ''}
      <span class="puce motif">${esc(colDef.titre)}</span>
      ${p.channel && p.column !== 'planifie' ? `<span class="puce">${esc(p.channel)}</span>` : ''}
      ${p.alerts.map((v) => `<span class="puce vig">${esc(v)}</span>`).join('')}
    </div>
    <div class="pk-rows">${rows.map(([l, v, m]) => `<div class="pk-row"><span class="l">${esc(l)}</span><span class="v${m ? ' ' + m : ''}">${esc(v)}</span></div>`).join('')}</div>
    <div class="pk-foot">${FOOTS[p.column].map(([a2, l2, c2, off]) => `<button type="button" class="btn ${c2}" data-pk="${a2}"${off ? ` disabled data-tip="${esc(off)}"` : ''}>${esc(l2)}</button>`).join('')}</div>`;
  $('.pk-close', peek).addEventListener('click', fermerPeek);
  $$('[data-pk]:not([disabled])', peek).forEach((b2) => b2.addEventListener('click', () => {
    const a2 = b2.dataset.pk;
    fermerPeek();
    if (a2 === 'avancer') avancer(p.key);
    if (a2 === 'consult' && p.consultationId) go(url('consultation', p.consultationId));
    if (a2 === 'profil') go(url('animal', p.animalId));
    if (a2 === 'identifier') ouvrirIdentifier(p);
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
    if ($('#idVeil')?.classList.contains('open')) return fermerIdentifier();
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
  bindUrgence(signal);
  bindPetitesModales(signal);

  const board = $('#board');
  board.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    const av = t.closest('.avancer');
    if (av) { e.stopPropagation(); avancer(av.dataset.key); return; }
    const pm = t.closest('.pmenu');
    if (pm) { e.stopPropagation(); ouvrirActMenu(pm, pm.dataset.key); return; }
    if (t.closest('button')) return;
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
  ticker = setInterval(tick, 1000);

  // « Arrivée » from the dashboard or the A key elsewhere opens the modal;
  // « Nouvelle consultation » on an animal sheet also names the patient to search.
  const params = new URLSearchParams(window.location.search);
  if (params.has('arrivee')) {
    const patient = params.get('patient');
    params.delete('arrivee');
    params.delete('patient');
    const q = params.toString();
    window.history.replaceState(window.history.state, '', window.location.pathname + (q ? '?' + q : ''));
    ouvrirArrivee();
    if (patient) {
      const input = $('#ciSearch');
      input.value = patient;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearInterval(ticker);
  ticker = null;
  data = null;
}
