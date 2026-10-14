/**
 * Kiveto — assets/js/shell/shell.js
 * ──────────────────────────────────
 * Behaviour of the v-topbar application shell, ported from the shell portion
 * of docs/frontend-theme/kiveto-theme/*-v-topbar*.html: sidebar collapse,
 * clinic switcher, footer drop-ups (« Quoi de neuf », « Aide »), topbar
 * drop-downs (notifications, profile, language), search palette (⌘K),
 * shortcuts modal (?), quick-action keys, toast and dark tooltips.
 *
 * Every listener is delegated at document level and bound once by
 * initShell(): Turbo swaps <body> on each visit and the delegation keeps
 * working on the new one without rebinding.
 *
 * The shared kit helpers of the mock-ups (showToast, toastUndo, ancrer,
 * creerDropdown) are exported for the page modules:
 *   import { showToast, toastUndo, ancrer, creerDropdown } from 'kiveto/shell';
 */

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
const isTyping = (el) => el instanceof Element && (el.matches('input,select,textarea') || el.isContentEditable);

function visit(url) {
  if (window.Turbo && typeof window.Turbo.visit === 'function') window.Turbo.visit(url);
  else window.location.href = url;
}

// ═════════ Toast ═════════

let toastTimer;

function toastEl() {
  return $('body > .toast[data-shell-toast]');
}

/**
 * Shows the shell toast. A function as second argument makes it undoable
 * (same as toastUndo), a string sets the dot tone (success, danger, warning).
 *
 * @param {string} msg
 * @param {Function|string} [undoOrTone]
 * @returns {boolean} false when the page has no shell toast
 */
export function showToast(msg, undoOrTone) {
  if (typeof undoOrTone === 'function') return toastUndo(msg, undoOrTone);

  return render(msg, null, typeof undoOrTone === 'string' ? undoOrTone : '', 2600);
}

/**
 * Toast with « Annuler » (grammar of the mock-ups: everything reversible
 * gets its undo for 5 s).
 *
 * @param {string} msg
 * @param {Function} [onUndo]
 * @param {string} [label]
 */
export function toastUndo(msg, onUndo, label = 'Annuler') {
  return render(msg, onUndo ? { label, onClick: onUndo } : null, '', onUndo ? 5000 : 2600);
}

function render(msg, action, tone, duration) {
  const t = toastEl();
  if (!t) return false;

  const dot = $('.dot', t);
  if (dot) dot.className = 'dot' + (tone ? ' ' + tone : '');

  const out = $('#toastMsg', t);
  out.textContent = msg;

  if (action) {
    const undo = document.createElement('button');
    undo.type = 'button';
    undo.className = 'undo';
    undo.textContent = action.label;
    undo.addEventListener('click', () => {
      action.onClick();
      t.classList.remove('show');
    });
    out.append(' ', undo);
  }

  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), duration);

  return true;
}

// ═════════ Kit helpers ═════════

/** Anchors a `.pop` card next to an element (right, else left, clamped). */
export function ancrer(pop, el) {
  pop.classList.add('open');
  const W = pop.offsetWidth, H = pop.offsetHeight, r = el.getBoundingClientRect();
  let x = r.right + 10;
  if (x + W > window.innerWidth - 8) x = r.left - W - 10;
  if (x < 8) x = Math.min(Math.max(8, r.left), window.innerWidth - W - 8);
  const y = Math.min(Math.max(8, r.top), window.innerHeight - H - 8);
  pop.style.left = x + 'px';
  pop.style.top = y + 'px';
}

const SVG_CHEV = '<svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
const SVG_OK = '<svg class="ok" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>';
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/**
 * Custom dropdown of the kit (`.dd`): options {v, l, c?(colour), sub?}.
 *
 * @returns {{get: Function, set: Function, setOptions: Function}}
 */
export function creerDropdown(cont, options, defaut, onPick, placeholder) {
  if (typeof cont === 'string') cont = document.getElementById(cont);
  let val = defaut;

  function rendreDd() {
    const cur = options.find((o) => o.v === val);
    cont.innerHTML = `<button type="button" class="dd-btn" aria-haspopup="listbox">
        ${cur && cur.c ? `<span class="dot" style="background:${esc(cur.c)}"></span>` : ''}
        ${cur ? `<span>${esc(cur.l)}</span>` : `<span class="ph">${esc(placeholder || 'Sélectionner…')}</span>`}${SVG_CHEV}
      </button>
      <div class="dd-menu scr" role="listbox">${options.map((o) =>
        `<button type="button" class="dd-it${o.v === val ? ' on' : ''}" data-v="${esc(o.v)}" role="option">
           ${o.c ? `<span class="dot" style="background:${esc(o.c)}"></span>` : ''}${esc(o.l)}${o.v === val ? SVG_OK : (o.sub ? `<span class="sub">${esc(o.sub)}</span>` : '')}
         </button>`).join('')}</div>`;
    $('.dd-btn', cont).addEventListener('click', (e) => {
      e.stopPropagation();
      const was = cont.classList.contains('open');
      $$('.dd.open').forEach((d) => d.classList.remove('open'));
      if (!was) cont.classList.add('open');
    });
    $$('.dd-it', cont).forEach((b) => b.addEventListener('click', (e) => {
      e.stopPropagation();
      val = b.dataset.v;
      cont.classList.remove('open');
      rendreDd();
      if (onPick) onPick(val);
    }));
  }

  rendreDd();

  return {
    get: () => val,
    set: (v) => { val = v; cont.classList.remove('open'); rendreDd(); },
    setOptions: (o) => { options = o; rendreDd(); },
  };
}

// ═════════ Popovers of the shell ═════════

function closeTbPops() { $$('.tb-pop').forEach((p) => p.classList.remove('open')); }
function closeFootPops() { $$('.foot-pop').forEach((p) => p.classList.remove('open')); }
function closeClinic() { $('#sbClinique')?.classList.remove('open'); }

function toggleTbPop(pop) {
  const was = pop.classList.contains('open');
  closeTbPops();
  if (!was) pop.classList.add('open');
}

function toggleFootPop(btn, pop, after) {
  const was = pop.classList.contains('open');
  closeFootPops();
  if (was) return;
  pop.classList.add('open');
  const r = btn.getBoundingClientRect();
  pop.style.bottom = (window.innerHeight - r.top + 8) + 'px';
  pop.style.top = 'auto';
  pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pop.offsetWidth - 10)) + 'px';
  if (after) after();
}

function toggleClinic() {
  const sb = $('#sbClinique');
  if (!sb) return;
  sb.classList.toggle('open');
  if (!sb.classList.contains('open')) return;
  const pop = $('#clinPop');
  const r = $('#clinBtn').getBoundingClientRect();
  pop.style.top = (r.bottom + 6) + 'px';
  pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pop.offsetWidth - 10)) + 'px';
}

/** Submits a clinic switch form, with the mock-up's fade of the work area. */
function switchClinic(form) {
  closeClinic();
  $('.main')?.animate([{ opacity: 0.35 }, { opacity: 1 }], { duration: 320, easing: 'ease-out' });
  form.requestSubmit();
}

// « Quoi de neuf »: the dot stays until the member opened the latest notes.
const QDN_KEY = 'kiveto-qdn-lu';

function syncQdnDot() {
  const dot = $('#qdnDot');
  if (!dot) return;
  let seen = null;
  try { seen = window.localStorage.getItem(QDN_KEY); } catch (_) { /* storage unavailable */ }
  dot.classList.toggle('lu', seen === dot.dataset.version);
}

function markQdnRead() {
  const dot = $('#qdnDot');
  if (!dot) return;
  dot.classList.add('lu');
  try { window.localStorage.setItem(QDN_KEY, dot.dataset.version || ''); } catch (_) { /* storage unavailable */ }
}

// ═════════ Search palette (⌘K) ═════════

let srchType = 'all';
let srchTimer;
let srchAbort;

function veil() { return $('#veil'); }

function openPal() {
  const v = veil();
  if (!v) return;
  v.classList.add('open');
  setTimeout(() => $('#palInput')?.focus(), 30);
}

function closePal() {
  const v = veil();
  if (!v || !v.classList.contains('open')) return;
  v.classList.remove('open');
  $('#palInput').value = '';
  srchType = 'all';
  $$('.srch-pill', v).forEach((x, i) => {
    x.classList.toggle('is-active', i === 0);
    x.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
  });
  srchFiltre();
}

function srchFiltre() {
  const v = veil();
  if (!v) return;
  const q = $('#palInput').value.trim();
  const hint = $('#srchHint', v);
  const out = $('#srchResults', v);

  clearTimeout(srchTimer);
  if (srchAbort) srchAbort.abort();

  if (q.length < 2) {
    hint.hidden = false;
    out.innerHTML = '';
    return;
  }

  srchTimer = setTimeout(async () => {
    const url = new URL(v.dataset.searchUrl, window.location.origin);
    url.searchParams.set('q', q);
    url.searchParams.set('mode', 'navigate');
    url.searchParams.set('format', 'palette');
    if (srchType !== 'all') url.searchParams.set('type', srchType);

    srchAbort = new AbortController();
    try {
      const res = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: srchAbort.signal });
      if (!res.ok) return;
      hint.hidden = true;
      out.innerHTML = await res.text();
    } catch (err) {
      if (err.name !== 'AbortError') console.warn('[kiveto] search failed', err);
    }
  }, 200);
}

// ═════════ Tooltips (data-tip) ═════════

let tipTimer;

function tipEl() {
  let tip = $('body > .tip');
  if (!tip) {
    tip = document.createElement('div');
    tip.className = 'tip';
    document.body.appendChild(tip);
  }
  return tip;
}

function onMouseOver(e) {
  const el = e.target instanceof Element ? e.target.closest('[data-tip]') : null;
  const tip = tipEl();
  if (!el) { tip.classList.remove('on'); return; }
  if (el.closest('.sbar') && !document.body.classList.contains('sb-mini')) return;
  clearTimeout(tipTimer);
  tipTimer = setTimeout(() => {
    tip.textContent = el.dataset.tip;
    const pos = el.dataset.tipPos || 'top';
    const r = el.getBoundingClientRect(), w = tip.offsetWidth, ht = tip.offsetHeight;
    let x, y, cls;
    if (pos === 'right') {
      x = r.right + 10; y = Math.max(8, Math.min(r.top + r.height / 2 - ht / 2, window.innerHeight - ht - 8)); cls = 'right';
    } else {
      x = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, window.innerWidth - w - 8));
      y = r.top - ht - 9; cls = 'top';
      if (y < 8) { y = r.bottom + 9; cls = 'bottom'; }
    }
    tip.className = 'tip pos-' + cls;
    tip.style.left = x + 'px';
    tip.style.top = y + 'px';
    tip.classList.add('on');
  }, 350);
}

function onMouseOut(e) {
  if (e.target instanceof Element && e.target.closest('[data-tip]')) {
    clearTimeout(tipTimer);
    tipEl().classList.remove('on');
  }
}

// ═════════ Delegated handlers ═════════

function onClick(e) {
  const t = e.target instanceof Element ? e.target : null;
  if (!t) return;

  // Tooltips never outlive a click.
  clearTimeout(tipTimer);
  $('body > .tip')?.classList.remove('on');

  // ── Sidebar collapse
  if (t.closest('#sbCollapse')) { document.body.classList.toggle('sb-mini'); return; }

  // ── Clinic switcher: the star sets the default clinic, the row switches
  const star = t.closest('#clinPop .cs-star');
  if (star) {
    e.preventDefault();
    e.stopPropagation();
    const form = document.getElementById(star.dataset.defaultForm || '');
    if (form && !star.classList.contains('on')) form.requestSubmit();
    return;
  }
  const switchBtn = t.closest('#clinPop .cs-item');
  if (switchBtn && switchBtn.form) {
    e.preventDefault();
    switchClinic(switchBtn.form);
    return;
  }
  if (t.closest('#clinBtn')) { e.stopPropagation(); toggleClinic(); return; }
  if (!t.closest('.sb-clinique')) closeClinic();

  // ── Topbar drop-downs
  if (t.closest('#btnNotif')) { toggleTbPop($('#popNotif')); return; }
  if (t.closest('#btnProfil')) { toggleTbPop($('#popProfil')); return; }

  const filtre = t.closest('.nf-filtre button');
  if (filtre) {
    $$('.nf-filtre button').forEach((x) => x.classList.remove('on'));
    filtre.classList.add('on');
    const f = filtre.dataset.f;
    $$('#nfListe .nf').forEach((n) => { n.hidden = !(f === 'tout' || (f === 'tache' ? n.dataset.k === 'tache' : n.dataset.k !== 'tache')); });
    return;
  }
  if (t.closest('#nfToutLu')) {
    $$('#nfListe .nf').forEach((n) => n.classList.add('lu'));
    $('#btnNotif .tb-dot')?.style.setProperty('display', 'none');
    showToast('Toutes les notifications marquées lues');
    return;
  }

  const langRow = t.closest('#langRow');
  if (langRow) {
    const open = $('#langListe').classList.toggle('open');
    langRow.setAttribute('aria-expanded', String(open));
    return;
  }
  const langItem = t.closest('.lang-item');
  if (langItem) {
    $('#langListe').classList.remove('open');
    $('#langRow')?.setAttribute('aria-expanded', 'false');
    showToast('Langue : ' + langItem.dataset.label);
    return;
  }

  if (!t.closest('.tb-pop')) closeTbPops();

  // ── Footer drop-ups
  if (t.closest('#btnQdn')) { toggleFootPop(t.closest('#btnQdn'), $('#popQdn'), markQdnRead); return; }
  if (t.closest('#btnAide')) { toggleFootPop(t.closest('#btnAide'), $('#popAide')); return; }
  if (t.closest('#btnRacc')) { closeFootPops(); $('#veilRacc')?.classList.add('open'); return; }
  if (!t.closest('.sb-foot')) closeFootPops();

  // ── Shortcuts modal
  const racc = $('#veilRacc');
  if (racc && (t.closest('#raccClose') || t === racc)) { racc.classList.remove('open'); return; }

  // ── Search palette
  if (t.closest('#openPalette')) { openPal(); return; }
  const v = veil();
  if (v) {
    if (t === v || t.closest('#srchClose') || t.closest('#srchFermer')) { closePal(); return; }
    const pill = t.closest('.srch-pill');
    if (pill && v.contains(pill)) {
      if (pill.getAttribute('aria-disabled') === 'true') {
        showToast(pill.dataset.toast || 'Bientôt disponible');
        return;
      }
      $$('.srch-pill', v).forEach((x) => { x.classList.remove('is-active'); x.setAttribute('aria-selected', 'false'); });
      pill.classList.add('is-active');
      pill.setAttribute('aria-selected', 'true');
      srchType = pill.dataset.type;
      srchFiltre();
      return;
    }
    if (t.closest('.srch-res')) closePal();
  }

  // ── Plain informational toasts (features announced but not open yet)
  const toaster = t.closest('[data-toast]');
  if (toaster) {
    e.preventDefault();
    showToast(toaster.dataset.toast);
  }
}

function anyOverlayOpen() {
  return Boolean($('.veil.open, .mveil.open, .racc-veil.open, .modal-overlay.is-open, .modal-overlay.open'));
}

function onKeydown(e) {
  // ⌘K / Ctrl+K — search palette
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    if (!veil()) return;
    e.preventDefault();
    veil().classList.contains('open') ? closePal() : openPal();
    return;
  }

  // ⌘1-9 — switch to the n-th clinic directly
  if ((e.metaKey || e.ctrlKey) && /^[1-9]$/.test(e.key)) {
    const form = $(`#clinPop form[data-clinic-index="${Number(e.key) - 1}"]`);
    if (form) { e.preventDefault(); switchClinic(form); }
    else if ($(`#clinPop [data-clinic-current="${Number(e.key) - 1}"]`)) e.preventDefault();
    return;
  }

  if (e.key === 'Escape') {
    closePal();
    closeClinic();
    closeTbPops();
    closeFootPops();
    $('#veilRacc')?.classList.remove('open');
    return;
  }

  if (isTyping(e.target)) return;

  // ? — shortcuts modal
  if (e.key === '?') {
    const racc = $('#veilRacc');
    if (racc) { e.preventDefault(); racc.classList.toggle('open'); }
    return;
  }

  // [ — collapse the sidebar
  if (e.key === '[' && !$('.veil.open, .racc-veil.open')) {
    if (!$('.sbar')) return;
    e.preventDefault();
    document.body.classList.toggle('sb-mini');
    return;
  }

  // R / A / E — quick actions (new appointment, arrival, checkout)
  if (e.metaKey || e.ctrlKey || e.altKey || anyOverlayOpen()) return;
  const racc = $('#veilRacc');
  const url = racc?.dataset['shortcut' + e.key.toUpperCase()];
  if (url && /^[rae]$/i.test(e.key)) {
    e.preventDefault();
    visit(url);
  }
}

function onInput(e) {
  if (e.target instanceof Element && e.target.id === 'palInput') srchFiltre();
}

let bound = false;

/**
 * Binds the shell once (document-level delegation) and syncs the per-page
 * state; call on every turbo:load.
 */
export function initShell() {
  syncQdnDot();
  if (bound) return;
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('input', onInput);
  document.addEventListener('mouseover', onMouseOver);
  document.addEventListener('mouseout', onMouseOut);
  // Never let Turbo snapshot an open popover, palette or toast.
  document.addEventListener('turbo:before-cache', () => {
    closePal();
    closeClinic();
    closeTbPops();
    closeFootPops();
    $('#veilRacc')?.classList.remove('open');
    toastEl()?.classList.remove('show');
    $('body > .tip')?.classList.remove('on');
  });
  document.addEventListener('click', (e) => {
    if (!(e.target instanceof Element) || !e.target.closest('.dd')) $$('.dd.open').forEach((d) => d.classList.remove('open'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') $$('.dd.open').forEach((d) => d.classList.remove('open'));
  });
}
