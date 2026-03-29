/**
 * Kiveto UI Kit — assets/js/ui/index.js
 * ────────────────────────────────────────
 * Single entry point for the JS kit.
 *
 * Bundled usage:
 *   import { drawer, modal, toast, popover, tabs } from 'vs/ui';
 *
 * Individual usage (optimal tree-shaking):
 *   import { drawer } from 'vs/drawer';
 *   import { toast }  from 'vs/toast';
 *
 * Global initialization (in app.js):
 *   import { initUI } from 'vs/ui';
 *   initUI();
 */

export { drawer }  from 'vs/drawer';
export { modal }   from 'vs/modal';
export { toast }   from 'vs/toast';
export { popover } from 'vs/popover';
export { tabs }    from 'vs/tabs';

/**
 * Global initialization of the kit.
 * Call once in app.js.
 *
 * - Activates declarative tabs [data-tabs]
 * - Activates declarative popovers [data-popover-anchor]
 * - Adds global Escape shortcut to close overlays
 */
export function initUI() {
  import('vs/tabs').then(({ tabs }) => tabs.init());
  import('vs/popover').then(({ popover }) => popover.init());
}
