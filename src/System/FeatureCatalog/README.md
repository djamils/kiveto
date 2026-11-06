# FeatureCatalog System Context

What Kiveto switches on for each clinic, from the console (« Fonctionnalités »,
F1–F3, and the features card of a clinic's sheet). The catalogue itself is code
(`Domain/FeatureCatalogue`); the database keeps the global settings changed
since and the state of each clinic.

## Ubiquitous Language

| Term | Meaning |
|------|---------|
| **Feature** | A module a clinic can have on or off, by its code (`portal.online_booking`). |
| **Status** | `available` (Disponible), `beta` (Bêta), `internal` (Interne, Kiveto's own use), `upcoming` (À venir: listed, greyed, never on — its module does not exist yet). |
| **Default** | Whether a new clinic starts with it: pre-ticked at the third step of « Nouvelle clinique ». |
| **Legacy state** | The state of a clinic that never had the feature set: a clinic created before the catalogue keeps the modules it already had (`FeatureDefinition::legacyOn`). |

## The catalogue

| Code | Name | Initial status | Default | Legacy | Gates |
|------|------|----------------|---------|--------|-------|
| `compliance.controlled_substances` | Registre des stupéfiants | À venir | — | off | nothing yet |
| `portal.online_booking` | Prise de RDV en ligne | Disponible | on | on | Scheduling: `IsOnlineBookingOpen` and every online booking (`OnlineSlotChecker`) |
| `notification.sms_reminders` | Rappels SMS | Disponible | on | on | Scheduling: `RequestAppointmentReminder` drops the reminder |
| `clinic.hospitalisation` | Hospitalisation | À venir | — | off | nothing yet |
| `billing.electronic_invoicing` | Facturation électronique | Bêta | off | on | Billing: `RequestDocumentTransmission` leaves the document waiting (the reconciliation sends it once the feature is on) |
| `support.write_mode` | Écriture en session support | Interne | off | off | the console's support sessions |

## Model

- `FeatureSettings` — status and default of a feature; a feature never saved reads its definition's initial settings. `change()` refuses an upcoming feature and the status « À venir », and answers the changes in French for the history.
- `ClinicFeature` — on or off for one clinic, when and by whom.
- `FeatureResolver` — the one place that decides, in this order: « À venir » (off everywhere), cut off everywhere (the kill switch, `CutFeature` / `RestoreFeature`), kept for a plan the clinic is not on (`FeatureAudience`, plans read through `ClinicPlansInterface` only for such a feature), the clinic's own setting, else the legacy state. Its `FeatureDecision` says what decided.
- Tables `feature_catalog__features` (code, status, default_on, updated_at) and `feature_catalog__clinic_features` (clinic, code, enabled, changed_at, changed_by_id, changed_by_name), `DbalFeatureRepository`.

## Use cases

| Use case | What it does |
|----------|--------------|
| `SetClinicFeature(clinicId, code, enabled, actorId, actorName)` | One clinic, one feature; answers whether the state in force changed. An upcoming or unknown feature is refused (`DomainException`). |
| `ApplyStartingFeatures(clinicId, enabledCodes, actorId, actorName)` | A new clinic: every feature that exists is set, on when ticked. |
| `ChangeFeatureSettings(code, status, defaultOn)` | « Réglages globaux »; answers the changes. |
| `ListFeatures(clinicIds)` | Every feature with how many of the given clinics have it and its last change. |
| `ListClinicFeatures(clinicIds)` | code → clinic → `ClinicFeatureView` (state, since when, by whom). |
| `IsFeatureEnabled(clinicId, code)` | For the modules it gates; an unknown code answers false. |

## Cross-BC Dependencies

| Direction | Counterpart | Mechanism |
|-----------|-------------|-----------|
| Consumed by | Scheduling | `ClinicFeaturesInterface` (`QueryBusClinicFeatures` → `IsFeatureEnabled`): online booking, SMS reminders |
| Consumed by | Billing | `ElectronicInvoicingFeatureInterface` (`QueryBusElectronicInvoicingFeature`) |
| Consumed by | Presentation (console) | every use case; the audit entries (`feature.*`) are written by the console |

The consumers take their port as an optional constructor argument: wired in the
container, absent in the unit tests that do not concern it.
