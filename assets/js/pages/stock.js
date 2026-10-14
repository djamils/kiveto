/**
 * Page module — Stocks (stock-v-topbar_3 mock-up). Loaded by the app.js
 * dispatcher on clinic_stock (« État du stock »), clinic_stock_movements
 * (« Mouvements ») and clinic_stock_alerts (« Alertes »): one page, opened on
 * the route's tab. Switching tabs happens in place and the address follows.
 *
 * The mock-up renders from its demo data; this module renders the same way
 * from the `data-payload` of StockPageBuilder — every article the clinic
 * follows, its lots, its weekly outflows and latest movements, the supplier
 * that sells it, the alerts of the day and the orders under way — and
 * searches, filters, sorts and pages it here. The movement trail is the one
 * list that grows without bound: it is asked page by page of
 * clinic_stock_movements_data.
 *
 * Quantities are in the article's unit; a supplier's packaging (`cont` units
 * per `cond`) is what is ordered and received, as in the mock-up's « 3 boîtes
 * + 6 cp ».
 *
 * Every change (threshold, place, withdrawal, loss, count, recall, receipt,
 * order lines, sending an order, stopping a follow-up) is posted in the
 * background to its single-action controller with the page's token, then
 * clinic_stock_data is asked again and the page redrawn. The trail is
 * append-only: « Annuler » writes a correction that brings each lot back to
 * what it held. What Kiveto does not track yet (opened vials, SMS to the
 * owners of a recalled lot, the scan simulator) is drawn but disabled.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

const PP_KEY = 'kiveto-stock-pp';
const NIL = '00000000-0000-7000-8000-000000000000';

let ac = null;
let mvTimer = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const fmtN = (n, d = 2) => Number(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
const fmtE = n => fmtN(n) + ' €';
const parseN = s => { const v = parseFloat(String(s ?? '').replace(/\s/g, '').replace(',', '.')); return isNaN(v) ? null : v; };
const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const D = s => new Date(s + 'T12:00:00');
const TODAY_ISO = iso(new Date());
const jours = s => Math.round((D(s) - D(TODAY_ISO)) / 864e5);
const fmtD = s => (s ? s.split('-').reverse().join('/') : '—');
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

const ICO = {
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  rm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>',
  tri: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
  undo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>',
  dots: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  cart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>',
  chev: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
};

/* ── Référentiels ── */
const EMPL = {
  PHARMACY: { l: 'Pharmacie', c: '#4338ca' }, FRIDGE: { l: 'Réfrigérateur', c: '#0891b2' }, NARCOTICS_SAFE: { l: 'Coffre stupéfiants', c: '#1e1b4b' },
  OPERATING_ROOM: { l: 'Bloc', c: '#dc2626' }, RECEPTION: { l: 'Accueil', c: '#16a34a' },
};
const SANS_EMPL = { l: 'Non rangé', c: '#cbd5e1' };
const TYPES = { med: 'Médicament', conso: 'Consommable', ali: 'Alimentation', mat: 'Matériel' };
/* Units of the shared registry, singular and plural as the shelves say them. */
const UNITES = {
  TABLET: ['cp', 'cp'], CAPSULE: ['gélule', 'gélules'], VIAL: ['flacon', 'flacons'], BOTTLE: ['flacon', 'flacons'], UNIT: ['unité', 'unités'],
  BOX: ['boîte', 'boîtes'], BAG: ['sac', 'sacs'], PIPETTE: ['pipette', 'pipettes'], DOSE: ['dose', 'doses'], SACHET: ['sachet', 'sachets'],
  AMPULE: ['ampoule', 'ampoules'], ML: ['mL', 'mL'], L: ['L', 'L'], G: ['g', 'g'], KG: ['kg', 'kg'],
};
const pl = (u, n) => { const x = UNITES[u] || [String(u || '').toLowerCase(), String(u || '').toLowerCase()]; return n > 1 ? x[1] : x[0]; };
const MOTIFS = [
  ['SALE_RETAIL', 'Vente au comptoir'], ['CONSUMPTION_INTERNAL', 'Utilisé hors consultation'], ['LOSS_EXPIRY', 'Perte — péremption'],
  ['LOSS_BREAKAGE', 'Perte — casse'], ['LOSS_THEFT', 'Perte — vol'], ['RETURN_TO_SUPPLIER', 'Retour fournisseur'],
];
const GRAV = { crit: ['Critique', '#dc2626'], surv: ['À surveiller', '#d97706'], info: ['Pour information', '#8b5cf6'] };
const STATUT = { jamais: ['jamais', 'Jamais réceptionné'], rup: ['rup', 'Rupture'], bas: ['bas', 'Sous le seuil'] };
const TAB_URL = { etat: 'tabEtat', mvts: 'tabMvts', alertes: 'tabAlertes' };
const SOON = 'Pas encore disponible dans Kiveto';

export function init() {
  const root = document.querySelector('.stock[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const czone = $('czone'), fiBody = $('fiBody');
  const pick = $('pick'), pickQ = $('pickQ'), pickL = $('pickL');

  /* ═════════ Données ═════════ */

  let ART = [], ALERTES = [], DRAFTS = [], SENT = [], SUPPLIERS = [], DERNIER_INV = null;

  function charger(d) {
    ART = d.items.map(a => ({ ...a, cont: a.fourn ? a.fourn.cont : 1, cond: a.fourn ? a.fourn.cond : a.u, achat: a.fourn ? a.fourn.prix / 100 : 0 }));
    ALERTES = d.alerts;
    DRAFTS = d.orders.draft;
    SENT = d.orders.sent;
    SUPPLIERS = d.suppliers;
    DERNIER_INV = d.dernierInv;
  }
  charger(P);

  const art = id => ART.find(a => a.art === id);
  const suivis = () => ART.filter(a => !a.arch);
  const lotDe = (a, id) => a.lots.find(l => l.id === id);
  const actifs = a => a.lots.filter(l => l.statut === 'actif');
  const perimes = a => a.lots.filter(l => l.q > 0 && (l.statut === 'perime' || (l.statut === 'actif' && jours(l.per) < 0)));
  const dispo = a => actifs(a).filter(l => jours(l.per) >= 0).reduce((s, l) => s + l.q, 0);
  const fefo = a => actifs(a).filter(l => l.q > 0 && jours(l.per) >= 0).sort((x, y) => x.per.localeCompare(y.per));
  const prochaine = a => fefo(a)[0];
  const couverture = a => (a.conso ? dispo(a) / (a.conso / 30) : null);
  const valeur = a => actifs(a).reduce((s, l) => s + l.q, 0) / a.cont * a.achat;
  const empl = a => EMPL[a.empl] || SANS_EMPL;
  const froid = a => a.empl === 'FRIDGE';
  function fmtQ(a, n, court) {
    n = Math.round(n * 100) / 100;
    if (a.cont > 1) {
      const b = Math.floor(n / a.cont), r = Math.round((n - b * a.cont) * 100) / 100;
      if (court) return `${fmtN(n, n % 1 ? 1 : 0)} ${pl(a.u, n)}`;
      if (!b) return `${fmtN(r, r % 1 ? 1 : 0)} ${pl(a.u, r)}`;
      return `${b} ${pl(a.cond, b)}${r ? ` + ${fmtN(r, r % 1 ? 1 : 0)} ${pl(a.u, r)}` : ''}`;
    }
    return `${fmtN(n, n % 1 ? 1 : 0)} ${pl(a.u, n)}`;
  }
  function statut(a) {
    if (!a.lots.length) return 'jamais';
    const d = dispo(a);
    if (d <= 0) return 'rup';
    if (d <= a.seuil) return 'bas';
    return 'ok';
  }
  /* What is being ordered: the lines of the drafts, by article. */
  const cmdDe = id => { for (const o of DRAFTS) { const l = o.lignes.find(x => x.art === id); if (l) return { o, l }; } return null; };
  const nCmd = () => DRAFTS.reduce((s, o) => s + o.lignes.length, 0);
  const suggestion = a => Math.max(1, Math.ceil((a.seuil * 2 - dispo(a)) / a.cont));

  /* ═════════ État ═════════ */

  const ST = {
    tab: P.tab, q: '', sel: new Set(), page: { etat: 1, mvts: 1 }, pp: 25, sort: { k: 'lib', d: 1 },
    f: { etat: { etat: '', type: '', empl: '' }, mvts: { t: '', per: '30', reg: false } }, fiche: null,
    mv: { rows: [], total: 0, cle: null }, repris: new Set(),
  };
  try { const v = +localStorage.getItem(PP_KEY); if ([25, 50, 100].includes(v)) ST.pp = v; } catch (_) { /* private mode */ }

  /* ═════════ Serveur ═════════ */

  async function envoyer(href, body) {
    body.append('_token', P.token);
    const r = await fetch(href, { method: 'POST', body, headers: { Accept: 'application/json' }, signal });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'L\'opération n\'a pas pu être enregistrée');
    return d;
  }
  function poster(href, data = {}, listes = {}) {
    const body = new FormData();
    Object.entries(data).forEach(([k, v]) => { if (v !== undefined) body.append(k, v === null ? '' : String(v)); });
    Object.entries(listes).forEach(([k, rows]) => rows.forEach((row, i) => Object.entries(row).forEach(([f, v]) => body.append(`${k}[${i}][${f}]`, v == null ? '' : String(v)))));
    return envoyer(href, body);
  }
  async function recharger() {
    const r = await fetch(P.urls.data, { headers: { Accept: 'application/json' }, signal });
    if (!r.ok) throw new Error('Le stock n\'a pas pu être rechargé');
    charger(await r.json());
  }
  const url = (k, id, id2) => { let u = P.urls[k].replace(NIL, id); if (id2) u = u.replace(NIL, id2); return u; };
  const echec = e => { if (e.name !== 'AbortError') showToast(e.message, 'danger'); };
  async function apres() {
    await recharger();
    ST.mv.cle = null;
    rendre();
    if (ST.fiche) rendreFiche();
    if ($('cmdP').classList.contains('open')) rendreCmd();
  }
  /* « Annuler » of a movement: every lot back to what it held. */
  const restaurer = undo => async () => {
    try { await poster(P.urls.restore, {}, { lots: undo }); await apres(); showToast('Mouvement annulé — correction inscrite au registre'); } catch (e) { echec(e); }
  };

  /* ═════════ Alertes ═════════ */

  function alertes() {
    return ALERTES.map(x => {
      const a = art(x.art);
      if (!a) return null;
      const l = x.lot ? lotDe(a, x.lot) : null;
      const c = couverture(a);
      if (x.k === 'perime') return { ...x, a, l, ico: ICO.clock, t: 'Lot périmé encore en stock', s: `${x.n} · périmé depuis le ${fmtD(x.per)} · ${fmtQ(a, x.q)} à sortir`, act: 'perte' };
      if (x.k === 'rup') return { ...x, a, ico: ICO.box, t: 'Rupture de stock', s: `Seuil d'alerte : ${fmtQ(a, a.seuil)} · consommation ≈ ${fmtQ(a, a.conso, 1)} par mois`, act: 'cmd' };
      if (x.k === 'bas') return { ...x, a, ico: ICO.box, t: 'Sous le seuil', s: `${fmtQ(a, dispo(a))} en stock pour un seuil de ${fmtQ(a, a.seuil)}${c != null ? ` · ≈ ${Math.round(c)} jours de consommation` : ''}`, act: 'cmd' };
      if (x.k === 'perp') return { ...x, a, l, ico: ICO.clock, t: `Péremption dans ${x.j} jours`, s: `${x.n} · périme le ${fmtD(x.per)} · ${fmtQ(a, x.q)}${a.conso && x.q / (a.conso / 30) > x.j ? ' · ne sera pas écoulé à ce rythme' : ' · sera écoulé en premier'}`, act: 'voir' };
      const r = l && l.rappel ? l.rappel : {};
      return { ...x, a, l, ico: ICO.undo, t: 'Lot rappelé', s: `${x.n}${r.motif ? ' · ' + r.motif : ''}${r.d ? ' · le ' + fmtD(r.d) : ''}`, act: 'voir' };
    }).filter(Boolean);
  }

  /* ═════════ Filtres ═════════ */

  const FILTRES = {
    etat: [
      { k: 'etat', l: 'État', o: [{ v: 'rup', l: 'En rupture' }, { v: 'bas', l: 'Sous le seuil' }, { v: 'perp', l: 'Péremption < 60 j' }, { v: 'perime', l: 'Lot périmé' }, { v: 'rappel', l: 'Lot rappelé' }, { v: 'jamais', l: 'Jamais réceptionné' }, { v: 'arch', l: 'Suivi arrêté' }] },
      { k: 'type', l: 'Type', o: Object.entries(TYPES).map(([v, l]) => ({ v, l })) },
      { k: 'empl', l: 'Emplacement', o: Object.entries(EMPL).map(([v, e]) => ({ v, l: e.l, c: e.c })) },
    ],
    mvts: [
      { k: 't', l: 'Type', o: [{ v: 'in', l: 'Entrées' }, { v: 'out', l: 'Sorties' }, { v: 'adj', l: 'Ajustements' }] },
    ],
  };
  function testEtat(a, v) {
    if (v === 'arch') return a.arch;
    if (a.arch) return false;
    if (v === 'rup' || v === 'bas' || v === 'jamais') return statut(a) === v;
    if (v === 'perp') return fefo(a).some(l => jours(l.per) <= 60);
    if (v === 'perime') return perimes(a).length > 0;
    if (v === 'rappel') return a.lots.some(l => l.statut === 'rappel');
    return true;
  }
  function rendreFiltres() {
    const box = $('fdd');
    box.innerHTML = '';
    const F = ST.f[ST.tab];
    FILTRES[ST.tab].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (F[fd.k] ? ' actif' : '');
      box.appendChild(el);
      creerDropdown(el, [{ v: '', l: 'Tous' }, ...fd.o], F[fd.k], v => { F[fd.k] = v; ST.page[ST.tab] = 1; ST.sel.clear(); rendre(); });
      if (!F[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
    });
    if (ST.tab === 'mvts') {
      const seg = document.createElement('div');
      seg.className = 'seg';
      seg.innerHTML = [['7', '7 jours'], ['30', '30 jours'], ['90', '90 jours'], ['tout', 'Tout']].map(([v, l]) => `<button type="button" data-v="${v}" class="${F.per === v ? 'on' : ''}">${l}</button>`).join('');
      seg.addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; F.per = b.dataset.v; ST.page.mvts = 1; rendre(); });
      box.appendChild(seg);
      const r = document.createElement('button');
      r.type = 'button';
      r.className = 'chipf' + (F.reg ? ' on' : '');
      r.id = 'chipReg';
      r.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Registre des stupéfiants';
      r.addEventListener('click', () => { F.reg = !F.reg; if (F.reg) F.per = 'tout'; ST.page.mvts = 1; rendre(); });
      box.appendChild(r);
    }
  }

  /* ═════════ Colonnes ═════════ */

  const emplHtml = a => { const e = empl(a); return `<span class="empl"><i style="background:${e.c}"></i>${e.l}</span>`; };
  const COLS = {
    etat: [
      { k: 'lib', l: 'Article', sort: a => a.lib, html: a => {
        const x = STATUT[statut(a)], p = [], c = cmdDe(a.art);
        if (a.arch) p.push('<span class="puce jamais">Suivi arrêté</span>');
        else if (x) p.push(`<span class="puce ${x[0]}">${x[1]}</span>`);
        if (perimes(a).length) p.push('<span class="puce perime">Lot périmé</span>');
        if (c) p.push(`<span class="puce cmd">Commandé · ${fmtN(c.l.n, 0)}</span>`);
        return `<div class="cid"><b>${esc(a.lib)}${p.join('')}</b><span><span class="code">${esc(a.code)}</span> · ${emplHtml(a)}</span></div>`;
      } },
      { k: 'type', l: 'Type', sort: a => TYPES[a.type], html: a => `<div class="flags"><span class="puce ty-${a.type}">${TYPES[a.type]}</span>${a.stup ? '<span class="puce stup">Stupéfiant</span>' : ''}${froid(a) ? '<span class="puce froid">2–8 °C</span>' : ''}</div>` },
      { k: 'q', l: 'En stock', cls: 'num', sort: a => dispo(a) / Math.max(1, a.seuil), html: a => {
        if (!a.lots.length) return '<div class="qte"><b style="color:var(--encre-35);font-weight:500">—</b></div>';
        const d = dispo(a), s = statut(a), pct = a.seuil ? Math.min(100, d / (a.seuil * 2.5) * 100) : 100;
        return `<div class="qte ${s === 'rup' ? 'rup' : s === 'bas' ? 'bas' : ''}" data-tip="Seuil d'alerte : ${esc(fmtQ(a, a.seuil))}"><b>${fmtQ(a, d)}</b>${a.cont > 1 ? `<span>${fmtN(d, d % 1 ? 1 : 0)} ${pl(a.u, d)}</span>` : ''}<div class="jauge"><i style="width:${pct}%"></i></div></div>`;
      } },
      { k: 'couv', l: 'Couverture', cls: 'num', sort: a => couverture(a) ?? 1e9, html: a => {
        const c = couverture(a);
        if (c == null || !a.lots.length) return '<span class="couv na">—</span>';
        return c <= 0 ? '<span class="couv ko">Épuisé</span>' : `<span class="couv ${c < 7 ? 'ko' : c < 15 ? 'moy' : ''}" data-tip="Au rythme des 30 derniers jours">≈ ${Math.round(c)} j</span>`;
      } },
      { k: 'per', l: 'Prochaine péremption', sort: a => prochaine(a)?.per || '9999', html: a => {
        const l = prochaine(a);
        if (!l) return '<div class="per"><b style="color:var(--encre-35)">—</b></div>';
        const j = jours(l.per);
        return `<div class="per ${j <= 60 ? 'proche' : ''}"><b>${fmtD(l.per)}</b><span>${j <= 60 ? `dans ${j} j · ` : ''}${esc(l.n)}</span></div>`;
      } },
      { k: 'val', l: 'Valeur HT', cls: 'num', xo: 1, sort: valeur, html: a => `<div class="cnum${a.lots.length && a.fourn ? '' : ' mute'}"><b>${a.lots.length && a.fourn ? fmtE(valeur(a)) : '—'}</b></div>` },
    ],
    mvts: [
      { k: 'd', l: 'Date', html: m => `<div class="mdate"><b>${+m.d.slice(8)} ${MOIS[+m.d.slice(5, 7) - 1]}</b><span>${m.h}</span></div>` },
      { k: 'a', l: 'Article', html: m => { const a = art(m.a); return `<div class="cid"><b>${esc(a ? a.lib : 'Article')}</b><span><span class="code">${esc(a ? a.code : '')}</span>${m.lot ? ' · ' + esc(m.lot) : ''}</span></div>`; } },
      { k: 'motif', l: 'Mouvement', html: m => `<div class="flags"><span class="puce t-${m.t}">${{ in: 'Entrée', out: 'Sortie', adj: 'Ajustement' }[m.t]}</span><span style="font-size:.8rem;color:var(--encre-60);line-height:20px">${esc(m.motif)}</span></div>` },
      { k: 'q', l: 'Quantité', cls: 'num', html: m => { const a = art(m.a) || { cont: 1, u: '' }; return `<span class="sens ${m.t}">${m.q > 0 ? '+' : '−'}${fmtQ(a, Math.abs(m.q), 1)}</span>`; } },
      { k: 'solde', l: 'Solde', cls: 'num', reg: 1, html: m => { const a = art(m.a) || { cont: 1, u: '' }; return `<span class="solde">${m.solde == null ? '—' : fmtQ(a, m.solde, 1)}</span>`; } },
      { k: 'ref', l: 'Référence', html: m => `${m.cons ? `<a class="ref" href="${esc(P.urls.directory)}?q=${encodeURIComponent(m.ref.replace(/\s*\(.*$/, ''))}" data-tip="Retrouver l'animal dans le répertoire">${esc(m.ref)}</a>` : `<span class="ref">${esc(m.ref || '—')}</span>`}${m.note ? `<span style="display:block;font-size:.7rem;color:var(--encre-60);margin-top:2px;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(m.note)}</span>` : ''}` },
      { k: 'par', l: 'Par', html: m => (m.par ? `<span class="av" style="background:${esc(m.par.c)}" data-tip="${esc(m.par.n)}">${esc(m.par.ini)}</span>` : '<span class="av" style="background:var(--encre-35)" data-tip="Automatique">—</span>') },
    ],
  };

  /* ═════════ Rendu ═════════ */

  function visibles() {
    const q = norm(ST.q), F = ST.f.etat;
    const Lx = ART.filter(a => (F.etat ? testEtat(a, F.etat) : !a.arch) && (!F.type || a.type === F.type) && (!F.empl || a.empl === F.empl)
      && (!q || norm([a.code, a.lib, a.gtin, ...a.lots.map(l => l.n)].join(' ')).includes(q)));
    const c = COLS.etat.find(x => x.k === ST.sort.k), d = ST.sort.d;
    if (c && c.sort) Lx.sort((x, y) => { const u = c.sort(x), v = c.sort(y); return (typeof u === 'string' ? u.localeCompare(v, 'fr') : u - v) * d; });
    return Lx;
  }
  function pageDe(Lt) { const n = Math.max(1, Math.ceil(Lt.length / ST.pp)); ST.page.etat = Math.min(Math.max(1, ST.page.etat), n); return Lt.slice((ST.page.etat - 1) * ST.pp, ST.page.etat * ST.pp); }
  function rendrePager(tot, lbl) {
    const pg = ST.page[ST.tab], n = Math.max(1, Math.ceil(tot / ST.pp)), a = tot ? (pg - 1) * ST.pp + 1 : 0, b = Math.min(tot, pg * ST.pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> ${tot > 1 ? lbl[1] : lbl[0]}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${[25, 50, 100].map(v => `<button type="button" data-pp="${v}" class="${v === ST.pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }
  $('cfoot').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || b.disabled) return;
    if (b.dataset.pp) { ST.pp = +b.dataset.pp; ST.page[ST.tab] = 1; try { localStorage.setItem(PP_KEY, ST.pp); } catch (_) { /* private mode */ } } else ST.page[ST.tab] = +b.dataset.pg;
    rendre();
    root.querySelector('.cscroll').scrollTop = 0;
  }, { signal });
  function entete() {
    const AL = alertes(), crit = AL.filter(x => x.g === 'crit').length;
    $('nEtat').textContent = suivis().length;
    $('nAl').textContent = AL.length;
    $('nAl').classList.toggle('al', crit > 0);
    const val = suivis().reduce((s, a) => s + valeur(a), 0);
    $('phSub').textContent = `${fmtN(val, 0)} € HT en stock · ${DERNIER_INV ? 'dernier inventaire le ' + fmtD(DERNIER_INV) : 'aucun inventaire enregistré'}`;
    $('cmdN').textContent = nCmd() || '';
  }
  function rendre() {
    entete();
    const al = ST.tab === 'alertes';
    czone.style.display = al ? 'none' : '';
    $('cfil').style.display = al ? 'none' : '';
    $('azone').classList.toggle('on', al);
    $('regbar').classList.toggle('on', ST.tab === 'mvts' && ST.f.mvts.reg);
    if (al) { rendreAlertes(); majBulk(); return; }
    $('q').placeholder = ST.tab === 'etat' ? 'Article, code, n° de lot…' : 'Article, lot, animal, référence…';
    if (ST.tab === 'mvts') { rendreMvts(); rendreFiltres(); majBulk(); return; }
    const Lt = visibles(), Lp = pageDe(Lt), cols = COLS.etat;
    const allSel = Lp.length && Lp.every(a => ST.sel.has(a.art)), some = Lp.some(a => ST.sel.has(a.art));
    $('thead').innerHTML = `<tr><th class="selc"><input type="checkbox" class="ck${some && !allSel ? ' mix' : ''}" id="ckAll" ${allSel ? 'checked' : ''} aria-label="Tout sélectionner"></th>${cols.map(c =>
      `<th class="${c.cls || ''}${c.xo ? ' xo' : ''}${c.sort ? ' sortable' : ''}${ST.sort.k === c.k ? ' son' : ''}" data-k="${c.k}">${c.l}${ST.sort.k === c.k ? `<span class="sarr">${ST.sort.d > 0 ? '▲' : '▼'}</span>` : ''}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = Lp.map(x => `<tr data-c="${x.art}" class="${ST.sel.has(x.art) ? 'sel' : ''}${ST.fiche === x.art ? ' cur' : ''}${x.arch ? ' arch' : ''}"><td class="selc"><input type="checkbox" class="ck" ${ST.sel.has(x.art) ? 'checked' : ''} aria-label="Sélectionner"></td>${cols.map(c => `<td class="${c.cls || ''}${c.xo ? ' xo' : ''}">${c.html(x)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = Lt.length ? '' : (ART.length ? '<div class="cempty"><b>Aucun résultat</b>Modifiez la recherche ou les filtres.</div>' : '<div class="cempty"><b>Aucun article suivi en stock</b>Activez le suivi du stock d\'un article dans le catalogue, puis réceptionnez sa première livraison.</div>');
    rendrePager(Lt.length, ['article', 'articles']);
    rendreFiltres();
    majBulk();
  }

  /* The trail is asked page by page; the table shows what came back. */
  function cleMvts() { const F = ST.f.mvts; return JSON.stringify([F.t, F.per, F.reg, ST.q, ST.page.mvts, ST.pp]); }
  function rendreMvts() {
    const F = ST.f.mvts, cols = COLS.mvts.filter(c => !c.reg || F.reg);
    $('thead').innerHTML = `<tr>${cols.map(c => `<th class="${c.cls || ''}" data-k="${c.k}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = ST.mv.rows.map(m => `<tr data-c="${m.a}" class="${ST.fiche === m.a ? 'cur' : ''}">${cols.map(c => `<td class="${c.cls || ''}">${c.html(m)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = ST.mv.cle === null || ST.mv.rows.length ? '' : '<div class="cempty"><b>Aucun mouvement</b>Modifiez la recherche, le type ou la période.</div>';
    rendrePager(ST.mv.total, ['mouvement', 'mouvements']);
    const cle = cleMvts();
    if (cle !== ST.mv.cle) chargerMvts(cle);
  }
  async function chargerMvts(cle) {
    clearTimeout(mvTimer);
    mvTimer = setTimeout(async () => {
      const F = ST.f.mvts;
      const qs = new URLSearchParams({ t: F.t, per: F.per, reg: F.reg ? '1' : '', q: ST.q, page: ST.page.mvts, pp: ST.pp });
      try {
        const r = await fetch(`${P.urls.movements}?${qs}`, { headers: { Accept: 'application/json' }, signal });
        if (!r.ok) throw new Error('Les mouvements n\'ont pas pu être chargés');
        const d = await r.json();
        ST.mv = { rows: d.rows, total: d.total, cle };
        if (ST.tab === 'mvts') rendre();
      } catch (e) { echec(e); }
    }, ST.mv.cle === null ? 0 : 120);
  }

  $('thead').addEventListener('click', e => {
    if (ST.tab !== 'etat') return;
    if (e.target.id === 'ckAll') { const Lp = pageDe(visibles()), all = Lp.every(a => ST.sel.has(a.art)); Lp.forEach(a => (all ? ST.sel.delete(a.art) : ST.sel.add(a.art))); rendre(); return; }
    const th = e.target.closest('th.sortable');
    if (!th) return;
    if (ST.sort.k === th.dataset.k) ST.sort.d = -ST.sort.d; else { ST.sort.k = th.dataset.k; ST.sort.d = 1; }
    ST.page.etat = 1;
    rendre();
  }, { signal });
  $('tbody').addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (!tr || e.target.closest('a')) return;
    if (ST.tab === 'etat' && e.target.closest('.selc')) { const c = tr.dataset.c; ST.sel.has(c) ? ST.sel.delete(c) : ST.sel.add(c); rendre(); return; }
    ouvrirFiche(tr.dataset.c);
  }, { signal });
  $('tabs').addEventListener('click', e => { const b = e.target.closest('button'); if (b) allerOnglet(b.dataset.t); }, { signal });
  function allerOnglet(t, q) {
    ST.tab = t;
    ST.sel.clear();
    ST.q = q || '';
    $('q').value = ST.q;
    if (t === 'alertes') fermerFiche();
    root.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x.dataset.t === t));
    ST.page[t] = 1;
    try { history.replaceState(history.state, '', P.urls[TAB_URL[t]]); } catch (_) { /* sandboxed */ }
    rendre();
  }
  $('q').addEventListener('input', e => { ST.q = e.target.value; ST.page[ST.tab] = 1; rendre(); }, { signal });

  /* ═════════ Alertes ═════════ */

  function actionAlerte(x, i) {
    if (x.act === 'cmd') {
      const c = cmdDe(x.a.art);
      if (c) return `<button type="button" class="btn ok" data-al="${i}" data-a="voircmd">✓ Commandé · ${fmtN(c.l.n, 0)} ${pl(x.a.cond, c.l.n)}</button>`;
      if (!x.a.fourn) return '<button type="button" class="btn primaire" disabled data-tip="Aucun fournisseur de la clinique ne référence cet article">Commander</button>';
      return `<button type="button" class="btn primaire" data-al="${i}" data-a="cmd">Commander ${suggestion(x.a)} ${pl(x.a.cond, suggestion(x.a))}</button>`;
    }
    if (x.act === 'perte') return `<button type="button" class="btn primaire" data-al="${i}" data-a="perte">Sortir en perte</button>`;
    return '';
  }
  function rendreAlertes() {
    const AL = alertes(), z = $('azone');
    if (!AL.length) { z.innerHTML = '<div class="avide"><b>Aucune alerte</b>Stock au-dessus des seuils, aucun lot proche de la péremption.</div>'; return; }
    z.innerHTML = Object.entries(GRAV).map(([g, [l, c]]) => {
      const G = AL.map((x, i) => ({ x, i })).filter(o => o.x.g === g);
      if (!G.length) return '';
      return `<div class="agrp"><h3><i style="background:${c}"></i>${l} <span>${G.length}</span></h3>${G.map(({ x, i }) => `<div class="acard ${g}"><div class="ai">${x.ico}</div>
        <div class="at"><b>${esc(x.t)} · ${esc(x.a.lib)}</b><span>${esc(x.s)}</span><small>${esc(x.a.code)} · ${empl(x.a).l}</small></div>
        <div class="aa">${actionAlerte(x, i)}<button type="button" class="btn" data-al="${i}" data-a="voir">Voir</button></div></div>`).join('')}</div>`;
    }).join('');
    z._al = AL;
  }
  $('azone').addEventListener('click', e => {
    const b = e.target.closest('[data-al]');
    if (!b || b.disabled) return;
    const x = $('azone')._al[+b.dataset.al], a = x.a;
    if (b.dataset.a === 'voir') { allerOnglet('etat'); ouvrirFiche(a.art); return; }
    if (b.dataset.a === 'voircmd') { ouvrirCmd(); return; }
    if (b.dataset.a === 'cmd') { ajouterCmd(a); return; }
    if (b.dataset.a === 'perte') perte(a, x.lot, x.q);
  }, { signal });
  async function perte(a, lotId, q) {
    const l = lotDe(a, lotId);
    try {
      await poster(url('discard', a.id, lotId));
      await apres();
      showToast(`${fmtQ(a, q)} de ${l ? l.n : 'ce lot'} sorti en perte`);
    } catch (e) { echec(e); }
  }

  /* ═════════ Commande ═════════ */

  async function ajouterCmd(a, n) {
    const q = n || suggestion(a), c = cmdDe(a.art);
    try {
      await poster(P.urls.orderAdd, {}, { lines: [{ art: a.art, n: q }] });
      await apres();
      toastUndo(`${a.lib} : ${q} ${pl(a.cond, q)} ajouté${q > 1 ? 's' : ''} à la commande`, async () => {
        try {
          if (c) await poster(url('orderLine', c.o.id, c.l.id), { n: c.l.n });
          else { const k = cmdDe(a.art); if (k) await poster(url('orderLineDrop', k.o.id, k.l.id)); }
          await apres();
        } catch (e) { echec(e); }
      });
    } catch (e) { echec(e); }
  }
  function ouvrirCmd() { rendreCmd(); $('cmdV').classList.add('open'); $('cmdP').classList.add('open'); }
  function fermerCmd() { $('cmdV').classList.remove('open'); $('cmdP').classList.remove('open'); }
  $('btnCmd').addEventListener('click', ouvrirCmd, { signal });
  $('cmdV').addEventListener('click', fermerCmd, { signal });
  $('cmdX').addEventListener('click', fermerCmd, { signal });
  const totCmd = o => o.lignes.reduce((s, l) => s + l.prix * l.n, 0) / 100;
  function rendreCmd() {
    const n = nCmd(), sugg = suivis().filter(a => ['rup', 'bas'].includes(statut(a)) && a.fourn && !cmdDe(a.art));
    $('cmdSub').textContent = n ? `${n} ligne${n > 1 ? 's' : ''} · ${DRAFTS.length} fournisseur${DRAFTS.length > 1 ? 's' : ''}` : 'Vide';
    let h = DRAFTS.map(o => `<section class="fs"><div class="fsg"><b>${esc(o.fourn.nom)}</b><span>${esc(o.numero)} · à envoyer</span><span class="tot">${fmtE(totCmd(o))} HT</span></div>
      <div class="lst">${o.lignes.map(l => { const a = art(l.art); if (!a) return ''; return `<div class="li"><div class="lt"><b>${esc(a.lib)}</b><span>${esc(a.code)} · ${fmtE(l.prix / 100)} HT / ${pl(a.cond, 1)} · en stock ${fmtQ(a, dispo(a))}</span></div>
        <div class="qty"><button type="button" data-cq="${l.id}" data-o="${o.id}" data-d="-1" aria-label="Moins">−</button><input data-ci="${l.id}" data-o="${o.id}" value="${fmtN(l.n, 0)}" aria-label="Quantité"><button type="button" data-cq="${l.id}" data-o="${o.id}" data-d="1" aria-label="Plus">+</button></div>
        <button type="button" class="rm" data-crm="${l.id}" data-o="${o.id}" data-tip="Retirer" aria-label="Retirer">${ICO.rm}</button></div>`; }).join('')}</div>
      <button type="button" class="btn primaire" style="width:100%;justify-content:center;margin-top:10px" data-env="${o.id}">Envoyer à ${esc(o.fourn.nom)}</button></section>`).join('');
    if (!n) h += '<p class="hint" style="text-align:center;margin:18px 0">Aucune ligne. Ajoutez des articles depuis les alertes, la fiche d\'un article ou une sélection.</p>';
    if (sugg.length) h += `<section class="fs"><h3>Sous le seuil, pas encore commandés</h3><div class="lst">${sugg.map(a => `<div class="li"><div class="lt"><b>${esc(a.lib)}</b><span>${esc(a.fourn.nom)} · ${fmtQ(a, dispo(a))} pour un seuil de ${fmtQ(a, a.seuil)}</span></div><button type="button" class="btn" style="padding:6px 10px;font-size:.76rem" data-cadd="${a.art}">+ ${suggestion(a)} ${pl(a.cond, suggestion(a))}</button></div>`).join('')}</div></section>`;
    if (SENT.length) h += `<section class="fs"><h3>Envoyées, en attente de livraison</h3>${SENT.map(c => `<div class="envoyee"><b>${esc(c.fourn.nom)}</b><span style="color:var(--encre-60)">${c.lignes.length} ligne${c.lignes.length > 1 ? 's' : ''} · ${c.statut === 'SEND_FAILED' ? 'échec de l\'envoi' : c.statut === 'PARTIALLY_RECEIVED' ? 'livrée en partie' : 'envoyée le ' + fmtD(c.d)}</span>${c.fichier ? `<a class="lien" href="${esc(url('orderFile', c.id))}" download data-tip="Bon de commande à transmettre au fournisseur">Bon (CSV)</a>` : ''}<span style="margin-left:auto;font-family:var(--font-data);font-size:.78rem">${fmtE(c.tot / 100)} HT</span></div>`).join('')}<p class="hint">À la livraison, « Réceptionner » reprend ces lignes.</p></section>`;
    $('cmdBody').innerHTML = h;
  }
  /* The quantity of a line follows the − / + at once; the order is written a moment later. */
  const qTimers = new Map();
  function changerQte(oid, lid, n) {
    const o = DRAFTS.find(x => x.id === oid), l = o && o.lignes.find(x => x.id === lid);
    if (!l) return;
    l.n = n;
    rendreCmd();
    entete();
    clearTimeout(qTimers.get(lid));
    qTimers.set(lid, setTimeout(async () => {
      try { await poster(url('orderLine', oid, lid), { n }); await recharger(); entete(); } catch (e) { echec(e); }
    }, 450));
  }
  $('cmdBody').addEventListener('click', async e => {
    const t = e.target;
    const q = t.closest('[data-cq]');
    if (q) { const o = DRAFTS.find(x => x.id === q.dataset.o), l = o && o.lignes.find(x => x.id === q.dataset.cq); if (l) changerQte(o.id, l.id, Math.max(1, l.n + +q.dataset.d)); return; }
    const rm = t.closest('[data-crm]');
    if (rm) {
      const o = DRAFTS.find(x => x.id === rm.dataset.o), l = o && o.lignes.find(x => x.id === rm.dataset.crm);
      if (!l) return;
      try {
        await poster(url('orderLineDrop', o.id, l.id));
        await apres();
        toastUndo('Ligne retirée', async () => { try { await poster(P.urls.orderAdd, {}, { lines: [{ art: l.art, n: l.n }] }); await apres(); } catch (err) { echec(err); } });
      } catch (err) { echec(err); }
      return;
    }
    const ad = t.closest('[data-cadd]');
    if (ad) { const a = art(ad.dataset.cadd); try { await poster(P.urls.orderAdd, {}, { lines: [{ art: a.art, n: suggestion(a) }] }); await apres(); } catch (err) { echec(err); } return; }
    const env = t.closest('[data-env]');
    if (env) {
      const o = DRAFTS.find(x => x.id === env.dataset.env);
      if (!o) return;
      env.disabled = true;
      try {
        const r = await poster(url('orderSend', o.id));
        await apres();
        showToast(r.fichier
          ? `Bon de commande ${o.fourn.nom} prêt — ${o.lignes.length} ligne${o.lignes.length > 1 ? 's' : ''}, ${fmtE(totCmd(o))} HT · à télécharger dans « Envoyées »`
          : `Commande transmise à ${o.fourn.nom} — ${o.lignes.length} ligne${o.lignes.length > 1 ? 's' : ''}, ${fmtE(totCmd(o))} HT`);
      } catch (err) { echec(err); env.disabled = false; }
    }
  }, { signal });
  $('cmdBody').addEventListener('input', e => {
    const lid = e.target.dataset.ci;
    if (!lid) return;
    const v = parseInt(e.target.value, 10);
    if (v > 0) {
      const o = DRAFTS.find(x => x.id === e.target.dataset.o), l = o && o.lignes.find(x => x.id === lid);
      if (!l) return;
      l.n = v;
      entete();
      clearTimeout(qTimers.get(lid));
      qTimers.set(lid, setTimeout(async () => { try { await poster(url('orderLine', o.id, lid), { n: v }); await recharger(); entete(); } catch (err) { echec(err); } }, 450));
    }
  }, { signal });

  /* ═════════ Fiche article ═════════ */

  function ouvrirFiche(c) { ST.fiche = c; czone.classList.add('ouvert'); rendreFiche(); rendre(); fiBody.scrollTop = 0; }
  function fermerFiche() { if (!ST.fiche) return; ST.fiche = null; czone.classList.remove('ouvert'); rendre(); }
  function spark(a) {
    const S = a.spark.length ? a.spark : Array(12).fill(0), mx = Math.max(1, ...S), W = 100 / S.length;
    return `<div class="spark"><svg viewBox="0 0 100 46" preserveAspectRatio="none">${S.map((v, i) => `<rect x="${i * W + 0.8}" y="${46 - Math.max(1.5, v / mx * 42)}" width="${W - 1.6}" height="${Math.max(1.5, v / mx * 42)}" rx="1" fill="${i === S.length - 1 ? '#4338ca' : '#c7d2fe'}"><title>${esc(fmtQ(a, v, 1))}</title></rect>`).join('')}</svg>
      <div class="lg"><span>il y a 12 semaines</span><span>sorties par semaine</span><span>cette semaine</span></div></div>`;
  }
  function seuilHint(a) {
    if (!a.seuil) return 'Zéro : pas d\'alerte de stock bas.';
    return a.conso ? `Alerte quand il reste ≈ ${Math.round(a.seuil / (a.conso / 30))} jours de consommation.` : 'Alerte en dessous de ce niveau.';
  }
  function rendreFiche() {
    const a = art(ST.fiche);
    if (!a) return;
    const d = dispo(a), c = couverture(a), s = statut(a), cmd = cmdDe(a.art);
    const lots = [...a.lots].sort((x, y) => (x.statut === 'actif' ? 0 : 1) - (y.statut === 'actif' ? 0 : 1) || x.per.localeCompare(y.per));
    const e = empl(a);
    $('fiHead').innerHTML = `<div class="fi-ico">${ICO.box}</div><div class="fi-t"><b>${esc(a.lib)}</b><span><span class="code">${esc(a.code)}</span> · ${TYPES[a.type]} · <span class="emplm" id="fiEmpl"><button type="button" class="emplb" ${a.arch ? 'disabled' : ''} aria-haspopup="listbox" data-tip="Changer d'emplacement"><span class="empl"><i style="background:${e.c}"></i>${e.l}</span>${a.arch ? '' : ICO.chev}</button><div class="dd-menu" role="listbox">${Object.entries(EMPL).map(([v, x]) => `<button type="button" class="dd-it${a.empl === v ? ' on' : ''}" data-empl="${v}"><span class="dot" style="background:${x.c}"></span>${x.l}</button>`).join('')}</div></span>${a.stup ? ' <span class="puce stup">Stupéfiant</span>' : ''}${froid(a) ? ' <span class="puce froid">2–8 °C</span>' : ''}</span></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;
    const mvA = a.mv.slice(0, 5);
    fiBody.innerHTML = `
      ${a.arch ? `<div class="bandeau info">${ICO.undo}<div><b>Suivi arrêté</b>L'article n'est plus compté dans le stock de la clinique ; son historique reste consultable.<div class="act"><button type="button" id="fiReprendre">Reprendre le suivi</button></div></div></div>` : ''}
      <section class="fs"><div class="skpis">
        <div class="skpi ${s === 'rup' ? 'ko' : s === 'bas' ? 'moy' : ''}"><small>Disponible</small><b>${a.lots.length ? fmtQ(a, d) : '—'}</b><span>${a.cont > 1 ? `${fmtN(d, d % 1 ? 1 : 0)} ${pl(a.u, d)}` : '&nbsp;'}</span></div>
        <div class="skpi ${c != null && c < 7 ? 'ko' : c != null && c < 15 ? 'moy' : ''}"><small>Couverture</small><b>${c != null && a.lots.length ? '≈ ' + Math.round(c) + ' jours' : '—'}</b><span>${a.conso ? `${fmtQ(a, a.conso, 1)} / mois` : 'pas de consommation'}</span></div>
        <div class="skpi"><small>Valeur</small><b>${a.fourn ? fmtE(valeur(a)) : '—'}</b><span>HT, au prix d'achat</span></div>
      </div>${a.lots.length ? spark(a) : ''}
      ${!a.arch && ['rup', 'bas'].includes(s) ? (cmd ? `<div class="bandeau info" style="margin-top:12px">${ICO.ok}<div><b>Dans la commande ${esc(cmd.o.fourn.nom)} · ${fmtN(cmd.l.n, 0)} ${pl(a.cond, cmd.l.n)}</b><div class="act"><button type="button" id="fiVoirCmd">Voir la commande</button></div></div></div>`
        : `<div class="bandeau warn" style="margin-top:12px">${ICO.tri}<div><b>${s === 'rup' ? 'Rupture de stock' : 'Sous le seuil d\'alerte'}</b>${a.fourn ? `Proposition : ${suggestion(a)} ${pl(a.cond, suggestion(a))} chez ${esc(a.fourn.nom)}, pour remonter à deux fois le seuil.<div class="act"><button type="button" class="p" id="fiCmd">Ajouter à la commande</button></div>` : 'Aucun fournisseur de la clinique ne référence cet article : commandez-le hors de Kiveto.'}</div></div>`) : ''}
      </section>
      <section class="fs"><h3>Seuil d'alerte</h3>
        <div class="seuilrow"><label class="seuilin"><input id="fiSeuil" value="${fmtN(a.seuil, a.seuil % 1 ? 1 : 0)}" inputmode="decimal" aria-label="Seuil d'alerte" ${a.arch ? 'disabled' : ''}><span>${pl(a.u, 2)}</span></label>
        <span class="hint" style="margin:0" id="fiSeuilH">${seuilHint(a)}</span></div>
      </section>
      <section class="fs"><h3>Lots<span class="tag hint" style="margin:0 0 0 auto;font-weight:400;letter-spacing:0;text-transform:none">Utilisés dans l'ordre de péremption</span></h3>
        ${lots.length ? lots.map(l => {
          const j = jours(l.per), off = l.statut !== 'actif' || l.q <= 0;
          const etat = l.statut === 'rappel' ? '<span class="puce rappel">Rappelé</span>' : l.statut === 'perime' && l.q > 0 ? '<span class="puce perime">Périmé</span>' : l.statut === 'epuise' || l.q <= 0 ? '<span class="puce jamais">Épuisé</span>' : j < 0 ? '<span class="puce perime">Périmé</span>' : j <= 60 ? `<span class="puce perp">dans ${j} j</span>` : '';
          return `<div class="lot${off ? ' off' : ''}"><div class="lt"><b>${esc(l.n)}${etat}</b>
            <span>Périme le ${fmtD(l.per)}${l.recu ? ' · reçu le ' + fmtD(l.recu) : ''}${l.bl ? ' · ' + esc(l.bl) : ''}</span>
            ${l.statut === 'rappel' && l.rappel && l.rappel.motif ? `<em style="color:#6d28d9">${esc(l.rappel.motif)}</em>` : ''}</div>
            <span class="lq">${fmtQ(a, l.q)}</span>
            ${!off && !a.arch ? `<div class="lmenu"><button type="button" data-lm="${l.id}" aria-label="Actions du lot">${ICO.dots}</button><div class="dd-menu">
              <button type="button" class="dd-it" data-la="compter" data-l="${l.id}">Compter ce lot</button>
              ${j >= 0 ? `<button type="button" class="dd-it" data-la="sortir" data-l="${l.id}">Sortir de ce lot</button>` : ''}
              <div class="dd-sep"></div><button type="button" class="dd-it" data-la="rappel" data-l="${l.id}" style="color:#b91c1c">Rappeler le lot…</button></div></div>` : '<span style="width:28px"></span>'}</div>`;
        }).join('')
        : '<p class="hint" style="margin:0">Aucun lot : l\'article n\'a jamais été réceptionné.</p>'}
      </section>
      ${mvA.length ? `<section class="fs mvmini"><h3>Derniers mouvements<span class="tag"><button type="button" class="lien" id="fiTousMv">Tout voir</button></span></h3><div class="lst">${mvA.map(m => `<div class="li"><div class="lt"><b>${esc(m.motif)}</b><span>${fmtD(m.d)}${m.lot ? ' · ' + esc(m.lot) : ''}${m.ref ? ' · ' + esc(m.ref) : ''}</span></div><span class="m ${m.t}">${m.q > 0 ? '+' : '−'}${fmtQ(a, Math.abs(m.q), 1)}</span></div>`).join('')}</div></section>` : ''}
      <section class="fs"><h3>Approvisionnement</h3><div class="hist">
        ${a.fourn ? `<div><span>Fournisseur</span><b>${esc(a.fourn.nom)}</b></div><div><span>Prix d'achat</span><b>${fmtE(a.achat)} HT / ${pl(a.cond, 1)}${a.cont > 1 ? ` de ${fmtN(a.cont, 0)} ${pl(a.u, a.cont)}` : ''}</b></div>` : '<div><span>Fournisseur</span><b>Aucun fournisseur de la clinique ne le référence</b></div>'}
        ${a.gtin ? `<div><span>GTIN</span><b>${esc(a.gtin)}</b></div>` : ''}</div>
        <a class="lien" style="margin-top:8px" href="${esc(P.urls.catalog)}?article=${encodeURIComponent(a.art)}">Fiche dans le catalogue ${ICO.ext}</a></section>`;
    const vide = a.id && !a.arch && !a.lots.some(l => l.statut === 'actif' && l.q > 0);
    $('fiFoot').innerHTML = `${vide ? '<button type="button" class="btn" id="fiArch" data-tip="Le stock est vide : l\'article ne sera plus compté">Arrêter le suivi</button>' : ''}<span class="sp"></span>${!a.arch && d > 0 ? '<button type="button" class="btn" id="fiSortie">Sortie de stock</button>' : ''}${a.arch ? '' : '<button type="button" class="btn primaire" id="fiRecep">Réceptionner</button>'}`;
    $('fiX').onclick = fermerFiche;
  }
  let seuilTimer = null;
  fiBody.addEventListener('input', e => {
    if (e.target.id !== 'fiSeuil') return;
    const a = art(ST.fiche), v = parseN(e.target.value);
    if (v == null || v < 0) return;
    a.seuil = v;
    $('fiSeuilH').textContent = seuilHint(a);
    clearTimeout(seuilTimer);
    seuilTimer = setTimeout(() => rendre(), 400);
  }, { signal });
  fiBody.addEventListener('change', async e => {
    if (e.target.id !== 'fiSeuil') return;
    const a = art(ST.fiche), v = parseN(e.target.value);
    if (v == null || v < 0) { rendreFiche(); return; }
    try { await poster(url('threshold', a.art), { threshold: String(v) }); await apres(); showToast('Seuil d\'alerte enregistré'); } catch (err) { echec(err); }
  }, { signal });
  $('fiHead').addEventListener('click', async e => {
    const b = e.target.closest('.emplb');
    if (b && !b.disabled) { e.stopPropagation(); $('fiEmpl').classList.toggle('open'); return; }
    const it = e.target.closest('[data-empl]');
    if (!it) return;
    const a = art(ST.fiche), v = it.dataset.empl;
    $('fiEmpl').classList.remove('open');
    if (v === a.empl) return;
    const avant = a.empl;
    try {
      await poster(url('location', a.art), { location: v });
      await apres();
      toastUndo(`${a.lib} rangé : ${EMPL[v].l}`, async () => { try { await poster(url('location', a.art), { location: avant || '' }); await apres(); } catch (err) { echec(err); } });
    } catch (err) { echec(err); }
  }, { signal });
  fiBody.addEventListener('click', async e => {
    const a = art(ST.fiche), t = e.target;
    const lm = t.closest('[data-lm]');
    if (lm) { e.stopPropagation(); const m = lm.parentElement, was = m.classList.contains('open'); root.querySelectorAll('.lmenu.open').forEach(x => x.classList.remove('open')); if (!was) m.classList.add('open'); return; }
    const la = t.closest('[data-la]');
    if (la) {
      root.querySelectorAll('.lmenu.open').forEach(x => x.classList.remove('open'));
      const l = lotDe(a, la.dataset.l);
      if (la.dataset.la === 'compter') ouvrirInv([a.art]);
      if (la.dataset.la === 'sortir') ouvrirSortie(a, l.id);
      if (la.dataset.la === 'rappel') ouvrirRappel(a, l);
      return;
    }
    if (t.closest('#fiCmd')) { ajouterCmd(a); return; }
    if (t.closest('#fiVoirCmd')) { ouvrirCmd(); return; }
    if (t.closest('#fiTousMv')) { ST.f.mvts = { t: '', per: 'tout', reg: false }; allerOnglet('mvts', a.code); return; }
    if (t.closest('#fiReprendre')) {
      try { await poster(url('unarchive', a.id)); await apres(); showToast(`Suivi de ${a.lib} repris`); } catch (err) { echec(err); }
    }
  }, { signal });
  document.addEventListener('click', e => {
    if (!e.target.closest('.lmenu')) root.querySelectorAll('.lmenu.open').forEach(x => x.classList.remove('open'));
    if (!e.target.closest('.emplm')) $('fiEmpl')?.classList.remove('open');
  }, { signal });
  $('fiFoot').addEventListener('click', async e => {
    const a = art(ST.fiche);
    if (e.target.closest('#fiSortie')) ouvrirSortie(a);
    if (e.target.closest('#fiRecep')) { const c = cmdDe(a.art); ouvrirRecep([{ art: a.art, n: c ? c.l.n : 1 }]); }
    if (e.target.closest('#fiArch')) {
      try {
        await poster(url('archive', a.id));
        await apres();
        toastUndo(`Suivi de ${a.lib} arrêté`, async () => { try { await poster(url('unarchive', a.id)); await apres(); } catch (err) { echec(err); } });
      } catch (err) { echec(err); }
    }
  }, { signal });

  /* ═════════ Modales ═════════ */

  const ouvrirModale = id => $(id).classList.add('open');
  function fermerModales() { root.querySelectorAll('.mveil.open').forEach(m => m.classList.remove('open')); fermerPick(); }
  root.querySelectorAll('.mveil').forEach(m => {
    m.addEventListener('mousedown', e => { if (e.target === m) fermerModales(); }, { signal });
    m.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', fermerModales, { signal }));
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (pick.classList.contains('open')) { fermerPick(); e.stopPropagation(); return; }
    if (root.querySelector('.mveil.open')) { fermerModales(); e.stopPropagation(); return; }
    if ($('fiEmpl')?.classList.contains('open')) { $('fiEmpl').classList.remove('open'); e.stopPropagation(); return; }
    if (root.querySelector('.lmenu.open')) { root.querySelectorAll('.lmenu.open').forEach(x => x.classList.remove('open')); e.stopPropagation(); return; }
    if (document.querySelector('.dd.open') || document.querySelector('.veil.open')) return;
    if ($('cmdP').classList.contains('open')) { fermerCmd(); e.stopPropagation(); return; }
    if (ST.fiche) { fermerFiche(); e.stopPropagation(); }
  }, { signal, capture: true });

  /* — sélecteur d'article — */
  let pickCb = null, pickHl = 0, pickRes = [];
  function ouvrirPick(anchor, cb) {
    pickCb = cb;
    pickQ.value = '';
    pickHl = 0;
    const r = anchor.getBoundingClientRect(), W = Math.min(520, Math.max(380, r.width));
    pick.style.width = W + 'px';
    pick.classList.add('open');
    rendrePick();
    const H = pick.offsetHeight;
    pick.style.left = Math.min(Math.max(8, r.left), innerWidth - W - 8) + 'px';
    pick.style.top = (r.bottom + 6 + H > innerHeight - 8 ? Math.max(8, r.top - H - 6) : r.bottom + 6) + 'px';
    pickQ.focus();
  }
  function rendrePick() {
    const q = norm(pickQ.value);
    pickRes = suivis().filter(a => !q || norm(a.code + ' ' + a.lib + ' ' + a.gtin).includes(q)).slice(0, 40);
    pickHl = Math.min(pickHl, Math.max(0, pickRes.length - 1));
    pickL.innerHTML = pickRes.map((a, i) => `<button type="button" class="pick-it${i === pickHl ? ' hl' : ''}" data-i="${i}"><div class="lt"><b>${esc(a.lib)}</b><span>${esc(a.code)}${a.fourn ? ' · ' + esc(a.fourn.nom) : ''}</span></div><span class="m">${fmtQ(a, dispo(a))}</span></button>`).join('') || '<div class="pick-vide">Aucun résultat</div>';
  }
  pickQ.addEventListener('input', () => { pickHl = 0; rendrePick(); }, { signal });
  pickQ.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); pickHl = Math.min(pickRes.length - 1, pickHl + 1); rendrePick(); } else if (e.key === 'ArrowUp') { e.preventDefault(); pickHl = Math.max(0, pickHl - 1); rendrePick(); } else if (e.key === 'Enter' && pickRes[pickHl]) { e.preventDefault(); const a = pickRes[pickHl]; fermerPick(); pickCb(a); }
  }, { signal });
  pickL.addEventListener('click', e => { const b = e.target.closest('.pick-it'); if (b) { const a = pickRes[+b.dataset.i]; fermerPick(); pickCb(a); } }, { signal });
  function fermerPick() { pick.classList.remove('open'); }
  document.addEventListener('mousedown', e => { if (pick.classList.contains('open') && !e.target.closest('#pick,#rcAdd')) fermerPick(); }, { signal });

  /* — Réception — */
  let RC = { lignes: [], order: null };
  /* « Autre » is stock that comes from none of the clinic's suppliers — what it held before Kiveto, mostly. */
  const fournOpts = () => [...SUPPLIERS.map(f => ({ v: f.id, l: f.nom })), { v: '', l: 'Autre provenance', sub: 'hors fournisseurs' }];
  const rcFourn = creerDropdown('rcFourn', fournOpts(), SUPPLIERS[0] ? SUPPLIERS[0].id : '', () => rendreRc());
  function ouvrirRecep(lignes) {
    RC = { lignes: (lignes || []).map(l => ({ art: l.art, lot: '', per: '', n: l.n, pol: '' })), order: null };
    rcFourn.setOptions(fournOpts());
    const a0 = RC.lignes[0] ? art(RC.lignes[0].art) : null;
    if (a0 && a0.fourn) rcFourn.set(a0.fourn.id); else rcFourn.set(SUPPLIERS[0] ? SUPPLIERS[0].id : '');
    $('rcBl').value = '';
    $('rcScan').value = '';
    rendreRc();
    ouvrirModale('mRecep');
    setTimeout(() => (RC.lignes.length ? root.querySelector('#rcTab [data-rf="lot"]') : $('rcScan'))?.focus(), 50);
  }
  $('btnRecep').addEventListener('click', () => ouvrirRecep(), { signal });
  function rendreRc() {
    const f = rcFourn.get(), env = SENT.filter(c => c.fourn.id === f && c.statut !== 'SEND_FAILED' && RC.order !== c.id);
    $('rcCmd').innerHTML = env.map(c => `<div class="bandeau info">${ICO.cart}<div><b>Commande ${esc(c.fourn.nom)} du ${fmtD(c.d)} · ${c.lignes.length} ligne${c.lignes.length > 1 ? 's' : ''}</b>Reprenez-la pour n'avoir plus qu'à saisir lots et péremptions.<div class="act"><button type="button" data-rep="${c.id}">Reprendre la commande</button></div></div></div>`).join('');
    const T = $('rcTab');
    T.innerHTML = RC.lignes.length ? '<tr><th>Article</th><th>N° de lot</th><th>Péremption</th><th style="width:150px">Quantité reçue</th><th></th></tr>' + RC.lignes.map((l, i) => {
      const a = art(l.art);
      return `<tr data-i="${i}" class="${l.flash ? 'flash' : ''}"><td class="art"><b>${esc(a.lib)}</b><span>${esc(a.code)}${froid(a) ? ' · <b style="display:inline;color:#0e7490;font-size:.68rem">au froid</b>' : ''}</span></td>
        <td><input class="finput" data-rf="lot" value="${esc(l.lot)}" placeholder="LOT-…" style="font-family:var(--font-data)" aria-label="N° de lot"></td>
        <td><input class="finput" type="date" data-rf="per" value="${l.per}" min="${TODAY_ISO}" aria-label="Péremption"></td>
        <td><div class="inu"><input class="finput num" data-rf="n" value="${l.n ?? ''}" aria-label="Quantité reçue"><i>${pl(a.cond, 2)}</i></div></td>
        <td><button type="button" class="rm" data-rrm="${i}" aria-label="Retirer">${ICO.rm}</button></td></tr>`;
    }).join('') : '';
    RC.lignes.forEach(l => delete l.flash);
    majRc();
  }
  function majRc() {
    const ok = RC.lignes.length && RC.lignes.every(l => l.lot && l.per && l.per >= TODAY_ISO && l.n > 0);
    $('rcOk').disabled = !ok;
    const tot = RC.lignes.reduce((s, l) => s + (art(l.art).achat * (l.n || 0)), 0);
    $('rcTot').innerHTML = RC.lignes.length ? `<b>${RC.lignes.length}</b> ligne${RC.lignes.length > 1 ? 's' : ''} · <b>${fmtE(tot)}</b> HT` : '';
  }
  $('rcTab').addEventListener('input', e => {
    const tr = e.target.closest('tr'), f = e.target.dataset.rf;
    if (!tr || !f) return;
    const l = RC.lignes[+tr.dataset.i];
    l[f] = f === 'n' ? parseN(e.target.value) : f === 'lot' ? e.target.value.toUpperCase() : e.target.value;
    majRc();
  }, { signal });
  $('rcTab').addEventListener('click', e => { const b = e.target.closest('[data-rrm]'); if (b) { RC.lignes.splice(+b.dataset.rrm, 1); if (!RC.lignes.some(l => l.pol)) RC.order = null; rendreRc(); } }, { signal });
  $('rcCmd').addEventListener('click', e => {
    const b = e.target.closest('[data-rep]');
    if (!b) return;
    const c = SENT.find(x => x.id === b.dataset.rep);
    RC.order = c.id;
    c.lignes.forEach(x => {
      const reste = Math.max(0, x.n - x.recu);
      if (!reste || !art(x.art)) return;
      const ex = RC.lignes.find(r => r.art === x.art && !r.pol);
      if (ex) Object.assign(ex, { n: reste, pol: x.id }); else RC.lignes.push({ art: x.art, lot: '', per: '', n: reste, pol: x.id });
    });
    rendreRc();
  }, { signal });
  /* A scan of a box's DataMatrix (GS1: (01) GTIN, (17) expiry, (10) lot). */
  function scanner(code) {
    const m = code.match(/\(01\)(\d{14})(?:\(17\)(\d{6}))?(?:\(10\)([\w-]+))?/), s = m ? { g: m[1], per: m[2] ? `20${m[2].slice(0, 2)}-${m[2].slice(2, 4)}-${m[2].slice(4, 6)}` : '', lot: m[3] || '' } : null;
    const a = s && suivis().find(x => x.gtin === s.g);
    if (!a) { showToast('Code non reconnu — ajoutez l\'article à la main'); return; }
    const ex = RC.lignes.find(r => r.art === a.art && r.lot === s.lot), vide = RC.lignes.find(r => r.art === a.art && !r.lot);
    if (ex) { ex.n = (ex.n || 0) + 1; ex.flash = true; } else if (vide) Object.assign(vide, { lot: s.lot, per: s.per, flash: true }); else RC.lignes.push({ art: a.art, lot: s.lot, per: s.per, n: 1, pol: '', flash: true });
    rendreRc();
  }
  $('rcScan').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); scanner(e.target.value); e.target.value = ''; } }, { signal });
  $('rcAdd').addEventListener('click', e => ouvrirPick(e.currentTarget, a => { RC.lignes.push({ art: a.art, lot: '', per: '', n: 1, pol: '', flash: true }); rendreRc(); setTimeout(() => root.querySelector(`#rcTab tr[data-i="${RC.lignes.length - 1}"] [data-rf="lot"]`)?.focus(), 30); }), { signal });
  $('rcOk').addEventListener('click', async () => {
    const bl = $('rcBl').value.trim(), n = RC.lignes.length;
    $('rcOk').disabled = true;
    try {
      const r = await poster(P.urls.receive, { supplier: rcFourn.get(), bl, order: RC.order || '' }, { lines: RC.lignes.map(l => ({ art: l.art, lot: l.lot, per: l.per, n: l.n, pol: l.pol || '' })) });
      fermerModales();
      await apres();
      const msg = `${n} ligne${n > 1 ? 's' : ''} entrée${n > 1 ? 's' : ''} en stock · ${bl || 'sans bon de livraison'}`;
      if (r.undo && r.undo.length) toastUndo(msg, restaurer(r.undo)); else showToast(msg);
    } catch (e) { echec(e); majRc(); }
  }, { signal });

  /* — Sortie — */
  let SO = null, soLot = null;
  function ouvrirSortie(a, lotId) {
    SO = { a, motif: 'SALE_RETAIL' };
    $('soSub').textContent = `${a.lib} · ${fmtQ(a, dispo(a))} disponible`;
    $('soMotif').innerHTML = MOTIFS.map(([v, l]) => `<button type="button" class="chip${v === SO.motif ? ' on' : ''}" data-m="${v}">${l}</button>`).join('');
    $('soQ').value = '';
    $('soNote').value = '';
    $('soStup').value = '';
    $('soU').textContent = pl(a.u, 2);
    $('soStupRow').style.display = a.stup ? '' : 'none';
    const F = fefo(a);
    soLot = creerDropdown('soLot', [{ v: '', l: `Automatique · ${F[0] ? F[0].n : '—'}`, sub: 'le plus proche de sa péremption' }, ...F.map(l => ({ v: l.id, l: l.n, sub: `${fmtQ(a, l.q)} · ${fmtD(l.per)}` }))], lotId || '', majSo);
    majSo();
    ouvrirModale('mSortie');
    setTimeout(() => $('soQ').focus(), 50);
  }
  $('soMotif').addEventListener('click', e => { const b = e.target.closest('[data-m]'); if (!b) return; SO.motif = b.dataset.m; root.querySelectorAll('#soMotif .chip').forEach(x => x.classList.toggle('on', x === b)); majSo(); }, { signal });
  const lotSo = () => (soLot.get() ? lotDe(SO.a, soLot.get()) : fefo(SO.a)[0]);
  function majSo() {
    if (!SO) return;
    const q = parseN($('soQ').value), lot = lotSo();
    $('soOk').disabled = !(q > 0 && lot && q <= lot.q && (!SO.a.stup || $('soStup').value.trim()));
  }
  ['soQ', 'soStup'].forEach(id => $(id).addEventListener('input', majSo, { signal }));
  $('soOk').addEventListener('click', async () => {
    const a = SO.a, q = parseN($('soQ').value), lot = lotSo(), stup = $('soStup').value.trim();
    const note = [stup, $('soNote').value.trim()].filter(Boolean).join(' · ');
    const motif = MOTIFS.find(m => m[0] === SO.motif)[1];
    $('soOk').disabled = true;
    try {
      const r = await poster(url('consume', a.id), { lot: lot.id, quantity: String(q), reason: SO.motif, note, reference: a.stup ? stup.split(' · ')[0] : '' });
      fermerModales();
      await apres();
      toastUndo(`${fmtQ(a, q)} sorti${q > 1 ? 's' : ''} · ${motif.toLowerCase()}`, restaurer(r.undo));
    } catch (e) { echec(e); majSo(); }
  }, { signal });

  /* — Inventaire — */
  let INV = { codes: null, empl: '', compte: {} };
  function ouvrirInv(codes) {
    INV = { codes, empl: '', compte: {} };
    $('invSub').textContent = codes ? (codes.length === 1 ? art(codes[0]).lib : `${codes.length} articles sélectionnés`) : 'Comptez, l\'écart avec le théorique devient un ajustement';
    $('invEmpl').style.display = codes ? 'none' : '';
    rendreInv();
    ouvrirModale('mInv');
    setTimeout(() => root.querySelector('#invTab input')?.focus(), 50);
  }
  $('btnInv').addEventListener('click', () => ouvrirInv(null), { signal });
  const lignesInv = () => suivis().filter(a => (!INV.codes || INV.codes.includes(a.art)) && (!INV.empl || a.empl === INV.empl))
    .flatMap(a => actifs(a).filter(l => l.q > 0).map(l => ({ a, l, k: a.art + '|' + l.id })));
  const ecartHtml = d => (d == null ? '' : d === 0 ? '<span class="ecart" style="color:var(--encre-35)">=</span>' : `<span class="ecart ${d < 0 ? 'moins' : 'plus'}">${d > 0 ? '+' : '−'}${fmtN(Math.abs(d), Math.abs(d) % 1 ? 1 : 0)}</span>`);
  function rendreInv() {
    $('invEmpl').innerHTML = [['', 'Tous les emplacements'], ...Object.entries(EMPL).map(([v, e]) => [v, e.l])].map(([v, l]) => `<button type="button" class="chip${INV.empl === v ? ' on' : ''}" data-e="${v}">${l}</button>`).join('');
    const Ls = lignesInv();
    $('invTab').innerHTML = '<tr><th>Article</th><th>Lot</th><th class="num">Théorique</th><th style="width:130px">Compté</th><th class="num">Écart</th></tr>' + (Ls.map(({ a, l, k }) => {
      const c = INV.compte[k], e = c != null ? c - l.q : null;
      return `<tr><td class="art"><b>${esc(a.lib)}</b><span>${empl(a).l}</span></td><td style="font-family:var(--font-data);font-size:.76rem">${esc(l.n)}<span style="display:block;color:var(--encre-35);font-size:.68rem">${fmtD(l.per)}</span></td>
        <td class="num" style="font-family:var(--font-data);font-size:.78rem">${fmtN(l.q, l.q % 1 ? 1 : 0)} ${pl(a.u, l.q)}</td>
        <td><div class="inu"><input class="finput num" data-k="${k}" value="${c ?? ''}" placeholder="${fmtN(l.q, l.q % 1 ? 1 : 0)}" aria-label="Quantité comptée"><i>${pl(a.u, 2)}</i></div></td>
        <td class="num">${ecartHtml(e)}</td></tr>`;
    }).join('') || '<tr><td colspan="5" class="hint" style="padding:14px 6px">Aucun lot en stock à compter ici.</td></tr>');
    majInv();
  }
  function majInv() {
    const Ls = lignesInv(), comptes = Ls.filter(x => INV.compte[x.k] != null), ecarts = comptes.filter(x => INV.compte[x.k] !== x.l.q);
    const val = ecarts.reduce((s, x) => s + (INV.compte[x.k] - x.l.q) / x.a.cont * x.a.achat, 0);
    $('invTot').innerHTML = `<b>${comptes.length}</b> / ${Ls.length} lots comptés${ecarts.length ? ` · <b>${ecarts.length}</b> écart${ecarts.length > 1 ? 's' : ''} · <b style="color:${val < 0 ? '#b91c1c' : '#15803d'}">${val < 0 ? '−' : '+'}${fmtE(Math.abs(val))}</b> HT` : ''}`;
    const b = $('invOk');
    b.disabled = !comptes.length;
    b.textContent = ecarts.length ? `Valider ${ecarts.length} ajustement${ecarts.length > 1 ? 's' : ''}` : 'Valider le comptage';
  }
  $('invEmpl').addEventListener('click', e => { const b = e.target.closest('[data-e]'); if (!b) return; INV.empl = b.dataset.e; rendreInv(); }, { signal });
  $('invTab').addEventListener('input', e => {
    const k = e.target.dataset.k;
    if (!k) return;
    const v = parseN(e.target.value);
    if (v == null || v < 0) delete INV.compte[k]; else INV.compte[k] = v;
    const x = lignesInv().find(o => o.k === k);
    e.target.closest('tr').querySelector('td:last-child').innerHTML = ecartHtml(v == null || v < 0 ? null : v - x.l.q);
    majInv();
  }, { signal });
  $('invTab').addEventListener('keydown', e => { if (e.key === 'Enter') { const ins = [...root.querySelectorAll('#invTab input')], i = ins.indexOf(e.target); ins[i + 1]?.focus(); } }, { signal });
  $('invOk').addEventListener('click', async () => {
    const Ls = lignesInv().filter(x => INV.compte[x.k] != null), ec = Ls.filter(x => INV.compte[x.k] !== x.l.q);
    $('invOk').disabled = true;
    try {
      const r = await poster(P.urls.count, {}, { counts: Ls.map(x => ({ item: x.a.id, lot: x.l.id, q: INV.compte[x.k] })) });
      fermerModales();
      await apres();
      const undo = (r.undo || []).filter(u => ec.some(x => x.l.id === u.lot));
      if (ec.length) toastUndo(`Inventaire validé · ${ec.length} ajustement${ec.length > 1 ? 's' : ''}`, restaurer(undo)); else showToast('Comptage validé · aucun écart');
    } catch (e) { echec(e); majInv(); }
  }, { signal });

  /* — Rappel — */
  let RA = null;
  async function ouvrirRappel(a, l) {
    RA = { a, l };
    $('raSub').textContent = `${a.lib} · ${l.n} · ${fmtQ(a, l.q)} en stock`;
    $('raMotif').value = '';
    $('raAni').innerHTML = '<p class="hint">Recherche des animaux qui ont reçu ce lot…</p>';
    $('raOk').disabled = true;
    ouvrirModale('mRappel');
    setTimeout(() => $('raMotif').focus(), 50);
    try {
      const qs = new URLSearchParams({ lot: l.id, cons: '1', per: 'tout', pp: '100' });
      const r = await fetch(`${P.urls.movements}?${qs}`, { headers: { Accept: 'application/json' }, signal });
      const d = r.ok ? await r.json() : { rows: [] };
      const anis = [...new Set(d.rows.map(m => m.ref).filter(Boolean))];
      if (RA.l !== l) return;
      $('raAni').innerHTML = anis.length
        ? `<div class="animaux"><div class="swrow lock" style="border:none"><span class="sw" role="switch" aria-checked="false" aria-disabled="true" data-tip="${SOON}"></span><div><b>Prévenir ${anis.length === 1 ? 'le propriétaire' : `les ${anis.length} propriétaires`} par SMS</b><small>Le lot a été administré ou délivré à : ${anis.map(esc).join(', ')}. L'envoi de SMS n'est pas encore disponible : prévenez-les vous-même.</small></div></div></div>`
        : '<p class="hint">Ce lot n\'a encore été délivré à aucun animal.</p>';
    } catch (e) { echec(e); }
  }
  $('raMotif').addEventListener('input', e => { $('raOk').disabled = !e.target.value.trim(); }, { signal });
  $('raOk').addEventListener('click', async () => {
    const { a, l } = RA, motif = $('raMotif').value.trim();
    $('raOk').disabled = true;
    try {
      await poster(url('recall', a.id, l.id), { reason: motif });
      fermerModales();
      await apres();
      showToast(`${l.n} rappelé · ${fmtQ(a, l.q)} retourné${l.q > 1 ? 's' : ''} au fournisseur`);
    } catch (e) { echec(e); $('raOk').disabled = false; }
  }, { signal });

  /* ═════════ Actions groupées ═════════ */

  function majBulk() {
    const n = ST.tab === 'etat' ? ST.sel.size : 0;
    $('bulk').classList.toggle('on', n > 0);
    $('bulkN').textContent = n;
    $('bulkL').textContent = n > 1 ? 'sélectionnés' : 'sélectionné';
  }
  $('bkX').addEventListener('click', () => { ST.sel.clear(); rendre(); }, { signal });
  $('bkCmd').addEventListener('click', async () => {
    const As = [...ST.sel].map(art).filter(a => a && !a.arch), avec = As.filter(a => a.fourn && !cmdDe(a.art)), sans = As.filter(a => !a.fourn);
    ST.sel.clear();
    if (!avec.length) { rendre(); showToast(sans.length ? 'Aucun fournisseur de la clinique ne référence ces articles' : 'Ces articles sont déjà dans la commande'); return; }
    try {
      await poster(P.urls.orderAdd, {}, { lines: avec.map(a => ({ art: a.art, n: suggestion(a) })) });
      await apres();
      toastUndo(`${avec.length} article${avec.length > 1 ? 's' : ''} ajouté${avec.length > 1 ? 's' : ''} à la commande${sans.length ? ` · ${sans.length} sans fournisseur` : ''}`, async () => {
        try { for (const a of avec) { const k = cmdDe(a.art); if (k) await poster(url('orderLineDrop', k.o.id, k.l.id)); } await apres(); } catch (e) { echec(e); }
      });
    } catch (e) { echec(e); rendre(); }
  }, { signal });
  $('bkInv').addEventListener('click', () => { const c = [...ST.sel]; ST.sel.clear(); rendre(); ouvrirInv(c); }, { signal });

  document.addEventListener('turbo:before-cache', () => { fermerModales(); fermerCmd(); }, { signal });

  /* ═════════ Démarrage ═════════ */
  try {
    const p = new URLSearchParams(location.search), t = p.get('onglet');
    if (['etat', 'mvts', 'alertes'].includes(t)) ST.tab = t;
    root.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x.dataset.t === ST.tab));
    const c = p.get('article'), a = c && (art(c) || ART.find(x => x.code === c));
    if (a) { ST.tab = 'etat'; ST.fiche = a.art; czone.classList.add('ouvert'); rendreFiche(); }
  } catch (_) { /* malformed address */ }
  rendre();
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearTimeout(mvTimer);
  mvTimer = null;
}
