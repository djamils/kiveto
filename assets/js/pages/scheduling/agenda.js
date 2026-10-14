/**
 * Page module — Agenda (agenda-v-topbar_5 / agenda-v-topbar-charge_3 mock-ups).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * Ports the mock-up's agenda script onto the server's data:
 * - week view (one fixed lane per practitioner in each day) and day view
 *   (one column per practitioner), overlap lanes, now-line, past / cancelled
 *   / no-show / emergency states;
 * - aside: mini-calendar (dots on booked days, week highlight), practitioner
 *   filters with counts, motif legend with counts;
 * - « Blocs planning » (rails in the week view, bands in the day view, lunch
 *   breaks read from the gaps), « Créneaux libres » with a minimum duration;
 * - click or drag on the grid, a free slot, « Nouveau RDV » or R → booking
 *   modal (date / time pickers, patient search, practitioner, motif, note,
 *   overlap and outside-planning warnings);
 * - booking card: profile, edit, cancel (inline confirmation), no-show;
 * - keys: T today, ← → period, Esc closes, R new booking.
 *
 * Data: the payload of AgendaViewBuilder::page() (#agenda-data) covers the
 * 6-week grid of the opening month; other periods are fetched from the range
 * endpoint as the user browses. Every change posts to its Scheduling
 * endpoint and the touched days are fetched again. Listeners are bound in
 * init() and released in cleanup().
 */
import { showToast, toastUndo, ancrer, creerDropdown } from 'kiveto/shell';

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
const jourCourt = (d) => `${JOURS_C[d.getDay()].toLowerCase()} ${d.getDate()}`;
const dureeTxt = (d) => (d < 60 ? d + ' min' : Math.floor(d / 60) + ' h' + (d % 60 ? ' ' + String(d % 60).padStart(2, '0') : ''));

let AUJ = null;
let t0 = 0;
/** Minutes since midnight in the clinic, advancing with the page's clock. */
const nowMin = () => D.nowMin + Math.floor((Date.now() - t0) / 60000);

/* ═════════ Reference data ═════════ */
const MOTIFS = [
  { k: 'consult', l: 'Consultation', c: '#4338ca', d: 30 },
  { k: 'vaccin', l: 'Vaccin', c: '#0891b2', d: 20 },
  { k: 'suivi', l: 'Suivi', c: '#7c3aed', d: 20 },
  { k: 'chir', l: 'Chirurgie', c: '#b45309', d: 60 },
  { k: 'bilan', l: 'Bilan', c: '#059669', d: 30 },
  { k: 'urg', l: 'Urgence', c: '#dc2626', d: 30 },
];
const MOT = (k) => MOTIFS.find((m) => m.k === k) || MOTIFS[0];

/* Planning block types — same names as the planning page */
const TYPES = [
  { k: 'consult', l: 'Consultation', c: '#4338ca', rdv: true },
  { k: 'chir', l: 'Chirurgie', c: '#b45309', rdv: true },
  { k: 'garde', l: 'Garde / urgences', c: '#dc2626', rdv: true },
  { k: 'bilan', l: 'Bilans / labo', c: '#059669', rdv: true },
  { k: 'admin', l: 'Administratif', c: '#64748b', rdv: false },
  { k: 'formation', l: 'Formation', c: '#0891b2', rdv: false },
  { k: 'conge', l: 'Congé', c: '#94a3b8', rdv: false },
  { k: 'pause', l: 'Pause déjeuner', c: '#cbd5e1', rdv: false },
];
const TYP = (k) => TYPES.find((t) => t.k === k) || TYPES[0];
const COURT = { consult: 'Consult.', chir: 'Chir.', garde: 'Garde', bilan: 'Bilan', admin: 'Admin.', formation: 'Form.', conge: 'Congé', pause: 'Pause' };
/* Blocks open to any booking (free slots are searched there) */
const RDV_OK = (t) => t === 'consult' || t === 'garde' || t === 'bilan';
/* Blocks the domain refuses bookings in (and real lunch-break blocks, see below) */
const FERME = (t) => t === 'admin' || t === 'formation' || t === 'conge';

let VETS = [];
const VET = (id) => VETS.find((v) => v.id === id) || { id, nom: 'Praticien', court: 'Praticien', ini: '?', c: '#64748b' };

/* ═════════ Store: bookings and blocks of the loaded days ═════════ */
let RDVS = [];
let BLOCS = [];
const loaded = new Set();
const pending = new Map();

/* A booking without a file keeps the typed name in its note (« Nouveau patient : Rex — … ») */
const PREFIXE = 'Nouveau patient : ';
const nomSansFiche = (r) => (r.note && r.note.startsWith(PREFIXE) ? r.note.slice(PREFIXE.length).split(' — ')[0] : '');
const pat = (r) => ({ a: r.a || nomSansFiche(r) || 'Sans fiche', e: r.e || '🐾', cli: r.cli || (r.a ? 'Sans propriétaire' : 'Nouveau client'), tel: r.tel || '' });
const cliCourt = (c) => { const m = c.split(' '); return m.length > 2 ? m[0] + ' ' + m[m.length - 1] : c; };
const motifTxt = (r) => { const mo = MOT(r.m); return r.reason && r.reason !== mo.l ? r.reason : mo.l; };

function blocsDe(vet, d) { const k = iso(d); return BLOCS.filter((b) => b.vet === vet && b.date === k).sort((x, y) => x.s - y.s); }

/* Lunch break: any gap of 30 min to 2 h starting between 11:30 and 14:00 in a worked day,
   unless the planning already holds a break block there (`virt` marks the derived ones) */
function pauses(dates) {
  const out = [];
  dates.forEach((dt) => VETS.forEach((v) => {
    const du = BLOCS.filter((b) => b.date === dt && b.vet === v.id);
    const bl = du.filter((b) => b.t !== 'conge' && b.t !== 'pause').sort((a, b) => a.s - b.s);
    for (let i = 0; i < bl.length - 1; i++) {
      const s = bl[i].e, e = bl[i + 1].s;
      if (du.some((b) => b.t === 'pause' && b.s < e && b.e > s)) continue;
      if (e - s >= 30 && e - s <= 120 && s >= 11 * 60 + 30 && s <= 14 * 60) out.push({ id: `p-${v.id}-${dt}-${s}`, date: dt, vet: v.id, s, e, t: 'pause', note: '', virt: true });
    }
  }));
  return out;
}

function merge(range) {
  const days = [];
  for (let d = parse(range.from); d <= parse(range.to); d = addD(d, 1)) days.push(iso(d));
  const set = new Set(days);
  RDVS = RDVS.filter((r) => !set.has(r.date)).concat(range.appointments);
  BLOCS = BLOCS.filter((b) => !set.has(b.date)).concat(range.blocks);
  BLOCS = BLOCS.concat(pauses(days));
  days.forEach((k) => loaded.add(k));
}

async function fetchRange(from, to) {
  const key = iso(from) + '|' + iso(to);
  if (pending.has(key)) return pending.get(key);
  const p = (async () => {
    const res = await fetch(`${D.urls.range}?from=${iso(from)}&to=${iso(to)}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('range ' + res.status);
    merge(await res.json());
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

/** Fetches the given days again after a change (canonical labels and statuses). */
async function refresh(...dates) {
  const ds = [...new Set(dates.map(iso))].map(parse);
  try {
    await Promise.all(ds.map((d) => fetchRange(d, d)));
  } catch {
    showToast('Agenda non actualisé — rechargez la page', 'danger');
  }
  if (D) rendre();
}

/* ═════════ View state ═════════ */
let START = 8 * 60, END = 20 * 60;
const HH = 56, PX = HH / 60, RAIL = 15;
let vue = 'week', cur = null, moisVu = null;
let visibles = new Set();
let blocsOn = false, libresOn = false, durLibre = 20, popId = null;
let calHead, calInner, calBody, pop, rdvVeilEl;

function estPasse(d, m) { return (d < AUJ && !memeJour(d, AUJ)) || (memeJour(d, AUJ) && m < nowMin()); }

function colonnes() {
  if (vue === 'week') {
    const l = lundiDe(cur);
    return Array.from({ length: 7 }, (_, i) => { const d = addD(l, i); return { d, vets: VETS.map((v) => v.id).filter((v) => visibles.has(v)), key: iso(d) + '|*' }; });
  }
  return VETS.filter((v) => visibles.has(v.id)).map((v) => ({ d: new Date(cur), vets: [v.id], vet: v, key: iso(cur) + '|' + v.id }));
}
function rdvsCol(col) { const k = iso(col.d); return RDVS.filter((r) => r.date === k && col.vets.includes(r.vet)); }

/* Overlaps: clusters + lanes */
function couloirs(evs) {
  evs.sort((a, b) => a.s - b.s || b.dur - a.dur);
  let grappe = [], fin = -1;
  const fermer = () => {
    const lanes = [];
    grappe.forEach((e) => { let i = lanes.findIndex((x) => x <= e.s); if (i < 0) { i = lanes.length; lanes.push(0); } lanes[i] = e.s + e.dur; e._l = i; });
    grappe.forEach((e) => { e._n = lanes.length; });
  };
  evs.forEach((e) => { if (e.s >= fin && grappe.length) { fermer(); grappe = []; } grappe.push(e); fin = Math.max(fin, e.s + e.dur); });
  if (grappe.length) fermer();
  return evs;
}

/* Free slots: 5-min ticks, set of free practitioners per tick */
function plagesLibres(col) {
  const out = []; let run = null; const k = iso(col.d);
  for (let m = START; m < END; m += 5) {
    const libres = col.vets.filter((v) => {
      if (!blocsDe(v, col.d).some((b) => RDV_OK(b.t) && m >= b.s && m + 5 <= b.e)) return false;
      if (RDVS.some((r) => r.st !== 'annule' && r.vet === v && r.date === k && m < r.s + r.dur && m + 5 > r.s)) return false;
      return !estPasse(col.d, m);
    });
    const key = libres.join(',');
    if (run && run.key === key) { run.e = m + 5; } else { if (run && run.key) out.push(run); run = { key, vets: libres, s: m, e: m + 5 }; }
  }
  if (run && run.key) out.push(run);
  return out.filter((r) => r.e - r.s >= durLibre);
}

function evHtml(r, col) {
  const v = VET(r.vet), mo = MOT(r.m), p = pat(r);
  const h = r.dur * PX;
  const n = r._n || 1, l = r._l || 0;
  const pl = blocsOn && vue === 'week' && col.vets.length > 1 ? (col.vets.length * RAIL + 4) + 'px' : '0px';
  const pos = `top:${(r.s - START) * PX + 1}px;height:${h - 2}px;left:calc(${pl} + (100% - ${pl}) * ${l} / ${n} + 2px);width:calc((100% - ${pl}) / ${n} - 4px)`;
  const passe = r.st === 'fait' || estPasse(parse(r.date), r.s + r.dur);
  const cls = ['ev', h < 40 ? 'xs' : '', vue === 'week' && n >= 2 ? 'nar' : '', r.m === 'urg' ? 'urg' : '', r.st === 'annule' ? 'annule' : r.st === 'absent' ? 'absent' : passe ? 'passe' : '', popId === r.id ? 'sel' : '', r._busy ? 'busy' : ''].filter(Boolean).join(' ');
  const etroit = vue === 'week' && n >= 2;
  const corps = etroit
    ? `<span class="ea en"><i class="mdot" style="background:${mo.c}"></i>${esc(p.a)}</span>${h >= 40 ? `<span class="et">${hm(r.s)}</span>` : ''}`
    : h < 40
      ? `<span class="et"><i class="mdot" style="background:${mo.c}"></i>${hm(r.s)}</span><span class="ea">${esc(p.a)}</span>`
      : `<span class="et"><i class="mdot" style="background:${mo.c}"></i>${hm(r.s)} – ${hm(r.s + r.dur)}</span><span class="ea">${p.e} ${esc(p.a)}</span>${h >= 46 ? `<span class="em">${esc(r.note || motifTxt(r))} · ${esc(cliCourt(p.cli))}</span>` : ''}`;
  const stc = !etroit && h >= 28 && (r.st === 'annule' || r.st === 'absent') ? `<span class="stc">${r.st === 'annule' ? 'Annulé' : 'Non venu'}</span>` : '';
  const tip = `${r.st === 'annule' ? 'Annulé · ' : r.st === 'absent' ? 'Non venu · ' : ''}${p.a} · ${hm(r.s)}–${hm(r.s + r.dur)} · ${motifTxt(r)}`;
  return `<div class="${cls}" data-id="${esc(r.id)}" style="${pos};--c:${v.c};--cbg:${v.c}38" data-tip="${esc(tip)}">${corps}${stc}</div>`;
}

/* Grid bounds: 08:00–20:00, stretched to whole hours around what the period holds */
function bornes(cols) {
  let s = 8 * 60, e = 20 * 60;
  cols.forEach((c) => {
    rdvsCol(c).forEach((r) => { s = Math.min(s, r.s); e = Math.max(e, r.s + r.dur); });
    if (blocsOn) c.vets.forEach((v) => blocsDe(v, c.d).forEach((b) => { if (b.t !== 'conge') { s = Math.min(s, b.s); e = Math.max(e, b.e); } }));
  });
  START = Math.max(0, Math.floor(s / 60) * 60);
  END = Math.min(24 * 60, Math.ceil(e / 60) * 60);
}

function rendre() {
  if (!D) return;
  const cols = colonnes();
  const visStart = vue === 'week' ? lundiDe(cur) : new Date(cur);
  const visEnd = vue === 'week' ? addD(lundiDe(cur), 6) : new Date(cur);
  const g = lundiDe(new Date(moisVu.getFullYear(), moisVu.getMonth(), 1));
  ensure(visStart < g ? visStart : g, visEnd > addD(g, 41) ? visEnd : addD(g, 41))
    .then((fetched) => { if (fetched && D) rendre(); })
    .catch(() => showToast('Impossible de charger cette période', 'danger'));

  bornes(cols);
  const tpl = `56px repeat(${cols.length},minmax(0,1fr))`;
  const today = AUJ, nm = nowMin();
  // headers
  calHead.style.gridTemplateColumns = tpl;
  calHead.innerHTML = '<div></div>' + cols.map((c) => {
    const n = rdvsCol(c).filter((r) => r.st !== 'annule').length;
    if (vue === 'week') {
      return `<div class="cal-hd${memeJour(c.d, today) ? ' auj' : ''}"><span class="dn">${JOURS_C[c.d.getDay()]}</span><span class="dnum">${c.d.getDate()}</span><span class="cnt">${n ? n + ' RDV' : '—'}</span>${blocsOn && c.vets.length > 1 ? `<span class="rail-ini">${c.vets.map((v) => `<i style="color:${VET(v).c}">${esc(VET(v).ini)}</i>`).join('')}</span>` : ''}</div>`;
    }
    return `<div class="cal-hd vet"><span class="vav" style="background:${c.vet.c}22;color:${c.vet.c}">${esc(c.vet.ini)}</span><b>${esc(c.vet.nom)}</b><span class="cnt">${n} RDV</span></div>`;
  }).join('');
  // grid
  calInner.style.gridTemplateColumns = tpl;
  calInner.style.setProperty('--hh', HH + 'px');
  const H = (END - START) * PX;
  let html = `<div class="gut" style="height:${H}px">` + Array.from({ length: (END - START) / 60 }, (_, i) => (i ? `<span style="top:${i * HH}px">${hm(START + i * 60)}</span>` : '')).join('') + '</div>';
  cols.forEach((c, ci) => {
    const we = c.d.getDay() === 0 || c.d.getDay() === 6;
    let inner = '';
    // planning blocks
    if (blocsOn) {
      c.vets.forEach((vid, i) => {
        const v = VET(vid);
        blocsDe(vid, c.d).forEach((b) => {
          const t = TYP(b.t), top = (Math.max(b.s, START) - START) * PX, hgt = (Math.min(b.e, END) - Math.max(b.s, START)) * PX;
          if (hgt <= 0) return;
          const tip = `${v.nom} · ${t.l} ${hm(b.s)}–${hm(b.e)}${b.note ? ' · ' + b.note : ''}`;
          if (vue === 'week' && c.vets.length > 1) {
            inner += `<div class="prail t-${b.t}" style="top:${top}px;height:${hgt}px;left:${2 + i * RAIL}px;--c:${t.c}" data-tip="${esc(tip)}">${hgt > 44 && b.t !== 'pause' ? `<span>${COURT[b.t]}</span>` : ''}</div>`;
          } else {
            inner += `<div class="pband t-${b.t}" style="top:${top}px;height:${hgt}px;--c:${t.c}"><span class="pb-l"><i></i>${t.l}${b.note ? ` · <em>${esc(b.note)}</em>` : ''}<b>${hm(b.s)}–${hm(b.e)}</b></span></div>`;
          }
        });
      });
    }
    // free slots
    if (libresOn) {
      plagesLibres(c).forEach((f) => {
        const lbl = vue === 'week' ? 'Libre · ' + f.vets.map((v) => VET(v).ini).join(' ') : `Libre · ${hm(f.s)}–${hm(f.e)}`;
        inner += `<div class="free" data-s="${f.s}" data-e="${f.e}" data-v="${esc(f.vets[0])}" style="top:${(f.s - START) * PX + 1}px;height:${(f.e - f.s) * PX - 2}px"><span>${esc(lbl)}</span></div>`;
      });
    }
    // bookings
    if (vue === 'week' && c.vets.length > 1) {
      // week: one fixed lane per practitioner (same order as the rails)
      const actifs = c.vets.filter((v) => rdvsCol(c).some((r) => r.vet === v));
      actifs.forEach((v, i) => couloirs(rdvsCol(c).filter((r) => r.vet === v).map((r) => Object.assign(r, { _l: 0, _n: 1 }))).forEach((r) => { r._n = actifs.length * r._n; r._l = i * (r._n / actifs.length) + r._l; inner += evHtml(r, c); }));
    } else couloirs(rdvsCol(c).map((r) => Object.assign(r, { _l: 0, _n: 1 }))).forEach((r) => { inner += evHtml(r, c); });
    // now
    if (memeJour(c.d, today) && nm > START && nm < END) inner += `<div class="nowl" style="top:${(nm - START) * PX}px"></div>`;
    inner += '<div class="hover-slot"></div>';
    html += `<div class="ccol${we ? ' we' : ''}" data-ci="${ci}" style="height:${H}px">${inner}</div>`;
  });
  calInner.innerHTML = html;
  calHead.style.paddingRight = (calBody.offsetWidth - calBody.clientWidth) + 'px';

  // period label
  if (vue === 'week') {
    const l = lundiDe(cur), d7 = addD(l, 6);
    $('#schLabel').textContent = l.getMonth() === d7.getMonth()
      ? `${l.getDate()} – ${d7.getDate()} ${MOIS[d7.getMonth()]} ${d7.getFullYear()}`
      : `${l.getDate()} ${MOIS[l.getMonth()]} – ${d7.getDate()} ${MOIS[d7.getMonth()]} ${d7.getFullYear()}`;
  } else {
    $('#schLabel').textContent = `${fmtLong(cur)} ${cur.getFullYear()}`;
  }
  brancherGrille(cols);
  rendreAside();
  majUrl();
}

/* Keeps the period in the address so a reload (or a shared link) opens it */
function majUrl() {
  const u = new URL(window.location.href);
  u.searchParams.set('view', vue);
  u.searchParams.set('date', iso(cur));
  u.searchParams.delete('new');
  if (u.href !== window.location.href) window.history.replaceState(window.history.state, '', u.href);
}

function brancherGrille(cols) {
  $$('.ev', calInner).forEach((el) => el.addEventListener('click', (e) => { e.stopPropagation(); ouvrirPop(el.dataset.id, el); }));
  $$('.free', calInner).forEach((el) => el.addEventListener('click', (e) => {
    e.stopPropagation();
    const col = cols[+el.closest('.ccol').dataset.ci];
    const y = e.clientY - el.closest('.ccol').getBoundingClientRect().top;
    let m = Math.round((START + y / PX) / 5) * 5;
    m = Math.max(+el.dataset.s, Math.min(m, +el.dataset.e - durLibre));
    ouvrirRdv({ date: col.d, s: m, vet: el.dataset.v, dur: durLibre });
  }));
  $$('.ccol', calInner).forEach((colEl) => {
    const col = cols[+colEl.dataset.ci];
    const ghost = $('.hover-slot', colEl);
    const minuteAt = (e) => Math.floor((START + (e.clientY - colEl.getBoundingClientRect().top) / PX) / 15) * 15;
    colEl.addEventListener('mousemove', (e) => {
      if (ghost.classList.contains('drag')) return;
      if (e.target.closest('.ev,.free,.prail')) { ghost.style.display = 'none'; return; }
      const m = Math.min(minuteAt(e), END - 30);
      ghost.style.display = 'block';
      ghost.style.top = (m - START) * PX + 1 + 'px';
      ghost.style.height = 30 * PX - 2 + 'px';
      ghost.textContent = '+ ' + hm(m);
    });
    colEl.addEventListener('mouseleave', () => { ghost.style.display = 'none'; });
    const vetA = (m) => (col.vet ? col.vet.id : (col.vets.find((v) => blocsDe(v, col.d).some((b) => RDV_OK(b.t) && m >= b.s && m < b.e)) || defautVet()));
    colEl.addEventListener('mousedown', (e) => {
      if (e.button !== 0 || e.target.closest('.ev')) return;
      const surLibre = !!e.target.closest('.free');
      const m0 = Math.min(minuteAt(e), END - 15);
      let moved = false, s = m0, f = m0 + 15;
      e.preventDefault(); document.body.classList.add('dragging');
      const mv = (ev) => {
        const m1 = Math.max(START, Math.min(END, Math.round((START + (ev.clientY - colEl.getBoundingClientRect().top) / PX) / 15) * 15));
        if (!moved && Math.abs(m1 - m0) < 15) return;
        moved = true;
        s = Math.min(m0, m1); f = Math.max(m0 + 15, m1); if (m1 < m0) { s = m1; f = m0 + 15; }
        ghost.classList.add('drag'); ghost.style.display = 'block';
        ghost.style.top = (s - START) * PX + 1 + 'px'; ghost.style.height = (f - s) * PX - 2 + 'px';
        ghost.textContent = `${hm(s)} – ${hm(f)} · ${dureeTxt(f - s)}`;
      };
      const up = () => {
        document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up);
        document.body.classList.remove('dragging'); ghost.classList.remove('drag'); ghost.style.display = 'none';
        if (moved) ouvrirRdv({ date: col.d, s, vet: vetA(s), dur: f - s });
        else if (!surLibre) ouvrirRdv({ date: col.d, s: Math.min(m0, END - 30), vet: vetA(m0) });
      };
      document.addEventListener('mousemove', mv); document.addEventListener('mouseup', up);
    });
  });
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

/* ═════════ Aside: mini-calendar, practitioners, motifs ═════════ */
function rendreAside() {
  const g = lundiDe(new Date(moisVu.getFullYear(), moisVu.getMonth(), 1));
  ensure(g, addD(g, 41)).then((fetched) => { if (fetched && D) rendreAside(); }).catch(() => {});
  const jours = new Set(RDVS.map((r) => r.date));
  rendreMiniCal($('#miniCal'), cur, moisVu, {
    semaine: vue === 'week',
    has: (d) => jours.has(iso(d)),
    onMois: (n) => { moisVu = new Date(moisVu.getFullYear(), moisVu.getMonth() + n, 1); rendreAside(); },
    onPick: (d) => { cur = d; moisVu = new Date(d.getFullYear(), d.getMonth(), 1); fermerPop(); rendre(); },
  });
  const l = lundiDe(cur), k = iso(cur);
  const periode = vue === 'week' ? ((r) => { const d = parse(r.date); return d >= l && d < addD(l, 7); }) : ((r) => r.date === k);
  $('#vetList').innerHTML = VETS.length ? VETS.map((v) =>
    `<button type="button" class="vchip${visibles.has(v.id) ? '' : ' off'}" data-v="${esc(v.id)}"><span class="vdot" style="background:${v.c};border-color:${v.c}"></span>${esc(v.court)}<span class="vn">${RDVS.filter((r) => r.st !== 'annule' && r.vet === v.id && periode(r)).length}</span></button>`).join('')
    : '<div class="vchip" style="cursor:default;color:var(--encre-35)">Aucun praticien</div>';
  $$('#vetList .vchip[data-v]').forEach((b) => b.addEventListener('click', () => {
    const id = b.dataset.v;
    if (visibles.has(id)) { if (visibles.size === 1) { showToast('Au moins un vétérinaire doit rester affiché'); return; } visibles.delete(id); } else visibles.add(id);
    fermerPop(); rendre();
  }));
  $('#motLegend').innerHTML = MOTIFS.map((m) =>
    `<div class="vchip" style="cursor:default"><span class="vdot" style="background:${m.c};border-radius:50%"></span>${m.l}<span class="vn">${RDVS.filter((r) => r.st !== 'annule' && r.m === m.k && periode(r)).length}</span></div>`).join('');
}

/* ═════════ Next free slot of a practitioner from now ═════════ */
const defautVet = () => (VETS.find((v) => v.id === D.me && visibles.has(v.id)) || VETS.find((v) => visibles.has(v.id)) || VETS[0] || { id: '' }).id;

async function prochainLibre(vet, dur) {
  try { await ensure(AUJ, addD(AUJ, 6)); } catch { /* search what is known */ }
  for (let j = 0; j < 7; j++) {
    const d = addD(AUJ, j), k = iso(d);
    for (let m = Math.ceil(Math.max(8 * 60, j === 0 ? nowMin() : 0) / 15) * 15; m + dur <= 20 * 60; m += 15) {
      if (!blocsDe(vet, d).some((b) => RDV_OK(b.t) && m >= b.s && m + dur <= b.e)) continue;
      if (RDVS.some((r) => r.st !== 'annule' && r.vet === vet && r.date === k && m < r.s + r.dur && m + dur > r.s)) continue;
      return { date: d, s: m, vet, dur };
    }
  }
  return { date: new Date(AUJ), s: 14 * 60, vet, dur };
}
const nouveau = async () => { if (!VETS.length) { showToast('Aucun praticien dans cette clinique'); return; } ouvrirRdv(await prochainLibre(defautVet(), 30)); };

/* ═════════ Server calls ═════════ */
const ERREURS = {
  APPOINTMENT_CONFLICT: 'Créneau déjà pris — le praticien a un autre rendez-vous à cette heure',
  APPOINTMENT_TERMINAL_STATUS: 'Ce rendez-vous est clôturé — il ne peut plus être modifié',
  APPOINTMENT_NOT_MARKABLE: 'Ce rendez-vous ne peut pas être marqué non venu',
  CSRF_INVALID: 'Session expirée — rechargez la page',
};
function message(body, fallback) {
  const g = body?.errors?.global?.[0] || '';
  if (/capacity/i.test(g)) return 'Plage complète — la capacité du planning est atteinte';
  if (/block/i.test(g)) return 'Hors des plages réservables du praticien (congé, administratif, formation)';
  if (/eligible/i.test(g)) return 'Ce praticien ne peut pas recevoir de rendez-vous dans cette clinique';
  if (/passé/i.test(g)) return 'Impossible d\'annuler un rendez-vous passé';
  return ERREURS[body?.errorCode] || fallback;
}
async function post(target, fields) {
  const fd = new FormData();
  fd.append('_token', D.token);
  Object.entries(fields).forEach(([k, v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
  let res, body = null;
  try {
    res = await fetch(target, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    body = await res.json().catch(() => null);
  } catch {
    return { ok: false, body: null };
  }
  return { ok: res.ok && body?.success !== false, body };
}

/* ═════════ Booking card (popover) ═════════ */
const I_CLOCK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
const I_USER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c.9-3.6 3.8-6 8-6s7.1 2.4 8 6"/></svg>';
const I_TAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r=".5"/></svg>';
const I_NOTE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>';

function ouvrirPop(id, el) {
  const r = RDVS.find((x) => x.id === id); if (!r) return;
  popId = id;
  $$('.ev.sel', calInner).forEach((x) => x.classList.remove('sel'));
  el.classList.add('sel');
  const p = pat(r), v = VET(r.vet), mo = MOT(r.m), d = parse(r.date);
  const passe = r.st === 'fait' || estPasse(d, r.s + r.dur);
  const profil = r.animalId ? url('animal', r.animalId) : r.ownerId ? url('client', r.ownerId) : null;
  const st = r.st === 'annule' || r.st === 'absent' ? r.st : null;
  pop.classList.remove('conf');
  pop.innerHTML = `
    <div class="pop-head">
      <span class="pid"><b>${p.e} ${esc(p.a)}<span class="puce" style="background:${mo.c}17;color:${mo.c}"><i class="pdot" style="background:${mo.c}"></i>${mo.l}</span></b><span class="sous">${esc(p.cli)}${p.tel ? ' · ' + esc(p.tel) : ''}</span></span>
      <button type="button" class="pop-x" aria-label="Fermer"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="pop-rows">
      <div class="pop-row">${I_CLOCK}<span>${JOURS_C[d.getDay()]} ${d.getDate()} ${MOIS[d.getMonth()]} · <span class="m">${hm(r.s)} – ${hm(r.s + r.dur)}</span></span><span class="g">${r.dur} min</span></div>
      <div class="pop-row">${I_USER}<span style="display:flex;align-items:center;gap:6px"><i style="width:8px;height:8px;border-radius:3px;background:${v.c}"></i>${esc(v.nom)}</span></div>
      <div class="pop-row">${I_TAG}<span>${esc(motifTxt(r))}</span></div>
      ${r.note ? `<div class="pop-row note">${I_NOTE}<span>${esc(r.note)}</span></div>` : ''}
    </div>
    ${st ? `<div class="pop-st ${st}">${st === 'annule' ? 'Annulé — créneau libéré' : 'Non venu — l\'animal ne s\'est pas présenté'}</div>` : ''}
    <div class="pop-foot">
      ${profil ? `<a class="btn primaire" href="${esc(profil)}" data-a="profil">Profil</a>` : '<button type="button" class="btn primaire" disabled data-tip="Rendez-vous sans fiche patient">Profil</button>'}
      ${st || r.st === 'fait' ? ''
        : passe ? '<button type="button" class="btn ghost-danger" data-a="absent">Marquer non venu</button>'
          : '<button type="button" class="btn" data-a="modifier">Modifier</button><button type="button" class="btn ghost-danger" data-a="annuler">Annuler</button>'}
    </div>
    <div class="pop-confirm">
      <p>Annuler le rendez-vous de ${esc(p.a)} ?</p>
      <div><button type="button" class="btn danger" data-a="confirmer">Confirmer</button><button type="button" class="btn" data-a="non">Non</button></div>
    </div>`;
  $('.pop-x', pop).addEventListener('click', fermerPop);
  $$('[data-a]', pop).forEach((b) => b.addEventListener('click', async (e) => {
    const a = b.dataset.a;
    if (a === 'profil') return;
    e.preventDefault();
    if (a === 'modifier') { fermerPop(); ouvrirRdv({ edit: r }); }
    if (a === 'annuler') pop.classList.add('conf');
    if (a === 'non') pop.classList.remove('conf');
    if (a === 'confirmer') {
      const avant = r.st;
      r.st = 'annule'; fermerPop(); rendre();
      const { ok, body } = await post(url('cancel', r.id), {});
      if (!ok) { r.st = avant; rendre(); showToast(message(body, 'Le rendez-vous n\'a pas pu être annulé'), 'danger'); return; }
      showToast(`RDV annulé — ${p.a}, ${jourCourt(d)} à ${hm(r.s)}`);
      refresh(d);
    }
    if (a === 'absent') {
      const avant = r.st;
      r.st = 'absent'; fermerPop(); rendre();
      const { ok, body } = await post(url('noShow', r.id), {});
      if (!ok) { r.st = avant; rendre(); showToast(message(body, 'Le marquage n\'a pas pu être enregistré'), 'danger'); return; }
      showToast(`${p.a} marqué non venu — ajouté à l'historique du client`);
      refresh(d);
    }
  }));
  ancrer(pop, el);
}
function fermerPop() {
  if (!pop) return;
  pop.classList.remove('open'); popId = null;
  $$('.ev.sel', calInner).forEach((x) => x.classList.remove('sel'));
}

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

/* ═════════ Booking modal ═════════ */
let M = null; // { date, s, dur, vet, pat, m, note, edit, durManuelle }
let ddJour, ddVet, inH, psIn, psMenu, searchCtrl = null, searchTmr = null;

function bindModal(signal) {
  ddJour = creerDatePicker($('#ddJour'), '', (v) => { M.date = parse(v); majModale(); }, { has: (d) => RDVS.some((r) => r.date === iso(d)) });
  ddVet = creerDropdown('ddVet', VETS.map((v) => ({ v: v.id, l: v.nom, c: v.c })), defautVet(), (v) => { M.vet = v; majModale(); });
  // Not wired yet: shown as in the mock-up, inert
  creerDropdown('ddSms', [{ v: '24', l: '24 h avant' }, { v: '48', l: '48 h avant' }, { v: '1', l: '1 h avant' }], '24');
  creerDropdown('ddRec', [{ v: 'non', l: 'Aucune' }, { v: 'hebdo', l: 'Hebdomadaire' }, { v: 'bimens', l: 'Bimensuelle' }, { v: 'mens', l: 'Mensuelle' }], 'non');

  $('#motChips').innerHTML = MOTIFS.map((m) =>
    `<button type="button" class="mot" data-m="${m.k}" style="--mc:${m.c};--mbg:${m.c}12"><i class="mdot" style="background:${m.c}"></i>${m.l}<span class="md">${m.d} min</span></button>`).join('');
  $$('.mot').forEach((b) => b.addEventListener('click', () => {
    M.m = b.dataset.m;
    if (!M.durManuelle) M.dur = MOT(M.m).d;
    majModale();
  }, { signal }));
  $$('#durChips button').forEach((b) => b.addEventListener('click', () => { M.dur = +b.dataset.d; M.durManuelle = true; majModale(); }, { signal }));
  inH = $('#rdvH');
  creerTimePicker(inH, { debut: 8 * 60, fin: 19 * 60 });
  inH.addEventListener('input', () => {
    const ok = /^([01]?\d|2[0-3]):([0-5]\d)$/.test(inH.value.trim());
    inH.style.borderColor = ok ? '' : 'var(--garance)';
    if (ok) { M.s = toMin(inH.value.trim()); majModale(true); }
  }, { signal });

  $('#rdvX').addEventListener('click', fermerRdv, { signal });
  $('#rdvAnnuler').addEventListener('click', fermerRdv, { signal });
  rdvVeilEl.addEventListener('click', (e) => { if (e.target === rdvVeilEl) fermerRdv(); }, { signal });

  psIn = $('#psIn'); psMenu = $('#psMenu');
  psIn.addEventListener('input', chercher, { signal });
  psIn.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && psMenu.classList.contains('open')) { e.preventDefault(); $('.ps-opt', psMenu)?.click(); }
    if (e.key === 'Escape' && psMenu.classList.contains('open')) { e.stopPropagation(); psMenu.classList.remove('open'); }
  }, { signal });
  document.addEventListener('click', (e) => { if (!e.target.closest?.('.ps')) psMenu?.classList.remove('open'); }, { signal });

  $('#rdvOk').addEventListener('click', confirmer, { signal });
}

function ouvrirRdv(o) {
  fermerPop();
  if (!VETS.length) { showToast('Aucun praticien dans cette clinique'); return; }
  if (o.edit) {
    const r = o.edit;
    M = { date: parse(r.date), s: r.s, dur: r.dur, vet: r.vet, pat: { id: r.animalId, ownerId: r.ownerId, ...pat(r) }, m: r.m, note: r.note || '', edit: r, durManuelle: true };
  } else {
    M = { date: new Date(o.date), s: o.s, dur: o.dur || 30, vet: o.vet || defautVet(), pat: null, m: null, note: '', edit: null, durManuelle: !!o.dur };
  }
  ddJour.set(iso(M.date));
  ddVet.set(M.vet);
  inH.value = hm(M.s); inH.style.borderColor = '';
  $('#rdvNote').value = M.note;
  psIn.value = '';
  psMenu.classList.remove('open');
  $('#rdvTitre').textContent = M.edit ? 'Modifier le rendez-vous' : 'Nouveau rendez-vous';
  majPatient();
  majModale();
  rdvVeilEl.classList.add('open');
  setTimeout(() => (M.pat ? $('#rdvNote') : psIn).focus(), 40);
}
function fermerRdv() { rdvVeilEl?.classList.remove('open'); }

function conflits() {
  const k = iso(M.date);
  return RDVS.filter((r) => r.st !== 'annule' && r.st !== 'absent' && r.vet === M.vet && r.date === k && r.id !== M.edit?.id && M.s < r.s + r.dur && M.s + M.dur > r.s);
}
const I_W = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>';
function majModale(sansInput) {
  const v = VET(M.vet), d = M.date;
  if (!sansInput) inH.value = hm(M.s);
  $('#rdvFin').textContent = '→ ' + hm(M.s + M.dur);
  $$('#durChips button').forEach((b) => b.classList.toggle('on', +b.dataset.d === M.dur));
  $$('.mot').forEach((b) => b.classList.toggle('on', b.dataset.m === M.m));
  $('#rdvSlot').textContent = `${fmtLong(d)} · ${hm(M.s)} – ${hm(M.s + M.dur)} · ${v.nom}`;
  // overlaps, closed blocks, outside the planning
  const cf = conflits();
  const box = $('#rdvConflit');
  const bl = blocsDe(M.vet, d);
  const ferme = bl.find((b) => (FERME(b.t) || (b.t === 'pause' && !b.virt)) && M.s < b.e && M.s + M.dur > b.s);
  const horsBloc = !bl.some((b) => (RDV_OK(b.t) || b.t === 'chir') && M.s >= b.s && M.s + M.dur <= b.e);
  let bloque = false;
  if (cf.length) {
    const c = cf[0];
    box.className = 'conflit on warn';
    box.innerHTML = `${I_W}<span>${esc(v.nom)} a déjà ${esc(pat(c).a)} de ${hm(c.s)} à ${hm(c.s + c.dur)} — le créneau se chevauche.</span>`;
    bloque = true;
  } else if (ferme) {
    box.className = 'conflit on warn';
    box.innerHTML = `${I_W}<span>${esc(v.nom)} est en ${esc(TYP(ferme.t).l.toLowerCase())} de ${hm(ferme.s)} à ${hm(ferme.e)} — aucun RDV sur ce créneau.</span>`;
    bloque = true;
  } else if (horsBloc) {
    box.className = 'conflit on info';
    box.innerHTML = `${I_W}<span>Hors du planning de ${esc(v.nom)} ce jour-là — le RDV reste possible.</span>`;
  } else { box.className = 'conflit'; box.innerHTML = ''; }
  // CTA
  const ok = M.pat && M.m && !bloque;
  const btn = $('#rdvOk');
  btn.disabled = !ok;
  btn.textContent = bloque ? 'Créneau indisponible — choisissez un autre horaire'
    : !ok ? (M.edit ? 'Enregistrer les modifications' : 'Confirmer le rendez-vous')
      : (M.edit ? `Enregistrer — ${M.pat.a}, ${jourCourt(d)} à ${hm(M.s)}`
        : `Confirmer — ${M.pat.a}, ${jourCourt(d)} à ${hm(M.s)}`);
}

/* Patient search */
function majPatient() {
  const z = $('#psZone'), f = $('#psFiche');
  if (M.pat) {
    z.style.display = 'none'; f.style.display = '';
    f.innerHTML = `<div class="pfiche"><span class="oe">${M.pat.e}</span><span><b>${esc(M.pat.a)}</b><small>${esc(M.pat.cli)}${M.pat.tel ? ' · ' + esc(M.pat.tel) : ''}</small></span>
      <button type="button" class="fx" aria-label="Changer de patient"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>`;
    $('.fx', f).addEventListener('click', () => { M.pat = null; majPatient(); majModale(); psIn.focus(); });
  } else { z.style.display = ''; f.style.display = 'none'; }
}
function chercher() {
  const q = psIn.value.trim();
  clearTimeout(searchTmr);
  if (!q) { psMenu.classList.remove('open'); return; }
  const creer = `<button type="button" class="ps-opt creer" data-new="1"><span class="oe">＋</span><span><b>Créer « ${esc(q)} »</b><small>Nouveau patient — fiche complétée à l'arrivée</small></span></button>`;
  searchTmr = setTimeout(async () => {
    searchCtrl?.abort();
    searchCtrl = new AbortController();
    let html = '';
    if (q.length >= 2) {
      try {
        const r = await fetch(D.urls.search + '&q=' + encodeURIComponent(q), { signal: searchCtrl.signal, headers: { Accept: 'text/html' } });
        if (r.ok) html = await r.text();
      } catch (e) {
        if (e.name === 'AbortError') return;
      }
    }
    psMenu.innerHTML = html.trim() + creer;
    psMenu.classList.add('open');
    $$('.ps-opt', psMenu).slice(0, -1).slice(6).forEach((b) => b.remove());
    $$('.ps-opt', psMenu).forEach((b) => b.addEventListener('click', () => {
      if (b.dataset.new) {
        M.pat = { id: null, ownerId: null, a: q.charAt(0).toUpperCase() + q.slice(1), e: '🐾', cli: 'Nouveau client', tel: '', cree: true };
      } else {
        M.pat = { id: b.dataset.id, ownerId: null, a: b.dataset.name, e: b.dataset.emoji || '🐾', cli: b.dataset.owner || 'Sans propriétaire', tel: '' };
      }
      psMenu.classList.remove('open'); majPatient(); majModale();
    }));
  }, 160);
}

function noteEnvoyee(note) {
  if (!M.pat.cree) return note;
  return PREFIXE + M.pat.a + (note ? ' — ' + note : '');
}

async function confirmer() {
  const btn = $('#rdvOk');
  if (btn.disabled) return;
  const note = $('#rdvNote').value.trim();
  const d = M.date, s = M.s, dur = M.dur, vet = M.vet, m = M.m, p = M.pat, edit = M.edit;
  const champs = {
    practitionerUserId: vet,
    startsAtUtc: `${iso(d)} ${hm(s)}`,
    durationMinutes: String(dur),
    // An unchanged motif keeps the booking's own wording (« Bilan gériatrique »)
    reason: edit && edit.m === m && edit.reason ? edit.reason : MOT(m).l,
    notes: noteEnvoyee(note),
    animalId: p.id || '',
    // The server reads the owner from the animal when none is given
    ownerId: edit && p.id && p.id === edit.animalId ? (edit.ownerId || '') : '',
  };
  btn.disabled = true;

  if (edit) {
    const r = edit;
    const avant = { ...r };
    Object.assign(r, { date: iso(d), s, dur, vet, m, note: champs.notes, a: p.a, e: p.e, cli: p.cli, animalId: p.id, _busy: true });
    fermerRdv(); cur = new Date(d); rendre();
    const { ok, body } = await post(url('reschedule', r.id), champs);
    r._busy = false;
    if (!ok) { Object.assign(r, avant); rendre(); showToast(message(body, 'Le rendez-vous n\'a pas pu être modifié'), 'danger'); return; }
    toastUndo(`RDV modifié — ${p.a}, ${jourCourt(d)} à ${hm(s)}`, async () => {
      const res = await post(url('reschedule', r.id), {
        practitionerUserId: avant.vet, startsAtUtc: `${avant.date} ${hm(avant.s)}`, durationMinutes: String(avant.dur),
        reason: avant.reason || MOT(avant.m).l, notes: avant.note || '', animalId: avant.animalId || '', ownerId: avant.ownerId || '',
      });
      if (!res.ok) showToast(message(res.body, 'La modification n\'a pas pu être annulée'), 'danger');
      refresh(parse(avant.date), d);
    });
    refresh(parse(avant.date), d);
    return;
  }

  const tmp = { id: 'tmp-' + Date.now(), date: iso(d), s, dur, vet, a: p.a, e: p.e, cli: p.cli, tel: p.tel, animalId: p.id, ownerId: null, m, reason: MOT(m).l, note: champs.notes, st: null, _busy: true };
  RDVS.push(tmp);
  fermerRdv(); cur = new Date(d); moisVu = new Date(d.getFullYear(), d.getMonth(), 1); rendre();
  const { ok, body } = await post(D.urls.create, champs);
  RDVS = RDVS.filter((x) => x !== tmp);
  if (!ok) { rendre(); showToast(message(body, 'Le rendez-vous n\'a pas pu être créé'), 'danger'); return; }
  if (body?.appointment?.id) RDVS.push({ ...tmp, id: body.appointment.id, _busy: false });
  rendre();
  showToast(`RDV confirmé — ${p.a}, ${jourCourt(d)} à ${hm(s)}`);
  refresh(d);
}

/* ═════════ Init ═════════ */
export function init() {
  const root = $('.agd');
  const raw = $('#agenda-data');
  if (!root || !raw) return;

  D = JSON.parse(raw.textContent);
  t0 = Date.now();
  AUJ = parse(D.today);
  VETS = D.vets;
  RDVS = []; BLOCS = []; loaded.clear(); pending.clear();
  merge(D.range);
  vue = D.view;
  cur = parse(D.date);
  moisVu = new Date(cur.getFullYear(), cur.getMonth(), 1);
  visibles = new Set(VETS.map((v) => v.id));
  blocsOn = false; libresOn = false; durLibre = 20; popId = null;

  calHead = $('#calHead'); calInner = $('#calInner'); calBody = $('#calBody');
  pop = $('#rdvPop'); rdvVeilEl = $('#rdvVeil');

  ac = new AbortController();
  const { signal } = ac;

  $('#btnAuj').addEventListener('click', () => { cur = new Date(AUJ); moisVu = new Date(AUJ.getFullYear(), AUJ.getMonth(), 1); fermerPop(); rendre(); }, { signal });
  $('#btnPrev').addEventListener('click', () => { cur = addD(cur, vue === 'week' ? -7 : -1); moisVu = new Date(cur.getFullYear(), cur.getMonth(), 1); fermerPop(); rendre(); }, { signal });
  $('#btnNext').addEventListener('click', () => { cur = addD(cur, vue === 'week' ? 7 : 1); moisVu = new Date(cur.getFullYear(), cur.getMonth(), 1); fermerPop(); rendre(); }, { signal });
  $$('#vueSeg button').forEach((b) => b.addEventListener('click', () => {
    vue = b.dataset.v;
    $$('#vueSeg button').forEach((x) => x.classList.toggle('on', x === b));
    fermerPop(); rendre();
  }, { signal }));
  $('#btnBlocs').addEventListener('click', (e) => { blocsOn = !blocsOn; e.currentTarget.classList.toggle('on', blocsOn); rendre(); }, { signal });
  $('#btnLibres').addEventListener('click', (e) => {
    libresOn = !libresOn;
    e.currentTarget.classList.toggle('free-on', libresOn);
    $('#durs').classList.toggle('on', libresOn);
    rendre();
  }, { signal });
  $$('#durs button').forEach((b) => b.addEventListener('click', () => {
    durLibre = +b.dataset.d;
    $$('#durs button').forEach((x) => x.classList.toggle('on', x === b));
    rendre();
  }, { signal }));
  $('#btnNouveau').addEventListener('click', nouveau, { signal });

  document.addEventListener('click', (e) => { if (!(e.target instanceof Element) || (!e.target.closest('.pop') && !e.target.closest('.ev'))) fermerPop(); }, { signal });
  document.addEventListener('click', (e) => { if (!(e.target instanceof Element) || !e.target.closest('.dp')) $$('.dp.open').forEach((x) => x.classList.remove('open')); }, { signal });
  calBody.addEventListener('scroll', fermerPop, { signal });

  bindModal(signal);

  // Keys — captured before the shell, whose R would reload this page with ?new=1
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if ($('.dd.open,.dp.open,.tp.open')) return;
      if (pop.classList.contains('open')) { e.stopPropagation(); fermerPop(); return; }
      if (rdvVeilEl.classList.contains('open')) { e.stopPropagation(); fermerRdv(); return; }
      return;
    }
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.target instanceof Element && e.target.matches('input,textarea,select,[contenteditable]')) return;
    if ($('.veil.open,.mveil.open,.racc-veil.open')) return;
    const k = e.key.toLowerCase();
    if (k === 'r') { e.preventDefault(); e.stopPropagation(); nouveau(); }
    if (k === 't') $('#btnAuj').click();
    if (e.key === 'ArrowLeft') $('#btnPrev').click();
    if (e.key === 'ArrowRight') $('#btnNext').click();
  }, { signal, capture: true });

  let resizeTmr;
  window.addEventListener('resize', () => { clearTimeout(resizeTmr); resizeTmr = setTimeout(rendre, 60); }, { signal });
  document.addEventListener('turbo:before-cache', () => { fermerPop(); fermerRdv(); }, { signal });

  rendre();
  const voitAuj = vue === 'week' ? AUJ >= lundiDe(cur) && AUJ < addD(lundiDe(cur), 7) : memeJour(cur, AUJ);
  calBody.scrollTop = voitAuj ? Math.max(0, (nowMin() - START - 90) * PX) : 0;

  // The now-line and the past / to-come states follow the clock
  ticker = setInterval(() => { if (!rdvVeilEl.classList.contains('open') && !pop.classList.contains('open')) rendre(); }, 60000);

  if (D.openNew) nouveau();
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearInterval(ticker);
  ticker = null;
  searchCtrl?.abort();
  clearTimeout(searchTmr);
  document.body.classList.remove('dragging');
  D = null;
}
