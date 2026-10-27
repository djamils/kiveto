/**
 * Kiveto — assets/js/pages/consultation.js
 * ─────────────────────────────────────────
 * Consultation cockpit, ported from
 * docs/frontend-theme/kiveto-theme/consultation-v-topbar_8.html (and its
 * -vierge / -cloturee variants). The mock-up renders from a demo state `C`;
 * here every block renders from the server state of #consultation-data, and
 * every change posts to its single-action cockpit endpoint, which answers with
 * the refreshed state: the page re-renders from server truth.
 *
 * Controls whose backend does not exist yet (files, lab requests, e-mail,
 * certificates, transfer / pause…) are drawn as in the mock-up but disabled.
 */
import { showToast, toastUndo, creerDropdown } from 'kiveto/shell';

let mounted = false;
const cleanups = [];

export function cleanup() {
  cleanups.splice(0).forEach((fn) => { try { fn(); } catch { /* already gone */ } });
  mounted = false;
}

export function init() {
  const root = document.querySelector('.cs[data-page]');
  if (!root || mounted) return;
  mounted = true;

  const $ = (id) => document.getElementById(id);
  const on = (target, type, fn, opts) => { target.addEventListener(type, fn, opts); cleanups.push(() => target.removeEventListener(type, fn, opts)); };

  let H;
  try { H = JSON.parse($('consultation-data').textContent || '{}'); } catch { H = {}; }
  let S = H.consultation || {};
  const REF = H.reference || {};
  const EP = H.endpoints || {};
  const P = H.patient || {};
  const O = H.owner || null;
  const HIST = H.history || [];
  const DOCS = H.documents || { prescriptions: [], invoice: null };
  const CTX = H.context || {};
  const URLS = CTX.urls || {};
  const LH = CTX.letterhead || { name: 'Clinique vétérinaire', lines: [] };
  const VET = CTX.practitioner || null;
  const VET_NAME = VET ? VET.name : 'Le praticien';
  const TZ = LH.timeZone || 'Europe/Paris';

  // ═════════ Référentiels ═════════
  const ICO = {
    heart: '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
    wind: '<path d="M12.8 19.6A2 2 0 1 0 14 16H2M17.5 8a2.5 2.5 0 1 1 2 4H2M9.8 4.4A2 2 0 1 1 11 8H2"/>',
    gi: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
    droplet: '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    bone: '<path d="M17 10c.7-.7 1.69 0 2.5 0a2.5 2.5 0 1 0 0-5 .5.5 0 0 1-.5-.5 2.5 2.5 0 1 0-5 0c0 .81.7 1.8 0 2.5l-7 7c-.7.7-1.69 0-2.5 0a2.5 2.5 0 0 0 0 5c.28 0 .5.22.5.5a2.5 2.5 0 1 0 5 0c0-.81-.7-1.8 0-2.5Z"/>',
    brain: '<path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M12 5v13"/>',
    sparkle: '<path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0z"/>',
    eye: '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
    tooth: '<path d="M7 3c-2 0-4 1.5-4 4.5 0 3 1.5 4.5 2 7.5.4 2.5 1 6 2.5 6s1.8-3.5 2.5-5.5c.3-.9 1.7-.9 2 0 .7 2 1 5.5 2.5 5.5s2.1-3.5 2.5-6c.5-3 2-4.5 2-7.5C21 4.5 19 3 17 3c-2 0-3 1-5 1S9 3 7 3Z"/>',
    ear: '<path d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/><path d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>',
    shell: '<path d="M14 11a2 2 0 1 1-4 0 4 4 0 0 1 8 0 6 6 0 0 1-12 0 8 8 0 0 1 16 0 10 10 0 1 1-20 0 11.93 11.93 0 0 1 2.42-7.22 2 2 0 1 1 3.16 2.44"/>',
  };
  const svg = (p, s = 14) => `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
  const X_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" width="11" height="11"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  const W_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>';
  const SYSTEMES = REF.systems || [];
  const TEMPLATES = REF.examTemplates || [];
  const VITAUX = REF.vitals || [];
  const KINDS = {
    MED: { l: 'Médicaments', s: 'Médicament', c: '#0891b2', bg: '#ecfeff', i: '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7ZM8.5 8.5l7 7"/>' },
    ACT: { l: 'Actes', s: 'Acte', c: '#4338ca', bg: '#eef2ff', i: '<path d="m18 2 4 4M17 7l3-3M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5M9 11l4 4M5 19l-3 3M14 4l6 6"/>' },
    PRD: { l: 'Produits', s: 'Produit', c: '#b45309', bg: '#fff7ed', i: '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12M3.3 7 12 12l8.7-5"/>' },
    RDV: { l: 'RDV de suivi', s: 'RDV', c: '#7c3aed', bg: '#f5f3ff', i: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' },
    ADV: { l: 'Conseils', s: 'Conseil', c: '#059669', bg: '#ecfdf5', i: '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>' },
    OTH: { l: 'Autre', s: 'Autre', c: '#64748b', bg: '#f1f5f9', i: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>' },
  };
  const PLAN_KIND = { PERFORMED_ACT: 'ACT', MEDICATION_PRESCRIPTION: 'MED', FOLLOW_UP_APPOINTMENT: 'RDV', ADVICE: 'ADV', OTHER: 'OTH' };
  const KIND_PLAN = { ACT: 'PERFORMED_ACT', MED: 'MEDICATION_PRESCRIPTION', RDV: 'FOLLOW_UP_APPOINTMENT', ADV: 'ADVICE', OTH: 'OTHER' };
  const VOIES = ['Per os', 'Sous-cutanée', 'Intramusculaire', 'Intraveineuse', 'Cutanée', 'Auriculaire', 'Oculaire'];
  const MOMENTS = ['Indifférent', 'Pendant le repas', 'À jeun', 'Le matin', 'Le soir'];
  const FREQ = { '1×/j': { l: '1× par jour', pj: 1 }, '2×/j': { l: '2× par jour', pj: 2 }, '3×/j': { l: '3× par jour', pj: 3 }, '1×/sem': { l: '1× par semaine', pj: 1 / 7 }, 'dose unique': { l: 'Prise unique', pj: 0 } };
  const ANA_RX = /bilan|biochim|nfs|urine|ecbu|t4 |t4$|fructos|sdma|sanguin/i;
  const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  const DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  const STAR = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6-5.4-2.8L6.6 19.7l1-6L3.2 9.4l6.1-.9Z"/></svg>';
  const STAR_O = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6-5.4-2.8L6.6 19.7l1-6L3.2 9.4l6.1-.9Z"/></svg>';
  const SPARK = '<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/>';

  // ═════════ Utilitaires ═════════
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const norm = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const fmtE = (minor) => (Math.round(Number(minor) || 0) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' €';
  // Catalogue prices keep the mode they were entered in: TTC unless entered HT.
  const modeTxt = (pm) => (pm === 'excl' ? 'HT' : 'TTC');
  const fmtN = (x, d = 2) => String(Math.round(Number(x) * 10 ** d) / 10 ** d).replace('.', ',');
  const num = (v) => parseFloat(String(v ?? '').replace(',', '.'));
  const pad = (n) => String(n).padStart(2, '0');
  const parseUtc = (v) => {
    if (!v) return null;
    const d = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/.test(v) ? v : v.replace(' ', 'T') + 'Z');
    return Number.isNaN(d.getTime()) ? null : d;
  };
  const hmOf = (d) => d ? d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: TZ }) : '';
  const dateOf = (d) => d ? d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', timeZone: TZ }) : '';
  const fullDateOf = (d) => d ? d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TZ }) : '';
  const serverNow = parseUtc(H.serverNowUtc);
  const clockOffset = serverNow ? serverNow.getTime() - Date.now() : 0;
  const now = () => new Date(Date.now() + clockOffset);
  // Follow-up dates count from the day of the consultation (today while it is open).
  const inDays = (j) => { const d = new Date((parseUtc(S.startedAtUtc) || now()).getTime()); d.setDate(d.getDate() + j); return d; };
  const fmtCourt = (j) => { const d = inDays(j); return `${d.getDate()} ${MONTHS[d.getMonth()]}`; };
  const fr = (j) => `${DAYS[inDays(j).getDay()]} ${fmtCourt(j)}`;
  const visit = (url) => { if (window.Turbo?.visit) window.Turbo.visit(url); else window.location.href = url; };
  const closed = () => S.isClosed === true;
  const speciesKey = P.species === 'cat' ? 'chat' : P.species === 'dog' ? 'chien' : P.species === 'reptile' ? 'reptile' : P.species === 'nac' || P.species === 'rabbit' ? 'lapin' : 'chien';
  const PNAME = P.name || 'le patient';

  // ═════════ Autosave & minuteur ═════════
  let lastSaved = null;
  function setSave(state) {
    const s = $('save');
    if (closed()) { s.dataset.s = 'closed'; $('saveLbl').textContent = 'Clôturée · ' + hmOf(parseUtc(S.closedAtUtc)); return; }
    s.dataset.s = state;
    $('saveLbl').textContent = state === 'saving' ? 'Enregistrement…' : state === 'saved' ? 'Enregistré · ' + hmOf(lastSaved) : state === 'error' ? 'Non enregistré' : 'Brouillon';
  }

  const ERROR_COPY = {
    CSRF_INVALID: 'Session expirée — rechargez la page.',
    CONFLICT: 'Modification concurrente — réessayez.',
    DOMAIN_ERROR: 'Modification refusée dans l\'état actuel de la consultation.',
    VALIDATION: 'Valeur invalide — vérifiez la saisie.',
  };
  let chain = Promise.resolve();
  async function send(url, data) {
    const body = new URLSearchParams();
    Object.entries(data || {}).forEach(([k, v]) => {
      if (v === undefined || v === null) return;
      if (Array.isArray(v)) { v.forEach((x) => body.append(`${k}[]`, x)); return; }
      if (typeof v === 'object') { Object.entries(v).forEach(([sk, sv]) => { if (sv !== undefined && sv !== null) body.append(`${k}[${sk}]`, sv); }); return; }
      body.append(k, v);
    });
    body.append('_token', $('consultation-csrf').value);
    const r = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
    let payload = null;
    try { payload = await r.json(); } catch { /* not JSON */ }
    return { status: r.status, payload };
  }
  /** Queues one mutation (one in flight at a time) and re-renders from the answer. */
  function save(url, data, { render = true } = {}) {
    const run = async () => {
      if (!url) return { success: false };
      setSave('saving');
      try {
        let res = await send(url, typeof data === 'function' ? data() : data);
        if (res.status === 409 && res.payload?.errorCode === 'CONFLICT') res = await send(url, typeof data === 'function' ? data() : data);
        if (res.payload?.success) {
          lastSaved = now();
          if (res.payload.consultation) { S = res.payload.consultation; if (render) rendreTout(); }
          setSave('saved');
          return res.payload;
        }
        setSave('error');
        showToast((res.payload?.errorCode && ERROR_COPY[res.payload.errorCode]) || 'Erreur lors de l\'enregistrement', 'danger');
        if (render) rendreTout();
        return res.payload || { success: false };
      } catch {
        setSave('error');
        showToast('Connexion perdue — modification non enregistrée', 'danger');
        return { success: false };
      }
    };
    chain = chain.then(run, run);
    return chain;
  }
  async function getJson(url, params) {
    try {
      const r = await fetch(`${url}?${new URLSearchParams(params || {})}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      return await r.json();
    } catch { return null; }
  }
  const debounces = {};
  const later = (key, fn, ms = 700) => { clearTimeout(debounces[key]); debounces[key] = setTimeout(fn, ms); };
  cleanups.push(() => Object.values(debounces).forEach(clearTimeout));

  const startedAt = parseUtc(S.startedAtUtc);
  function durationTxt() {
    const end = parseUtc(S.closedAtUtc) || now();
    const t = Math.max(0, Math.floor((end - startedAt) / 1000));
    const h = Math.floor(t / 3600);
    return h ? `${pad(h)}:${pad(Math.floor((t % 3600) / 60))}:${pad(t % 60)}` : `${pad(Math.floor(t / 60))}:${pad(t % 60)}`;
  }
  const tickTimer = () => { if (startedAt) $('timer').textContent = durationTxt(); };
  tickTimer();
  const timerInt = setInterval(() => { if (!closed()) tickTimer(); }, 1000);
  cleanups.push(() => clearInterval(timerInt));

  // ═════════ Menu flottant générique ═════════
  const menu = $('menuPop');
  function ouvrirMenu(anchor, html, bind) {
    menu.innerHTML = html;
    menu.classList.add('open');
    const r = anchor.getBoundingClientRect(), w = menu.offsetWidth, h = menu.offsetHeight;
    let x = Math.min(r.left, window.innerWidth - w - 8), y = r.bottom + 6;
    if (y + h > window.innerHeight - 8) y = r.top - h - 6;
    menu.style.left = Math.max(8, x) + 'px';
    menu.style.top = Math.max(8, y) + 'px';
    bind(menu);
  }
  const fermerMenu = () => menu.classList.remove('open');
  on(document, 'click', (e) => { if (!e.target.closest('.menu-pop') && !e.target.closest('[data-menu]')) fermerMenu(); });
  on(document, 'scroll', (e) => { if (!e.target.closest || !e.target.closest('.menu-pop')) fermerMenu(); }, true);

  /** Re-renders a block without losing the field being typed in. */
  function keepFocus(container, fn) {
    const a = document.activeElement;
    const inside = a && container.contains(a) && (a.matches('input,textarea'));
    const key = inside ? (a.id ? `#${a.id}` : a.dataset.note ? `[data-note="${a.dataset.note}"]` : null) : null;
    const sel = inside ? [a.selectionStart, a.selectionEnd, a.value] : null;
    fn();
    if (key) {
      const b = container.querySelector(key);
      if (b) { b.value = sel[2]; b.focus(); try { b.setSelectionRange(sel[0], sel[1]); } catch { /* not a text field */ } }
    }
  }

  // ═════════ Origine & motifs ═════════
  function rendreOrigine() {
    const o = CTX.origin || {};
    $('motifOrig').textContent = o.type === 'rdv' ? `Depuis le RDV de ${o.at}` : o.type === 'urgence' ? `Urgence · sans RDV${o.at ? ` · arrivé à ${o.at}` : ''}` : `Sans RDV${o.at ? ` · arrivé à ${o.at}` : ''}`;
  }
  const motifLabels = () => (S.motifs || []).map((m) => m.label);
  function saveMotifs(labels) { return save(EP.motifs, { labels }); }
  function rendreMotifs() {
    const s = $('motifStrip'), inp = $('motifFree');
    s.querySelectorAll('.mchipx,.addchip').forEach((x) => x.remove());
    const labels = motifLabels();
    inp.insertAdjacentHTML('beforebegin', labels.map((m, i) => `<span class="mchipx">${esc(m)}<button type="button" class="edit-only" data-i="${i}" aria-label="Retirer">${X_SVG}</button></span>`).join('')
      + '<button type="button" class="addchip edit-only" id="btnMotif" data-menu>+ ajouter</button>');
    if (document.activeElement !== inp) inp.value = S.chiefComplaint || '';
    s.querySelectorAll('.mchipx button').forEach((b) => b.addEventListener('click', () => {
      const i = +b.dataset.i, m = labels[i], next = labels.filter((_, j) => j !== i);
      saveMotifs(next);
      toastUndo(`Motif retiré — ${m}`, () => saveMotifs(labels));
    }));
    $('btnMotif').addEventListener('click', (e) => {
      e.stopPropagation();
      ouvrirMenu(e.currentTarget, (REF.motifs || []).map((g) => `<div class="dd-titre">${esc(g.cat)}</div>` + g.items.filter((x) => !labels.includes(x)).map((x) => `<button type="button" class="dd-it" data-m="${esc(x)}">${esc(x)}</button>`).join('')).join(''), (m) => {
        m.querySelectorAll('[data-m]').forEach((b) => b.addEventListener('click', () => { fermerMenu(); saveMotifs([...motifLabels(), b.dataset.m]); }));
      });
    });
  }
  on($('motifFree'), 'input', (e) => { const v = e.target.value; setSave('saving'); later('motif', () => save(EP.chiefComplaint, { chiefComplaint: v })); });

  // ═════════ Constantes & vigilances ═════════
  let vitEdit = null;
  const vitList = () => {
    const out = [];
    if (S.vitals?.weightKg != null && S.vitals.weightKg !== '') out.push({ id: 'POIDS', v: fmtN(S.vitals.weightKg, 2) });
    if (S.vitals?.temperatureC != null && S.vitals.temperatureC !== '') out.push({ id: 'TEMP', v: fmtN(S.vitals.temperatureC, 1) });
    (S.typedVitals || []).forEach((t) => out.push({ id: t.type, v: String(t.value).replace('.', ','), at: t.recordedAt }));
    return out;
  };
  const range = (id) => (REF.normalRanges?.[speciesKey] || {})[id] || null;
  const hors = (id, v) => { const r = range(id); return !!r && !Number.isNaN(num(v)) && (num(v) < r[0] || num(v) > r[1]); };
  function poids() { const p = vitList().find((v) => v.id === 'POIDS'); return p ? num(p.v) : null; }
  function saveVital(id, value) {
    const cur = S.vitals || {};
    if (id === 'POIDS' || id === 'TEMP') {
      return save(EP.vitals, {
        weightKg: id === 'POIDS' ? String(value).replace(',', '.') : (cur.weightKg ?? ''),
        temperatureC: id === 'TEMP' ? String(value).replace(',', '.') : (cur.temperatureC ?? ''),
      });
    }
    return save(EP.typedVitalRecord, { type: id, value: String(value).replace(',', '.') });
  }
  function removeVital(id) {
    const cur = S.vitals || {};
    if (id === 'POIDS' || id === 'TEMP') return save(EP.vitals, { weightKg: id === 'POIDS' ? '' : (cur.weightKg ?? ''), temperatureC: id === 'TEMP' ? '' : (cur.temperatureC ?? '') });
    return save(EP.typedVitalRemove, { type: id });
  }
  function rendreVitaux() {
    const s = $('vitStrip');
    const list = vitList();
    let html = list.map((x, i) => {
      const vt = VITAUX.find((t) => t.id === x.id) || { c: x.id, l: x.id, u: '' };
      const r = range(x.id);
      if (vitEdit === x.id) return `<span class="vc-edit"><b>${esc(vt.c)}</b><input id="vitIn" value="${esc(x.v)}"><small>${esc(vt.u)}</small></span>`;
      return `<span class="vc${hors(x.id, x.v) ? ' hors' : ''}" data-i="${i}" data-tip="${esc(vt.l)}${x.at ? ' · relevé à ' + hmOf(parseUtc(x.at)) : ''}${r ? ` · réf. ${fmtN(r[0], 1)}–${fmtN(r[1], 1)} ${vt.u}` : ''}"><b>${esc(vt.c)}</b><span class="v">${esc(x.v)}</span><small>${esc(vt.u)}</small><button type="button" class="edit-only" data-rm="${i}" aria-label="Retirer">${X_SVG}</button></span>`;
    }).join('');
    if (vitEdit && !list.find((x) => x.id === vitEdit)) {
      const vt = VITAUX.find((t) => t.id === vitEdit);
      html += `<span class="vc-edit"><b>${esc(vt.c)}</b><input id="vitIn" placeholder="—"><small>${esc(vt.u)}</small></span>`;
    }
    html += '<button type="button" class="addchip edit-only" id="btnVit" data-menu>+ constante</button>';
    const alertes = S.medicalAlerts || [];
    if (closed() && !list.length) html += '<span style="font-size:.78rem;color:var(--encre-35)">Aucune constante relevée</span>';
    if (alertes.length) html += '<span class="vg-sep"></span>' + alertes.map((a) => `<span class="vg"${a.note ? ` data-tip="${esc(a.note)}"` : ''}>${W_SVG}${a.kind === 'ALLERGY' ? 'Allergie · ' : ''}${esc(a.label)}</span>`).join('');
    s.innerHTML = html;
    s.querySelectorAll('.vc').forEach((c) => c.addEventListener('click', (e) => {
      if (e.target.closest('[data-rm]') || closed()) return;
      vitEdit = list[+c.dataset.i].id; rendreVitaux();
    }));
    s.querySelectorAll('[data-rm]').forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      const x = list[+b.dataset.rm], vt = VITAUX.find((t) => t.id === x.id);
      removeVital(x.id);
      toastUndo(`Constante retirée — ${vt ? vt.l : x.id}`, () => saveVital(x.id, x.v));
    }));
    const inp = $('vitIn');
    if (inp) {
      inp.focus(); inp.select();
      let done = false;
      const valider = () => {
        if (done) return; done = true;
        const v = inp.value.trim(), id = vitEdit, ex = list.find((x) => x.id === id);
        vitEdit = null;
        if (v && (!ex || ex.v !== v)) saveVital(id, v); else rendreVitaux();
      };
      inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); valider(); } if (e.key === 'Escape') { done = true; vitEdit = null; rendreVitaux(); } });
      inp.addEventListener('blur', valider);
    }
    $('btnVit').addEventListener('click', (e) => {
      e.stopPropagation();
      const dispo = VITAUX.filter((t) => !list.find((x) => x.id === t.id));
      ouvrirMenu(e.currentTarget, dispo.map((t) => `<button type="button" class="dd-it" data-v="${t.id}">${esc(t.l)}<span class="sub">${esc(t.u)}</span></button>`).join(''), (m) => {
        m.querySelectorAll('[data-v]').forEach((b) => b.addEventListener('click', () => { fermerMenu(); vitEdit = b.dataset.v; rendreVitaux(); }));
      });
    });
  }

  // ═════════ Examen clinique (O) ═════════
  let TYPE_PERSO = {};
  try { TYPE_PERSO = JSON.parse(localStorage.getItem('kiveto-examen-type') || '{}') || {}; } catch { TYPE_PERSO = {}; }
  const U = { tpl: TEMPLATES.find((t) => t.v === speciesKey) ? speciesKey : (TEMPLATES[0]?.v || 'chien'), sysAjout: [], sysRetire: [] };
  U.sysAjout = [...(TYPE_PERSO[U.tpl] || [])];
  const tplOf = () => TEMPLATES.find((t) => t.v === U.tpl) || { base: ['GENERAL'], sugg: [], dispo: [], l: '' };
  const tplName = () => tplOf().l.replace(/^\S+\s/, '');
  const sameType = () => { const t = TYPE_PERSO[U.tpl] || []; return t.length === U.sysAjout.length && t.every((x) => U.sysAjout.includes(x)); };
  const sysOf = (id) => SYSTEMES.find((x) => x.id === id) || { id, l: id, i: 'sparkle', h: '' };
  const examMap = () => Object.fromEntries((S.examSystems || []).map((e) => [e.system, { st: e.status, note: e.notes || '', data: e.structuredData || {} }]));
  const optimistic = {};
  const exam = () => ({ ...examMap(), ...optimistic });
  function sysVisibles() {
    const E = exam(), out = [];
    const add = (id) => { if (!out.find((x) => x.id === id)) out.push({ id }); };
    tplOf().base.forEach(add);
    Object.entries(E).forEach(([id, e]) => { if (e.st !== 'UNTESTED') add(id); });
    U.sysAjout.forEach(add);
    return out.filter((x) => !U.sysRetire.includes(x.id) || (E[x.id] && E[x.id].st !== 'UNTESTED'));
  }
  const sysIds = () => sysVisibles().map((x) => x.id);
  function saveSystem(id, st, note) {
    const cur = exam()[id] || {};
    optimistic[id] = { st, note: note ?? cur.note ?? '', data: cur.data || {} };
    return save(EP.examSystem, { system: id, status: st, notes: st === 'UNTESTED' ? '' : (note ?? cur.note ?? ''), structuredData: st === 'UNTESTED' ? {} : (cur.data || {}) })
      .then((r) => { delete optimistic[id]; return r; });
  }
  let ddTpl = null;
  if (!closed()) {
    ddTpl = creerDropdown('ddTpl', TEMPLATES.map((t) => ({ v: t.v, l: t.l })), U.tpl, (v) => {
      U.tpl = v; U.sysAjout = [...(TYPE_PERSO[v] || [])]; rendreExamen(); showToast('Examen de base : ' + tplName());
    });
  }
  function rendreExamen() {
    const L = $('sysList'), E = exam(), vis = sysVisibles(), T = tplOf();
    const sugg = [];
    motifLabels().forEach((m) => { const P2 = (REF.examByMotif || {})[m] || {}; [...(P2.p || []), ...(P2.s || [])].forEach((id) => sugg.push([id, m.toLowerCase()])); });
    (T.sugg || []).forEach((id) => sugg.push([id, tplName().toLowerCase()]));
    (S.medicalAlerts || []).forEach((al) => Object.entries(REF.examByAlert || {}).forEach(([w, ids]) => { if (norm(al.label).includes(norm(w))) ids.forEach((id) => sugg.push([id, al.label.toLowerCase()])); }));
    const suggV = sugg.filter(([id], i) => !vis.find((x) => x.id === id) && sugg.findIndex((x) => x[0] === id) === i);
    const restants = [...T.base, ...T.dispo].filter((id) => !vis.find((x) => x.id === id) && !suggV.find((x) => x[0] === id));
    keepFocus(L, () => {
      L.innerHTML = vis.map(({ id }) => {
        const s = sysOf(id), e = E[id] || { st: 'UNTESTED', note: '' };
        return `<div class="sys st-${e.st}" data-id="${id}">
          <span class="sic">${svg(ICO[s.i] || ICO.sparkle)}</span>
          <span class="sl">${esc(s.l)}${s.h && id === 'GENERAL' && e.st !== 'ANOMALY' ? `<small class="hint">${esc(s.h)}</small>` : ''}${e.st === 'ANOMALY' && e.note && closed() ? `<small>${esc(e.note)}</small>` : ''}</span>
          ${!closed() && id !== 'GENERAL' ? `<button type="button" class="sys-rm" data-rm="${id}" data-tip="${e.st === 'UNTESTED' ? 'Retirer de l\'examen' : 'Retirer ce système et son résultat'}">${X_SVG}</button>` : ''}
          ${closed() ? `<span class="res ${e.st}">${{ NORMAL: 'RAS', ANOMALY: 'Anomalie', UNTESTED: 'Non examiné' }[e.st]}</span>` : `<div class="tri">${[['NORMAL', 'RAS'], ['ANOMALY', 'Anomalie']].map(([k, l]) => `<button type="button" data-st="${k}" class="${e.st === k ? 'on' : ''}">${l}</button>`).join('')}</div>`}
        </div>${e.st === 'ANOMALY' && !closed() ? `<div class="sys-note"><input data-note="${id}" placeholder="Décrire l'anomalie…" value="${esc(e.note)}"></div>` : ''}`;
      }).join('') + (!closed() ? `<div class="sys-add">${suggV.length ? `<span>Suggérés</span>${suggV.map(([id, why]) => `<button type="button" class="sg" data-add="${id}" data-tip="Suggéré — ${esc(why)}">＋ ${esc(sysOf(id).l)}<i>${esc(why)}</i></button>`).join('')}` : ''}${restants.length ? '<button type="button" class="autre" data-menu id="btnAutreSys">＋ Autre système</button>' : ''}${U.sysAjout.length && !sameType() ? `<button type="button" class="autre type" id="btnType" data-tip="Ces systèmes seront ajoutés d'office à vos prochaines consultations ${esc(tplName().toLowerCase())}">☆ En faire mon examen type</button>` : ''}</div>` : '');
    });
    L.querySelectorAll('.tri button').forEach((b) => b.addEventListener('click', () => {
      const id = b.closest('.sys').dataset.id, cur = (exam()[id] || {}).st;
      const st = cur === b.dataset.st ? 'UNTESTED' : b.dataset.st;
      saveSystem(id, st);
      rendreExamen();
      if (st === 'ANOMALY') setTimeout(() => { const i = L.querySelector(`[data-note="${id}"]`); if (i) i.focus(); }, 0);
    }));
    L.querySelectorAll('[data-rm]').forEach((b) => b.addEventListener('click', () => {
      const id = b.dataset.rm, avant = exam()[id] ? { ...exam()[id] } : null, etaitAjout = U.sysAjout.includes(id);
      U.sysAjout = U.sysAjout.filter((x) => x !== id);
      if (!U.sysRetire.includes(id)) U.sysRetire.push(id);
      if (avant && avant.st !== 'UNTESTED') {
        saveSystem(id, 'UNTESTED');
        toastUndo(`${sysOf(id).l} retiré${avant.st === 'ANOMALY' ? ' avec son anomalie' : ''}`, () => {
          U.sysRetire = U.sysRetire.filter((x) => x !== id); if (etaitAjout) U.sysAjout.push(id); saveSystem(id, avant.st, avant.note);
        });
      }
      rendreExamen();
    }));
    const addSys = (id) => { U.sysRetire = U.sysRetire.filter((x) => x !== id); if (!U.sysAjout.includes(id)) U.sysAjout.push(id); rendreExamen(); };
    L.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => addSys(b.dataset.add)));
    const bT = $('btnType');
    if (bT) bT.addEventListener('click', () => {
      TYPE_PERSO[U.tpl] = [...U.sysAjout];
      try { localStorage.setItem('kiveto-examen-type', JSON.stringify(TYPE_PERSO)); } catch { /* storage unavailable */ }
      rendreExamen();
      showToast(`Examen type enregistré — ${U.sysAjout.map((id) => sysOf(id).l).join(', ')} ajoutés d'office pour vos consultations ${tplName().toLowerCase()}`);
    });
    const bA = $('btnAutreSys');
    if (bA) bA.addEventListener('click', (e) => {
      e.stopPropagation();
      ouvrirMenu(bA, restants.map((id) => `<button type="button" class="dd-it" data-sy="${id}">${esc(sysOf(id).l)}</button>`).join(''), (m) => {
        m.querySelectorAll('[data-sy]').forEach((b) => b.addEventListener('click', () => { fermerMenu(); addSys(b.dataset.sy); }));
      });
    });
    L.querySelectorAll('[data-note]').forEach((i) => i.addEventListener('input', () => {
      const id = i.dataset.note, v = i.value;
      optimistic[id] = { ...(exam()[id] || {}), st: 'ANOMALY', note: v };
      setSave('saving');
      later('note-' + id, () => saveSystem(id, 'ANOMALY', v));
    }));
    const n = vis.filter((x) => E[x.id] && E[x.id].st !== 'UNTESTED').length;
    const a = vis.filter((x) => E[x.id] && E[x.id].st === 'ANOMALY').length;
    $('oCount').textContent = `${n}/${vis.length}` + (a ? ` · ${a} anomalie${a > 1 ? 's' : ''}` : '');
  }
  on($('btnRas'), 'click', () => {
    const E = exam();
    const ids = sysIds().filter((id) => !E[id] || E[id].st === 'UNTESTED');
    if (!ids.length) { showToast('Tous les systèmes affichés sont déjà examinés'); return; }
    ids.forEach((id) => { optimistic[id] = { st: 'NORMAL', note: '', data: {} }; });
    rendreExamen(); majSortie();
    save(EP.examSystemsAllNormal, { systems: ids }).then(() => ids.forEach((id) => delete optimistic[id]));
    toastUndo(`${ids.length} système${ids.length > 1 ? 's' : ''} passé${ids.length > 1 ? 's' : ''} en RAS — anomalies conservées`, () => ids.forEach((id) => saveSystem(id, 'UNTESTED')));
  });

  // ═════════ Diagnostic (A) ═════════
  let dxAjout = false, iaOuvert = false;
  const NOMEN = REF.nomenclature || [];
  function suggestionsIA() {
    const out = [];
    const E = exam();
    Object.entries(E).filter(([, e]) => e.st === 'ANOMALY').forEach(([sys]) => ((REF.suggestions || {})[sys] || []).forEach(([code, why]) => {
      const n = NOMEN.find((x) => x[0] === code);
      if (n && !out.find((o) => o.code === code)) out.push({ code, l: n[1], why: (E[sys].note ? E[sys].note.slice(0, 60) + ' · ' : '') + why });
    }));
    (S.medicalAlerts || []).filter((a) => a.kind === 'CHRONIC_CONDITION').forEach((a) => {
      const n = NOMEN.find((x) => norm(x[1]).includes(norm(a.label)) || norm(a.label).includes(norm(x[1])));
      if (n && !out.find((o) => o.code === n[0])) out.push({ code: n[0], l: n[1], why: `Antécédent au dossier${a.note ? ' (' + a.note.replace(/\.$/, '') + ')' : ''} — à reporter` });
    });
    return out.filter((s) => !(S.diagnoses || []).find((d) => d.code === s.code));
  }
  function addDx(dx) {
    return save(EP.diagnosisAdd, { code: dx.code || '', label: dx.label, certainty: dx.certainty, note: dx.note || '', isPrimary: dx.isPrimary ? '1' : '', source: dx.source || 'MANUAL' });
  }
  function rendreDx() {
    const B = $('aBody');
    const DX = S.diagnoses || [];
    let html = '';
    if (iaOuvert && !closed()) {
      const sg = suggestionsIA();
      html += `<div class="iabox"><p>${svg(SPARK, 12)}Suggestions à partir de l'examen et du dossier</p>`
        + (sg.length ? sg.map((s, i) => `<div class="iarow"><span>${esc(s.l)} <small>${esc(s.code)}</small><span class="why">${esc(s.why)}</span></span><button type="button" data-ia="${i}">+ Ajouter</button></div>`).join('') : '<div class="iarow"><span style="color:var(--encre-60)">Aucune nouvelle piste — signalez une anomalie à l\'examen ou toutes les suggestions sont déjà au différentiel.</span></div>')
        + '</div>';
    }
    if (dxAjout) html += '<div class="addbox"><div class="ab-in"><span class="ab-k">Diagnostic</span><input id="dxIn" placeholder="Code ou libellé (ex. arthrose, M.CARDIO…)" autocomplete="off"></div><div class="ab-res scr" id="dxRes"></div></div>';
    html += DX.length ? DX.map((d, i) => `<div class="dx${d.isPrimary ? ' primary' : ''}${d.certainty === 'EXCLUDED' ? ' excl' : ''}">
        <button type="button" class="star" data-i="${i}" data-tip="${d.isPrimary ? 'Diagnostic principal' : 'Définir comme principal'}">${d.isPrimary ? STAR : STAR_O}</button>
        <div class="dxt"><b title="${esc(d.label)}">${esc(d.label)}</b><div class="dxm"><small>${esc(d.code || 'Texte libre')}${d.source === 'AI_SUGGESTION' ? '<span class="ia-tag">IA</span>' : ''}${d.isPrimary ? ' · principal' : ''}</small>
        ${closed() ? `<span class="res cert-${d.certainty}">${{ CERTAIN: 'Certain', PROBABLE: 'Probable', POSSIBLE: 'Possible', EXCLUDED: 'Exclu' }[d.certainty]}</span></div></div>` : `<div class="cert">${[['CERTAIN', 'Cert.'], ['PROBABLE', 'Prob.'], ['POSSIBLE', 'Poss.'], ['EXCLUDED', 'Exclu']].map(([k, l]) => `<button type="button" data-c="${k}" data-i="${i}" class="${d.certainty === k ? 'on' : ''}">${l}</button>`).join('')}</div></div></div>`}
        <button type="button" class="rm edit-only" data-rm="${i}" aria-label="Retirer">${X_SVG}</button>
      </div>`).join('') : (dxAjout || iaOuvert ? '' : `<div class="vide-q">${closed() ? 'Aucun diagnostic posé.' : 'Aucun diagnostic — ajoutez-en un ou demandez des suggestions.'}</div>`);
    B.innerHTML = html;
    $('aCount').textContent = `${DX.filter((d) => d.certainty !== 'EXCLUDED').length} dx`;
    B.querySelectorAll('[data-ia]').forEach((b) => b.addEventListener('click', () => {
      const s = suggestionsIA()[+b.dataset.ia];
      addDx({ code: s.code, label: s.l, certainty: 'POSSIBLE', isPrimary: !DX.some((d) => d.isPrimary), source: 'AI_SUGGESTION' })
        .then((r) => { if (r?.success) showToast(`Ajouté au différentiel — ${s.l} (possible)`); });
    }));
    B.querySelectorAll('.star').forEach((b) => b.addEventListener('click', () => {
      if (closed()) return;
      const d = DX[+b.dataset.i];
      if (!d.isPrimary) save(EP.diagnosisPrimary, { diagnosisId: d.id });
    }));
    B.querySelectorAll('[data-c]').forEach((b) => b.addEventListener('click', () => {
      const d = DX[+b.dataset.i];
      if (d.certainty === b.dataset.c) return;
      d.certainty = b.dataset.c; rendreDx();
      save(EP.diagnosisUpdate, { diagnosisId: d.id, code: d.code || '', label: d.label, certainty: b.dataset.c, note: d.note || '' });
    }));
    B.querySelectorAll('[data-rm]').forEach((b) => b.addEventListener('click', () => {
      const d = DX[+b.dataset.rm];
      save(EP.diagnosisRemove, { diagnosisId: d.id }).then((r) => {
        // The principal diagnosis passes to the next one, as in the mock-up.
        const next = (S.diagnoses || []).find((x) => x.certainty !== 'EXCLUDED');
        if (r?.success && d.isPrimary && next && !(S.diagnoses || []).some((x) => x.isPrimary)) save(EP.diagnosisPrimary, { diagnosisId: next.id });
      });
      toastUndo(`Diagnostic retiré — ${d.label}`, () => addDx(d));
    }));
    const dxIn = $('dxIn');
    if (dxIn) {
      const res = $('dxRes');
      const ajouter = (code) => {
        const n = NOMEN.find((x) => x[0] === code);
        dxAjout = false;
        addDx(n ? { code: n[0], label: n[1], certainty: 'PROBABLE', isPrimary: !DX.some((d) => d.isPrimary) } : { code: '', label: dxIn.value.trim(), certainty: 'PROBABLE', isPrimary: !DX.some((d) => d.isPrimary) });
      };
      const filtrer = () => {
        const q = norm(dxIn.value.trim());
        const r = NOMEN.filter(([c, l, s]) => !DX.find((d) => d.code === c) && (!q || norm(c + ' ' + l + ' ' + s).includes(q))).slice(0, 7);
        res.innerHTML = r.map(([c, l, s], i) => `<button type="button" class="ab-opt${i === 0 ? ' focus' : ''}" data-c="${c}"><span>${esc(l)}<span class="sub">${esc(s)}</span></span><small>${esc(c)}</small></button>`).join('') || '<div class="ab-opt" style="color:var(--encre-35)">Aucun code — Entrée pour saisir en texte libre</div>';
        res.querySelectorAll('[data-c]').forEach((b) => b.addEventListener('mousedown', (e) => { e.preventDefault(); ajouter(b.dataset.c); }));
      };
      dxIn.addEventListener('input', filtrer);
      dxIn.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { dxAjout = false; rendreDx(); }
        if (e.key === 'Enter') { e.preventDefault(); const f = res.querySelector('[data-c]'); if (f) ajouter(f.dataset.c); else if (dxIn.value.trim()) ajouter(null); }
      });
      dxIn.addEventListener('blur', () => setTimeout(() => { if (dxAjout) { dxAjout = false; rendreDx(); } }, 150));
      filtrer(); dxIn.focus();
    }
  }
  on($('btnAddDx'), 'click', () => { dxAjout = true; rendreDx(); });
  on($('btnIA'), 'click', (e) => { iaOuvert = !iaOuvert; e.currentTarget.classList.toggle('on', iaOuvert); rendreDx(); });

  // ═════════ Plan (P) ═════════
  const billingOf = (id) => (S.billingLines || []).find((b) => b.sourceLineId === id) || null;
  const warningsOf = (id) => (S.allergyWarnings || []).filter((w) => w.prescriptionLineId === id);
  /** One list for the whole plan: plan actions and prescription lines. */
  function planItems() {
    const items = [];
    (S.planActions || []).forEach((a) => {
      const k = PLAN_KIND[a.kind] || 'OTH';
      items.push({ src: 'plan', id: a.id, k, d: a.description, posology: a.posology, duree: a.durationDays, fu: a.followUpDays, qte: a.quantity, pu: a.unitPriceMinorUnits, pm: a.unitPriceMode, code: a.catalogCode, deliv: k === 'MED' ? 'ordo' : null, tot: billingOf(a.id)?.totalMinorUnits || 0 });
    });
    (S.prescriptionLines || []).forEach((l) => {
      const k = l.articleKind && l.articleKind !== 'DRUG' ? 'PRD' : 'MED';
      items.push({ src: 'rx', id: l.id, k, d: l.label, articleId: l.articleId, code: l.code, dose: l.dose, freq: l.frequency, duree: l.durationDays, voie: l.route, qte: l.quantity, pu: l.unitPriceMinorUnits, pm: l.unitPriceMode, stock: l.stock, deliv: 'clinique', tot: billingOf(l.id)?.totalMinorUnits || 0 });
    });
    return items;
  }
  const posoRx = (p) => [p.dose, p.freq, p.duree ? p.duree + ' j' : null, p.voie ? String(p.voie).toLowerCase() : null].filter(Boolean).join(' · ');
  function detail(p) {
    if (p.k === 'MED') return p.src === 'rx' ? posoRx(p) || 'Posologie à préciser' : `${esc(p.posology || 'Posologie à préciser')} · <span class="ordo">ordonnance seule</span>`;
    if (p.k === 'RDV') return p.fu ? `dans ${p.fu} j · ${fmtCourt(p.fu)}` : 'Date à préciser';
    if (p.k === 'ACT') return `${esc(VET_NAME)}${ANA_RX.test(p.d) ? (closed() ? ' · <span class="anst">Non envoyé</span>' : ' · <span class="anst prep"><button type="button" disabled data-tip="Aucun appareil ni laboratoire connecté à la clinique">Laboratoire ▾</button><button type="button" class="go" disabled data-tip="Aucun appareil ni laboratoire connecté à la clinique">Envoyer</button></span>') : ''}`;
    if (p.k === 'PRD') return esc(p.code || '');
    return esc(p.posology || '');
  }
  function qteTxt(p) {
    if (p.k === 'MED') return p.src === 'rx' ? `×${fmtN(p.qte)}` : '';
    if (p.k === 'ACT' || p.k === 'PRD') return p.qte > 1 ? `×${fmtN(p.qte)}` : '';
    return '';
  }
  function alertePresc(p) {
    if (p.src !== 'rx') return '';
    const w = warningsOf(p.id);
    if (w.length) return `Allergie connue — ${w.map((x) => x.alertLabel).join(', ')} : ${p.d} contient ${w.map((x) => x.substance).join(', ')}, à vérifier`;
    if (p.stock === 0) return 'Rupture de stock';
    return '';
  }
  const dueIgnore = new Set();
  function dus() {
    const items = planItems();
    return (CTX.due || []).filter((d) => !dueIgnore.has(d.id) && !items.some((p) => norm(p.d).includes(norm(d.label)) || norm(d.label).includes(norm(p.d))));
  }
  // ── Writes of the plan ──
  function addItem(it) {
    if ((it.k === 'MED' && it.deliv === 'clinique') || it.k === 'PRD') {
      return save(EP.prescriptionLineAdd, { articleId: it.articleId, dose: it.dose || '', frequency: it.freq || '', durationDays: it.duree || '', route: it.voie || '', quantity: it.qte || 1 });
    }
    const posology = it.k === 'MED' ? (it.posology ?? posoRx(it)) : (it.posology || '');
    return save(EP.planActionAdd, {
      kind: KIND_PLAN[it.k], description: it.d, catalogItemId: it.k === 'ACT' ? (it.catalogItemId || '') : '', catalogCode: it.code || '',
      posology, durationDays: it.duree || '', followUpDays: it.k === 'RDV' ? (it.fu || '') : '', quantity: it.qte || 1,
    });
  }
  function removeItem(p) {
    return p.src === 'rx' ? save(EP.prescriptionLineRemove, { prescriptionLineId: p.id }) : save(EP.planActionRemove, { planActionId: p.id });
  }
  /** Re-adds a removed line (undo): a billed act finds its catalog item again by code. */
  async function readd(p) {
    if (p.k === 'ACT' && p.code && p.pu != null) {
      const r = await getJson(EP.catalogSearch, { term: p.d, kind: 'ACT' });
      const hit = (r?.items || []).find((x) => x.code === p.code);
      return addItem({ ...p, catalogItemId: hit?.itemId || '' });
    }
    return addItem({ ...p });
  }
  /** Ids added between two states, so a template can be undone as a whole. */
  const allIds = () => planItems().map((p) => p.id);
  async function catalogFirst(kind, term) {
    const r = await getJson(EP.catalogSearch, { term, kind });
    return (r?.items || [])[0] || null;
  }
  async function appliquerTemplate(t) {
    const before = new Set(allIds());
    for (const item of t.items) {
      if (item.q) {
        const hit = await catalogFirst(item.k, item.q);
        if (item.k === 'MED') {
          if (hit) await addItem({ k: 'MED', deliv: 'clinique', articleId: hit.itemId, dose: item.dose, freq: item.freq, duree: item.duree, voie: item.voie, qte: 1 });
          else await addItem({ k: 'MED', deliv: 'ordo', d: item.q, dose: item.dose, freq: item.freq, duree: item.duree, voie: item.voie });
        } else if (item.k === 'PRD') {
          if (hit) await addItem({ k: 'PRD', articleId: hit.itemId, qte: 1 });
        } else {
          await addItem({ k: 'ACT', d: hit ? hit.name : item.q, catalogItemId: hit?.itemId || '', code: hit?.code || '', qte: 1 });
        }
      } else {
        await addItem({ k: item.k, d: item.d, fu: item.fu });
      }
    }
    const added = planItems().filter((p) => !before.has(p.id));
    toastUndo(`Template « ${t.l.replace(/^\S+\s/, '')} » — ${added.length} ligne${added.length > 1 ? 's' : ''} ajoutée${added.length > 1 ? 's' : ''}`, () => added.forEach(removeItem));
  }
  let ddPlanTpl = null;
  if (!closed()) {
    ddPlanTpl = creerDropdown('ddPlanTpl', (REF.planTemplates || []).map((t) => ({ v: t.v, l: t.l })), '', (v) => {
      const t = (REF.planTemplates || []).find((x) => x.v === v);
      ddPlanTpl.set('');
      if (t) appliquerTemplate(t);
    }, 'Template');
  }
  function rendrePlan() {
    const B = $('pBody');
    const items = planItems();
    let html = '';
    const D = dus();
    if (D.length && !closed()) html += `<div class="due"><span class="due-t">Dû selon le dossier</span>${D.map((d) => `<span class="due-it"><button type="button" data-due="${d.id}">＋ ${esc(d.label)}<i>${esc(d.why)}</i></button><button type="button" class="due-x" data-dux="${d.id}" data-tip="Ignorer pour cette consultation">${X_SVG}</button></span>`).join('')}</div>`;
    const groupes = Object.keys(KINDS).filter((k) => items.some((p) => p.k === k));
    if (!groupes.length) html += `<div class="vide-q">${closed() ? 'Aucune action au plan.' : 'Plan vide — ajoutez une ligne ou partez d\'un template.'}</div>`;
    html += groupes.map((k) => {
      const K = KINDS[k];
      return `<div class="pgrp"><p class="pgt">${K.l}</p>` + items.filter((p) => p.k === k).map((p) => {
        const al = alertePresc(p);
        return `<div class="pit${al ? ' alerte' : ''}" data-id="${p.id}">
          <span class="pk" style="background:${K.bg};color:${K.c}">${svg(K.i, 13)}</span>
          <span class="pt"><b title="${esc(p.d)}">${esc(p.d)}</b>${detail(p) ? `<small>${detail(p)}</small>` : ''}</span>
          ${qteTxt(p) ? `<span class="pq">${qteTxt(p)}</span>` : ''}
          ${p.tot ? `<span class="pp" data-tip="Hors taxes">${fmtE(p.tot)}</span>` : ''}
          <button type="button" class="rm edit-only" data-rm="${p.id}" aria-label="Retirer">${X_SVG}</button>
          ${al ? `<div class="pal">${W_SVG}<span>${esc(al)}</span></div>` : ''}
        </div>`;
      }).join('') + '</div>';
    }).join('');
    html += `<div class="quick edit-only">${['MED', 'ACT', 'PRD', 'RDV', 'ADV'].map((k) => `<button type="button" data-q="${k}">${svg('<path d="M12 5v14M5 12h14"/>', 12)}${KINDS[k].s}</button>`).join('')}</div>`;
    B.innerHTML = html;
    $('pCount').textContent = `${items.length} ligne${items.length > 1 ? 's' : ''}`;
    B.querySelectorAll('[data-rm]').forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      const p = items.find((x) => x.id === b.dataset.rm);
      removeItem(p);
      toastUndo(`${KINDS[p.k].s} retiré — ${p.d}`, () => readd(p));
    }));
    B.querySelectorAll('[data-due]').forEach((b) => b.addEventListener('click', async () => {
      const d = (CTX.due || []).find((x) => x.id === b.dataset.due);
      const hit = await catalogFirst('ACT', d.label);
      const before = new Set(allIds());
      await addItem({ k: 'ACT', d: hit ? hit.name : d.label, catalogItemId: hit?.itemId || '', code: hit?.code || '', qte: 1 });
      const it = planItems().find((p) => !before.has(p.id));
      if (it) toastUndo(`Acte ajouté — ${it.d}`, () => removeItem(it));
    }));
    B.querySelectorAll('[data-dux]').forEach((b) => b.addEventListener('click', () => { dueIgnore.add(b.dataset.dux); rendrePlan(); }));
    B.querySelectorAll('.pit').forEach((el) => el.addEventListener('click', (e) => {
      if (e.target.closest('.pal,.anst,[data-rm]') || closed()) return;
      ouvrirEditeur({ edit: items.find((p) => p.id === el.dataset.id) });
    }));
    B.querySelectorAll('[data-q]').forEach((b) => b.addEventListener('click', () => ouvrirEditeur({ k: b.dataset.q })));
  }
  on($('btnAddPlan'), 'click', () => ouvrirEditeur({ k: 'MED' }));

  // ═════════ Éditeur de ligne de plan ═════════
  const edVeil = $('edVeil');
  let ED = null;
  const I_CALC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/></svg>';
  const I_SRCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
  const SOON = 'Bientôt disponible';
  const stockPuce = (s) => s == null ? '' : s <= 0 ? '<span class="puce rappel">Rupture</span>' : s <= 2 ? `<span class="puce rappel">Stock ${fmtN(s, 0)}</span>` : `<span class="puce ok">Stock ${fmtN(s, 0)}</span>`;
  function ouvrirEditeur(o) {
    if (o.edit) ED = { mode: 'edit', k: o.edit.k, it: { ...o.edit }, ref: o.edit };
    else ED = { mode: 'add', k: o.k, it: null, ref: null };
    $('edKinds').style.display = ED.mode === 'add' ? '' : 'none';
    rendreKinds(); rendreEd();
    edVeil.classList.add('open');
    if (ED.mode === 'add') setTimeout(() => { const i = $('edQ'); if (i) i.focus(); }, 30);
  }
  function fermerEd() { edVeil.classList.remove('open'); ED = null; }
  function rendreKinds() {
    const Sg = $('edKinds');
    Sg.innerHTML = Object.entries(KINDS).map(([k, K]) => `<button type="button" data-k="${k}" class="${ED.k === k ? 'on' : ''}">${svg(K.i, 13)}${K.s}</button>`).join('');
    Sg.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => { ED.k = b.dataset.k; ED.it = null; rendreKinds(); rendreEd(); $('edQ')?.focus(); }));
  }
  const catalogKinds = { MED: 'MED', ACT: 'ACT', PRD: 'PRD' };
  const fromCatalog = (k, c) => {
    if (k === 'ACT') return { k, d: c.name, catalogItemId: c.itemId, code: c.code, pu: c.basePriceMinorUnits, pm: c.basePriceMode, qte: 1 };
    if (k === 'PRD') return { k, d: c.name, articleId: c.itemId, code: c.code, pu: c.basePriceMinorUnits, pm: c.basePriceMode, stock: c.stock, qte: 1 };
    return { k, d: c.name, articleId: c.itemId, code: c.code, pu: c.basePriceMinorUnits, pm: c.basePriceMode, stock: c.stock, conflicts: c.allergyConflicts || [], dose: '', freq: '1×/j', duree: 7, voie: 'Per os', qte: 1, deliv: c.stock === 0 ? 'ordo' : 'clinique' };
  };
  function rendreEd() {
    const K = KINDS[ED.k];
    $('edTitre').textContent = ED.mode === 'add' ? 'Ajouter au plan' : `Modifier — ${K.s.toLowerCase()}`;
    const w = poids();
    $('edSous').textContent = [PNAME, (P.speciesLabel || '').toLowerCase(), w ? fmtN(w, 1) + ' kg' : null, ...(S.medicalAlerts || []).filter((a) => a.kind === 'CHRONIC_CONDITION').map((a) => a.label)].filter(Boolean).join(' · ');
    $('edSuppr').style.display = ED.mode === 'edit' ? '' : 'none';
    const pick = $('edPick'), form = $('edForm');
    if (!ED.it) {
      form.innerHTML = '';
      pick.innerHTML = `<div class="ed-search">${I_SRCH}<input id="edQ" placeholder="Rechercher ${{ MED: 'un médicament', ACT: 'un acte', PRD: 'un produit', RDV: 'un motif de RDV', ADV: 'un conseil', OTH: 'une action' }[ED.k]}…" autocomplete="off"></div><div class="ed-res scr" id="edRes"></div>`;
      const q = $('edQ'), res = $('edRes'), k = ED.k;
      let rows = [];
      const libre = (txt) => {
        if (k === 'MED') choisir({ k: 'MED', d: txt, deliv: 'ordo', dose: '', freq: '1×/j', duree: 7, voie: 'Per os', qte: 1, libre: true });
        else if (k === 'ACT') choisir({ k: 'ACT', d: txt, qte: 1, pu: null, libre: true });
        else choisir({ k, d: txt, fu: 14, qte: 1 });
      };
      const draw = () => {
        const s = norm(q.value.trim());
        const r = catalogKinds[k] ? rows : (REF.planEntries?.[k] || []).filter((c) => !s || norm(c.d).includes(s));
        res.innerHTML = (catalogKinds[k]
          ? r.map((c, i) => `<button type="button" class="ed-opt${i === 0 ? ' focus' : ''}" data-i="${i}"><span><b>${esc(c.name)}</b><span class="sub">${esc(c.code)}${c.allergyConflicts?.length ? ` · <span style="color:#c2410c;font-weight:600">allergie ${esc(c.allergyConflicts.join(', '))}</span>` : ''}</span></span><span class="r">${k !== 'ACT' ? stockPuce(c.stock) : ''}<span class="pr">${fmtE(c.basePriceMinorUnits)}</span></span></button>`).join('')
          : r.map((c, i) => `<button type="button" class="ed-opt${i === 0 ? ' focus' : ''}" data-i="${i}"><span><b>${esc(c.d)}</b>${c.fu ? `<span class="sub">dans ${c.fu} j · ${fmtCourt(c.fu)}</span>` : ''}</span></button>`).join(''))
          + (!r.length && catalogKinds[k] && !q.value.trim() ? '<div class="ed-vide">Aucun article de ce type dans le catalogue de la clinique.</div>' : '')
          + (q.value.trim() && k !== 'PRD' ? `<button type="button" class="ed-opt libre" data-libre>＋ Saisie libre « ${esc(q.value.trim())} »${k === 'MED' ? ' — ordonnance seule' : k === 'ACT' ? ' — non facturé' : ''}</button>` : '');
        res.querySelectorAll('[data-i]').forEach((b) => b.addEventListener('click', () => choisir(catalogKinds[k] ? fromCatalog(k, r[+b.dataset.i]) : { k, d: r[+b.dataset.i].d, fu: r[+b.dataset.i].fu || 14, qte: 1 })));
        const lb = res.querySelector('[data-libre]'); if (lb) lb.addEventListener('click', () => libre(q.value.trim()));
      };
      const load = async () => {
        if (!catalogKinds[k]) { draw(); return; }
        const r = await getJson(EP.catalogSearch, { term: q.value.trim(), kind: catalogKinds[k] });
        if (!ED || ED.it || ED.k !== k) return;
        rows = r?.items || [];
        draw();
      };
      q.addEventListener('input', () => { if (catalogKinds[k]) later('edq', load, 220); else draw(); });
      q.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); const f = res.querySelector('[data-i]'); if (f) f.click(); else if (q.value.trim() && k !== 'PRD') libre(q.value.trim()); }
      });
      load();
    } else { pick.innerHTML = ''; rendreForm(); }
    majEd();
  }
  function choisir(it) { ED.it = it; rendreEd(); const f = document.querySelector('#edForm [data-autofocus]'); if (f) { f.focus(); if (f.select) f.select(); } }
  const unitIn = (id, v, u, extra = '') => `<div class="unit"><input class="finput" id="${id}" value="${esc(v)}" inputmode="decimal" ${extra}><i>${u}</i></div>`;
  const stepper = (id, v) => `<div class="step"><button type="button" data-st="-1" data-for="${id}">−</button><input id="${id}" value="${esc(v)}" inputmode="numeric"><button type="button" data-st="1" data-for="${id}">+</button></div>`;
  const ckrow = (id, l, sub) => `<div class="ckrow off" id="${id}" data-tip="${SOON}"><span class="ck"></span><span>${l}${sub ? ` <small>${sub}</small>` : ''}</span></div>`;
  function rendreForm() {
    const it = ED.it, K = KINDS[ED.k], F = $('edForm');
    const editable = ['ADV', 'OTH', 'RDV'].includes(ED.k) || it.libre || (ED.k === 'MED' && it.deliv === 'ordo' && ED.mode === 'edit' && !it.articleId);
    let h = `<div class="ed-chosen"><span class="pk" style="background:${K.bg};color:${K.c}">${svg(K.i, 16)}</span>
        <span style="flex:1;min-width:0">${editable ? `<input class="ed-name" id="fNom" value="${esc(it.d)}">` : `<b>${esc(it.d)}</b>`}<small>${it.code ? esc(it.code) : K.s}${it.libre ? ' · saisie libre' : ''}</small></span>
        <span class="r">${ED.k === 'MED' || ED.k === 'PRD' ? stockPuce(it.stock) : ''}${ED.mode === 'add' ? '<button type="button" class="lnk" id="edChanger">Changer</button>' : ''}</span></div>`;
    if (ED.k === 'MED') {
      const w = poids();
      h += `<div class="ed-sec"><p class="ed-st">Posologie<span class="r">${w ? `poids ${fmtN(w, 1)} kg · relevé aujourd'hui` : 'poids non relevé — à peser'}</span></p>
        <div class="g3">
          <div class="frow"><label class="flbl">Dose</label><input class="finput" id="fDose" value="${esc(it.dose || '')}" placeholder="ex. 0,1 mg/kg" data-autofocus></div>
          <div class="frow"><label class="flbl">Voie</label><div class="dd" id="fVoie"></div></div>
          <div class="frow"><label class="flbl">Moment</label><div class="dd off" id="fMoment" data-tip="${SOON}"></div></div>
        </div>
        <div class="g3" style="grid-template-columns:1fr 2fr">
          <div class="frow"><label class="flbl">Fréquence</label><div class="dd" id="fFreq"></div></div>
          <div class="frow" id="rowDuree"><label class="flbl">Durée</label>${unitIn('fDuree', it.duree || '', 'jours')}
            <div class="mini-chips" id="chDuree">${[3, 5, 7, 10, 14, 28, 30].map((d) => `<button type="button" data-d="${d}" class="${+it.duree === d ? 'on' : ''}">${d} j</button>`).join('')}</div></div>
        </div>
        <div class="calc" id="fCalc"></div>
      </div>
      <div class="ed-sec"><p class="ed-st">Délivrance</p>
        <div class="seg" id="fDeliv" style="margin-bottom:10px"><button type="button" data-v="clinique"${it.articleId ? '' : ` disabled data-tip="Choisissez le médicament dans le catalogue pour le délivrer ici"`}>Délivré par la clinique</button><button type="button" data-v="ordo">Ordonnance seule</button></div>
        <div class="g3">
          <div class="frow" id="rowQte"><label class="flbl">Quantité</label>${stepper('fQte', it.qte || 1)}</div>
          <div class="frow" id="rowPu"><label class="flbl">Prix unitaire ${modeTxt(it.pm)}</label>${unitIn('fPu', it.pu != null ? fmtN(it.pu / 100) : '—', '€', 'disabled data-tip="Prix du catalogue de la clinique"')}</div>
          <div class="frow"><label class="flbl">Renouvellements</label><div class="dd off" id="fRenouv" data-tip="Se règle sur l'ordonnance, à sa signature"></div></div>
        </div>
        <p class="qhint" id="fQhint" style="margin-top:-4px"></p>
      </div>
      <div class="frow"><label class="flbl">Instructions pour le propriétaire <span class="opt">(imprimées sur l'ordonnance)</span></label><textarea class="ftext" id="fInstr" disabled data-tip="Se rédigent sur l'ordonnance, à sa signature" placeholder="Ex. administrer sur la nourriture…"></textarea></div>
      <div id="fWarn"></div>
      ${ckrow('fCascade', 'Prescription hors AMM (cascade)', 'mention obligatoire ajoutée à l\'ordonnance')}`;
    }
    if (ED.k === 'ACT' || ED.k === 'PRD') {
      h += `<div class="ed-sec"><p class="ed-st">Facturation</p><div class="g3">
          <div class="frow"><label class="flbl">Quantité</label>${stepper('fQte', it.qte || 1)}</div>
          <div class="frow"><label class="flbl">Prix unitaire ${modeTxt(it.pm)}</label>${unitIn('fPu', it.pu != null ? fmtN(it.pu / 100) : '—', '€', 'disabled data-tip="Prix du catalogue de la clinique"')}</div>
          <div class="frow"><label class="flbl">Remise</label>${unitIn('fRemise', '0', '%', `disabled data-tip="${SOON}"`)}</div>
        </div>${it.libre ? '<p class="qhint warn">Saisie libre : l\'acte figure au compte rendu mais n\'est pas facturé.</p>' : ''}</div>`;
      if (ED.k === 'ACT') h += `<div class="g2"><div class="frow"><label class="flbl">Réalisé par</label><div class="dd off" id="fPar" data-tip="${SOON}"></div></div>
          <div class="frow"><label class="flbl">Note <span class="opt">(optionnel)</span></label><input class="finput" id="fNote" disabled data-tip="${SOON}" placeholder="Ex. sous sédation légère"></div></div>`;
    }
    if (ED.k === 'RDV') {
      h += `<div class="ed-sec"><p class="ed-st">Rendez-vous de suivi</p>
        <div class="g3" style="grid-template-columns:1.4fr 1fr 1fr">
          <div class="frow"><label class="flbl">Dans</label>${unitIn('fFu', it.fu || 14, 'jours', 'data-autofocus')}
            <div class="mini-chips" id="chFu">${[7, 10, 14, 30, 90].map((d) => `<button type="button" data-d="${d}" class="${+it.fu === d ? 'on' : ''}">${d} j</button>`).join('')}</div></div>
          <div class="frow"><label class="flbl">Praticien</label><div class="dd off" id="fVet" data-tip="Se choisit en prenant le RDV dans l'agenda"></div></div>
          <div class="frow"><label class="flbl">Durée</label><div class="dd off" id="fDur" data-tip="Se choisit en prenant le RDV dans l'agenda"></div></div>
        </div>
        <div class="calc" id="fCalc"></div></div>
        ${ckrow('fSms', 'Envoyer le lien de réservation par SMS à la clôture', 'le client choisit son créneau')}`;
    }
    if (ED.k === 'ADV') h += ckrow('fSurOrdo', 'Imprimer ce conseil sur l\'ordonnance');
    if (ED.k === 'OTH') h += `<div class="frow"><label class="flbl">Précisions <span class="opt">(optionnel)</span></label><textarea class="ftext" id="fNote" placeholder="Ex. Dr Martin, orthopédie — Aix">${esc(it.posology || '')}</textarea></div>`;
    F.innerHTML = h;

    const n = (v) => { const x = num(v); return Number.isNaN(x) ? 0 : x; };
    $('edChanger')?.addEventListener('click', () => { ED.it = null; rendreEd(); $('edQ')?.focus(); });
    $('fNom')?.addEventListener('input', (e) => { it.d = e.target.value; majEd(); });
    F.querySelectorAll('[data-st]').forEach((b) => b.addEventListener('click', () => {
      const inp = $(b.dataset.for); inp.value = Math.max(1, n(inp.value) + +b.dataset.st); inp.dispatchEvent(new Event('input'));
    }));
    if (ED.k === 'MED') {
      creerDropdown('fVoie', VOIES.map((v) => ({ v, l: v })), VOIES.includes(it.voie) ? it.voie : (it.voie ? null : 'Per os'), (v) => { it.voie = v; majEd(); }, it.voie || 'Voie');
      creerDropdown('fMoment', MOMENTS.map((v) => ({ v, l: v })), 'Indifférent', null);
      creerDropdown('fFreq', Object.entries(FREQ).map(([v, f]) => ({ v, l: f.l })), FREQ[it.freq] ? it.freq : null, (v) => { it.freq = v; majEd(); }, it.freq || 'Fréquence');
      creerDropdown('fRenouv', [{ v: '0', l: 'Aucun' }], '0', null);
      $('fDose').addEventListener('input', (e) => { it.dose = e.target.value; majEd(); });
      $('fDuree').addEventListener('input', (e) => { it.duree = Math.max(1, Math.round(n(e.target.value))) || ''; F.querySelectorAll('#chDuree button').forEach((b) => b.classList.toggle('on', +b.dataset.d === it.duree)); majEd(); });
      F.querySelectorAll('#chDuree button').forEach((b) => b.addEventListener('click', () => { $('fDuree').value = b.dataset.d; $('fDuree').dispatchEvent(new Event('input')); }));
      $('fQte').addEventListener('input', (e) => { it.qte = Math.max(1, Math.round(n(e.target.value))); majEd(); });
      F.querySelectorAll('#fDeliv button').forEach((b) => b.addEventListener('click', () => { if (!b.disabled) { it.deliv = b.dataset.v; majEd(); } }));
    }
    if (ED.k === 'ACT' || ED.k === 'PRD') {
      $('fQte').addEventListener('input', (e) => { it.qte = Math.max(1, Math.round(n(e.target.value))); majEd(); });
      if (ED.k === 'ACT') creerDropdown('fPar', [{ v: 'p', l: VET_NAME }], 'p', null);
    }
    if (ED.k === 'RDV') {
      creerDropdown('fVet', [{ v: 'p', l: VET_NAME }], 'p', null);
      creerDropdown('fDur', [{ v: '30', l: '30 min' }], '30', null);
      $('fFu').addEventListener('input', (e) => { it.fu = Math.max(1, Math.round(n(e.target.value))); F.querySelectorAll('#chFu button').forEach((b) => b.classList.toggle('on', +b.dataset.d === it.fu)); majEd(); });
      F.querySelectorAll('#chFu button').forEach((b) => b.addEventListener('click', () => { $('fFu').value = b.dataset.d; $('fFu').dispatchEvent(new Event('input')); }));
    }
    if (ED.k === 'OTH') $('fNote').addEventListener('input', (e) => { it.posology = e.target.value; });
  }
  function totLigne(it) {
    if (it.pu == null) return 0;
    if (it.k === 'MED') return it.deliv === 'clinique' ? (it.qte || 1) * it.pu : 0;
    if (it.k === 'ACT' || it.k === 'PRD') return (it.qte || 1) * it.pu;
    return 0;
  }
  function majEd() {
    const ok = $('edOk'), tot = $('edTot'), it = ED?.it;
    if (!it) { ok.disabled = true; ok.textContent = 'Ajouter au plan'; tot.innerHTML = ''; return; }
    if (ED.k === 'MED') {
      const w = poids(), m = /([\d.,]+)\s*mg\s*\/\s*kg/i.exec(it.dose || ''), f = FREQ[it.freq];
      const prises = f ? (f.pj === 0 ? 1 : Math.max(1, Math.round(f.pj * (it.duree || 1)))) : null;
      $('rowDuree').style.opacity = it.freq === 'dose unique' ? 0.4 : 1;
      $('fCalc').innerHTML = `${I_CALC}<span>${m && w ? `<b>${fmtN(num(m[1]) * w)} mg</b> par prise (${fmtN(w, 1)} kg) · ` : ''}${it.dose ? `<b>${esc(it.dose)}</b>` : 'dose à préciser'} · ${esc(it.freq || 'fréquence à préciser')}${it.freq === 'dose unique' ? '' : ` · ${it.duree || '?'} j`}${prises ? ` = <b>${prises} prise${prises > 1 ? 's' : ''}</b>` : ''}${m && !w ? ' <span style="opacity:.75">(pesez l\'animal pour calculer la quantité)</span>' : ''}</span>`;
      document.querySelectorAll('#fDeliv button').forEach((b) => b.classList.toggle('on', b.dataset.v === it.deliv));
      const cli = it.deliv === 'clinique';
      $('rowQte').style.display = cli ? '' : 'none'; $('rowPu').style.display = cli ? '' : 'none';
      $('fRenouv').closest('.g3').style.gridTemplateColumns = cli ? '' : '1fr';
      const h = $('fQhint');
      if (!cli) { h.className = 'qhint'; h.innerHTML = 'Le client achète le traitement en pharmacie — rien n\'est facturé ici.'; }
      else if (it.stock != null && it.stock <= 0) { h.className = 'qhint warn'; h.innerHTML = 'Rupture de stock — basculez en « Ordonnance seule » ou commandez.'; }
      else if (it.stock != null && it.qte > it.stock) { h.className = 'qhint warn'; h.innerHTML = `Seulement ${fmtN(it.stock, 0)} en stock.`; }
      else { h.className = 'qhint'; h.innerHTML = it.stock != null ? `${fmtN(it.stock, 0)} en stock · facturé à la clôture.` : 'Facturé à la clôture.'; }
      $('fWarn').innerHTML = (it.conflicts || []).length ? `<div class="rxw" style="margin-bottom:10px">${W_SVG}<span><b>Allergie connue : ${esc(it.conflicts.join(', '))}.</b> Ce médicament en contient — vérifiez avant de prescrire.</span></div>` : '';
    }
    if (ED.k === 'RDV') {
      const d = inDays(it.fu || 0);
      $('fCalc').innerHTML = `${I_CALC}<span>Visée : <b>${DAYS[d.getDay()]} ${fmtCourt(it.fu || 0)}</b>${d.getDay() === 0 ? ' (dimanche — décalez au lundi)' : ''} · le créneau se réserve dans l'agenda</span>`;
    }
    const t = totLigne(it);
    tot.innerHTML = t ? `<small>${it.k === 'MED' ? 'Facturé' : 'Total ligne'} ${modeTxt(it.pm)}</small><b>${fmtE(t)}</b>` : (it.k === 'MED' && it.deliv === 'ordo' ? '<small>Non facturé</small><b>Ordonnance seule</b>' : '');
    ok.disabled = !String(it.d || '').trim();
    ok.textContent = ED.mode === 'edit' ? 'Enregistrer' : `Ajouter au plan${t ? ' — ' + fmtE(t) : ''}`;
  }
  on($('edOk'), 'click', async () => {
    if (!ED || !ED.it) return;
    const it = ED.it, k = ED.k, ref = ED.ref, mode = ED.mode;
    fermerEd();
    if (mode === 'add') {
      const r = await addItem(it);
      if (r?.success) showToast(`${KINDS[k].s} ajouté — ${it.d}`);
      return;
    }
    // Plan actions are updated in place; a prescription line (no update
    // endpoint) or a change of delivery is replaced by a new line.
    const sameSource = (ref.src === 'rx') === ((k === 'MED' && it.deliv === 'clinique') || k === 'PRD');
    let r;
    if (ref.src === 'plan' && sameSource) {
      r = await save(EP.planActionUpdate, {
        planActionId: ref.id, description: it.d, posology: k === 'MED' ? posoRx(it) || it.posology || '' : (it.posology || ''),
        durationDays: it.duree || '', followUpDays: k === 'RDV' ? (it.fu || '') : '', quantity: it.qte || 1,
      });
    } else {
      r = await addItem(it);
      if (r?.success) r = await removeItem(ref);
    }
    if (r?.success) showToast(`${KINDS[k].s} mis à jour — ${it.d}`);
  });
  on($('edSuppr'), 'click', () => {
    const p = ED.ref; fermerEd();
    removeItem(p);
    toastUndo(`${KINDS[p.k].s} retiré — ${p.d}`, () => readd(p));
  });
  ['edX', 'edAnnuler'].forEach((id) => on($(id), 'click', fermerEd));
  on(edVeil, 'click', (e) => { if (e.target === edVeil) fermerEd(); });

  // ═════════ Sortie : ordonnance, facturation, check de clôture ═════════
  const OI = { ordo: ['#ecfeff', '#0891b2', KINDS.MED.i], facture: ['#fff7ed', '#c2410c', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>'], rdv: ['#f5f3ff', '#7c3aed', KINDS.RDV.i], cons: ['#ecfdf5', '#059669', KINDS.ADV.i], cr: ['#eef2ff', '#4338ca', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/>'] };
  const ttc = () => S.totals?.totalTtcMinorUnits || 0;
  function majSortie() {
    const items = planItems();
    const meds = items.filter((p) => p.k === 'MED'), delivres = meds.filter((m) => m.src === 'rx'), rdv = items.filter((p) => p.k === 'RDV'), cons = items.filter((p) => p.k === 'ADV');
    const lignes = S.billingLines || [];
    const nAl = items.filter(alertePresc).length;
    const hasConsult = lignes.some((l) => /^consultation/i.test(l.label));
    const row = (k, t, sub, ap, warn) => { const [bg, c, ic] = OI[k]; return `<button type="button" class="orow" ${ap ? `data-ap="${ap}"` : ''}><span class="oic" style="background:${bg};color:${c}">${svg(ic, 14)}</span><span class="ot"><b>${esc(t)}</b><small>${esc(sub)}</small>${warn ? `<small class="ow">${esc(warn)}</small>` : ''}</span>${ap ? '<span class="oap">Aperçu</span>' : ''}</button>`; };
    const signed = DOCS.prescriptions || [];
    $('outList').innerHTML =
      (meds.length ? row('ordo', 'Ordonnance', closed() && signed.length ? `${signed.map((x) => x.number).join(', ')} · signée` : `${meds.length} médicament${meds.length > 1 ? 's' : ''}${delivres.length ? ` · ${delivres.length} délivré${delivres.length > 1 ? 's' : ''} ici` : ''}`, 'ordo', nAl ? `${nAl} alerte${nAl > 1 ? 's' : ''} à vérifier dans le plan` : '') : '')
      + row('facture', 'Facture', closed() ? (DOCS.invoice ? `${DOCS.invoice.number} · émise` : `${lignes.length} ligne${lignes.length > 1 ? 's' : ''} · à encaisser à l'accueil`) : lignes.length ? `${lignes.length} ligne${lignes.length > 1 ? 's' : ''}${hasConsult ? '' : ' · aucun acte de consultation'}` : 'Vide — ajoutez un acte de consultation', 'facture')
      + rdv.map((r) => row('rdv', r.d, r.fu ? `${fr(r.fu)} · à réserver dans l'agenda` : 'Date à préciser', '')).join('')
      + (cons.length ? row('cons', 'Conseils au propriétaire', `${cons.length} conseil${cons.length > 1 ? 's' : ''}`, 'conseils') : '')
      + row('cr', 'Compte rendu', closed() ? `Signé · ${VET_NAME}` : 'Généré depuis S·O·A·P', 'cr')
      + (!items.length ? '<p class="out-vide">Ordonnance, RDV de suivi et conseils apparaîtront ici au fil du plan.</p>' : '');
    document.querySelectorAll('#outList [data-ap]').forEach((b) => b.addEventListener('click', () => apercuDoc(b.dataset.ap)));
    $('totTtc').textContent = fmtE(ttc());
    $('totHt').textContent = `HT ${fmtE(S.totals?.totalHtMinorUnits || 0)} · TVA ${fmtE(S.totals?.totalTvaMinorUnits || 0)}`;
    const badge = $('billBadge');
    badge.textContent = closed() ? 'à encaisser' : 'brouillon';
    badge.className = closed() ? 'puce rappel' : 'puce';
    const E = exam(), sys = sysIds();
    const nt = sys.filter((id) => !E[id] || E[id].st === 'UNTESTED').length;
    const anoms = Object.values(E).filter((e) => e.st === 'ANOMALY').length, prim = (S.diagnoses || []).some((d) => d.isPrimary);
    const chk = [
      [motifLabels().length > 0, motifLabels().length ? 'Motif renseigné' : 'Motif manquant'],
      [nt === 0, nt === 0 ? 'Examen complet' : `${nt} système${nt > 1 ? 's' : ''} non examiné${nt > 1 ? 's' : ''} — « Tout RAS » ?`],
      prim ? [true, 'Diagnostic principal posé'] : anoms ? [false, `${anoms} anomalie${anoms > 1 ? 's' : ''} sans diagnostic — à préciser ?`] : [true, 'Sans diagnostic — examen normal'],
    ].concat(meds.length ? [[!nAl, nAl ? `${nAl} alerte${nAl > 1 ? 's' : ''} de prescription à vérifier` : 'Prescriptions vérifiées']] : []);
    $('chk').innerHTML = closed() ? '' : chk.map(([ok, l]) => `<div class="${ok ? 'ok' : 'ko'}"><i>${ok ? '✓' : '!'}</i>${esc(l)}</div>`).join('');
    const b = $('btnClore');
    b.disabled = !closed() && !motifLabels().length;
    b.textContent = closed() ? `Encaisser ${fmtE(ttc())} →` : 'Clôturer la consultation';
    if (!closed()) b.dataset.tip = b.disabled ? 'Renseignez au moins un motif pour clôturer' : ''; else delete b.dataset.tip;
  }

  // ═════════ Aperçus papier ═════════
  const prevVeil = $('prevVeil');
  const ENTETE = `<div><b>${esc(LH.name)}</b><p>${(LH.lines || []).map(esc).join('<br>')}${LH.contact ? '<br>' + esc(LH.contact) : ''}${LH.siret ? '<br>SIRET ' + esc(LH.siret) : ''}</p></div>`;
  const today = fullDateOf(now());
  const animalLine = () => [`<b>${esc(PNAME)}</b> · ${esc([(P.speciesLabel || '').toLowerCase(), P.breed].filter(Boolean).join(' '))}`, [P.sexLabel ? (P.sexLabel === '♀' ? 'Femelle' : 'Mâle') + (P.sterilized ? (P.sexLabel === '♀' ? ' stérilisée' : ' castré') : '') : null, P.ageLabel, poids() ? fmtN(poids(), 1) + ' kg' : null].filter(Boolean).map(esc).join(' · '), P.microchip ? 'Puce ' + esc(P.microchip) : ''].filter(Boolean).join('<br>');
  const ownerLine = () => O ? `<b>${esc(O.fullName)}</b>${O.address ? '<br>' + esc(O.address) : ''}` : '—';
  function setPdf(url) {
    const a = $('prevPdf');
    if (url) { a.href = url; a.removeAttribute('aria-disabled'); a.removeAttribute('data-tip'); }
    else { a.removeAttribute('href'); a.setAttribute('aria-disabled', 'true'); a.dataset.tip = 'Le PDF existe une fois le document signé ou émis'; }
  }
  function apercuDoc(k) {
    const Pp = $('paper'), br = !closed();
    const T = $('prevTitre'), Su = $('prevSous');
    const wm = br ? '<div class="wm">BROUILLON</div>' : '';
    const items = planItems();
    if (k === 'facture') {
      const L = S.billingLines || [];
      T.textContent = br || !DOCS.invoice ? 'Facture — brouillon' : `Facture ${DOCS.invoice.number}`;
      Su.textContent = br ? 'Mis à jour en direct · numéro attribué à l\'émission' : DOCS.invoice ? 'Émise' : 'Émise à l\'accueil à l\'encaissement';
      Pp.innerHTML = `${br || !DOCS.invoice ? '<div class="wm">BROUILLON</div>' : ''}
        <div class="pp-head">${ENTETE}<div class="r"><div class="pp-doc">Facture</div><p>N° ${DOCS.invoice && !br ? esc(DOCS.invoice.number) : '— (à l\'émission)'}<br>Date : ${today}<br>Praticien : ${esc(VET_NAME)}</p></div></div>
        <div class="pp-meta"><div><p class="k">Client</p><p>${ownerLine()}</p></div><div><p class="k">Animal</p><p>${animalLine()}</p></div></div>
        <table class="pp-tab"><thead><tr><th>Désignation</th><th class="n">Qté</th><th class="n">PU</th><th class="n">Total</th></tr></thead><tbody>
        ${L.map((l) => `<tr><td>${esc(l.label)}${l.code ? `<small>${esc(l.code)}</small>` : ''}</td><td class="n">${fmtN(l.quantity)}</td><td class="n">${fmtE(l.unitPriceMinorUnits)} ${modeTxt(l.unitPriceMode)}</td><td class="n">${fmtE(l.totalMinorUnits)} ${modeTxt(l.unitPriceMode)}</td></tr>`).join('') || '<tr><td colspan="4" style="color:#94a3b8">Aucune ligne facturable.</td></tr>'}
        </tbody></table>
        <div class="pp-tot"><div><span>Total HT</span><span>${fmtE(S.totals?.totalHtMinorUnits || 0)}</span></div><div><span>TVA</span><span>${fmtE(S.totals?.totalTvaMinorUnits || 0)}</span></div><div class="g"><span>Total TTC</span><span>${fmtE(ttc())}</span></div></div>
        <div class="pp-foot"><span>${br ? 'Document provisoire — non valable comme facture.' : 'Règlement à réception.'}</span><span>Page 1/1</span></div>`;
      setPdf(DOCS.invoice?.url || null);
    } else if (k === 'ordo') {
      const meds = items.filter((p) => p.k === 'MED');
      const signedDoc = (DOCS.prescriptions || [])[0];
      T.textContent = signedDoc ? `Ordonnance ${signedDoc.number}` : 'Ordonnance — brouillon';
      Su.textContent = signedDoc ? `Signée · ${signedDoc.lineCount} ligne${signedDoc.lineCount > 1 ? 's' : ''}${br ? ' · le plan affiché ici peut avoir changé depuis' : ''}` : 'À signer depuis l\'ordonnance';
      Pp.innerHTML = `${signedDoc ? '' : '<div class="wm">BROUILLON</div>'}
        <div class="pp-head">${ENTETE}<div class="r"><div class="pp-doc">Ordonnance</div><p>${esc(VET_NAME)}${LH.ordinal ? '<br>N° ordinal ' + esc(LH.ordinal) : ''}<br>${LH.city ? esc(LH.city) + ', le ' : 'Le '}${today}</p></div></div>
        <div class="pp-meta"><div><p class="k">Animal</p><p>${animalLine()}</p></div><div><p class="k">Détenteur</p><p>${ownerLine()}</p></div></div>
        ${meds.map((m) => `<div class="pp-rx"><b>${esc(m.d)}</b>
          <p>${esc(m.src === 'rx' ? posoRx(m) || 'Posologie à préciser' : m.posology || 'Posologie à préciser')}.</p>
          <p class="q">${m.src === 'rx' ? `Qté : ${fmtN(m.qte)} · délivré par la clinique` : 'À acheter en pharmacie'}</p></div>`).join('') || '<p class="q">Aucun médicament.</p>'}
        <div class="pp-foot"><span>Ne pas dépasser la posologie prescrite.<br>Tenir hors de portée des enfants.</span><div class="pp-sign">${signedDoc ? esc(VET_NAME) : 'signature à la validation de l\'ordonnance'}</div></div>`;
      setPdf(signedDoc?.url || null);
      if (!signedDoc && meds.some((m) => m.src === 'rx') && URLS.prescribe) {
        Su.innerHTML = `${esc(Su.textContent)} · <a href="${esc(URLS.prescribe)}" style="color:var(--pin);font-weight:600">Préparer et signer</a>`;
      }
    } else if (k === 'cr') {
      T.textContent = 'Compte rendu de consultation'; Su.textContent = br ? 'Généré depuis S·O·A·P · mis à jour en direct' : `Signé · ${VET_NAME}`;
      const E = exam(), sys = sysIds();
      const vits = vitList().map((v) => { const vt = VITAUX.find((t) => t.id === v.id); return vt ? `${vt.c} ${v.v} ${vt.u}` : ''; }).filter(Boolean).join(' · ');
      Pp.innerHTML = `${wm}<div class="pp-head">${ENTETE}<div class="r"><div class="pp-doc">Compte rendu</div><p>Consultation du ${fullDateOf(startedAt)}<br>${esc(VET_NAME)}</p></div></div>
        <div class="pp-meta"><div><p class="k">Animal</p><p>${animalLine()}${vits ? '<br>' + esc(vits) : ''}</p></div><div><p class="k">Motif</p><p>${esc(motifLabels().join(', ') || '—')}${S.chiefComplaint ? '<br>' + esc(S.chiefComplaint) : ''}</p></div></div>
        <div class="pp-rx"><b>Anamnèse</b><p>${esc($('qS').value || '—')}</p></div>
        <div class="pp-rx"><b>Examen clinique</b><p>${sys.filter((id) => E[id]?.st === 'ANOMALY').map((id) => `${esc(sysOf(id).l)} : ${esc(E[id].note || 'anomalie')}`).join('<br>') || 'RAS'}${sys.some((id) => E[id]?.st === 'NORMAL') ? `<br><span class="q">${sys.filter((id) => E[id]?.st === 'NORMAL').map((id) => esc(sysOf(id).l)).join(', ')} : RAS</span>` : ''}</p>${$('qO').value ? `<p>${esc($('qO').value)}</p>` : ''}</div>
        <div class="pp-rx"><b>Diagnostic</b><p>${(S.diagnoses || []).filter((d) => d.certainty !== 'EXCLUDED').map((d) => `${esc(d.label)} (${{ CERTAIN: 'certain', PROBABLE: 'probable', POSSIBLE: 'possible' }[d.certainty]})${d.isPrimary ? ' — principal' : ''}`).join('<br>') || '—'}</p></div>
        <div class="pp-rx"><b>Plan</b><p>${items.map((p) => '• ' + esc(p.d) + (p.k === 'MED' ? ' — ' + esc(p.src === 'rx' ? posoRx(p) : p.posology || '') : p.k === 'RDV' && p.fu ? ' — le ' + fmtCourt(p.fu) : '')).join('<br>') || '—'}</p></div>
        ${(S.addenda || []).length ? `<div class="pp-rx"><b>Avenants</b>${S.addenda.map((a) => `<p><span class="q">${dateOf(parseUtc(a.at))} à ${hmOf(parseUtc(a.at))} · ${esc(a.author)} —</span> ${esc(a.text)}</p>`).join('')}</div>` : ''}
        <div class="pp-foot"><span>Compte rendu destiné au propriétaire et aux confrères.</span><div class="pp-sign">${br ? 'signature à la clôture' : esc(VET_NAME)}</div></div>`;
      setPdf(DOCS.report || null);
    } else if (k === 'etiq') {
      T.textContent = 'Étiquettes de délivrance'; Su.textContent = 'Format 70 × 37 mm · imprimante d\'étiquettes de l\'accueil';
      Pp.innerHTML = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">${items.filter((p) => p.k === 'MED' && p.src === 'rx').map((m) => `<div style="border:1px dashed #94a3b8;border-radius:6px;padding:10px 12px;font-size:.72rem;line-height:1.45"><b style="font-size:.8rem">${esc(m.d)}</b><br>Pour : <b>${esc(PNAME)}</b>${O ? ` (${esc(O.fullName)})` : ''}<br>${esc(posoRx(m))}<br><span style="color:#64748b">${esc(VET_NAME)} · ${today} · Usage vétérinaire</span></div>`).join('')}</div>`;
      setPdf(null);
    } else if (k === 'conseils') {
      T.textContent = 'Fiche conseils propriétaire'; Su.textContent = 'Remise au client avec l\'ordonnance';
      const r = items.find((p) => p.k === 'RDV');
      Pp.innerHTML = `${wm}<div class="pp-head">${ENTETE}<div class="r"><div class="pp-doc">Vos conseils</div><p>Pour ${esc(PNAME)} · ${today}</p></div></div>
        ${items.filter((p) => p.k === 'ADV').map((a) => `<div class="pp-rx"><b>${esc(a.d)}</b></div>`).join('')}
        ${r && r.fu ? `<div class="pp-rx"><b>Prochain rendez-vous</b><p>${esc(r.d)} — à partir du ${fmtCourt(r.fu)}.</p></div>` : ''}
        <div class="pp-foot"><span>${LH.contact ? 'Une question ? ' + esc(LH.contact) : ''}</span><span></span></div>`;
      setPdf(null);
    }
    prevVeil.classList.add('open');
  }
  const fermerPrev = () => prevVeil.classList.remove('open');
  ['prevX', 'prevFermer'].forEach((id) => on($(id), 'click', fermerPrev));
  on(prevVeil, 'click', (e) => { if (e.target === prevVeil) fermerPrev(); });
  on($('prevPrint'), 'click', () => {
    document.body.classList.add('cs-print');
    window.print();
    document.body.classList.remove('cs-print');
  });

  // ═════════ Clôture ═════════
  const cloVeil = $('cloVeil');
  const CI = (bg, c, p) => `<span class="ci" style="background:${bg};color:${c}">${svg(p)}</span>`;
  on($('btnClore'), 'click', () => {
    const b = $('btnClore');
    if (b.disabled) return;
    if (closed()) { visit(URLS.checkout); return; }
    const items = planItems();
    const meds = items.filter((p) => p.k === 'MED'), rdv = items.find((p) => p.k === 'RDV');
    $('conseq').innerHTML = [
      meds.length ? `<div>${CI('#ecfeff', '#0891b2', KINDS.MED.i)}<span><b>Ordonnance à signer</b><small>${meds.length} médicament${meds.length > 1 ? 's' : ''} · depuis Documents, après la clôture</small></span></div>` : '',
      `<div>${CI('#fff7ed', '#c2410c', OI.facture[2])}<span><b>Facture de ${fmtE(ttc())} prête à encaisser</b><small>${esc(PNAME)} passe en « Sortie » dans le flux ambulatoire</small></span></div>`,
      rdv && rdv.fu ? `<div>${CI('#f5f3ff', '#7c3aed', KINDS.RDV.i)}<span><b>${esc(rdv.d)} à proposer le ${fmtCourt(rdv.fu)}</b><small>À réserver dans l'agenda avec le client</small></span></div>` : '',
      `<div>${CI('#f1f5f9', '#334155', '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>')}<span><b>Dossier verrouillé</b><small>Toute correction ultérieure passera par un avenant daté</small></span></div>`,
    ].join('');
    cloVeil.classList.add('open');
  });
  const fermerClo = () => cloVeil.classList.remove('open');
  on($('cloX'), 'click', fermerClo);
  on($('cloAnnuler'), 'click', fermerClo);
  on(cloVeil, 'click', (e) => { if (e.target === cloVeil) fermerClo(); });
  // Pending text saves must land before the record is frozen.
  on($('cloForm'), 'submit', (e) => {
    e.preventDefault();
    const f = e.currentTarget;
    $('cloOk').disabled = true;
    flushText();
    chain.then(() => f.submit(), () => f.submit());
  });

  // ═════════ Avenants ═════════
  function rendreAvenants() {
    $('avList').innerHTML = (S.addenda || []).map((a) => `<div class="aven"><span class="av-h"><b>Avenant</b> · ${dateOf(parseUtc(a.at))} à ${hmOf(parseUtc(a.at))} · ${esc(a.author)}</span><p>${esc(a.text)}</p></div>`).join('');
  }
  on($('btnAvenant'), 'click', () => { $('avBox').classList.add('on'); $('avIn').focus(); });
  on($('avAnnuler'), 'click', () => { $('avBox').classList.remove('on'); $('avIn').value = ''; });
  on($('avOk'), 'click', () => {
    const t = $('avIn').value.trim(); if (!t) return;
    save(EP.addendum, { content: t }).then((r) => {
      if (!r?.success) return;
      $('avBox').classList.remove('on'); $('avIn').value = '';
      showToast('Avenant signé et ajouté au dossier — le compte rendu le mentionne');
    });
  });

  // ═════════ Barre patient : historique, plus, dictée ═════════
  on($('btnHisto'), 'click', (e) => {
    e.stopPropagation();
    ouvrirMenu(e.currentTarget, '<div class="dd-titre">Consultations précédentes</div>' + (HIST.length ? HIST.slice(0, 8).map((h, i) => `<button type="button" class="dd-it" data-h="${i}" style="flex-direction:column;align-items:flex-start;gap:1px"><b style="font-size:.8rem;font-weight:600">${esc(h.motif || h.summary || 'Consultation')}</b><span style="font-size:.7rem;color:var(--encre-60)">${fullDateOf(parseUtc(h.startedAtUtc))} · ${h.status === 'OPEN' ? 'en cours' : 'clôturée'}</span></button>`).join('') : '<div class="dd-it" style="color:var(--encre-35);cursor:default">Aucune consultation précédente</div>'), (m) => {
      m.querySelectorAll('[data-h]').forEach((b) => b.addEventListener('click', () => { fermerMenu(); visit(HIST[+b.dataset.h].url); }));
    });
  });
  on($('btnPlus'), 'click', (e) => {
    e.stopPropagation();
    const soon = (l) => `<button type="button" class="dd-it" disabled data-tip="${SOON}">${l}<span class="soon">Bientôt</span></button>`;
    ouvrirMenu(e.currentTarget, `${soon('Transférer à un confrère')}${soon('Mettre en pause')}
      <div class="dd-sep"></div>${URLS.agenda ? '<button type="button" class="dd-it" data-p="agenda">Ouvrir l\'agenda</button>' : ''}<button type="button" class="dd-it rouge" disabled data-tip="${SOON}">Annuler la consultation<span class="soon">Bientôt</span></button>`, (m) => {
      m.querySelector('[data-p="agenda"]')?.addEventListener('click', () => { fermerMenu(); visit(URLS.agenda); });
    });
  });
  // Dictation through the browser's speech recognition, when it has one.
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  const bDict = $('btnDicter');
  if (!SR) { bDict.disabled = true; bDict.dataset.tip = 'Dictée non disponible sur ce navigateur'; }
  let dictee = null;
  on(bDict, 'click', () => {
    if (!SR) return;
    if (dictee) { dictee.stop(); return; }
    const rec = new SR();
    rec.lang = 'fr-FR'; rec.continuous = true; rec.interimResults = false;
    bDict.dataset.html = bDict.innerHTML;
    bDict.classList.add('rec'); bDict.innerHTML = '<span class="rdot"></span>Écoute… (cliquer pour arrêter)';
    rec.onresult = (ev) => {
      const txt = [...ev.results].slice(ev.resultIndex).filter((r) => r.isFinal).map((r) => r[0].transcript).join(' ').trim();
      if (!txt) return;
      const ta = $('qS');
      ta.value = (ta.value ? ta.value.trim() + '\n' : '') + txt.charAt(0).toUpperCase() + txt.slice(1);
      ta.dispatchEvent(new Event('input'));
    };
    rec.onend = () => { dictee = null; bDict.classList.remove('rec'); bDict.innerHTML = bDict.dataset.html; showToast('Dictée transcrite dans le Subjectif'); };
    rec.onerror = () => { showToast('Dictée interrompue — micro indisponible', 'warning'); };
    dictee = rec; rec.start();
  });
  cleanups.push(() => { if (dictee) dictee.abort(); });
  const textFields = { qS: [EP.subjective, 'text'], qO: [EP.objective, 'text'] };
  const pendingText = {};
  function flushText() {
    Object.keys(pendingText).forEach((id) => { clearTimeout(debounces['t-' + id]); const [url, key] = textFields[id]; save(url, { [key]: $(id).value }, { render: false }); delete pendingText[id]; });
    if (debounces.motif) { clearTimeout(debounces.motif); save(EP.chiefComplaint, { chiefComplaint: $('motifFree').value }, { render: false }); }
  }
  Object.entries(textFields).forEach(([id, [url, key]]) => on($(id), 'input', () => {
    pendingText[id] = true; setSave('saving');
    later('t-' + id, () => { delete pendingText[id]; save(url, { [key]: $(id).value }, { render: false }); }, 900);
  }));

  // ═════════ Tiroir : notes internes, fichiers, documents ═════════
  const drawer = $('drawer'), dwVeil = $('dwVeil');
  let dwTab = 'notes';
  const SV = (p) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
  const I_EYE = SV('<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>');
  const I_SEND = SV('<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>');
  const I_PRINT = SV('<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>');
  const I_UP = SV('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>');
  const I_PDF = SV('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/>');
  function ouvrirDrawer(t) {
    const top = document.querySelector('.cs .pbar').getBoundingClientRect().bottom + 'px';
    drawer.style.top = top; dwVeil.style.top = top;
    dwTab = t; drawer.classList.add('open'); dwVeil.classList.add('open'); rendreDrawer();
  }
  function fermerDrawer() { drawer.classList.remove('open'); dwVeil.classList.remove('open'); majBtnsDw(); }
  const notesAll = () => (S.teamMemo ? [{ id: 'memo', memo: true, text: S.teamMemo }] : []).concat(S.teamNotes || []);
  function majBtnsDw() {
    const o = drawer.classList.contains('open');
    $('btnNotes').classList.toggle('on', o && dwTab === 'notes');
    $('btnFiles').classList.toggle('on', o && dwTab === 'files');
    $('btnDocs').classList.toggle('on', o && dwTab === 'docs');
    const n = notesAll().length;
    ['pcNotes', 'tcNotes'].forEach((id) => { $(id).textContent = n; });
    ['pcFiles', 'tcFiles'].forEach((id) => { $(id).textContent = 0; });
    $('pcNotes').classList.toggle('pin', !!S.teamMemo);
  }
  ['notes', 'files', 'docs'].forEach((t) => on($('btn' + t.charAt(0).toUpperCase() + t.slice(1)), 'click', () => (drawer.classList.contains('open') && dwTab === t ? fermerDrawer() : ouvrirDrawer(t))));
  on($('dwX'), 'click', fermerDrawer);
  on(dwVeil, 'click', fermerDrawer);
  document.querySelectorAll('#dwTabs button').forEach((b) => on(b, 'click', () => { dwTab = b.dataset.t; rendreDrawer(); }));
  function rendreDrawer() {
    document.querySelectorAll('#dwTabs button').forEach((b) => b.classList.toggle('on', b.dataset.t === dwTab));
    ({ notes: rendreNotes, files: rendreFichiers, docs: rendreDocs })[dwTab]($('dwBody'), $('dwFoot'), $('dwSub'));
    majBtnsDw();
  }
  // ── Notes internes ──
  const mentions = (s) => esc(s).replace(/@([A-Za-zÀ-ÿ]+)/g, '<span class="men">@$1</span>');
  function rendreNotes(B, F, Sub) {
    Sub.textContent = 'Visibles par l\'équipe uniquement — jamais imprimées ni envoyées au client.';
    B.innerHTML = notesAll().map((n) => n.memo
      ? `<div class="note pin"><span class="av" style="background:#fef3c7;color:#92400e">MÉ</span><div class="nb"><div class="nh"><b>Mémo de la consultation</b><span class="vis clinique">Clinique</span><span class="vis" style="background:#fef3c7;color:#92400e">Épinglée</span></div><p>${mentions(n.text)}</p></div></div>`
      : `<div class="note"><span class="av" style="background:${esc(n.color)}22;color:${esc(n.color)}">${esc(n.initials)}</span>
        <div class="nb"><div class="nh"><b>${esc(n.author)}</b><span class="vis clinique">Clinique</span><time>${dateOf(parseUtc(n.at)) === dateOf(now()) ? hmOf(parseUtc(n.at)) : dateOf(parseUtc(n.at)) + ' ' + hmOf(parseUtc(n.at))}</time></div><p>${mentions(n.text)}</p></div></div>`).join('') || '<div class="vide-q">Aucune note pour l\'instant.</div>';
    if (closed()) { F.innerHTML = '<p class="fhint" style="margin:0">Consultation clôturée — complétez le dossier par un avenant.</p>'; return; }
    F.innerHTML = `<div class="compose" style="position:relative"><textarea id="noteIn" placeholder="Note pour l'équipe… @ pour mentionner"></textarea>
        <div class="crow"><div class="seg" id="noteVis"><button type="button" class="on">Clinique</button><button type="button" disabled data-tip="${SOON}">ASV</button><button type="button" disabled data-tip="${SOON}">Vétos</button><button type="button" disabled data-tip="${SOON}">Privé</button></div>
        <button type="button" class="btn primaire" id="notePub" disabled>Publier</button></div>
        <div class="mention-pop" id="menPop"></div></div>
      <div class="compose-opt"><div class="ckrow off" data-tip="${SOON}"><span class="ck"></span><span>Créer aussi une tâche « À traiter »</span></div><span class="kbd">Ctrl + Entrée</span></div>`;
    const ta = $('noteIn'), pub = $('notePub'), mp = $('menPop');
    const team = (CTX.team || []).filter((m) => !CTX.me || m.name !== CTX.me.name);
    const publier = () => {
      const t = ta.value.trim(); if (!t) return;
      pub.disabled = true;
      save(EP.noteAdd, { noteType: 'GENERAL', content: t }).then((r) => {
        if (!r?.success) { pub.disabled = false; return; }
        showToast('Note publiée pour l\'équipe');
        if (drawer.classList.contains('open') && dwTab === 'notes') { rendreDrawer(); $('dwBody').scrollTop = 0; }
      });
    };
    ta.addEventListener('input', () => {
      pub.disabled = !ta.value.trim();
      const m = ta.value.slice(0, ta.selectionStart).match(/@([A-Za-zÀ-ÿ]*)$/);
      if (m) {
        const q = norm(m[1]), opts = team.filter((A) => norm(A.name).includes(q));
        mp.innerHTML = opts.map((A) => `<button type="button" class="dd-it" data-m="${esc(A.name.replace(/^(Dr|Pr)\.?\s+/i, '').split(' ').pop())}"><span class="av" style="width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:.56rem;font-weight:700;background:${esc(A.color)}22;color:${esc(A.color)}">${esc(A.initials)}</span>${esc(A.name)}</button>`).join('');
        mp.style.left = '8px'; mp.style.bottom = 'calc(100% + 4px)';
        mp.classList.toggle('open', opts.length > 0);
        mp.querySelectorAll('[data-m]').forEach((b) => b.addEventListener('mousedown', (ev) => {
          ev.preventDefault();
          const pos = ta.selectionStart, avant = ta.value.slice(0, pos).replace(/@[A-Za-zÀ-ÿ]*$/, '@' + b.dataset.m + ' ');
          ta.value = avant + ta.value.slice(pos); ta.selectionStart = ta.selectionEnd = avant.length; mp.classList.remove('open'); ta.focus();
        }));
      } else mp.classList.remove('open');
    });
    ta.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); publier(); } if (e.key === 'Escape') mp.classList.remove('open'); });
    pub.addEventListener('click', publier);
  }
  // ── Fichiers (no storage behind them yet) ──
  function rendreFichiers(B, F, Sub) {
    Sub.textContent = `Rattachés à cette consultation et visibles dans le dossier de ${PNAME}.`;
    B.innerHTML = `<div class="drop off" data-tip="Le stockage des fichiers n'est pas encore disponible"><span class="di">${I_UP}</span><span><b>Glissez des fichiers ou parcourez</b><small>Radios DICOM, échos, PDF d'analyses, photos — bientôt disponible</small></span></div>
      <div class="fcats"><button type="button" class="on">Tous<i>0</i></button></div>
      <div class="vide-q">Aucun fichier joint à cette consultation.</div>`;
    F.innerHTML = '';
  }
  // ── Documents (hub d'impression) ──
  const docSel = new Set(['cr']);
  const DOC_IC = { cr: OI.cr, ordo: OI.ordo, facture: OI.facture, etiq: ['#f0fdf4', '#15803d', '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>'], conseils: OI.cons };
  function listeDocs() {
    const items = planItems();
    const meds = items.filter((p) => p.k === 'MED'), deliv = meds.filter((m) => m.src === 'rx'), cons = items.filter((p) => p.k === 'ADV');
    const st = closed() ? 'Signé' : 'Brouillon';
    const signed = DOCS.prescriptions || [];
    return [
      { k: 'cr', n: 'Compte rendu de consultation', s: `${st} · généré depuis S·O·A·P`, pdf: DOCS.report },
      meds.length ? { k: 'ordo', n: 'Ordonnance', s: signed.length ? `Signée · ${signed.map((x) => x.number).join(', ')}` : `À signer · ${meds.length} médicament${meds.length > 1 ? 's' : ''}`, pdf: signed[0]?.url || null } : null,
      { k: 'facture', n: 'Facture', s: `${DOCS.invoice ? DOCS.invoice.number : closed() ? 'Prête à encaisser' : 'Brouillon'} · ${fmtE(ttc())}`, pdf: DOCS.invoice?.url || null },
      deliv.length ? { k: 'etiq', n: 'Étiquettes de délivrance', s: `${deliv.length} étiquette${deliv.length > 1 ? 's' : ''} · posologie + nom de l'animal` } : null,
      cons.length ? { k: 'conseils', n: 'Fiche conseils propriétaire', s: `${cons.length} conseil${cons.length > 1 ? 's' : ''} · rappel du suivi` } : null,
    ].filter(Boolean);
  }
  // « Envoyer par e-mail »: the report, the signed ordonnances and the issued invoice only (labels and advice are print-only).
  const SENDABLE = ['cr', 'ordo', 'facture'];
  const SEND = DOCS.send || { url: null, recipient: null };
  const DEST = SEND.recipient;
  const docsOf = (keys) => keys.flatMap((k) => {
    if (k === 'cr') return ['report'];
    if (k === 'ordo') return (DOCS.prescriptions || []).map((x) => `prescription:${x.id}`);
    if (k === 'facture') return DOCS.invoice?.sendable ? ['invoice'] : [];
    return [];
  });
  /** Why the ticked documents cannot be e-mailed, or '' when they can. */
  function envoiBloque(keys) {
    if (!closed()) return 'Clôturez la consultation pour envoyer ses documents';
    if (!DEST) return 'Aucune adresse e-mail pour ce client';
    if (!docsOf(keys).length) {
      if (keys.length === 1 && keys[0] === 'facture' && DOCS.invoice && !DOCS.invoice.sendable) return 'Facture avoirée ou annulée : elle ne part pas par e-mail';
      return keys.some((k) => SENDABLE.includes(k)) ? 'Document pas encore signé ou émis' : 'Cochez le compte rendu, l\'ordonnance ou la facture';
    }
    return '';
  }
  async function envoyerDocs(keys, btn) {
    if (envoiBloque(keys) || !SEND.url) return;
    btn.disabled = true;
    try {
      const res = await send(SEND.url, { documents: docsOf(keys) });
      if (res.payload?.success) showToast(res.payload.message);
      else showToast(res.payload?.error || 'Envoi impossible — réessayez', 'danger');
    } catch {
      showToast('Connexion perdue — documents non envoyés', 'danger');
    } finally {
      btn.disabled = false;
    }
  }
  function rendreDocs(B, F, Sub) {
    Sub.textContent = 'Tous les documents de la consultation, à jour en direct.';
    const D = listeDocs();
    [...docSel].forEach((k) => { if (!D.find((d) => d.k === k)) docSel.delete(k); });
    B.innerHTML = '<p class="dgrp">De cette consultation</p>' + D.map((d) => { const [bg, c, p] = DOC_IC[d.k]; return `<div class="drow${docSel.has(d.k) ? ' sel' : ''}" data-k="${d.k}">
        <span class="ck"></span><span class="dic" style="background:${bg};color:${c}">${SV(p)}</span>
        <span class="dt"><b>${esc(d.n)}</b><small>${esc(d.s)}</small></span>
        <span class="da"><button type="button" data-ap="${d.k}" data-tip="Aperçu">${I_EYE}</button>${d.pdf ? `<button type="button" data-pdf="${esc(d.pdf)}" data-tip="Télécharger le PDF">${I_PDF}</button>` : ''}${SENDABLE.includes(d.k) ? `<button type="button" data-env="${d.k}"${envoiBloque([d.k]) ? ` disabled data-tip="${esc(envoiBloque([d.k]))}"` : ' data-tip="Envoyer ce document par e-mail"'}>${I_SEND}</button>` : '<button type="button" disabled data-tip="Impression seule">' + I_SEND + '</button>'}</span></div>`; }).join('')
      + `<p class="dgrp" style="margin-top:8px">Générer un document</p><div class="gen">${['Certificat de bonne santé', 'Consentement éclairé (anesthésie)', 'Devis chirurgie', 'Lettre de référé', 'Certificat de vaccination'].map((g) => `<button type="button" disabled data-tip="${SOON}">＋ ${g}</button>`).join('')}</div>`;
    const n = docSel.size;
    const nEnv = docsOf([...docSel]).length;
    F.innerHTML = `<p class="fhint">${n ? `${n} document${n > 1 ? 's' : ''} sélectionné${n > 1 ? 's' : ''}` : 'Sélectionnez des documents'}${O ? ` · ${esc(O.fullName)}${O.email ? ' · ' + esc(O.email) : ''}` : ''}</p>
      <div class="fr"><button type="button" class="btn" id="docEnv"${envoiBloque([...docSel]) ? ` disabled data-tip="${esc(envoiBloque([...docSel]))}"` : ` data-tip="${esc(`Envoyer ${nEnv} document${nEnv > 1 ? 's' : ''} à ${DEST.email}`)}"`}>${I_SEND.replace('<svg', '<svg width="14" height="14"')}Envoyer par e-mail</button><button type="button" class="btn primaire" id="docPrint" ${n ? '' : 'disabled'} data-tip="${n > 1 || (n === 1 && !docSel.has('cr')) ? 'Dossier PDF : compte rendu, ordonnances signées et facture émise' : 'Compte rendu en PDF'}">${I_PRINT.replace('<svg', '<svg width="14" height="14"')}Imprimer${n ? ' (' + n + ')' : ''}</button></div>`;
    B.querySelectorAll('.drow').forEach((r) => r.addEventListener('click', (e) => {
      if (e.target.closest('.da')) return;
      const k = r.dataset.k; if (docSel.has(k)) docSel.delete(k); else docSel.add(k); rendreDrawer();
    }));
    B.querySelectorAll('[data-ap]').forEach((b) => b.addEventListener('click', () => apercuDoc(b.dataset.ap)));
    B.querySelectorAll('[data-pdf]').forEach((b) => b.addEventListener('click', () => window.open(b.dataset.pdf, '_blank', 'noopener')));
    B.querySelectorAll('[data-env]').forEach((b) => b.addEventListener('click', () => envoyerDocs([b.dataset.env], b)));
    $('docEnv').addEventListener('click', (e) => envoyerDocs([...docSel], e.currentTarget));
    $('docPrint').addEventListener('click', () => {
      const url = n === 1 && docSel.has('cr') ? DOCS.report : DOCS.bundle;
      if (url) window.open(url, '_blank', 'noopener');
    });
  }

  // ═════════ Clavier ═════════
  on(document, 'keydown', (e) => {
    if (e.key !== 'Escape') return;
    fermerMenu();
    if (cloVeil.classList.contains('open')) { fermerClo(); return; }
    if (edVeil.classList.contains('open')) { if (!document.querySelector('#edVeil .dd.open')) fermerEd(); return; }
    if (prevVeil.classList.contains('open')) { fermerPrev(); return; }
    if (drawer.classList.contains('open')) fermerDrawer();
  });
  cleanups.push(() => { fermerMenu(); fermerEd(); fermerPrev(); fermerClo(); fermerDrawer(); });

  // ═════════ Init ═════════
  function rendreTout() {
    root.classList.toggle('cs-closed', closed());
    rendreMotifs(); rendreVitaux(); rendreExamen(); rendreDx(); rendrePlan(); majSortie(); rendreAvenants(); majBtnsDw();
    if (drawer.classList.contains('open') && dwTab !== 'notes') rendreDrawer();
  }
  if (closed()) {
    const at = parseUtc(S.closedAtUtc);
    $('closedTxt').textContent = `Consultation clôturée le ${dateOf(at)} à ${hmOf(at)} par ${VET_NAME} · durée ${durationTxt()}`;
    const live = root.querySelector('.live'); if (live) live.style.cssText = 'background:var(--encre-35);animation:none';
  }
  rendreOrigine();
  rendreTout();
  setSave(closed() ? 'closed' : 'idle');
}
