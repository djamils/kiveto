/**
 * Shared tax-profile block (« profil fiscal »): the two-option status
 * selector, the legal name and — for a VAT-liable professional — the SIREN
 * and the French VAT number. One module for every window that creates or
 * edits a client (counter « Professionnel » tab, directory « Nouveau client »,
 * client sheet card); the markup comes from the `#tplFisc` template
 * (templates/clinic/clients/_tax_profile_block.html.twig) and the server
 * checks the same rules again (TaxProfileInput, Siren, FrenchVatNumber).
 * The SIREN rules (length, key, a pasted SIRET reduced to its SIREN) live on
 * the server only: the block sends the digits typed and shows its answer.
 */

export const IND = 'ind';
export const PRO = 'pro';
/* As the server (TaxProfileInput): beyond it the block says so, nothing is cut. */
const LEGAL_NAME_MAX = 255;
const CHECKING = 'Vérification du SIREN…';
const CHECK_DELAY_MS = 200;

const digits = v => String(v ?? '').replace(/\D/g, '');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** French VAT key of a SIREN: (12 + 3 × (SIREN mod 97)) mod 97, on two digits. */
export const vatKey = siren => String((12 + 3 * (Number(siren) % 97)) % 97).padStart(2, '0');

/** « FR31523456788 » from what was typed (upper case, no spaces or dots). */
export const vatOf = v => String(v ?? '').toUpperCase().replace(/[\s.  ]/g, '');

/** Digits grouped by three, as « 523 456 788 00012 » for a pasted SIRET. */
export const fmtSiren = d => [d.slice(0, 3), d.slice(3, 6), d.slice(6, 9), d.slice(9)].filter(Boolean).join(' ');
export const fmtVat = v => { const x = vatOf(v); return [x.slice(0, 2), x.slice(2, 4), x.slice(4)].filter(Boolean).join(' '); };

export function blank(fisc = IND) {
  return { fisc, rs: '', siren: '', tva: '' };
}

/**
 * What to correct, field by field: {rs?, siren?, tva?} (empty when valid).
 * `structureOnly` (a window that only creates structures) asks for the legal
 * name whatever the status. The SIREN is judged by the server's answer for
 * the digits typed (`s.check`, kept by `mount()`); with no answer to be had,
 * the server judges it on submit.
 */
export function validate(s, structureOnly = false) {
  const e = {};
  if (!s.rs.trim() && (structureOnly || s.fisc === PRO)) e.rs = 'La raison sociale est obligatoire.';
  if (s.rs.trim().replace(/\s+/g, ' ').length > LEGAL_NAME_MAX) e.rs = `La raison sociale ne doit pas dépasser ${LEGAL_NAME_MAX} caractères.`;
  if (s.fisc !== PRO) return e;
  const c = s.check;
  if (!c || c.for !== digits(s.siren)) { e.siren = CHECKING; return e; }
  if (c.error) { e.siren = c.error; return e; }
  const t = vatOf(s.tva);
  if (t && c.siren) {
    const m = t.match(/^FR(\d{2})(\d{9})$/);
    if (!m) e.tva = 'N° de TVA invalide : format FR + 2 chiffres + SIREN.';
    else if (m[2] !== c.siren) e.tva = 'N° de TVA : il ne correspond pas au SIREN.';
    else if (m[1] !== vatKey(c.siren)) e.tva = 'N° de TVA invalide : clé incorrecte.';
  }
  return e;
}

/** The fields the server reads (`fisc`, `rs`, `siren`, `tva`). */
export function toPayload(s) {
  const pro = s.fisc === PRO;
  return { fisc: s.fisc, rs: s.rs.trim(), siren: pro ? digits(s.siren) : '', tva: pro ? vatOf(s.tva) : '' };
}

/**
 * Clones the block into `root` and keeps `state` in sync; `onChange()` runs
 * after every change the user makes. Options: `sirenUrl` (GET ?siren= →
 * {siren, error, items: cards}) for the SIREN check and the duplicate-SIREN
 * warning, `onCheck()` (runs when its answer arrives; `onChange` by default),
 * `self` (a client id the warning ignores), `lockFisc` (status not
 * changeable), `hideStatus` (no status selector: the host page sets
 * `state.fisc` itself), `structureOnly` (legal name required whatever the
 * status, e.g. the counter « Professionnel » tab).
 * Returns {showErrors(), refresh(), focus()}.
 */
export function mount(root, state, onChange = () => {}, opts = {}) {
  const tpl = document.getElementById('tplFisc');
  if (!tpl || !root) return { showErrors: () => false, refresh: () => {}, focus: () => {} };
  root.replaceChildren(tpl.content.cloneNode(true));
  const q = sel => root.querySelector(sel);
  const inp = k => q(`[data-ff="${k}"]`);
  const onCheck = opts.onCheck ?? onChange;
  let shown = false, left = false, seq = 0, timer = 0;

  const paint = () => {
    const pro = state.fisc === PRO;
    root.querySelectorAll('.fisc-opt').forEach(b => {
      const on = b.dataset.fv === state.fisc;
      b.classList.toggle('on', on);
      b.setAttribute('aria-checked', String(on));
      b.disabled = !!opts.lockFisc && !on;
    });
    q('.fisc-opts').hidden = !!opts.hideStatus;
    q('[data-pro]').hidden = !pro;
    const rsReq = pro || !!opts.structureOnly;
    q('[data-rs-req]').hidden = !rsReq;
    const e = shown ? validate(state, !!opts.structureOnly) : {};
    ['rs', 'siren', 'tva'].forEach(k => {
      inp(k).classList.toggle('ko', !!e[k]);
      q(`[data-fe="${k}"]`).textContent = e[k] || '';
    });
    const c = state.check, fresh = !!c && c.for === digits(state.siren);
    inp('siren').classList.toggle('ok', pro && fresh && !!c.siren);
    // Once the field is left, the server's verdict shows without waiting for submit.
    if (pro && fresh && c.error && left && !shown) { inp('siren').classList.add('ko'); q('[data-fe="siren"]').textContent = c.error; }
  };

  const values = () => {
    inp('rs').value = state.rs;
    inp('siren').value = fmtSiren(digits(state.siren));
    inp('tva').value = fmtVat(state.tva);
  };

  const duplicatesOf = items => {
    const box = q('[data-dbl]');
    const other = (items || []).find(c => c.id !== opts.self);
    box.hidden = !other;
    box.innerHTML = other ? `<span>Un client porte déjà ce SIREN : <b>${esc(other.rs || other.n)}</b></span><a href="${esc(other.url)}">Ouvrir sa fiche</a>` : '';
  };

  /* Asks the server what the digits typed stand for, and who carries them. */
  const check = () => {
    window.clearTimeout(timer);
    const d = digits(state.siren);
    if (state.fisc !== PRO) { duplicatesOf([]); return; }
    if (state.check?.for === d) return;
    const id = ++seq;
    // No answer to be had: the server judges the SIREN on submit.
    const unknown = () => { if (id !== seq) return; state.check = { for: d, siren: null, error: null }; duplicatesOf([]); paint(); onCheck(); };
    if (!opts.sirenUrl) { unknown(); return; }
    timer = window.setTimeout(() => {
      fetch(`${opts.sirenUrl}?siren=${encodeURIComponent(d)}`, { headers: { Accept: 'application/json' } })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
        .then(r => {
          if (id !== seq) return;
          state.check = { for: d, siren: r.siren ?? null, error: r.error ?? null };
          duplicatesOf(r.items);
          paint();
          onCheck();
        })
        .catch(unknown);
    }, CHECK_DELAY_MS);
  };

  root.addEventListener('click', e => {
    const b = e.target.closest('.fisc-opt');
    if (!b || b.disabled || b.dataset.fv === state.fisc) return;
    state.fisc = b.dataset.fv;
    paint();
    check();
    onChange();
  });
  root.addEventListener('input', e => {
    const k = e.target.dataset.ff;
    if (!k) return;
    if (k === 'rs') state.rs = e.target.value;
    if (k === 'siren') { state.siren = digits(e.target.value); e.target.value = fmtSiren(state.siren); left = false; }
    if (k === 'tva') { state.tva = vatOf(e.target.value); e.target.value = fmtVat(state.tva); }
    onChange();
    // After the host page's own input listeners, which may clear the field's state.
    queueMicrotask(() => { paint(); if (k === 'siren') check(); });
  });
  root.addEventListener('focusout', e => {
    if (e.target.dataset.ff !== 'siren') return;
    left = true;
    paint();
  });

  values();
  paint();
  check();

  return {
    /** Shows every error; true when the block is valid. */
    showErrors() { shown = true; paint(); return !Object.keys(validate(state, !!opts.structureOnly)).length; },
    refresh() { values(); paint(); check(); },
    focus() { (state.fisc === PRO ? inp('rs') : root.querySelector('.fisc-opt.on'))?.focus(); },
  };
}
