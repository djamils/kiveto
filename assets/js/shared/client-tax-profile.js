/**
 * Shared tax-profile block (« profil fiscal »): the two-option status
 * selector, the legal name and — for a VAT-liable professional — the SIREN
 * and the French VAT number. One module for every window that creates or
 * edits a client (counter « Professionnel » tab, directory « Nouveau client »,
 * client sheet card); the markup comes from the `#tplFisc` template
 * (templates/clinic/clients/_tax_profile_block.html.twig) and the server
 * checks the same rules again (TaxProfileInput, Siren, FrenchVatNumber).
 */

export const IND = 'ind';
export const PRO = 'pro';

const digits = v => String(v ?? '').replace(/\D/g, '');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** Luhn check, as the SIREN typo guard (not a norm rule). */
export const luhn = d => {
  let t = 0;
  [...d].reverse().forEach((c, i) => { let n = +c; if (i % 2) { n *= 2; if (n > 9) n -= 9; } t += n; });
  return t % 10 === 0;
};

/** French VAT key of a SIREN: (12 + 3 × (SIREN mod 97)) mod 97, on two digits. */
export const vatKey = siren => String((12 + 3 * (Number(siren) % 97)) % 97).padStart(2, '0');

/** The 9 digits typed, a pasted 14-digit SIRET being reduced to its SIREN. */
export const sirenOf = v => digits(v).slice(0, 9);

/** « FR31523456788 » from what was typed (upper case, no spaces or dots). */
export const vatOf = v => String(v ?? '').toUpperCase().replace(/[\s.  ]/g, '');

export const fmtSiren = d => [d.slice(0, 3), d.slice(3, 6), d.slice(6, 9)].filter(Boolean).join(' ');
export const fmtVat = v => { const x = vatOf(v); return [x.slice(0, 2), x.slice(2, 4), x.slice(4, 13)].filter(Boolean).join(' '); };

export const sirenOk = d => /^\d{9}$/.test(d) && luhn(d);

export function blank(fisc = IND) {
  return { fisc, rs: '', siren: '', tva: '' };
}

/**
 * What to correct, field by field: {rs?, siren?, tva?} (empty when valid).
 * `structureOnly` (a window that only creates structures) asks for the legal
 * name whatever the status.
 */
export function validate(s, structureOnly = false) {
  const e = {};
  if (!s.rs.trim() && (structureOnly || s.fisc === PRO)) e.rs = 'La raison sociale est obligatoire.';
  if (s.fisc !== PRO) return e;
  const d = sirenOf(s.siren);
  if (!/^\d{9}$/.test(d)) { e.siren = 'SIREN invalide : 9 chiffres attendus.'; return e; }
  if (!luhn(d)) { e.siren = 'SIREN invalide : clé de contrôle incorrecte.'; return e; }
  const t = vatOf(s.tva);
  if (t) {
    const m = t.match(/^FR(\d{2})(\d{9})$/);
    if (!m) e.tva = 'N° de TVA invalide : format FR + 2 chiffres + SIREN.';
    else if (m[2] !== d) e.tva = 'N° de TVA : il ne correspond pas au SIREN.';
    else if (m[1] !== vatKey(d)) e.tva = 'N° de TVA invalide : clé incorrecte.';
  }
  return e;
}

/** The fields the server reads (`fisc`, `rs`, `siren`, `tva`). */
export function toPayload(s) {
  const pro = s.fisc === PRO;
  return { fisc: s.fisc, rs: s.rs.trim(), siren: pro ? sirenOf(s.siren) : '', tva: pro ? vatOf(s.tva) : '' };
}

/**
 * Clones the block into `root` and keeps `state` in sync; `onChange()` runs
 * after every change. Options: `dblUrl` (GET ?siren= → {items: cards}) for
 * the duplicate-SIREN warning, `self` (a client id the warning ignores),
 * `lockFisc` (status not changeable), `structureOnly` (legal name required
 * whatever the status, e.g. the counter « Professionnel » tab).
 * Returns {showErrors(), refresh(), focus()}.
 */
export function mount(root, state, onChange = () => {}, opts = {}) {
  const tpl = document.getElementById('tplFisc');
  if (!tpl || !root) return { showErrors: () => false, refresh: () => {}, focus: () => {} };
  root.replaceChildren(tpl.content.cloneNode(true));
  const q = sel => root.querySelector(sel);
  const inp = k => q(`[data-ff="${k}"]`);
  let shown = false, dblSeq = 0, dblFor = '';

  const paint = () => {
    const pro = state.fisc === PRO;
    root.querySelectorAll('.fisc-opt').forEach(b => {
      const on = b.dataset.fv === state.fisc;
      b.classList.toggle('on', on);
      b.setAttribute('aria-checked', String(on));
      b.disabled = !!opts.lockFisc && !on;
    });
    q('[data-pro]').hidden = !pro;
    const rsReq = pro || !!opts.structureOnly;
    q('[data-rs-opt]').hidden = rsReq;
    q('[data-rs-req]').hidden = !rsReq;
    const e = shown ? validate(state, !!opts.structureOnly) : {};
    ['rs', 'siren', 'tva'].forEach(k => {
      inp(k).classList.toggle('ko', !!e[k]);
      q(`[data-fe="${k}"]`).textContent = e[k] || '';
    });
    const d = sirenOf(state.siren);
    inp('siren').classList.toggle('ok', pro && sirenOk(d));
    const live = pro && d.length === 9 && !luhn(d);
    if (live && !shown) { inp('siren').classList.add('ko'); q('[data-fe="siren"]').textContent = 'SIREN invalide : clé de contrôle incorrecte.'; }
  };

  const values = () => {
    inp('rs').value = state.rs;
    inp('siren').value = fmtSiren(sirenOf(state.siren));
    inp('tva').value = fmtVat(state.tva);
  };

  const duplicates = () => {
    const box = q('[data-dbl]'), d = sirenOf(state.siren);
    if (!opts.dblUrl || state.fisc !== PRO || !sirenOk(d)) { box.hidden = true; dblFor = ''; return; }
    if (d === dblFor) return;
    dblFor = d;
    const id = ++dblSeq;
    fetch(`${opts.dblUrl}?siren=${d}`, { headers: { Accept: 'application/json' } })
      .then(r => (r.ok ? r.json() : { items: [] }))
      .then(r => {
        if (id !== dblSeq) return;
        const other = (r.items || []).find(c => c.id !== opts.self);
        box.hidden = !other;
        box.innerHTML = other ? `<span>Un client porte déjà ce SIREN : <b>${esc(other.rs || other.n)}</b></span><a href="${esc(other.url)}">Ouvrir sa fiche</a>` : '';
      })
      .catch(() => { if (id === dblSeq) box.hidden = true; });
  };

  root.addEventListener('click', e => {
    const b = e.target.closest('.fisc-opt');
    if (!b || b.disabled || b.dataset.fv === state.fisc) return;
    state.fisc = b.dataset.fv;
    paint();
    duplicates();
    onChange();
  });
  root.addEventListener('input', e => {
    const k = e.target.dataset.ff;
    if (!k) return;
    if (k === 'rs') state.rs = e.target.value;
    if (k === 'siren') { state.siren = sirenOf(e.target.value); e.target.value = fmtSiren(state.siren); }
    if (k === 'tva') { state.tva = vatOf(e.target.value).slice(0, 13); e.target.value = fmtVat(state.tva); }
    onChange();
    // After the host page's own input listeners, which may clear the field's state.
    queueMicrotask(() => { paint(); if (k === 'siren') duplicates(); });
  });

  values();
  paint();
  duplicates();

  return {
    /** Shows every error; true when the block is valid. */
    showErrors() { shown = true; paint(); return !Object.keys(validate(state, !!opts.structureOnly)).length; },
    refresh() { values(); paint(); duplicates(); },
    focus() { (state.fisc === PRO ? inp('rs') : root.querySelector('.fisc-opt.on'))?.focus(); },
  };
}
