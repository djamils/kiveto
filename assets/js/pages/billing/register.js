/**
 * Page module — Facturation (facturation-v-topbar_4 mock-up). Loaded by the
 * app.js dispatcher on turbo:load, on the register (clinic_invoices), on one
 * document (clinic_invoice_details / clinic_credit_note_details: the register
 * with the sheet already open) and on the checkout of a visit
 * (clinic_checkout: the sheet of its invoice with « Encaisser » up).
 *
 * The mock-up renders the register from its demo data; this module renders it
 * the same way from the `data-payload` of BillingPageBuilder, and asks
 * clinic_invoices_search for every other page or filter (period segment,
 * status / settlement / practitioner pills, search, pager, page size
 * remembered in this browser). A row opens its sheet beside the table; the
 * address follows, so a reload comes back to it.
 *
 * Payments, checkouts, credit notes and counter sales post #factForm to their
 * single-action controllers, which come back to the document with a toast.
 * The cheque remittance and the cash closing are posted in the background.
 * Printing and downloading open the PDFs the server renders. What the clinic
 * cannot do yet (e-mail, reminders, instalments, accounting export,
 * e-invoicing) is drawn but disabled.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';
import { mount as monterFisc, blank as fiscVide, validate as fiscErr, toPayload as fiscPayload, fmtSiren, fmtVat, IND, PRO } from 'kiveto/client-tax-profile';

const SEARCH_DEBOUNCE_MS = 250;
const PP_KEY = 'kiveto-fact-pp';
const PP_SIZES = [25, 50, 100];
const PER_DEFAULT = '30';
const NIL = '00000000-0000-7000-8000-000000000000';

let ac = null;
let searchTimer = null;
let articleTimer = null;
let clientTimer = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fmtN = (n, d = 2) => Number(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
/* Amounts travel in cents. */
const fmtE = c => fmtN((c || 0) / 100) + ' €';
const parseN = s => { const v = parseFloat(String(s ?? '').replace(/\s/g, '').replace(',', '.')); return isNaN(v) ? null : v; };
const cents = s => { const v = parseN(s); return v === null ? null : Math.round(v * 100); };
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS_LONGS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const fmtD = s => (s ? s.split('-').reverse().join('/') : '—');
const dCourt = s => `${+s.slice(8)} ${MOIS[+s.slice(5, 7) - 1]}`;
const dLong = s => { const d = new Date(s + 'T12:00:00'); const j = JOURS[d.getDay()]; return `${j[0].toUpperCase()}${j.slice(1)} ${d.getDate()} ${MOIS_LONGS[d.getMonth()]} ${d.getFullYear()}`; };

const ICO = {
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  ok: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  steth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>',
  rx: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  rm: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  crit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
};

const MODES = {
  cb: { l: 'Carte bancaire', s: 'CB', bg: '#eef2ff', c: '#4338ca' },
  esp: { l: 'Espèces', s: '€', bg: '#f0fdf4', c: '#15803d' },
  chq: { l: 'Chèque', s: 'CHQ', bg: '#fff7ed', c: '#c2410c' },
  vir: { l: 'Virement', s: 'VIR', bg: '#ecfeff', c: '#0e7490' },
  av: { l: 'Avoir', s: 'AV', bg: '#f5f3ff', c: '#6d28d9' },
  ass: { l: 'Assurance', s: 'ASS', bg: '#f5f3ff', c: '#6d28d9' },
  autre: { l: 'Autre', s: '···', bg: '#f1f5f9', c: '#64748b' },
};
/* Mode of the window → the Billing context's payment method. */
const METHOD = { cb: 'CARD', esp: 'CASH', chq: 'CHECK', vir: 'TRANSFER' };
const STATUTS = { brou: ['s-brou', 'Brouillon'], emise: ['s-deliv', 'Émise'], annav: ['s-annav', 'Annulée par avoir'], ann: ['s-annav', 'Annulée'], av: ['s-av', 'Avoir'] };
const FILTRE_STATUTS = [['brou', 'Brouillon'], ['emise', 'Émise'], ['annav', 'Annulée par avoir'], ['av', 'Avoir']];
const MOTIFS_AV = ['Erreur de facturation', 'Retour produit', 'Geste commercial', 'Prestation non réalisée'];
const ENVOI_TIP = 'L\'envoi par e-mail et SMS n\'est pas encore disponible';

export function init() {
  const root = document.querySelector('.fact[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const LH = P.letterhead || { name: 'Clinique vétérinaire', lines: [] };
  const TODAY = P.today;
  const czone = $('czone');

  const ST = {
    f: { ...P.filters },
    rows: P.register.rows,
    total: P.register.total,
    page: P.register.page,
    pp: P.register.pp,
    kpi: P.kpi,
    caisse: P.caisse,
    sel: new Set(),
    known: new Map(),
    fiche: P.sheet || null,
    seq: 0,
    posting: false,
    // Back from a refused checkout: what the draft's professional invoice lacks, each with where to fix it.
    ready: P.sheet && Array.isArray(P.readiness) && P.readiness.length ? { id: P.sheet.id, items: P.readiness } : null,
  };
  const remember = () => ST.rows.forEach(o => ST.known.set(o.id, o));
  remember();

  /* ═════════ Address ═════════ */

  function query(f = ST.f) {
    const q = new URLSearchParams();
    if (f.q) q.set('q', f.q);
    if (f.per !== PER_DEFAULT) q.set('per', f.per);
    if (f.statut) q.set('statut', f.statut);
    if (f.pay) q.set('pay', f.pay);
    if (f.vet) q.set('vet', f.vet);
    if (f.page > 1) q.set('page', String(f.page));
    if (f.pp !== 25) q.set('pp', String(f.pp));
    return q;
  }
  const detailsUrl = o => (o.kind === 'av' ? P.urls.detailsAv : P.urls.detailsFac).replace(NIL, o.id);
  function majAdresse() {
    const base = ST.fiche ? detailsUrl(ST.fiche) : P.urls.list;
    const q = query();
    // A document's page opens on « Tout »: keep the period explicit there.
    if (ST.fiche && ST.f.per === PER_DEFAULT) q.set('per', PER_DEFAULT);
    const url = base + (q.toString() ? '?' + q : '');
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
      Object.assign(ST, { rows: d.rows, total: d.total, page: d.page, pp: d.pp });
      ST.f.page = d.page;
      remember();
      rendre();
      majAdresse();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('Le registre n\'a pas pu être chargé', 'danger');
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
      { k: 'statut', l: 'Statut', o: FILTRE_STATUTS.map(([v, l]) => ({ v, l })) },
      { k: 'pay', l: 'Règlement', o: [{ v: 'du', l: 'Reste dû' }, { v: 'retard', l: 'En retard' }, { v: 'solde', l: 'Soldée' }] },
      { k: 'vet', l: 'Praticien', o: (P.team || []).map(x => ({ v: x.id, l: x.n, c: x.c })) },
    ].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (ST.f[fd.k] ? ' actif' : '');
      box.appendChild(el);
      creerDropdown(el, [{ v: '', l: 'Tous' }, ...fd.o], ST.f[fd.k], v => {
        // Looking for what is owed is looking over the whole register.
        filtrer(fd.k === 'pay' && v ? { pay: v, per: 'tout' } : { [fd.k]: v });
      });
      if (!ST.f[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
    });
    // « Facture électronique »: the clinic does not file through an approved platform yet.
    const fe = document.createElement('div');
    fe.className = 'dd fpill off';
    fe.dataset.tip = 'La facturation électronique n\'est pas encore branchée';
    fe.innerHTML = '<button type="button" class="dd-btn" disabled aria-disabled="true"><span>Facture électronique</span><svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>';
    box.appendChild(fe);
  }

  /* ═════════ Table ═════════ */

  const anisHtml = o => (o.anis.length
    ? o.anis.map(a => `<span class="ani-e">${a.e}</span><span>${esc(a.n)}</span>`).join('<span> · </span>')
    : o.cpt === 'REFUSED' ? 'Comptoir · refus des coordonnées' : o.cpt === 'NEW_CLIENT' ? 'Comptoir · nouvelle fiche' : o.cpt ? 'Vente au comptoir' : o.cli.passage ? 'sans fiche client' : '—');
  const COLS = [
    { l: 'Date', html: o => `<div class="mdate"><b>${o.d === TODAY ? "Aujourd'hui" : dCourt(o.d)} · ${o.h}</b><span class="num-o">${esc(o.num || 'numéro à l\'encaissement')}</span></div>` },
    { l: 'Client', html: o => `<div class="pat"><div style="min-width:0"><b>${esc(o.cli.n)}</b><span class="anis">${anisHtml(o)}</span></div></div>` },
    { l: 'Objet', xo: 1, html: o => `<div class="objet">${esc(o.objet)}</div>` },
    { l: 'Praticien', xo: 1, html: o => (o.vet ? `<span class="prs"><span class="av" style="background:${esc(o.vet.c)}">${esc(o.vet.ini)}</span>${esc(o.vet.n)}</span>` : '<span class="prs" style="color:var(--encre-35)">—</span>') },
    { l: 'Statut', html: o => { const [c, l] = STATUTS[o.st]; return `<span class="puce ${c}">${l}</span>`; } },
    { l: 'Montant TTC', cls: 'num', html: o => `<div class="mtt${o.kind === 'av' ? ' neg' : ''}">${o.kind === 'av' ? '−' : ''}${fmtE(o.tot)}</div>` },
    { l: 'Reste dû', cls: 'num', html: o => {
      if (o.st === 'brou') return '<div class="du ok">en cours</div>';
      if (!o.du) return '<div class="du ok">—</div>';
      return `<div class="du${o.retard ? ' retard' : ''}"><b>${fmtE(o.du)}</b><span>${o.retard ? `en retard · ${o.retard} j` : 'à régler'}</span></div>`;
    } },
  ];

  function rendrePager() {
    const tot = ST.total, pg = ST.page, n = Math.max(1, Math.ceil(tot / ST.pp));
    const a = tot ? (pg - 1) * ST.pp + 1 : 0, b = Math.min(tot, pg * ST.pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> document${tot > 1 ? 's' : ''}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${PP_SIZES.map(v => `<button type="button" data-pp="${v}" class="${v === ST.pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }

  function rendreEntete() {
    $('phSub').textContent = `Factures et avoirs · numérotation continue ${TODAY.slice(0, 4)}`;
    const k = ST.kpi;
    $('phk').innerHTML = `<span><b>${fmtE(k.enc)}</b> encaissés aujourd'hui</span><span><b class="am">${fmtE(k.du)}</b> restent dus${k.retard ? ` · ${k.retard} en retard` : ''}</span>`;
    const c = ST.caisse.cheques;
    $('bordSub').textContent = c.n ? `${c.n} chèque${c.n > 1 ? 's' : ''} à remettre · ${fmtE(c.total)}` : 'Aucun chèque en attente';
  }

  function rendre() {
    rendreEntete();
    const Lp = ST.rows;
    const allSel = Lp.length && Lp.every(o => ST.sel.has(o.id)), some = Lp.some(o => ST.sel.has(o.id));
    $('thead').innerHTML = `<tr><th class="selc"><input type="checkbox" class="ck${some && !allSel ? ' mix' : ''}" id="ckAll" ${allSel ? 'checked' : ''} aria-label="Tout sélectionner"></th>${COLS.map(c => `<th class="${c.cls || ''}${c.xo ? ' xo' : ''}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = Lp.map(o => `<tr data-id="${esc(o.id)}" class="${ST.sel.has(o.id) ? 'sel' : ''}${ST.fiche?.id === o.id ? ' cur' : ''}${o.st === 'annav' || o.st === 'ann' ? ' annav' : ''}"><td class="selc"><input type="checkbox" class="ck" ${ST.sel.has(o.id) ? 'checked' : ''} aria-label="Sélectionner"></td>${COLS.map(c => `<td class="${c.cls || ''}${c.xo ? ' xo' : ''}">${c.html(o)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = Lp.length ? '' : P.vierge
      ? '<div class="cempty"><b>Aucune facture pour l\'instant</b>Une facture naît à l\'encaissement d\'une visite, ou d\'une vente au comptoir.</div>'
      : '<div class="cempty"><b>Aucun document</b>Modifiez la période, la recherche ou les filtres.</div>';
    rendrePager();
    majBulk();
  }

  function majBulk() {
    const n = ST.sel.size;
    $('bulk').classList.toggle('on', n > 0);
    $('bulkN').textContent = n;
    $('bulkL').textContent = n > 1 ? 'sélectionnées' : 'sélectionnée';
  }

  /* Drafts are not invoices yet: the bulk bar leaves them out. */
  function selDocs() {
    const ids = [], av = [];
    ST.sel.forEach(id => { const o = ST.known.get(id); if (!o || o.st === 'brou') return; (o.kind === 'av' ? av : ids).push(id); });
    return { ids, av, n: ids.length + av.length };
  }
  const selUrl = (base, s) => `${base}?${[...s.ids.map(id => 'ids[]=' + encodeURIComponent(id)), ...s.av.map(id => 'av[]=' + encodeURIComponent(id))].join('&')}`;
  const telecharger = (url, name) => { const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); };

  /* ═════════ Sheet ═════════ */

  async function ouvrirFiche(id, kind) {
    const seq = ++ST.seq;
    const url = (kind === 'av' ? P.urls.sheetAv : P.urls.sheetFac).replace(NIL, encodeURIComponent(id));
    try {
      const r = await fetch(url, { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const o = await r.json();
      if (seq !== ST.seq) return;
      ST.fiche = o;
      czone.classList.add('ouvert');
      rendreFiche();
      rendre();
      $('fiBody').scrollTop = 0;
      majAdresse();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('Le document n\'a pas pu être ouvert', 'danger');
    }
  }
  function fermerFiche() {
    if (!ST.fiche) return;
    ST.fiche = null;
    czone.classList.remove('ouvert');
    rendre();
    majAdresse();
  }

  const animauxNoms = o => o.anis.map(a => a.n);

  function paperHtml(o) {
    const st = o.st, av = o.kind === 'av', c = o.cli;
    const ids = [LH.siret ? 'SIRET ' + esc(LH.siret) : '', LH.vat ? 'TVA ' + esc(LH.vat) : ''].filter(Boolean).join(' · ');
    const entete = `<div><b>${esc(LH.name)}</b><p>${(LH.lines || []).map(esc).join('<br>')}${LH.contact ? '<br>' + esc(LH.contact) : ''}${ids ? '<br>' + ids : ''}</p></div>`;
    const sign = av ? '−' : '';
    const rows = o.lignes.map(x => `<tr><td>${esc(x.lib)}</td><td class="n">${esc(x.q)}</td><td class="n">${fmtE(x.pu)}</td><td class="n">${esc(x.t)} %</td><td class="n">${sign}${fmtE(x.tot)}</td></tr>`).join('');
    const tva = o.tva.map(v => `<div><span>Total HT</span><span>${sign}${fmtE(v.ht)}</span></div><div><span>TVA ${esc(v.t)} %</span><span>${sign}${fmtE(v.tva)}</span></div>`).join('');
    const reste = !av && st !== 'brou'
      ? `${o.credite ? `<div><span>Avoirs</span><span>−${fmtE(o.credite)}</span></div>` : ''}<div><span>Réglé</span><span>${fmtE(o.paye)}</span></div>${o.du ? `<div class="r"><span>Reste dû</span><span>${fmtE(o.du)}</span></div>` : ''}`
      : '';
    const legal = `${st !== 'brou' ? `Opération : ${esc(o.nature)}. ` : ''}${av
      ? `Avoir sur la facture ${esc(o.origin.num)}${o.origin.d ? ` du ${fmtD(o.origin.d)}` : ''} · motif : ${esc(o.motif)}${o.remb ? ` · remboursé par ${esc(o.remb.l.toLowerCase())}` : ' · déduit des prochaines factures'}.`
      : st === 'brou' ? 'Document provisoire — non valable comme facture.' : 'Règlement à réception.'}`;
    return `${st === 'brou' ? '<div class="wm">BROUILLON</div>' : ''}
      <div class="pp-head">${entete}<div class="r"><div class="pp-doc">${av ? 'Avoir' : 'Facture'}</div><p>N° ${esc(o.num || '— (à l\'encaissement)')}<br>Date : ${fmtD(o.d)}<br>${av ? `Sur facture ${esc(o.origin.num)}` : 'Praticien : ' + esc(o.vet ? o.vet.n : '—')}</p></div></div>
      <div class="pp-meta"><div><p class="k">Client</p><p><b>${esc(c.n)}</b>${c.att ? `<br>À l'attention de ${esc(c.att)}` : ''}${(c.adr || []).length ? '<br>' + c.adr.map(esc).join('<br>') : ''}</p></div><div><p class="k">${o.anis.length > 1 ? 'Animaux' : 'Animal'}</p><p>${animauxNoms(o).map(n => `<b>${esc(n)}</b>`).join('<br>') || '—'}</p></div></div>
      <table class="pp-tab"><thead><tr><th>Désignation</th><th class="n">Qté</th><th class="n">PU TTC</th><th class="n">TVA</th><th class="n">Total TTC</th></tr></thead><tbody>${rows}</tbody></table>
      <div class="pp-tot">${o.remise ? `<div><span>Remise${o.remise.motif ? ` (${esc(o.remise.motif)})` : ''}</span><span>−${fmtE(o.remise.m)} HT</span></div>` : ''}${tva}
        <div class="g"><span>Total TTC</span><span>${sign}${fmtE(o.tot)}</span></div>${reste}</div>
      <div class="pp-legal">${legal}</div>`;
  }

  function rendreFiche() {
    const o = ST.fiche;
    if (!o) return;
    const st = o.st, [sc, sl] = STATUTS[st], av = o.kind === 'av', A = o.actions || {}, c = o.cli;
    $('fiHead').innerHTML = `<div class="fi-ico">${ICO.rx}</div><div class="fi-t"><b>${esc(c.n)}</b><span><span class="code">${esc(o.num || 'Brouillon')}</span> · <span class="puce ${sc}">${sl}</span></span></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;

    let band = '';
    if (st === 'brou' && ST.ready && ST.ready.id === o.id) {
      band = `<div class="bandeau crit">${ICO.crit}<div><b>Facture non émise</b>Une facture professionnelle exige ces informations ; rien n'a été numéroté, le brouillon reste ouvert.<ul class="rdy">${ST.ready.items.map(x => `<li><span>${esc(x.text)}</span>${x.href ? `<a href="${esc(x.href)}">${esc(x.label)}</a>` : ''}</li>`).join('')}</ul></div></div>`;
    } else if (st === 'brou') {
      band = o.cons && o.cons.open
        ? `<div class="bandeau info">${ICO.info}<div><b>Consultation en cours</b>La facture se complète au fil du plan de traitement ; elle sera numérotée et encaissée à la clôture.</div></div>`
        : `<div class="bandeau info">${ICO.info}<div><b>Prête à encaisser</b>La facture reprend le plan de traitement de la visite ; elle prend son numéro à l'encaissement.</div></div>`;
    } else if (!av && o.du) {
      const j = o.retard || ST.known.get(o.id)?.retard || 0;
      band = `<div class="dueband${j ? ' retard' : ''}"><div class="lt"><small>${j ? `Reste dû · en retard de ${j} jours` : 'Reste dû'}</small><b>${fmtE(o.du)}</b></div>${j ? `<button type="button" class="btn" id="fiRel" disabled data-tip="${ENVOI_TIP}">Relancer</button>` : ''}</div>`;
    } else if (!av && st === 'emise') {
      const last = o.pays.at(-1);
      band = `<div class="paidband">${ICO.ok}<span><b>Soldée</b>${last ? ` · le ${fmtD(last.d)} par ${esc(last.l.toLowerCase())}` : ''}${o.credit ? ` · ${fmtE(o.credit)} en avoir client` : ''}</span></div>`;
    } else if (st === 'annav') {
      band = `<div class="bandeau info">${ICO.info}<div><b>Entièrement créditée</b>Par ${o.avoirs.map(a => esc(a.num)).join(', ')} · la facture reste au registre avec son numéro.</div></div>`;
    } else if (st === 'ann') {
      band = `<div class="bandeau crit">${ICO.crit}<div><b>Annulée${o.annul ? ` le ${fmtD(o.annul.d)}` : ''}</b>${esc(o.annul?.motif || '')} · conservée au registre.</div></div>`;
    }

    const lien = (href, ico, b, s, goto) => `<a class="ocons" href="${esc(href)}"${goto ? ` data-goto="${esc(goto.id)}" data-kind="${goto.kind}"` : ''}><span class="e"${goto && goto.kind === 'av' ? ' style="background:#f5f3ff;color:#6d28d9"' : ''}>${ico}</span><span class="lt"><b>${b}</b><span>${s}</span></span><span class="lien">Ouvrir ${ICO.ext}</span></a>`;
    const links = av
      ? lien(P.urls.detailsFac.replace(NIL, o.origin.id), ICO.rx, `Facture d'origine ${esc(o.origin.num)}`, `${esc(o.motif)} · ${o.remb ? 'remboursé par ' + esc(o.remb.l.toLowerCase()) : 'gardé en avoir client'}`, { id: o.origin.id, kind: 'fac' })
      : `<div class="links">${o.cons ? lien(o.cons.url, ICO.steth, esc(o.objet), `Consultation du ${fmtD(o.cons.d)}${o.vet ? ' · ' + esc(o.vet.n) : ''}`) : ''}
        ${o.ords.map(r => lien(r.url, ICO.rx, `Ordonnance ${esc(r.num)}`, 'Liée à cette consultation')).join('')}
        ${o.avoirs.map(a => lien(P.urls.detailsAv.replace(NIL, a.id), ICO.rx, `Avoir ${esc(a.num)} · −${fmtE(a.tot)}`, `${esc(a.motif)} · le ${fmtD(a.d)}`, { id: a.id, kind: 'av' })).join('')}</div>`;

    const sign = av ? '−' : '';
    const pays = av
      ? (o.remb ? `<div class="pay"><span class="md" style="background:${MODES[o.remb.mode].bg};color:${MODES[o.remb.mode].c}">${MODES[o.remb.mode].s}</span><span class="lt"><b>${esc(o.remb.l)}</b><span>${fmtD(o.d)} · Remboursement</span></span><span class="m">−${fmtE(o.remb.m)}</span></div>` : '')
      : o.pays.map(p => { const m = MODES[p.mode] || MODES.autre; return `<div class="pay"><span class="md" style="background:${m.bg};color:${m.c}">${m.s}</span><span class="lt"><b>${esc(p.l)}${p.mode === 'chq' ? (p.remis ? ' · remis en banque' : ' · à remettre en banque') : ''}</b><span>${fmtD(p.d)}${p.ref ? ' · ' + esc(p.ref) : p.par ? ' · ' + esc(p.par) : ''}</span></span><span class="m">${fmtE(p.m)}</span></div>`; }).join('');

    $('fiBody').innerHTML = `${band}
      <section class="fs"><div class="oinfo">
        <div><small>${av ? 'Établi le' : st === 'brou' ? 'Préparée le' : 'Émise le'}</small><b>${fmtD(o.d)}</b><span>à ${o.h}</span></div>
        <div><small>Praticien</small><b>${esc(o.vet ? o.vet.n : '—')}</b><span>&nbsp;</span></div>
        <div><small>Client</small><b>${esc(c.court)}</b><span>${esc(c.tel || '') || '&nbsp;'}</span></div>
      </div></section>
      ${links}
      <section class="fs"><h3>Détail<span class="tag hint" style="margin:0 0 0 auto;font-weight:400;letter-spacing:0;text-transform:none">${o.lignes.length} ligne${o.lignes.length > 1 ? 's' : ''}</span></h3>
        <table class="flines">${o.lignes.map(x => `<tr${x.credited ? ' class="cr"' : ''}><td>${esc(x.lib)}<small>${fmtE(x.pu)} · TVA ${esc(x.t)} %${x.credited ? ' · créditée' : ''}</small></td><td class="q">× ${esc(x.q)}</td><td class="n">${sign}${fmtE(x.tot)}</td></tr>`).join('')
          || '<tr><td class="hint">Aucun acte facturable n\'a été relevé au plan de la consultation.</td></tr>'}</table>
        <div class="ftot">${o.remise ? `<div><span>Remise${o.remise.motif ? ` · ${esc(o.remise.motif)}` : ''}</span><span>−${fmtE(o.remise.m)} HT</span></div>` : ''}${o.tva.map(v => `<div><span>HT</span><span>${sign}${fmtE(v.ht)}</span></div><div><span>TVA ${esc(v.t)} %</span><span>${sign}${fmtE(v.tva)}</span></div>`).join('')}<div class="g"><span>Total TTC</span><span>${sign}${fmtE(o.tot)}</span></div></div>
      </section>
      ${pays ? `<section class="fs"><h3>${av ? 'Remboursement' : 'Règlements'}</h3>${pays}</section>` : ''}
      <section class="fs"><h3>Document</h3><button type="button" class="thumb" id="fiThumb" aria-label="Voir le document"><div class="paper" id="fiPaper">${paperHtml(o)}</div></button></section>
      <section class="fs"><h3>Historique</h3><div class="hist2">${[...o.hist].reverse().map(h => `<div><time>${fmtD(h.d)}</time><span>${esc(h.t)}<small>${esc(h.s)}</small></span></div>`).join('')}</div></section>`;

    // The thumbnail: the paper laid out at 640 px, scaled to the sheet's width.
    const th = $('fiThumb'), pp = $('fiPaper'), w = th.clientWidth - 28;
    pp.style.width = '640px';
    const k = w / 640;
    pp.style.transform = `scale(${k})`;
    th.style.height = (pp.offsetHeight * k + 28) + 'px';

    const envoi = `<button type="button" class="btn" disabled data-tip="${ENVOI_TIP}">Envoyer</button>`;
    if (st === 'brou') {
      $('fiFoot').innerHTML = `<span class="sp"></span>${A.consultation ? `<a class="btn${A.checkout ? '' : ' primaire'}" href="${esc(A.consultation)}">Reprendre dans la consultation</a>` : ''}${A.checkout ? `<button type="button" class="btn primaire" id="fiPay2"${A.checkout.hasLines ? '' : ' disabled data-tip="Aucun acte à encaisser"'}>Encaisser ${fmtE(o.du)}</button>` : ''}`;
    } else if (av) {
      $('fiFoot').innerHTML = `<span class="sp"></span>${envoi}<button type="button" class="btn primaire" id="fiPrint">Imprimer</button>`;
    } else {
      $('fiFoot').innerHTML = `${A.avoir ? '<button type="button" class="btn ghost-danger" id="fiAv">Établir un avoir</button>' : ''}<span class="sp"></span>${envoi}<button type="button" class="btn${A.pay ? '' : ' primaire'}" id="fiPrint">Imprimer</button>${A.pay ? `<button type="button" class="btn primaire" id="fiPay2">Encaisser ${fmtE(o.du)}</button>` : ''}`;
    }
  }

  /* ═════════ Acts posted to their controllers ═════════ */

  function poster(act, fields = {}) {
    if (ST.posting) return;
    ST.posting = true;
    const f = $('factForm');
    f.action = act.url;
    $('ffTok').value = act.token;
    const map = { invoice_id: 'ffInvoice', method: 'ffMethod', amount: 'ffAmount', reference: 'ffRef', discount_percent: 'ffRem', discount_reason: 'ffRemMotif', lines: 'ffLines', reason: 'ffReason', refund: 'ffRefund', mode: 'ffMode', client: 'ffClient', payer: 'ffPayer', civ: 'ffCiv', nom: 'ffNom', pre: 'ffPre', tel: 'ffTel', mail: 'ffMail', fisc: 'ffFisc', rs: 'ffRs', siren: 'ffSiren', tva: 'ffTva', imprimer: 'ffPrint' };
    Object.entries(map).forEach(([k, id]) => { $(id).value = fields[k] ?? ''; });
    f.requestSubmit();
  }
  async function posterJson(url, data) {
    const body = new FormData();
    Object.entries(data).forEach(([k, v]) => body.append(k, v ?? ''));
    const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' }, signal });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.error || 'L\'opération n\'a pas pu être enregistrée');
    return d;
  }

  /* ═════════ Windows ═════════ */

  const ouvrirModale = id => $(id).classList.add('open');
  const fermerModales = () => root.querySelectorAll('.mveil.open').forEach(m => m.classList.remove('open'));
  // The desk only offers what the clinic takes (Paramètres › Facturation › Moyens acceptés).
  const ACCEPT = Array.isArray(P.modes) && P.modes.length ? P.modes : ['cb', 'esp', 'chq', 'vir'];
  const accepted = liste => { const l = liste.filter(m => ACCEPT.includes(m)); return l.length ? l : liste; };
  const chipsModes = (id, sel, liste) => { $(id).innerHTML = accepted(liste).map(m => `<button type="button" class="chip${m === sel ? ' on' : ''}" data-m="${m}">${MODES[m].l}</button>`).join(''); };

  function ouvrirApercu(o) {
    $('pvT').textContent = `${o.kind === 'av' ? 'Avoir' : 'Facture'} ${o.num || '— brouillon'}`;
    $('pvS').textContent = `${o.cli.n} · ${STATUTS[o.st][1]}`;
    $('pvPaper').innerHTML = paperHtml(o);
    $('pvPrint').style.display = o.st === 'brou' ? 'none' : '';
    $('pvPdf').style.display = o.st === 'brou' ? 'none' : '';
    ouvrirModale('mPrev');
  }

  /* Encaisser */
  let PY = null;
  function ouvrirPay(o) {
    const A = o.actions || {};
    const act = A.checkout || A.pay;
    if (!act) return;
    PY = { o, act, draft: !!A.checkout, mode: accepted(['cb', 'esp', 'chq', 'vir'])[0] };
    $('pySub').textContent = `${o.num || 'Brouillon'} · ${o.cli.n} · reste ${fmtE(o.du)}`;
    $('pyRemRow').style.display = PY.draft ? '' : 'none';
    $('pyRem').value = '';
    $('pyRemMotif').value = '';
    chipsModes('pyMode', PY.mode, ['cb', 'esp', 'chq', 'vir']);
    $('pyM').value = fmtN(o.du / 100);
    majPay();
    ouvrirModale('mPay');
    setTimeout(() => $('pyM').select(), 50);
  }
  /* What is left to pay, after the discount a draft may still take. */
  function resteAPayer() {
    if (!PY) return 0;
    const pct = PY.draft ? Math.min(100, Math.max(0, parseN($('pyRem').value) || 0)) : 0;
    return Math.round(PY.o.du * (1 - pct / 100));
  }
  function majPay() {
    const m = cents($('pyM').value) || 0, r = resteAPayer();
    $('pyXtra').innerHTML = PY.mode === 'esp'
      ? `<label class="flbl" for="pyRecu">Reçu du client</label><div class="inu"><input class="finput num" id="pyRecu" inputmode="decimal" placeholder="${fmtN(m / 100)}"><i>€</i></div>`
      : PY.mode === 'chq' ? '<label class="flbl" for="pyRef">N° de chèque · banque</label><input class="finput" id="pyRef" placeholder="n° 1234567 · banque" maxlength="120">' : '';
    majRendu();
    const ok = m > 0 && m <= r;
    const b = $('pyOk');
    b.disabled = !ok;
    b.textContent = `Encaisser ${fmtE(m)}`;
  }
  function majRendu() {
    const m = cents($('pyM').value) || 0, re = $('pyRecu'), v = re ? cents(re.value) : null;
    $('pyRendu').innerHTML = PY.mode === 'esp' && v > m ? `Rendu monnaie : <b>${fmtE(v - m)}</b>` : '';
  }

  /* Avoir */
  let AV = null;
  function ouvrirAvoir(o) {
    if (!o.actions?.avoir) return;
    const libres = o.lignes.filter(x => !x.credited);
    const somme = libres.reduce((s, x) => s + x.tot, 0);
    // A discounted invoice credits what was charged: lines scaled down to what is left to credit.
    const ratio = somme ? Math.min(1, (o.tot - o.credite) / somme) : 1;
    AV = { o, sel: new Set(), motif: null, remb: 'cb', ratio, libres };
    $('avSub').textContent = `${o.num} · ${o.cli.n} · ${fmtE(o.tot)}`;
    $('avLines').innerHTML = `<div class="avl" data-all><input type="checkbox" class="ck" tabindex="-1"><span class="lt"><b>${o.credite ? 'Tout ce qui reste' : 'Toute la facture'}</b><span>${o.credite ? 'Annule le reste de la facture' : 'Annule entièrement la facture'}</span></span><span>${fmtE(o.tot - o.credite)}</span></div>`
      + o.lignes.map(x => `<div class="avl${x.credited ? ' done' : ''}" data-i="${esc(x.id)}"><input type="checkbox" class="ck" tabindex="-1"${x.credited ? ' checked disabled' : ''}><span class="lt">${esc(x.lib)}<span>× ${esc(x.q)}${x.credited ? ' · déjà créditée' : ''}</span></span><span>${fmtE(x.credited ? x.tot : Math.round(x.tot * ratio))}</span></div>`).join('');
    $('avMotif').innerHTML = MOTIFS_AV.map(m => `<button type="button" class="chip" data-m="${m}">${m}</button>`).join('');
    majAv();
    ouvrirModale('mAv');
  }
  function totalAvoir() {
    if (!AV) return 0;
    if (AV.sel.size && AV.sel.size === AV.libres.length) return AV.o.tot - AV.o.credite;
    return AV.libres.filter(x => AV.sel.has(x.id)).reduce((s, x) => s + Math.round(x.tot * AV.ratio), 0);
  }
  function majAv() {
    const o = AV.o, tot = totalAvoir();
    root.querySelectorAll('#avLines [data-i]').forEach(el => { if (!el.classList.contains('done')) el.querySelector('.ck').checked = AV.sel.has(el.dataset.i); });
    root.querySelector('#avLines [data-all] .ck').checked = AV.sel.size === AV.libres.length;
    $('avTot').textContent = '−' + fmtE(tot);
    // What the client paid beyond what will be left is given back, or kept as a credit.
    const rendre = Math.max(0, o.paye - (o.tot - o.credite - tot));
    $('avRembRow').style.display = rendre > 0 && tot ? '' : 'none';
    $('avRembLbl').textContent = `Somme déjà réglée · ${fmtE(rendre)} à rendre`;
    $('avRemb').innerHTML = [['cb', 'Rembourser sur la carte'], ['esp', 'Rembourser en espèces'], ['credit', 'Garder en avoir client']].map(([v, l]) => `<button type="button" class="chip${AV.remb === v ? ' on' : ''}" data-r="${v}">${l}</button>`).join('');
    $('avOk').disabled = !(tot > 0 && AV.motif);
  }

  /* Vente au comptoir — every sale names its client: one on file, a newcomer
     whose record the sale creates, or nobody when the client refuses to give
     any contact details (the refusal is kept with the sale). The
     « Professionnel » tab finds a structure on file (legal name or SIREN) or
     opens one with the shared tax-profile block; the sale stays paid at the
     counter (transfer, 30-day terms and e-invoicing come later). */
  let VT = null;
  const VT_MODES = ['cb', 'esp', 'chq'];
  const ICX = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  const LOUPE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
  const norm = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const chif = (v, n) => String(v).replace(/\D/g, '').slice(0, n);
  const fmtTel = d => d.replace(/(\d{2})(?=\d)/g, '$1 ');
  const mailOk = m => /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(m);
  const telOk = d => /^0[1-9]\d{8}$/.test(d);
  const initiales = n => String(n).replace(/^(M\.|Mme)\s+/, '').split(/[\s·]+/).filter(Boolean).slice(0, 2).map(x => x[0]).join('').toUpperCase();
  const focusZone = () => setTimeout(() => { const i = root.querySelector('#vtZone input'); if (i) i.focus(); }, 30);

  async function chercherArticles() {
    const q = $('vtQ').value.trim();
    try {
      const r = await fetch(`${P.urls.articles}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const d = await r.json();
      if (!VT || $('vtQ').value.trim() !== q) return;
      VT.res = d.items;
      VT.rx = d.rx || [];
      rendreVt();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('Le catalogue n\'a pas pu être lu', 'danger');
    }
  }
  /** The person typed in the current tab: the newcomer, or the professional's contact. */
  const per = () => (VT.ty === 'pro' ? VT.pp : VT.p);
  /* The refusal of contact details belongs to the « Particulier » tab only. */
  const sansCoord = () => VT.ty === 'part' && VT.anon;
  async function chercherClients(q, kind = '') {
    const r = await fetch(`${P.urls.clients}?q=${encodeURIComponent(q)}${kind ? '&kind=' + kind : ''}`, { headers: { Accept: 'application/json' }, signal });
    if (!r.ok) throw new Error(String(r.status));
    const d = await r.json();
    d.items.forEach(c => VT.known.set(c.id, c));
    return d.items;
  }
  async function rechercheCli() {
    const pro = VT.ty === 'pro', q = pro ? VT.pq : VT.q, n = norm(q), d = q.replace(/\D/g, '');
    if (n.length < 2 && d.length < 3) { if (pro) { VT.pcl = null; VT.preg = null; VT.pregDown = false; } else VT.cl = null; rendreCl(); return; }
    // The registry needs 3 letters, or a SIREN / SIRET.
    if (pro && (n.replace(/\d/g, '').trim().length >= 3 || d.length === 9 || d.length === 14)) chercherSirene(q);
    else if (pro) Object.assign(VT, { preg: null, pregSlow: false, pregDown: false });
    try {
      const items = await chercherClients(q, pro ? 'pro' : '');
      if (!VT || (pro ? VT.pq : VT.q) !== q) return;
      if (pro) VT.pcl = items; else VT.cl = items;
      rendreCl();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('Le fichier clients n\'a pas pu être lu', 'danger');
    }
  }
  // A newcomer's mobile that already belongs to a client: use their record instead of creating a second one.
  async function chercherDoublon() {
    const tel = per().tel;
    if (!telOk(tel)) { VT.dbl = null; majPart(); rendreVt(); return; }
    try {
      const items = await chercherClients(tel);
      if (!VT || per().tel !== tel) return;
      VT.dbl = items.find(c => chif(c.tel || '', 10) === tel) || null;
      majPart();
      rendreVt();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('Le fichier clients n\'a pas pu être lu', 'danger');
    }
  }

  function ouvrirVente() {
    VT = { L: [], res: [], rx: [], mode: accepted(VT_MODES)[0], ty: 'cli', cli: null, q: '', cl: null, anon: false, dbl: null, known: new Map(), p: { civ: 'Mme', nom: '', pre: '', tel: '', mail: '' }, rem: new Set(['imp']),
      pc: null, pq: '', pcl: null, preg: null, pregQ: '', pregDown: false, pregSlow: false, pnew: false, fisc: fiscVide(PRO), pp: { civ: 'Mme', nom: '', pre: '', tel: '', mail: '' }, pa: { adr: '', cp: '', ville: '' }, ready: null };
    $('vtQ').value = '';
    rendreVtTy();
    rendreZone();
    rendreReady();
    rendreVt();
    chercherArticles();
    ouvrirModale('mVente');
    setTimeout(() => { const i = $('vtCq'); if (i) i.focus(); }, 50);
  }
  function rendreVtTy() { root.querySelectorAll('#vtTy button').forEach(b => b.classList.toggle('on', b.dataset.t === VT.ty)); }

  /* ── Client ── */
  function carteCli(c) {
    return `<div class="vt-card"><span class="av2">${esc(initiales(c.n))}</span><div class="lt"><b>${esc(c.n)}</b>
      <span class="s">${esc(c.tel || 'Sans téléphone')}${c.adr ? ' · ' + esc(c.adr) : ''}</span></div>
      <button type="button" class="x" data-vtx aria-label="Changer de client">${ICX}</button></div>`;
  }
  /** A structure on file: legal name and badge, identifiers, contact and address. */
  function carteCliPro(c) {
    const ids = c.siren ? `SIREN ${esc(fmtSiren(c.siren))}${c.tva ? ' · TVA ' + esc(fmtVat(c.tva)) : ''}` : 'sans SIREN';
    return `<div class="vt-card"><span class="av2">${esc(initiales(c.rs || c.n))}</span><div class="lt"><b>${esc(c.rs || c.n)}${c.badge ? ` <span class="vt-badge">${esc(c.badge)}</span>` : ''}</b>
      <span class="s">${ids}</span><span class="s">${esc(c.n)}${c.adr ? ' · ' + esc(c.adr) : ''}</span></div>
      <button type="button" class="x" data-vpx aria-label="Changer de client">${ICX}</button></div>${c.warn ? `<div class="vt-msg warn">${ICO.info}<div><b>${esc(c.warn)}</b> · vérifiez avec le client ; la vente peut être enregistrée.</div></div>` : ''}`;
  }
  /** The contact person and the means to reach them (shared by the newcomer and the professional's contact). */
  function champsPersonne(p, telPh, aide) {
    return `<div class="vt-g"><div class="seg" id="vtCiv"><button type="button" data-v="M." class="${p.civ === 'M.' ? 'on' : ''}">M.</button><button type="button" data-v="Mme" class="${p.civ === 'Mme' ? 'on' : ''}">Mme</button></div>
        <div class="vt-f"><input class="finput" id="vpNom" placeholder="Nom *" maxlength="40" value="${esc(p.nom)}" aria-label="Nom"></div><div class="vt-f"><input class="finput" id="vpPre" placeholder="Prénom *" maxlength="40" value="${esc(p.pre)}" aria-label="Prénom"></div></div>
      <div class="vt-g2"><div class="vt-f"><input class="finput" id="vpTel" inputmode="numeric" placeholder="${telPh}" value="${fmtTel(p.tel)}" aria-label="Téléphone"><small id="vpTelH"></small></div>
        <div class="vt-f"><input class="finput" id="vpMail" type="email" placeholder="E-mail" maxlength="80" value="${esc(p.mail)}" aria-label="E-mail"><small id="vpMailH"></small></div></div>
      <p class="vt-aide">${aide}</p>
      <div id="vpDbl"></div>`;
  }
  function rendreZonePro(z) {
    if (VT.pc) { z.innerHTML = carteCliPro(VT.known.get(VT.pc)); return; }
    if (!VT.pnew) {
      z.innerHTML = `<div class="crech" style="max-width:none">${LOUPE}<input id="vtPq" placeholder="Raison sociale ou SIREN…" autocomplete="off" value="${esc(VT.pq)}"></div><div class="vt-l" id="vtCl"></div>`;
      rendreCl();
      return;
    }
    const a = VT.pa;
    z.innerHTML = `<div id="vtFisc"></div><div class="vt-h" style="margin-top:12px">Adresse</div>
      <div class="vt-f"><input class="finput" id="vpAdr" placeholder="Adresse" maxlength="80" value="${esc(a.adr)}" aria-label="Adresse"></div>
      <div class="vt-g2"><div class="vt-f"><input class="finput" id="vpCp" inputmode="numeric" placeholder="Code postal" maxlength="5" value="${esc(a.cp)}" aria-label="Code postal"></div>
        <div class="vt-f"><input class="finput" id="vpVille" placeholder="Ville" maxlength="80" value="${esc(a.ville)}" aria-label="Ville"></div></div>
      <p class="vt-aide">En France. Obligatoire pour un professionnel assujetti à la TVA : elle figure sur sa facture électronique.</p>
      <div class="vt-h" style="margin-top:12px">Contact</div>
      ${champsPersonne(VT.pp, 'Mobile ou fixe', 'Un téléphone (mobile ou fixe) ou un e-mail au moins.')}
      <div class="vt-alt"><button type="button" data-vpback>Rechercher un professionnel déjà client</button></div>`;
    monterFisc($('vtFisc'), VT.fisc, () => rendreVt(), { sirenUrl: P.urls.siren, structureOnly: true });
    majPart();
  }
  function rendreZone() {
    const z = $('vtZone');
    if (VT.ty === 'pro') { rendreZonePro(z); return; }
    if (VT.ty === 'cli') {
      if (VT.cli) { z.innerHTML = carteCli(VT.known.get(VT.cli)); return; }
      z.innerHTML = `<div class="crech" style="max-width:none">${LOUPE}<input id="vtCq" placeholder="Nom, téléphone ou animal…" autocomplete="off" value="${esc(VT.q)}"></div><div class="vt-l" id="vtCl"></div>`;
      rendreCl();
      return;
    }
    if (VT.anon) {
      z.innerHTML = `<div class="vt-msg warn">${ICO.info}<div><b>Vente sans coordonnées</b><br>Le client refuse de les donner : ticket sans nom, refus tracé dans le journal de caisse.</div><button type="button" class="act" data-vtanon="0">Saisir quand même</button></div>`;
      return;
    }
    z.innerHTML = `${champsPersonne(VT.p, 'Mobile · 06 00 00 00 00', 'Un mobile ou un e-mail au moins : c\'est ce qui permet de retrouver et de joindre ce client.')}
      <div class="vt-alt"><button type="button" data-vtanon="1">Le client refuse de donner ses coordonnées</button></div>`;
    majPart();
  }
  function rendreCl() {
    const el = $('vtCl');
    if (!el) return;
    if (VT.ty === 'pro') {
      const l = VT.pcl;
      el.innerHTML = `<div class="vt-grp">Clients de la clinique</div>${!l ? '' : l.length
        ? l.map(c => `<button type="button" class="vt-o" data-pcli="${esc(c.id)}"><span class="lt"><b>${esc(c.rs || c.n)}${c.badge ? ` <span class="vt-badge">${esc(c.badge)}</span>` : ''}</b><span class="s">${c.siren ? 'SIREN ' + esc(fmtSiren(c.siren)) + ' · ' : ''}${esc(c.n)}</span></span></button>`).join('')
        : '<div class="vt-vide">Aucun professionnel trouvé.</div>'}
        ${rendreSirene(l)}
        <button type="button" class="vt-new" data-vpnew>+ Nouveau professionnel</button>`;
      return;
    }
    if (!VT.cl) { el.innerHTML = ''; return; }
    el.innerHTML = VT.cl.length
      ? VT.cl.map(c => `<button type="button" class="vt-o" data-cli="${esc(c.id)}"><span class="lt"><b>${esc(c.n)}</b><span class="s">${esc(c.tel || '')}${c.adr ? ' · ' + esc(c.adr) : ''}</span></span></button>`).join('')
      : '<div class="vt-vide">Aucun client trouvé.<button type="button" data-vtnew>Créer un nouveau particulier</button></div>';
  }
  /* The registry hits (mock-up facturation-v-topbar_5 rendrePl), companies already in file left out. */
  function rendreSirene(clients) {
    const tete = '<div class="vt-grp">Annuaire des entreprises · SIRENE</div>';
    if (VT.pregDown) return `${tete}<div class="vt-vide">Annuaire indisponible pour le moment : créez le professionnel à la main.</div>`;
    const lent = VT.pregSlow ? '<div class="vt-vide">Recherche ralentie, continuez à taper.</div>' : '';
    if (!VT.preg) return `${tete}${lent || '<div class="vt-vide">Les entreprises viennent de la base SIRENE : raison sociale, SIREN, adresse et TVA sont repris tels quels.</div>'}`;
    const connus = new Set((clients || []).map(c => c.siren).filter(Boolean));
    const L = VT.preg.filter(h => !connus.has(h.siren));
    return tete + lent + (L.length
      ? L.map(h => `<button type="button" class="vt-o" data-sir="${esc(h.siren)}"><span class="lt"><b>${esc(h.n)}</b><span class="s">${esc([h.forme, h.ets[0]?.a.split(', ').pop()].filter(Boolean).join(' · '))}${h.cesse ? ' · radiée' : ''}</span></span><code>${esc(fmtSiren(h.siren))}</code></button>`).join('')
      : '<div class="vt-vide">Aucune autre entreprise dans l\'annuaire.</div>');
  }
  /* The registry, asked beside the clinic's own clients; throttled keeps the last hits. */
  async function chercherSirene(q) {
    if (!P.urls.registry) return;
    try {
      const r = await fetch(`${P.urls.registry}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const d = await r.json();
      if (!VT || VT.pq !== q) return;
      if (!d.available && d.reason === 'throttled') {
        // Hits of another search would mislead: only those of the text typed stay.
        if (VT.pregQ !== q) VT.preg = null;
        VT.pregSlow = true;
      } else if (!d.available) Object.assign(VT, { preg: null, pregDown: true, pregSlow: false });
      else Object.assign(VT, { preg: d.items, pregQ: q, pregDown: false, pregSlow: false });
      rendreCl();
    } catch (e) {
      if (e.name !== 'AbortError' && VT && VT.pq === q) { VT.preg = null; VT.pregDown = true; rendreCl(); }
    }
  }
  function majPart() {
    const p = per(), t = $('vpTel');
    if (!t) return;
    const th = $('vpTelH'), m = $('vpMail'), mh = $('vpMailH');
    t.classList.toggle('ko', p.tel.length === 10 && !telOk(p.tel));
    t.classList.toggle('ok', telOk(p.tel));
    th.textContent = p.tel.length === 10 && !telOk(p.tel) ? 'Numéro invalide' : '';
    th.className = 'ko';
    m.classList.toggle('ko', !!p.mail && !mailOk(p.mail) && document.activeElement !== m);
    m.classList.toggle('ok', mailOk(p.mail));
    mh.textContent = p.mail && !mailOk(p.mail) && document.activeElement !== m ? 'Adresse incomplète' : '';
    mh.className = 'ko';
    const dbl = VT.dbl && VT.dbl.tel && chif(VT.dbl.tel, 10) === p.tel ? VT.dbl : null;
    $('vpDbl').innerHTML = dbl ? `<div class="vt-msg warn">${ICO.info}<div><b>Ce numéro est déjà celui de ${esc(dbl.n)}</b><br>Utilisez sa fiche pour ne pas créer de doublon.</div><button type="button" class="act" data-cli="${esc(dbl.id)}">Utiliser sa fiche</button></div>` : '';
  }

  /* What the professional invoice still lacked when the sale was refused (nothing was recorded). */
  function rendreReady() {
    const el = $('vtReady');
    if (!el) return;
    const L = VT && VT.ready;
    el.innerHTML = L && L.length
      ? `<div class="vt-msg crit">${ICO.crit}<div><b>La facture professionnelle ne peut pas être émise</b><ul class="rdy">${L.map(x => `<li><span>${esc(x.text)}</span>${x.href ? `<a href="${esc(x.href)}">${esc(x.label)}</a>` : ''}</li>`).join('')}</ul></div></div>`
      : '';
  }

  /* ── Articles et règlement ── */
  function clientOk() {
    if (VT.ty === 'cli') return VT.cli ? '' : 'Choisissez le client';
    const pro = VT.ty === 'pro';
    if (pro && VT.pc) return '';
    if (pro && !VT.pnew) return 'Choisissez le client';
    if (pro) { const fe = Object.values(fiscErr(VT.fisc, true))[0]; if (fe) return fe.replace(/\.$/, ''); }
    // A VAT-liable professional is invoiced at its postal address.
    if (pro && VT.fisc.fisc === PRO) {
      if (!VT.pa.adr.trim()) return 'Adresse du professionnel requise';
      if (!/^\d{5}$/.test(VT.pa.cp)) return 'Code postal requis (5 chiffres)';
      if (!VT.pa.ville.trim()) return 'Ville requise';
    }
    if (sansCoord()) return '';
    const p = per();
    if (p.nom.length < 2) return pro ? 'Nom du contact requis' : 'Nom du client requis';
    if (!p.pre) return pro ? 'Prénom du contact requis' : 'Prénom du client requis';
    if (!telOk(p.tel) && !mailOk(p.mail)) return pro ? 'Téléphone ou e-mail requis' : 'Mobile ou e-mail requis';
    if (p.tel.length && !telOk(p.tel)) return pro ? 'Téléphone invalide' : 'Mobile invalide';
    if (p.mail && !mailOk(p.mail)) return 'E-mail invalide';
    if (VT.dbl && chif(VT.dbl.tel || '', 10) === p.tel) return 'Client déjà connu : utilisez sa fiche';
    return '';
  }
  function rendreVt() {
    const q = $('vtQ').value.trim();
    $('vtRes').innerHTML = (VT.res.map(a => `<button type="button" class="citem" data-k="${esc(a.k)}" style="padding:7px 10px"><span class="lt"><b style="font-size:.8rem">${esc(a.l)}</b></span><span class="go" style="color:var(--encre)">${fmtE(a.p)}</span></button>`).join('')
      + VT.rx.map(a => `<a class="citem vt-rx" href="${esc(P.urls.ordonnances)}" style="padding:7px 10px;text-decoration:none;color:inherit"><span class="lt"><b style="font-size:.8rem">${esc(a.l)}</b><span style="font-size:.7rem;color:var(--encre-60)">Médicament sur ordonnance : il se délivre depuis l'ordonnance du client</span></span><span class="go">Ordonnances →</span></a>`).join(''))
      || `<p class="hint" style="text-align:center;margin:10px 0">${q ? 'Aucun article en vente libre pour cette recherche.' : 'Aucun article en vente libre au catalogue.'}</p>`;
    $('vtLines').innerHTML = VT.L.map((x, i) => `<div class="li"><div class="lt"><b>${esc(x.l)}</b><span>${fmtE(x.p)} l'unité</span></div><div class="qty"><button type="button" data-vq="${i}" data-d="-1" aria-label="Un de moins">−</button><input value="${x.q}" readonly aria-label="Quantité"><button type="button" data-vq="${i}" data-d="1" aria-label="Un de plus">+</button></div><span class="m">${fmtE(x.q * x.p)}</span><button type="button" class="rm" data-vrm="${i}" aria-label="Retirer">${ICO.rm}</button></div>`).join('');
    const tot = VT.L.reduce((s, x) => s + x.q * x.p, 0);
    chipsModes('vtMode', VT.mode, VT_MODES);
    if (VT.ty === 'pro') {
      // Credit sales come with e-invoicing: the professional pays at the counter for now.
      $('vtMode').insertAdjacentHTML('beforeend', '<button type="button" class="chip" disabled data-tip="Bientôt disponible">Virement · bientôt</button><button type="button" class="chip" disabled data-tip="Bientôt disponible">À régler sous 30 jours · bientôt</button>');
      $('vtFe').innerHTML = `<div class="vt-msg">${ICO.info}<div><b>Vente à un professionnel</b> · réglée au comptoir. La facture électronique et le paiement à 30 jours seront bientôt disponibles.</div></div>`;
    } else {
      $('vtFe').innerHTML = `<div class="vt-msg">${ICO.info}<div><b>Vente à un particulier</b> · pas de facture électronique : les totaux du jour relèvent de l'e-reporting (bientôt disponible).</div></div>`;
    }
    rendreRemise();
    const manque = clientOk() || (!tot ? 'Ajoutez un article' : '') || (!VT.rem.size ? 'Choisissez comment remettre la facture' : '');
    $('vtTot').textContent = fmtE(tot);
    $('vtManque').textContent = manque;
    const b = $('vtOk');
    b.disabled = !!manque;
    b.textContent = tot ? `Encaisser ${fmtE(tot)}` : 'Encaisser';
  }

  /* How the invoice is handed over. Sending it by e-mail or SMS is not wired
     yet, so printing is the only channel the desk can pick for now. */
  const REMISE_OFF = { mail: 'L\'envoi par e-mail n\'est pas encore disponible', sms: 'L\'envoi par SMS n\'est pas encore disponible' };
  function rendreRemise() {
    const LBL = { imp: sansCoord() ? 'Imprimer le ticket' : 'Imprimer', mail: 'E-mail', sms: 'SMS' };
    $('vtRem').innerHTML = (sansCoord() ? ['imp'] : ['imp', 'mail', 'sms']).map(r => `<button type="button" class="chip${VT.rem.has(r) ? ' on' : ''}" data-r="${r}"${REMISE_OFF[r] ? ` disabled title="${esc(REMISE_OFF[r])}"` : ''}>${LBL[r]}</button>`).join('');
    $('vtRemH').textContent = sansCoord() ? 'Sans coordonnées, le ticket est remis en main propre.' : VT.rem.has('imp') ? 'Remise en main propre.' : '';
  }

  /* Clôture de caisse */
  let CLO = {};
  function ouvrirClo() {
    CLO = { compte: { esp: null, chq: null } };
    $('cloSub').textContent = `${dLong(TODAY)} · comptez ce qui est physiquement en caisse`;
    $('cloCom').value = '';
    rendreClo();
    ouvrirModale('mClo');
  }
  function rendreClo() {
    const att = ST.caisse.attendu;
    const rows = [['cb', 'Carte bancaire', false], ['esp', 'Espèces', true], ['chq', 'Chèques', true], ['vir', 'Virements', false]];
    let ecart = 0;
    $('cloTab').innerHTML = '<tr><th>Moyen</th><th class="n">Attendu</th><th class="n">Compté</th><th class="n">Écart</th></tr>' + rows.map(([k, l, cpt]) => {
      const th = att[k] || 0, c = CLO.compte[k], e = cpt && c != null ? c - th : null;
      if (e) ecart += e;
      return `<tr><td>${l}</td><td class="n">${fmtE(th)}</td><td class="n">${cpt ? `<input class="finput" data-cl="${k}" value="${c == null ? '' : fmtN(c / 100)}" placeholder="${fmtN(th / 100)}" inputmode="decimal" aria-label="${l} comptés">` : '<span style="color:var(--encre-35)">télécollecte</span>'}</td><td class="n">${e == null ? '' : `<span class="${e ? 'ec1' : 'ec0'}">${e > 0 ? '+' : e < 0 ? '−' : ''}${fmtE(Math.abs(e))}</span>`}</td></tr>`;
    }).join('')
      + `<tr class="tot"><td>Total encaissé</td><td class="n">${fmtE(ST.caisse.total)}</td><td></td><td class="n">${ecart ? `<span class="ec1">${ecart > 0 ? '+' : '−'}${fmtE(Math.abs(ecart))}</span>` : ''}</td></tr>`;
    CLO.ecart = ecart;
    $('cloComRow').style.display = ecart ? '' : 'none';
    majClo();
  }
  function majClo() {
    const b = $('cloOk'), fait = ST.caisse.cloture;
    b.disabled = !!fait || (!!CLO.ecart && !$('cloCom').value.trim());
    b.textContent = fait ? `Journée clôturée · ticket Z n° ${fait.num}` : 'Clôturer la journée';
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

  $('thead').addEventListener('click', e => {
    if (e.target.id !== 'ckAll') return;
    const all = ST.rows.every(o => ST.sel.has(o.id));
    ST.rows.forEach(o => (all ? ST.sel.delete(o.id) : ST.sel.add(o.id)));
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
    ouvrirFiche(tr.dataset.id, ST.known.get(tr.dataset.id)?.kind);
  }, { signal });

  $('q').addEventListener('input', e => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => filtrer({ q: e.target.value.trim() }), SEARCH_DEBOUNCE_MS);
  }, { signal });

  const hmenu = $('hmenu');
  $('hmenuBtn').addEventListener('click', e => { e.stopPropagation(); hmenu.classList.toggle('open'); }, { signal });
  document.addEventListener('click', e => { if (!e.target.closest('#hmenu')) hmenu.classList.remove('open'); }, { signal });
  hmenu.querySelectorAll('.dd-it:not([disabled])').forEach(b => b.addEventListener('click', () => hmenu.classList.remove('open'), { signal }));

  $('btnBord').addEventListener('click', async () => {
    if (!ST.caisse.cheques.n) { showToast('Aucun chèque à remettre'); return; }
    // Opened before the request so that the browser lets the slip through.
    const slip = window.open('about:blank', '_blank');
    try {
      const d = await posterJson(P.urls.deposit, { _token: P.tokens.deposit });
      ST.caisse.cheques = { n: 0, total: 0 };
      rendreEntete();
      if (slip) slip.location.href = d.slip; else window.open(d.slip, '_blank', 'noopener');
      showToast(d.message);
      if (ST.fiche) ouvrirFiche(ST.fiche.id, ST.fiche.kind);
    } catch (e) {
      slip?.close();
      if (e.name !== 'AbortError') showToast(e.message, 'danger');
    }
  }, { signal });
  $('btnClos').addEventListener('click', () => {
    const n = ST.caisse.clotures, t = ST.caisse.cloture;
    showToast(n ? `Historique des clôtures : ${n} journée${n > 1 ? 's' : ''} scellée${n > 1 ? 's' : ''}${t ? ` · aujourd'hui, ticket Z n° ${t.num}` : ''}` : 'Aucune journée clôturée pour l\'instant');
  }, { signal });

  $('fiBody').addEventListener('click', e => {
    const g = e.target.closest('[data-goto]');
    if (g) { e.preventDefault(); ouvrirFiche(g.dataset.goto, g.dataset.kind); return; }
    if (e.target.closest('#fiThumb') && ST.fiche) ouvrirApercu(ST.fiche);
  }, { signal });
  $('fiHead').addEventListener('click', e => { if (e.target.closest('#fiX')) fermerFiche(); }, { signal });
  $('fiFoot').addEventListener('click', e => {
    const o = ST.fiche;
    if (!o) return;
    const b = e.target.closest('button');
    if (!b || b.disabled) return;
    if (b.id === 'fiPay2') ouvrirPay(o);
    if (b.id === 'fiPrint') ouvrirApercu(o);
    if (b.id === 'fiAv') ouvrirAvoir(o);
  }, { signal });

  $('pvPrint').addEventListener('click', () => {
    if (!ST.fiche) return;
    fermerModales();
    window.open(ST.fiche.pdf, '_blank', 'noopener');
    showToast('Document envoyé à l\'impression');
  }, { signal });
  $('pvPdf').addEventListener('click', () => {
    if (!ST.fiche) return;
    telecharger(ST.fiche.pdf, `${ST.fiche.num || 'facture'}.pdf`);
    showToast('PDF téléchargé');
  }, { signal });

  $('pyMode').addEventListener('click', e => { const b = e.target.closest('[data-m]'); if (!b) return; PY.mode = b.dataset.m; chipsModes('pyMode', PY.mode, ['cb', 'esp', 'chq', 'vir']); majPay(); }, { signal });
  $('pyM').addEventListener('input', majPay, { signal });
  $('pyRem').addEventListener('input', () => { $('pyM').value = fmtN(resteAPayer() / 100); majPay(); }, { signal });
  $('pyXtra').addEventListener('input', e => { if (e.target.id === 'pyRecu') majRendu(); }, { signal });
  $('pyOk').addEventListener('click', () => {
    if (!PY || $('pyOk').disabled) return;
    const fields = { amount: $('pyM').value, method: METHOD[PY.mode], reference: $('pyRef')?.value.trim() || '' };
    if (PY.draft) Object.assign(fields, { invoice_id: PY.o.id, discount_percent: $('pyRem').value.trim(), discount_reason: $('pyRemMotif').value.trim() });
    poster(PY.act, fields);
  }, { signal });
  $('pyEch').addEventListener('click', () => showToast('Les échéanciers ne sont pas encore disponibles'), { signal });

  $('avLines').addEventListener('click', e => {
    const r = e.target.closest('.avl');
    if (!r || r.classList.contains('done')) return;
    e.preventDefault();
    if (r.dataset.all != null) {
      const all = AV.sel.size === AV.libres.length;
      AV.sel = new Set(all ? [] : AV.libres.map(x => x.id));
    } else if (AV.sel.has(r.dataset.i)) AV.sel.delete(r.dataset.i); else AV.sel.add(r.dataset.i);
    majAv();
  }, { signal });
  $('avMotif').addEventListener('click', e => { const b = e.target.closest('[data-m]'); if (!b) return; AV.motif = b.dataset.m; root.querySelectorAll('#avMotif .chip').forEach(x => x.classList.toggle('on', x === b)); majAv(); }, { signal });
  $('avRemb').addEventListener('click', e => { const b = e.target.closest('[data-r]'); if (!b) return; AV.remb = b.dataset.r; majAv(); }, { signal });
  $('avOk').addEventListener('click', () => {
    if (!AV || $('avOk').disabled) return;
    const remb = $('avRembRow').style.display === 'none' || AV.remb === 'credit' ? '' : AV.remb;
    poster(AV.o.actions.avoir, { lines: [...AV.sel].join(','), reason: AV.motif, refund: remb });
  }, { signal });

  $('btnVente').addEventListener('click', ouvrirVente, { signal });
  $('vtTy').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b || b.disabled || !VT) return;
    VT.ty = b.dataset.t;
    VT.anon = false;
    VT.ready = null;
    rendreVtTy();
    rendreZone();
    rendreReady();
    rendreVt();
    focusZone();
  }, { signal });
  const zone = $('vtZone');
  zone.addEventListener('input', e => {
    const t = e.target;
    if (!VT) return;
    if (t.id === 'vtCq') { VT.q = t.value; window.clearTimeout(clientTimer); clientTimer = window.setTimeout(rechercheCli, SEARCH_DEBOUNCE_MS); }
    if (t.id === 'vtPq') { VT.pq = t.value; window.clearTimeout(clientTimer); clientTimer = window.setTimeout(rechercheCli, SEARCH_DEBOUNCE_MS); }
    if (t.id === 'vpNom') { t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').toUpperCase(); per().nom = t.value.trim(); }
    if (t.id === 'vpPre') { t.value = t.value.replace(/[^A-Za-zÀ-ÿ' -]/g, '').replace(/(^|[\s'-])\p{Ll}/gu, x => x.toUpperCase()); per().pre = t.value.trim(); }
    if (t.id === 'vpTel') { per().tel = chif(t.value, 10); t.value = fmtTel(per().tel); majPart(); chercherDoublon(); }
    if (t.id === 'vpMail') { t.value = t.value.replace(/\s/g, '').toLowerCase(); per().mail = t.value; majPart(); }
    if (t.id === 'vpAdr') VT.pa.adr = t.value;
    if (t.id === 'vpCp') { t.value = chif(t.value, 5); VT.pa.cp = t.value; }
    if (t.id === 'vpVille') VT.pa.ville = t.value;
    rendreVt();
  }, { signal });
  zone.addEventListener('focusout', () => setTimeout(() => { if (VT) { majPart(); rendreVt(); } }, 0), { signal });
  zone.addEventListener('click', e => {
    if (!VT) return;
    const c = e.target.closest('[data-cli]');
    if (c) { VT.ty = 'cli'; VT.cli = c.dataset.cli; rendreVtTy(); rendreZone(); rendreVt(); $('vtQ').focus(); return; }
    if (e.target.closest('[data-vtx]')) { VT.cli = null; VT.ready = null; rendreZone(); rendreReady(); rendreVt(); focusZone(); return; }
    const pc = e.target.closest('[data-pcli]');
    if (pc) { VT.pc = pc.dataset.pcli; rendreZone(); rendreVt(); $('vtQ').focus(); return; }
    if (e.target.closest('[data-vpx]')) { VT.pc = null; VT.ready = null; rendreZone(); rendreReady(); rendreVt(); focusZone(); return; }
    if (e.target.closest('[data-vpnew]')) {
      VT.pnew = true;
      // What was searched is likely the legal name, or the SIREN.
      const q = VT.pq.trim();
      if (q && !VT.fisc.rs && !VT.fisc.siren) { if (/^[\d\s.]+$/.test(q)) VT.fisc.siren = q.replace(/\D/g, ''); else VT.fisc.rs = q; }
      rendreZone(); rendreVt(); focusZone(); return;
    }
    const sir = e.target.closest('[data-sir]');
    if (sir) {
      // « Nouveau professionnel » prefilled with the registry's record.
      const h = (VT.preg || []).find(x => x.siren === sir.dataset.sir);
      if (h) {
        VT.fisc = h.ass ? { ...fiscVide(PRO), rs: h.n, siren: h.siren, tva: h.tva || '' } : { ...fiscVide(IND), rs: h.n };
        if (h.adresse) VT.pa = { adr: h.adresse.rue || '', cp: chif(h.adresse.cp || '', 5), ville: h.adresse.ville || '' };
        VT.pnew = true;
        rendreZone(); rendreVt(); focusZone();
      }
      return;
    }
    if (e.target.closest('[data-vpback]')) { VT.pnew = false; rendreZone(); rendreVt(); focusZone(); return; }
    if (e.target.closest('[data-vtnew]')) {
      const q = VT.q.trim();
      VT.ty = 'part';
      if (/^[\d\s]+$/.test(q)) VT.p.tel = chif(q, 10); else VT.p.nom = q.replace(/[^A-Za-zÀ-ÿ' -]/g, '').toUpperCase().trim();
      rendreVtTy();
      rendreZone();
      rendreVt();
      chercherDoublon();
      setTimeout(() => $(VT.p.nom ? 'vpTel' : 'vpNom').focus(), 30);
      return;
    }
    const an = e.target.closest('[data-vtanon]');
    if (an) { VT.anon = an.dataset.vtanon === '1'; rendreZone(); rendreVt(); return; }
    const cv = e.target.closest('#vtCiv button');
    if (cv) { per().civ = cv.dataset.v; root.querySelectorAll('#vtCiv button').forEach(x => x.classList.toggle('on', x === cv)); }
  }, { signal });
  $('vtQ').addEventListener('input', () => { window.clearTimeout(articleTimer); articleTimer = window.setTimeout(chercherArticles, SEARCH_DEBOUNCE_MS); }, { signal });
  $('vtQ').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); root.querySelector('#vtRes button.citem')?.click(); } }, { signal });
  $('vtRes').addEventListener('click', e => {
    const b = e.target.closest('[data-k]');
    if (!b) return;
    const a = VT.res.find(x => x.k === b.dataset.k), ex = VT.L.find(x => x.k === b.dataset.k);
    if (ex) ex.q++; else if (a) VT.L.push({ k: a.k, l: a.l, p: a.p, q: 1 });
    $('vtQ').value = '';
    rendreVt();
    chercherArticles();
    $('vtQ').focus();
  }, { signal });
  $('vtLines').addEventListener('click', e => {
    const q = e.target.closest('[data-vq]');
    if (q) { const x = VT.L[+q.dataset.vq]; x.q = Math.max(1, x.q + +q.dataset.d); rendreVt(); }
    const r = e.target.closest('[data-vrm]');
    if (r) { VT.L.splice(+r.dataset.vrm, 1); rendreVt(); }
  }, { signal });
  $('vtMode').addEventListener('click', e => { const b = e.target.closest('[data-m]'); if (!b) return; VT.mode = b.dataset.m; rendreVt(); }, { signal });
  $('vtRem').addEventListener('click', e => {
    const b = e.target.closest('[data-r]');
    if (!b || b.disabled || !VT) return;
    if (VT.rem.has(b.dataset.r)) VT.rem.delete(b.dataset.r); else VT.rem.add(b.dataset.r);
    rendreVt();
  }, { signal });
  /* The sale is posted in the background so that a refusal keeps the window
     as typed: an error comes back as a toast, what a professional invoice
     lacks inline (see RecordCounterSaleController); a recorded sale goes to
     its invoice. */
  $('vtOk').addEventListener('click', async () => {
    if (!VT || $('vtOk').disabled || ST.posting) return;
    const p = per();
    const payer = VT.ty === 'cli' || (VT.ty === 'pro' && VT.pc) ? 'cli' : VT.ty === 'pro' ? 'pro' : sansCoord() ? 'anon' : 'part';
    const fields = {
      _token: P.tokens.counter,
      lines: VT.L.map(x => `${x.k}:${x.q}`).join(','),
      mode: VT.mode,
      payer,
      client: payer === 'cli' ? (VT.ty === 'pro' ? VT.pc : VT.cli) : '',
      ...(payer === 'part' || payer === 'pro' ? { civ: p.civ, nom: p.nom, pre: p.pre, tel: p.tel, mail: p.mail } : {}),
      ...(payer === 'pro' ? { ...fiscPayload(VT.fisc), adr: VT.pa.adr.trim(), cp: VT.pa.cp, ville: VT.pa.ville.trim() } : {}),
      imprimer: VT.rem.has('imp') ? '1' : '',
    };
    const body = new FormData();
    Object.entries(fields).forEach(([k, v]) => body.append(k, v ?? ''));
    ST.posting = true;
    $('vtOk').disabled = true;
    VT.ready = null;
    rendreReady();
    try {
      const r = await fetch(P.urls.counter, { method: 'POST', body, headers: { Accept: 'application/json' }, signal });
      const d = await r.json().catch(() => ({}));
      if (r.ok && d.redirect) {
        if (window.Turbo && typeof window.Turbo.visit === 'function') window.Turbo.visit(d.redirect); else window.location.href = d.redirect;
        return;
      }
      ST.posting = false;
      if (Array.isArray(d.readiness)) { VT.ready = d.readiness; rendreReady(); $('vtReady').scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
      else showToast(d.error || 'La vente n\'a pas pu être enregistrée', 'danger');
      rendreVt();
    } catch (e) {
      ST.posting = false;
      if (e.name !== 'AbortError') { showToast('La vente n\'a pas pu être enregistrée', 'danger'); rendreVt(); }
    }
  }, { signal });

  $('btnClo').addEventListener('click', ouvrirClo, { signal });
  $('cloTab').addEventListener('change', e => { const k = e.target.dataset.cl; if (!k) return; CLO.compte[k] = cents(e.target.value); rendreClo(); }, { signal });
  $('cloCom').addEventListener('input', majClo, { signal });
  $('cloOk').addEventListener('click', async () => {
    if ($('cloOk').disabled) return;
    $('cloOk').disabled = true;
    try {
      const d = await posterJson(P.urls.close, {
        _token: P.tokens.close,
        cash: CLO.compte.esp == null ? '' : fmtN(CLO.compte.esp / 100),
        cheques: CLO.compte.chq == null ? '' : fmtN(CLO.compte.chq / 100),
        comment: $('cloCom').value.trim(),
      });
      ST.caisse.cloture = { num: d.number };
      ST.caisse.clotures++;
      fermerModales();
      showToast(d.message);
    } catch (e) {
      if (e.name !== 'AbortError') showToast(e.message, 'danger');
      majClo();
    }
  }, { signal });

  $('bkX').addEventListener('click', () => { ST.sel.clear(); rendre(); }, { signal });
  $('bkPrint').addEventListener('click', () => {
    const s = selDocs();
    if (!s.n) { showToast('Rien à imprimer : les brouillons ne sont pas encore des factures'); return; }
    window.open(selUrl(P.urls.selection, s), '_blank', 'noopener');
    showToast(`${s.n} document${s.n > 1 ? 's' : ''} regroupé${s.n > 1 ? 's' : ''} en un PDF pour l'impression`);
  }, { signal });
  $('bkExp').addEventListener('click', () => {
    const s = selDocs();
    if (!s.n) { showToast('Rien à exporter : les brouillons ne sont pas encore des factures'); return; }
    telecharger(selUrl(P.urls.export, s), `factures-${TODAY}.csv`);
    showToast(`Export CSV de ${s.n} document${s.n > 1 ? 's' : ''}`);
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
  if (P.autoPay && ST.fiche) ouvrirPay(ST.fiche);
  if (P.autoPrint && ST.fiche) ouvrirApercu(ST.fiche);

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
  window.clearTimeout(articleTimer);
  window.clearTimeout(clientTimer);
  searchTimer = null;
  articleTimer = null;
}
