/**
 * Kiveto — assets/js/pages/settings.js
 * ───────────────────────────────────
 * The settings pages: the two team modals, and the fields that only concern a
 * practitioner.
 *
 * Every action URL comes off the template; the module fills forms and opens
 * things, it never builds a route.
 */

import { modal } from 'kiveto/modal';

const listeners = [];

function on(target, type, handler) {
  target.addEventListener(type, handler);
  listeners.push(() => target.removeEventListener(type, handler));
}

function initModals() {
  on(document, 'click', e => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      modal.open(opener.dataset.modalOpen);
      return;
    }

    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
      const overlay = closer.closest('.modal-overlay');
      if (overlay) modal.close(overlay.id);
    }
  });
}

/** A registration number and a title only mean something for a practitioner. */
function initRoleFields() {
  const role = document.getElementById('member-role');
  if (!role) return;

  const sync = () => {
    const isPractitioner = role.value === 'VETERINARY' || role.value === 'VETERINARY_ASSISTANT';
    document.querySelectorAll('[data-vet-only]').forEach(field => {
      field.style.display = isPractitioner ? '' : 'none';
    });
  };

  on(role, 'change', sync);
  sync();
}

/** Points the edit form at the member whose row was clicked. */
function initMemberEdit() {
  on(document, 'click', e => {
    const button = e.target.closest('[data-member-edit]');
    if (!button) return;

    const form = document.getElementById('member-edit-form');
    if (!form) return;

    form.setAttribute('action', `/settings/team/members/${button.dataset.membershipId}`);

    const target = form.querySelector('[data-member-target]');
    if (target) target.textContent = button.dataset.name || '';

    const set = (selector, value) => {
      const field = form.querySelector(selector);
      if (field) field.value = value ?? '';
    };

    set('[data-profile-field]', button.dataset.profileId);
    set('[data-role-field]', button.dataset.role);
    set('[data-engagement-field]', button.dataset.engagement);
    set('[data-phone-field]', button.dataset.phone);

    modal.open('member-edit-modal');
  });
}

export function init() {
  initModals();
  initRoleFields();
  initMemberEdit();
}

export function cleanup() {
  listeners.forEach(off => off());
  listeners.length = 0;
}
