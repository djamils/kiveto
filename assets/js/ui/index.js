/**
 * Kiveto UI Kit — assets/js/ui/index.js
 * ────────────────────────────────────────
 * Single entry point for the JS kit.
 *
 * Bundled usage:
 *   import { drawer, modal, toast, popover, tabs } from 'kiveto/ui';
 *
 * Individual usage (optimal tree-shaking):
 *   import { drawer } from 'kiveto/drawer';
 *   import { toast }  from 'kiveto/toast';
 *
 * Global initialization (in app.js):
 *   import { initUI } from 'kiveto/ui';
 *   initUI();
 */

export { drawer }  from 'kiveto/drawer';
export { modal }   from 'kiveto/modal';
export { toast }   from 'kiveto/toast';
export { popover } from 'kiveto/popover';
export { tabs }    from 'kiveto/tabs';

/**
 * Global initialization of the kit.
 * Call once in app.js.
 *
 * - Activates declarative tabs [data-tabs]
 * - Activates declarative popovers [data-popover-anchor]
 * - Adds global Escape shortcut to close overlays
 */
export function initUI() {
  import('kiveto/tabs').then(({ tabs }) => tabs.init());
  import('kiveto/popover').then(({ popover }) => popover.init());
  // Side-effect import: registers declarative [data-modal-open] / [data-modal-close] listeners
  import('kiveto/modal');
}
