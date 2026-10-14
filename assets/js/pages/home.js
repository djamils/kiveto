/**
 * Page module — Clinic dashboard (dashboard-v-topbar-calme_2 mock-up).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * - the now-line rides the « Fil du jour » at the clinic's current time and
 *   moves with it (the mock-up simulated 10:42);
 * - « Pour moi / Toutes » filters the « À traiter » pile by audience;
 * - a patient card of the flux announces the flux page before opening it.
 *
 * Listeners are bound in init() and released in cleanup(), so a Turbo cache
 * restore never doubles them.
 */
import { showToast } from 'kiveto/shell';

let controller = null;
let clockTimer = null;

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
 * Places the now-line: inside the slot running now (in proportion), at the
 * bottom of the last slot started, or on top of the first one before the day
 * starts.
 */
function placeNowLine() {
  const dash = root();
  const line = document.getElementById('nowLine');
  const tl = document.getElementById('timeline');
  if (!dash || !line || !tl) return;

  const slots = [...tl.querySelectorAll('.slot')];
  if (!slots.length) return;

  const { minutes, label } = clinicNow(dash.dataset.tz || undefined);
  line.dataset.h = label;

  let top = slots[0].offsetTop;

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

  const count = document.getElementById('tkN');
  if (count) count.textContent = String(visibles);
  document.getElementById('tkVide')?.classList.toggle('on', visibles === 0);
}

export function init() {
  if (!root()) return;

  controller = new AbortController();
  const { signal } = controller;

  placeNowLine();
  clockTimer = setInterval(placeNowLine, 30000);
  window.addEventListener('resize', placeNowLine, { signal });

  filterTasks('moi');

  document.addEventListener('click', e => {
    const filter = e.target.closest('.dash .tk-filtre button');
    if (filter) {
      filter.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === filter));
      filterTasks(filter.dataset.f);
      return;
    }

    const card = e.target.closest('.dash a.ambu');
    if (card && !e.metaKey && !e.ctrlKey && !e.shiftKey && e.button === 0) {
      e.preventDefault();
      showToast('Flux ambulatoire — ouvert sur ' + card.dataset.nom);
      setTimeout(() => {
        if (window.Turbo) window.Turbo.visit(card.href);
        else location.href = card.href;
      }, 450);
    }
  }, { signal });
}

export function cleanup() {
  controller?.abort();
  controller = null;
  if (clockTimer) {
    clearInterval(clockTimer);
    clockTimer = null;
  }
}
