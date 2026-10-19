/**
 * Kiveto — assets/js/pages/dossier/kit.js
 * ───────────────────────────────────────
 * What the client and animal sheets share (« Commun aux dossiers » of
 * docs/frontend-theme/kiveto-theme/client-v-topbar_5.html and
 * animal-v-topbar_5.html): date and money formats, the icon set and the visit
 * kinds, controlled inputs (masks), read-then-edit cards, the « ⋯ » menu,
 * expandable rows, the dossier windows and the rail's fade.
 *
 * Nothing here binds a listener by itself: every helper that listens takes
 * the AbortSignal of the page module, so a Turbo visit releases it all.
 */

export const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ═════════ Dates and amounts (relative to the clinic's today) ═════════ */

export const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
export const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
export const JC = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];

export function dates(todayIso) {
  const D = s => new Date(s + 'T12:00:00');
  const dAn = s => `${+s.slice(8)} ${MOIS[+s.slice(5, 7) - 1]} ${s.slice(0, 4)}`;

  return {
    TODAY_ISO: todayIso,
    D,
    fmtD: s => (s ? s.split('-').reverse().join('/') : '—'),
    dCourt: s => `${+s.slice(8)} ${MOIS[+s.slice(5, 7) - 1]}`,
    dAn,
    dLong: s => { const x = `${JOURS[D(s).getDay()]} ${dAn(s)}`; return x[0].toUpperCase() + x.slice(1); },
    jours: s => Math.round((D(s) - D(todayIso)) / 864e5),
    /** « 10 semaines », « 8 mois », « 1 an et 4 mois », « 9 ans » — at `ref` (today by default). */
    age(n, ref = todayIso) {
      const a = D(n), b = D(ref);
      let m = (b.getFullYear() - a.getFullYear()) * 12 + b.getMonth() - a.getMonth();
      if (b.getDate() < a.getDate()) m--;
      if (m < 3) return `${Math.floor((b - a) / 6048e5)} semaines`;
      return m >= 24 ? `${Math.floor(m / 12)} ans` : m >= 12 ? `1 an${m % 12 ? ` et ${m % 12} mois` : ''}` : `${m} mois`;
    },
  };
}

export const fmtN = (n, d = 1) => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
export const fmtE = n => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
/** Quantity without trailing zeros: 1 → « 1 », 2.5 → « 2,5 ». */
export const fmtQ = n => Number(n).toLocaleString('fr-FR', { maximumFractionDigits: 2 });

/* ═════════ Icons and kinds of visit ═════════ */

const I = p => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
export const IC = {
  cons: I('<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>'),
  vacc: I('<path d="m18 2 4 4M17 7l3-3M19 9 8.7 19.3a2.4 2.4 0 0 1-3.4 0L4.7 18.7a2.4 2.4 0 0 1 0-3.4L15 5M9 11l4 4M5 19l-3 3M14 4l6 6"/>'),
  labo: I('<path d="M9 2v6l-5 9a3 3 0 0 0 2.6 4.5h10.8A3 3 0 0 0 20 17l-5-9V2M8 2h8M7 15h10"/>'),
  ordo: I('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h3"/>'),
  doc: I('<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><path d="M14 2v6h6"/>'),
  img: I('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>'),
  sign: I('<path d="M3 17c3-3 5-6 7-6s-1 6 2 6 4-4 6-4 2 2 3 2M3 21h18"/>'),
  vente: I('<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 2h3l2.7 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>'),
  x: I('<path d="M18 6 6 18M6 6l12 12"/>'), plus: I('<path d="M12 5v14M5 12h14"/>'), chev: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>',
  ext: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>',
  cal: I('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
  crayon: I('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/>'),
  pin: I('<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'),
  tel: I('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/>'),
  mail: I('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'),
  user: I('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'),
  puce: I('<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9zM9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3"/>'),
  bouclier: I('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>'),
};
// A logged exchange (call, SMS, e-mail, letter) reads as a message.
IC.comm = IC.mail;

export const KTYPE = {
  cons: ['Consultation', '#eef2ff', '#4338ca'], vacc: ['Vaccination', '#ecfeff', '#0891b2'], labo: ['Examen', '#fff7ed', '#c2410c'],
  ordo: ['Ordonnance', '#f0fdf4', '#15803d'], vente: ['Achat au comptoir', '#f8fafc', '#475569'],
  comm: ['Échange', '#f8fafc', '#475569'],
};
export const KPLUR = { cons: 'Consultations', vacc: 'Vaccinations', labo: 'Examens', ordo: 'Ordonnances', vente: 'Achats' };
export const icT = k => `<span class="ic" style="background:${KTYPE[k][1]};color:${KTYPE[k][2]}">${IC[k]}</span>`;

/* ═════════ Controlled inputs ═════════ */

export const chiffres = v => v.replace(/\D/g, '');
export const MASK = {
  tel: { f: v => chiffres(v).slice(0, 10).replace(/(\d{2})(?=\d)/g, '$1 '), ok: v => !v || /^0[1-9]\d{8}$/.test(chiffres(v)), err: '10 chiffres, commençant par 0.', im: 'tel', max: 14, live: true },
  cp: { f: v => chiffres(v).slice(0, 5), ok: v => /^(0[1-9]|[1-8]\d|9[0-8])\d{3}$/.test(v), err: '5 chiffres.', im: 'numeric', max: 5, live: true },
  mail: { f: v => v.trim().toLowerCase().replace(/\s/g, ''), ok: v => !v || /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/.test(v), err: 'Adresse e-mail invalide.', im: 'email', max: 80 },
  puce: { f: v => chiffres(v).slice(0, 15), ok: v => !v || /^\d{15}$/.test(v), err: '15 chiffres (norme ISO).', im: 'numeric', max: 15, live: true },
  contrat: { f: v => v.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 20), ok: v => !v || v.replace(/-/g, '').length >= 4, err: '4 caractères minimum, lettres et chiffres.', max: 20, live: true },
  kg: { f: v => v.replace(/[^\d,.]/g, '').replace('.', ','), ok: v => /^\d{1,4}(,\d{1,3})?$/.test(v) && parseFloat(v.replace(',', '.')) > 0, err: 'Un poids en kg, par exemple 14,2.', im: 'decimal', max: 8 },
  nom: { f: v => v.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ' -]/g, '').replace(/\s+/g, ' ').trim().replace(/(^|[ '-])(\p{L})/gu, (m, a, b) => a + b.toUpperCase()), ok: v => v.length >= 2, err: '2 lettres minimum.', max: 40 },
  txt: { f: v => v.replace(/[ \t]+/g, ' ').trim(), ok: () => true },
};

/** A labelled input of the kit (`o.name` makes it post with its form). */
export const champ = (k, v, o = {}) => {
  const M = MASK[o.m || 'txt'];
  return `<div>${o.l ? `<label class="flbl" for="f_${k}">${o.l}${o.req ? ' <span class="req">*</span>' : ''}${o.opt ? ' <span class="opt">· facultatif</span>' : ''}</label>` : ''}<input class="finput" id="f_${k}" data-k="${k}" data-m="${o.m || 'txt'}"${o.name ? ` name="${o.name}"` : ''}${o.req ? ' data-req="1"' : ''} value="${esc(v ?? '')}"${o.ph ? ` placeholder="${esc(o.ph)}"` : ''}${M.im ? ` inputmode="${M.im}"` : ''}${o.max || M.max ? ` maxlength="${o.max || M.max}"` : ''} autocomplete="off" spellcheck="false"${o.type ? ` type="${o.type}"` : ''}${o.maxd ? ` max="${o.maxd}"` : ''}${o.libre ? ' data-libre="1"' : ''}><small class="ferr"></small></div>`;
};

/** A closed list (filled by creerDropdown) with its label and error line. */
export const liste = (k, l, req) => `<div><label class="flbl">${l}${req ? ' <span class="req">*</span>' : ''}</label><div class="dd" id="dd_${k}"></div><small class="ferr" id="err_${k}"></small></div>`;

/** Normalises and checks every `[data-k]` input; null when one is invalid. */
export function lireChamps(root) {
  const V = {};
  let ok = true;
  root.querySelectorAll('[data-k]').forEach(t => {
    if (t.dataset.libre) return;
    const M = MASK[t.dataset.m] || MASK.txt, v = t.type === 'date' ? t.value : M.f(t.value);
    if (t.type !== 'date') t.value = v;
    const err = (t.dataset.req && !v) ? 'Obligatoire.' : (v && !M.ok(v)) ? M.err : '';
    t.classList.toggle('ko', !!err);
    const fe = t.parentElement.querySelector('.ferr');
    if (fe) fe.textContent = err;
    if (err) ok = false;
    V[t.dataset.k] = v;
  });
  return ok ? V : null;
}

export function masquesLive(root, onDirty, signal) {
  root.addEventListener('input', e => {
    const t = e.target;
    if (t.dataset?.m && MASK[t.dataset.m]?.live) {
      const v = MASK[t.dataset.m].f(t.value);
      if (v !== t.value) t.value = v;
    }
    t.classList?.remove('ko');
    const fe = t.parentElement?.querySelector('.ferr');
    if (fe) fe.textContent = '';
    if (!t.dataset?.addin && !t.dataset?.libre && onDirty) onDirty();
  }, { signal });
}

/* ═════════ Cards: read by default, explicit edit ═════════ */

/**
 * Turns a `<section class="card">` into a read-then-edit card. `o` gives
 * titre, meta, metaHaut (meta in the header), lecture (no « Modifier »), lire(), editer(), avant(), monte(),
 * valider() → null (invalid) | {change:false} | {change:true, appliquer()}.
 * appliquer() posts the card's form: the page reloads on the saved card.
 */
export function carte(el, o, cartes, signal) {
  cartes[el.id] = o;
  o.el = el;
  o.edit = false;
  el.innerHTML = `<header><h2>${o.titre}</h2><div class="r"></div></header><div class="cb"></div>`;
  o.rendre = () => {
    el.classList.toggle('edit', o.edit);
    if (o.metaHaut) {
      const h = el.querySelector('header');
      h.querySelector('.meta')?.remove();
      if (!o.edit && o.meta) h.querySelector('.r').insertAdjacentHTML('beforebegin', `<span class="meta" style="margin-left:auto;padding:0;margin-top:0">${esc(o.meta)}</span>`);
    }
    el.querySelector('header .r').innerHTML = o.edit ? `<span class="dirty"${o.dirty ? '' : ' hidden'}>Non enregistré</span>` : (o.lecture ? '' : '<button type="button" class="bmod">Modifier</button>');
    el.querySelector('.cb').innerHTML = o.edit ? `<div class="fg">${o.editer()}</div>` : o.lire();
    el.querySelector('.efoot')?.remove();
    el.querySelector(':scope > .meta')?.remove();
    if (!o.edit && o.meta && !o.metaHaut) el.insertAdjacentHTML('beforeend', `<div class="meta">Modifié le ${esc(o.meta)}</div>`);
    if (o.edit) {
      el.insertAdjacentHTML('beforeend', '<div class="efoot"><small>Échap pour annuler · ⌘ Entrée pour enregistrer</small><span class="sp"></span><button type="button" class="btn" data-ann>Annuler</button><button type="button" class="btn primaire" data-ok>Enregistrer</button></div>');
      o.monte && o.monte();
    }
  };
  el.addEventListener('click', e => {
    if (e.target.closest('.bmod')) {
      o.edit = true; o.dirty = false; o.avant && o.avant(); o.rendre();
      setTimeout(() => { el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); el.querySelector('.fg input:not([data-addin]):not(:disabled),.fg textarea')?.focus({ preventScroll: true }); }, 30);
      return;
    }
    if (e.target.closest('[data-ann]')) { o.edit = false; o.dirty = false; o.rendre(); return; }
    if (e.target.closest('[data-ok]')) enregistrer(o);
  }, { signal });
  masquesLive(el, () => o.edit && marquer(o), signal);
  el.addEventListener('keydown', e => {
    if (!o.edit) return;
    if (e.key === 'Escape') { e.stopPropagation(); e.preventDefault(); o.edit = false; o.dirty = false; o.rendre(); }
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); enregistrer(o); }
  }, { signal });
  o.rendre();
}

export function marquer(o) { o.dirty = true; o.el.querySelector('.dirty')?.removeAttribute('hidden'); }

function enregistrer(o) {
  const r = o.valider();
  if (!r) { o.el.querySelector('.ko')?.focus(); return; }
  if (!r.change) { o.edit = false; o.dirty = false; o.rendre(); return; }
  o.el.querySelectorAll('.efoot .btn').forEach(b => { b.disabled = true; });
  r.appliquer();
}

/** The green pulse of a card just saved. */
export const flashSaved = el => el?.animate([{ boxShadow: '0 0 0 2px #16a34a' }, { boxShadow: '0 0 0 0 transparent' }], { duration: 900 });

/* ═════════ « ⋯ » menu ═════════ */

/** Items: {l, s?, f, danger?, disabled?, tip?} or '-' for a separator. */
export function menu(btn, items, host, signal) {
  const m = document.createElement('div');
  m.className = 'dd-menu dmenu';
  m.style.cssText = 'position:fixed;z-index:120;left:auto;right:auto;width:max-content;min-width:260px;max-width:320px;max-height:none;display:none';
  m.innerHTML = items.map((it, i) => it === '-'
    ? '<div style="height:1px;background:var(--trait);margin:4px 0"></div>'
    : `<button type="button" class="dd-it" data-i="${i}"${it.danger ? ' style="color:#b91c1c"' : ''}${it.disabled ? ` aria-disabled="true" style="opacity:.5;cursor:not-allowed"${it.tip ? ` data-tip="${esc(it.tip)}"` : ''}` : ''}><span>${it.l}${it.s ? `<small>${it.s}</small>` : ''}</span></button>`).join('');
  host.appendChild(m);
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const r = btn.getBoundingClientRect();
    m.style.display = m.style.display === 'block' ? 'none' : 'block';
    m.style.top = (r.bottom + 6) + 'px';
    m.style.left = Math.max(8, r.right - m.offsetWidth) + 'px';
  }, { signal });
  m.addEventListener('click', e => {
    const b = e.target.closest('[data-i]');
    if (!b || b.getAttribute('aria-disabled') === 'true') return;
    m.style.display = 'none';
    items[+b.dataset.i].f();
  }, { signal });
  document.addEventListener('click', e => { if (!e.target.closest('.dmenu')) m.style.display = 'none'; }, { signal });
  return m;
}

/* ═════════ Windows ═════════ */

export function ouvrirModale(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.add('open');
  m.removeAttribute('data-dirty');
}

export function fermerModales() {
  document.querySelectorAll('.dossier .mveil.open').forEach(m => m.classList.remove('open'));
}

export function bindModales(root, signal) {
  root.querySelectorAll('.mveil').forEach(m => {
    m.addEventListener('mousedown', e => { if (e.target === m) fermerModales(); }, { signal });
    m.addEventListener('click', e => { if (e.target.closest('[data-close]')) fermerModales(); }, { signal });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && root.querySelector('.mveil.open') && !document.querySelector('.dd.open')) { fermerModales(); e.stopPropagation(); }
  }, { signal, capture: true });
  // A segmented control inside a window posts through the hidden input it names.
  root.addEventListener('click', e => {
    const b = e.target.closest('.seg[data-seg] button');
    if (!b) return;
    const seg = b.closest('.seg');
    seg.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    const input = seg.closest('form')?.querySelector(`input[type="hidden"][name="${seg.dataset.seg}"]`);
    if (input) input.value = b.dataset.v;
  }, { signal });
  // Switches (role=switch) toggle with the keyboard too.
  document.addEventListener('keydown', e => {
    if ((e.key === ' ' || e.key === 'Enter') && e.target instanceof Element && e.target.matches('.dossier [role="switch"],.dossier .row')) { e.preventDefault(); e.target.click(); }
  }, { signal });
}

/* ═════════ Tabs with a sliding indicator ═════════ */

export function onglets(box, onChange, signal) {
  const ind = document.createElement('span');
  ind.className = 'ind';
  box.prepend(ind);
  const place = () => {
    const b = box.querySelector('button.on');
    if (!b) return;
    ind.style.left = b.offsetLeft + 'px';
    ind.style.width = b.offsetWidth + 'px';
  };
  box.addEventListener('click', e => {
    const b = e.target.closest('button[data-t]');
    if (!b) return;
    box.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    place();
    onChange(b.dataset.t);
  }, { signal });
  requestAnimationFrame(place);
  window.addEventListener('resize', place, { signal });
  return place;
}

/* ═════════ Expandable rows (they replace the side panel) ═════════ */

export function lignes() {
  let OUV = null;

  function deplier(row, html, anim = true) {
    row.classList.add('deplie');
    row.setAttribute('aria-expanded', 'true');
    row.insertAdjacentHTML('afterend', `<div class="dep${anim ? '' : ' on'}"><div>${row.classList.contains('prdv') ? html : `<div class="dep-in">${html}</div>`}</div></div>`);
    const d = row.nextElementSibling;
    if (anim) requestAnimationFrame(() => requestAnimationFrame(() => { d.classList.add('on'); setTimeout(() => d.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 280); }));
  }

  function replier(box) {
    box.querySelectorAll('.deplie').forEach(r => {
      r.classList.remove('deplie');
      r.setAttribute('aria-expanded', 'false');
      const d = r.nextElementSibling;
      if (d && d.classList.contains('dep')) { d.classList.remove('on'); setTimeout(() => d.remove(), 270); }
    });
  }

  return {
    get ouvert() { return OUV; },
    fermer() { OUV = null; },
    replier,
    basculer(row, html) {
      const box = row.parentElement, deja = row.classList.contains('deplie');
      replier(box);
      if (deja) { OUV = null; return; }
      OUV = row.dataset.id;
      deplier(row, html);
    },
    rouvrir(box, fn) {
      if (!OUV) return;
      const r = box.querySelector(`[data-id="${OUV}"]`);
      if (r) { const h = fn(OUV); if (h) deplier(r, h, false); }
    },
  };
}

/* ═════════ Rail ═════════ */

/** The rail fades at the bottom while there is more to scroll. */
export function fonduRail(rail, signal) {
  const fondu = () => rail.classList.toggle('deb', rail.scrollTop + rail.clientHeight < rail.scrollHeight - 4);
  rail.addEventListener('scroll', fondu, { signal });
  window.addEventListener('resize', fondu, { signal });
  const mo = new MutationObserver(() => requestAnimationFrame(fondu));
  mo.observe(rail, { childList: true, subtree: true });
  signal.addEventListener('abort', () => mo.disconnect());
  // A dropdown inside the rail opens in fixed position so the rail's overflow does not clip it.
  document.addEventListener('click', e => {
    const b = e.target.closest('.col.rail .dd-btn');
    if (!b) return;
    requestAnimationFrame(() => {
      const dd = b.parentElement, m = dd.querySelector('.dd-menu');
      if (!dd.classList.contains('open')) return;
      const r = b.getBoundingClientRect(), h = Math.min(m.scrollHeight, 260), up = r.bottom + h + 8 > innerHeight;
      Object.assign(m.style, { position: 'fixed', left: r.left + 'px', width: r.width + 'px', top: (up ? r.top - h - 4 : r.bottom + 4) + 'px', bottom: 'auto', maxHeight: '260px', overflowY: 'auto', zIndex: 130 });
    });
  }, { signal, capture: true });
  rail.addEventListener('scroll', () => rail.querySelectorAll('.dd.open').forEach(d => d.classList.remove('open')), { signal });
  return fondu;
}
