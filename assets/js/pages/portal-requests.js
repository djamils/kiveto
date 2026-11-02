/**
 * Page module — « Demandes » (demandes-v-topbar mock-up), on the register
 * pattern of « Consultations ». Loaded by the app.js dispatcher on
 * turbo:load, on the list and on the three request URLs.
 *
 * The register is drawn from the `data-payload` of PortalRequestPageBuilder;
 * every other page or filter (status segment, type menu, head counters,
 * search, pager, page size remembered in this browser) is asked from
 * clinic_portal_requests_search, without reloading the page. A row opens
 * its request's sheet (its `sheet` JSON) beside the table; the address
 * follows — the request's own URL while its sheet is open — so a reload
 * comes back to it, and back / forward replay the sheets opened (the
 * module's history handler, kiveto/history, claims only its own entries).
 *
 * The answers (« Marquer comme traitée » / « Refuser » with a message of at
 * most 280 characters and, for a renewal, the prescription written for it;
 * « Confirmer » / « Refuser » a « Rattachement » for the files shown, the
 * refusal confirmed in the VET-OS dialog #mRefLink;
 * « Valider et confirmer le RDV » with the client file and animal chosen, or
 * « Refuser », of a « Nouveau client ») are posted in the background with
 * their CSRF token; the row, the counters and the sheet follow, with a
 * toast, and « Demande suivante » opens the next one to answer. An answered
 * « Rattachement » or « Nouveau client » says who decided and when.
 *
 * Owner free text (notes, names, proposed values) is only ever written
 * through esc(). Listeners are bound in init() with one AbortSignal and
 * released in cleanup().
 */
import { showToast, creerDropdown } from 'kiveto/shell';
import { registerHistoryHandler } from 'kiveto/history';

const SEARCH_DEBOUNCE_MS = 250;
const PP_KEY = 'kiveto-dem-pp';
const PP_SIZES = [25, 50, 100];
const STATUT_DEFAULT = 'submitted';
const MSG_MAX = 280;

let ac = null;
let searchTimer = null;
let releaseHistory = null;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const I = {
  modif: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
  renouv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M15 14a3 3 0 1 1-1-2.2"/><path d="M15 11v2h-2"/></svg>',
  ratt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>',
  nouveau: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>',
  x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  ext: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>',
  ne: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M5 9h14M5 15h14"/><path d="m16 5-8 14"/></svg>',
  warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3"/><path d="M12 9v4M12 17h.01"/></svg>',
  tel: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
  cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>',
  phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg>',
  next: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
  back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>',
};
const TAG = 'class="tag hint" style="margin:0 0 0 auto;font-weight:400;letter-spacing:0;text-transform:none"';

const SEGMENTS = [['submitted', 'À traiter'], ['done', 'Traitées'], ['rejected', 'Refusées'], ['tout', 'Toutes']];
const TYPES = [
  { v: 'pet_change', l: 'Modification de fiche' },
  { v: 'prescription_renewal', l: 'Renouvellement' },
  { v: 'client_link', l: 'Rattachement de compte' },
  { v: 'booking_request', l: 'Nouveau client' },
];
/* Refusal motifs of the mock-up: the chip fills the message (%a: the pet). */
const MOTIFS = {
  change: [
    ['Valeur à vérifier en clinique', 'Merci pour votre message. Nous vérifierons cette information lors de votre prochaine visite.'],
    ['Information déjà à jour', 'Merci ! Cette information est déjà à jour dans le dossier.'],
    ['Pièce justificative nécessaire', 'Pour modifier cette information, merci d’apporter le document correspondant lors de votre prochaine visite.'],
  ],
  renewal: [
    ['Consultation de contrôle nécessaire', '%a doit être revu avant tout renouvellement. Prenez rendez-vous depuis l’app.'],
    ['Traitement terminé', 'Le traitement de %a est terminé : il n’y a pas lieu de le renouveler.'],
    ['Ordonnance d’une autre clinique', 'Cette ordonnance n’a pas été établie chez nous : merci de vous adresser au vétérinaire prescripteur.'],
  ],
};

export function init() {
  const root = document.querySelector('.dem[data-payload]');
  if (!root) return;

  ac?.abort();
  ac = new AbortController();
  const { signal } = ac;

  const P = JSON.parse(root.dataset.payload);
  const $ = id => document.getElementById(id);
  const czone = $('czone');
  const mRef = $('mRefLink'); // the refusal dialog of a « Rattachement »
  let refusLien = null;       // {o, from}: the sheet it answers, the button to give the focus back to

  const ST = {
    f: { ...P.filters },
    reg: P.register,
    fiche: null,
    mode: null,      // 'refus' while a refusal is being written
    motif: null,     // the refusal chip chosen
    msg: '',         // the message to the owner
    rx: '',          // the prescription written for a renewal
    book: null,      // « Nouveau client »: {client, animal} chosen
    resolved: null,  // a « Rattachement » answered from this sheet: {ok}
    busy: false,
    seq: 0,
    fseq: 0,
  };
  const VIERGE = () => ST.reg.head.blank;

  /* ═════════ Address ═════════ */

  function query(f = ST.f, withStatut = false) {
    const q = new URLSearchParams();
    if (withStatut || f.statut !== STATUT_DEFAULT) q.set('statut', f.statut);
    if (f.type) q.set('type', f.type);
    if (f.q) q.set('q', f.q);
    if (f.page > 1) q.set('page', String(f.page));
    if (f.pp !== 25) q.set('pp', String(f.pp));
    return q;
  }
  // A sheet's URL keeps the list's status explicit: without it, a request
  // opens over the list of its own state.
  function adresse() {
    const q = query(ST.f, !!ST.fiche);
    return (ST.fiche ? ST.fiche.url : P.urls.list) + (q.toString() ? '?' + q : '');
  }
  function majAdresse(push = false) {
    const url = adresse();
    if (url === location.pathname + location.search) return;
    try {
      if (push) history.pushState(ENTRY, '', url);
      else history.replaceState(ENTRY, '', url);
    } catch (_) { /* sandboxed */ }
  }
  // This page's history entries keep the Turbo state of the visit, so another
  // page's back / forward reaching one is a plain Turbo restore; while this
  // page is on screen, its handler replays them in place instead.
  const ENTRY = { ...(history.state?.turbo ? { turbo: history.state.turbo } : {}), kivetoPage: 'clinic_portal_requests' };
  const notreAdresse = path => path === P.urls.list || path.startsWith(P.urls.list + '/');

  /* Back / forward between the list and the sheets: replayed here, not reloaded. */
  function appliquerAdresse() {
    const p = new URLSearchParams(location.search);
    const statut = p.has('statut') ? (p.get('statut') || 'tout') : STATUT_DEFAULT;
    const pp = +p.get('pp');
    const f = {
      statut: SEGMENTS.some(([v]) => v === statut) ? statut : STATUT_DEFAULT,
      type: TYPES.some(t => t.v === p.get('type')) ? p.get('type') : '',
      q: (p.get('q') || '').trim(),
      page: Math.max(1, +p.get('page') || 1),
      pp: PP_SIZES.includes(pp) ? pp : 25,
    };
    if (['statut', 'type', 'q', 'page', 'pp'].some(k => f[k] !== ST.f[k])) {
      ST.f = f;
      $('q').value = f.q;
      rendreFiltres();
      charger({ adresse: false });
    }
    const page = location.pathname.replace(/\/$/, '');
    if (page === P.urls.list) fermerFiche({ adresse: false });
    else if (ST.fiche?.url !== page) ouvrirFiche({ url: page, sheet: page + '/sheet' }, { adresse: false });
  }

  /* ═════════ Loading a page of the register ═════════ */

  async function charger({ adresse = true } = {}) {
    const seq = ++ST.seq;
    const q = query(ST.f, true);
    q.set('page', String(ST.f.page));
    q.set('pp', String(ST.f.pp));
    try {
      const r = await fetch(`${P.urls.search}?${q}`, { headers: { Accept: 'application/json' }, signal });
      if (!r.ok) throw new Error(String(r.status));
      const d = await r.json();
      if (seq !== ST.seq) return;
      ST.reg = d;
      ST.f.page = d.page;
      rendre();
      if (ST.fiche) $('fiFoot').innerHTML = pied(ST.fiche);
      if (adresse) majAdresse();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('La liste des demandes n’a pas pu être chargée', 'danger');
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
    seg.setAttribute('role', 'tablist');
    seg.setAttribute('aria-label', 'Statut');
    const n = ST.reg.counts.submitted;
    seg.innerHTML = SEGMENTS.map(([v, l]) => `<button type="button" role="tab" data-v="${v}" class="${ST.f.statut === v ? 'on' : ''}" aria-selected="${ST.f.statut === v}">${l}${v === 'submitted' && n ? `<span class="segn">${n}</span>` : ''}</button>`).join('');
    seg.addEventListener('click', e => { const b = e.target.closest('button'); if (b && b.dataset.v !== ST.f.statut) filtrer({ statut: b.dataset.v }); });
    box.appendChild(seg);
    const el = document.createElement('div');
    el.className = 'dd fpill' + (ST.f.type ? ' actif' : '');
    el.id = 'ddType';
    box.appendChild(el);
    creerDropdown(el, [{ v: '', l: 'Tous les types' }, ...TYPES.map(t => ({ v: t.v, l: t.l, sub: String(ST.reg.kinds[t.v] || '') }))], ST.f.type, v => filtrer({ type: v }));
    if (!ST.f.type) el.querySelector('.dd-btn > span').textContent = 'Type';
  }

  /* ═════════ Table ═════════ */

  function drapeaux(r) {
    const f = [];
    if (r.urgent) f.push('<span class="puce urgx">Urgent</span>');
    if (r.condition) f.push(`<span class="puce ${esc(r.condition.cls)}">${esc(r.condition.label)}</span><span class="puce vto">Vétérinaire</span>`);
    (r.flags || []).forEach(x => f.push(`<span class="puce et-am">${esc(x)}</span>`));
    if (r.unreachable) f.push('<span class="puce et-am">Client injoignable</span>');
    return f.length ? `<div class="flags">${f.join('')}</div>` : '';
  }
  // `m` is the cell's place on a phone (resp-lst): title, wide line, meta, hidden.
  const COLS = [
    { l: 'Reçue', m: 'm-m', html: r => `<div class="mdate"><b>${esc(r.day)}</b>${r.age ? `<span class="${r.late ? 'late' : ''}">${esc(r.age)}</span>` : ''}</div>` },
    { l: 'Animal', m: 'm-t', html: r => `<div class="pat"><span class="e">${esc(r.petEmoji)}</span><div style="min-width:0"><b>${esc(r.petName)}</b><span>${esc(r.clientName)}${r.isNew ? ' · nouveau' : ''}</span></div></div>` },
    { l: 'Demande', m: 'm-w', html: r => `<div class="dq"><span class="tic t-${esc(r.type)}">${I[r.type] || ''}</span><div style="min-width:0"><div class="mot"><b>${esc(r.short)}</b> · ${esc(r.subject)}${r.note ? ` <small>· « ${esc(r.note)} »</small>` : ''}</div>${drapeaux(r)}</div></div>` },
    { l: 'Traitée par', xo: 1, hist: 1, m: 'm-h', html: r => (r.resolvedBy || r.resolvedDay ? `<span class="tpar">${esc(r.resolvedBy || '—')}${r.resolvedDay ? `<small>${esc(r.resolvedDay)}</small>` : ''}</span>` : '<span class="tpar mu">—</span>') },
    { l: 'Statut', hist: 1, m: 'm-m', html: r => `<span class="puce ${esc(r.statusClass)}">${esc(r.statusLabel)}</span>` },
  ];

  function rendrePager() {
    const tot = ST.reg.total, pg = ST.reg.page, pp = ST.reg.pp, n = Math.max(1, Math.ceil(tot / pp));
    const a = tot ? (pg - 1) * pp + 1 : 0, b = Math.min(tot, pg * pp);
    const nums = [];
    for (let i = 1; i <= n; i++) if (i === 1 || i === n || Math.abs(i - pg) <= 1) nums.push(i); else if (nums.at(-1) !== '…') nums.push('…');
    const chev = d => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
    $('cfoot').innerHTML = `<span><b>${a}–${b}</b> sur <b>${tot}</b> demande${tot > 1 ? 's' : ''}</span>
      <span class="pp"><span style="margin-right:3px">Par page :</span>${PP_SIZES.map(v => `<button type="button" data-pp="${v}" class="${v === pp ? 'on' : ''}">${v}</button>`).join('')}</span>
      <span class="pages"><button type="button" class="pg" data-pg="${pg - 1}" ${pg <= 1 ? 'disabled' : ''} aria-label="Page précédente">${chev('m15 18-6-6 6-6')}</button>${nums.map(i => (i === '…' ? '<span class="pg">…</span>' : `<button type="button" class="pg${i === pg ? ' on' : ''}" data-pg="${i}">${i}</button>`)).join('')}<button type="button" class="pg" data-pg="${pg + 1}" ${pg >= n ? 'disabled' : ''} aria-label="Page suivante">${chev('m9 18 6-6-6-6')}</button></span>`;
  }

  /* Header: what asks for an answer, in one click. */
  function rendreTete() {
    const h = ST.reg.head;
    $('phSub').textContent = h.blank ? 'Envoyées par vos clients depuis l’app Kiveto' : `Envoyées depuis l’app Kiveto · ${h.pending} en attente`;
    const K = [['submitted', h.pending, 'à traiter', 'pin'], ['urgent', h.urgent, h.urgent > 1 ? 'urgentes' : 'urgente', 'rouge'], ['late', h.late, 'depuis plus de 24 h', 'am']]
      .filter(x => x[1] || x[0] === 'submitted');
    $('phk').innerHTML = h.blank ? '' : K.map(([k, n, l, c]) => `<button type="button" data-k="${k}"><b class="${c}">${n}</b><span>${l}</span></button>`).join('');
  }

  /* The navigation badge follows the queue. */
  function majBadge() {
    const n = ST.reg.head.pending;
    document.querySelectorAll(`a.sb-it[href="${P.urls.list}"]`).forEach(a => {
      let b = a.querySelector('.badge');
      if (!n) { b?.remove(); return; }
      if (!b) { b = document.createElement('span'); b.className = 'badge'; a.appendChild(b); }
      b.textContent = String(n);
    });
  }

  function rendre() {
    rendreTete();
    rendreFiltres();
    const L = ST.reg.rows, hist = ST.f.statut !== 'submitted', COLV = COLS.filter(c => !c.hist || hist);
    $('thead').innerHTML = `<tr>${COLV.map(c => `<th class="${c.xo ? 'xo' : ''}">${c.l}</th>`).join('')}</tr>`;
    $('tbody').innerHTML = L.map(r => `<tr data-id="${esc(r.id)}" tabindex="0" class="${ST.fiche?.id === r.id ? 'cur' : ''}${r.urgent ? ' prio' : ''}${r.status !== 'submitted' ? ' fait' : ''}">${COLV.map(k => `<td class="${k.m}${k.xo ? ' xo' : ''}">${k.html(r)}</td>`).join('')}</tr>`).join('');
    $('cempty').innerHTML = L.length ? '' : VIERGE()
      ? `<div class="cempty"><span class="ce-ico">${I.phone}</span><b>Aucune demande pour l’instant</b>Vos clients envoient ici, depuis l’app Kiveto, leurs modifications de fiche, renouvellements d’ordonnance et demandes de rattachement. Vous les validez en un clic.<div class="acts"><a class="btn" href="${esc(P.urls.directory)}">Inviter des clients depuis le répertoire</a></div></div>`
      : ST.f.statut === 'submitted' && !ST.f.q && !ST.f.type
        ? `<div class="cempty"><span class="ce-ico ok">${I.ok}</span><b>Tout est traité</b>Les nouvelles demandes arriveront ici, avec un compteur dans la navigation.</div>`
        : '<div class="cempty"><b>Aucune demande</b>Modifiez la recherche ou les filtres.</div>';
    rendrePager();
    $('cfil').style.display = VIERGE() ? 'none' : '';
    root.classList.toggle('vide', VIERGE());
    majBadge();
  }

  /* ═════════ Sheet ═════════ */

  function etatInitial(o) {
    ST.mode = null;
    ST.motif = null;
    ST.msg = '';
    ST.rx = '';
    ST.resolved = null;
    ST.book = null;
    if (o.type === 'booking') {
      const m = o.matches || [];
      const client = o.attachedClientId || (m.length === 1 && !m[0].mismatch ? m[0].clientId : 'new');
      const same = m.find(c => c.clientId === client)?.pets.find(p => p.same);
      ST.book = { client, animal: same ? same.id : 'new' };
    }
  }

  async function ouvrirFiche(ref, { adresse = true } = {}) {
    const seq = ++ST.fseq;
    try {
      const r = await fetch(ref.sheet, { headers: { Accept: 'application/json' }, signal });
      if (r.status === 404) {
        if (seq === ST.fseq) { showToast('Cette demande n’est plus en attente', 'danger'); fermerFiche({ adresse }); }
        return;
      }
      if (!r.ok) throw new Error(String(r.status));
      const o = await r.json();
      if (seq !== ST.fseq) return;
      ST.fiche = o;
      etatInitial(o);
      czone.classList.add('ouvert');
      rendreFiche();
      rendre();
      $('fiBody').scrollTop = 0;
      if (adresse) majAdresse(true);
    } catch (e) {
      if (e.name !== 'AbortError') showToast('La demande n’a pas pu être ouverte', 'danger');
    }
  }
  async function rechargerFiche() {
    const o = ST.fiche;
    if (!o) return;
    const seq = ++ST.fseq;
    try {
      const r = await fetch(o.sheet, { headers: { Accept: 'application/json' }, signal });
      if (seq !== ST.fseq) return;
      if (r.status === 404) { fermerFiche(); return; }
      if (!r.ok) throw new Error(String(r.status));
      const n = await r.json();
      if (seq !== ST.fseq) return;
      ST.fiche = n;
      etatInitial(n);
      rendreFiche();
    } catch (e) {
      if (e.name !== 'AbortError') showToast('La demande n’a pas pu être mise à jour', 'danger');
    }
  }
  function fermerFiche({ adresse = true } = {}) {
    ++ST.fseq;
    fermerRefusLien(false);
    if (!ST.fiche) return;
    ST.fiche = null;
    ST.mode = null;
    czone.classList.remove('ouvert');
    rendre();
    if (adresse) majAdresse(true);
  }

  const fini = o => (o.type === 'link' ? !!ST.resolved || o.decided : o.type === 'booking' ? !o.pending : o.status !== 'submitted');
  // « par Lemoine · 2 nov. 2026 à 10:12 »
  const parQui = o => `${o.resolvedBy ? ` par ${esc(o.resolvedBy)}` : ''}${o.resolvedAt ? ` · ${esc(o.resolvedAt)}` : ''}`;
  const suivante = o => (ST.reg.head.next || []).find(x => x.id !== o.id) || null;
  const bandeau = (cls, ico, titre, texte) => `<div class="bandeau ${cls}">${ico}<div><b>${titre}</b>${texte}</div></div>`;

  function message(o) {
    const refus = ST.mode === 'refus';
    if (!refus) {
      return `<section class="fs"><h3>Message au client<span ${TAG}>facultatif</span></h3>
        <textarea class="finput msg" id="msg" maxlength="${MSG_MAX}" rows="2" placeholder="Ex. L’ordonnance est prête à l’accueil.">${esc(ST.msg)}</textarea>
        <div class="msgf"><span>${I.phone}Envoyé avec la notification de votre décision</span><span id="msgN">0 / ${MSG_MAX}</span></div></section>`;
    }
    const L = MOTIFS[o.isRenewal ? 'renewal' : 'change'];
    return `<section class="fs refus"><h3>Refuser la demande</h3>
      <div class="chips motifs">${L.map(([m], i) => `<button type="button" class="chip${ST.motif === i ? ' on' : ''}" data-motif="${i}">${esc(m)}</button>`).join('')}</div>
      <label class="flbl" for="msg" style="margin-top:12px">Message au client</label>
      <textarea class="finput msg" id="msg" maxlength="${MSG_MAX}" rows="3" placeholder="Choisissez un motif ou écrivez au client">${esc(ST.msg)}</textarea>
      <div class="msgf"><span>${I.phone}Notification dans l’app</span><span id="msgN">0 / ${MSG_MAX}</span></div></section>`;
  }

  /* A change or a renewal. */
  function corpsChange(r) {
    const pending = r.status === 'submitted', answer = pending && r.canResolve;
    let h = '';
    if (!pending) {
      h += r.status === 'closed'
        ? bandeau('info', I.warn, 'Close', esc(r.closedReason ? `Demande close : ${r.closedReason}.` : 'Demande close sans suite.'))
        : bandeau(r.status === 'done' ? 'fait' : 'crit', r.status === 'done' ? I.ok : I.x,
          `${esc(r.statusLabel)}${r.resolvedBy ? ` par ${esc(r.resolvedBy)}` : ''}${r.resolvedAt ? ` · ${esc(r.resolvedAt)}` : ''}`,
          r.resultingPrescriptionUrl
            ? `Ordonnance émise pour cette demande. <a class="lien" href="${esc(r.resultingPrescriptionUrl)}">Voir l’ordonnance émise ${I.ext}</a>`
            : r.status === 'done' ? 'Le client est prévenu dans l’app.' : 'Le client est prévenu du refus dans l’app.');
    }
    h += `<section class="fs"><div class="oinfo">
        <div><small>Animal</small><b><a href="${esc(r.petUrl)}">${esc(r.petEmoji)} ${esc(r.petName)}</a></b><span>${esc(r.petFacts || ' ')}</span></div>
        <div><small>Client</small><b><a href="${esc(r.clientUrl)}">${esc(r.clientName)}</a></b><span>${esc(r.clientPhone || ' ')}</span></div>
        <div><small>Compte app</small><b class="okc">${I.ok}Vérifié</b><span>par code SMS</span></div>
      </div>${r.note ? `<blockquote class="qnote">${esc(r.note)}</blockquote>` : ''}</section>`;

    const v = r.values;
    if (v && (r.field !== 'other' || v.proposed)) {
      h += `<section class="fs"><h3>${esc(r.fieldLabel)}${v.gap ? `<span class="ecart${v.gap.strong ? ' fort' : ''}">${esc(v.gap.label)}</span>` : ''}</h3>
        <div class="avap">
          <div><small>Dans le dossier</small><b class="${v.current == null ? 'vide' : ''}">${esc(v.current ?? 'non renseigné')}</b><span>Valeur à la demande : ${esc(v.atSubmit ?? 'non renseignée')}</span></div>
          <span class="fl">${I.next}</span>
          <div class="new"><small>Proposé par le client</small><b>${esc(v.proposed ?? '—')}</b>${r.measuredOn ? `<span>pesé le ${esc(r.measuredOn)}</span>` : ''}</div>
        </div>
        ${r.weighUrl
          ? `<div class="sugg"><a class="doc" href="${esc(r.weighUrl)}" data-weigh><span class="e">${I.modif}</span><span class="lt"><b>Saisir cette pesée sur la fiche</b><span>Le formulaire de pesée s’ouvre prérempli : vérifiez puis enregistrez</span></span><span class="st mu">${I.ext}</span></a></div>`
          : pending ? `<p class="hint">La fiche n’est pas modifiée automatiquement : appliquez le changement sur la <a class="lien" href="${esc(r.petUrl)}">fiche de ${esc(r.petName)}</a>, puis marquez la demande comme traitée.</p>` : ''}
      </section>`;
    } else if (v && pending) {
      h += `<section class="fs"><h3>Suite à donner</h3><div class="sugg"><a class="doc" href="${esc(r.petUrl)}"><span class="e">${I.modif}</span><span class="lt"><b>Ouvrir le dossier de ${esc(r.petName)}</b><span>Pour noter la demande dans le dossier</span></span><span class="st mu">${I.ext}</span></a></div></section>`;
    }

    if (r.isRenewal) {
      if (r.urgent) {
        h += bandeau('crit', I.warn, 'Effets indésirables signalés', `Ne pas renouveler sans avis. Appelez ${esc(r.clientName)} pour décider de la conduite à tenir.${r.clientPhone ? `<div class="act"><a class="btn-tel" href="tel:${esc(r.clientPhone.replace(/ /g, ''))}">${I.tel}Appeler · ${esc(r.clientPhone)}</a></div>` : ''}`);
      }
      const o = r.prescription;
      h += `<section class="fs"><h3>Ordonnance à renouveler${o ? `<a class="fi-anim" style="margin-left:auto" href="${esc(o.url)}">${esc(o.number)} ${I.ext}</a>` : ''}</h3>
        ${o ? `<div class="ordc"><b>${esc(o.medication)}</b>${o.posology ? `<span>${esc(o.posology)}</span>` : ''}<span>${esc(o.origin)}</span></div>` : '<div class="ordc"><b>Ordonnance introuvable</b><span>L’ordonnance à renouveler n’est plus disponible.</span></div>'}
        <div class="kvs"><div><small>État déclaré</small><span class="puce ${esc(r.conditionClass)}">${esc(r.conditionLabel)}</span></div><div><small>Valable jusqu’au</small><b>${esc(o?.validUntil || '—')}</b></div></div>
        ${pending ? '<p class="hint">Rédigez le renouvellement depuis l’ordonnance, puis rattachez l’ordonnance émise à cette demande avant de la marquer comme traitée.</p>' : ''}</section>`;
    }

    if (answer) {
      if (r.isRenewal && ST.mode !== 'refus') {
        h += `<section class="fs"><h3>Ordonnance émise pour cette demande<span ${TAG}>facultatif</span></h3>
          <select class="finput" id="rx" aria-label="Ordonnance émise pour cette demande"><option value="">— Aucune —</option>${(r.candidates || []).map(c => `<option value="${esc(c.id)}"${ST.rx === c.id ? ' selected' : ''}>${esc(c.label)}</option>`).join('')}</select>
          ${(r.candidates || []).length ? '' : `<p class="hint">Aucune ordonnance émise pour ${esc(r.petName)} depuis la demande.</p>`}</section>`;
      }
      h += message(r);
    } else if (pending) {
      h += bandeau('info', I.warn, 'Réservé aux prescripteurs', 'Seul un membre habilité à prescrire peut répondre à une demande de renouvellement.');
    } else if (r.resolutionMessage) {
      h += `<section class="fs"><h3>Message envoyé</h3><blockquote class="qnote env">${esc(r.resolutionMessage)}</blockquote></section>`;
    }
    return h;
  }

  /* A « Rattachement »: the Portal profile next to each file shown. */
  function corpsLink(r) {
    const many = r.files.length > 1;
    let h = '';
    const ok = ST.resolved ? ST.resolved.ok : r.status === 'done';
    const fait = !!ST.resolved || r.decided;
    if (fait) {
      h += ok
        ? bandeau('fait', I.ok, `Rattachement confirmé${parQui(r)}`, 'Le compte Portal accède au dossier.')
        : bandeau('crit', I.x, `Rattachement refusé${parQui(r)}`, 'La clinique n’est plus proposée à ce compte Portal.');
    }
    h += `<section class="fs"><div class="oinfo">
        <div><small>Animal${r.petNames.length > 1 ? 'aux' : ''}</small><b>🐾 ${esc(r.petNames.length ? r.petNames.join(', ') : '—')}</b><span>${many ? `${r.files.length} fiches client` : ' '}</span></div>
        <div><small>Client</small><b>${esc(r.files.map(f => f.name).join(' · '))}</b><span>${esc(r.files[0]?.phone || ' ')}</span></div>
        <div><small>Compte app</small><b class="okc">${I.ok}Vérifié</b><span>par code SMS</span></div>
      </div></section>`;
    if (!fait) {
      if (r.mismatch) h += bandeau('warn', I.warn, 'Le nom ne correspond pas', 'Le téléphone vérifié est le bon, mais le nom diffère (mariage, nom d’usage ?). Une fois relié, ce compte voit tout l’historique de la fiche : en cas de doute, vérifiez au comptoir.');
      if (r.linkedElsewhere) h += bandeau('warn', I.warn, 'Déjà relié à un autre compte Portal', 'Un dossier listé est déjà relié à un autre compte : vérifiez avec le client avant de confirmer.');
    }
    r.files.forEach((f, i) => {
      h += `<section class="fs" data-client-id="${esc(f.clientId)}"><h3>Compte app <span class="vs">et</span> fiche de la clinique${many ? `<span ${TAG}>${i + 1} / ${r.files.length}</span>` : ''}</h3>
        <div class="cmp">
          <div class="cr"><small>Nom</small><span class="cv">${esc(r.ownerName)}</span><span class="cs ${f.mismatch ? 'ne' : 'ok'}">${f.mismatch ? I.ne : I.ok}</span><span class="cv"><a class="lien" href="${esc(f.url)}">${esc(f.name)}</a></span></div>
          <div class="cr"><small>Téléphone</small><span class="cv">${esc(f.phone || 'mobile du compte')} <em>vérifié</em></span><span class="cs ok">${I.ok}</span><span class="cv">${esc(f.phone || 'même mobile')}</span></div>
          <div class="cr"><small>Depuis</small><span class="cv">demande du ${esc(r.requestedOn)}</span><span class="cs"></span><span class="cv">${esc(f.since ? `client depuis ${f.since}` : '—')}${f.city ? ` · ${esc(f.city)}` : ''}</span></div>
        </div>
        <div class="kvs"><div><small>Animaux sur la fiche</small><b>${esc(f.petNames.length ? f.petNames.join(', ') : '—')}</b></div><div><small>Vérifications</small><div class="flags">${badges(f)}</div></div></div>
        ${i === r.files.length - 1 && !fait ? `<p class="hint">Le propriétaire ne voit encore aucune donnée de la clinique. Confirmez seulement si le profil Portal correspond bien à ce${many ? 's' : ''} dossier${many ? 's' : ''} (en cas de doute, appelez le client).</p>` : ''}
      </section>`;
    });
    return h;
  }
  const badges = (f, attached = false) => [
    f.mismatch ? '<span class="puce et-am" data-badge="mismatch">Nom différent du profil Portal</span>' : '',
    f.linkedElsewhere ? '<span class="puce et-am" data-badge="linked-elsewhere">Déjà relié à un autre compte Portal</span>' : '',
    attached ? '<span class="puce et-ok" data-badge="attached">Dossier d’une autre demande confirmée</span>' : '',
    !f.mismatch && !f.linkedElsewhere ? '<span class="puce et-ok">Nom identique</span>' : '',
  ].join('');

  /* A « Nouveau client »: who asks, for which pet and slot, the files with that mobile. */
  function corpsBooking(r) {
    const m = r.matches || [];
    let h = '';
    if (!r.pending) {
      // « Refusée par Lemoine · … »; without a staff member, what ended it (« Expirée sans réponse · … »).
      const titre = `${esc(r.resolvedBy ? (r.state === 'confirmed' ? 'Confirmée' : 'Refusée') : r.statusLabel)}${parQui(r)}`;
      h += r.state === 'confirmed'
        ? bandeau('fait', I.ok, titre, 'Le rendez-vous est confirmé et le compte Portal rattaché au dossier client.')
        : bandeau('crit', I.x, titre, 'Le créneau est libéré.');
    }
    h += `<section class="fs"><div class="oinfo">
        <div><small>Animal déclaré</small><b>${esc(r.petEmoji)} ${esc(r.petName)}</b><span>${esc(r.petSpecies)}</span></div>
        <div><small>Client</small><b>${esc(r.ownerName)}</b><span>${esc(r.phone || ' ')}</span></div>
        <div><small>Compte app</small><b class="okc">${I.ok}Vérifié</b><span>par code SMS</span></div>
      </div></section>`;
    if (m.length && r.pending) {
      h += bandeau('warn', I.warn, 'Ce client existe peut-être déjà', `${m.length > 1 ? 'Des fiches portent' : 'Une fiche porte'} le même téléphone : ${esc(m.map(x => x.name).join(', '))}. Relier évite un doublon et garde son historique.`);
    }
    h += `<section class="fs"><h3>Rendez-vous demandé${r.pending ? `<span ${TAG}>créneau bloqué jusqu’à votre réponse</span>` : ''}</h3>
      <div class="rdvd"><span class="cal">${I.cal}</span><div><b>${esc(r.slotLong)}</b><span>${esc(r.motif)} · <a class="lien" href="${esc(r.agendaUrl)}">voir l’agenda</a></span></div></div>
      ${r.pending ? '<p class="hint">Le propriétaire n’accède à aucune donnée de la clinique tant que vous n’avez pas confirmé.</p>' : ''}</section>`;
    if (!r.pending) return h;
    if (ST.mode === 'refus') return h + bandeau('crit', I.warn, 'Refuser cette demande ?', 'Le créneau sera libéré et le propriétaire prévenu par SMS.');

    const B = ST.book;
    h += `<section class="fs" id="bkClient"><h3>Fiche client</h3><div class="alist">
      ${m.map(c => `<label class="aitem" data-client-id="${esc(c.clientId)}"><input type="radio" name="client" value="${esc(c.clientId)}"${B.client === c.clientId ? ' checked' : ''}>
        <b>${esc(c.name)} <a class="lien" href="${esc(c.url)}" aria-label="Ouvrir la fiche de ${esc(c.name)}">${I.ext}</a></b>
        <span>Profil Portal : ${esc(r.ownerName)} · ${esc(c.pets.length ? c.pets.map(p => p.label).join(', ') : 'aucun animal')}</span>
        <span class="flags">${badges(c, c.attached)}</span></label>`).join('')}
      ${r.attachedClientId ? '' : `<label class="aitem" data-client-id="new"><input type="radio" name="client" value="new"${B.client === 'new' ? ' checked' : ''}><b>Créer une nouvelle fiche « ${esc(r.ownerName)} »</b><span>Avec ce mobile${r.phone ? ` · ${esc(r.phone)}` : ''}</span></label>`}
      </div></section>
      <section class="fs" id="bkAnimal"><h3>Animal</h3><div class="alist">
      ${m.flatMap(c => c.pets.map(p => `<label class="aitem"><input type="radio" name="animal" value="${esc(p.id)}"${B.animal === p.id ? ' checked' : ''}><b>${esc(p.emoji)} ${esc(p.label)}</b><span>Fiche de ${esc(c.name)}</span></label>`)).join('')}
      <label class="aitem"><input type="radio" name="animal" value="new"${B.animal === 'new' ? ' checked' : ''}><b>Créer « ${esc(r.petName)} » (${esc(r.petSpecies)})</b><span>Pour la fiche choisie</span></label>
      </div></section>`;
    return h;
  }

  function pied(o) {
    if (fini(o) || (o.type === 'change' && !o.canResolve)) {
      const nx = suivante(o);
      return `<span class="sp"></span>${nx ? `<button type="button" class="btn" data-act="suiv">Demande suivante ${I.next}</button>` : ''}`;
    }
    if (ST.mode === 'refus') {
      const off = o.type === 'change' && ST.motif == null && !ST.msg.trim();
      const [act, l] = o.type === 'booking' ? ['decline', 'Refuser la demande'] : ['rejected', 'Refuser et prévenir le client'];
      return `<button type="button" class="btn" data-act="annul">Retour</button><span class="sp"></span><button type="button" class="btn danger" data-act="${act}" id="fiRefuse"${off ? ' disabled' : ''}>${l}</button>`;
    }
    const [act, l] = o.type === 'link' ? ['confirm', 'Confirmer le rattachement'] : o.type === 'booking' ? ['confirm', 'Valider et confirmer le RDV'] : ['done', 'Marquer comme traitée'];
    return `<button type="button" class="btn ghost-danger" data-act="refus">Refuser</button><span class="sp"></span><button type="button" class="btn primaire" data-act="${act}">${l}</button>`;
  }

  function rendreFiche() {
    const o = ST.fiche;
    if (!o) return;
    const [cls, label] = ST.resolved ? (ST.resolved.ok ? ['s-clot', 'Traitée'] : ['s-ann', 'Refusée']) : [o.statusClass, o.statusLabel];
    $('fiHead').innerHTML = `<button type="button" class="fi-retour" data-close aria-label="Retour à la liste">${I.back}</button><div class="fi-ico t-${esc(o.typeKey)}">${I[o.typeKey] || ''}</div><div class="fi-t"><b>${esc(o.title)}</b><span>Reçue ${esc(o.received)} · <span class="puce ${esc(cls)}">${esc(label)}</span>${o.urgent ? '<span class="puce urgx">Urgent</span>' : ''}${o.unreachable ? '<span class="puce et-am">Client injoignable</span>' : ''}</span></div><button type="button" class="fi-x fi-x-orig" id="fiX" data-close data-tip="Fermer · Échap" aria-label="Fermer">${I.x}</button>`;
    const body = $('fiBody'), top = body.scrollTop;
    body.innerHTML = o.type === 'link' ? corpsLink(o) : o.type === 'booking' ? corpsBooking(o) : corpsChange(o);
    body.scrollTop = top;
    $('fiFoot').innerHTML = pied(o);
    compteur();
  }

  function compteur() {
    const n = $('msgN');
    if (n) n.textContent = `${ST.msg.length} / ${MSG_MAX}`;
    const b = $('fiRefuse');
    if (b && ST.fiche?.type === 'change') b.disabled = ST.motif == null && !ST.msg.trim();
  }

  /* ═════════ Answers ═════════ */

  async function poster(url, fields, token) {
    const body = new FormData();
    body.set('_token', token);
    Object.entries(fields).forEach(([k, v]) => (Array.isArray(v) ? v.forEach(x => body.append(`${k}[]`, x)) : body.set(k, v)));
    const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal });
    let d = null;
    try { d = await r.json(); } catch (_) { /* an error page */ }
    return { ok: r.ok && !!d?.success, status: r.status, d };
  }

  async function agir(act) {
    const o = ST.fiche;
    if (!o || ST.busy) return;
    ST.busy = true;
    $('fiFoot').querySelectorAll('button').forEach(b => { b.disabled = true; });
    let res;
    try {
      if (o.type === 'change') {
        res = await poster(o.actions.resolve, { outcome: act, message: ST.msg.trim(), resulting_prescription_id: act === 'done' ? ST.rx : '' }, P.tokens.request);
      } else if (o.type === 'link') {
        res = await poster(o.actions[act], { client_ids: o.files.map(f => f.clientId) }, P.tokens.link);
      } else {
        res = await poster(o.actions[act], act === 'confirm' ? { client: ST.book.client, animal: ST.book.animal } : {}, P.tokens.booking);
      }
    } catch (e) {
      if (e.name === 'AbortError') return;
      res = { ok: false, status: 0, d: null };
    } finally {
      ST.busy = false;
    }
    const texte = res.d?.message || (res.status === 403 ? 'Action refusée : rechargez la page.' : 'La réponse n’a pas pu être enregistrée.');
    // Another sheet was opened meanwhile: say what happened, refresh the list only.
    if (ST.fiche !== o) { showToast(texte, res.ok ? undefined : 'danger'); charger(); return; }
    if (!res.ok) {
      showToast(texte, 'danger');
      // Answered elsewhere first: show what it became.
      if (res.status === 409) { rechargerFiche(); charger(); } else rendreFiche();
      return;
    }
    showToast(texte);
    if (o.type === 'link' && !res.d.pending) {
      ST.mode = null;
      ST.resolved = { ok: act === 'confirm' };
      rendreFiche();
    } else {
      await rechargerFiche();
    }
    charger();
  }

  /* ═════════ Events ═════════ */

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

  // A head counter shows the queue and opens the first request it counts.
  $('phk').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    const ref = ST.reg.head.first[b.dataset.k];
    $('q').value = '';
    filtrer({ statut: 'submitted', type: '', q: '' });
    if (ref) ouvrirFiche(ref);
  }, { signal });

  const ligne = tr => ST.reg.rows.find(r => r.id === tr?.dataset.id);
  $('tbody').addEventListener('click', e => {
    const r = ligne(e.target.closest('tr'));
    if (!r) return;
    // A modified click opens the request's page in a new tab.
    if (e.metaKey || e.ctrlKey) { window.open(r.url, '_blank', 'noopener'); return; }
    if (ST.fiche?.id === r.id) return;
    ouvrirFiche(r);
  }, { signal });
  $('tbody').addEventListener('auxclick', e => {
    const r = ligne(e.target.closest('tr'));
    if (e.button === 1 && r) window.open(r.url, '_blank', 'noopener');
  }, { signal });
  $('tbody').addEventListener('keydown', e => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const r = ligne(e.target.closest('tr'));
    if (!r) return;
    e.preventDefault();
    ouvrirFiche(r);
  }, { signal });

  $('q').addEventListener('input', e => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => filtrer({ q: e.target.value.trim() }), SEARCH_DEBOUNCE_MS);
  }, { signal });

  $('fiHead').addEventListener('click', e => { if (e.target.closest('[data-close]')) fermerFiche(); }, { signal });

  $('fiFoot').addEventListener('click', e => {
    const b = e.target.closest('[data-act]');
    const o = ST.fiche;
    if (!b || b.disabled || !o) return;
    const act = b.dataset.act;
    if (act === 'suiv') {
      const nx = suivante(o);
      if (!nx) return;
      if (ST.f.statut !== 'submitted') filtrer({ statut: 'submitted' });
      ouvrirFiche(nx);
      return;
    }
    // A « Rattachement » has nothing to write: its refusal is confirmed in a dialog.
    if (act === 'refus' && o.type === 'link') {
      ouvrirRefusLien(o, b);
      return;
    }
    if (act === 'refus') {
      ST.mode = 'refus';
      ST.motif = null;
      ST.msg = '';
      rendreFiche();
      $('fiBody').scrollTop = $('fiBody').scrollHeight;
      $('msg')?.focus();
      return;
    }
    if (act === 'annul') {
      ST.mode = null;
      ST.motif = null;
      ST.msg = '';
      rendreFiche();
      return;
    }
    agir(act);
  }, { signal });

  /* The refusal of a « Rattachement »: the VET-OS dialog, owner names written escaped. */
  function ouvrirRefusLien(o, from) {
    refusLien = { o, from };
    $('rlS').textContent = o.files.map(f => f.name).join(' · ');
    $('rlP').innerHTML = `Le compte Portal de <b>${esc(o.ownerName)}</b> ne sera pas relié ${o.files.length > 1 ? 'à ces dossiers' : 'à ce dossier'} et la clinique ne lui sera plus proposée ; ses consentements ne changent pas.`;
    mRef.classList.add('open');
    requestAnimationFrame(() => mRef.querySelector('.mfoot [data-close]')?.focus());
  }
  function fermerRefusLien(rendreFocus = true) {
    if (!mRef.classList.contains('open')) return;
    mRef.classList.remove('open');
    const from = refusLien?.from;
    refusLien = null;
    if (rendreFocus && from?.isConnected) from.focus();
  }
  mRef.addEventListener('mousedown', e => { if (e.target === mRef) fermerRefusLien(); }, { signal });
  mRef.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => fermerRefusLien(), { signal }));
  $('rlOk').addEventListener('click', () => {
    const o = refusLien?.o;
    fermerRefusLien(false);
    // The sheet changed or was answered meanwhile: nothing to refuse.
    if (!o || ST.fiche !== o || fini(o)) return;
    agir('reject');
  }, { signal });

  $('fiBody').addEventListener('click', e => {
    const chip = e.target.closest('[data-motif]');
    if (!chip || !ST.fiche) return;
    ST.motif = +chip.dataset.motif;
    const L = MOTIFS[ST.fiche.isRenewal ? 'renewal' : 'change'];
    ST.msg = L[ST.motif][1].replaceAll('%a', ST.fiche.petName || 'votre animal').slice(0, MSG_MAX);
    rendreFiche();
  }, { signal });
  $('fiBody').addEventListener('input', e => {
    if (e.target.id !== 'msg') return;
    ST.msg = e.target.value;
    compteur();
  }, { signal });
  $('fiBody').addEventListener('change', e => {
    const t = e.target;
    if (t.id === 'rx') { ST.rx = t.value; return; }
    if (!ST.book || t.type !== 'radio') return;
    if (t.name === 'animal') { ST.book.animal = t.value; return; }
    if (t.name === 'client') {
      // The animal follows the file: its look-alike of the new pet, else the new pet.
      const same = (ST.fiche.matches || []).find(c => c.clientId === t.value)?.pets.find(p => p.same);
      ST.book = { client: t.value, animal: same ? same.id : 'new' };
      rendreFiche();
    }
  }, { signal });

  // Escape leaves the refusal first, then closes the sheet (the dropdowns
  // and the search palette handle their own).
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (mRef.classList.contains('open')) { fermerRefusLien(); e.stopPropagation(); return; }
    if (document.querySelector('.dd.open') || document.querySelector('.veil.open') || document.querySelector('.mveil.open')) return;
    if (ST.fiche && ST.mode) { ST.mode = null; ST.motif = null; ST.msg = ''; rendreFiche(); e.stopPropagation(); return; }
    if (ST.fiche) { fermerFiche(); e.stopPropagation(); }
  }, { signal, capture: true });

  // Back / forward within « Demandes » are replayed here; any other entry is
  // left to Turbo.
  releaseHistory?.();
  releaseHistory = registerHistoryHandler(e => {
    if (!root.isConnected || e.state?.kivetoPage !== ENTRY.kivetoPage || !notreAdresse(location.pathname)) return false;
    appliquerAdresse();
    return true;
  });

  /* ═════════ Start ═════════ */

  try { history.replaceState(ENTRY, '', location.href); } catch (_) { /* sandboxed */ }

  // A snapshot Turbo restores keeps the sheet of the moment it was left: the payload decides.
  czone.classList.toggle('ouvert', !!P.sheet);
  if (P.sheet) {
    ST.fiche = P.sheet;
    etatInitial(P.sheet);
    rendreFiche();
  }
  rendreFiltres();
  rendre();

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
  releaseHistory?.();
  releaseHistory = null;
  window.clearTimeout(searchTimer);
  searchTimer = null;
}
