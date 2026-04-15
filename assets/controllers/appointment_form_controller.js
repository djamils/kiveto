import { Controller } from '@hotwired/stimulus';

/**
 * Kiveto — assets/controllers/appointment_form_controller.js
 * ──────────────────────────────────────────────────────────
 * Stimulus controller for the appointment create/edit modal.
 *
 * Communication with agenda.js via CustomEvents:
 *   IN:  'appointment:open-modal'  { mode, prefill?, appointment? }
 *   OUT: 'appointment:saved'       { appointment, action }
 */
export default class extends Controller {
  static targets = [
    'modalTitle', 'form', 'submitBtn', 'submitLabel', 'submitSpinner',
    'errorBanner', 'startsAt', 'duration', 'practitioner',
    'ownerInput', 'ownerId', 'animalInput', 'animalId',
    'reason', 'notes',
    'errorStartsAt', 'errorPractitioner',
  ];

  static values = {
    createUrl:               { type: String, default: '' },
    rescheduleUrlTemplate:   { type: String, default: '' },
    cancelUrlTemplate:       { type: String, default: '' },
  };

  _mode = 'create'; // 'create' or 'edit'
  _appointmentId = null;
  _submitting = false;

  connect() {
    this._openHandler = (e) => this._onOpenModal(e);
    document.addEventListener('appointment:open-modal', this._openHandler);

    // Listen for owner selection to dispatch owner:changed for animal autocomplete
    this._ownerObserver = new MutationObserver(() => {
      if (!this.hasOwnerIdTarget) return;
      const ownerId = this.ownerIdTarget.value;
      document.dispatchEvent(new CustomEvent('owner:changed', {
        detail: { ownerId },
      }));
    });

    if (this.hasOwnerIdTarget) {
      this._ownerObserver.observe(this.ownerIdTarget, { attributes: true, attributeFilter: ['value'] });
      // Also poll on input changes since MutationObserver doesn't catch .value changes from JS
      this._ownerPollInterval = setInterval(() => {
        if (!this.hasOwnerIdTarget) return;
        const current = this.ownerIdTarget.value;
        if (current !== this._lastOwnerId) {
          this._lastOwnerId = current;
          document.dispatchEvent(new CustomEvent('owner:changed', {
            detail: { ownerId: current },
          }));
        }
      }, 200);
    }
    this._lastOwnerId = '';
  }

  disconnect() {
    if (this._openHandler) {
      document.removeEventListener('appointment:open-modal', this._openHandler);
    }
    if (this._ownerObserver) this._ownerObserver.disconnect();
    if (this._ownerPollInterval) clearInterval(this._ownerPollInterval);
  }

  _onOpenModal(e) {
    const { mode, prefill, appointment } = e.detail || {};
    this._mode = mode || 'create';
    this._appointmentId = appointment?.id || null;

    this._clearErrors();
    this._populatePractitionerSelect();

    if (this._mode === 'edit' && appointment) {
      this._fillEditMode(appointment);
    } else {
      this._fillCreateMode(prefill || {});
    }

    this._open();
  }

  _populatePractitionerSelect() {
    if (!this.hasPractitionerTarget) return;

    const vets = window.__agendaVets ?? [];
    const select = this.practitionerTarget;
    // Keep the placeholder option, clear the rest
    while (select.options.length > 1) select.remove(1);

    if (vets.length === 0) {
      select.disabled = true;
      select.options[0].textContent = 'Aucun praticien disponible';
      return;
    }

    select.disabled = false;
    select.options[0].textContent = '-- Sélectionner --';
    vets.forEach((vet) => {
      const opt = document.createElement('option');
      opt.value = vet.userId;
      opt.textContent = vet.label;
      select.appendChild(opt);
    });
  }

  _fillCreateMode(prefill) {
    if (this.hasModalTitleTarget) this.modalTitleTarget.textContent = 'Nouveau rendez-vous';
    if (this.hasSubmitLabelTarget) this.submitLabelTarget.textContent = 'Créer le RDV';

    // Reset form
    if (this.hasFormTarget) this.formTarget.reset();

    // Pre-fill date/time
    if (prefill.date && this.hasStartsAtTarget) {
      const time = prefill.time || '';
      this.startsAtTarget.value = time ? `${prefill.date}T${time}` : `${prefill.date}T09:00`;
    }

    // Pre-fill practitioner
    if (prefill.practitionerUserId && this.hasPractitionerTarget) {
      this.practitionerTarget.value = prefill.practitionerUserId;
    }

    // Enable owner/animal fields
    this._setOwnerAnimalReadonly(false);
  }

  _fillEditMode(appointment) {
    if (this.hasModalTitleTarget) this.modalTitleTarget.textContent = 'Modifier le rendez-vous';
    if (this.hasSubmitLabelTarget) this.submitLabelTarget.textContent = 'Enregistrer';

    // Fill fields
    if (this.hasStartsAtTarget && appointment.startsAtUtc) {
      // Convert UTC string to datetime-local value
      const dt = appointment.startsAtUtc.replace(' ', 'T').replace('Z', '');
      this.startsAtTarget.value = dt.substring(0, 16);
    }

    if (this.hasPractitionerTarget) {
      this.practitionerTarget.value = appointment.practitionerUserId || '';
    }

    // Owner/animal in read-only mode
    if (this.hasOwnerInputTarget) {
      this.ownerInputTarget.value = appointment.ownerLabel || appointment.ownerId || '';
      this.ownerInputTarget.readOnly = true;
    }
    if (this.hasOwnerIdTarget) {
      this.ownerIdTarget.value = appointment.ownerId || '';
    }
    if (this.hasAnimalInputTarget) {
      this.animalInputTarget.value = appointment.animalLabel || appointment.animalId || '';
      this.animalInputTarget.readOnly = true;
    }
    if (this.hasAnimalIdTarget) {
      this.animalIdTarget.value = appointment.animalId || '';
    }

    this._setOwnerAnimalReadonly(true);
  }

  _setOwnerAnimalReadonly(readonly) {
    if (this.hasOwnerInputTarget) {
      this.ownerInputTarget.readOnly = readonly;
      this.ownerInputTarget.style.opacity = readonly ? '0.6' : '';
    }
    if (this.hasAnimalInputTarget) {
      this.animalInputTarget.readOnly = readonly;
      this.animalInputTarget.style.opacity = readonly ? '0.6' : '';
    }
  }

  _open() {
    this.element.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  close() {
    this.element.classList.add('hidden');
    document.body.style.overflow = '';
  }

  async submit(e) {
    e.preventDefault();
    if (this._submitting) return;

    this._clearErrors();
    this._setSubmitting(true);

    const formData = new FormData(this.formTarget);
    const url = this._mode === 'edit'
      ? this.rescheduleUrlTemplateValue.replace(':id', this._appointmentId)
      : this.createUrlValue;

    try {
      const response = await fetch(url, {
        method: 'POST',
        body: new URLSearchParams(formData),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
      });

      let data;
      try {
        data = await response.json();
      } catch (_e) {
        this._showGlobalError('Réponse inattendue du serveur.');
        return;
      }

      if (data.success) {
        this.close();
        const action = this._mode === 'edit' ? 'rescheduled' : 'created';
        document.dispatchEvent(new CustomEvent('appointment:saved', {
          detail: { appointment: data.appointment, action },
        }));
      } else {
        this._showErrors(data.errors || {}, data.errorCode);
      }
    } catch (_e) {
      this._showGlobalError('Erreur réseau. Veuillez réessayer.');
    } finally {
      this._setSubmitting(false);
    }
  }

  _setSubmitting(value) {
    this._submitting = value;
    if (this.hasSubmitBtnTarget) {
      this.submitBtnTarget.disabled = value;
    }
    if (this.hasSubmitSpinnerTarget) {
      this.submitSpinnerTarget.classList.toggle('hidden', !value);
    }
    if (this.hasSubmitLabelTarget) {
      this.submitLabelTarget.classList.toggle('hidden', value);
    }
  }

  _clearErrors() {
    if (this.hasErrorBannerTarget) {
      this.errorBannerTarget.textContent = '';
      this.errorBannerTarget.classList.add('hidden');
    }
    if (this.hasErrorStartsAtTarget) {
      this.errorStartsAtTarget.textContent = '';
      this.errorStartsAtTarget.classList.add('hidden');
    }
    if (this.hasErrorPractitionerTarget) {
      this.errorPractitionerTarget.textContent = '';
      this.errorPractitionerTarget.classList.add('hidden');
    }
  }

  _showErrors(errors, errorCode) {
    if (errors.global?.length) {
      this._showGlobalError(errors.global.join(' '));
    } else if (errorCode === 'APPOINTMENT_CONFLICT') {
      this._showGlobalError('Ce créneau est déjà occupé.');
    }

    if (errors.startsAtUtc?.length && this.hasErrorStartsAtTarget) {
      this.errorStartsAtTarget.textContent = errors.startsAtUtc.join(' ');
      this.errorStartsAtTarget.classList.remove('hidden');
    }
    if (errors.practitionerUserId?.length && this.hasErrorPractitionerTarget) {
      this.errorPractitionerTarget.textContent = errors.practitionerUserId.join(' ');
      this.errorPractitionerTarget.classList.remove('hidden');
    }
  }

  _showGlobalError(message) {
    if (this.hasErrorBannerTarget) {
      this.errorBannerTarget.textContent = message;
      this.errorBannerTarget.classList.remove('hidden');
    }
  }
}
