import { Controller } from '@hotwired/stimulus';

// Friendly error messages keyed by backend errorCode. Keep in sync with the
// codes returned by Create/Reschedule/Cancel appointment controllers.
const FRIENDLY_ERRORS = {
  APPOINTMENT_CONFLICT:        'Ce créneau est déjà occupé.',
  APPOINTMENT_TERMINAL_STATUS: 'Ce rendez-vous n\'est plus modifiable.',
};

/**
 * Kiveto — assets/controllers/appointment_form_controller.js
 * ──────────────────────────────────────────────────────────
 * Stimulus controller for the appointment create/edit modal.
 *
 * Communication with agenda.js via CustomEvents:
 *   IN:  'appointment:open-modal'  { mode, prefill?, appointment? }
 *   OUT: 'appointment:saved'       { appointment, action }
 *
 * Form model:
 *   - startsAtUtc (hidden): combined 'YYYY-MM-DDTHH:MM' — set from prefill
 *     date + visible time input (or from appointment in edit mode).
 *   - durationMinutes (hidden): driven by chip buttons (15/20/30/60).
 *   - reason (hidden): driven by motif chip buttons.
 *   - ownerId, animalId (hidden): filled by autocomplete controllers.
 *   - practitionerUserId (select): populated from window.__agendaVets.
 *   - notes (textarea).
 */
export default class extends Controller {
  static targets = [
    'modalTitle', 'slotLabel',
    'form', 'submitBtn', 'submitLabel', 'submitSpinner',
    'errorBanner', 'errorStartsAt', 'errorPractitioner',
    'startsAt', 'time', 'endTime', 'duration', 'durationChips',
    'practitioner',
    'ownerInput', 'ownerId', 'animalInput', 'animalId',
    'reason', 'motifChips',
    'notes',
  ];

  static values = {
    createUrl:               { type: String, default: '' },
    rescheduleUrlTemplate:   { type: String, default: '' },
    cancelUrlTemplate:       { type: String, default: '' },
  };

  _mode = 'create';
  _appointmentId = null;
  _submitting = false;
  _date = ''; // 'YYYY-MM-DD' held separately from the visible time input

  connect() {
    this._openHandler = (e) => this._onOpenModal(e);
    document.addEventListener('appointment:open-modal', this._openHandler);

    // Close on Escape when the modal is open.
    this._keydownHandler = (e) => {
      if (e.key === 'Escape' && !this.element.classList.contains('hidden')) {
        this.close();
      }
    };
    document.addEventListener('keydown', this._keydownHandler);

    // Propagate owner selection to animal autocomplete via 'owner:changed'.
    this._lastOwnerId = '';
    if (this.hasOwnerIdTarget) {
      this._ownerPollInterval = setInterval(() => {
        const current = this.ownerIdTarget.value;
        if (current !== this._lastOwnerId) {
          this._lastOwnerId = current;
          document.dispatchEvent(new CustomEvent('owner:changed', {
            detail: { ownerId: current },
          }));
        }
      }, 200);
    }
  }

  disconnect() {
    if (this._openHandler) {
      document.removeEventListener('appointment:open-modal', this._openHandler);
    }
    if (this._keydownHandler) {
      document.removeEventListener('keydown', this._keydownHandler);
    }
    if (this._ownerPollInterval) clearInterval(this._ownerPollInterval);
  }

  onOverlayClick(event) {
    // Close only when the click actually lands on the overlay backdrop,
    // not when it bubbles up from inside the .modal content.
    if (event.target === this.element) {
      this.close();
    }
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

    this._updateEndTime();
    this._open();
  }

  _populatePractitionerSelect() {
    if (!this.hasPractitionerTarget) return;

    const vets = window.__agendaVets ?? [];
    const select = this.practitionerTarget;
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
    if (this.hasSubmitLabelTarget) this.submitLabelTarget.textContent = 'Confirmer le rendez-vous';

    if (this.hasFormTarget) this.formTarget.reset();

    const today = new Date().toISOString().slice(0, 10);
    this._date = prefill.date || today;
    const time = prefill.time || '09:00';

    if (this.hasTimeTarget) this.timeTarget.value = time;
    this._syncStartsAt(time);
    this._updateSlotLabel();

    // Default duration: 30 min (first active chip)
    this._setDuration(30);
    // Reset motif chips (no default selection)
    this._setReason('');

    if (prefill.practitionerUserId && this.hasPractitionerTarget) {
      this.practitionerTarget.value = prefill.practitionerUserId;
    }

    this._setOwnerAnimalReadonly(false);
  }

  _fillEditMode(appointment) {
    if (this.hasModalTitleTarget) this.modalTitleTarget.textContent = 'Modifier le rendez-vous';
    if (this.hasSubmitLabelTarget) this.submitLabelTarget.textContent = 'Enregistrer';

    // Prefer the clinic-local date/time already computed by agenda.js
    // (prepareAppointments() decorates appointments with _date / _startTime
    // in the clinic's timezone). Fall back to slicing startsAtUtc only when
    // those fields are missing — that path silently drops the timezone.
    let date = appointment._date || '';
    let time = appointment._startTime || '';
    if ((!date || !time) && appointment.startsAtUtc) {
      const raw = appointment.startsAtUtc.replace(' ', 'T').replace('Z', '');
      date = date || raw.slice(0, 10);
      time = time || raw.slice(11, 16);
    }
    if (date) {
      this._date = date;
      if (this.hasTimeTarget) this.timeTarget.value = time;
      this._syncStartsAt(time);
      this._updateSlotLabel();
    }

    this._setDuration(appointment.durationMinutes || 30);
    this._setReason(appointment.reason || '');

    if (this.hasPractitionerTarget) {
      this.practitionerTarget.value = appointment.practitionerUserId || '';
    }

    if (this.hasOwnerInputTarget) {
      this.ownerInputTarget.value = appointment.ownerLabel || appointment.ownerId || '';
    }
    if (this.hasOwnerIdTarget) {
      this.ownerIdTarget.value = appointment.ownerId || '';
    }
    if (this.hasAnimalInputTarget) {
      this.animalInputTarget.value = appointment.animalLabel || appointment.animalId || '';
    }
    if (this.hasAnimalIdTarget) {
      this.animalIdTarget.value = appointment.animalId || '';
    }
    if (this.hasNotesTarget) {
      this.notesTarget.value = appointment.notes || '';
    }

    this._setOwnerAnimalReadonly(false);
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

  _syncStartsAt(time) {
    if (!this.hasStartsAtTarget) return;
    this.startsAtTarget.value = `${this._date}T${time || '09:00'}`;
  }

  _updateSlotLabel() {
    if (!this.hasSlotLabelTarget) return;
    if (!this._date) {
      this.slotLabelTarget.textContent = '';
      return;
    }
    const today = new Date().toISOString().slice(0, 10);
    const time = this.hasTimeTarget ? this.timeTarget.value : '';
    let datePart;
    if (this._date === today) {
      datePart = "Aujourd'hui";
    } else {
      const d = new Date(`${this._date}T00:00:00`);
      datePart = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    }
    const timePart = time ? ` · ${time.replace(':', ' h ')}` : '';
    this.slotLabelTarget.textContent = datePart + timePart;
  }

  _updateEndTime() {
    if (!this.hasEndTimeTarget) return;
    const time = this.hasTimeTarget ? this.timeTarget.value : '';
    const duration = parseInt(this._readDuration(), 10) || 0;
    if (!time || !duration) {
      this.endTimeTarget.textContent = '—';
      return;
    }
    const [h, m] = time.split(':').map((n) => parseInt(n, 10));
    const endMinutes = h * 60 + m + duration;
    const eh = Math.floor(endMinutes / 60) % 24;
    const em = endMinutes % 60;
    const pad = (n) => String(n).padStart(2, '0');
    this.endTimeTarget.textContent = `${pad(eh)} h ${pad(em)}`;
  }

  _readDuration() {
    return this.hasDurationTarget ? this.durationTarget.value : '30';
  }

  // ── Action handlers ──

  onTimeChange() {
    const time = this.hasTimeTarget ? this.timeTarget.value : '';
    this._syncStartsAt(time);
    this._updateSlotLabel();
    this._updateEndTime();
  }

  onDurationClick(event) {
    const duration = parseInt(event.currentTarget.dataset.duration, 10);
    if (!duration) return;
    this._setDuration(duration);
    this._updateEndTime();
  }

  _setDuration(minutes) {
    if (this.hasDurationTarget) this.durationTarget.value = String(minutes);
    if (this.hasDurationChipsTarget) {
      this.durationChipsTarget.querySelectorAll('.appt-chip').forEach((chip) => {
        chip.classList.toggle('is-active', parseInt(chip.dataset.duration, 10) === minutes);
      });
    }
  }

  onMotifClick(event) {
    const chip = event.currentTarget;
    const reason = chip.dataset.reason || '';
    // Toggle off if clicking the already-active chip
    if (chip.classList.contains('is-active')) {
      this._setReason('');
    } else {
      this._setReason(reason);
    }
  }

  _setReason(reason) {
    if (this.hasReasonTarget) this.reasonTarget.value = reason;
    if (this.hasMotifChipsTarget) {
      this.motifChipsTarget.querySelectorAll('.appt-motif-chip').forEach((chip) => {
        const active = chip.dataset.reason === reason && '' !== reason;
        chip.classList.toggle('is-active', active);
        if (active) {
          chip.style.setProperty('--motif-active-color', chip.dataset.color || '');
        } else {
          chip.style.removeProperty('--motif-active-color');
        }
      });
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

    // Make sure the hidden startsAtUtc is up-to-date with the visible time.
    if (this.hasTimeTarget) this._syncStartsAt(this.timeTarget.value);

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
    // Prefer a user-friendly message keyed by errorCode — the raw server
    // message for conflicts currently exposes the practitioner UUID, which
    // isn't presentable (will be swapped for a real name once IdentityAccess
    // profiles are wired).
    const friendly = FRIENDLY_ERRORS[errorCode];
    if (friendly) {
      this._showGlobalError(friendly);
    } else if (errors.global?.length) {
      this._showGlobalError(errors.global.join(' '));
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
