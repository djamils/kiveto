/**
 * Page module — Catalogue (catalogue-v-topbar_1 mock-up). Loaded by the app.js
 * dispatcher on clinic_catalogue (acts), clinic_catalogue_articles,
 * clinic_catalogue_packages and clinic_catalogue_price_lists: one page, opened
 * on the route's tab. Switching tabs happens in place and the address follows.
 *
 * The mock-up renders from its demo data; this module renders the same way
 * from the `data-payload` of CatalogPageBuilder — the clinic's whole catalogue,
 * archived items included — and searches, filters, sorts and pages it here.
 * Prices travel as the clinic entered them: `price` in cents and `pm`, its
 * mode ('incl' all taxes included, 'excl' excluding tax). Every price field of
 * the page is labelled TTC and posts what it shows with mode 'incl'; nothing
 * is converted before it is stored. An 'excl' price (entered before the modes
 * existed) is shown TTC through its family's rate, for display only.
 *
 * Every change (sheet saved, archived / restored, bulk price revision or
 * family change, package composition, price list entries, new price list) is
 * posted in the background to its single-action controller with the page's
 * token, then clinic_catalogue_data is asked again and the page redrawn. What
 * Kiveto does not store yet (scheduled price changes, size-based prices,
 * online booking, purchase prices, dosage, schedules and rules of the price
 * lists…) is drawn but disabled.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

const PP_KEY = 'kiveto-catalogue-pp';
const NIL = '00000000-0000-7000-8000-000000000000';
const SOON = 'Pas encore disponible dans Kiveto';

let ac = null;
let regTimer = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const fmtN = (n, d = 2) => Number(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
const fmtE = n => fmtN(n) + ' €';
const r2 = n => Math.round(n * 100) / 100;
const parseN = s => { const v = parseFloat(String(s ?? '').replace(/\s/g, '').replace(',', '.')); return isNaN(v) ? null : v; };

const ICO = {
  tri: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>',
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
  ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  rm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  acte: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>',
  med: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
  box: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>',
  pack: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C9 3 12 8 12 8s3-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
};

/* ── Référentiels ── */
const CATS = {
  CONSULTATION: { l: 'Consultation', c: '#4338ca' }, SURGERY: { l: 'Chirurgie', c: '#dc2626' }, IMAGING: { l: 'Imagerie', c: '#0891b2' },
  LABORATORY: { l: 'Laboratoire', c: '#1d4ed8' }, HOSPITALIZATION: { l: 'Hospitalisation', c: '#7c3aed' }, VACCINATION: { l: 'Vaccination', c: '#16a34a' },
  PREVENTION: { l: 'Prévention', c: '#0d9488' }, OTHER: { l: 'Autre', c: '#64748b' },
};
const ESP = ['Chien', 'Chat', 'NAC', 'Équin', 'Bovin', 'Ovin/caprin', 'Porcin', 'Volaille'];
const TYPES = { med: 'Médicament', conso: 'Consommable', ali: 'Alimentation', mat: 'Matériel' };
const KIND = { med: 'DRUG', conso: 'CONSUMABLE', ali: 'FOOD', mat: 'EQUIPMENT' };
/* Delivery classes of the Catalog context, drawn with the mock-up's chips. */
const CLASSES = { NONE: ['cl-libre', 'Sans ordonnance'], RX: ['cl-l1', 'Sur ordonnance'], RX_CONTROLLED: ['aic', 'Substance contrôlée'], RX_NARCOTIC: ['cl-stup', 'Stupéfiant'] };
const CLASSE_TXT = {
  NONE: 'Délivrable sans ordonnance.',
  RX: 'Ordonnance requise pour la délivrance.',
  RX_CONTROLLED: 'Ordonnance requise ; substance à délivrance contrôlée, suivie au registre.',
  RX_NARCOTIC: 'Ordonnance sécurisée, 28 jours maximum, sans renouvellement. Chaque entrée et sortie est inscrite au registre des stupéfiants ; stockage sous clé.',
};
const UOM = { UNIT: 'unité', BOX: 'boîte', TABLET: 'cp', CAPSULE: 'gélule', VIAL: 'flacon', BOTTLE: 'bouteille', BAG: 'sac', SACHET: 'sachet', AMPULE: 'ampoule', DOSE: 'dose', PIPETTE: 'pipette', ML: 'mL', L: 'L', G: 'g', KG: 'kg' };
const NEWL = { actes: 'Nouvel acte', articles: 'Nouvel article', forfaits: 'Nouveau forfait', grilles: 'Nouvelle grille' };
const PH = { actes: 'Rechercher un acte, un code…', articles: 'Libellé, code, code-barres (GTIN)…', forfaits: 'Rechercher un forfait…' };
const STATUTS = [{ v: 'cat', l: 'Au catalogue' }, { v: 'arch', l: 'Archivés' }, { v: 'tous', l: 'Tous' }];
const TAB_URL = { actes: 'tabActes', articles: 'tabArticles', forfaits: 'tabForfaits', grilles: 'tabGrilles' };

export function init() {
  const root = document.querySelector('.cata[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const czone = $('czone'), fiBody = $('fiBody'), gview = $('gview'), hmenu = $('hmenu');
  const pick = $('pick'), pickQ = $('pickQ'), pickL = $('pickL');

  /* ═════════ Données ═════════ */

  let FAM = [], ACTES = [], ARTICLES = [], FORFAITS = [], GRILLES = [];
  const famDe = v => FAM.find(f => f.v === v) || { v, l: v || '—', t: 0 };
  const tva = v => famDe(v).t;
  const tvaTxt = t => fmtN(t, t % 1 ? 1 : 0) + ' %';
  const famOpts = () => FAM.map(f => ({ v: f.v, l: f.l, sub: tvaTxt(f.t) }));
  // The TTC figure the page shows for a stored price (in euros).
  const ttc = (price, pm, fam) => (price == null ? null : pm === 'incl' ? price / 100 : Math.round(price * (1 + tva(fam) / 100)) / 100);
  // The HT figure, a preview from the family's rate unless the price was entered HT.
  const htDe = x => (x.price == null ? null : x.pm === 'excl' ? x.price / 100 : x.prix / (1 + tva(x.fam) / 100));
  // What a « Prix TTC » field posts: the typed amount, mode 'incl'. A field left
  // on the figure it was shown (`shown`) keeps the stored price and its mode.
  const saisie = (x, prix, shown) => (x.price != null && prix === shown ? { price: x.price, price_mode: x.pm } : { price: Math.round(prix * 100), price_mode: 'incl' });

  function charger(d) {
    FAM = d.fam;
    ACTES = d.acts.map(a => ({ ...a, prix: ttc(a.price, a.pm, a.fam) }));
    ARTICLES = d.articles.map(a => ({ ...a, prix: ttc(a.price, a.pm, a.fam) }));
    FORFAITS = d.forfaits.map(f => ({ ...f, prix: f.mode === 'fixe' ? ttc(f.price, f.pm, f.fam) : null, comp: f.comp.map(l => ({ ...l, c: l.item })) }));
    GRILLES = d.grilles.map(g => ({ ...g, tarifs: g.tarifs.map(t => ({ ...t, c: t.item })) }));
    GRILLES.forEach(g => g.tarifs.forEach(t => { const it = item(t.c); t.p = ttc(t.price, t.pm, t.fam || (it ? it.fam : null)); }));
  }
  const LISTES = { actes: () => ACTES, articles: () => ARTICLES, forfaits: () => FORFAITS };
  const tous = () => [...ACTES, ...ARTICLES];
  const item = id => ACTES.find(a => a.id === id) || ARTICLES.find(a => a.id === id) || FORFAITS.find(f => f.id === id);
  const sommeDetail = f => r2(f.comp.reduce((s, l) => { const it = item(l.c); return s + (it && it.prix ? it.prix * l.q : 0); }, 0));
  const prixForfait = f => (f.mode === 'fixe' ? f.prix || 0 : sommeDetail(f));
  const condTxt = a => (a.uom === 'UNIT' ? 'À l\'unité' : a.uom === 'KG' ? 'Au kg' : `Par ${UOM[a.uom] || a.uom.toLowerCase()}`);
  const ligneLib = l => { const it = item(l.c); return it ? it.lib : (l.lib || l.code || 'Prestation supprimée'); };

  charger(P);

  /* ═════════ État ═════════ */

  const ST = {
    tab: P.tab, q: '', sel: new Set(), sort: { actes: { k: 'lib', d: 1 }, articles: { k: 'lib', d: 1 }, forfaits: { k: 'lib', d: 1 } },
    f: { actes: { cat: '', statut: 'cat' }, articles: { type: '', classe: '', stock: '', statut: 'cat' }, forfaits: { mode: '', statut: 'cat' } },
    fiche: null, draft: null, dirty: false, neuf: false, grille: (GRILLES.find(g => g.defaut) || GRILLES[0] || {}).id || null,
    page: { actes: 1, articles: 1, forfaits: 1 }, pp: 25, reg: new Map(), posting: false,
  };
  try { const v = +localStorage.getItem(PP_KEY); if ([25, 50, 100].includes(v)) ST.pp = v; } catch (_) { /* private mode */ }

  /* ═════════ Serveur ═════════ */

  async function poster(url, data = {}) {
    const body = new FormData();
    body.append('_token', P.token);
    Object.entries(data).forEach(([k, v]) => { if (v !== undefined) body.append(k, v === null ? '' : typeof v === 'boolean' ? (v ? '1' : '0') : String(v)); });
    const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' }, signal });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'L\'opération n\'a pas pu être enregistrée');
    return d;
  }
  async function recharger() {
    const r = await fetch(P.urls.data, { headers: { Accept: 'application/json' }, signal });
    if (!r.ok) throw new Error('Le catalogue n\'a pas pu être rechargé');
    charger(await r.json());
  }
  const url = (k, id, id2) => { let u = P.urls[k].replace(NIL, id); if (id2) u = u.replace(NIL, id2); return u; };
  const BASE = { acte: 'act', article: 'art', forfait: 'pkg' };
  const echec = e => { if (e.name !== 'AbortError') showToast(e.message, 'danger'); };

  /* ═════════ Filtres ═════════ */

  const FILTRES = {
    actes: [{ k: 'cat', l: 'Catégorie', o: Object.entries(CATS).map(([v, c]) => ({ v, l: c.l, c: c.c })), test: (a, v) => a.cat === v }],
    articles: [
      { k: 'type', l: 'Type', o: Object.entries(TYPES).map(([v, l]) => ({ v, l })), test: (a, v) => a.type === v },
      { k: 'classe', l: 'Délivrance', o: [{ v: 'ordo', l: 'Sur ordonnance' }, { v: 'controle', l: 'Substances contrôlées' }, { v: 'stup', l: 'Stupéfiants' }, { v: 'libre', l: 'Sans ordonnance' }],
        test: (a, v) => a.type === 'med' && (v === 'ordo' ? a.classe !== 'NONE' : v === 'controle' ? ['RX_CONTROLLED', 'RX_NARCOTIC'].includes(a.classe) : v === 'stup' ? a.classe === 'RX_NARCOTIC' : a.classe === 'NONE') },
      { k: 'stock', l: 'Stock', o: [{ v: 'bas', l: 'Sous le seuil' }, { v: 'non', l: 'Non suivi' }], test: (a, v) => (v === 'bas' ? a.suivi && !!a.stock && a.stock.q <= a.stock.seuil : !a.suivi) },
    ],
    forfaits: [{ k: 'mode', l: 'Tarification', o: [{ v: 'fixe', l: 'Tarif forfaitaire' }, { v: 'somme', l: 'Somme des prestations' }], test: (f, v) => f.mode === v }],
  };
  function rendreFiltres() {
    const box = $('fdd');
    box.innerHTML = '';
    if (ST.tab === 'grilles') return;
    const F = ST.f[ST.tab];
    [...FILTRES[ST.tab], { k: 'statut', l: 'Statut', o: STATUTS, sansTous: true }].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (F[fd.k] && !(fd.k === 'statut' && F[fd.k] === 'cat') ? ' actif' : '');
      box.appendChild(el);
      const opts = fd.sansTous ? fd.o : [{ v: '', l: 'Tous · ' + fd.l.toLowerCase() }, ...fd.o];
      creerDropdown(el, opts, F[fd.k], v => { F[fd.k] = v; ST.sel.clear(); ST.page[ST.tab] = 1; rendre(); });
      if (!F[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
    });
  }
  function visibles() {
    const F = ST.f[ST.tab], q = norm(ST.q);
    const L = LISTES[ST.tab]().filter(x => {
      if (F.statut === 'cat' && x.arch) return false;
      if (F.statut === 'arch' && !x.arch) return false;
      for (const fd of FILTRES[ST.tab]) if (F[fd.k] && !fd.test(x, F[fd.k])) return false;
      if (q && !norm([x.code, x.lib, x.desc, x.gtin, x.cat ? CATS[x.cat]?.l : '', famDe(x.fam).l].join(' ')).includes(q)) return false;
      return true;
    });
    const col = COLS[ST.tab].find(c => c.k === ST.sort[ST.tab].k), d = ST.sort[ST.tab].d;
    if (col && col.sort) L.sort((a, b) => { const x = col.sort(a), y = col.sort(b); return (typeof x === 'string' ? x.localeCompare(y, 'fr') : x - y) * d; });
    return L;
  }

  /* ═════════ Colonnes ═════════ */

  const famCell = x => `<span class="fam">${esc(famDe(x.fam).l)}<i class="${tva(x.fam) !== 20 ? 'r' : ''}">${tvaTxt(tva(x.fam))}</i></span>`;
  const durTxt = a => (a.dur >= 480 && a.dur % 60 === 0 ? `${a.dur / 60} h` : a.dur + ' min');
  const archP = x => (x.arch ? ' <span class="puce">Archivé</span>' : '');
  const stockCell = a => {
    if (!a.suivi) return '<span class="stk na">Non suivi</span>';
    if (!a.stock) return '<span class="stk na" data-tip="Aucune entrée en stock">—</span>';
    const q = a.stock.q, s = a.stock.seuil;
    return `<span class="stk${q <= s ? ' bas' : ''}" style="justify-content:flex-end" data-tip="Seuil d'alerte : ${fmtN(s, s % 1 ? 1 : 0)}">${fmtN(q, q % 1 ? 1 : 0)}<span class="bar"><i style="width:${Math.min(100, q / Math.max(1, s * 3) * 100)}%"></i></span></span>`;
  };
  const COLS = {
    actes: [
      { k: 'lib', l: 'Acte', sort: a => a.lib, html: a => `<div class="cid"><b>${esc(a.lib)}${archP(a)}</b><span><span class="code">${esc(a.code)}</span>${a.desc ? ' · ' + esc(a.desc) : ''}</span></div>` },
      { k: 'cat', l: 'Catégorie', sort: a => CATS[a.cat]?.l || '', html: a => { const c = CATS[a.cat] || CATS.OTHER; return `<span class="puce" style="background:${c.c}14;color:${c.c}"><span class="pdot" style="background:${c.c}"></span>${c.l}</span>`; } },
      { k: 'dur', l: 'Durée', cls: 'num', sort: a => a.dur, html: a => `<span class="code" style="color:var(--encre)">${durTxt(a)}</span>` },
      { k: 'flags', l: 'Pratique', xo: 1, html: a => `<div class="flags">${a.ag ? '<span class="puce ag">AG</span>' : ''}</div>` },
      { k: 'fam', l: 'Famille · TVA', xo: 1, sort: a => famDe(a.fam).l, html: famCell },
      { k: 'prix', l: 'Tarif TTC', cls: 'num', sort: a => a.prix, html: a => `<div class="cnum"><b>${fmtE(a.prix)}</b><span>HT ${fmtE(htDe(a))}</span></div>` },
    ],
    articles: [
      { k: 'lib', l: 'Article', sort: a => a.lib, html: a => `<div class="cid"><b>${esc(a.lib)}${archP(a)}</b><span><span class="code">${esc(a.code)}</span> · ${condTxt(a)}</span></div>` },
      { k: 'type', l: 'Type', xo: 1, sort: a => TYPES[a.type], html: a => `<span style="font-size:.8rem">${TYPES[a.type]}</span>` },
      { k: 'deliv', l: 'Délivrance', sort: a => (a.type === 'med' ? Object.keys(CLASSES).indexOf(a.classe) : -1), html: a => (a.type !== 'med' ? '<span class="stk na">—</span>'
        : `<div class="flags"><span class="puce ${CLASSES[a.classe][0]}">${CLASSES[a.classe][1]}</span>${a.auth ? '<span class="puce lg" data-tip="Lié au registre pharmaceutique">Registre</span>' : ''}</div>`) },
      { k: 'stock', l: 'Stock', cls: 'num', sort: a => (a.suivi && a.stock ? a.stock.q / Math.max(1, a.stock.seuil) : -1), html: stockCell },
      { k: 'achat', l: 'Achat HT', cls: 'num', xo: 1, html: () => '<div class="cnum mute" data-tip="Le prix d\'achat n\'est pas encore suivi dans Kiveto"><b>—</b><span></span></div>' },
      { k: 'prix', l: 'Prix TTC', cls: 'num', sort: a => a.prix, html: a => `<div class="cnum"><b>${fmtE(a.prix)}</b><span>${tva(a.fam) !== 20 ? 'TVA ' + tvaTxt(tva(a.fam)) : 'HT ' + fmtE(htDe(a))}</span></div>` },
    ],
    forfaits: [
      { k: 'lib', l: 'Forfait', sort: f => f.lib, html: f => `<div class="cid"><b>${esc(f.lib)}${archP(f)}</b><span><span class="code">${esc(f.code)}</span> · ${f.comp.length} prestation${f.comp.length > 1 ? 's' : ''}</span></div>` },
      { k: 'compo', l: 'Contenu', xo: 1, html: f => `<div class="compo">${f.comp.map(l => `${l.q}× ${esc(ligneLib(l))}`).join(' · ') || '<span style="color:var(--encre-35)">Vide</span>'}</div>` },
      { k: 'mode', l: 'Tarification', sort: f => f.mode, html: f => `<span style="font-size:.8rem">${f.mode === 'fixe' ? 'Forfaitaire' : 'Somme des prestations'}</span>` },
      { k: 'valid', l: 'Validité', xo: 1, html: () => '<span style="font-size:.8rem">—</span>' },
      { k: 'detail', l: 'Au détail', cls: 'num', xo: 1, sort: f => sommeDetail(f), html: f => `<div class="cnum mute"><b>${fmtE(sommeDetail(f))}</b></div>` },
      { k: 'prix', l: 'Prix TTC', cls: 'num', sort: f => prixForfait(f), html: f => { const s = sommeDetail(f), p = prixForfait(f), e = s ? (p - s) / s * 100 : 0;
        return `<div class="cnum"><b>${fmtE(p)}</b><span>${f.mode === 'fixe' ? `<span class="ecart ${e < 0 ? 'moins' : 'plus'}">${e < 0 ? '−' : '+'}${fmtN(Math.abs(e), 0)} %</span>` : '= détail'}</span></div>`; } },
    ],
  };

  /* ═════════ Rendu principal ═════════ */

  function compter() {
    const n = l => l.filter(x => !x.arch).length;
    $('nActes').textContent = n(ACTES);
    $('nArticles').textContent = n(ARTICLES);
    $('nForfaits').textContent = n(FORFAITS);
    $('nGrilles').textContent = GRILLES.length;
  }
  function pageDe(Ltot) {
    const n = Math.max(1, Math.ceil(Ltot.length / ST.pp));
    ST.page[ST.tab] = Math.min(Math.max(1, ST.page[ST.tab]), n);
    return Ltot.slice((ST.page[ST.tab] - 1) * ST.pp, ST.page[ST.tab] * ST.pp);
  }
  function rendrePager(tot, lbl) {
    const pg = ST.page[ST.tab], n = Math.max(1, Math.ceil(tot / ST.pp)), a = tot ? (pg - 1) * ST.pp + 1 : 0, b = Math.min(tot, pg * ST.pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> ${tot > 1 ? lbl[1] : lbl[0]}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${[25, 50, 100].map(v => `<button type="button" data-pp="${v}" class="${v === ST.pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg" style="cursor:default">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }
  function rendre() {
    compter();
    const g = ST.tab === 'grilles';
    czone.style.display = g ? 'none' : '';
    $('cfil').style.display = g ? 'none' : '';
    gview.classList.toggle('on', g);
    $('btnNewL').textContent = NEWL[ST.tab];
    if (g) { rendreGrilles(); majBulk(); return; }
    $('q').placeholder = PH[ST.tab];
    const Ltot = visibles(), L = pageDe(Ltot), cols = COLS[ST.tab], so = ST.sort[ST.tab];
    const allSel = L.length && L.every(x => ST.sel.has(x.id)), someSel = L.some(x => ST.sel.has(x.id));
    $('thead').innerHTML = `<tr><th class="selc"><input type="checkbox" class="ck${someSel && !allSel ? ' mix' : ''}" id="ckAll" ${allSel ? 'checked' : ''} aria-label="Tout sélectionner"></th>${cols.map(c =>
      `<th class="${c.cls || ''}${c.xo ? ' xo' : ''}${c.sort ? ' sortable' : ''}${so.k === c.k ? ' son' : ''}" data-k="${c.k}">${c.l}${so.k === c.k ? `<span class="sarr">${so.d > 0 ? '▲' : '▼'}</span>` : ''}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = L.map(x => `<tr data-c="${x.id}" class="${ST.sel.has(x.id) ? 'sel' : ''}${ST.fiche && ST.fiche.id === x.id ? ' cur' : ''}${x.arch ? ' arch' : ''}">
      <td class="selc"><input type="checkbox" class="ck" ${ST.sel.has(x.id) ? 'checked' : ''} aria-label="Sélectionner"></td>${cols.map(c => `<td class="${c.cls || ''}${c.xo ? ' xo' : ''}">${c.html(x)}</td>`).join('')}</tr>`).join('');
    const lbl = { actes: ['acte', 'actes'], articles: ['article', 'articles'], forfaits: ['forfait', 'forfaits'] }[ST.tab];
    const vide = !LISTES[ST.tab]().length;
    $('cempty').innerHTML = L.length ? '' : vide
      ? `<div class="cempty"><b>Aucun ${lbl[0]} au catalogue</b>Ajoutez le premier avec « ${NEWL[ST.tab]} ».</div>`
      : '<div class="cempty"><b>Aucun résultat</b>Modifiez la recherche ou les filtres.</div>';
    rendrePager(Ltot.length, lbl);
    rendreFiltres();
    majBulk();
  }
  $('cfoot').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || b.disabled) return;
    if (b.dataset.pp) { ST.pp = +b.dataset.pp; ST.page[ST.tab] = 1; try { localStorage.setItem(PP_KEY, String(ST.pp)); } catch (_) { /* private mode */ } }
    else ST.page[ST.tab] = +b.dataset.pg;
    rendre();
    root.querySelector('.cscroll').scrollTop = 0;
  }, { signal });
  $('thead').addEventListener('click', e => {
    if (e.target.id === 'ckAll') { const L = pageDe(visibles()); const all = L.every(x => ST.sel.has(x.id)); L.forEach(x => (all ? ST.sel.delete(x.id) : ST.sel.add(x.id))); rendre(); return; }
    const th = e.target.closest('th.sortable');
    if (!th) return;
    const s = ST.sort[ST.tab];
    if (s.k === th.dataset.k) s.d = -s.d; else { s.k = th.dataset.k; s.d = 1; }
    ST.page[ST.tab] = 1;
    rendre();
  }, { signal });
  $('tbody').addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.closest('.selc')) { const c = tr.dataset.c; if (ST.sel.has(c)) ST.sel.delete(c); else ST.sel.add(c); rendre(); return; }
    ouvrirFiche(item(tr.dataset.c));
  }, { signal });
  function allerOnglet(t) {
    ST.tab = t;
    ST.sel.clear();
    ST.q = '';
    $('q').value = '';
    root.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('on', x.dataset.t === t));
    try { history.replaceState(history.state, '', P.urls[TAB_URL[t]]); } catch (_) { /* sandboxed */ }
    rendre();
  }
  $('tabs').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || b.dataset.t === ST.tab) return;
    if (!fermerFiche()) return;
    allerOnglet(b.dataset.t);
  }, { signal });
  $('q').addEventListener('input', e => { ST.q = e.target.value; ST.page[ST.tab] = 1; rendre(); }, { signal });
  $('hmenuBtn').addEventListener('click', e => { e.stopPropagation(); hmenu.classList.toggle('open'); }, { signal });
  document.addEventListener('click', e => { if (!e.target.closest('#hmenu')) hmenu.classList.remove('open'); }, { signal });
  hmenu.querySelectorAll('.dd-it:not([disabled])').forEach(b => b.addEventListener('click', () => {
    hmenu.classList.remove('open');
    if (b.id === 'btnExport') showToast('Export du catalogue complet (CSV) — actes, articles, forfaits et grilles');
  }, { signal }));

  /* ═════════ Fiche ═════════ */

  function ouvrirFiche(x, neuf) {
    if (!x) return;
    if (ST.fiche && ST.fiche.id && ST.fiche.id === x.id && !neuf) return;
    if (!fermerFiche(!ST.dirty || ST.neuf)) return;
    ST.fiche = x;
    ST.neuf = !!neuf;
    ST.draft = structuredClone(x);
    ST.dirty = !!neuf;
    czone.classList.add('ouvert');
    rendreFiche();
    rendre();
    fiBody.scrollTop = 0;
    if (x.k === 'article' && x.auth) chargerRegistre(x.auth);
    if (neuf) setTimeout(() => fiBody.querySelector('[data-f="lib"]')?.focus(), 220);
  }
  function fermerFiche(silencieux) {
    if (!ST.fiche) return true;
    const avant = ST.fiche, draft = ST.draft, dirty = ST.dirty, neuf = ST.neuf;
    ST.fiche = null;
    ST.draft = null;
    ST.dirty = false;
    czone.classList.remove('ouvert');
    if (dirty && !silencieux) toastUndo('Modifications abandonnées', () => { ST.fiche = avant; ST.draft = draft; ST.dirty = true; ST.neuf = neuf; czone.classList.add('ouvert'); rendreFiche(); rendre(); });
    if (!silencieux) rendre();
    return true;
  }
  function vierge(tab) {
    const f0 = c => (FAM.find(f => f.v === c) || FAM[0] || { v: '' }).v;
    if (tab === 'actes') return { id: '', k: 'acte', code: '', lib: '', desc: '', cat: 'CONSULTATION', dur: 20, ag: false, fam: f0('veterinary.act.consultation'), price: null, pm: 'incl', prix: null, arch: false };
    if (tab === 'articles') return { id: '', k: 'article', code: '', lib: '', type: 'med', fam: f0('veterinary.drug.prescription'), price: null, pm: 'incl', prix: null, gtin: '', uom: 'UNIT', suivi: true, classe: 'RX', auth: null, arch: false, stock: null };
    return { id: '', k: 'forfait', code: '', lib: '', mode: 'fixe', price: null, pm: 'incl', prix: null, fam: f0('veterinary.act.preventive'), arch: false, comp: [] };
  }
  $('btnNew').addEventListener('click', () => {
    if (ST.tab === 'grilles') { ouvrirModale('mGrille'); setTimeout(() => $('ngNom').focus(), 50); return; }
    ouvrirFiche(vierge(ST.tab), true);
  }, { signal });

  /* — petits constructeurs — */
  const lab = (l, req, opt) => `<label class="flbl">${l}${req ? ' <span class="req">*</span>' : ''}${opt ? ` <span class="opt">${opt}</span>` : ''}</label>`;
  const inp = (f, v, o = {}) => `${o.u ? '<div class="inu">' : ''}<input class="finput${o.num ? ' num' : ''}"${f ? ` data-f="${f}"` : ''} value="${esc(v ?? '')}"${o.ph ? ` placeholder="${esc(o.ph)}"` : ''}${o.num ? ' inputmode="decimal"' : ''}${o.off ? ` disabled data-tip="${SOON}"` : ''}>${o.u ? `<i>${o.u}</i></div>` : ''}`;
  const off = (v, o = {}) => inp('', v, { ...o, off: 1 });
  const ro = (v, mono) => `<div class="ro${mono ? ' mono' : ''}">${esc(v || '—')}${ICO.lock}</div>`;
  const sw = (f, on, t, s, lock) => `<div class="swrow${lock ? ' lock' : ''}"${lock ? ` data-tip="${lock === true ? SOON : lock}"` : ''}><span class="sw${on ? ' on' : ''}"${lock ? '' : ` data-sw="${f}" tabindex="0"`} role="switch" aria-checked="${!!on}"${lock ? ' aria-disabled="true"' : ''}></span><div><b>${t}</b>${s ? `<small>${s}</small>` : ''}</div></div>`;
  const seg = (f, opts, v, lock) => `<div class="seg${lock ? ' off' : ''}"${lock ? '' : ` data-seg="${f}"`}>${opts.map(([k, l]) => `<button type="button" data-v="${k}" class="${String(k) === String(v) ? 'on' : ''}"${lock && String(k) !== String(v) ? ` disabled data-tip="${lock === true ? SOON : lock}"` : ''}>${l}</button>`).join('')}</div>`;
  const ddm = (f, lock) => `<div class="dd${lock ? ' off' : ''}" data-dd="${f}"${lock ? ` data-tip="${SOON}"` : ''}></div>`;
  const sec = (t, body, tag, cls) => `<section class="fs${cls ? ' ' + cls : ''}"><h3>${t}${tag ? `<span class="tag">${tag}</span>` : ''}</h3>${body}</section>`;
  const chips = (f, all, sel, lock) => `<div class="chips${lock === 'reg' ? ' lock' : lock ? ' off' : ''}"${lock ? '' : ` data-chips="${f}"`}${lock && lock !== 'reg' ? ` data-tip="${SOON}"` : ''}>${all.map(e => `<button type="button" class="chip${sel.includes(e) ? ' on' : ''}" data-v="${esc(e)}"${lock ? ' disabled' : ''}>${esc(e)}</button>`).join('')}</div>`;
  const soon = '<span class="soon">Bientôt</span>';
  const lockTag = `<span class="lockt">${ICO.lock}Registre</span>`;
  const calcTitre = t => `<div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--encre-35);margin-bottom:4px">${t}</div>`;
  const codeChamp = (d, ph) => (ST.neuf ? inp('code', d.code, { ph }) : ro(d.code, 1));

  function htmlActe(d) {
    const t = tva(d.fam), ht = d.prix != null ? d.prix / (1 + t / 100) : null;
    const grillesTxt = GRILLES.filter(g => !g.defaut).map(g => { const tp = g.tarifs.find(x => x.c === d.id); return tp ? `<div><span>${esc(g.lib)}</span><b>${fmtE(tp.p)}</b></div>` : ''; }).join('');
    return `
    ${sec('Identification', `
      <div class="frow">${lab('Libellé', 1)}${inp('lib', d.lib, { ph: 'Consultation standard' })}</div>
      <div class="frow2"><div>${lab('Code', 1)}${codeChamp(d, 'CONS_STD')}</div><div>${lab('Catégorie')}${ddm('cat')}</div></div>
      <div class="frow">${lab('Description', 0, '· visible sur les devis et la prise de RDV en ligne')}<textarea class="ftext" data-f="desc" rows="2" style="min-height:52px">${esc(d.desc)}</textarea></div>`)}
    ${sec('Tarif', `
      <div class="frow3">
        <div>${lab('Prix TTC', 1)}${inp('prix', d.prix != null ? fmtN(d.prix) : '', { num: 1, u: '€', ph: '0,00' })}</div>
        <div>${lab('Facturé')}${ddm('unite', 1)}</div>
        <div>${lab('Prix HT')}<div class="ro mono" id="vHt">${ht != null ? fmtE(ht) : '—'}</div></div>
      </div>
      <div class="frow">${lab('Famille comptable · TVA')}${ddm('fam')}</div>
      ${sw('gabOn', false, 'Le prix varie selon le gabarit', 'Le bon prix est choisi d\'après l\'espèce et le dernier poids de l\'animal, en consultation et sur le devis.', true)}
      ${grillesTxt ? `<div class="calc" style="display:block">${calcTitre('Dans les grilles')}<div class="hist">${grillesTxt}</div></div>` : ''}
      ${!ST.neuf ? '<p class="hint">Un nouveau prix ne touche pas les consultations déjà facturées : elles gardent le prix du jour.</p>' : ''}`)}
    ${sec('Agenda', `
      <div class="frow2"><div>${lab('Durée')}${inp('dur', d.dur, { num: 1, u: 'min' })}</div><div>${lab('Réalisé par')}${seg('par', [['V', 'Vétérinaire'], ['A', 'ASV']], 'V', true)}</div></div>
      <div class="frow">${lab('Espèces')}${chips('esp', ESP, [], true)}</div>
      ${sw('ligne', false, 'Réservable en ligne', 'Proposé comme motif de RDV sur la page de réservation, dans les créneaux qui l\'acceptent au planning.', true)}`)}
    ${sec('Clinique', `
      ${sw('ag', d.ag, 'Sous anesthésie générale', 'Bloque un créneau de chirurgie au planning et ajoute le suivi anesthésique à la consultation.')}
      ${d.ag ? sw('consent', false, 'Consentement éclairé et devis signés avant l\'acte', 'Le client signe sur tablette ; l\'acte ne peut être réalisé sans.', true) : ''}
      <div class="swrow"><span style="width:32px"></span><div><b>Rappel</b><small>Programme l'échéance dans le dossier de l'animal (« Dû selon le dossier » en consultation, relance au client).</small><div style="margin-top:8px;max-width:220px">${ddm('rappel', 1)}</div></div></div>
      ${sw('affiche', false, 'Affiché en salle d\'attente', 'Fait partie des prestations courantes dont le prix doit être affiché ; reprise sur l\'affiche imprimable.', true)}`)}
    ${d.cat === 'VACCINATION' ? sec('Vaccin administré', `
      <div class="frow">${ddm('vaccin', 1)}</div>
      <p class="hint">À l'injection, le n° de lot et la péremption sont demandés puis reportés sur le certificat et le passeport européen ; la dose sort du stock.</p>`, soon) : ''}
    ${sec('Consommés automatiquement', `
      <div class="lst"><p class="hint" style="margin:0">Aucun article.</p></div>
      <button type="button" class="addl" disabled data-tip="${SOON}">${ICO.plus}Ajouter un article</button>
      <p class="hint">Sortis du stock à chaque acte, sans être facturés en plus : le prix de l'acte les inclut.</p>`, soon)}`;
  }

  function htmlArticle(d) {
    const t = tva(d.fam), med = d.type === 'med', R = med && d.auth ? ST.reg.get(d.auth) : null, L = med && !!d.auth;
    const regBand = !med ? '' : L
      ? `<div class="bandeau reg">${ICO.ok}<div><b>Lié au registre pharmaceutique</b>Laboratoire, forme et classe de délivrance suivent la fiche officielle de l'autorisation de mise sur le marché.</div></div>`
      : `<div class="bandeau info">${ICO.info}<div><b>${ST.neuf ? 'Partir du registre pharmaceutique' : 'Non lié au registre pharmaceutique'}</b>${ST.neuf ? 'Libellé, laboratoire, code-barres et classe de délivrance se remplissent seuls depuis l\'autorisation de mise sur le marché.' : 'La classe de délivrance est saisie à la main et ne suit pas les mises à jour du registre.'}<div class="act"><button type="button" id="btnReg"${ST.neuf ? '' : ` disabled data-tip="${SOON}"`}>Rechercher dans le registre</button></div></div></div>`;
    const rv = k => (L ? (R ? R[k] : '…') : '');
    const esps = R ? [...new Set(R.withdrawals.map(w => w.species))] : [];
    const ta = R ? R.withdrawals.filter(w => w.days != null) : [];
    return `
    ${regBand}
    ${sec('Identification', `
      <div class="frow">${lab('Type')}${seg('type', Object.entries(TYPES), d.type, ST.neuf ? false : 'Le type d\'un article ne change pas une fois créé')}</div>
      <div class="frow">${lab('Libellé', 1)}${inp('lib', d.lib, { ph: med ? 'Amoxival 500 mg comprimés' : 'Collerette élisabéthaine' })}</div>
      <div class="frow2"><div>${lab('Code', 1)}${codeChamp(d, 'AMOX-500')}</div><div>${lab('Code-barres (GTIN)', 0, med ? '' : '· facultatif')}${L && d.gtin ? ro(d.gtin, 1) : inp('gtin', d.gtin, { ph: '0360…' })}</div></div>
      ${med ? `<div class="frow2"><div>${lab('Laboratoire')}${L ? ro(rv('labo')) : off('')}</div><div>${lab('Forme')}${L ? ro(rv('forme')) : off('')}</div></div>
        <div class="frow">${lab('Principes actifs')}${L ? ro(R ? R.substances.join(', ') : '…') : off('')}</div>`
        : `<div class="frow">${lab(d.type === 'ali' ? 'Marque' : 'Fabricant', 0, '· facultatif')}${off('')}</div>`}
      ${d.type === 'ali' ? `<div class="frow">${lab('Espèces')}${chips('esp', ['Chien', 'Chat', 'NAC'], [], true)}</div>` : ''}`)}
    ${med ? sec('Réglementation', `
      <div class="frow">${lab('Délivrance')}${L ? ro(CLASSES[d.classe][1]) : seg('classe', Object.entries(CLASSES).map(([k, v]) => [k, v[1]]), d.classe)}<p class="hint">${CLASSE_TXT[d.classe]}</p></div>
      <div class="frow">${lab('Usage')}${seg('usage', [['deliv', 'Délivré au client'], ['interne', 'Usage interne uniquement']], 'deliv', true)}</div>
      <div class="frow">${lab('Espèces de l\'AMM')}${L && esps.length ? chips('esp', esps, esps, 'reg') : chips('esp', ESP, [], true)}<p class="hint">Prescrit à une autre espèce : la consultation le signale comme une prescription hors AMM (cascade).</p></div>
      ${ta.length ? `<div class="frow">${lab('Temps d\'attente')}<div class="frow3" style="margin:0">${ta.slice(0, 3).map(w => `<div>${ro(`${w.days} j · ${esc(w.purpose || w.species)}`)}</div>`).join('')}</div>
        <p class="hint">Reportés d'office sur l'ordonnance et le registre d'élevage pour les animaux producteurs de denrées.</p></div>` : ''}
      ${sw('antibio', false, 'Antibiotique', 'Cessions déclarées chaque année à l\'ANSES (Calypso) : l\'export est prêt dans Stock.', true)}
      ${sw('lot', d.classe === 'RX_NARCOTIC', 'Lot tracé à chaque administration', 'N° de lot et péremption demandés à l\'injection ou à la délivrance (vaccins, stupéfiants).', true)}`, L ? lockTag : '') : ''}
    ${sec(med ? 'Délivrance et posologie' : 'Vente', `
      <div class="frow2"><div>${lab('Conditionnement')}<div style="display:flex;gap:8px"><div style="width:96px">${off('1', { num: 1 })}</div><div style="flex:1">${ddm('uom')}</div></div></div>
        <div>${lab('Vendu')}${seg('vente', [['cond', d.type === 'ali' ? 'Au sac' : 'Entier'], ['unite', d.type === 'ali' ? 'Au kg' : 'À l\'unité']], 'cond', true)}</div></div>
      ${med ? `<div class="frow">${lab('Conservation')}${seg('froid', [['false', 'Température ambiante'], ['true', 'Chaîne du froid 2–8 °C']], 'false', true)}</div>` : ''}
      ${!med ? sw('facture', true, 'Facturé au client', 'Désactivé : consommé pendant les actes et sorti du stock, sans ligne sur la facture.', true) : ''}
      ${med ? `<div class="frow" style="margin-top:4px">${lab('Posologie proposée', 0, '· pré-remplit le plan de traitement en consultation')}
        <div class="frow3" style="margin-bottom:8px"><div>${off('', { u: 'mg/kg', ph: 'dose' })}</div><div>${ddm('poso.freq', 1)}</div><div>${off('', { num: 1, u: 'jours', ph: 'durée' })}</div></div>
        ${ddm('poso.voie', 1)}</div>` : ''}`)}
    ${sec('Achat et prix', `
      <div class="frow2"><div>${lab('Fournisseur')}${ddm('fourn', 1)}</div><div>${lab('Réf. fournisseur', 0, '· facultatif')}${off('')}</div></div>
      <div class="frow3"><div>${lab('Achat HT')}${off('', { num: 1, u: '€', ph: '0,00' })}</div><div>${lab('Coefficient')}${off('', { num: 1, ph: '1,80' })}</div><div>${lab('Prix de vente TTC', 1)}${inp('prix', d.prix != null ? fmtN(d.prix) : '', { num: 1, u: '€', ph: '0,00' })}</div></div>
      <div class="frow">${lab('Famille comptable · TVA')}${ddm('fam')}</div>
      <div class="calc" id="vMarge">${margeHtml(d, t)}</div>`)}
    ${sec('Stock', `
      ${sw('suivi', d.suivi, 'Suivre le stock', 'Entrées, sorties, lots et péremptions sont gérés dans Stock.')}
      ${d.suivi ? `<div class="frow2" style="margin-top:4px"><div>${lab('Seuil d\'alerte')}${ro(!ST.neuf && d.stock ? fmtN(d.stock.seuil, d.stock.seuil % 1 ? 1 : 0) : '—', 1)}</div><div>${lab('En stock')}<div class="ro mono">${ST.neuf || !d.stock ? '—' : `${fmtN(d.stock.q, d.stock.q % 1 ? 1 : 0)} · ${d.stock.lots} lot${d.stock.lots > 1 ? 's' : ''}`}</div></div></div>
        ${!ST.neuf ? `<a class="lien" href="${esc(P.urls.stock)}?article=${encodeURIComponent(d.id)}">Ouvrir dans Stock ${ICO.ext}</a>` : ''}` : ''}`)}`;
  }
  function margeHtml(d, t) {
    if (!d.prix) return 'Renseignez le prix de vente.';
    return `HT <b>${fmtE(d.prix / (1 + t / 100))}</b><span class="sep"></span>TVA <b>${tvaTxt(t)}</b><span class="sep"></span>Prix d'achat inconnu : marge non calculée`;
  }

  function ffCalc(d) {
    const s = sommeDetail(d), p = prixForfait(d), e = s ? (p - s) / s * 100 : 0;
    const taux = {};
    d.comp.forEach(l => { const it = item(l.c); if (it && it.prix) { const t = tva(it.fam); taux[t] = (taux[t] || 0) + it.prix * l.q; } });
    return `${d.mode === 'fixe' && d.prix ? `${e <= 0 ? 'Remise' : 'Surcoût'} <b class="${e <= 0 ? 'ok' : 'ko'}">${fmtE(Math.abs(p - s))} · ${fmtN(Math.abs(e), 0)} %</b><span class="sep"></span>` : ''}TVA ventilée par prestation : ${Object.entries(taux).map(([t, v]) => `<b>${tvaTxt(+t)}</b> sur ${fmtE(d.mode === 'fixe' && s ? v * p / s : v)}`).join(' · ') || '—'}`;
  }
  function htmlForfait(d) {
    const s = sommeDetail(d);
    return `
    ${sec('Identification', `
      <div class="frow">${lab('Libellé', 1)}${inp('lib', d.lib, { ph: 'Pack chaton première année' })}</div>
      <div class="frow2"><div>${lab('Code', 1)}${codeChamp(d, 'PKG-CHATON')}</div><div>${lab('Famille comptable')}${ddm('fam')}</div></div>
      <div class="frow">${lab('Description', 0, '· visible sur le devis')}<textarea class="ftext" rows="2" style="min-height:52px" disabled data-tip="${SOON}"></textarea></div>`)}
    ${sec('Composition', `
      <div class="lst">${d.comp.map((l, i) => { const it = item(l.c); return `<div class="li"><div class="lt"><b>${esc(ligneLib(l))}</b><span>${esc(it ? it.code : l.code)} · ${it && it.prix ? fmtE(it.prix) : '—'}</span></div>
        <div class="qty"><button type="button" data-q="${i}" data-d="-1" aria-label="Moins">−</button><input data-qi="${i}" value="${l.q}" aria-label="Quantité"><button type="button" data-q="${i}" data-d="1" aria-label="Plus">+</button></div>
        <span class="m" style="width:76px;text-align:right">${it && it.prix ? fmtE(it.prix * l.q) : ''}</span><button type="button" class="rm" data-rm="comp.${i}" data-tip="Retirer" aria-label="Retirer">${ICO.rm}</button></div>`; }).join('') || '<p class="hint" style="margin:0">Ajoutez les actes et articles compris.</p>'}</div>
      <button type="button" class="addl" id="addComp">${ICO.plus}Ajouter un acte ou un article</button>`)}
    ${sec('Tarification', `
      <div class="frow">${seg('mode', [['fixe', 'Prix forfaitaire'], ['somme', 'Somme des prestations']], d.mode)}</div>
      <div class="frow2"><div>${lab('Prix TTC', d.mode === 'fixe')}${d.mode === 'fixe' ? inp('prix', d.prix != null ? fmtN(d.prix) : '', { num: 1, u: '€' }) : ro(fmtE(s))}</div>
        <div>${lab('Au détail')}<div class="ro mono" id="vDet">${fmtE(s)}</div></div></div>
      <div class="calc" id="vFf">${ffCalc(d)}</div>
      <p class="hint">Sur la facture, le prix du forfait est réparti entre les prestations au prorata, pour que chaque taux de TVA soit juste.</p>`)}
    ${sec('Validité et suivi', `
      <div class="frow">${lab('Valable')}${seg('valid', [[0, 'Sans limite'], [6, '6 mois'], [12, '12 mois'], [24, '24 mois']], 0, true)}</div>
      ${sw('decompte', false, 'Payé à l\'achat, décompté à chaque visite', 'Le solde (« 1 consultation restante ») s\'affiche dans le dossier de l\'animal et la consultation le propose avant de facturer.', true)}`, soon)}`;
  }

  function rendreFiche() {
    const d = ST.draft, x = ST.fiche;
    if (!d) return;
    const kind = d.k, ico = kind === 'acte' ? ICO.acte : kind === 'forfait' ? ICO.pack : d.type === 'med' ? ICO.med : ICO.box;
    const sous = kind === 'acte' ? `<span class="code">${esc(d.code || 'Nouveau')}</span> · ${(CATS[d.cat] || CATS.OTHER).l}`
      : kind === 'article' ? `<span class="code">${esc(d.code || 'Nouveau')}</span> · ${TYPES[d.type]}${d.type === 'med' ? ` <span class="puce ${CLASSES[d.classe][0]}">${CLASSES[d.classe][1]}</span>` : ''}`
        : `<span class="code">${esc(d.code || 'Nouveau')}</span> · ${d.comp.length} prestation${d.comp.length > 1 ? 's' : ''}`;
    $('fiHead').innerHTML = `<div class="fi-ico">${ico}</div><div class="fi-t"><b>${esc(d.lib || NEWL[ST.tab])}</b><span>${sous}${x.arch ? ' · <span class="puce">Archivé</span>' : ''}</span></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;
    const st = fiBody.scrollTop;
    fiBody.innerHTML = kind === 'acte' ? htmlActe(d) : kind === 'article' ? htmlArticle(d) : htmlForfait(d);
    fiBody.scrollTop = st;
    if (x.arch) fiBody.querySelectorAll('input,textarea,.seg button,.chip,.addl,.rm,.qty button,#btnReg').forEach(el => { el.disabled = true; });
    monterDd(d);
    majFoot();
    $('fiX').addEventListener('click', () => fermerFiche());
  }
  function monterDd(d) {
    const M = {
      cat: () => Object.entries(CATS).map(([v, c]) => ({ v, l: c.l, c: c.c })),
      fam: () => famOpts(),
      uom: () => Object.entries(UOM).map(([v, l]) => ({ v, l })),
      unite: () => [{ v: 'acte', l: 'Par acte' }],
      rappel: () => [{ v: '0', l: 'Aucun' }],
      vaccin: () => [],
      fourn: () => [],
      'poso.freq': () => [],
      'poso.voie': () => [],
    };
    const PHD = { vaccin: 'Choisir le vaccin…', fourn: 'Fournisseur', 'poso.freq': 'Fréquence', 'poso.voie': 'Voie' };
    const DEF = { unite: 'acte', rappel: '0' };
    fiBody.querySelectorAll('[data-dd]').forEach(el => {
      const f = el.dataset.dd, locked = el.classList.contains('off');
      const v = locked ? DEF[f] ?? null : d[f];
      creerDropdown(el, M[f](), v == null ? null : String(v), nv => { d[f] = nv; marquer(); rendreFiche(); }, PHD[f]);
      if (locked || ST.fiche.arch) el.querySelector('.dd-btn').disabled = true;
    });
  }
  function marquer() { ST.dirty = true; majFoot(); }
  const CODE_RE = /^[A-Z0-9_-]{2,20}$/;
  const doublon = d => ST.neuf && !!LISTES[{ acte: 'actes', article: 'articles', forfait: 'forfaits' }[d.k]]().find(x => x.code === d.code);
  function valide(d) {
    if (!d.lib || !d.lib.trim() || !CODE_RE.test(d.code) || doublon(d)) return false;
    if (d.k === 'forfait') return d.mode === 'somme' || d.prix > 0;
    if (d.k === 'article' && d.gtin && !/^\d{8,14}$/.test(d.gtin)) return false;
    return d.prix > 0 && (d.k !== 'acte' || d.dur > 0);
  }
  function majFoot() {
    const d = ST.draft, ok = valide(d), f = $('fiFoot');
    f.className = 'fi-foot' + (ST.dirty && !ST.neuf ? ' d' : '');
    f.innerHTML = `${ST.neuf ? '' : `<button type="button" class="btn ghost-danger" id="fiArch">${ST.fiche.arch ? 'Remettre au catalogue' : 'Archiver'}</button>`}
      <span class="sp"></span><span class="dirty">Modifications non enregistrées</span>
      <button type="button" class="btn" id="fiAnn">${ST.neuf ? 'Annuler' : 'Fermer'}</button>
      <button type="button" class="btn primaire" id="fiOk" ${ST.dirty && ok && !ST.posting ? '' : 'disabled'}>${ST.neuf ? 'Ajouter au catalogue' : 'Enregistrer'}</button>`;
    $('fiAnn').onclick = () => fermerFiche();
    $('fiOk').onclick = enregistrer;
    const a = $('fiArch');
    if (a) a.onclick = archiver;
  }

  /* What a sheet posts, from its draft. */
  function champs(d) {
    // The « Prix TTC » field posts what it shows, all taxes included.
    const p = d.prix != null ? saisie(d, d.prix, ttc(d.price, d.pm, d.fam)) : { price: '', price_mode: '' };
    if (d.k === 'acte') return { name: d.lib.trim(), code: d.code, description: d.desc || '', category: d.cat, tax_category: d.fam, ...p, duration: d.dur, requires_anesthesia: d.ag };
    if (d.k === 'article') {
      const c = { name: d.lib.trim(), code: d.code, kind: KIND[d.type], tax_category: d.fam, ...p, gtin: d.gtin || '', unit: d.uom, track_stock: d.suivi };
      if (d.type === 'med') c.prescription_class = d.classe;
      if (ST.neuf && d.auth) c.authorization_ref = d.auth;
      return c;
    }
    return { name: d.lib.trim(), code: d.code, tax_category: d.fam, pricing_mode: d.mode === 'fixe' ? 'FIXED_PRICE' : 'SUM_OF_COMPONENTS', ...(d.mode === 'fixe' ? p : { price: '', price_mode: '' }), components: JSON.stringify(d.comp.map(l => { const it = item(l.c); return { type: l.type || (it && it.k === 'acte' ? 'ACT' : 'ARTICLE'), item: l.c, q: l.q }; })) };
  }
  async function enregistrer() {
    const d = ST.draft;
    if (!valide(d) || ST.posting) return;
    ST.posting = true;
    majFoot();
    const neuf = ST.neuf, avant = neuf ? null : champs({ ...ST.fiche }), base = BASE[d.k];
    try {
      const r = await poster(neuf ? P.urls[base + 'Create'] : url(base + 'Update', d.id), champs(d));
      const id = neuf ? r.id : d.id;
      await recharger();
      ST.posting = false;
      ST.fiche = item(id);
      ST.draft = structuredClone(ST.fiche);
      ST.dirty = false;
      ST.neuf = false;
      rendreFiche();
      rendre();
      if (neuf) showToast(`« ${d.lib.trim()} » ajouté au catalogue`);
      else toastUndo(`« ${d.lib.trim()} » enregistré`, () => annulerEdition(d.k, id, avant));
    } catch (e) {
      ST.posting = false;
      majFoot();
      echec(e);
    }
  }
  async function annulerEdition(k, id, avant) {
    try {
      await poster(url(BASE[k] + 'Update', id), avant);
      await recharger();
      if (ST.fiche && ST.fiche.id === id) { ST.fiche = item(id); ST.draft = structuredClone(ST.fiche); ST.dirty = false; rendreFiche(); }
      rendre();
    } catch (e) { echec(e); }
  }
  async function basculer(x, archiver) {
    await poster(url(BASE[x.k] + (archiver ? 'Archive' : 'Restore'), x.id));
  }
  async function archiver() {
    const x = ST.fiche, v = !x.arch;
    try {
      await basculer(x, v);
      fermerFiche(true);
      await recharger();
      rendre();
      toastUndo(v ? `« ${x.lib} » archivé — il ne sera plus proposé` : `« ${x.lib} » remis au catalogue`, async () => {
        try { await basculer(x, !v); await recharger(); rendre(); } catch (e) { echec(e); }
      });
    } catch (e) { echec(e); }
  }

  /* — interactions de la fiche (délégation) — */
  fiBody.addEventListener('input', e => {
    const d = ST.draft, el = e.target;
    if (el.dataset.qi != null) { const v = parseN(el.value); if (v > 0) { d.comp[+el.dataset.qi].q = Math.round(v); marquer(); majCalc(); } return; }
    const f = el.dataset.f;
    if (!f) return;
    if (f === 'prix') d.prix = parseN(el.value);
    else if (f === 'dur') d.dur = Math.round(parseN(el.value) || 0);
    else if (f === 'code') {
      d.code = el.value.toUpperCase().replace(/\s+/g, '_');
      if (el.value !== d.code) { const p = el.selectionStart; el.value = d.code; el.setSelectionRange(p, p); }
      d._codeManuel = true;
    } else if (f === 'gtin') d.gtin = el.value.replace(/\s/g, '');
    else {
      d[f] = el.value;
      if (f === 'lib' && ST.neuf && !d._codeManuel) {
        d.code = genCode(d);
        const ci = fiBody.querySelector('[data-f="code"]');
        if (ci) ci.value = d.code;
      }
    }
    if (f === 'code' || f === 'lib') { const c = fiBody.querySelector('[data-f="code"]'); c?.classList.toggle('err', !!d.code && (doublon(d) || (d.code.length > 1 && !CODE_RE.test(d.code)))); }
    if (f === 'gtin') el.classList.toggle('err', !!d.gtin && !/^\d{8,14}$/.test(d.gtin));
    marquer();
    majCalc();
  }, { signal });
  function genCode(d) {
    const w = norm(d.lib).toUpperCase().split(/[^A-Z0-9]+/).filter(m => m.length > 2 || /\d/.test(m));
    return (w.length > 1 ? [w[0].slice(0, 4), ...w.slice(1).map(m => m.slice(0, 8))] : w).join(d.k === 'acte' ? '_' : '-').slice(0, 20);
  }
  function majCalc() {
    const d = ST.draft, t = tva(d.fam);
    const ht = $('vHt');
    if (ht) ht.textContent = d.prix != null ? fmtE(d.prix / (1 + t / 100)) : '—';
    const m = $('vMarge');
    if (m) m.innerHTML = margeHtml(d, t);
    if (d.k === 'forfait') { const v = $('vFf'); if (v) v.innerHTML = ffCalc(d); const s = $('vDet'); if (s) s.textContent = fmtE(sommeDetail(d)); }
    root.querySelector('#fiHead .fi-t b').textContent = d.lib || NEWL[ST.tab];
  }
  fiBody.addEventListener('click', e => {
    const d = ST.draft, t = e.target;
    if (!d || ST.fiche.arch) return;
    const sB = t.closest('[data-seg] button');
    if (sB) {
      const f = sB.closest('[data-seg]').dataset.seg, v = sB.dataset.v;
      if (f === 'type') {
        if (v !== 'med') { d.classe = 'NONE'; d.auth = null; if (d.fam.startsWith('veterinary.drug')) d.fam = v === 'ali' ? 'veterinary.food.therapeutic' : 'veterinary.consumable.medical'; }
        else { if (!d.fam.startsWith('veterinary.drug')) d.fam = 'veterinary.drug.prescription'; d.classe = 'RX'; }
      }
      if (f === 'mode' && v === 'fixe' && !d.prix) d.prix = sommeDetail(d) || null;
      d[f] = v;
      marquer();
      rendreFiche();
      return;
    }
    const swE = t.closest('[data-sw]');
    if (swE) { const f = swE.dataset.sw; d[f] = !d[f]; marquer(); rendreFiche(); return; }
    const q = t.closest('[data-q]');
    if (q) { const l = d.comp[+q.dataset.q]; l.q = Math.max(1, l.q + (+q.dataset.d)); marquer(); rendreFiche(); return; }
    const rm = t.closest('[data-rm]');
    if (rm) { d.comp.splice(+rm.dataset.rm.split('.')[1], 1); marquer(); rendreFiche(); return; }
    if (t.closest('#btnReg')) {
      ouvrirPick(t.closest('#btnReg'), 'registre', async r => {
        try {
          const ref = await chargerRegistre(r.id);
          d.auth = ref.id;
          if (!d.lib) d.lib = ref.lib;
          if (ref.gtin) d.gtin = ref.gtin;
          d.classe = ref.classe;
          if (ST.neuf && !d._codeManuel) d.code = genCode(d);
          marquer();
          rendreFiche();
        } catch (err) { echec(err); }
      });
      return;
    }
    if (t.closest('#addComp')) {
      ouvrirPick(t.closest('#addComp'), 'tous', a => {
        const ex = d.comp.find(l => l.c === a.id);
        if (ex) ex.q++; else d.comp.push({ c: a.id, item: a.id, type: a.k === 'acte' ? 'ACT' : 'ARTICLE', code: a.code, lib: a.lib, q: 1 });
        marquer();
        rendreFiche();
      });
    }
  }, { signal });
  fiBody.addEventListener('keydown', e => { if ((e.key === ' ' || e.key === 'Enter') && e.target.matches('[data-sw]')) { e.preventDefault(); e.target.click(); } }, { signal });

  /* What the registry says of a linked drug, fetched once per authorisation. */
  async function chargerRegistre(id) {
    if (ST.reg.has(id) && ST.reg.get(id)) return ST.reg.get(id);
    ST.reg.set(id, null);
    const r = await fetch(url('registryItem', id), { headers: { Accept: 'application/json' }, signal });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) { ST.reg.delete(id); throw new Error(d.error || 'Le registre n\'a pas répondu'); }
    ST.reg.set(id, d);
    if (ST.draft && ST.draft.auth === id) rendreFiche();
    return d;
  }

  /* ═════════ Sélecteur ═════════ */

  let pickCb = null, pickSrc = 'tous', pickHl = 0, pickRes = [], pickSeq = 0;
  function ouvrirPick(anchor, src, cb) {
    pickCb = cb;
    pickSrc = src;
    pickQ.value = '';
    pickHl = 0;
    pickRes = [];
    pickQ.placeholder = src === 'registre' ? 'Nom ou code-barres (GTIN)…' : src === 'articles' ? 'Rechercher un article…' : 'Rechercher un acte ou un article…';
    const r = anchor.getBoundingClientRect(), W = Math.min(560, Math.max(380, r.width));
    pick.style.width = W + 'px';
    pick.classList.add('open');
    rendrePick();
    const H = pick.offsetHeight;
    pick.style.left = Math.min(Math.max(8, r.left), innerWidth - W - 8) + 'px';
    pick.style.top = (r.bottom + 6 + H > innerHeight - 8 ? Math.max(8, r.top - H - 6) : r.bottom + 6) + 'px';
    pickQ.focus();
  }
  function dessinerPick() {
    pickHl = Math.min(pickHl, Math.max(0, pickRes.length - 1));
    pickL.innerHTML = pickRes.map((x, i) => `<button type="button" class="pick-it${i === pickHl ? ' hl' : ''}" data-i="${i}"><div class="lt"><b>${esc(x.lib)}</b><span>${pickSrc === 'registre' ? `${esc(x.labo)}${x.gtin ? ' · ' + esc(x.gtin) : ''}` : esc(x.code)}</span></div>
      ${pickSrc === 'registre' ? '' : `<span class="k">${x.k === 'acte' ? 'Acte' : TYPES[x.type]}</span><span class="m">${x.prix ? fmtE(x.prix) : ''}</span>`}</button>`).join('')
      || `<div class="pick-vide">${pickSrc === 'registre' && !pickQ.value.trim() ? 'Tapez le nom du médicament' : 'Aucun résultat'}</div>`;
  }
  function rendrePick() {
    const q = norm(pickQ.value);
    if (pickSrc === 'registre') {
      clearTimeout(regTimer);
      if (!q) { pickRes = []; dessinerPick(); return; }
      const seq = ++pickSeq;
      regTimer = setTimeout(async () => {
        try {
          const r = await fetch(`${P.urls.registry}?q=${encodeURIComponent(pickQ.value.trim())}`, { headers: { Accept: 'application/json' }, signal });
          const d = await r.json();
          if (seq !== pickSeq) return;
          pickRes = Array.isArray(d) ? d : [];
          dessinerPick();
        } catch (e) { echec(e); }
      }, 200);
      return;
    }
    const src = (pickSrc === 'articles' ? ARTICLES : tous()).filter(x => !x.arch);
    pickRes = src.filter(x => !q || norm([x.lib, x.code, x.gtin].join(' ')).includes(q)).slice(0, 40);
    dessinerPick();
  }
  pickQ.addEventListener('input', () => { pickHl = 0; rendrePick(); }, { signal });
  pickQ.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); pickHl = Math.min(pickRes.length - 1, pickHl + 1); dessinerPick(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); pickHl = Math.max(0, pickHl - 1); dessinerPick(); }
    else if (e.key === 'Enter' && pickRes[pickHl]) { e.preventDefault(); choisir(pickHl); }
  }, { signal });
  pickL.addEventListener('click', e => { const b = e.target.closest('.pick-it'); if (b) choisir(+b.dataset.i); }, { signal });
  function choisir(i) { const x = pickRes[i]; fermerPick(); if (pickCb) pickCb(x); }
  function fermerPick() { pick.classList.remove('open'); }
  document.addEventListener('mousedown', e => { if (pick.classList.contains('open') && !e.target.closest('#pick') && !e.target.closest('#addComp,#btnReg,#tfPick')) fermerPick(); }, { signal });

  /* ═════════ Grilles ═════════ */

  function grilleSous(g) {
    const n = g.tarifs.length, tp = `${n} tarif${n > 1 ? 's' : ''} particulier${n > 1 ? 's' : ''}`;
    return g.defaut ? `Prix du catalogue · appliquée partout par défaut${n ? ' · ' + tp : ''}` : `Au choix, sur la facture · ${tp}`;
  }
  function tableTarifs(g) {
    return `<table class="gtab"><tr><th>Prestation</th><th>Type</th><th class="num">Catalogue</th><th class="num">Dans la grille</th><th class="num">Écart</th><th></th></tr>${g.tarifs.map((t, i) => {
      const it = item(t.c), base = it ? it.prix : null, e = base ? t.p - base : 0;
      return `<tr><td><div class="cid"><b>${esc(it ? it.lib : t.lib || 'Prestation supprimée')}</b><span class="code">${esc(it ? it.code : t.code)}</span></div></td><td style="font-size:.8rem">${t.type === 'ACT' ? 'Acte' : 'Article'}</td><td class="num">${base != null ? fmtE(base) : '—'}</td>
        <td class="num"><div class="inu" style="display:inline-block"><input class="finput num" data-gt="${i}" value="${fmtN(t.p)}" inputmode="decimal" aria-label="Prix dans la grille"><i>€</i></div></td>
        <td class="num">${base ? `<span class="ecart ${e < 0 ? 'moins' : 'plus'}">${e < 0 ? '−' : '+'}${fmtE(Math.abs(e))} · ${fmtN(Math.abs(e / base * 100), 0)} %</span>` : '—'}</td>
        <td style="width:36px"><button type="button" class="rm" data-gr="${i}" data-tip="Retirer de la grille" aria-label="Retirer de la grille">${ICO.rm}</button></td></tr>`; }).join('')}</table>`;
  }
  function rendreGrilles() {
    if (!GRILLES.length) {
      $('glist').innerHTML = '<p class="hint" style="margin:0">Aucune grille tarifaire.</p>';
      $('gdet').innerHTML = '<div class="cempty"><b>Aucune grille tarifaire</b>Créez-en une avec « Nouvelle grille ».</div>';
      return;
    }
    const g = GRILLES.find(x => x.id === ST.grille) || GRILLES[0];
    ST.grille = g.id;
    $('glist').innerHTML = GRILLES.map(x => `<button type="button" class="gcard${x.id === g.id ? ' on' : ''}" data-g="${x.id}"><b>${esc(x.lib)}${x.defaut ? '<span class="puce" style="margin-left:auto">Par défaut</span>' : ''}</b><span>${grilleSous(x)}</span></button>`).join('');
    let html = `<div class="ghead"><h2>${esc(g.lib)}<small>${grilleSous(g)}</small></h2><div class="right"><button type="button" class="btn" id="gAdd">${ICO.plus.replace('<svg', '<svg width="14" height="14"')}Tarifer une prestation</button></div></div>`;
    if (g.defaut) {
      html += `<div class="bandeau reg" style="max-width:920px">${ICO.ok}<div><b>${g.tarifs.length ? 'La grille par défaut' : 'Les prix du catalogue font foi'}</b>${g.tarifs.length
        ? 'Elle s\'applique partout où aucune autre grille ne prend le relais : ses tarifs particuliers priment sur les prix du catalogue, les autres prestations gardent le leur.'
        : 'Ils s\'appliquent partout où aucune autre grille ne prend le relais. Pour changer un prix, modifiez l\'acte ou l\'article ; pour une hausse générale, sélectionnez des lignes puis « Réviser les prix ».'}</div></div>`;
      if (g.tarifs.length) html += sec('Tarifs particuliers', tableTarifs(g), '<span class="hint" style="margin:0">Priment sur le catalogue</span>');
    } else {
      html += sec('Quand s\'applique-t-elle ?', `
        <div class="frow">${seg('appli', [['horaire', 'Selon l\'horaire'], ['clients', 'À certains clients'], ['manuelle', 'Au choix, sur la facture']], 'manuelle', true)}</div>
        <p class="hint">Proposée dans la facture, sans jamais s'appliquer d'elle-même.</p>`);
      html += sec('Majoration générale', `
        <div class="frow2"><div>${lab('Majoration des actes')}<div class="pct"><div class="inu" style="width:110px">${off('', { num: 1, ph: '0' })}<i>%</i></div><span class="hint" style="margin:0">Les médicaments et articles gardent leur prix.</span></div></div></div>
        <div class="frow">${lab('Sur les catégories')}${chips('cats', Object.values(CATS).map(c => c.l), [], true)}</div>`, soon);
      html += sec('Tarifs particuliers', g.tarifs.length ? tableTarifs(g) : '<p class="hint" style="margin:0">Aucun pour l\'instant. Ajoutez une prestation pour lui fixer un prix précis dans cette grille.</p>', '<span class="hint" style="margin:0">Priment sur le catalogue</span>');
      html += `<div style="max-width:920px"><button type="button" class="btn ghost-danger" id="gDel" disabled data-tip="${SOON}">Supprimer la grille</button></div>`;
    }
    $('gdet').innerHTML = html;
  }
  gview.addEventListener('click', async e => {
    const t = e.target, g = GRILLES.find(x => x.id === ST.grille);
    const c = t.closest('.gcard');
    if (c) { ST.grille = c.dataset.g; rendreGrilles(); return; }
    const rmb = t.closest('[data-gr]');
    if (rmb) {
      const old = g.tarifs[+rmb.dataset.gr], it = item(old.c);
      try {
        await poster(url('gridItemDrop', g.id, old.eid));
        await recharger();
        rendre();
        toastUndo(`${it ? it.lib : 'Prestation'} retiré de « ${g.lib} »`, async () => {
          try { await poster(url('gridItemSave', g.id), { item_id: old.c, item_type: old.type, price: old.price, price_mode: old.pm }); await recharger(); rendre(); } catch (err) { echec(err); }
        });
      } catch (err) { echec(err); }
      return;
    }
    if (t.closest('#gAdd')) ouvrirTarif(g);
  }, { signal });
  gview.addEventListener('change', async e => {
    if (e.target.dataset.gt == null) return;
    const g = GRILLES.find(x => x.id === ST.grille), tp = g.tarifs[+e.target.dataset.gt], v = parseN(e.target.value), it = item(tp.c);
    if (!(v > 0)) { e.target.value = fmtN(tp.p); return; }
    const avant = { price: tp.price, price_mode: tp.pm };
    try {
      await poster(url('gridItemSave', g.id), { entry_id: tp.eid, ...saisie(tp, v, tp.p) });
      await recharger();
      rendre();
      toastUndo(`${it ? it.lib : 'Prestation'} : ${fmtE(v)} dans « ${g.lib} »`, async () => {
        try { await poster(url('gridItemSave', g.id), { entry_id: tp.eid, ...avant }); await recharger(); rendre(); } catch (err) { echec(err); }
      });
    } catch (err) { echec(err); e.target.value = fmtN(tp.p); }
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
    if (hmenu.classList.contains('open')) { hmenu.classList.remove('open'); e.stopPropagation(); return; }
    if (document.querySelector('.dd.open') || document.querySelector('.veil.open')) return;
    if (ST.fiche) { fermerFiche(); e.stopPropagation(); }
  }, { signal, capture: true });

  /* Nouvelle grille */
  $('ngNom').addEventListener('input', e => { $('ngOk').disabled = !e.target.value.trim(); }, { signal });
  $('ngNom').addEventListener('keydown', e => { if (e.key === 'Enter' && !$('ngOk').disabled) $('ngOk').click(); }, { signal });
  $('ngOk').addEventListener('click', async () => {
    const lib = $('ngNom').value.trim();
    if (!lib) return;
    $('ngOk').disabled = true;
    try {
      const r = await poster(P.urls.gridCreate, { name: lib });
      await recharger();
      ST.grille = r.id;
      fermerModales();
      $('ngNom').value = '';
      rendre();
      showToast(`Grille « ${lib} » créée`);
    } catch (e) { echec(e); $('ngOk').disabled = false; }
  }, { signal });

  /* Tarifer une prestation */
  let tfItem = null, tfG = null;
  function ouvrirTarif(g) {
    tfG = g;
    tfItem = null;
    $('tfSub').textContent = g.lib;
    $('tfPick').innerHTML = '<span class="ph">Rechercher un acte ou un article…</span>';
    $('tfBase').textContent = '—';
    $('tfPrix').value = '';
    majTf();
    ouvrirModale('mTarif');
  }
  function majTf() { $('tfOk').disabled = !(tfItem && parseN($('tfPrix').value) > 0); }
  $('tfPick').addEventListener('click', e => ouvrirPick(e.currentTarget, 'tous', x => {
    tfItem = x;
    $('tfPick').innerHTML = `<span>${esc(x.lib)} <span class="code">${esc(x.code)}</span></span>`;
    $('tfBase').textContent = fmtE(x.prix);
    const ex = tfG.tarifs.find(t => t.c === x.id);
    const pi = $('tfPrix');
    pi.value = fmtN(ex ? ex.p : x.prix);
    pi.focus();
    pi.select();
    majTf();
  }), { signal });
  $('tfPrix').addEventListener('input', majTf, { signal });
  $('tfOk').addEventListener('click', async () => {
    const p = parseN($('tfPrix').value), ex = tfG.tarifs.find(t => t.c === tfItem.id), x = tfItem, g = tfG;
    $('tfOk').disabled = true;
    try {
      await poster(url('gridItemSave', g.id), ex ? { entry_id: ex.eid, price: Math.round(p * 100), price_mode: 'incl' } : { item_id: x.id, item_type: x.k === 'acte' ? 'ACT' : 'ARTICLE', price: Math.round(p * 100), price_mode: 'incl' });
      await recharger();
      fermerModales();
      rendre();
      showToast(`${x.lib} : ${fmtE(p)} dans « ${g.lib} »`);
    } catch (e) { echec(e); majTf(); }
  }, { signal });

  /* ═════════ Actions groupées ═════════ */

  const selItems = () => [...ST.sel].map(item).filter(Boolean);
  const nEl = n => `${n} élément${n > 1 ? 's' : ''}`;
  function majBulk() {
    const n = ST.tab === 'grilles' ? 0 : ST.sel.size;
    $('bulk').classList.toggle('on', n > 0);
    $('bulkN').textContent = n;
    $('bulkL').textContent = n > 1 ? 'sélectionnés' : 'sélectionné';
    $('bkFam').style.display = ST.tab === 'forfaits' ? 'none' : '';
  }
  /* Posts one change per item, in turn; returns the items it went through for. */
  async function pourChacun(L, fn) {
    const faits = [];
    for (const x of L) { await fn(x); faits.push(x); }
    return faits;
  }
  $('bkX').addEventListener('click', () => { ST.sel.clear(); rendre(); }, { signal });
  $('bkArch').addEventListener('click', async () => {
    const L = selItems().filter(x => !x.arch);
    if (!L.length) { showToast('Les éléments sélectionnés sont déjà archivés'); return; }
    let faits = [];
    try { faits = await pourChacun(L, x => basculer(x, true)); } catch (e) { echec(e); }
    ST.sel.clear();
    if (ST.fiche && faits.some(x => x.id === ST.fiche.id)) fermerFiche(true);
    try { await recharger(); } catch (e) { echec(e); }
    rendre();
    if (faits.length) toastUndo(`${nEl(faits.length)} archivé${faits.length > 1 ? 's' : ''}`, async () => {
      try { await pourChacun(faits, x => basculer(x, false)); await recharger(); rendre(); } catch (e) { echec(e); }
    });
  }, { signal });
  const fmDd = creerDropdown('fmDd', famOpts(), (FAM[0] || {}).v);
  $('bkFam').addEventListener('click', () => { fmDd.setOptions(famOpts()); $('fmSub').textContent = nEl(ST.sel.size); ouvrirModale('mFam'); }, { signal });
  $('fmOk').addEventListener('click', async () => {
    const v = fmDd.get(), L = selItems().filter(x => x.k !== 'forfait' && !x.arch);
    // The price all taxes included stays what it was: it is (re)posted TTC, whatever the new rate.
    const av = L.map(x => ({ x, tax_category: x.fam, price: x.price, price_mode: x.pm }));
    let faits = [];
    try { faits = await pourChacun(L, x => poster(url(BASE[x.k] + 'Update', x.id), { tax_category: v, price: Math.round(x.prix * 100), price_mode: 'incl' })); } catch (e) { echec(e); }
    fermerModales();
    try { await recharger(); } catch (e) { echec(e); }
    rendre();
    if (faits.length) toastUndo(`Famille « ${famDe(v).l} » appliquée à ${nEl(faits.length)}`, async () => {
      try { await pourChacun(av.filter(o => faits.includes(o.x)), o => poster(url(BASE[o.x.k] + 'Update', o.x.id), { tax_category: o.tax_category, price: o.price, price_mode: o.price_mode })); await recharger(); rendre(); } catch (e) { echec(e); }
    });
  }, { signal });
  let rvArr = 0.1;
  const rvEff = creerDropdown('rvEff', [{ v: 'now', l: 'Immédiatement' }], 'now');
  $('rvEff').dataset.tip = 'La révision programmée (au 1er janvier) n\'est pas encore disponible';
  $('rvArr').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; rvArr = +b.dataset.v; root.querySelectorAll('#rvArr button').forEach(x => x.classList.toggle('on', x === b)); majRev(); }, { signal });
  $('rvPct').addEventListener('input', majRev, { signal });
  const arrondir = p => r2(Math.round(p / rvArr) * rvArr);
  const revisables = () => selItems().filter(x => !x.arch && (x.k !== 'forfait' || x.mode === 'fixe'));
  function majRev() {
    const pct = parseN($('rvPct').value) || 0, L = revisables();
    $('rvTab').innerHTML = '<tr><th>Prestation</th><th class="num">Actuel</th><th class="num">Nouveau</th></tr>' + (L.map(x =>
      `<tr><td>${esc(x.lib)}</td><td class="num">${fmtE(x.prix)}</td><td class="num"><b>${fmtE(arrondir(x.prix * (1 + pct / 100)))}</b></td></tr>`).join('')
      || '<tr><td colspan="3" class="hint">Rien à réviser : les forfaits à la somme des prestations suivent leurs prestations.</td></tr>');
    $('rvOk').textContent = 'Appliquer';
    $('rvOk').disabled = !L.length || !pct || rvEff.get() !== 'now';
  }
  $('bkRev').addEventListener('click', () => { $('rvSub').textContent = `${nEl(ST.sel.size)} sélectionné${ST.sel.size > 1 ? 's' : ''}`; majRev(); ouvrirModale('mRev'); }, { signal });
  $('rvOk').addEventListener('click', async () => {
    const pct = parseN($('rvPct').value) || 0, L = revisables();
    const av = L.map(x => ({ x, price: x.price, price_mode: x.pm }));
    $('rvOk').disabled = true;
    let faits = [];
    try { faits = await pourChacun(L, x => poster(url(BASE[x.k] + 'Update', x.id), { price: Math.round(arrondir(x.prix * (1 + pct / 100)) * 100), price_mode: 'incl' })); } catch (e) { echec(e); }
    fermerModales();
    ST.sel.clear();
    try { await recharger(); } catch (e) { echec(e); }
    rendre();
    if (ST.fiche) { ST.fiche = item(ST.fiche.id) || ST.fiche; ST.draft = structuredClone(ST.fiche); ST.dirty = false; rendreFiche(); }
    if (faits.length) toastUndo(`${faits.length} prix révisé${faits.length > 1 ? 's' : ''} de ${pct > 0 ? '+' : ''}${fmtN(pct, pct % 1 ? 1 : 0)} %`, async () => {
      try { await pourChacun(av.filter(o => faits.includes(o.x)), o => poster(url(BASE[o.x.k] + 'Update', o.x.id), { price: o.price, price_mode: o.price_mode })); await recharger(); rendre(); } catch (e) { echec(e); }
    });
  }, { signal });

  document.addEventListener('turbo:before-cache', () => { fermerModales(); hmenu.classList.remove('open'); }, { signal });

  /* ═════════ Démarrage ═════════ */
  rendre();
  // ?article=<id> — the stock page's « Fiche dans le catalogue » opens the sheet.
  try { const id = new URLSearchParams(location.search).get('article'), x = id && item(id); if (x) ouvrirFiche(x); } catch (_) { /* malformed address */ }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  clearTimeout(regTimer);
  regTimer = null;
}
