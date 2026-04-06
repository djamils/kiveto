/**
 * Page module — Dashboard
 * Loaded by app.js dispatcher on turbo:load.
 */

let clockInterval = null;

/**
 * Update the dashboard clock and date display.
 */
function tickClock() {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2, '0');
  const mm = String(now.getMinutes()).padStart(2, '0');
  const el = document.getElementById('dash-clock');
  const de = document.getElementById('dash-date');
  if (el) el.textContent = hh + ':' + mm;
  if (de) de.textContent = now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}

export function init() {
  // Start the dashboard clock
  tickClock();
  clockInterval = setInterval(tickClock, 10000);
}

export function cleanup() {
  // Clear the clock interval
  if (clockInterval) {
    clearInterval(clockInterval);
    clockInterval = null;
  }
}
