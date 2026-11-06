import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — the checks of every form of the page, as one types.
 *
 * The forms are made `novalidate`: no browser bubble. A field's own
 * constraints (required, type, pattern, minlength, maxlength, min, max)
 * give a red message under it, once it was left or its form sent, and the
 * message goes as soon as the field is right. A form with a wrong field is
 * not sent; the first one gets the focus. `data-co-message` replaces the
 * message of a pattern. The server checks everything again.
 *
 *   <body data-controller="co-forms" data-action="submit->co-forms#submit focusout->co-forms#leave input->co-forms#input change->co-forms#input">
 */
const MESSAGES = {
  valueMissing: (field) => (field.type === 'checkbox' ? 'À cocher pour continuer.' : 'Ce champ est obligatoire.'),
  typeMismatch: (field) => ({ email: 'Adresse e-mail invalide.', url: 'Adresse web invalide.' })[field.type] || 'Format invalide.',
  patternMismatch: () => 'Format invalide.',
  tooShort: (field) => `Au moins ${field.minLength} caractères.`,
  tooLong: (field) => `Au plus ${field.maxLength} caractères.`,
  rangeUnderflow: (field) => `Au moins ${field.min}.`,
  rangeOverflow: (field) => `Au plus ${field.max}.`,
  stepMismatch: () => 'Valeur invalide.',
  badInput: () => 'Valeur invalide.',
};

export default class extends Controller {
  connect() {
    this.prepare();
    this.observer = new MutationObserver(() => this.prepare());
    this.observer.observe(this.element, { childList: true, subtree: true });
  }

  disconnect() {
    this.observer?.disconnect();
  }

  // Every form, including those added later (dialogs, frames), without bubbles.
  prepare() {
    this.element.querySelectorAll('form:not([data-co-forms])').forEach((form) => {
      form.noValidate = true;
      form.dataset.coForms = '';
    });
  }

  submit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.submitter?.formNoValidate) {
      return;
    }
    const wrong = this.fields(form).filter((field) => !this.check(field));
    if (wrong.length > 0) {
      event.preventDefault();
      event.stopImmediatePropagation();
      wrong[0].focus();
    }
  }

  leave(event) {
    const field = event.target;
    if (this.isField(field)) {
      field.dataset.coTouched = '';
      this.check(field);
    }
  }

  input(event) {
    const field = event.target;
    if (this.isField(field) && (field.dataset.coTouched !== undefined || field.getAttribute('aria-invalid') === 'true')) {
      this.check(field);
    }
  }

  fields(form) {
    return [...form.elements].filter((field) => this.isField(field) && field.closest('[hidden]') === null);
  }

  isField(field) {
    return (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)
      && field.willValidate && field.type !== 'hidden' && field.dataset.coCodeTarget === undefined;
  }

  // Whether the field is right; its message under it if not.
  check(field) {
    const validity = field.validity;
    const reason = Object.keys(MESSAGES).find((key) => validity[key]);
    const message = reason === undefined ? '' : (reason === 'patternMismatch' && field.dataset.coMessage) || MESSAGES[reason](field);
    this.show(field, message);
    return message === '';
  }

  show(field, message) {
    const box = field.closest('.co-field, .co-check, label') || field.parentElement;
    let error = box.querySelector(':scope > .co-field-error[data-co-forms-error]');

    if (message === '') {
      field.removeAttribute('aria-invalid');
      error?.remove();
      return;
    }

    field.setAttribute('aria-invalid', 'true');
    if (error === null) {
      error = document.createElement('p');
      error.className = 'co-field-error';
      error.dataset.coFormsError = '';
      error.id = `${field.id || field.name}-error`.replace(/[^\w-]/g, '-');
      box.append(error);
      field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
    }
    error.textContent = message;
  }
}
