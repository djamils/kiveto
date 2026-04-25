// IMPORTANT: Must be placed on a <turbo-frame> element.
// this.element.reload() is TurboFrameElement API — throws on non-frame elements.
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = { interval: { type: Number, default: 20000 } };

  connect() {
    this._timer = setInterval(() => this.element.reload(), this.intervalValue);
  }

  disconnect() {
    clearInterval(this._timer);
  }
}
