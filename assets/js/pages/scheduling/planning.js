/**
 * Page module — Planning (planning-v-topbar_5 mock-up).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * Ports the mock-up's planning script onto the server's data:
 * - day view (one column per practitioner), week view (one lane per
 *   practitioner in each day), month view (one bar per practitioner per day),
 *   now-line, week-end shading, leave and lunch-break looks;
 * - aside: mini-calendar (dots on planned days, week highlight),
 *   practitioner and activity-type filters with their hours over the period;
 * - hover ghost, click or drag on the grid, « Ajouter un bloc » or B → block
 *   modal (types, practitioner, date / time pickers, recurrence, note,
 *   overlap and time-range checks); click a block → same modal in edit mode
 *   with « Supprimer »; every change is undoable from its toast for 5 s;
 * - keys: T today, B new block, ← → period, Esc closes.
 *
 * Data: the payload of PlanningViewBuilder::page() (#planning-data) covers
 * the 6-week grid of the opening month; other periods are fetched from the
 * range endpoint as the user browses. A recurring block is one series on the
 * server: editing or deleting one of its occurrences acts on the whole
 * series, its recurrence and anchor day stay as created. Online booking and
 * accepted motifs have no backend yet: shown, disabled. Listeners are bound
 * in init() and released in cleanup().
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

let ac = null;
let D = null;
let ticker = null;

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const url = (name, id) => D.urls[name].replace('__ID__', encodeURIComponent(id));

/* ═════════ Time helpers ═════════ */
const hm = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
const toMin = (s) => { const [a, b] = s.split(':').map(Number); return a * 60 + (b || 0); };
const JOURS_L = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const JOURS_C = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parse = (v) => { const [y, m, d] = v.split('-').map(Number); return new Date(y, m - 1, d); };
const addD = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const lundiDe = (d) => addD(d, -((d.getDay() + 6) % 7));
const memeJour = (a, b) => iso(a) === iso(b);
const fmtLong = (d) => `${JOURS_L[d.getDay()]} ${d.getDate()} ${MOIS[d.getMonth()]}`;
const dureeTxt = (d) => (d < 60 ? d + ' min' : Math.floor(d / 60) + ' h' + (d % 60 ? ' ' + String(d % 60).padStart(2, '0') : ''));

let AUJ = null;
let t0 = 0;
/** Minutes since midnight in the clinic, advancing with the page's clock. */
const nowMin = () => D.nowMin + Math.floor((Date.now() - t0) / 60000);

/* ═════════ Reference data ═════════ */
/* Mock-up keys (k), domain types (v) and the capacity per hour a new block gets */
const TYPES = [
  { k: 'consult', l: 'Consultation', c: '#4338ca', rdv: true, v: 'consultation', cap: 4 },
  { k: 'chir', l: 'Chirurgie', c: '#b45309', rdv: true, v: 'surgery', cap: 1 },
  { k: 'garde', l: 'Garde / urgences', c: '#dc2626', rdv: true, v: 'on_call', cap: 2 },
  { k: 'bilan', l: 'Bilans / labo', c: '#059669', rdv: true, v: 'health_check', cap: 3 },
  { k: 'admin', l: 'Administratif', c: '#64748b', rdv: false, v: 'admin', cap: 0 },
  { k: 'formation', l: 'Formation', c: '#0891b2', rdv: false, v: 'training', cap: 0 },
  { k: 'conge', l: 'Congé', c: '#94a3b8', rdv: false, v: 'leave', cap: 0 },
  { k: 'pause', l: 'Pause déjeuner', c: '#cbd5e1', rdv: false, v: 'break', cap: 0 },
];
const TYP = (k) => TYPES.find((t) => t.k === k) || TYPES[0];
/* Recurrence: mock-up value ↔ server frequency */
const REC = [
  { v: 'non', l: 'Aucune', f: 'NONE' },
  { v: 'jour', l: 'Chaque jour', f: 'DAILY' },
  { v: 'hebdo', l: 'Chaque semaine', f: 'WEEKLY' },
  { v: 'ouvres', l: 'Jours ouvrés', f: 'WEEKDAYS' },
];
const recDe = (f) => (REC.find((r) => r.f === f) || REC[0]).v;
const freqDe = (v) => (REC.find((r) => r.v === v) || REC[0]).f;
const MOTIFS_RDV = ['Consultation', 'Vaccin', 'Suivi', 'Bilan', 'Urgence', 'Chirurgie'];

let VETS = [];
const VET = (id) => VETS.find((v) => v.id === id) || { id, nom: 'Praticien', court: 'Praticien', ini: '?', c: '#64748b' };

const I_LOCK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
const I_W = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>';
/* No online booking yet: every bookable block is booked by the team, for any motif */
const RESA_TXT = 'Interne · tous motifs';

/* ═════════ Store: block occurrences of the loaded days ═════════ */
let BLOCS = [];
const loaded = new Set();
const pending = new Map();
const cle = (b) => `${b.id}@${b.date}`;

function merge(range) {
  const days = [];
  for (let d = parse(range.from); d <= parse(range.to); d = addD(d, 1)) days.push(iso(d));
  const set = new Set(days);
  BLOCS = BLOCS.filter((b) => !set.has(b.date)).concat(range.blocks);
  days.forEach((k) => loaded.add(k));
}

async function fetchRange(from, to) {
  const key = iso(from) + '|' + iso(to);
  if (pending.has(key)) return pending.get(key);
  const p = (async () => {
    const res = await fetch(`${D.urls.range}?from=${iso(from)}&to=${iso(to)}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('range ' + res.status);
    const range = await res.json();
    if (D) merge(range);
  })().finally(() => pending.delete(key));
  pending.set(key, p);
  return p;
}

/** Loads the 6-week grid around `d` unless every day of [from, to] is known. */
async function ensure(from, to) {
  for (let d = new Date(from); d <= to; d = addD(d, 1)) {
    if (!loaded.has(iso(d))) {
      const g = lundiDe(new Date(d.getFullYear(), d.getMonth(), 1));
      const end = addD(g, 41) < to ? to : addD(g, 41);
      await fetchRange(g < from ? g : from, end);
      return true;
    }
  }
  return false;
}

/** A series spans many days: after a change, everything shown is fetched again. */
async function recharger() {
  BLOCS = []; loaded.clear();
  try {
    const g = lundiDe(new Date(moisVu.getFullYear(), moisVu.getMonth(), 1));
    const [a, b] = periodeVue();
    await fetchRange(a < g ? a : g, b > addD(g, 41) ? b : addD(g, 41));
  } catch {
    showToast('Planning non actualisé — rechargez la page', 'danger');
  }
  if (D) rendre();
}

/* ═════════ View state ═════════ */
let START = 7 * 60, END = 20 * 60;
const HH = 46, PX = HH / 60;
let vue = 'day', cur = null, moisVu = null;
let visibles = new Set();
let typesOn = new Set();
let calHead, calInner, calBody, blkVeilEl;

const visible = (b) => visibles.has(b.vet) && typesOn.has(b.t);
const blocsJour = (d, vet) => { const k = iso(d); return BLOCS.filter((b) => b.date === k && (!vet || b.vet === vet) && visible(b)).sort((a, b) => a.s - b.s); };

function periodeVue() {
  if (vue === 'day') return [new Date(cur), new Date(cur)];
  if (vue === 'week') return [lundiDe(cur), addD(lundiDe(cur), 6)];
  const g = lundiDe(new Date(cur.getFullYear(), cur.getMonth(), 1));
  return [g, addD(g, 41)];
}

function blkHtml(b, pos, lane) {
  const t = TYP(b.t), v = VET(b.vet), h = (b.e - b.s) * PX;
  const style = `${pos};--c:${t.c};--cbg:${t.c}2b`;
  if (b.t === 'pause') return `<div class="blk pause" data-k="${esc(cle(b))}" style="${pos}" data-tip="${esc(v.nom)} · Pause déjeuner ${hm(b.s)}–${hm(b.e)}">${!lane && h > 16 ? `<span>Pause déjeuner · ${hm(b.s)}–${hm(b.e)}</span>` : ''}</div>`;
  if (lane) {
    return `<div class="blk lane${b.t === 'conge' ? ' conge' : ''}" data-k="${esc(cle(b))}" style="${style}" data-tip="${esc(v.nom)} · ${t.l} ${hm(b.s)}–${hm(b.e)}${t.rdv ? ' · ' + RESA_TXT : ''}">
      <span class="ini">${esc(v.ini)}</span>${h > 34 ? `<span class="bh">${hm(b.s)}</span>` : ''}${h > 52 ? `<span class="bh">${hm(b.e)}</span>` : ''}</div>`;
  }
  return `<div class="blk${b.t === 'conge' ? ' conge' : ''}" data-k="${esc(cle(b))}" style="${style}">
    <span class="bt"><i style="width:7px;height:7px;border-radius:2px;background:${t.c};flex:none"></i>${t.l}</span>
    <span class="bh">${hm(b.s)} – ${hm(b.e)}</span>
    ${t.rdv && h > 60 ? `<span class="bc">${I_LOCK}${RESA_TXT}</span>` : ''}
    ${b.note && h > 78 ? `<span class="bn">${esc(b.note)}</span>` : ''}
  </div>`;
}

function rendre() {
  if (!D) return;
  const [a, b] = periodeVue();
  const g = lundiDe(new Date(moisVu.getFullYear(), moisVu.getMonth(), 1));
  ensure(a < g ? a : g, b > addD(g, 41) ? b : addD(g, 41))
    .then((fetched) => { if (fetched && D) rendre(); })
    .catch(() => showToast('Impossible de charger cette période', 'danger'));

  const isMois = vue === 'month';
  $('#calZone').style.display = isMois ? 'none' : '';
  $('#mGrid').style.display = isMois ? '' : 'none';
  if (isMois) rendreMois(); else rendreGrille();
  libelle();
  rendreAside();
  majUrl();
}

/* Grid hours: 07:00 – 20:00, widened to the blocks shown */
function bornes(cols) {
  let s = 7 * 60, e = 20 * 60;
  cols.forEach((c) => blocsJour(c.d, c.vet ? c.vet.id : null).forEach((b) => { s = Math.min(s, b.s); e = Math.max(e, b.e); }));
  START = Math.max(0, Math.floor(s / 60) * 60);
  END = Math.min(24 * 60, Math.ceil(e / 60) * 60);
}

function rendreGrille() {
  const vets = VETS.filter((v) => visibles.has(v.id));
  const cols = vue === 'day'
    ? vets.map((v) => ({ d: new Date(cur), vet: v }))
    : Array.from({ length: 7 }, (_, i) => ({ d: addD(lundiDe(cur), i) }));
  bornes(cols);
  const tpl = `56px repeat(${Math.max(1, cols.length)},minmax(0,1fr))`;
  calHead.style.gridTemplateColumns = tpl;
  calHead.innerHTML = '<div></div>' + (cols.length ? cols.map((c) => {
    if (vue === 'day') {
      const hrs = blocsJour(c.d, c.vet.id).filter((x) => TYP(x.t).rdv).reduce((s, x) => s + (x.e - x.s), 0) / 60;
      return `<div class="cal-hd vet"><span class="vav" style="background:${c.vet.c}22;color:${c.vet.c}">${esc(c.vet.ini)}</span><b>${esc(c.vet.nom)}</b><span class="cnt">${hrs ? (Math.round(hrs * 2) / 2).toString().replace('.', ',') + ' h' : 'off'}</span></div>`;
    }
    const n = blocsJour(c.d).filter((x) => x.t !== 'pause').length;
    return `<div class="cal-hd${memeJour(c.d, AUJ) ? ' auj' : ''}"><span class="dn">${JOURS_C[c.d.getDay()]}</span><span class="dnum">${c.d.getDate()}</span><span class="cnt">${n ? n + ' blocs' : '—'}</span></div>`;
  }).join('') : '<div class="cal-hd vet"><b style="color:var(--encre-35)">Aucun vétérinaire dans cette clinique</b></div>');
  calInner.style.gridTemplateColumns = tpl;
  calInner.style.setProperty('--hh', HH + 'px');
  const H = (END - START) * PX, nm = nowMin();
  let html = `<div class="gut" style="height:${H}px">` + Array.from({ length: (END - START) / 60 }, (_, i) => (i ? `<span style="top:${i * HH}px">${hm(START + i * 60)}</span>` : '')).join('') + '</div>';
  cols.forEach((c, ci) => {
    const we = c.d.getDay() === 0 || c.d.getDay() === 6;
    let inner = '';
    if (vue === 'day') {
      blocsJour(c.d, c.vet.id).forEach((b) => {
        inner += blkHtml(b, `top:${(b.s - START) * PX + 1}px;height:${(b.e - b.s) * PX - 2}px;left:3px;right:3px`);
      });
    } else {
      const n = vets.length;
      vets.forEach((v, i) => {
        if (i) inner += `<div class="lane-sep" style="left:${i * 100 / n}%"></div>`;
        blocsJour(c.d, v.id).forEach((b) => {
          inner += blkHtml(b, `top:${(b.s - START) * PX + 1}px;height:${(b.e - b.s) * PX - 2}px;left:calc(${i * 100 / n}% + 2px);width:calc(${100 / n}% - 4px)`, true);
        });
      });
    }
    if (memeJour(c.d, AUJ) && nm > START && nm < END) inner += `<div class="nowl" style="top:${(nm - START) * PX}px"></div>`;
    inner += '<div class="hover-slot"></div>';
    html += `<div class="ccol${we ? ' we' : ''}" data-ci="${ci}" style="height:${H}px">${inner}</div>`;
  });
  if (!cols.length) html += `<div class="ccol" style="height:${H}px;cursor:default"></div>`;
  calInner.innerHTML = html;
  calHead.style.paddingRight = (calBody.offsetWidth - calBody.clientWidth) + 'px';
  if (cols.length) brancherGrille(cols, vets);
}

function brancherGrille(cols, vets) {
  $$('.blk', calInner).forEach((el) => el.addEventListener('click', (e) => { e.stopPropagation(); ouvrirBloc({ edit: BLOCS.find((b) => cle(b) === el.dataset.k) }); }));
  $$('.ccol', calInner).forEach((colEl) => {
    const c = cols[+colEl.dataset.ci];
    const ghost = $('.hover-slot', colEl);
    const at = (e) => {
      const r = colEl.getBoundingClientRect();
      const m = Math.max(START, Math.min(Math.floor((START + (e.clientY - r.top) / PX) / 60) * 60, END - 60));
      const vet = c.vet ? c.vet.id : vets[Math.max(0, Math.min(vets.length - 1, Math.floor((e.clientX - r.left) / r.width * vets.length)))].id;
      const lane = c.vet ? 0 : vets.findIndex((v) => v.id === vet);
      return { m, vet, lane };
    };
    colEl.addEventListener('mousemove', (e) => {
      if (ghost.classList.contains('drag')) return;
      if (e.target.closest('.blk')) { ghost.style.display = 'none'; return; }
      const p = at(e), n = c.vet ? 1 : vets.length;
      ghost.style.display = 'block';
      ghost.style.top = (p.m - START) * PX + 1 + 'px';
      ghost.style.height = 60 * PX - 2 + 'px';
      ghost.style.left = `calc(${p.lane * 100 / n}% + 2px)`;
      ghost.style.right = 'auto';
      ghost.style.width = `calc(${100 / n}% - 4px)`;
      ghost.textContent = c.vet ? '+ ' + hm(p.m) : '+ ' + VET(p.vet).ini;
    });
    colEl.addEventListener('mouseleave', () => { ghost.style.display = 'none'; });
    colEl.addEventListener('mousedown', (e) => {
      if (e.button !== 0 || e.target.closest('.blk')) return;
      const p0 = at(e), n = c.vet ? 1 : vets.length;
      const snap = (ev) => Math.max(START, Math.min(END, Math.round((START + (ev.clientY - colEl.getBoundingClientRect().top) / PX) / 15) * 15));
      const m0 = Math.max(START, Math.min(END - 15, Math.floor((START + (e.clientY - colEl.getBoundingClientRect().top) / PX) / 15) * 15));
      let moved = false, s = m0, f = m0 + 15;
      e.preventDefault(); document.body.classList.add('dragging');
      const mv = (ev) => {
        const m1 = snap(ev);
        if (!moved && Math.abs(m1 - m0) < 15) return;
        moved = true;
        if (m1 >= m0) { s = m0; f = Math.max(m0 + 15, m1); } else { s = m1; f = m0 + 15; }
        ghost.classList.add('drag'); ghost.style.display = 'block';
        ghost.style.top = (s - START) * PX + 1 + 'px'; ghost.style.height = (f - s) * PX - 2 + 'px';
        ghost.style.left = `calc(${p0.lane * 100 / n}% + 2px)`; ghost.style.width = `calc(${100 / n}% - 4px)`;
        ghost.textContent = `${hm(s)} – ${hm(f)} · ${dureeTxt(f - s)}`;
      };
      const up = () => {
        document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up);
        document.body.classList.remove('dragging'); ghost.classList.remove('drag'); ghost.style.display = 'none';
        if (moved) ouvrirBloc({ date: c.d, vet: p0.vet, s, e: f });
        else ouvrirBloc({ date: c.d, vet: p0.vet, s: p0.m, e: Math.min(p0.m + 4 * 60, END) });
      };
      document.addEventListener('mousemove', mv); document.addEventListener('mouseup', up);
    });
  });
}

function rendreMois() {
  const g = $('#mGrid');
  const premier = new Date(cur.getFullYear(), cur.getMonth(), 1);
  const debut = lundiDe(premier);
  const ordre = (id) => VETS.findIndex((v) => v.id === id);
  let html = ['Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.', 'Dim.'].map((d) => `<div class="mdow">${d}</div>`).join('');
  const M0 = 7 * 60, M1 = 21 * 60, pc = (m) => ((Math.max(M0, Math.min(M1, m)) - M0) / (M1 - M0) * 100).toFixed(2) + '%';
  for (let i = 0; i < 42; i++) {
    const d = addD(debut, i), k = iso(d);
    const bl = BLOCS.filter((b) => b.date === k && visible(b)).sort((a, b) => ordre(a.vet) - ordre(b.vet) || a.s - b.s);
    const lignes = VETS.filter((v) => visibles.has(v.id)).map((v) => {
      const bv = bl.filter((b) => b.vet === v.id);
      return `<div class="mrow"><b>${esc(v.ini)}</b><span class="mbar">${bv.map((b) => { const t = TYP(b.t); return `<i class="mseg${b.t === 'conge' ? ' conge' : ''}" data-k="${esc(cle(b))}" style="left:${pc(b.s)};width:calc(${pc(b.e)} - ${pc(b.s)});--c:${t.c}" data-tip="${esc(`${v.nom} · ${t.l} ${hm(b.s)}–${hm(b.e)}${b.note ? ' · ' + b.note : ''}`)}"></i>`; }).join('')}</span></div>`;
    }).join('');
    html += `<div class="mcell${d.getMonth() !== cur.getMonth() ? ' hors' : ''}${memeJour(d, AUJ) ? ' auj' : ''}" data-d="${k}">
      <span class="mnum">${d.getDate()}</span>${lignes}
    </div>`;
  }
  g.style.gridTemplateRows = 'auto repeat(6,minmax(0,1fr))';
  g.innerHTML = html;
  $$('.mseg', g).forEach((p) => p.addEventListener('click', (e) => { e.stopPropagation(); ouvrirBloc({ edit: BLOCS.find((b) => cle(b) === p.dataset.k) }); }));
  $$('.mcell', g).forEach((c) => c.addEventListener('click', () => {
    cur = parse(c.dataset.d); vue = 'day';
    $$('#vueSeg button').forEach((x) => x.classList.toggle('on', x.dataset.v === 'day'));
    rendre();
  }));
}

function libelle() {
  const L = $('#schLabel');
  if (vue === 'day') L.textContent = `${fmtLong(cur)} ${cur.getFullYear()}`;
  else if (vue === 'week') {
    const l = lundiDe(cur), d7 = addD(l, 6);
    L.textContent = l.getMonth() === d7.getMonth() ? `${l.getDate()} – ${d7.getDate()} ${MOIS[d7.getMonth()]} ${d7.getFullYear()}` : `${l.getDate()} ${MOIS[l.getMonth()]} – ${d7.getDate()} ${MOIS[d7.getMonth()]} ${d7.getFullYear()}`;
  } else L.textContent = `${MOIS[cur.getMonth()].replace(/^./, (c) => c.toUpperCase())} ${cur.getFullYear()}`;
}

/* Keeps the period in the address so a reload (or a shared link) opens it */
function majUrl() {
  const u = new URL(window.location.href);
  u.searchParams.set('view', vue);
  u.searchParams.set('date', iso(cur));
  if (u.href !== window.location.href) window.history.replaceState(window.history.state, '', u.href);
}

/* ═════════ Mini-calendar ═════════ */
function rendreMiniCal(cont, sel, mois, opts) {
  const premier = new Date(mois.getFullYear(), mois.getMonth(), 1);
  const debut = lundiDe(premier);
  const lun = lundiDe(sel);
  let html = `<div class="mc-head">
      <button type="button" class="mc-nav" data-mc="-1" aria-label="Mois précédent"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
      <b>${MOIS[mois.getMonth()].replace(/^./, (c) => c.toUpperCase())} ${mois.getFullYear()}</b>
      <button type="button" class="mc-nav" data-mc="1" aria-label="Mois suivant"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
    </div><div class="mc-grid">` + ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((x) => `<span class="mc-dow">${x}</span>`).join('');
  for (let i = 0; i < 42; i++) {
    const d = addD(debut, i);
    const dansSem = opts.semaine && d >= lun && d < addD(lun, 7);
    const cls = ['mc-d',
      d.getMonth() !== mois.getMonth() ? 'hors' : '',
      dansSem ? 'wk' : '', dansSem && d.getDay() === 1 ? 'wk-l' : '', dansSem && d.getDay() === 0 ? 'wk-r' : '',
      memeJour(d, AUJ) ? 'today' : '', memeJour(d, sel) && !opts.semaine ? 'sel' : '',
      opts.has && opts.has(d) ? 'has' : ''].filter(Boolean).join(' ');
    html += `<div class="${cls}" data-d="${iso(d)}"><span>${d.getDate()}</span></div>`;
  }
  cont.innerHTML = html + '</div>';
  $$('[data-mc]', cont).forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); opts.onMois(+b.dataset.mc); }));
  $$('.mc-d', cont).forEach((c) => c.addEventListener('click', () => opts.onPick(parse(c.dataset.d))));
}

/* ═════════ Aside: mini-calendar, practitioners, types ═════════ */
function periode(b) {
  if (vue === 'day') return b.date === iso(cur);
  const d = parse(b.date);
  if (vue === 'week') { const l = lundiDe(cur); return d >= l && d < addD(l, 7); }
  return d.getMonth() === cur.getMonth() && d.getFullYear() === cur.getFullYear();
}
const heures = (list) => { const h = list.reduce((s, b) => s + (b.e - b.s), 0) / 60; return h ? (Math.round(h * 2) / 2).toString().replace('.', ',') + ' h' : '—'; };

function rendreAside() {
  const g = lundiDe(new Date(moisVu.getFullYear(), moisVu.getMonth(), 1));
  ensure(g, addD(g, 41)).then((fetched) => { if (fetched && D) rendreAside(); }).catch(() => {});
  const jours = new Set(BLOCS.filter((b) => b.t !== 'conge' && visibles.has(b.vet)).map((b) => b.date));
  rendreMiniCal($('#miniCal'), cur, moisVu, {
    semaine: vue === 'week',
    has: (d) => jours.has(iso(d)),
    onMois: (n) => { moisVu = new Date(moisVu.getFullYear(), moisVu.getMonth() + n, 1); rendreAside(); },
    onPick: (d) => { cur = d; moisVu = new Date(d.getFullYear(), d.getMonth(), 1); rendre(); },
  });
  $('#vetList').innerHTML = VETS.length ? VETS.map((v) =>
    `<button type="button" class="vchip${visibles.has(v.id) ? '' : ' off'}" data-v="${esc(v.id)}"><span class="vdot" style="background:${v.c};border-color:${v.c}"></span>${esc(v.court)}<span class="vn">${heures(BLOCS.filter((b) => b.vet === v.id && periode(b) && TYP(b.t).rdv))}</span></button>`).join('')
    : '<div class="vchip" style="cursor:default;color:var(--encre-35)">Aucun vétérinaire</div>';
  $$('#vetList .vchip[data-v]').forEach((b) => b.addEventListener('click', () => {
    const id = b.dataset.v;
    if (visibles.has(id)) { if (visibles.size === 1) { showToast('Au moins un vétérinaire doit rester affiché'); return; } visibles.delete(id); } else visibles.add(id);
    rendre();
  }));
  $('#typeList').innerHTML = TYPES.map((t) =>
    `<button type="button" class="vchip${typesOn.has(t.k) ? '' : ' off'}" data-t="${t.k}"><span class="vdot" style="background:${t.c};border-color:${t.c}"></span>${t.l}<span class="vn">${heures(BLOCS.filter((b) => b.t === t.k && periode(b) && visibles.has(b.vet)))}</span></button>`).join('');
  $$('#typeList .vchip').forEach((b) => b.addEventListener('click', () => {
    const k = b.dataset.t;
    if (typesOn.has(k)) { if (typesOn.size === 1) return; typesOn.delete(k); } else typesOn.add(k);
    rendre();
  }));
}

/* ═════════ Toolbar ═════════ */
function decaler(n) {
  if (vue === 'month') cur = new Date(cur.getFullYear(), cur.getMonth() + n, 1);
  else cur = addD(cur, n * (vue === 'day' ? 1 : 7));
  moisVu = new Date(cur.getFullYear(), cur.getMonth(), 1);
  rendre();
}
const defautVet = () => (VETS.find((v) => v.id === D.me && visibles.has(v.id)) || VETS.find((v) => visibles.has(v.id)) || VETS[0] || { id: '' }).id;

/* First gap of 2 h or more in the practitioner's day (Sundays skipped), from `d0` */
function premierTrou(vet, d0) {
  for (let i = 0; i < 14; i++) {
    const d = addD(d0, i); if (d.getDay() === 0) continue;
    const k = iso(d);
    const bl = BLOCS.filter((b) => b.vet === vet && b.date === k).sort((a, b) => a.s - b.s);
    let t = 8 * 60;
    for (const b of bl.concat([{ s: 19 * 60, e: 19 * 60 }])) { if (b.s - t >= 120) return { date: d, vet, s: t, e: Math.min(t + 240, b.s) }; t = Math.max(t, b.e); }
  }
  return { date: d0, vet, s: 8 * 60, e: 12 * 60 };
}
async function ajouter() {
  if (!VETS.length) { showToast('Aucun vétérinaire dans cette clinique'); return; }
  const vet = defautVet();
  try { await ensure(cur, addD(cur, 13)); } catch { /* search what is known */ }
  if (D) ouvrirBloc(premierTrou(vet, cur));
}

/* ═════════ Server calls ═════════ */
const ERREURS = {
  CannotCreateOverlappingPlanningBlock: 'Chevauchement — ce vétérinaire a déjà un bloc sur ce créneau',
  CannotModifyRecurrenceRuleOnExistingBlock: 'La répétition d\'une série ne peut pas être modifiée',
  CannotChangePlanningBlockTypeWithActiveAppointments: 'Des rendez-vous sont pris sur ce bloc — son type ne peut pas changer',
  CannotShrinkPlanningBlockBelowExistingAppointments: 'Des rendez-vous sont pris sur ce bloc — ses horaires et son vétérinaire ne peuvent pas changer',
  CannotReduceCapacityBelowExistingAppointmentCount: 'Des rendez-vous sont pris sur ce bloc — sa capacité ne peut pas baisser',
  CannotDeletePlanningBlockWithAppointments: 'Des rendez-vous sont pris sur ce bloc — il ne peut pas être supprimé',
  InvalidPlanningBlockTimeRange: 'Horaires invalides — 15 minutes minimum, la fin après le début',
};
async function appel(method, target, payload) {
  let res, body = null;
  try {
    res = await fetch(target, {
      method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: payload ? JSON.stringify(payload) : undefined,
    });
    body = await res.json().catch(() => null);
  } catch {
    return { ok: false, msg: 'Serveur injoignable — réessayez' };
  }
  if (res.ok) return { ok: true, body };
  if (res.status === 404) return { ok: false, msg: 'Ce bloc n\'existe plus — planning actualisé', gone: true };
  return { ok: false, msg: ERREURS[body?.code] || 'Enregistrement impossible — réessayez' };
}
/* The server shape of a block (POST / PUT body) */
const corps = (b) => ({
  vet: b.vet, type: b.type, date: b.date, start: hm(b.s), end: hm(b.e),
  capacity: b.cap, recurrence: b.rec, recurrenceUntil: b.until, note: b.note || null,
});

/* ═════════ Date picker (calendar in a popover) ═════════ */
const I_CAL = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
const I_CLK = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
function creerDatePicker(cont, val, onPick, opts = {}) {
  cont.classList.add('dp');
  let curV = val, mv = null;
  const lbl = (v) => { if (!v) return '<span class="ph">Choisir une date</span>'; const d = parse(v); return `${JOURS_C[d.getDay()]} ${d.getDate()} ${MOIS[d.getMonth()]}${memeJour(d, AUJ) ? ' <em>aujourd\'hui</em>' : ''}`; };
  cont.innerHTML = `<button type="button" class="dd-btn dp-btn"><span class="dp-ic">${I_CAL}</span><span class="dp-l"></span></button><div class="dp-pop"><div class="dp-cal"></div><div class="dp-q"></div></div>`;
  const btn = $('.dp-btn', cont), popEl = $('.dp-pop', cont), cal = $('.dp-cal', cont);
  const maj = () => { $('.dp-l', cont).innerHTML = lbl(curV); };
  const fermer = () => cont.classList.remove('open');
  const choisir = (d) => { curV = iso(d); maj(); fermer(); if (onPick) onPick(curV); };
  const rendreCal = () => rendreMiniCal(cal, curV ? parse(curV) : AUJ, mv, { has: opts.has, onMois: (n) => { mv = new Date(mv.getFullYear(), mv.getMonth() + n, 1); rendreCal(); }, onPick: choisir });
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const was = cont.classList.contains('open');
    $$('.dd.open,.dp.open,.tp.open').forEach((x) => x.classList.remove('open'));
    if (was) return;
    const d = curV ? parse(curV) : AUJ; mv = new Date(d.getFullYear(), d.getMonth(), 1);
    rendreCal();
    const lundi = addD(lundiDe(AUJ), 7);
    $('.dp-q', cont).innerHTML = [['Aujourd\'hui', AUJ], ['Demain', addD(AUJ, 1)], ['Lundi prochain', lundi]].map(([l, d2]) => `<button type="button" data-q="${iso(d2)}">${l}</button>`).join('');
    $$('.dp-q button', cont).forEach((b) => b.addEventListener('click', (ev) => { ev.stopPropagation(); choisir(parse(b.dataset.q)); }));
    cont.classList.add('open');
  });
  popEl.addEventListener('click', (e) => e.stopPropagation());
  maj();
  return { get: () => curV, set: (v) => { curV = v; maj(); fermer(); } };
}

/* ═════════ Time picker (15-min slots, duration alongside) ═════════ */
function creerTimePicker(input, opts = {}) {
  const debut = opts.debut || 7 * 60, finJ = opts.fin || 20 * 60, pas = opts.pas || 15;
  const wrap = document.createElement('div'); wrap.className = 'tp';
  input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
  wrap.insertAdjacentHTML('afterbegin', `<span class="tp-ic">${I_CLK}</span>`);
  const popEl = document.createElement('div'); popEl.className = 'tp-pop scr'; wrap.appendChild(popEl);
  const ouvrir = () => {
    $$('.dd.open,.dp.open,.tp.open').forEach((x) => { if (x !== wrap) x.classList.remove('open'); });
    const ref = opts.ref ? opts.ref() : null, v = toMin(input.value.trim());
    let h = '';
    for (let m = debut; m <= finJ; m += pas) {
      if (ref != null && m <= ref) continue;
      const d = ref != null ? m - ref : null;
      h += `<button type="button" data-m="${m}" class="${m === v ? 'on' : ''}"><b>${hm(m)}</b>${d != null ? `<i>${dureeTxt(d)}</i>` : ''}</button>`;
    }
    popEl.innerHTML = h;
    $$('button', popEl).forEach((b) => b.addEventListener('mousedown', (e) => {
      e.preventDefault(); input.value = hm(+b.dataset.m); input.dispatchEvent(new Event('input')); wrap.classList.remove('open'); input.blur();
    }));
    wrap.classList.add('open');
    const on = $('.on', popEl) || $(`[data-m="${Math.round((Number.isNaN(v) ? 9 * 60 : v) / pas) * pas}"]`, popEl);
    if (on) popEl.scrollTop = on.offsetTop - 70;
  };
  input.addEventListener('focus', ouvrir);
  input.addEventListener('click', ouvrir);
  input.addEventListener('keydown', (e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === 'Tab') wrap.classList.remove('open'); });
  input.addEventListener('blur', () => setTimeout(() => wrap.classList.remove('open'), 120));
  return { ouvrir };
}

/* ═════════ Block modal ═════════ */
let P = null; // { orig, date, vet, s, e, t, note, rec }
let ddVetB, ddDate, ddRep, bS, bE;
const valide = (s) => /^([01]?\d|2[0-3]):([0-5]\d)$/.test(s.trim());
const serie = () => !!(P && P.orig && P.orig.rec !== 'NONE');

function bindModal(signal) {
  $('#typeGrid').innerHTML = TYPES.map((t) =>
    `<button type="button" class="typ" data-t="${t.k}" style="--c:${t.c};--cbg:${t.c}12"><i style="background:${t.c}"></i>${t.l}</button>`).join('');
  $$('.typ').forEach((b) => b.addEventListener('click', () => { P.t = b.dataset.t; majBloc(); }, { signal }));
  ddVetB = creerDropdown($('#ddVet'), VETS.map((v) => ({ v: v.id, l: v.nom, c: v.c })), defautVet(), (v) => { P.vet = v; majBloc(); });
  ddDate = creerDatePicker($('#ddDate'), '', (v) => { P.date = v; majBloc(); }, { has: (d) => { const k = iso(d); return BLOCS.some((b) => b.date === k && b.vet === P?.vet); } });
  ddRep = creerDropdown($('#ddRep'), REC.map((r) => ({ v: r.v, l: r.l })), 'non', (v) => { P.rec = v; majBloc(); });
  bS = $('#bS'); bE = $('#bE');
  // Online booking and motifs are not wired yet: the chips show what holds today (all motifs)
  $('#bMotifs').innerHTML = ['Tous', ...MOTIFS_RDV].map((m) => `<button type="button" tabindex="-1" class="${m === 'Tous' ? 'on' : ''}">${m}</button>`).join('');
  creerTimePicker(bS, { debut: 6 * 60, fin: 22 * 60 });
  creerTimePicker(bE, { debut: 6 * 60, fin: 22 * 60, ref: () => P?.s });
  [bS, bE].forEach((inp) => inp.addEventListener('input', () => {
    inp.style.borderColor = valide(inp.value) ? '' : 'var(--garance)';
    if (valide(bS.value)) P.s = toMin(bS.value.trim());
    if (valide(bE.value)) P.e = toMin(bE.value.trim());
    majBloc(true);
  }, { signal }));
  $('#blkX').addEventListener('click', fermerBloc, { signal });
  $('#blkAnnuler').addEventListener('click', fermerBloc, { signal });
  blkVeilEl.addEventListener('click', (e) => { if (e.target === blkVeilEl) fermerBloc(); }, { signal });
  $('#blkOk').addEventListener('click', enregistrer, { signal });
  $('#blkSuppr').addEventListener('click', supprimer, { signal });
}

function ouvrirBloc(o) {
  if (o.edit) {
    const b = o.edit;
    P = { orig: b, date: b.date, vet: b.vet, s: b.s, e: b.e, t: b.t, note: b.note || '', rec: recDe(b.rec) };
  } else if (o.date) {
    P = { orig: null, date: iso(o.date), vet: o.vet, s: o.s, e: o.e, t: 'consult', note: '', rec: 'non' };
  } else return;
  ddDate.set(P.date); ddVetB.set(P.vet); ddRep.set(P.rec);
  bS.value = hm(P.s); bE.value = hm(P.e); bS.style.borderColor = bE.style.borderColor = '';
  $('#bNote').value = P.note;
  $('#blkTitre').textContent = P.orig ? 'Modifier le bloc' : 'Nouveau bloc';
  $('#blkSuppr').style.display = P.orig ? '' : 'none';
  // A series keeps its anchor day and its recurrence: both stay as created
  const fige = serie();
  $('#repRow').classList.toggle('fige', !!P.orig);
  $('#repRow').dataset.tip = P.orig ? 'La répétition est fixée à la création du bloc' : '';
  if (!P.orig) delete $('#repRow').dataset.tip;
  $('#ddDate').style.opacity = fige ? '.55' : '';
  $('#ddDate').style.pointerEvents = fige ? 'none' : '';
  majBloc();
  blkVeilEl.classList.add('open');
}
function fermerBloc() { blkVeilEl?.classList.remove('open'); }

function majBloc(sansInputs) {
  const t = TYP(P.t), v = VET(P.vet);
  const dt = parse(P.date);
  $$('.typ').forEach((b) => b.classList.toggle('on', b.dataset.t === P.t));
  $('#capRow').style.display = t.rdv ? '' : 'none';
  if (!sansInputs) { bS.value = hm(P.s); bE.value = hm(P.e); }
  const rec = REC.find((r) => r.v === P.rec) || REC[0];
  $('#blkSous').textContent = `${v.nom} · ${fmtLong(dt)} · ${hm(P.s)} – ${hm(P.e)}${serie() ? ` · série ${rec.l.toLowerCase()}` : ''}`;
  const sid = P.orig ? P.orig.id : null;
  const chev = BLOCS.filter((b) => b.vet === P.vet && b.date === P.date && b.id !== sid && P.s < b.e && P.e > b.s);
  // The server refuses a new block overlapping a block anchored on the same day
  const refus = !P.orig && chev.some((b) => b.base === P.date);
  const bad = P.e <= P.s, court = !bad && P.e - P.s < 15;
  const box = $('#blkConflit');
  if (bad) { box.className = 'conflit on'; box.innerHTML = `${I_W}<span>L'heure de fin doit être après l'heure de début.</span>`; }
  else if (court) { box.className = 'conflit on'; box.innerHTML = `${I_W}<span>Un bloc dure au moins 15 minutes.</span>`; }
  else if (chev.length) {
    const c = chev[0];
    box.className = 'conflit on';
    box.innerHTML = `${I_W}<span>${esc(v.nom)} a déjà un bloc ${TYP(c.t).l.toLowerCase()} de ${hm(c.s)} à ${hm(c.e)} ce jour-là.${refus ? ' Choisissez un autre horaire.' : ''}</span>`;
  } else { box.className = 'conflit'; box.innerHTML = ''; }
  const ok = $('#blkOk');
  ok.disabled = bad || court || refus;
  ok.textContent = refus ? 'Créneau déjà occupé — choisissez un autre horaire'
    : `${chev.length ? 'Enregistrer malgré tout' : 'Enregistrer'} — ${v.ini} · ${JOURS_C[dt.getDay()].toLowerCase()} ${dt.getDate()} · ${hm(P.s)}–${hm(P.e)}`;
}

/* The mock-up's repetitions: 6 more days, 4 more weeks or 9 more working days */
function finRecurrence(dateIso, rec) {
  const d0 = parse(dateIso);
  if (rec === 'jour') return iso(addD(d0, 6));
  if (rec === 'hebdo') return iso(addD(d0, 28));
  if (rec === 'ouvres') { let n = 0, x = d0; while (n < 9) { x = addD(x, 1); if (x.getDay() > 0 && x.getDay() < 6) n++; } return iso(x); }
  return null;
}
function nbOccurrences(dateIso, rec) {
  if (rec === 'non') return 1;
  const fin = parse(finRecurrence(dateIso, rec));
  let n = 0;
  for (let x = parse(dateIso); x <= fin; x = addD(x, 1)) if (rec !== 'ouvres' || (x.getDay() > 0 && x.getDay() < 6)) n++;
  return rec === 'hebdo' ? 5 : n;
}

async function enregistrer() {
  const btn = $('#blkOk');
  if (btn.disabled || !P) return;
  P.note = $('#bNote').value.trim();
  const t = TYP(P.t), v = VET(P.vet), o = P.orig;
  btn.disabled = true;
  if (o) {
    const avant = { ...o };
    const apres = {
      ...o, vet: P.vet, s: P.s, e: P.e, note: P.note,
      date: serie() ? o.base : P.date,
      type: P.t === o.t ? o.type : t.v,
      cap: P.t === o.t ? o.cap : t.cap,
    };
    const r = await appel('PUT', url('update', o.id), corps(apres));
    if (!D) return;
    if (!r.ok) { btn.disabled = false; showToast(r.msg, 'danger'); if (r.gone) { fermerBloc(); recharger(); } return; }
    fermerBloc();
    await recharger();
    toastUndo(`${serie() ? 'Série modifiée' : 'Bloc modifié'} — ${v.nom} · ${t.l} ${hm(P.s)}–${hm(P.e)}`, async () => {
      const u = await appel('PUT', url('update', avant.id), corps({ ...avant, date: avant.base }));
      if (!u.ok) showToast(u.msg, 'danger');
      recharger();
    });
  } else {
    const nouveau = { vet: P.vet, type: t.v, date: P.date, s: P.s, e: P.e, cap: t.cap, rec: freqDe(P.rec), until: finRecurrence(P.date, P.rec), note: P.note };
    const r = await appel('POST', D.urls.create, corps(nouveau));
    if (!D) return;
    if (!r.ok) { btn.disabled = false; showToast(r.msg, 'danger'); return; }
    const n = nbOccurrences(P.date, P.rec), id = r.body?.id;
    fermerBloc();
    await recharger();
    toastUndo(`${n > 1 ? n + ' blocs créés' : 'Bloc créé'} — ${v.nom} · ${t.l} ${hm(P.s)}–${hm(P.e)}`, id ? async () => {
      const u = await appel('DELETE', url('delete', id));
      if (!u.ok) showToast(u.msg, 'danger');
      recharger();
    } : undefined);
  }
}

async function supprimer() {
  if (!P || !P.orig) return;
  const b = P.orig, v = VET(b.vet), t = TYP(b.t), estSerie = serie();
  const r = await appel('DELETE', url('delete', b.id));
  if (!D) return;
  if (!r.ok) { showToast(r.msg, 'danger'); if (r.gone) { fermerBloc(); recharger(); } return; }
  fermerBloc();
  await recharger();
  toastUndo(`${estSerie ? 'Série supprimée' : 'Bloc supprimé'} — ${v.nom} · ${t.l} ${hm(b.s)}–${hm(b.e)}`, async () => {
    const u = await appel('POST', D.urls.create, corps({ ...b, date: b.base }));
    if (!u.ok) showToast(u.msg, 'danger');
    recharger();
  });
}

/* ═════════ Init ═════════ */
export function init() {
  const root = $('.pln');
  const raw = $('#planning-data');
  if (!root || !raw) return;

  D = JSON.parse(raw.textContent);
  t0 = Date.now();
  AUJ = parse(D.today);
  VETS = D.vets;
  BLOCS = []; loaded.clear(); pending.clear();
  merge(D.range);
  vue = D.view;
  cur = parse(D.date);
  if (vue === 'month' && cur.getMonth() !== AUJ.getMonth()) cur = new Date(cur.getFullYear(), cur.getMonth(), 1);
  moisVu = new Date(cur.getFullYear(), cur.getMonth(), 1);
  visibles = new Set(VETS.map((v) => v.id));
  typesOn = new Set(TYPES.map((t) => t.k));
  P = null;

  calHead = $('#calHead'); calInner = $('#calInner'); calBody = $('#calBody');
  blkVeilEl = $('#blkVeil');

  ac = new AbortController();
  const { signal } = ac;

  $('#btnAuj').addEventListener('click', () => { cur = new Date(AUJ); moisVu = new Date(AUJ.getFullYear(), AUJ.getMonth(), 1); rendre(); }, { signal });
  $('#btnPrev').addEventListener('click', () => decaler(-1), { signal });
  $('#btnNext').addEventListener('click', () => decaler(1), { signal });
  $$('#vueSeg button').forEach((b) => b.addEventListener('click', () => {
    vue = b.dataset.v;
    if (vue === 'month' && cur.getMonth() !== AUJ.getMonth()) cur = new Date(cur.getFullYear(), cur.getMonth(), 1);
    $$('#vueSeg button').forEach((x) => x.classList.toggle('on', x === b));
    rendre();
  }, { signal }));
  $('#btnAjout').addEventListener('click', ajouter, { signal });

  document.addEventListener('click', (e) => { if (!(e.target instanceof Element) || !e.target.closest('.dp')) $$('.dp.open').forEach((x) => x.classList.remove('open')); }, { signal });

  bindModal(signal);

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if ($('.dd.open,.dp.open,.tp.open')) return;
      if (blkVeilEl.classList.contains('open')) { e.stopPropagation(); fermerBloc(); }
      return;
    }
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.target instanceof Element && e.target.matches('input,textarea,select,[contenteditable]')) return;
    if ($('.veil.open,.mveil.open,.racc-veil.open')) return;
    const k = e.key.toLowerCase();
    if (k === 't') $('#btnAuj').click();
    if (k === 'b') { e.preventDefault(); ajouter(); }
    if (e.key === 'ArrowLeft') decaler(-1);
    if (e.key === 'ArrowRight') decaler(1);
  }, { signal, capture: true });

  let resizeTmr;
  window.addEventListener('resize', () => { clearTimeout(resizeTmr); resizeTmr = setTimeout(() => { if (vue !== 'month') rendreGrille(); }, 60); }, { signal });
  document.addEventListener('turbo:before-cache', fermerBloc, { signal });

  rendre();
  calBody.scrollTop = Math.max(0, (8 * 60 - START - 20) * PX);

  // The now-line follows the clock
  ticker = setInterval(() => { if (vue !== 'month' && !blkVeilEl.classList.contains('open')) rendreGrille(); }, 60000);
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearInterval(ticker);
  ticker = null;
  document.body.classList.remove('dragging');
  D = null;
}
