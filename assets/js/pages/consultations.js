/**
 * Page module — Consultations (consultations-v-topbar mock-up). Loaded by the
 * app.js dispatcher on turbo:load, on the register (clinic_consultations).
 *
 * The mock-up renders the register from its demo data; this module renders it
 * the same way from the `data-payload` of ConsultationRegisterPageBuilder, and
 * asks clinic_consultations_search for every other page or filter (period
 * segment, status / practitioner / type pills, header counters, search, pager,
 * page size remembered in this browser). A row opens its reading sheet
 * (clinic_consultation_sheet) beside the table; the address follows
 * (`?consultation=<id>`), so a reload comes back to it. Every action of the
 * sheet leads to the consultation itself.
 *
 * « Nouvelle consultation » lists the waiting room (taking a patient in charge
 * posts #startForm) and, on search, the animals to register at the flow
 * board's arrival. What the clinic cannot do yet (sending reports by e-mail,
 * exports) is drawn but disabled.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';

const SEARCH_DEBOUNCE_MS = 250;
const PP_KEY = 'kiveto-cons-pp';
const PP_SIZES = [25, 50, 100];
const PER_DEFAULT = '7';
const LIVE_REFRESH_MS = 30000;

let ac = null;
let searchTimer = null;
let nwTimer = null;
let liveTimer = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const fmtD = s => (s ? s.split('-').reverse().join('/') : '—');
const dCourt = s => `${+s.slice(8)} ${MOIS[+s.slice(5, 7) - 1]}`;
const fmtE = n => (Number(n || 0) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const minutesDepuis = iso => Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));

const ICO = {
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  rx: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
  cr: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>',
  lab: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21h11.6a2 2 0 0 0 1.7-2.5L14 9V3"/><path d="M7 15h10"/></svg>',
  fa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-1 .7-3-2-3 2-3-2-3 2z"/><path d="M8 8h8M8 12h8"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
  warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
};

/* Status = the consultation's stage: being written, waiting to be closed, closed. */
const STATUTS = { cours: ['s-cours', 'En cours'], aclot: ['s-aclot', 'À clôturer'], clot: ['s-clot', 'Clôturée'] };
const TYPES = [{ v: 'urg', l: 'Urgences' }, { v: 'chir', l: 'Chirurgies' }, { v: 'lab', l: 'Avec analyses' }, { v: 'du', l: 'Reste à encaisser' }];
/* « Type » options of the mock-up the clinic does not track yet. */
const TYPES_A_VENIR = ['Compte rendu non envoyé', 'Historique repris'];

export function init() {
  const root = document.querySelector('.cons[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const TODAY = P.today;
  const czone = $('czone');

  const ST = {
    f: { ...P.filters },
    rows: P.register.rows,
    total: P.register.total,
    page: P.register.page,
    pp: P.register.pp,
    counts: P.register.counts,
    sel: new Set(),
    known: new Map(),
    fiche: P.sheet || null,
    seq: 0,
  };
  const remember = () => ST.rows.forEach(c => ST.known.set(c.id, c));
  remember();
  if (ST.fiche) ST.known.set(ST.fiche.row.id, ST.fiche.row);
  const VIERGE = () => ST.counts.recorded === 0;

  /* ═════════ Address ═════════ */

  function query(f = ST.f) {
    const q = new URLSearchParams();
    if (f.q) q.set('q', f.q);
    if (f.per !== PER_DEFAULT) q.set('per', f.per);
    if (f.statut) q.set('statut', f.statut);
    if (f.vet) q.set('vet', f.vet);
    if (f.type) q.set('type', f.type);
    if (f.page > 1) q.set('page', String(f.page));
    if (f.pp !== 25) q.set('pp', String(f.pp));
    return q;
  }
  function majAdresse() {
    const q = query();
    if (ST.fiche) {
      q.set('consultation', ST.fiche.row.id);
      // A consultation opened from a link shows « Tout »: keep the period explicit.
      if (ST.f.per === PER_DEFAULT) q.set('per', PER_DEFAULT);
    }
    const url = P.urls.list + (q.toString() ? '?' + q : '');
    try { history.replaceState(history.state, '', url); } catch (_) { /* sandboxed */ }
  }

  /* ═════════ Loading a page of the register ═════════ */

  async function charger() {
    const seq = ++ST.seq;
    const q = query();
    q.set('page', String(ST.f.page));
    q.set('pp', String(ST.f.pp));
    try {
      const r = await fetch(`${P.urls.search}?${q}`, { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const d = await r.json();
      if (seq !== ST.seq) return;
      Object.assign(ST, { rows: d.rows, total: d.total, page: d.page, pp: d.pp, counts: d.counts });
      ST.f.page = d.page;
      remember();
      rendre();
      majAdresse();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('La liste des consultations n\'a pas pu être chargée', 'danger');
    }
  }
  function filtrer(patch) {
    Object.assign(ST.f, patch, { page: 1 });
    rendreFiltres();
    charger();
  }

  /* ═════════ Filter bar ═════════ */

  function rendreFiltres() {
    const box = $('fdd');
    box.innerHTML = '';
    const seg = document.createElement('div');
    seg.className = 'seg';
    seg.innerHTML = [['0', "Aujourd'hui"], ['7', '7 jours'], ['30', '30 jours'], ['tout', 'Tout']]
      .map(([v, l]) => `<button type="button" data-v="${v}" class="${ST.f.per === v ? 'on' : ''}">${l}</button>`).join('');
    seg.addEventListener('click', e => { const b = e.target.closest('button'); if (b) filtrer({ per: b.dataset.v }); });
    box.appendChild(seg);
    [
      { k: 'statut', l: 'Statut', o: Object.entries(STATUTS).map(([v, [, l]]) => ({ v, l })) },
      { k: 'vet', l: 'Praticien', o: (P.team || []).map(x => ({ v: x.id, l: x.n, c: x.c })) },
      { k: 'type', l: 'Type', o: TYPES, soon: TYPES_A_VENIR },
    ].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (ST.f[fd.k] ? ' actif' : '');
      box.appendChild(el);
      creerDropdown(el, [{ v: '', l: 'Tous' }, ...fd.o], ST.f[fd.k], v => filtrer({ [fd.k]: v }));
      if (!ST.f[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
      if (fd.soon) {
        el.querySelector('.dd-menu').insertAdjacentHTML('beforeend', '<div class="dd-sep"></div><div class="dd-titre">Bientôt disponible</div>'
          + fd.soon.map(l => `<button type="button" class="dd-it" disabled data-tip="Ce suivi n'est pas encore tenu par la clinique">${esc(l)}</button>`).join(''));
      }
    });
  }

  /* ═════════ Table ═════════ */

  function drapeaux(c) {
    const f = [];
    if (c.flags.urg) f.push('<span class="puce urgx">Urgence</span>');
    if (c.flags.aid) f.push('<span class="puce aid">À identifier</span>');
    if (c.flags.chir) f.push('<span class="puce chir">Chirurgie</span>');
    if ((c.flags.lab || []).length) f.push(`<span class="puce lab" title="${esc(c.flags.lab.join(', '))}">Analyse${c.flags.lab.length > 1 ? 's' : ''}</span>`);
    return f.join('');
  }
  const dureeHtml = c => (c.st === 'cours'
    ? `<span class="dur live" data-live="${esc(c.started)}">${minutesDepuis(c.started)} min</span>`
    : c.dur ? `<span class="dur">${c.dur} min</span>` : '<span class="dur">—</span>');
  function montantHtml(c) {
    if (c.st === 'cours') return `<div class="fac mute"><b>${fmtE(c.ttc)}</b><span>en cours</span></div>`;
    if (c.pay === 'paye') return `<div class="fac"><b>${fmtE(c.ttc)}</b><span>réglée</span></div>`;
    if (c.pay === 'du') return `<div class="fac"><b>${fmtE(c.ttc)}</b><span class="du">à encaisser</span></div>`;
    return `<div class="fac${c.ttc ? '' : ' mute'}"><b>${fmtE(c.ttc)}</b><span>${c.ttc ? 'non facturée' : 'sans acte facturé'}</span></div>`;
  }
  const COLS = [
    { l: 'Date', html: c => `<div class="mdate"><b>${c.d === TODAY ? "Aujourd'hui" : dCourt(c.d)} · ${c.h}</b></div>` },
    { l: 'Animal', html: c => `<div class="pat"><span class="e">${c.a.e}</span><div style="min-width:0"><b>${esc(c.a.n)}</b><span>${esc(c.a.prop)}</span></div></div>` },
    { l: 'Motif', html: c => `<div class="mot">${esc(c.motif)}${c.diag && c.st !== 'cours' ? ` <small>· ${esc(c.diag.replace(/\.$/, ''))}</small>` : ''}</div>${drapeaux(c) ? `<div class="flags">${drapeaux(c)}</div>` : ''}` },
    { l: 'Praticien', xo: 1, html: c => `<span class="prs"><span class="av" style="background:${esc(c.vet.c)}">${esc(c.vet.ini)}</span>${esc(c.vet.n)}</span>` },
    { l: 'Durée', xo: 1, html: dureeHtml },
    { l: 'Statut', html: c => { const [k, l] = STATUTS[c.st]; return `<span class="puce ${k}">${l}</span>`; } },
    { l: 'Montant', cls: 'num', html: montantHtml },
  ];

  function rendrePager() {
    const tot = ST.total, pg = ST.page, n = Math.max(1, Math.ceil(tot / ST.pp));
    const a = tot ? (pg - 1) * ST.pp + 1 : 0, b = Math.min(tot, pg * ST.pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> consultation${tot > 1 ? 's' : ''}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${PP_SIZES.map(v => `<button type="button" data-pp="${v}" class="${v === ST.pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }

  /* Header: what asks for an action, in one click. */
  function rendreTete() {
    const jour = new Date(TODAY + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    $('phSub').textContent = VIERGE() ? 'Aucune consultation enregistrée pour l\'instant' : `${jour.charAt(0).toUpperCase() + jour.slice(1)} · ${ST.counts.today} aujourd'hui`;
    const K = [['cours', ST.counts.cours, 'en cours', 'pin'], ['aclot', ST.counts.aclot, 'à clôturer', 'am']].filter(x => x[1]);
    $('phk').innerHTML = K.map(([k, n, l, c]) => `<button type="button" data-k="${k}" class="${ST.f.statut === k ? 'on' : ''}"><b class="${c}">${n}</b><span>${l}</span></button>`).join('');
  }

  function rendre() {
    rendreTete();
    const Lp = ST.rows;
    const allSel = Lp.length && Lp.every(c => ST.sel.has(c.id)), some = Lp.some(c => ST.sel.has(c.id));
    $('thead').innerHTML = `<tr><th class="selc"><input type="checkbox" class="ck${some && !allSel ? ' mix' : ''}" id="ckAll" ${allSel ? 'checked' : ''} aria-label="Tout sélectionner"></th>${COLS.map(c => `<th class="${c.cls || ''}${c.xo ? ' xo' : ''}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = Lp.map(c => `<tr data-id="${esc(c.id)}" class="${ST.sel.has(c.id) ? 'sel' : ''}${ST.fiche?.row.id === c.id ? ' cur' : ''}"><td class="selc"><input type="checkbox" class="ck" ${ST.sel.has(c.id) ? 'checked' : ''} aria-label="Sélectionner"></td>${COLS.map(k => `<td class="${k.cls || ''}${k.xo ? ' xo' : ''}">${k.html(c)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = Lp.length ? '' : VIERGE()
      ? `<div class="cempty"><b>Aucune consultation pour l'instant</b>Elles s'ouvrent depuis le flux ambulatoire, à la prise en charge d'un patient, ou ici pour une visite hors flux.<div class="acts"><a class="btn" href="${esc(P.urls.flux)}">Aller au flux ambulatoire</a><button type="button" class="btn primaire" data-new>Nouvelle consultation</button></div></div>`
      : '<div class="cempty"><b>Aucune consultation</b>Modifiez la période, la recherche ou les filtres.</div>';
    rendrePager();
    majBulk();
    $('cfil').style.display = VIERGE() ? 'none' : '';
    root.classList.toggle('co-vide', VIERGE());
  }

  function majBulk() {
    const n = ST.sel.size;
    $('bulk').classList.toggle('on', n > 0);
    $('bulkN').textContent = n;
    $('bulkL').textContent = n > 1 ? 'sélectionnées' : 'sélectionnée';
  }

  /* The live duration of the consultations in progress. */
  function majLive() {
    root.querySelectorAll('[data-live]').forEach(el => { el.textContent = `${minutesDepuis(el.dataset.live)} min`; });
    if (ST.fiche && ST.fiche.row.st === 'cours') {
      const b = $('fiLive');
      if (b) b.textContent = `En cours depuis ${minutesDepuis(ST.fiche.row.started)} min`;
    }
  }

  /* ═════════ Sheet: reading, and one path to every action ═════════ */

  async function ouvrirFiche(id) {
    const seq = ++ST.seq;
    try {
      const r = await fetch(P.urls.sheet.replace('00000000-0000-7000-8000-000000000000', encodeURIComponent(id)), { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const o = await r.json();
      if (seq !== ST.seq) return;
      ST.fiche = o;
      ST.known.set(o.row.id, o.row);
      czone.classList.add('ouvert');
      rendreFiche();
      rendre();
      $('fiBody').scrollTop = 0;
      majAdresse();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('La consultation n\'a pas pu être ouverte', 'danger');
    }
  }
  function fermerFiche() {
    if (!ST.fiche) return;
    ST.fiche = null;
    czone.classList.remove('ouvert');
    rendre();
    majAdresse();
  }

  function rendreFiche() {
    const o = ST.fiche;
    if (!o) return;
    const c = o.row, a = c.a, [sk, sl] = STATUTS[c.st], v = c.vet;
    $('fiHead').innerHTML = `<div class="fi-ico" style="font-size:1.1rem">${a.e}</div><div class="fi-t"><b>${esc(a.n)} · ${esc(c.motif)}</b><span><span class="code">${c.d === TODAY ? "Aujourd'hui" : fmtD(c.d)} · ${c.h}</span> · <span class="puce ${sk}">${sl}</span></span></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;

    const bande = c.st === 'cours'
      ? `<div class="bandeau info">${ICO.info}<div><b id="fiLive">En cours depuis ${minutesDepuis(c.started)} min</b>Ouverte par ${esc(v.n)} à ${c.h}${c.flags.aid ? ' · patient encore à identifier depuis le flux' : ''}.</div></div>`
      : c.st === 'aclot'
        ? `<div class="bandeau warn">${ICO.warn}<div><b>Compte rendu à valider</b>${c.d === TODAY ? 'Le patient est parti' : `Ouverte le ${fmtD(c.d)}`}, la consultation reste ouverte : relire et signer le compte rendu pour la clôturer.</div></div>`
        : '';

    const docs = [];
    if (c.st === 'clot') docs.push(`<a class="doc" href="${esc(o.urls.report)}" target="_blank" rel="noopener"><span class="e">${ICO.cr}</span><span class="lt"><b>Compte rendu</b><span>Signé à la clôture · PDF</span></span><span class="st ok">signé</span></a>`);
    if (c.st === 'aclot') docs.push(`<a class="doc" href="${esc(c.url)}"><span class="e">${ICO.cr}</span><span class="lt"><b>Compte rendu</b><span>Brouillon, à relire et signer</span></span><span class="st am">à signer</span></a>`);
    (o.ordos || []).forEach(x => docs.push(`<a class="doc" href="${esc(x.url)}"><span class="e">${ICO.rx}</span><span class="lt"><b>Ordonnance</b><span>${esc(x.num)}</span></span><span class="st mu">${x.st === 'émise' ? ICO.ext : esc(x.st)}</span></a>`));
    (c.flags.lab || []).forEach(l => docs.push(`<a class="doc" href="${esc(c.url)}"><span class="e">${ICO.lab}</span><span class="lt"><b>${esc(l)}</b><span>Analyse facturée sur la consultation</span></span><span class="st mu">${ICO.ext}</span></a>`));
    if (c.invoice && c.st !== 'cours') docs.push(`<a class="doc" href="${esc(c.invoice.url)}"><span class="e">${ICO.fa}</span><span class="lt"><b>Facture</b><span>${esc(c.invoice.num || 'Brouillon')} · ${fmtE(c.ttc)}</span></span><span class="st ${c.pay === 'paye' ? 'ok' : 'am'}">${c.pay === 'paye' ? 'réglée' : 'à encaisser'}</span></a>`);

    $('fiBody').innerHTML = `${bande}
      <section class="fs"><div class="oinfo">
        <div><small>Praticien</small><b>${esc(v.n)}</b><span>${c.st === 'cours' ? 'en cours' : c.dur ? c.dur + ' min' : '—'}</span></div>
        <div><small>Date</small><b>${fmtD(c.d)}</b><span>à ${c.h}</span></div>
        <div><small>Animal</small><b>${esc(a.n)}</b><span>${esc(a.r || '—')}</span></div>
      </div></section>
      <section class="fs"><h3>Synthèse${a.url ? `<a class="fi-anim" style="margin-left:auto" href="${esc(a.url)}">Dossier de l'animal ${ICO.ext}</a>` : ''}</h3><div class="syn">
        <div><small>Propriétaire</small><p>${esc(a.prop)}</p></div>
        <div><small>Motif</small><p>${esc(c.motif)}${c.flags.urg ? ' · <b style="color:#b91c1c">urgence</b>' : ''}</p></div>
        ${o.conclusion ? `<div><small>Conclusion</small><p>${esc(o.conclusion)}</p></div>` : c.st !== 'clot' ? '<div><small>Conclusion</small><p style="color:var(--encre-35)">En cours de rédaction</p></div>' : ''}
        ${o.poids ? `<div><small>Poids</small><p class="poids">${esc(o.poids)}</p></div>` : ''}
      </div></section>
      ${o.actes.length ? `<section class="fs"><h3>Actes${c.st !== 'clot' ? '<span class="tag hint" style="margin:0 0 0 auto;font-weight:400;letter-spacing:0;text-transform:none">provisoire</span>' : ''}</h3><div class="actes">
        ${o.actes.map(x => `<div><span>${esc(x.l)}${x.q && +x.q !== 1 ? ` × ${String(+x.q).replace('.', ',')}` : ''}</span><b>${x.m ? fmtE(x.m) : 'inclus'}</b></div>`).join('')}${o.tva ? `<div><span>TVA</span><b>${fmtE(o.tva)}</b></div>` : ''}<div class="tot"><span>Total TTC</span><b>${fmtE(o.ttc)}</b></div></div></section>` : ''}
      <section class="fs"><h3>Documents</h3><div class="docs">
        ${docs.join('')}
        ${!docs.length ? '<p class="hint" style="margin:0">Les documents apparaîtront ici à mesure qu\'ils sont produits.</p>' : ''}
      </div></section>
      ${o.suivi ? `<section class="fs"><h3>Suivi</h3><div class="suivi"><span>${esc(o.suivi.t)} · <b>${fmtD(o.suivi.d)}</b></span><span class="sp"></span><a href="${esc(o.urls.agenda)}">Prendre RDV</a></div></section>` : ''}`;

    const PR = c.st === 'cours' ? ['Reprendre la consultation', c.url] : c.st === 'aclot' ? ['Relire et clôturer', c.url] : ['Ouvrir la consultation', c.url];
    $('fiFoot').innerHTML = `<span class="sp"></span>${c.st === 'clot' ? '<button type="button" class="btn" disabled data-tip="L\'envoi des comptes rendus par e-mail arrive bientôt">Envoyer le compte rendu</button>' : ''}<a class="btn primaire" href="${esc(PR[1])}">${PR[0]}</a>`;
  }

  /* ═════════ New consultation ═════════ */

  const ouvrirModale = id => $(id).classList.add('open');
  const fermerModales = () => root.querySelectorAll('.mveil.open').forEach(m => m.classList.remove('open'));

  let horsFlux = '';
  function rendreNw() {
    const q = norm($('nwQ').value.trim());
    const sp = (P.waiting || []).filter(s => !q || norm(s.a + ' ' + s.prop).includes(q));
    const html = (sp.length ? '<h4>En salle d\'attente</h4>' + sp.map(s => `<a class="citem${s.urg ? ' urg' : ''}" href="#" data-start="${esc(s.url)}">
        <span class="h">${esc(s.h)}</span><span class="e">${s.e}</span><span class="lt"><b>${esc(s.a)} · ${esc(s.m)}</b><span>${esc(s.prop)}${s.urg ? ' · urgence' : ''}</span></span><span class="go">Prendre en charge →</span></a>`).join('') : '')
      + (q && horsFlux ? '<h4>Hors flux · visite, téléconsultation</h4>' + horsFlux : '');
    $('nwList').innerHTML = html
      || `<p class="hint" style="text-align:center;margin:16px 0">${q ? 'Aucun animal trouvé. Un nouveau patient s\'enregistre d\'abord à l\'arrivée, dans le flux.' : 'Personne en salle d\'attente. Recherchez un animal pour une consultation hors flux.'}</p>`;
  }
  async function chercherHorsFlux() {
    const q = $('nwQ').value.trim();
    horsFlux = '';
    if (q.length < 2) { rendreNw(); return; }
    try {
      const r = await fetch(`${P.urls.patients}&q=${encodeURIComponent(q)}`, { headers: { Accept: 'text/html' }, signal });
      if (r.ok && $('nwQ').value.trim() === q) horsFlux = (await r.text()).trim();
    } catch (e) { /* the waiting room alone is still listed */ }
    rendreNw();
  }
  function ouvrirNew() {
    $('nwQ').value = '';
    horsFlux = '';
    rendreNw();
    ouvrirModale('mNew');
    setTimeout(() => $('nwQ').focus(), 50);
  }

  /* ═════════ Events ═════════ */

  root.querySelectorAll('.mveil').forEach(m => {
    m.addEventListener('mousedown', e => { if (e.target === m) fermerModales(); }, { signal });
    m.addEventListener('click', e => { if (e.target.closest('[data-close]')) fermerModales(); }, { signal });
  });

  $('cfoot').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || b.disabled) return;
    if (b.dataset.pp) {
      ST.f.pp = +b.dataset.pp;
      ST.f.page = 1;
      try { localStorage.setItem(PP_KEY, String(ST.f.pp)); } catch (_) { /* private mode */ }
    } else ST.f.page = +b.dataset.pg;
    charger().then(() => { root.querySelector('.cscroll').scrollTop = 0; });
  }, { signal });

  $('phk').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    const on = b.classList.contains('on');
    filtrer(on ? { statut: '' } : { statut: b.dataset.k, type: '', per: 'tout' });
  }, { signal });

  $('thead').addEventListener('click', e => {
    if (e.target.id !== 'ckAll') return;
    const all = ST.rows.every(c => ST.sel.has(c.id));
    ST.rows.forEach(c => (all ? ST.sel.delete(c.id) : ST.sel.add(c.id)));
    rendre();
  }, { signal });

  $('tbody').addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.closest('.selc')) {
      const id = tr.dataset.id;
      if (ST.sel.has(id)) ST.sel.delete(id); else ST.sel.add(id);
      rendre();
      return;
    }
    // A modified click opens the consultation itself in a new tab.
    const c = ST.known.get(tr.dataset.id);
    if ((e.metaKey || e.ctrlKey) && c) { window.open(c.url, '_blank', 'noopener'); return; }
    ouvrirFiche(tr.dataset.id);
  }, { signal });
  $('tbody').addEventListener('auxclick', e => {
    const tr = e.target.closest('tr');
    const c = tr && ST.known.get(tr.dataset.id);
    if (e.button === 1 && c && !e.target.closest('.selc')) window.open(c.url, '_blank', 'noopener');
  }, { signal });
  $('tbody').addEventListener('dblclick', e => {
    const tr = e.target.closest('tr');
    const c = tr && ST.known.get(tr.dataset.id);
    if (c && !e.target.closest('.selc')) window.location.href = c.url;
  }, { signal });

  $('bkX').addEventListener('click', () => { ST.sel.clear(); rendre(); }, { signal });
  $('bkPdf').addEventListener('click', () => {
    const L = [...ST.sel].filter(id => ST.known.get(id)?.st === 'clot');
    if (!L.length) { showToast('Rien à télécharger : seules les consultations clôturées ont un compte rendu'); return; }
    const a = document.createElement('a');
    a.href = `${P.urls.selection}?${L.map(id => 'ids[]=' + encodeURIComponent(id)).join('&')}`;
    a.download = `comptes-rendus-${L.length}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    showToast(`${L.length} compte${L.length > 1 ? 's' : ''} rendu${L.length > 1 ? 's' : ''} téléchargé${L.length > 1 ? 's' : ''} en un seul PDF${ST.sel.size > L.length ? ' · consultations non clôturées exclues' : ''}`);
  }, { signal });

  $('q').addEventListener('input', e => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => filtrer({ q: e.target.value.trim() }), SEARCH_DEBOUNCE_MS);
  }, { signal });

  const hmenu = $('hmenu');
  $('hmenuBtn').addEventListener('click', e => { e.stopPropagation(); hmenu.classList.toggle('open'); }, { signal });
  document.addEventListener('click', e => { if (!e.target.closest('#hmenu')) hmenu.classList.remove('open'); }, { signal });

  $('fiHead').addEventListener('click', e => { if (e.target.closest('#fiX')) fermerFiche(); }, { signal });

  $('btnNew').addEventListener('click', ouvrirNew, { signal });
  $('cempty').addEventListener('click', e => { if (e.target.closest('[data-new]')) ouvrirNew(); }, { signal });
  $('nwQ').addEventListener('input', () => {
    rendreNw();
    window.clearTimeout(nwTimer);
    nwTimer = window.setTimeout(chercherHorsFlux, SEARCH_DEBOUNCE_MS);
  }, { signal });
  $('nwList').addEventListener('click', e => {
    const a = e.target.closest('[data-start]');
    if (!a) return;
    e.preventDefault();
    const f = $('startForm');
    f.action = a.dataset.start;
    f.requestSubmit();
  }, { signal });

  // Escape closes the windows first, then the sheet (the dropdowns and the
  // search palette handle their own).
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (root.querySelector('.mveil.open')) { fermerModales(); e.stopPropagation(); return; }
    if (document.querySelector('.dd.open') || document.querySelector('.veil.open') || hmenu.classList.contains('open')) { hmenu.classList.remove('open'); return; }
    if (ST.fiche) { fermerFiche(); e.stopPropagation(); }
  }, { signal, capture: true });

  document.addEventListener('turbo:before-cache', () => { fermerModales(); hmenu.classList.remove('open'); }, { signal });

  /* ═════════ Start ═════════ */

  rendreFiltres();
  rendre();
  if (ST.fiche) rendreFiche();
  window.clearInterval(liveTimer);
  liveTimer = window.setInterval(majLive, LIVE_REFRESH_MS);

  // The page size this browser chose last, unless the address says otherwise.
  let pp = 0;
  try { pp = +localStorage.getItem(PP_KEY); } catch (_) { /* private mode */ }
  if (PP_SIZES.includes(pp) && pp !== ST.f.pp && !new URLSearchParams(location.search).has('pp')) {
    ST.f.pp = pp;
    charger();
  }
}

export function cleanup() {
  ac?.abort();
  ac = null;
  window.clearTimeout(searchTimer);
  window.clearTimeout(nwTimer);
  window.clearInterval(liveTimer);
  searchTimer = null;
  nwTimer = null;
  liveTimer = null;
}
