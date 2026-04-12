/**
 * Page module — Clients & Animaux
 * Loaded by app.js dispatcher on turbo:load.
 *
 * The page is now fully server-rendered: tabs, search, pagination and modals
 * all live in Twig templates and Symfony controllers. This module just keeps a
 * tiny init/cleanup contract for the dispatcher.
 *
 * The owner autocomplete is a Stimulus controller (client_search_autocomplete)
 * registered via data-controller in the modal template — no JS boot needed.
 */

export function init() {
  // No-op: search/pagination/tabs are server-driven (GET form + links).
  // Lucide icons get re-rendered globally on turbo:load.
}

export function cleanup() {
  // No-op.
}
