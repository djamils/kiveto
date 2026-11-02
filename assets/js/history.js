/**
 * Scoped back / forward for page modules that write their own history
 * entries with pushState (« Demandes » opening a sheet).
 *
 * A module registers one handler while it is on screen and unregisters it
 * in its cleanup(). Every popstate goes to the registered handler first: it
 * returns true when the entry is one it wrote and it replayed it in place,
 * and only then is the event kept from Turbo. Without a handler, or when it
 * returns false, nothing is done here: the browser and Turbo behave as
 * usual.
 *
 * The window listener is installed when this module is evaluated, which
 * app.js does first, before Turbo starts: listeners on `window` run in the
 * order they were added (capture or not), so ours precedes Turbo's and can
 * stop it.
 */
let handler = null;

/**
 * @param {(event: PopStateEvent) => boolean} fn
 * @returns {() => void} unregisters fn (a later registration is kept)
 */
export function registerHistoryHandler(fn) {
  handler = fn;
  return () => {
    if (handler === fn) handler = null;
  };
}

window.addEventListener('popstate', (event) => {
  if (handler !== null && handler(event) === true) event.stopImmediatePropagation();
});
