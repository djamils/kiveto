import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — opens and closes the page's <dialog> windows (the
 * modals and the side panel). A button carries the id of the dialog it
 * opens: data-action="co-dialog#open" data-co-dialog-id-param="invite".
 * A dialog named in `open` (a form sent back with an error, ?support=1)
 * opens on load.
 */
export default class extends Controller {
  static values = { open: String };

  connect() {
    if (this.openValue) {
      document.getElementById(this.openValue)?.showModal();
    }
  }

  open(event) {
    event.preventDefault();
    const dialog = document.getElementById(event.params.id);
    if (dialog && !dialog.open) {
      dialog.showModal();
      dialog.querySelector('[autofocus], input:not([type=hidden]), textarea, select')?.focus();
    }
  }

  close(event) {
    event.preventDefault();
    event.target.closest('dialog')?.close();
  }

  backdrop(event) {
    if (event.target === event.currentTarget) {
      event.currentTarget.close();
    }
  }
}
