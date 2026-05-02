# Consultation Bounded Context

The **Consultation** Bounded Context manages the clinical encounter between a veterinarian and a patient. It owns the SOAP-structured clinical record for each visit.

## Ubiquitous Language

- **Consultation** — the aggregate root representing a single clinical encounter, opened from an appointment or an admission.
- **ConsultationStatus** — `OPEN | CLOSED`.
- **Vitals** — a point-in-time measurement of a patient's vital signs (weight, temperature). Recorded as an embedded value object; multiple recordings are not yet supported (single-snapshot per consultation).
- **ClinicalNoteRecord** — an immutable note attached to a consultation, typed by `NoteType`.
- **NoteType** — `ANAMNESIS | EXAMINATION | DIAGNOSIS | TREATMENT | FOLLOW_UP`.
- **PerformedActRecord** — an immutable record of a clinical act performed during the consultation (label, quantity, timestamp).

## Aggregate

### Consultation
Opens from a scheduled appointment (`startFromAppointment()`) or directly from an active admission (`startFromAdmission()`). While `OPEN`, practitioners may record the chief complaint, vitals, clinical notes, and performed acts. Closing the consultation (`close()`) transitions status to `CLOSED` and automatically completes any linked appointment via the `SchedulingServiceCoordinatorInterface` port.

## Business Invariants

- Clinical data (notes, vitals, performed acts) can only be added to an `OPEN` consultation.
- A consultation cannot be reopened once `CLOSED`.
- At most one consultation per appointment (unique constraint on `appointment_id`).
- The opening practitioner must hold the `VETERINARY` role at the clinic (enforced via `PractitionerEligibilityCheckerInterface`).

## Application Ports

| Interface | Purpose |
|-----------|---------|
| `PractitionerEligibilityCheckerInterface` | Verifies the user holds the `VETERINARY` role at the clinic (Clinic BC) |
| `SchedulingAppointmentContextProviderInterface` | Reads appointment context (time slot, animal, owner) from Scheduling BC |
| `SchedulingServiceCoordinatorInterface` | Ensures the linked waiting room entry moves to `IN_SERVICE` on start; completes the appointment on close |
| `AdmissionContextProviderInterface` | Reads admission context (patient identity, intake channel) from the Admission BC |

## Cross-BC Notes

All external BC IDs (`ClinicId`, `UserId`, `AppointmentId`, `AdmissionId`) are encapsulated in BC-local value objects. No Doctrine relations cross BC boundaries.

## Persistence

Three Doctrine tables: `consultation__consultations`, `consultation__clinical_notes`, `consultation__performed_acts`. Clinical notes and performed acts are append-only child rows; the Doctrine repository re-inserts them on every save (no update path for individual records).
