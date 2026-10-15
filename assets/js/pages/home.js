/**
 * Page module — Clinic dashboard (dashboard-v-topbar_9 mock-up, with its
 * -busy_3 and -calme_3 states). Loaded by the app.js dispatcher on turbo:load.
 *
 * - the now-line rides the « Fil du jour » at the clinic's current time and
 *   moves with it; the timeline opens scrolled to it;
 * - the appointments already seen fold under « N rendez-vous terminés »,
 *   which shows or hides them;
 * - a click on a timeline row or a flux card opens its quick look
 *   (« Aperçu rapide ») instead of leaving the page;
 * - « Pour moi / Toutes » filters the « À traiter » pile by audience.
 *
 * Listeners are bound in init() and released in cleanup(), so a Turbo cache
 * restore never doubles them.
 */

let controller = null;
let clockTimer = null;
let veil = null;

const root = () => document.querySelector('.dash');

/** Minutes since midnight and « HH:MM » in the clinic's time zone. */
function clinicNow(timeZone) {
  let label;

  try {
    label = new Intl.DateTimeFormat('fr-FR', { timeZone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date());
  } catch {
    const now = new Date();
    label = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
  }

  const [h, m] = label.split(':').map(Number);

  return { minutes: h * 60 + m, label };
}

/**
 * Places the now-line among the visible slots: inside the slot running now
 * (in proportion), at the bottom of the last slot started, or just above the
 * first one before the day starts.
 */
function placeNowLine() {
  const dash = root();
  const line = document.getElementById('nowLine');
  const tl = document.getElementById('timeline');
  if (!dash || !line || !tl) return;

  const slots = [...tl.querySelectorAll('.slot')].filter(slot => slot.offsetParent);
  if (!slots.length) return;

  const { minutes, label } = clinicNow(dash.dataset.tz || undefined);
  line.dataset.h = label;

  let top = slots[0].offsetTop - 4;

  for (const slot of slots) {
    const start = Number(slot.dataset.min);
    const end = Math.max(start, Number(slot.dataset.end));

    if (minutes < start) break;

    const span = end - start;
    top = minutes >= end || span === 0
      ? slot.offsetTop + slot.offsetHeight
      : slot.offsetTop + slot.offsetHeight * ((minutes - start) / span);
  }

  line.style.top = Math.round(top) + 'px';
}

/** « Pour moi » keeps the tasks for everyone, for the member's group, or assigned to them. */
function filterTasks(mode) {
  const pile = document.getElementById('tkPile');
  if (!pile) return;

  const audiences = (pile.dataset.audiences || 'clinique').split(' ');
  const me = pile.dataset.me || '';
  let visibles = 0;

  pile.querySelectorAll('.tk').forEach(tk => {
    const assignee = tk.dataset.assignee;
    const mine = audiences.includes(tk.dataset.vis) && (!assignee || assignee === me);
    const show = mode === 'tout' || mine;
    tk.hidden = !show;
    if (show) visibles++;
  });

  pile.querySelectorAll('.tk-grp').forEach(grp => {
    grp.hidden = !pile.querySelector(`.tk[data-grp="${grp.dataset.grp}"]:not([hidden])`);
  });

  // An empty pile gives its room to the « Tout est traité » state.
  pile.hidden = visibles === 0;

  const count = document.getElementById('tkN');
  if (count) count.textContent = String(visibles);
  document.getElementById('tkVide')?.classList.toggle('on', visibles === 0);
}

/* ═════════ Aperçu rapide ═════════ */

const closeQuickLook = () => veil?.classList.remove('on');

function openQuickLook(id) {
  const tpl = document.getElementById(id);
  if (!tpl || !veil) return;

  const qm = veil.firstChild;
  qm.replaceChildren(tpl.content.cloneNode(true));
  qm.querySelector('.x')?.addEventListener('click', closeQuickLook);
  veil.classList.add('on');
  setTimeout(() => qm.querySelector('.qm-f .pri, .qm-f a')?.focus(), 60);
}

export function init() {
  if (!root()) return;

  controller = new AbortController();
  const { signal } = controller;

  const tl = document.getElementById('timeline');
  const now = document.getElementById('nowLine');

  placeNowLine();
  if (tl && now) requestAnimationFrame(() => { tl.scrollTop = Math.max(0, now.offsetTop - tl.clientHeight * 0.3); });
  clockTimer = setInterval(placeNowLine, 30000);
  window.addEventListener('resize', placeNowLine, { signal });

  filterTasks('moi');

  veil = document.createElement('div');
  veil.className = 'qm-veil';
  veil.innerHTML = '<div class="qm" role="dialog" aria-modal="true"></div>';
  document.body.appendChild(veil);
  veil.addEventListener('mousedown', e => { if (e.target === veil) closeQuickLook(); }, { signal });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && veil?.classList.contains('on')) { e.stopPropagation(); closeQuickLook(); }
  }, { signal, capture: true });

  document.addEventListener('click', e => {
    const filter = e.target.closest('.dash .tk-filtre button');
    if (filter) {
      filter.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === filter));
      filterTasks(filter.dataset.f);
      return;
    }

    const folded = e.target.closest('.dash .tl-faits');
    if (folded && tl) {
      const open = tl.classList.toggle('passes-on');
      folded.setAttribute('aria-expanded', String(open));
      folded.querySelector('.tog').textContent = open ? 'Masquer' : 'Afficher';
      placeNowLine();
      if (open) tl.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    const card = e.target.closest('.dash .ambu[data-qm]');
    if (card) {
      e.preventDefault();
      openQuickLook(card.dataset.qm);
      return;
    }

    if (e.target.closest('.dash .rdv .mini-btn, .dash .rdv form')) return;
    const row = e.target.closest('.dash .rdv[data-qm]');
    if (row) openQuickLook(row.dataset.qm);
  }, { signal });
}

export function cleanup() {
  controller?.abort();
  controller = null;
  veil?.remove();
  veil = null;
  if (clockTimer) {
    clearInterval(clockTimer);
    clockTimer = null;
  }
}
