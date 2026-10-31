# Prescription Bounded Context

The **Prescription** Bounded Context owns the ordonnance: what a practitioner prescribed during a consultation, for which animal and to which owner. A `DRAFT` is a working document; `issue()` signs it (number, validity); `renew()` records a pharmacy re-presentation; `cancel()` voids it. One controlled medicine (`RX_CONTROLLED`, `RX_NARCOTIC`) pulls the whole document into the strict regime: 28 days at most, no renewal.

## Client Portal integration

**Integration events.** `IssuePrescriptionHandler` and `CancelPrescriptionHandler` publish the aggregate's domain events on the event bus; two bridges (`Infrastructure/Messaging/Consumer/`) turn them into thin integration events written to the outbox in the same transaction:

- `PrescriptionIssuedIntegrationEvent{prescriptionId, clinicId}` — from `PrescriptionIssued`.
- `PrescriptionCancelledIntegrationEvent{prescriptionId, clinicId}` — from `PrescriptionCancelled`.

Consumers enrich them through queries (`GetPrescriptionDetails` exposes `ownerClientId`, `patientAnimalId`, `prescriberName`…).

**Renewable by the owner.** `Prescription::isRenewableByOwner()` — the prescription is `ISSUED` and carries no controlled substance. An expired prescription still qualifies (the owner asks the clinic for a new one). The rule lives once in `Prescription::renewableByOwner(status, carriesControlledSubstance)` and is exposed as `PrescriptionDetails::$renewableByOwner`.

**Queries.**

- `ListPrescriptionPortalSummaries(clinicId, prescriptionIds)` → `list<PrescriptionPortalSummary{id, status, validUntil, renewableByOwner}>` — the live state of a Portal page in one SQL read; ids of another clinic and malformed ids are left out, an empty list returns `[]`.
- `ListPortalPublishablePrescriptions(issuedFrom, afterId, limit)` → `list<PortalPublishablePrescription{prescriptionId, clinicId}>` — `ISSUED` prescriptions with an owner, issued at or after `issuedFrom`, all clinics, ordered by id with a keyset after `afterId` (go-live import).
