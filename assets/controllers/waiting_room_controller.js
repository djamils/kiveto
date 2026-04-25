import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['clockH', 'clockM', 'clockS', 'detailPanel'];

  connect() {
    this._updateClock();
    this._clockTimer = setInterval(() => this._updateClock(), 1000);

    this._keydownHandler = (e) => {
      if (e.key === 'Escape') {
        this.closeDetailPanel();
        this.closeAllModals();
      }
    };
    document.addEventListener('keydown', this._keydownHandler);

    const detailFrame = document.getElementById('patient-detail');
    if (detailFrame) {
      this._frameLoadHandler = () => this.showDetailPanel();
      detailFrame.addEventListener('turbo:frame-load', this._frameLoadHandler);
    }
  }

  disconnect() {
    clearInterval(this._clockTimer);
    document.removeEventListener('keydown', this._keydownHandler);
    const detailFrame = document.getElementById('patient-detail');
    if (detailFrame && this._frameLoadHandler) {
      detailFrame.removeEventListener('turbo:frame-load', this._frameLoadHandler);
    }
  }

  openModal(event) {
    const modalId = event.params.modalId;
    document.getElementById(modalId)?.classList.remove('hidden');
  }

  closeModal(event) {
    const modalId = event.params.modalId;
    document.getElementById(modalId)?.classList.add('hidden');
  }

  closeOnOverlayClick(event) {
    if (event.target === event.currentTarget) {
      event.currentTarget.classList.add('hidden');
    }
  }

  closeAllModals() {
    document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((m) => {
      m.classList.add('hidden');
    });
  }

  showDetailPanel() {
    this.detailPanelTarget.classList.remove('is-hidden');
  }

  closeDetailPanel() {
    if (this.hasDetailPanelTarget) {
      this.detailPanelTarget.classList.add('is-hidden');
    }
  }

  _updateClock() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    if (this.hasClockHTarget) this.clockHTarget.textContent = pad(now.getHours());
    if (this.hasClockMTarget) this.clockMTarget.textContent = pad(now.getMinutes());
    if (this.hasClockSTarget) this.clockSTarget.textContent = pad(now.getSeconds());
  }
}
