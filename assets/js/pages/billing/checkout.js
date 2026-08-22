/**
 * Page module — checkout screen.
 *
 * The only thing that needs a client here is the arithmetic the receptionist
 * would otherwise do in their head: typing a discount has to move the amount
 * the client is about to hand over, before the form is even sent.
 */

let total = 0;

export function init() {
  const totalEl = document.querySelector('[data-checkout-total]');
  total = Number(totalEl?.dataset.checkoutTotal ?? 0);

  document.addEventListener('input', onInput);
  document.addEventListener('submit', onSubmit);
}

export function cleanup() {
  document.removeEventListener('input', onInput);
  document.removeEventListener('submit', onSubmit);
  total = 0;
}

function onInput(e) {
  if (!e.target.matches?.('[data-checkout-discount]')) return;

  const amountField = document.querySelector('[data-checkout-amount]');
  if (!amountField) return;

  const percent = parseAmount(e.target.value);

  if (percent === null || percent < 0 || percent > 100) return;

  const due = Math.max(0, Math.round(total * (1 - percent / 100)));
  amountField.value = (due / 100).toFixed(2).replace('.', ',');
}

/** A double click on "Encaisser" must not take the money twice. */
function onSubmit(e) {
  const form = e.target.closest?.('.ck-form');
  if (!form) return;

  if (form.dataset.submitting === '1') {
    e.preventDefault();
    return;
  }

  form.dataset.submitting = '1';

  // Disabling the button while the submit event is still running cancels the
  // very submission it is meant to protect — Chrome drops a form whose submit
  // button goes disabled mid-event. Let this event finish first.
  window.setTimeout(() => {
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
  }, 0);
}

function parseAmount(raw) {
  const value = String(raw).trim().replace(',', '.');

  if (!/^\d+(\.\d{1,2})?$/.test(value)) return null;

  return Number(value);
}
