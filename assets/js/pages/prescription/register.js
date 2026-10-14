/**
 * Page module — Ordonnances (ordonnances-v-topbar_3 mock-up). Loaded by the
 * app.js dispatcher on turbo:load, on the register (clinic_prescriptions) and
 * on one ordonnance (clinic_prescription_details: the register with the sheet
 * already open).
 *
 * The mock-up renders the register from its demo data; this module renders it
 * the same way from the `data-payload` of PrescriptionPageBuilder, and asks
 * clinic_prescriptions_search for every other page or filter (period segment,
 * status / prescriber / medicines pills, search, pager, page size remembered
 * in this browser). A row opens its sheet (clinic_prescription_sheet) beside
 * the table; the address follows, so a reload comes back to it.
 *
 * Signing a draft, renewing and cancelling post #ordoForm to their
 * single-action controllers, which come back to the ordonnance with a toast.
 * Printing and downloading open the PDFs the server renders. What the clinic
 * cannot do yet (e-mail, antibiotic declaration) is drawn but disabled.
 *
 * Listeners are bound in init() with one AbortSignal and released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';

const SEARCH_DEBOUNCE_MS = 250;
const PP_KEY = 'kiveto-ordo-pp';
const PP_SIZES = [25, 50, 100];
const PER_DEFAULT = '30';

let ac = null;
let searchTimer = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const norm = t => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const fmtD = s => (s ? s.split('-').reverse().join('/') : '—');
const dCourt = s => `${+s.slice(8)} ${MOIS[+s.slice(5, 7) - 1]}`;

const ICO = {
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  steth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2v2M5 2v2M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  ok: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  crit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
};

/* Status = the document's life cycle; expiry is read from the validity. */
const STATUTS = { brou: ['s-brou', 'Brouillon'], emise: ['s-deliv', 'Émise'], exp: ['s-exp', 'Expirée'], ann: ['s-ann', 'Annulée'] };
const MOTIFS = ['Erreur de saisie', 'Traitement modifié', 'Demande du client', 'Autre'];
/* « Médicaments » of the mock-up the catalogue cannot tell apart yet. */
const CLASSES_A_VENIR = ['Antibiotiques', "Antibiotiques d'importance critique", 'Hors AMM (cascade)', 'Animaux de rente', 'Aliments diététiques'];

export function init() {
  const root = document.querySelector('.ordo[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const LH = P.letterhead || { name: 'Clinique vétérinaire', lines: [] };
  const TODAY = P.today;
  const TEAM = Object.fromEntries((P.team || []).map(v => [v.id, v]));
  const czone = $('czone');

  const ST = {
    f: { ...P.filters },
    rows: P.register.rows,
    total: P.register.total,
    page: P.register.page,
    pp: P.register.pp,
    drafts: P.register.drafts,
    sel: new Set(),
    known: new Map(),
    fiche: P.sheet || null,
    seq: 0,
  };
  const remember = () => ST.rows.forEach(o => ST.known.set(o.id, o));
  remember();

  /* ═════════ Address ═════════ */

  function query(f = ST.f) {
    const q = new URLSearchParams();
    if (f.q) q.set('q', f.q);
    if (f.per !== PER_DEFAULT) q.set('per', f.per);
    if (f.statut) q.set('statut', f.statut);
    if (f.vet) q.set('vet', f.vet);
    if (f.classe) q.set('classe', f.classe);
    if (f.page > 1) q.set('page', String(f.page));
    if (f.pp !== 25) q.set('pp', String(f.pp));
    return q;
  }
  function majAdresse() {
    const base = ST.fiche ? P.urls.details.replace('00000000-0000-7000-8000-000000000000', ST.fiche.id) : P.urls.list;
    const q = query();
    // The details page opens on « Tout »: keep the period explicit there.
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
      Object.assign(ST, { rows: d.rows, total: d.total, page: d.page, pp: d.pp, drafts: d.drafts });
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
      { k: 'statut', l: 'Statut', o: Object.entries(STATUTS).map(([v, [, l]]) => ({ v, l })) },
      { k: 'vet', l: 'Prescripteur', o: (P.team || []).map(x => ({ v: x.id, l: x.n, c: x.c })) },
      { k: 'classe', l: 'Médicaments', o: [{ v: 'ctrl', l: 'Substances contrôlées et stupéfiants' }], soon: CLASSES_A_VENIR },
    ].forEach(fd => {
      const el = document.createElement('div');
      el.className = 'dd fpill' + (ST.f[fd.k] ? ' actif' : '');
      box.appendChild(el);
      creerDropdown(el, [{ v: '', l: 'Tous' }, ...fd.o], ST.f[fd.k], v => filtrer({ [fd.k]: v }));
      if (!ST.f[fd.k]) el.querySelector('.dd-btn > span').textContent = fd.l;
      if (fd.soon) {
        el.querySelector('.dd-menu').insertAdjacentHTML('beforeend', '<div class="dd-sep"></div><div class="dd-titre">Bientôt disponible</div>'
          + fd.soon.map(l => `<button type="button" class="dd-it" disabled data-tip="Le catalogue ne distingue pas encore cette catégorie">${esc(l)}</button>`).join(''));
      }
    });
  }

  /* ═════════ Table ═════════ */

  const flagsHtml = o => (o.flags || []).map(f => `<span class="puce ${esc(f.c)}">${esc(f.l)}</span>`).join('');
  const COLS = [
    { l: 'Date', html: o => `<div class="mdate"><b>${o.d === TODAY ? "Aujourd'hui" : dCourt(o.d)} · ${o.h}</b><span class="num-o">${esc(o.num || 'Non numérotée')}</span></div>` },
    { l: 'Animal', html: o => `<div class="pat"><span class="e">${o.a.e}</span><div style="min-width:0"><b>${esc(o.a.n)}</b><span>${esc(o.a.prop)}</span></div></div>` },
    { l: 'Prescription', html: o => `<div class="meds">${o.meds.slice(0, o.meds.length > 3 ? 2 : 3).map(esc).join(' · ') || '<span style="color:var(--encre-35)">Aucun médicament</span>'}${o.meds.length > 3 ? ` <span class="plus">+${o.meds.length - 2}</span>` : ''}</div>${flagsHtml(o) ? `<div class="flags">${flagsHtml(o)}</div>` : ''}` },
    { l: 'Prescripteur', xo: 1, html: o => `<span class="prs"><span class="av" style="background:${esc(o.vet.c)}">${esc(o.vet.ini)}</span>${esc(o.vet.n)}</span>` },
    { l: 'Statut', html: o => { const [c, l] = STATUTS[o.st]; return `<span class="puce ${c}">${l}</span>`; } },
    { l: 'Validité', html: o => (['brou', 'ann'].includes(o.st) || !o.valid ? '<span class="val" style="color:var(--encre-35)">—</span>'
      : `<div class="val">${o.st === 'exp' ? 'Expirée le ' + fmtD(o.valid) : "Jusqu'au " + fmtD(o.valid)}</div>`) },
  ];

  function rendrePager() {
    const tot = ST.total, pg = ST.page, n = Math.max(1, Math.ceil(tot / ST.pp));
    const a = tot ? (pg - 1) * ST.pp + 1 : 0, b = Math.min(tot, pg * ST.pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> ordonnance${tot > 1 ? 's' : ''}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${PP_SIZES.map(v => `<button type="button" data-pp="${v}" class="${v === ST.pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }

  function rendre() {
    const b = ST.drafts;
    $('phSub').textContent = `Registre continu ${TODAY.slice(0, 4)}${b ? ` · ${b} brouillon${b > 1 ? 's' : ''} en consultation` : ''}`;
    const Lp = ST.rows;
    const allSel = Lp.length && Lp.every(o => ST.sel.has(o.id)), some = Lp.some(o => ST.sel.has(o.id));
    $('thead').innerHTML = `<tr><th class="selc"><input type="checkbox" class="ck${some && !allSel ? ' mix' : ''}" id="ckAll" ${allSel ? 'checked' : ''} aria-label="Tout sélectionner"></th>${COLS.map(c => `<th class="${c.xo ? 'xo' : ''}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = Lp.map(o => `<tr data-id="${esc(o.id)}" class="${ST.sel.has(o.id) ? 'sel' : ''}${ST.fiche?.id === o.id ? ' cur' : ''}${o.st === 'ann' ? ' ann' : ''}"><td class="selc"><input type="checkbox" class="ck" ${ST.sel.has(o.id) ? 'checked' : ''} aria-label="Sélectionner"></td>${COLS.map(c => `<td class="${c.xo ? 'xo' : ''}">${c.html(o)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = Lp.length ? '' : `<div class="cempty"><b>Aucune ordonnance</b>${ST.f.q || ST.f.statut || ST.f.vet || ST.f.classe || ST.f.per !== 'tout' ? 'Modifiez la période, la recherche ou les filtres.' : 'Les ordonnances se rédigent depuis le plan de traitement d\'une consultation.'}</div>`;
    rendrePager();
    majBulk();
  }

  function majBulk() {
    const n = ST.sel.size;
    $('bulk').classList.toggle('on', n > 0);
    $('bulkN').textContent = n;
    $('bulkL').textContent = n > 1 ? 'sélectionnées' : 'sélectionnée';
  }

  /* Only a signed, uncancelled ordonnance is a document to hand out. */
  const selOrd = () => [...ST.sel].filter(id => ['emise', 'exp'].includes(ST.known.get(id)?.st));
  const selUrl = ids => `${P.urls.selection}?${ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&')}`;
  const telecharger = (url, name) => { const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); };

  /* ═════════ Sheet ═════════ */

  async function ouvrirFiche(id) {
    const seq = ++ST.seq;
    try {
      const r = await fetch(P.urls.sheet.replace('__ID__', encodeURIComponent(id)), { headers: { Accept: 'application/json' }, signal });
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
      if (e.name !== 'AbortError') showToast('L\'ordonnance n\'a pas pu être ouverte', 'danger');
    }
  }
  function fermerFiche() {
    if (!ST.fiche) return;
    ST.fiche = null;
    czone.classList.remove('ouvert');
    rendre();
    majAdresse();
  }

  function paperHtml(o) {
    const e = o.st, v = o.vet, a = o.a;
    const entete = `<div><b>${esc(LH.name)}</b><p>${(LH.lines || []).map(esc).join('<br>')}${LH.contact ? '<br>' + esc(LH.contact) : ''}</p></div>`;
    return `${e === 'brou' ? '<div class="wm">BROUILLON</div>' : e === 'ann' ? '<div class="wm">ANNULÉE</div>' : ''}
      <div class="pp-head">${entete}<div class="r"><div class="pp-doc">Ordonnance</div><p>${esc(o.num || 'Non numérotée')}<br>${esc(v.n)}${v.ord ? ' · n° ordinal ' + esc(v.ord) : ''}<br>${LH.city ? esc(LH.city) + ', le ' : 'Le '}${fmtD(o.d)}</p></div></div>
      <div class="pp-meta"><div><p class="k">Animal</p><p><b>${esc(a.n)}</b>${a.d ? '<br>' + esc(a.d) : ''}${a.puce ? '<br>Puce ' + esc(a.puce) : ''}</p></div><div><p class="k">Détenteur</p><p><b>${esc(a.prop)}</b>${(a.adr || []).length ? '<br>' + a.adr.map(esc).join('<br>') : ''}</p></div></div>
      ${o.lignes.map(l => `<div class="pp-rx"><b>${esc(l.l)}</b>
        <p>${esc(l.poso || 'Posologie non précisée')}.</p>${l.instr ? `<p><i>${esc(l.instr)}</i></p>` : ''}<p class="q">Qté : ${esc(l.q)}${o.renouv.allowed ? ` · à renouveler ${o.renouv.allowed} fois` : ' · non renouvelable'}</p></div>`).join('') || '<p class="q" style="padding:10px 0">Aucun médicament.</p>'}
      ${o.note ? `<p style="margin-top:12px"><b>${esc(o.note).replace(/\n/g, '<br>')}</b></p>` : ''}
      <div class="pp-foot"><span>${o.valid ? `Valable jusqu'au ${fmtD(o.valid)}.<br>` : ''}Ne pas dépasser la posologie prescrite. Tenir hors de portée des enfants.</span><div class="pp-sign">${e === 'brou' ? 'signature à l\'émission' : esc(v.n)}</div></div>`;
  }

  function rendreFiche() {
    const o = ST.fiche;
    if (!o) return;
    const e = o.st, [ec, el] = STATUTS[e], v = o.vet, j = o.jours, A = o.actions;
    const signee = e === 'emise' || e === 'exp';
    $('fiHead').innerHTML = `<div class="fi-ico" style="font-size:1.1rem">${o.a.e}</div><div class="fi-t"><b>${esc(o.a.n)} · ${esc(o.a.prop)}</b><span><span class="code">${esc(o.num || 'Non numérotée')}</span> · <span class="puce ${ec}">${el}</span>${(o.flags || []).map(f => `<span class="puce ${esc(f.c)}">${esc(f.l)}</span>`).join('')}</span></div><button type="button" class="fi-x" id="fiX" data-tip="Fermer · Échap" aria-label="Fermer">${ICO.x}</button>`;

    const sign = A.issue;
    $('fiBody').innerHTML = `
      ${e === 'ann' ? `<div class="bandeau crit">${ICO.crit}<div><b>Annulée le ${fmtD(o.annul.d)}</b>${esc(o.annul.motif)} · conservée au registre avec son numéro.</div></div>` : ''}
      ${e === 'brou' ? `<div class="bandeau info">${ICO.info}<div><b>En cours de rédaction</b>Elle prendra son numéro définitif et sa validité à la signature.</div></div>` : ''}
      <section class="fs"><div class="oinfo">
        <div><small>Prescripteur</small><b>${esc(v.n)}</b><span>${v.ord ? 'n° ordinal ' + esc(v.ord) : 'sans n° ordinal'}</span></div>
        <div><small>${e === 'brou' ? 'Rédigée le' : 'Émise le'}</small><b>${fmtD(o.d)}</b><span>à ${o.h}</span></div>
        <div><small>Validité</small><b style="${e === 'exp' ? 'color:var(--encre-35)' : j !== null && j <= 14 ? 'color:#b45309' : ''}">${!signee ? '—' : e === 'exp' ? 'Expirée' : fmtD(o.valid)}</b><span>${!signee ? '&nbsp;' : e === 'exp' ? 'le ' + fmtD(o.valid) : `encore ${j} jour${j > 1 ? 's' : ''}${o.renouv.allowed ? ` · ${o.renouv.remaining}/${o.renouv.allowed} renouv.` : ''}`}</span></div>
      </div></section>
      <a class="ocons" href="${esc(o.cons.url)}"><span class="e">${ICO.steth}</span><span class="lt"><b>${esc(o.cons.label)}</b><span>Consultation du ${fmtD(o.cons.d)} · ${esc(v.n)}</span></span><span class="lien">Ouvrir ${ICO.ext}</span></a>
      <section class="fs"><h3>Prescription<span class="tag hint" style="margin:0 0 0 auto;font-weight:400;letter-spacing:0;text-transform:none">${o.lignes.length} ligne${o.lignes.length > 1 ? 's' : ''}</span></h3>
        ${o.lignes.map(l => {
          const flags = `<span class="puce ${esc(l.cl)}">${esc(l.clLabel)}</span>${l.ctrl ? '<span class="puce aic">28 jours max · non renouvelable</span>' : ''}${l.form ? `<span class="puce">${esc(l.form)}</span>` : ''}`;
          const ouTag = l.clinique ? '<span class="rx-ou"><i style="background:var(--pin)"></i>Délivré par la clinique</span>' : '<span class="rx-ou"><i style="background:#0891b2"></i>Ordonnance seule · le client se le procure en pharmacie, sans suivi ici</span>';
          const dl = l.clinique && signee ? `<div class="rx-d ok">${ICO.ok}<span><b>Délivré</b> · qté ${esc(l.q)} lors de la consultation du ${fmtD(o.cons.d)}</span></div>` : '';
          return `<div class="rx"><div class="rx-h"><div class="lt"><b>${esc(l.l)}</b><p>${esc(l.poso || 'Posologie non précisée')}${l.instr ? `<br><i>${esc(l.instr)}</i>` : ''}${(l.subst || []).length ? `<br>${l.subst.map(esc).join(', ')}` : ''}<br>qté ${esc(l.q)}${o.renouv.allowed ? ` · à renouveler ${o.renouv.allowed} fois` : ''}</p></div></div>
            <div class="flags">${flags}</div>${e !== 'ann' ? ouTag : ''}${dl}</div>`;
        }).join('') || '<p class="hint" style="margin:0">Aucun médicament n\'a été relevé sur cette consultation : ajoutez-les au plan de traitement, puis rouvrez l\'ordonnance depuis la consultation.</p>'}
      </section>
      ${sign ? `<section class="fs"><h3>Signature</h3>
        <div class="frow"><label class="flbl">Renouvellements autorisés</label><div class="dd" id="ddRenouv"></div>
          <p class="hint">${sign.controlled ? `Une substance contrôlée ou un stupéfiant : l'ordonnance vaut ${sign.validityDays} jours et n'est pas renouvelable.` : `L'ordonnance vaut ${sign.validityDays} jours à compter de la signature ; chaque renouvellement repousse la validité d'autant.`}</p></div>
        <div class="frow"><label class="flbl">Recommandations <span class="opt">(facultatif)</span></label><textarea class="ftext" id="fiNotes" rows="3" maxlength="2000" placeholder="Conseils au propriétaire, précautions d'administration…">${esc(o.note || '')}</textarea></div>
        ${!sign.canSign ? `<div class="bandeau crit" style="margin-top:10px">${ICO.crit}<div><b>Signature impossible</b>${esc(v.n)} n'a pas de numéro ordinal : renseignez-le dans l'équipe de la clinique.</div></div>` : ''}
      </section>` : ''}
      <section class="fs"><h3>Document</h3><button type="button" class="thumb" id="fiThumb" aria-label="Voir l'ordonnance"><div class="paper" id="fiPaper">${paperHtml(o)}</div></button></section>
      <section class="fs"><h3>Historique</h3><div class="hist2">${[...o.hist].reverse().map(h => `<div><time>${fmtD(h.d)}</time><span>${esc(h.t)}<small>${esc(h.s)}</small></span></div>`).join('')}</div></section>`;

    if (sign) {
      const opts = Array.from({ length: sign.maxRenewals + 1 }, (_, n) => ({ v: String(n), l: n ? `${n} fois` : 'Aucun' }));
      ST.renouv = creerDropdown($('ddRenouv'), opts, String(Math.min(o.renouv.allowed, sign.maxRenewals)), null);
    }

    // The thumbnail: the paper laid out at 620 px, scaled to the sheet's width.
    const th = $('fiThumb'), pp = $('fiPaper'), w = th.clientWidth - 28;
    pp.style.width = '620px';
    const k = w / 620;
    pp.style.transform = `scale(${k})`;
    th.style.height = (pp.offsetHeight * k + 28) + 'px';

    const envoi = '<button type="button" class="btn" disabled data-tip="L\'envoi par e-mail et SMS n\'est pas encore disponible">Envoyer au client</button>';
    $('fiFoot').innerHTML = e === 'brou'
      ? `<span class="sp"></span><a class="btn" href="${esc(A.consultation)}">Reprendre dans la consultation</a><button type="button" class="btn primaire" id="fiSign"${sign && sign.canSign && sign.hasLines ? '' : ' disabled'}${sign && !sign.hasLines ? ' data-tip="Aucun médicament à signer"' : ''}>Signer et émettre</button>`
      : e === 'ann'
        ? '<span class="sp"></span><button type="button" class="btn" id="fiPrint">Voir le document</button>'
        : `${A.cancel ? '<button type="button" class="btn ghost-danger" id="fiAnn">Annuler</button>' : ''}<span class="sp"></span>${A.renew ? `<button type="button" class="btn" id="fiRenew" data-tip="Repousse la validité de ${A.renew.validityDays} jours">Renouveler · ${o.renouv.remaining}</button>` : ''}${envoi}<button type="button" class="btn primaire" id="fiPrint">Imprimer</button>`;
  }

  /* ═════════ Acts posted to their controllers ═════════ */

  function poster(act, fields = {}) {
    const f = $('ordoForm');
    f.action = act.url;
    $('ordoTok').value = act.token;
    $('ordoReason').value = fields.reason ?? '';
    $('ordoRenewals').value = fields.renewals ?? '0';
    $('ordoNotes').value = fields.notes ?? '';
    f.requestSubmit();
  }

  /* ═════════ Windows ═════════ */

  const ouvrirModale = id => $(id).classList.add('open');
  const fermerModales = () => root.querySelectorAll('.mveil.open').forEach(m => m.classList.remove('open'));

  function ouvrirApercu(o) {
    $('pvT').textContent = `Ordonnance ${o.num || '(brouillon)'}`;
    $('pvS').textContent = `${o.a.n} · ${STATUTS[o.st][1]}`;
    $('pvPaper').innerHTML = paperHtml(o);
    $('pvPrint').style.display = o.st === 'ann' ? 'none' : '';
    ouvrirModale('mPrev');
  }

  let anM = null;
  function ouvrirAnnul(o) {
    anM = null;
    $('anS').textContent = `${o.num || 'Brouillon'} · ${o.a.n}`;
    $('anMotifs').innerHTML = MOTIFS.map(m => `<button type="button" class="chip" data-m="${m}">${m}</button>`).join('');
    $('anAutre').style.display = 'none';
    $('anAutre').value = '';
    $('anOk').disabled = true;
    ouvrirModale('mAnn');
  }
  const majAn = () => { $('anOk').disabled = !anM || (anM === 'Autre' && !$('anAutre').value.trim()); };

  function rendreNw() {
    const q = norm($('nwQ').value);
    $('nwList').innerHTML = (P.consultations || []).filter(c => !q || norm(c.a + ' ' + c.prop).includes(q)).map(c => `<a class="citem" href="${esc(c.url)}">
      <span class="h">${c.h}</span><span class="e">${c.e}</span><span class="lt"><b>${esc(c.a)} · ${esc(c.m)}</b><span>${esc(c.prop || 'Propriétaire inconnu')} · ${esc(c.v)} · ${c.st}</span></span><span class="go">${c.st === 'Clôturée' ? 'Voir la consultation' : 'Ouvrir le plan'} →</span></a>`).join('')
      || `<p class="hint" style="text-align:center;margin:16px 0">${(P.consultations || []).length ? 'Aucune consultation aujourd\'hui pour cette recherche.' : 'Aucune consultation aujourd\'hui : l\'ordonnance se rédige dans le plan d\'une consultation.'}</p>`;
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
    ouvrirFiche(tr.dataset.id);
  }, { signal });

  $('bkX').addEventListener('click', () => { ST.sel.clear(); rendre(); }, { signal });
  $('bkPrint').addEventListener('click', () => {
    const L = selOrd();
    if (!L.length) { showToast('Rien à imprimer : brouillons et annulées sont exclus'); return; }
    window.open(selUrl(L), '_blank', 'noopener');
    showToast(`${L.length} ordonnance${L.length > 1 ? 's' : ''} regroupée${L.length > 1 ? 's' : ''} en un seul PDF pour l'impression${ST.sel.size > L.length ? ' · brouillons et annulées exclus' : ''}`);
  }, { signal });
  $('bkPdf').addEventListener('click', () => {
    const L = selOrd();
    if (!L.length) { showToast('Rien à télécharger : brouillons et annulées sont exclus'); return; }
    telecharger(selUrl(L), `ordonnances-${L.length}.pdf`);
    showToast(`${L.length} ordonnance${L.length > 1 ? 's' : ''} téléchargée${L.length > 1 ? 's' : ''} en un seul PDF`);
  }, { signal });

  $('q').addEventListener('input', e => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => filtrer({ q: e.target.value.trim() }), SEARCH_DEBOUNCE_MS);
  }, { signal });

  const hmenu = $('hmenu');
  $('hmenuBtn').addEventListener('click', e => { e.stopPropagation(); hmenu.classList.toggle('open'); }, { signal });
  document.addEventListener('click', e => { if (!e.target.closest('#hmenu')) hmenu.classList.remove('open'); }, { signal });
  hmenu.querySelectorAll('.dd-it:not([disabled])').forEach(b => b.addEventListener('click', () => {
    hmenu.classList.remove('open');
    showToast('Registre des ordonnances exporté — numérotation continue, annulations comprises');
  }, { signal }));

  $('fiBody').addEventListener('click', e => {
    if (e.target.closest('#fiThumb') && ST.fiche) ouvrirApercu(ST.fiche);
  }, { signal });
  $('fiHead').addEventListener('click', e => { if (e.target.closest('#fiX')) fermerFiche(); }, { signal });
  $('fiFoot').addEventListener('click', e => {
    const o = ST.fiche;
    if (!o) return;
    const A = o.actions;
    if (e.target.closest('#fiPrint')) ouvrirApercu(o);
    if (e.target.closest('#fiAnn')) ouvrirAnnul(o);
    if (e.target.closest('#fiRenew') && A.renew) poster(A.renew);
    const s = e.target.closest('#fiSign');
    if (s && !s.disabled && A.issue) poster(A.issue, { renewals: ST.renouv ? ST.renouv.get() : '0', notes: $('fiNotes')?.value.trim() ?? '' });
  }, { signal });

  $('pvPrint').addEventListener('click', () => {
    if (!ST.fiche) return;
    fermerModales();
    window.open(ST.fiche.pdf, '_blank', 'noopener');
    showToast('Ordonnance envoyée à l\'impression');
  }, { signal });
  $('pvPdf').addEventListener('click', () => {
    if (!ST.fiche) return;
    telecharger(ST.fiche.pdf, `${ST.fiche.num || 'ordonnance'}.pdf`);
    showToast('PDF téléchargé');
  }, { signal });

  $('anMotifs').addEventListener('click', e => {
    const b = e.target.closest('[data-m]');
    if (!b) return;
    anM = b.dataset.m;
    root.querySelectorAll('#anMotifs .chip').forEach(x => x.classList.toggle('on', x === b));
    const au = $('anAutre');
    au.style.display = anM === 'Autre' ? '' : 'none';
    if (anM === 'Autre') au.focus();
    majAn();
  }, { signal });
  $('anAutre').addEventListener('input', majAn, { signal });
  $('anOk').addEventListener('click', () => {
    const o = ST.fiche;
    if (!o || !o.actions.cancel || $('anOk').disabled) return;
    poster(o.actions.cancel, { reason: anM === 'Autre' ? $('anAutre').value.trim() : anM });
  }, { signal });

  $('btnNew').addEventListener('click', () => {
    $('nwQ').value = '';
    rendreNw();
    ouvrirModale('mNew');
    setTimeout(() => $('nwQ').focus(), 50);
  }, { signal });
  $('nwQ').addEventListener('input', rendreNw, { signal });

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
  searchTimer = null;
}
