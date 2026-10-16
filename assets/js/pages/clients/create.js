/**
 * Répertoire — the two creation windows (repertoire-v-topbar_14 mock-up).
 * Driven by list.js: init() on page load, cleanup() before Turbo leaves.
 *
 * - « Nouveau client »: the client and their animals in one go. NOM is typed
 *   in capitals, Prénom gets its capitals, phones are grouped by two, the
 *   e-mail is lower-cased; a phone already on file asks whether it is the
 *   same person, a homonym is pointed at; the address is looked up in the
 *   Base Adresse Nationale and the commune chosen from the postal code; the
 *   reminders' channel follows what can be reached.
 * - « Nouvel animal »: the owner picked among the active clients (or, when
 *   they are not one yet, the client window takes over with the animal).
 * - The animal form is shared: closed lists for species, breed and estimated
 *   age, a year → month → day birth date picker, a chip checked against the
 *   clinic's file.
 * The footer always says the first thing missing; the server checks it all
 * again. Reading a chip with a reader and the I-CAD pre-fill are not wired
 * yet: drawn, marked « bientôt ».
 */
import { creerDropdown } from 'kiveto/shell';

let ac = null;
let CFG = null;
let NC = null;
let NA = null;
let DP = null;
let dp = null;
let seq = 0;
const timers = {};

const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const norm = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const chif = (v, n) => String(v).replace(/\D/g, '').slice(0, n);
const fmtTel = (d) => d.replace(/(\d{2})(?=\d)/g, '$1 ');
const fmtPuce = (d) => d.replace(/(\d{3})(?=\d)/g, '$1 ');
const mobOk = (d) => /^0[67]\d{8}$/.test(d);
const fixeOk = (d) => /^0[1-59]\d{8}$/.test(d);
const mailOk = (m) => /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(m);
const SOON = 'bientôt disponible';

const I = {
  i: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  scan: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/></svg>',
  cal: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  paw: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="4" r="2"/><circle cx="18" cy="8" r="2"/><circle cx="20" cy="16" r="2"/><path d="M9 10a5 5 0 0 1 5 5v3.5a3.5 3.5 0 0 1-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 0 1 5.5 10Z"/></svg>',
};

const AUJ = () => { const [y, m, d] = CFG.today.split('-').map(Number); return new Date(y, m - 1, d); };
const ESP = () => CFG.species;
const RACES = () => CFG.breeds;

async function getJson(url, params) {
  const r = await fetch(`${url}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' }, signal: ac.signal });
  if (!r.ok) throw new Error(String(r.status));
  return r.json();
}
const debounce = (key, ms, fn) => { window.clearTimeout(timers[key]); timers[key] = window.setTimeout(fn, ms); };

export function init() {
  const cfgEl = $('repCreate');
  if (!cfgEl || !$('mNvCli')) return;
  try { CFG = JSON.parse(cfgEl.textContent); } catch { return; }
  ac = new AbortController();
  const { signal } = ac;

  dp = document.createElement('div');
  dp.className = 'nw-dp';
  document.body.appendChild(dp);
  dp.addEventListener('mousedown', (e) => e.preventDefault(), { signal });
  dp.addEventListener('click', onDpClick, { signal });

  document.querySelectorAll('.nw-veil').forEach((m) => {
    m.addEventListener('mousedown', (e) => { if (e.target === m) fermer(); }, { signal });
    m.querySelectorAll('[data-nwx]').forEach((b) => b.addEventListener('click', fermer, { signal }));
  });
  document.addEventListener('keydown', onKeydown, { signal, capture: true });
  document.addEventListener('mousedown', (e) => { if (DP && !e.target.closest('.nw-dp') && !e.target.closest('[data-dp]')) fermerDP(); }, { signal, capture: true });
  document.querySelectorAll('.nw-body').forEach((b) => b.addEventListener('scroll', fermerDP, { signal }));
  document.addEventListener('turbo:before-cache', fermer, { signal });

  bindClient(signal);
  bindAnimal(signal);

  $('btnNvCli')?.addEventListener('click', () => ouvrirNvCli(), { signal });
  $('btnNvAni')?.addEventListener('click', ouvrirNvAni, { signal });
  const p = new URLSearchParams(window.location.search).get('nouveau');
  if (p === 'client') ouvrirNvCli();
  if (p === 'animal') ouvrirNvAni();
}

export function cleanup() {
  ac?.abort();
  ac = null;
  Object.values(timers).forEach((t) => window.clearTimeout(t));
  dp?.remove();
  dp = null;
  DP = null;
  NC = null;
  NA = null;
}

function fermer() {
  document.querySelectorAll('.nw-veil.open').forEach((m) => m.classList.remove('open'));
  fermerDP();
}

function onKeydown(e) {
  if (e.key !== 'Escape' || !document.querySelector('.nw-veil.open')) return;
  e.stopPropagation();
  if (DP) { fermerDP(); return; }
  const dd = document.querySelector('.nw-veil.open .dd.open');
  if (dd) { dd.classList.remove('open'); return; }
  const sugs = document.querySelectorAll('.nw-veil.open .nw-sug.open');
  if (sugs.length) { sugs.forEach((s) => s.classList.remove('open')); return; }
  fermer();
}

// ═════════ Animal form (shared by both windows) ═════════

const aniVide = () => ({ nom: '', esp: '', race: '', raceQ: '', sexe: '', ster: false, naiss: '', inconnue: false, age: '', puce: '', deja: undefined });
function ageDe(d) {
  const m = /^(\d\d)\/(\d\d)\/(\d{4})$/.exec(d);
  if (!m) return null;
  const dt = new Date(+m[3], +m[2] - 1, +m[1]);
  if (dt.getDate() !== +m[1] || dt.getMonth() !== +m[2] - 1) return null;
  return dt;
}
function naissErr(a) {
  if (a.inconnue || !a.naiss || a.naiss.length < 10) return '';
  const d = ageDe(a.naiss), auj = AUJ();
  if (!d) return 'Date inexistante';
  if (d > auj) return 'Date dans le futur';
  if (auj.getFullYear() - d.getFullYear() > CFG.maxAge) return 'Plus de 35 ans : vérifiez l\'année';
  return '';
}
function aniErr(a) {
  if (!a.nom.trim()) return 'Nom de l\'animal requis';
  if (!a.esp) return 'Espèce requise';
  if (!a.race) return 'Race à choisir dans la liste';
  if (!a.sexe) return 'Sexe requis';
  if (!a.inconnue && a.naiss.length !== 10) return 'Date de naissance, ou « date inconnue »';
  if (a.inconnue && !a.age) return 'Âge estimé requis';
  if (naissErr(a)) return naissErr(a);
  if (a.puce && a.puce.length !== 15) return 'Puce incomplète (15 chiffres)';
  if (a.puce && a.deja === undefined) return 'Vérification de la puce…';
  if (a.puce && a.deja) return 'Puce déjà enregistrée';
  return '';
}
const aniVierge = (a) => !a.nom && !a.esp && !a.puce && !a.naiss && !a.sexe;
const aniPayload = (a) => ({ nom: a.nom, esp: a.esp, race: a.race, sexe: a.sexe, ster: a.ster, naiss: a.naiss, inconnue: a.inconnue, age: a.age, puce: a.puce });

function aniHtml(a, i, rm) {
  const e = ESP().find((x) => x.v === a.esp), ne = naissErr(a);
  const puceCls = a.puce.length === 15 && a.deja !== undefined ? (a.deja ? ' ko' : ' ok') : '';
  return `<div class="nw-ani" data-ai="${i}">
    <div class="nw-ani-h"><span class="e">${e ? e.e : '🐾'}</span><b>${esc(a.nom) || 'Animal ' + (i + 1)}</b>${rm ? `<button type="button" class="rm" data-arm="${i}">Retirer</button>` : ''}</div>
    <div class="nw-row pn"><div class="nw-f"><input class="nw-in" data-k="nom" placeholder="Nom *" maxlength="30" value="${esc(a.nom)}" aria-label="Nom de l'animal"></div>
      <div class="nw-f nw-puce"><input class="nw-in mono${puceCls}" data-k="puce" inputmode="numeric" placeholder="N° de puce · 15 chiffres" value="${fmtPuce(a.puce)}" aria-label="Numéro de puce"><button type="button" class="nw-scan lit" aria-disabled="true" data-tip="Lecture au lecteur de puce — ${SOON}">${I.scan}Lire</button></div></div>
    <div class="pz">${puceMsg(a)}</div>
    <div class="nw-row"><div class="nw-seg esp" data-seg="esp">${ESP().map((x) => `<button type="button" data-v="${x.v}" class="${a.esp === x.v ? 'on' : ''}" title="${x.l}">${x.e} ${x.l}</button>`).join('')}</div></div>
    <div class="nw-row c2"><div class="nw-f"><input class="nw-in${a.race ? ' ok' : ''}" data-k="raceQ" placeholder="${a.esp ? 'Race *' : 'Race · choisissez l\'espèce'}" ${a.esp ? '' : 'disabled'} value="${esc(a.race || a.raceQ)}" autocomplete="off" aria-label="Race"><div class="nw-sug" data-sug="race"></div></div>
      <div class="nw-seg" data-seg="sexe"><button type="button" data-v="M" class="${a.sexe === 'M' ? 'on' : ''}">Mâle</button><button type="button" data-v="F" class="${a.sexe === 'F' ? 'on' : ''}">Femelle</button></div></div>
    <div class="nw-row c2">${a.inconnue
      ? `<div class="nw-f"><div class="dd" data-age="${i}"></div><small><button type="button" class="nw-link mute" data-inc="0">Saisir la date de naissance</button></small></div>`
      : `<div class="nw-f"><input class="nw-in mono${ne ? ' ko' : a.naiss.length === 10 ? ' ok' : ''}" data-k="naiss" inputmode="numeric" placeholder="JJ/MM/AAAA *" value="${a.naiss}" aria-label="Date de naissance"><button type="button" class="nw-cal" data-dp="${i}" aria-label="Ouvrir le calendrier">${I.cal}</button><small class="${ne ? 'ko' : ''}">${ne || 'Date de naissance · <button type="button" class="nw-link mute" data-inc="1">inconnue ?</button>'}</small></div>`}<button type="button" class="nw-chk${a.ster ? ' on' : ''}" data-ster>${a.sexe === 'F' ? 'Stérilisée' : a.sexe === 'M' ? 'Castré' : 'Stérilisé·e'}</button></div>
  </div>`;
}

function puceMsg(a) {
  if (a.puce.length !== 15 || a.deja === undefined) return '';
  if (a.deja) return `<div class="nw-msg ko">${I.i}<div><b>Puce déjà enregistrée : ${esc(a.deja.nom)}</b> (${esc(a.deja.cli)}). Un animal ne peut avoir qu'une fiche.</div><div class="act"><a href="${esc(a.deja.url)}">Ouvrir sa fiche</a></div></div>`;
  if (!a.puce.startsWith('250')) return `<div class="nw-msg">${I.i}<div><b>Puce étrangère (code ${a.puce.slice(0, 3)})</b> · hors fichier I-CAD, saisissez les informations.</div></div>`;
  return `<div class="nw-msg">${I.i}<div>Puce inconnue de la clinique.</div><div class="act"><span class="nw-soon" data-tip="Interrogation du fichier I-CAD — ${SOON}">Pré-remplir depuis l'I-CAD · bientôt</span></div></div>`;
}

/** Is the chip already on one of the clinic's animals? */
async function verifierPuce(a, rerender, maj) {
  const puce = a.puce;
  try {
    const d = await getJson(CFG.urls.chip, { chip: puce });
    if (a.puce !== puce) return;
    a.deja = d.found ? { nom: d.animal.nom, cli: d.animal.cli, url: CFG.urls.animalUrl.replace('__ID__', d.animal.id) } : null;
  } catch (e) {
    if (e.name === 'AbortError') return;
    a.deja = null;
  }
  rerender();
  maj();
}

/** Binds the events of a list of animals held in `root`. */
function brancherAnis(root, L, rerender, maj, signal) {
  root.addEventListener('input', (e) => {
    const box = e.target.closest('[data-ai]');
    if (!box) return;
    const a = L()[+box.dataset.ai], k = e.target.dataset.k, t = e.target;
    if (!k || !a) return;
    if (k === 'puce') {
      const avant = a.puce;
      a.puce = chif(t.value, 15);
      t.value = fmtPuce(a.puce);
      if (avant !== a.puce) a.deja = undefined;
      if ((avant.length === 15) !== (a.puce.length === 15)) { rerender(); focusK(root, box.dataset.ai, 'puce'); }
      if (a.puce.length === 15 && avant !== a.puce) {
        const i = box.dataset.ai;
        verifierPuce(a, () => {
          const f = document.activeElement?.dataset?.k === 'puce' && document.activeElement.closest(`[data-ai="${i}"]`);
          rerender();
          if (f) focusK(root, i, 'puce');
        }, maj);
      }
    }
    if (k === 'nom') {
      t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').replace(/(^|[\s'-])\p{Ll}/gu, (x) => x.toUpperCase());
      a.nom = t.value.trim();
      box.querySelector('.nw-ani-h b').textContent = a.nom || 'Animal ' + (+box.dataset.ai + 1);
    }
    if (k === 'naiss') {
      const d = chif(t.value, 8);
      t.value = d.length > 4 ? d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4) : d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
      const avant = a.naiss;
      a.naiss = t.value;
      if (a.naiss.length === 10 || avant.length === 10) { rerender(); focusK(root, box.dataset.ai, 'naiss'); }
    }
    if (k === 'raceQ') { a.raceQ = t.value; a.race = ''; t.classList.remove('ok'); sugRace(box, a); }
    maj();
  }, { signal });
  root.addEventListener('focusin', (e) => {
    if (e.target.dataset.k !== 'raceQ') return;
    const box = e.target.closest('[data-ai]');
    sugRace(box, L()[+box.dataset.ai]);
  }, { signal });
  root.addEventListener('focusout', (e) => {
    if (e.target.dataset.k !== 'raceQ') return;
    const box = e.target.closest('[data-ai]'), a = L()[+box.dataset.ai];
    window.setTimeout(() => {
      box.querySelector('[data-sug="race"]')?.classList.remove('open');
      if (a && !a.race) {
        const ex = (RACES()[a.esp] || []).find((r) => norm(r) === norm(a.raceQ));
        if (ex) { a.race = ex; e.target.value = ex; e.target.classList.add('ok'); } else if (a.raceQ) e.target.classList.add('ko');
        maj();
      }
    }, 120);
  }, { signal });
  root.addEventListener('mousedown', (e) => {
    const r = e.target.closest('[data-race]');
    if (!r) return;
    e.preventDefault();
    const box = r.closest('[data-ai]'), a = L()[+box.dataset.ai];
    a.race = r.dataset.race;
    a.raceQ = '';
    const inp = box.querySelector('[data-k="raceQ"]');
    inp.value = a.race;
    inp.classList.remove('ko');
    inp.classList.add('ok');
    box.querySelector('[data-sug="race"]').classList.remove('open');
    maj();
  }, { signal });
  root.addEventListener('click', (e) => {
    const box = e.target.closest('[data-ai]');
    if (!box) return;
    const i = +box.dataset.ai, a = L()[i];
    const sg = e.target.closest('[data-seg] button');
    if (sg) {
      const k = sg.parentElement.dataset.seg;
      if (k === 'esp' && a.esp !== sg.dataset.v) { a.race = ''; a.raceQ = ''; }
      a[k] = sg.dataset.v;
      rerender();
      if (k === 'esp') focusK(root, i, 'raceQ');
      maj();
      return;
    }
    if (e.target.closest('[data-ster]')) { a.ster = !a.ster; e.target.closest('[data-ster]').classList.toggle('on', a.ster); maj(); return; }
    const inc = e.target.closest('[data-inc]');
    if (inc) { a.inconnue = inc.dataset.inc === '1'; if (!a.inconnue) a.age = ''; rerender(); maj(); return; }
    if (e.target.closest('[data-arm]')) { L().splice(i, 1); rerender(); maj(); return; }
    const dpb = e.target.closest('[data-dp]');
    if (dpb) { ouvrirDP(dpb.previousElementSibling, a.naiss, (v) => { a.naiss = v; rerender(); maj(); }); }
  }, { signal });
}
function focusK(root, i, k) {
  const el = root.querySelector(`[data-ai="${i}"] [data-k="${k}"]`);
  if (!el) return;
  el.focus();
  const v = el.value;
  if (el.setSelectionRange && el.type !== 'email') el.setSelectionRange(v.length, v.length);
}
function sugRace(box, a) {
  const s = box.querySelector('[data-sug="race"]');
  if (!a.esp) return;
  const q = norm(a.raceQ);
  const L = (RACES()[a.esp] || []).filter((r) => !q || norm(r).includes(q));
  s.innerHTML = L.length ? L.map((r) => `<button type="button" data-race="${esc(r)}">${esc(r)}</button>`).join('') : '<div class="vide">Race absente de la liste : choisissez « Croisé » ou la plus proche.</div>';
  s.classList.add('open');
}
function monterAges(root, list) {
  root.querySelectorAll('[data-age]').forEach((el) => {
    const i = +el.dataset.age;
    creerDropdown(el, [{ v: '', l: 'Âge estimé *' }, ...CFG.ages], list[i].age, (v) => { list[i].age = v; root.dispatchEvent(new Event('nwmaj', { bubbles: true })); });
  });
}

// ═════════ Birth date picker: year → month → day (a birth often goes back years) ═════════

const MOISL = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const MOISF = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

function ouvrirDP(input, val, onPick) {
  const d = ageDe(val), auj = AUJ();
  DP = { input, onPick, vue: d ? 'j' : 'a', y: d ? d.getFullYear() : auj.getFullYear(), m: d ? d.getMonth() : auj.getMonth(), sel: d, page: (d ? d.getFullYear() : auj.getFullYear()) - 11 };
  rendreDP();
  const r = input.getBoundingClientRect();
  dp.classList.add('open');
  const h = dp.offsetHeight, top = r.bottom + 6 + h > window.innerHeight - 8 ? r.top - h - 6 : r.bottom + 6;
  dp.style.left = Math.max(8, Math.min(r.left, window.innerWidth - dp.offsetWidth - 8)) + 'px';
  dp.style.top = top + 'px';
}
function fermerDP() { dp?.classList.remove('open'); DP = null; }
function rendreDP() {
  const D2 = DP, auj = AUJ(), Y = auj.getFullYear();
  const chev = (d) => `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
  let tete, corps;
  if (D2.vue === 'a') {
    tete = `<button type="button" data-dpn="-12" aria-label="Années précédentes">${chev('m15 18-6-6 6-6')}</button><span>Année de naissance</span><button type="button" data-dpn="12" aria-label="Années suivantes" ${D2.page + 12 > Y ? 'disabled' : ''}>${chev('m9 18 6-6-6-6')}</button>`;
    corps = `<div class="g3">${Array.from({ length: 12 }, (_, i) => D2.page + i).map((y) => `<button type="button" data-y="${y}" class="${D2.sel && D2.sel.getFullYear() === y ? 'on' : ''}" ${y > Y ? 'disabled' : ''}>${y}<small>${y <= Y ? (Y - y) + ' an' + (Y - y > 1 ? 's' : '') : ''}</small></button>`).join('')}</div>`;
  } else if (D2.vue === 'm') {
    tete = `<span></span><button type="button" class="tt" data-vue="a">${D2.y}</button><span></span>`;
    corps = `<div class="g3">${MOISL.map((m, i) => `<button type="button" data-mo="${i}" class="${D2.sel && D2.sel.getFullYear() === D2.y && D2.sel.getMonth() === i ? 'on' : ''}" ${D2.y === Y && i > auj.getMonth() ? 'disabled' : ''}>${m}</button>`).join('')}</div>`;
  } else {
    const prem = new Date(D2.y, D2.m, 1), dec = (prem.getDay() + 6) % 7, nb = new Date(D2.y, D2.m + 1, 0).getDate(), fut = new Date(D2.y, D2.m + 1, 1) > auj;
    tete = `<button type="button" data-dpm="-1" aria-label="Mois précédent">${chev('m15 18-6-6 6-6')}</button><span><button type="button" class="tt" data-vue="m">${MOISF[D2.m]}</button> <button type="button" class="tt" data-vue="a">${D2.y}</button></span><button type="button" data-dpm="1" aria-label="Mois suivant" ${fut ? 'disabled' : ''}>${chev('m9 18 6-6-6-6')}</button>`;
    corps = `<div class="g7">${['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((j) => `<i>${j}</i>`).join('')}${'<span></span>'.repeat(dec)}${Array.from({ length: nb }, (_, i) => i + 1).map((j) => {
      const dt = new Date(D2.y, D2.m, j);
      return `<button type="button" data-j="${j}" class="${D2.sel && +D2.sel === +dt ? 'on' : ''}${+dt === +auj ? ' auj' : ''}" ${dt > auj ? 'disabled' : ''}>${j}</button>`;
    }).join('')}</div>`;
  }
  dp.innerHTML = `<div class="hd">${tete}</div>${corps}<div class="ft">${D2.vue !== 'a' ? '<button type="button" data-vue="a">Changer d\'année</button>' : '<span>Puis le mois et le jour</span>'}</div>`;
}
function onDpClick(e) {
  const D2 = DP;
  if (!D2) return;
  const b = e.target.closest('button');
  if (!b || b.disabled) return;
  if (b.dataset.dpn) D2.page += +b.dataset.dpn;
  else if (b.dataset.y) { D2.y = +b.dataset.y; D2.vue = 'm'; }
  else if (b.dataset.mo) { D2.m = +b.dataset.mo; D2.vue = 'j'; }
  else if (b.dataset.dpm) { D2.m += +b.dataset.dpm; if (D2.m < 0) { D2.m = 11; D2.y--; } if (D2.m > 11) { D2.m = 0; D2.y++; } }
  else if (b.dataset.vue) { D2.vue = b.dataset.vue; if (D2.vue === 'a') D2.page = D2.y - 6; }
  else if (b.dataset.j) {
    const v = String(b.dataset.j).padStart(2, '0') + '/' + String(D2.m + 1).padStart(2, '0') + '/' + D2.y;
    const f = D2.onPick;
    fermerDP();
    f(v);
    return;
  }
  rendreDP();
}

// ═════════ Nouveau client ═════════

function ouvrirNvCli(depuis) {
  NC = { civ: '', nom: '', pre: '', mob: '', fixe: '', mail: '', rue: '', cpl: '', cp: '', ville: '', ban: false, rap: '', rapTouche: false, news: false, autre: false, dbl: null, homo: null, communes: undefined, adrS: [], envoi: false, anis: [depuis || aniVide()] };
  rendreNcCli();
  rendreNcAnis();
  majNc();
  fermer();
  $('mNvCli').classList.add('open');
  window.setTimeout(() => $('ncNom')?.focus(), 40);
}
function rendreNcCli() {
  const c = NC;
  $('ncCli').innerHTML = `<div class="nw-h">Client</div>
    <div class="nw-row civ"><div class="nw-seg" id="ncCiv"><button type="button" data-v="M." class="${c.civ === 'M.' ? 'on' : ''}">M.</button><button type="button" data-v="Mme" class="${c.civ === 'Mme' ? 'on' : ''}">Mme</button></div>
      <div class="nw-f"><input class="nw-in" id="ncNom" placeholder="Nom *" maxlength="40" value="${esc(c.nom)}" aria-label="Nom"></div><div class="nw-f"><input class="nw-in" id="ncPre" placeholder="Prénom *" maxlength="40" value="${esc(c.pre)}" aria-label="Prénom"></div></div>
    <div id="ncDblNom"></div>
    <div class="nw-row c2"><div class="nw-f"><input class="nw-in mono" id="ncMob" inputmode="numeric" placeholder="Mobile · 06 00 00 00 00" value="${fmtTel(c.mob)}" aria-label="Mobile"><small id="ncMobH"></small></div>
      <div class="nw-f"><input class="nw-in mono" id="ncFixe" inputmode="numeric" placeholder="Fixe · facultatif" value="${fmtTel(c.fixe)}" aria-label="Fixe"><small id="ncFixeH"></small></div></div>
    <div id="ncDbl"></div>
    <div class="nw-row"><div class="nw-f"><input class="nw-in" id="ncMail" type="email" placeholder="E-mail" maxlength="80" value="${esc(c.mail)}" aria-label="E-mail"><small id="ncMailH"></small></div></div>
    <div class="nw-h">Adresse <span class="opt">· facultative</span></div>
    <div id="ncAdr"></div>
    <div class="nw-h">Rappels de vaccins et de rendez-vous</div>
    <div class="nw-row"><div class="nw-seg" id="ncRap"><button type="button" data-v="sms">SMS</button><button type="button" data-v="mail">E-mail</button><button type="button" data-v="aucun">Aucun</button></div></div>
    <button type="button" class="nw-chk${c.news ? ' on' : ''}" id="ncNews">Accepte les actualités de la clinique</button>`;
  rendreAdr();
  majRap();
}
/** The communes of the postal code: a list, [] for an unknown code, null when the official list cannot be reached. */
const communes = () => (NC.cp.length === 5 && Array.isArray(NC.communes) ? NC.communes : []);
const communesHorsLigne = () => NC.cp.length === 5 && NC.communes === null;
function rendreAdr() {
  const c = NC, z = $('ncAdr'), V = communes(), lu = c.cp.length === 5 && c.communes !== undefined;
  const hl = communesHorsLigne();
  z.innerHTML = `<div class="nw-row"><div class="nw-f"><input class="nw-in${c.ban ? ' ok' : ''}" id="ncRue" placeholder="N° et voie" maxlength="80" autocomplete="off" value="${esc(c.rue)}" aria-label="N° et voie"><div class="nw-sug" id="ncAdrS"></div>
      <small id="ncRueH">${c.ban ? '✓ Adresse reconnue (Base Adresse Nationale)' : 'Tapez le début : les adresses connues sont proposées et remplissent les champs.'}</small></div></div>
    <div class="nw-row"><div class="nw-f"><input class="nw-in" id="ncCpl" placeholder="Complément · bâtiment, étage, appartement" maxlength="60" value="${esc(c.cpl)}" aria-label="Complément d'adresse"></div></div>
    <div class="nw-row cpv"><div class="nw-f"><input class="nw-in mono${lu && !hl ? (V.length ? ' ok' : ' ko') : ''}" id="ncCp" inputmode="numeric" placeholder="Code postal" value="${c.cp}" aria-label="Code postal"><small class="ko">${lu && !hl && !V.length ? 'Code postal inconnu' : ''}</small></div>
      <div class="nw-f">${hl ? `<input class="nw-in" id="ncVilleT" placeholder="Ville" maxlength="80" value="${esc(c.ville)}" aria-label="Ville"><small>Liste des communes indisponible : saisissez la ville.</small>` : '<div class="dd" id="ncVille"></div>'}</div></div>`;
  if (hl) return;
  const opts = V.length ? V.map((v) => ({ v, l: v })) : [{ v: '', l: c.cp.length === 5 ? (lu ? 'Aucune commune' : 'Recherche des communes…') : 'Ville · saisissez le code postal' }];
  if (V.length === 1) c.ville = V[0]; else if (!V.includes(c.ville)) c.ville = '';
  creerDropdown('ncVille', V.length > 1 ? [{ v: '', l: 'Choisissez la commune' }, ...opts] : opts, V.length ? c.ville : '', (v) => { c.ville = v; majNc(); });
  $('ncVille').classList.toggle('off', !V.length);
}
async function chargerCommunes() {
  const cp = NC.cp;
  try {
    const d = await getJson(CFG.urls.communes, { cp });
    if (!NC || NC.cp !== cp) return;
    NC.communes = d.communes;
  } catch (e) {
    if (e.name === 'AbortError' || !NC || NC.cp !== cp) return;
    NC.communes = null;
  }
  rendreAdr();
  majNc();
}
function majRap() {
  const c = NC, dispo = { sms: mobOk(c.mob), mail: mailOk(c.mail), aucun: true };
  if (!c.rapTouche) c.rap = dispo.sms ? 'sms' : dispo.mail ? 'mail' : 'aucun';
  if (!dispo[c.rap]) c.rap = 'aucun';
  document.querySelectorAll('#ncRap button').forEach((b) => {
    b.classList.toggle('on', b.dataset.v === c.rap);
    b.disabled = !dispo[b.dataset.v];
    b.style.opacity = dispo[b.dataset.v] ? '' : '.4';
  });
  // The news go by SMS or e-mail: without either there is nothing to agree to.
  const news = $('ncNews');
  if (news) {
    news.disabled = !dispo.sms && !dispo.mail;
    if (news.disabled && c.news) { c.news = false; news.classList.remove('on'); }
  }
}
function rendreNcAnis() {
  const z = $('ncAnis');
  z.innerHTML = '<div class="nw-h">Animaux <span class="opt">· vous pouvez aussi les ajouter plus tard</span></div>' + NC.anis.map((a, i) => aniHtml(a, i, true)).join('') + `<button type="button" class="nw-add" id="ncAdd">${I.paw}Ajouter ${NC.anis.length ? 'un autre animal' : 'un animal'}</button>`;
  monterAges(z, NC.anis);
}
/** A valid phone already on file: is it the same person? */
function chercherDoublon() {
  const c = NC, nums = [c.mob, c.fixe].filter((d) => mobOk(d) || fixeOk(d));
  const id = ++seq;
  if (!nums.length) { c.dbl = null; majNc(); return; }
  debounce('dbl', 150, async () => {
    try {
      const res = await Promise.all(nums.map((tel) => getJson(CFG.urls.phones, { tel })));
      if (!NC || id !== seq) return;
      NC.dbl = res.flatMap((r) => r.items)[0] || null;
      majNc();
    } catch (e) {
      if (e.name !== 'AbortError' && NC) { NC.dbl = null; majNc(); }
    }
  });
}
/** A client already bearing this first and last name. */
function chercherHomonyme() {
  const c = NC;
  if (c.nom.length < 2 || c.pre.length < 2) { c.homo = null; majNc(); return; }
  debounce('homo', 250, async () => {
    const nom = c.nom, pre = c.pre;
    try {
      const d = await getJson(CFG.urls.clients, { q: nom });
      if (!NC || NC.nom !== nom || NC.pre !== pre) return;
      NC.homo = d.items.find((x) => norm(x.n).endsWith(norm(pre + ' ' + nom))) || null;
      majNc();
    } catch (e) {
      if (e.name !== 'AbortError' && NC) { NC.homo = null; majNc(); }
    }
  });
}
function ncErr() {
  const c = NC;
  if (!c.civ) return 'Civilité requise';
  if (c.nom.length < 2) return 'Nom requis';
  if (c.pre.length < 2) return 'Prénom requis';
  if (!mobOk(c.mob) && !fixeOk(c.fixe)) return c.mob.length || c.fixe.length ? 'Téléphone invalide' : 'Un téléphone au moins';
  if (c.mob.length && !mobOk(c.mob)) return 'Mobile invalide';
  if (c.fixe.length && !fixeOk(c.fixe)) return 'Fixe invalide';
  if (c.mail && !mailOk(c.mail)) return 'E-mail invalide';
  if (c.dbl && !c.autre) return 'Téléphone déjà utilisé : vérifiez le doublon';
  if (c.rue || c.cp || c.cpl) {
    if (!c.rue) return 'Adresse : n° et voie manquants';
    if (c.cp.length !== 5) return 'Adresse : code postal invalide';
    if (c.communes === undefined) return 'Adresse : recherche des communes…';
    if (!communesHorsLigne() && !communes().length) return 'Adresse : code postal invalide';
    if (!c.ville) return communesHorsLigne() ? 'Adresse : saisissez la ville' : 'Adresse : choisissez la commune';
  }
  for (const a of c.anis) {
    if (aniVierge(a)) continue;
    const e = aniErr(a);
    if (e) return (a.nom || 'Animal') + ' : ' + e.charAt(0).toLowerCase() + e.slice(1);
  }
  return '';
}
function majNc() {
  if (!NC) return;
  const c = NC, err = ncErr(), n = c.anis.filter((a) => !aniVierge(a)).length;
  $('ncManque').textContent = err;
  $('ncOk').disabled = !!err || c.envoi;
  $('ncOk').textContent = c.envoi ? 'Création…' : n ? `Créer le client et ${n > 1 ? n + ' animaux' : 'son animal'}` : 'Créer le client';
  const mob = $('ncMob'), fx = $('ncFixe'), ml = $('ncMail');
  if (mob) {
    const ko = c.mob.length === 10 && !mobOk(c.mob);
    mob.classList.toggle('ko', ko); mob.classList.toggle('ok', mobOk(c.mob));
    $('ncMobH').textContent = ko ? 'Un mobile commence par 06 ou 07' : ''; $('ncMobH').className = 'ko';
    const kf = c.fixe.length === 10 && !fixeOk(c.fixe);
    fx.classList.toggle('ko', kf); fx.classList.toggle('ok', fixeOk(c.fixe));
    $('ncFixeH').textContent = kf ? 'Numéro de fixe invalide' : ''; $('ncFixeH').className = 'ko';
    const km = c.mail && !mailOk(c.mail) && document.activeElement !== ml;
    ml.classList.toggle('ko', !!km); ml.classList.toggle('ok', mailOk(c.mail));
    $('ncMailH').textContent = km ? 'Adresse e-mail incomplète' : ''; $('ncMailH').className = 'ko';
  }
  const d = c.dbl;
  $('ncDbl').innerHTML = d ? `<div class="nw-msg ${c.autre ? '' : 'warn'}">${I.i}<div><b>Ce numéro est déjà celui de ${esc(d.n)}</b>${c.autre ? ' · confirmé : autre personne du même foyer.' : '<br>Même personne ? Ouvrez sa fiche plutôt que d\'en créer une seconde.'}</div><div class="act">${c.autre ? '' : `<a href="${esc(d.url)}">Ouvrir sa fiche</a><button type="button" data-autre>Autre personne</button>`}</div></div>` : '';
  const h = c.homo;
  $('ncDblNom').innerHTML = h && !d ? `<div class="nw-msg">${I.i}<div>Un client porte déjà ce nom : <b>${esc(h.n)}</b>${h.tel ? ' · ' + esc(h.tel) : ''}${h.ville ? ', ' + esc(h.ville) : ''}.</div><div class="act"><a href="${esc(h.url)}">Voir</a></div></div>` : '';
  majRap();
}
function chercherAdresses(q) {
  const s2 = $('ncAdrS');
  if (norm(q).length < 3) { s2?.classList.remove('open'); return; }
  debounce('ban', 200, async () => {
    try {
      const d = await getJson(CFG.urls.addresses, { q });
      if (!NC || NC.rue !== q.replace(/\s+/g, ' ').trimStart()) return;
      NC.adrS = d.items;
      const s = $('ncAdrS');
      if (!s) return;
      if (d.items.length && document.activeElement?.id === 'ncRue') {
        s.innerHTML = d.items.map((x, i) => `<button type="button" data-ban="${i}">${esc(x.rue)}<small>${esc(x.cp)} ${esc(x.ville)}</small></button>`).join('');
        s.classList.add('open');
      } else s.classList.remove('open');
    } catch (e) {
      if (e.name !== 'AbortError') $('ncAdrS')?.classList.remove('open');
    }
  });
}
function bindClient(signal) {
  const ncCli = $('ncCli');
  ncCli.addEventListener('input', (e) => {
    const t = e.target, c = NC;
    if (!c) return;
    if (t.id === 'ncNom') { t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').toUpperCase(); c.nom = t.value.trim(); chercherHomonyme(); }
    if (t.id === 'ncPre') { t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').replace(/(^|[\s'-])\p{Ll}/gu, (x) => x.toUpperCase()); c.pre = t.value.trim(); chercherHomonyme(); }
    if (t.id === 'ncMob') { c.mob = chif(t.value, 10); t.value = fmtTel(c.mob); c.autre = false; chercherDoublon(); }
    if (t.id === 'ncFixe') { c.fixe = chif(t.value, 10); t.value = fmtTel(c.fixe); c.autre = false; chercherDoublon(); }
    if (t.id === 'ncMail') { t.value = t.value.replace(/\s/g, '').toLowerCase(); c.mail = t.value; }
    if (t.id === 'ncRue') {
      c.rue = t.value.replace(/\s+/g, ' ').trimStart();
      if (c.ban) { c.ban = false; t.classList.remove('ok'); $('ncRueH').textContent = 'Adresse modifiée à la main : vérifiez-la.'; }
      chercherAdresses(t.value);
    }
    if (t.id === 'ncCpl') c.cpl = t.value.trim();
    if (t.id === 'ncVilleT') c.ville = t.value.trim();
    if (t.id === 'ncCp') {
      const av = c.cp;
      c.cp = chif(t.value, 5);
      t.value = c.cp;
      if ((av.length === 5) !== (c.cp.length === 5) || (av !== c.cp && c.cp.length === 5)) {
        c.communes = undefined;
        rendreAdr();
        const f = $('ncCp');
        f.focus();
        f.setSelectionRange(c.cp.length, c.cp.length);
        if (c.cp.length === 5) chargerCommunes();
      }
    }
    majNc();
  }, { signal });
  ncCli.addEventListener('focusout', () => window.setTimeout(majNc, 0), { signal });
  ncCli.addEventListener('mousedown', (e) => {
    const b = e.target.closest('[data-ban]');
    if (!b) return;
    e.preventDefault();
    const x = NC.adrS[+b.dataset.ban];
    if (!x) return;
    const cpAvant = NC.cp;
    Object.assign(NC, { rue: x.rue, cp: x.cp, ville: x.ville, ban: true });
    if (cpAvant !== x.cp) NC.communes = undefined;
    rendreAdr();
    majNc();
    $('ncCpl').focus();
    if (NC.communes === undefined) chargerCommunes();
  }, { signal });
  ncCli.addEventListener('focusout', (e) => { if (e.target.id === 'ncRue') window.setTimeout(() => $('ncAdrS')?.classList.remove('open'), 150); }, { signal });
  ncCli.addEventListener('click', (e) => {
    const c = NC;
    if (!c) return;
    const cv = e.target.closest('#ncCiv button');
    if (cv) { c.civ = cv.dataset.v; document.querySelectorAll('#ncCiv button').forEach((x) => x.classList.toggle('on', x === cv)); majNc(); return; }
    const rp = e.target.closest('#ncRap button');
    if (rp && !rp.disabled) { c.rap = rp.dataset.v; c.rapTouche = true; majRap(); return; }
    if (e.target.closest('#ncNews') && !$('ncNews').disabled) { c.news = !c.news; $('ncNews').classList.toggle('on', c.news); return; }
    if (e.target.closest('[data-autre]')) { c.autre = true; majNc(); }
  }, { signal });
  const ncAnis = $('ncAnis');
  brancherAnis(ncAnis, () => NC.anis, () => rendreNcAnis(), () => majNc(), signal);
  ncAnis.addEventListener('nwmaj', majNc, { signal });
  ncAnis.addEventListener('click', (e) => {
    if (!e.target.closest('#ncAdd')) return;
    NC.anis.push(aniVide());
    rendreNcAnis();
    majNc();
    const n = NC.anis.length - 1;
    window.setTimeout(() => focusK(ncAnis, n, 'puce'), 20);
  }, { signal });
  $('ncOk').addEventListener('click', () => {
    if ($('ncOk').disabled || !NC) return;
    const c = NC;
    c.envoi = true;
    majNc();
    envoyer(CFG.urls.client, {
      civ: c.civ, nom: c.nom, pre: c.pre, mob: c.mob, fixe: c.fixe, mail: c.mail,
      rue: c.rue, cpl: c.cpl, cp: c.cp, ville: c.ville, rap: c.rap, news: c.news, autre: c.autre,
      anis: c.anis.filter((a) => !aniVierge(a)).map(aniPayload),
    });
  }, { signal });
}

/** Posts the window's state; Turbo follows the redirect back to the directory and its toast. */
function envoyer(url, payload) {
  const f = document.createElement('form');
  f.method = 'post';
  f.action = url;
  f.hidden = true;
  [['_token', CFG.token], ['payload', JSON.stringify(payload)]].forEach(([name, value]) => {
    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = name;
    i.value = value;
    f.appendChild(i);
  });
  document.body.appendChild(f);
  f.requestSubmit();
}

// ═════════ Nouvel animal ═════════

function ouvrirNvAni() {
  NA = { cli: null, q: '', res: [], a: aniVide(), envoi: false };
  rendreNa();
  fermer();
  $('mNvAni').classList.add('open');
  window.setTimeout(() => $('naQ')?.focus(), 40);
}
function rendreNa() {
  const b = $('naBody'), cl = NA.cli;
  b.innerHTML = `<div class="nw-h">Propriétaire</div>
    ${cl ? `<div class="nw-pro"><span class="av" style="background:#4f86c6">${esc(cl.ini)}</span><div><b>${esc(cl.n)}</b><small>${[cl.tel, cl.ville].filter(Boolean).map(esc).join(' · ')}${cl.ani.length ? ' · ' + cl.ani.map((a) => a.e + ' ' + esc(a.nom)).join(', ') : ''}</small></div><button type="button" data-chg>Changer</button></div>`
      : `<div class="nw-f"><input class="nw-in" id="naQ" placeholder="Nom ou téléphone du client…" autocomplete="off" value="${esc(NA.q)}" aria-label="Propriétaire"><div class="nw-sug" id="naS"></div></div>`}
    <div class="nw-h" style="margin-top:16px">Animal</div><div id="naAni">${aniHtml(NA.a, 0, false)}</div>`;
  monterAges($('naAni'), [NA.a]);
  majNa();
}
function majNa() {
  if (!NA) return;
  const err = !NA.cli ? 'Choisissez le propriétaire' : aniErr(NA.a);
  $('naManque').textContent = err;
  $('naOk').disabled = !!err || NA.envoi;
  $('naOk').textContent = NA.envoi ? 'Création…' : 'Créer l\'animal';
}
function rendreNaS() {
  const s = $('naS');
  if (!s) return;
  const L = NA.res;
  s.innerHTML = (L.length ? L.map((c, i) => `<button type="button" data-cl="${i}">${esc(c.n)}<small>${[c.tel, c.ville].filter(Boolean).map(esc).join(' · ')}</small></button>`).join('') : '<div class="vide">Aucun client trouvé.</div>')
    + '<div class="vide"><button type="button" data-nvcli>Pas encore client : créer le client avec cet animal</button></div>';
  s.classList.add('open');
}
function bindAnimal(signal) {
  const naBody = $('naBody');
  brancherAnis(naBody, () => [NA.a], () => { $('naAni').innerHTML = aniHtml(NA.a, 0, false); monterAges($('naAni'), [NA.a]); }, majNa, signal);
  naBody.addEventListener('nwmaj', majNa, { signal });
  naBody.addEventListener('input', (e) => {
    if (e.target.id !== 'naQ') return;
    NA.q = e.target.value;
    const q = NA.q, d = q.replace(/\D/g, '');
    if (norm(q).trim().length < 2 && d.length < 3) { $('naS').classList.remove('open'); return; }
    debounce('owner', 200, async () => {
      try {
        const r = await getJson(CFG.urls.clients, { q });
        if (!NA || NA.cli || NA.q !== q) return;
        NA.res = r.items;
        rendreNaS();
      } catch (err) {
        if (err.name !== 'AbortError') $('naS')?.classList.remove('open');
      }
    });
  }, { signal });
  naBody.addEventListener('focusout', (e) => { if (e.target.id === 'naQ') window.setTimeout(() => $('naS')?.classList.remove('open'), 150); }, { signal });
  naBody.addEventListener('mousedown', (e) => {
    const b = e.target.closest('[data-cl]');
    if (b) {
      e.preventDefault();
      NA.cli = NA.res[+b.dataset.cl];
      rendreNa();
      window.setTimeout(() => focusK(naBody, 0, 'puce'), 20);
      return;
    }
    if (e.target.closest('[data-nvcli]')) {
      e.preventDefault();
      const a = NA.a;
      ouvrirNvCli(a);
    }
  }, { signal });
  naBody.addEventListener('click', (e) => {
    if (!e.target.closest('[data-chg]')) return;
    NA.cli = null;
    NA.q = '';
    rendreNa();
    window.setTimeout(() => $('naQ')?.focus(), 20);
  }, { signal });
  $('naOk').addEventListener('click', () => {
    if ($('naOk').disabled || !NA) return;
    NA.envoi = true;
    majNa();
    envoyer(CFG.urls.animal, { cli: NA.cli.id, a: aniPayload(NA.a) });
  }, { signal });
}
