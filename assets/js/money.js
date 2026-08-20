/**
 * Kiveto — assets/js/money.js
 * ─────────────────────────────────
 * Shared money formatter fed integer minor units.
 *
 * Usage:
 *   import { fmtMoney } from 'kiveto/money';
 *
 *   fmtMoney(1480)           // "14,80 €"
 *   fmtMoney(123456, 'EUR')  // "1 234,56 €"
 *
 * French-style formatting: comma decimal separator, narrow no-break space as
 * thousands separator, currency symbol suffixed with a no-break space.
 * Mirrors the PHP MoneyFormatRuntime Twig filter — which is registry-driven
 * and is the source of truth; this helper only covers the 2-decimal
 * currencies a clinic UI can encounter today. Extend SYMBOLS (and add a
 * decimals map) if a 0/3-decimal currency ever reaches the frontend.
 */

const SYMBOLS = {
  EUR: '€',
  CHF: 'CHF',
  USD: '$',
  GBP: '£',
};

const THIN_SPACE = '\u202f';
const NBSP = '\u00a0';

export function fmtMoney(minorUnits, currency = 'EUR') {
  if (!Number.isInteger(minorUnits)) {
    return '';
  }

  const negative = minorUnits < 0;
  const absolute = Math.abs(minorUnits);

  const integerPart = String(Math.trunc(absolute / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, THIN_SPACE);
  const fractionalPart = String(absolute % 100).padStart(2, '0');

  const symbol = SYMBOLS[currency] ?? currency;

  return `${negative ? '-' : ''}${integerPart},${fractionalPart}${NBSP}${symbol}`;
}
