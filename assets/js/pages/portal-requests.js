/**
 * Page module — « Demandes Portal » (queue and detail).
 * Loaded by the app.js dispatcher on turbo:load.
 *
 * - queue: a whole row opens its request;
 * - detail: the prescription select only applies to « Traiter », and the
 *   message counter follows the 280 characters the owner may receive.
 *
 * The page is rendered by Twig (owner text autoescaped); this module only
 * reads values and sets textContent. Listeners are released in cleanup().
 */
let ac = null;

export function init() {
  const root = document.querySelector('.prq[data-page]');
  if (!root || ac) return;
  ac = new AbortController();
  const { signal } = ac;

  root.querySelectorAll('tr[data-href]').forEach((row) => {
    row.addEventListener('click', (e) => {
      if (e.target instanceof Element && e.target.closest('a')) return;
      const href = row.dataset.href;
      if (window.Turbo?.visit) window.Turbo.visit(href); else window.location.href = href;
    }, { signal });
  });

  const form = root.querySelector('#prqForm');
  if (!form) return;

  const sync = () => {
    const outcome = form.querySelector('input[name="outcome"]:checked')?.value;
    form.querySelectorAll('[data-outcome-only]').forEach((el) => {
      const shown = el.dataset.outcomeOnly === outcome;
      el.hidden = !shown;
      el.querySelectorAll('select').forEach((s) => { s.disabled = !shown; });
    });
  };
  form.querySelectorAll('input[name="outcome"]').forEach((r) => r.addEventListener('change', sync, { signal }));
  sync();

  const message = form.querySelector('textarea[name="message"]');
  const count = form.querySelector('.prq-count');
  if (message && count) {
    const max = Number(count.dataset.max) || 280;
    const update = () => { count.textContent = `${message.value.length} / ${max}`; };
    message.addEventListener('input', update, { signal });
    update();
  }

  form.addEventListener('submit', () => {
    form.querySelector('button[type="submit"]')?.setAttribute('disabled', '');
  }, { signal });
}

export function cleanup() {
  ac?.abort();
  ac = null;
}
