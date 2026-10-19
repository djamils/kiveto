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
const SEARCH_DELAY_MS = 300;
const SEARCH_MIN_LETTERS = 3;

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
 * status, e.g. the counter « Professionnel » tab), `registryUrl` (GET ?q= →
 * {available, reason, items: CompanyHit[]}: the company registry, searched
 * from the legal name when the block asks for one) and `onCompany(hit)` (runs
 * once a registry hit filled the block, e.g. to take its address).
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
  let hits = [], hl = -1, searchTimer = 0, searchAbort = null;

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

  /* ── Company registry, searched from the legal name ── */
  const sug = q('[data-sug]'), hint = q('[data-hint]');
  const searching = () => !!opts.registryUrl && (state.fisc === PRO || !!opts.structureOnly);
  const closeHits = () => { sug.hidden = true; inp('rs').setAttribute('aria-expanded', 'false'); inp('rs').removeAttribute('aria-activedescendant'); };
  const showHits = (note = '') => {
    sug.innerHTML = note ? `<div class="fisc-sug-note">${esc(note)}</div>` : hits.map((h, i) => `<button type="button" role="option" id="fiscHit${i}" data-hit="${i}" aria-selected="${i === hl}"${i === hl ? ' class="hl"' : ''}><span>${esc(h.n)}<small>${esc([h.forme, h.ets[0]?.a.split(', ').pop()].filter(Boolean).join(' · '))}${h.cesse ? ' · radiée' : ''}</small></span><code>${esc(fmtSiren(h.siren))}</code></button>`).join('');
    sug.hidden = !note && !hits.length;
    inp('rs').setAttribute('aria-expanded', String(!sug.hidden));
    if (hl >= 0) inp('rs').setAttribute('aria-activedescendant', `fiscHit${hl}`); else inp('rs').removeAttribute('aria-activedescendant');
  };
  const search = text => {
    window.clearTimeout(searchTimer);
    const letters = text.normalize('NFD').replace(/[\u0300-\u036f\d]/g, '').trim(), d = digits(text);
    if (!searching() || (letters.length < SEARCH_MIN_LETTERS && d.length !== 9 && d.length !== 14)) { searchAbort?.abort(); hits = []; closeHits(); return; }
    searchTimer = window.setTimeout(() => {
      searchAbort?.abort();
      const ctl = searchAbort = new AbortController();
      fetch(`${opts.registryUrl}?${new URLSearchParams({ q: text })}`, { headers: { Accept: 'application/json' }, signal: ctl.signal })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
        .then(r => {
          if (ctl.signal.aborted) return;
          // Throttled: the last hits stay; down: typing by hand goes on.
          if (!r.available) { if (r.reason !== 'throttled') { hits = []; showHits('Recherche indisponible, saisie manuelle.'); } return; }
          hits = r.items;
          hl = -1;
          showHits(hits.length ? '' : 'Aucune entreprise trouvée : saisissez-la à la main.');
        })
        .catch(err => { if (err.name !== 'AbortError') { hits = []; showHits('Recherche indisponible, saisie manuelle.'); } });
    }, SEARCH_DELAY_MS);
  };
  const pick = h => {
    closeHits();
    hits = [];
    state.rs = h.n;
    if (state.fisc === PRO || !opts.structureOnly) {
      state.siren = h.siren;
      state.tva = h.ass ? (h.tva || '') : '';
    }
    hint.hidden = false;
    hint.textContent = h.ass
      ? (state.tva ? `N° de TVA proposé ${fmtVat(state.tva)} : à confirmer — certaines entreprises n'ont pas de n° de TVA.` : '')
      : 'Structure non assujettie ? Une association sans activité lucrative ne facture généralement pas la TVA : choisissez alors « Particulier ou structure non assujettie ».';
    hint.hidden = !hint.textContent;
    values();
    paint();
    check();
    onChange();
    opts.onCompany?.(h);
  };
  root.addEventListener('mousedown', e => {
    const b = e.target.closest('[data-hit]');
    if (!b) return;
    e.preventDefault();
    const h = hits[+b.dataset.hit];
    if (h) pick(h);
  });
  inp('rs').setAttribute('role', 'combobox');
  inp('rs').setAttribute('aria-autocomplete', 'list');
  inp('rs').setAttribute('aria-expanded', 'false');
  root.addEventListener('keydown', e => {
    if (e.target.dataset.ff !== 'rs' || sug.hidden) return;
    const n = hits.length;
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && n) {
      e.preventDefault();
      hl = e.key === 'ArrowDown' ? (hl + 1) % n : (hl - 1 + n) % n;
      showHits();
    } else if (e.key === 'Enter' && hl >= 0 && n) {
      e.preventDefault();
      pick(hits[hl]);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      closeHits();
    }
  });
  root.addEventListener('focusout', e => { if (e.target.dataset.ff === 'rs') window.setTimeout(closeHits, 150); });

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
    if (k === 'rs') { state.rs = e.target.value; hint.hidden = true; search(e.target.value); }
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
