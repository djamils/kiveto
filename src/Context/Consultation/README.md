# Consultation Bounded Context

The **Consultation** Bounded Context manages the clinical encounter between a veterinarian and a patient. It owns the SOAP-structured clinical record for each visit, plus the prescription and the billing draft that come out of it.

## Ubiquitous Language

- **Consultation** — the aggregate root representing a single clinical encounter, opened from an appointment or an admission.
- **ConsultationStatus** — `OPEN | CLOSED`.
- **MotifTag** — one reason-for-visit chip; the free-text detail lives in the consultation's chief complaint.
- **Vitals** — weight and temperature of the patient. Single snapshot per consultation, held as an embedded value object.
- **TypedVitalRecord** / **VitalType** — the additional constants (heart rate, respiratory rate, body-condition and pain scores, capillary refill time, mucous membranes, blood pressure, glycemia). `VitalType` carries the unit, the acceptance bounds and the reference range, and is the single server-side source for both validation and the cockpit's hydration payload. One record per type: recording the same type again replaces it.
- **ExamSystemRecord** / **BodySystem** / **ExamStatus** — the per-system clinical exam grid (SOAP "O"). Each of the ten body systems is `NORMAL | ANOMALY | UNTESTED`, with free-text notes and a system-specific structured drill-down stored as a JSON column (its schema varies per system, see the value object's docblock).
- **DiagnosisRecord** / **DiagnosisCertainty** / **DiagnosisSource** — the differential diagnosis list (SOAP "A"): a label, an optional nomenclature code, a certainty (`CERTAIN | PROBABLE | POSSIBLE | EXCLUDED`), a note, and the source (`MANUAL | AI_SUGGESTION`). At most one diagnosis is flagged primary.
- **PlanActionRecord** / **PlanActionKind** — the typed plan actions (SOAP "P"): `PERFORMED_ACT | MEDICATION_PRESCRIPTION | FOLLOW_UP_APPOINTMENT | ADVICE | OTHER`. Only performed acts are billable.
- **PrescriptionLineRecord** — one medication of the prescription, with its posology and the price snapshotted from the Catalog at add time.
- **BillingLineRecord** / **BillingLineSource** — the derived billing draft. Never written by hand: the aggregate re-derives it from billable plan actions and prescription lines.
- **ClinicalNoteRecord** — an immutable note attached to a consultation, typed by `NoteType`.
- **NoteType** — `ANAMNESIS | CLINICAL_EXAM | DIAGNOSIS | TREATMENT_PLAN | FOLLOWUP | GENERAL`.
- **PerformedActRecord** — an immutable record of a clinical act performed during the consultation (label, quantity, timestamp).

## Aggregate

### Consultation

Opens from a scheduled appointment (`startFromAppointment()`) or directly from an active admission (`startFromAdmission()`). While `OPEN`, practitioners record the chief complaint and motifs, the vitals, the SOAP free texts (`subjectiveText`, `objectiveObservations`), the per-system exam, the diagnoses, the plan actions, the prescription lines and the team memo. Closing the consultation (`close()`) transitions status to `CLOSED` and automatically completes any linked appointment via the `SchedulingServiceCoordinatorInterface` port.

**Billing derivation.** `syncBillingLines()` runs at the end of every mutation that adds, updates or removes a billable plan action or a prescription line. Existing lines are matched by `sourceLineId`: a match keeps its own id **and its snapshotted unit price** while label and quantity follow the source, a new source creates a line, an orphaned line disappears. Prices are copied from the source line's snapshot, never re-resolved — a later Catalog price change must not alter an existing consultation.

**Domain events.** The cockpit mutations record no events. This BC captures events but never publishes them (`pullDomainEvents()` is uncalled everywhere), so new event classes would be dead code; the existing ones are kept untouched.

## Business Invariants

- Every mutation requires an `OPEN` consultation (`Cannot modify a closed consultation`).
- A consultation cannot be reopened once `CLOSED`.
- At most one consultation per appointment (unique constraint on `appointment_id`).
- The opening practitioner must hold the `VETERINARY` role at the clinic (enforced via `PractitionerEligibilityCheckerInterface`).
- At most one primary diagnosis.
- One typed-vital record per `VitalType`, one exam record per `BodySystem`.
- `markAllSystemsNormal()` never overwrites a system already flagged as an anomaly.
- Command handlers reject a consultation belonging to another clinic with the same `Consultation not found` error as a missing one.

## Application Ports

| Interface | Purpose |
|-----------|---------|
| `PractitionerEligibilityCheckerInterface` | Verifies the user holds the `VETERINARY` role at the clinic (Clinic BC) |
| `SchedulingAppointmentContextProviderInterface` | Reads appointment context (time slot, animal, owner) from Scheduling BC |
| `SchedulingServiceCoordinatorInterface` | Ensures the linked waiting room entry moves to `IN_SERVICE` on start; completes the appointment on close |
| `AdmissionContextProviderInterface` | Reads admission context (patient identity, intake channel) from the Admission BC |
| `AdmissionServiceCoordinatorInterface` | Moves the admission to the consultation room when the encounter starts |
| `CatalogItemProviderInterface` | Searches acts and medications, resolves the price to snapshot, and reads a drug's active substances (Catalog BC + PharmaceuticalRegistry) |
| `TaxRateProviderInterface` | Effective VAT rate per tax category, for the billing totals (Taxation) |
| `PatientIdsProviderInterface` | All patient records of a clinic linked to one animal, so the history survives reconciliation (Patient BC) |
| `ConsultationReadRepositoryInterface` | Read model: consultation details and the patient's consultation history |

## Read Side

`GetConsultationDetails` returns the whole cockpit state. The DBAL read repository stays bus-free and returns zeroed totals; the **handler** owns the money maths: it collects the distinct tax categories of the billing lines, asks the Taxation port once per category, and sums HT / TVA / TTC.

`ListConsultationsForPatient` powers the history panel. It resolves the patient's sibling records through `PatientIdsProviderInterface` (keyed on the animal) before listing, so consultations recorded before a reconciliation stay visible.

## Cross-BC Notes

All external BC IDs (`ClinicId`, `UserId`, `AppointmentId`, `AdmissionId`, `PatientId`) are encapsulated in BC-local value objects. No Doctrine relations cross BC boundaries. The allergy warning shown on the prescription is computed in the Presentation layer by matching the animal's medical-alert labels (Animal BC) against the article's active substances (via `CatalogItemProviderInterface`) — a deliberately naive label match, not an interaction engine.

## Persistence

Ten Doctrine tables: `consultation__consultations` plus the child rows `__clinical_notes`, `__performed_acts`, `__motifs`, `__typed_vitals`, `__exam_systems`, `__diagnoses`, `__plan_actions`, `__prescription_lines` and `__billing_lines`. Children link to their root through a loose `consultation_id` column (no ORM relation) and implement `ConsultationChildEntity`, which lets `DoctrineConsultationRepository::save()` synchronise every collection by row id: managed rows are updated in place, new records are persisted, and rows the aggregate no longer holds are removed. Ordered collections carry a `position` column assigned from the aggregate's order.

> `consultation__exam_systems.system` is a reserved word in MySQL 8.0.3+; the entity maps it as a quoted identifier so Doctrine backticks it on write.
