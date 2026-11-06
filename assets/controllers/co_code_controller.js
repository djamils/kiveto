import { Controller } from '@hotwired/stimulus';

/*
 * Kiveto Console — a masked code: « 000 000 » (the app's code) or
 * « XXXXX-XXXXX » (a backup code), on the form that sends it.
 *
 * What is typed takes the place of the mask, character for character: the
 * rest of the mask stays where it is (the field's own text is transparent,
 * the mask is drawn under it in the same font) and the separator never
 * moves. Anything but the mask's characters is refused with a message, a
 * code too short is not sent. A complete app code is sent at once.
 *
 *   <form data-controller="co-code" data-action="submit->co-code#submit"
 *         data-co-code-mask-value="000 000" data-co-code-accept-value="[0-9]" …>
 *     <span class="co-code"><span class="co-code-ghost" data-co-code-target="ghost"></span>
 *       <input data-co-code-target="input" data-action="input->co-code#type"></span>
 *     <p class="co-field-error" data-co-code-target="error" hidden></p>
 */
export default class extends Controller {
  static targets = ['input', 'ghost', 'error'];
  static values = {
    mask: String,
    accept: String,
    autosubmit: Boolean,
    incomplete: String,
    refused: String,
  };

  connect() {
    this.slots = [...this.maskValue].filter((c) => this.isSlot(c)).length;
    this.accepted = new RegExp(`^${this.acceptValue}$`);
    this.inputTarget.maxLength = this.maskValue.length;
    this.sent = false;
    // From now on the mask is drawn under the field, whose own text turns transparent.
    this.element.querySelector('.co-code')?.classList.add('is-masked');
    const { chars } = this.clean(this.inputTarget.value);
    this.inputTarget.value = this.format(chars);
    this.draw(chars);
  }

  type(event) {
    const input = this.inputTarget;
    const caret = input.selectionStart ?? input.value.length;
    const { chars, refused } = this.clean(input.value);
    const before = this.clean(input.value.slice(0, caret)).chars.length;
    const text = this.format(chars);

    input.value = text;
    const at = this.caretAfter(text, before);
    input.setSelectionRange(at, at);
    this.draw(chars);

    if (refused) {
      this.fail(this.refusedValue);
    } else if (chars.length === this.slots || !this.sent) {
      this.clear();
    }

    const deleting = (event.inputType || '').startsWith('delete');
    if (this.autosubmitValue && !refused && !deleting && chars.length === this.slots) {
      input.form?.requestSubmit();
    }
  }

  submit(event) {
    this.sent = true;
    const { chars } = this.clean(this.inputTarget.value);
    if (chars.length < this.slots) {
      event.preventDefault();
      event.stopImmediatePropagation();
      this.fail(this.incompleteValue);
      this.inputTarget.focus();
    }
  }

  // The accepted characters of a text, upper-cased, at most one per slot.
  clean(raw) {
    const chars = [];
    let refused = false;
    for (const c of raw.toUpperCase()) {
      if (this.isSeparator(c)) {
        continue;
      }
      if (this.accepted.test(c)) {
        if (chars.length < this.slots) {
          chars.push(c);
        }
      } else {
        refused = true;
      }
    }
    return { chars, refused };
  }

  // The characters in the mask's places: « 1234 » → « 123 4 ».
  format(chars) {
    let text = '';
    let next = 0;
    for (const m of this.maskValue) {
      if (next >= chars.length) {
        break;
      }
      text += this.isSlot(m) ? chars[next++] : m;
    }
    return text;
  }

  // Where the caret goes once `count` characters are before it.
  caretAfter(text, count) {
    if (count === 0) {
      return 0;
    }
    let seen = 0;
    for (let i = 0; i < text.length; i++) {
      if (!this.isSeparator(text[i]) && ++seen === count) {
        return i + 1;
      }
    }
    return text.length;
  }

  // The mask under the field: the typed characters, then the mask's own.
  draw(chars) {
    let next = 0;
    const cells = [...this.maskValue].map((m) => {
      const cell = document.createElement('span');
      if (this.isSlot(m)) {
        const typed = chars[next++];
        cell.textContent = typed ?? m;
        cell.className = typed === undefined ? 'is-empty' : 'is-typed';
      } else {
        cell.textContent = m === ' ' ? ' ' : m;
        cell.className = 'is-sep';
      }
      return cell;
    });
    this.ghostTarget.replaceChildren(...cells);
  }

  fail(message) {
    this.errorTarget.textContent = message;
    this.errorTarget.hidden = false;
    this.inputTarget.setAttribute('aria-invalid', 'true');
    this.element.querySelector('.co-code')?.classList.add('is-invalid');
  }

  clear() {
    this.errorTarget.hidden = true;
    this.errorTarget.textContent = '';
    this.inputTarget.removeAttribute('aria-invalid');
    this.element.querySelector('.co-code')?.classList.remove('is-invalid');
  }

  isSlot(c) {
    return c === '0' || c === 'X';
  }

  isSeparator(c) {
    return c === ' ' || c === '-';
  }
}
