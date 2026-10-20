# ElectronicInvoicing System Bounded Context

## Purpose

Transmits neutral fiscal documents (invoices 380, credit notes 381) to an
organisation's approved platform (« plateforme agréée », PA) under the French
e-invoicing reform, and follows the lifecycle statuses the platform reports.
It renders the EN16931 CII D22B file, validates it against the UN/CEFACT XSD,
deposits it, polls the platform for lifecycle messages (CDAR) and publishes
the current status of every document as an integration event.

## Guiding rules

- **Neutral.** It knows no approved platform in particular and nothing
  veterinary: it transmits `TransmittableInvoice`s (parties, lines, VAT
  breakdown, notes) and nothing else.
- **Ports modelled on XP Z12-013 semantics** (`FlowSubmitterInterface`,
  `FlowSearcherInterface`, `FlowFetcherInterface`, `DirectoryLookupInterface`).
  The real platform adapter comes later (T4); in T1 the `LocalPlatformSimulator`
  implements them without any network.
- **Every platform call happens in an `ExternalApiCommandInterface` handler**
  (`async_external` / `async_external_batch`), outside any database
  transaction, and never writes itself: it dispatches a transactional
  *record* command on the command bus (network-only → record-only split).
- **Dependency direction**: Contexts → ElectronicInvoicing, never the reverse.
  Code outside this BC may import only its `Application\Command\…`,
  `Application\Query\…` and `Domain\Event\*IntegrationEvent`; this BC imports
  no `App\Context\…` and no `App\Presentation\…` (guarded by
  `ElectronicInvoicingBoundaryTest`).

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **PlatformConnection** | An organisation's (SIREN) link to its platform — one per SIREN, shared by every establishment carrying it. `PENDING → VERIFIED | REJECTED`; holds the synchronisation `SyncCursor`. |
| **SyncCursor** | `(updatedAfter, lastFlowId)`: flows are read strictly after it, ordered by `(updatedAt, flowId)`; it never moves backwards. |
| **OutboundTransmission** | One document on its way to the platform: `PREPARED → SUBMITTING → SUBMITTED → ACCEPTED | INADMISSIBLE`, or `BLOCKED` (requires action); a credit note may also be `AWAITING_INVOICE` or `NOT_REQUIRED` (internal). Optimistic lock on `version`; `sequence` numbers its published changes. |
| **TrackingId** | The id the issuing context knows a document by; a transmission is unique on it. |
| **FlowId** | The id the platform gives a deposit. |
| **DocumentReference** | The rendered, stored file (storage id + sha256): frozen once `SUBMITTING`, the same bytes are sent on every retry. |
| **LifecycleStatusCode** | Annex A statuses 200–228, 500, 501 with their French labels; after 210 (refused) or 213 (rejected) the document no longer exists for the tax authority. |
| **BlockReason** | `RENDERING_FAILED`, `ORGANISATION_MISMATCH`, `PLATFORM_REFUSED`, `SUBMISSION_ATTEMPTS_EXHAUSTED`, `DUPLICATE_FLOW`. |

## Business Invariants

1. One transmission per tracking id and per (seller SIREN, kind, document number).
2. A transmission is submitted only through a VERIFIED connection whose
   organisation is the seller (BT-30); otherwise `ORGANISATION_MISMATCH`.
3. The file is rendered and stored once; retries resubmit the same bytes, the
   platform contract being idempotent on (tracking id, sha256).
4. A credit note is never sent before its invoice is submitted: it waits
   (`AWAITING_INVOICE`, no submission attempt counted) while the invoice is
   not submitted — queued, blocked, inadmissible or not requested yet — and is
   released as soon as it is. It stays internal (`NOT_REQUIRED`, « Avoir
   interne ») only when its invoice was refused (210) or rejected (213).
5. What the platform reports is never lost: a receipt, a flow id or a status
   is recorded whatever the local state (waiting, blocked, internal) and moves
   the transmission to submitted; only a double deposit stays `BLOCKED`.
   Statuses are recorded once per (status, source flow).
6. Two writers never overwrite each other: a concurrent change fails the
   flush (`version`) and Messenger retries the message.
7. A flow that cannot be fetched or parsed, or that names an unknown
   document, is quarantined (`electronic_invoicing__unprocessed_flows`: raw
   content, error, attempts), logged as an error on `external_api`, retried by
   the next runs; the cursor moves on and the other flows are processed.
8. `NON_TRANSMISE` is only ever reported by the platform, never fabricated.

## Use Cases

| Command / Query | Bus | What it does |
|---|---|---|
| `RequestInvoiceTransmission` | command | Entry point: records the neutral document (no rendering, no network); queues `SubmitTransmission` when the organisation has a verified connection, else waits (`pending_connection`). Idempotent on the tracking id. |
| `SubmitTransmission` | external (`async_external`) | First time: directory lookup (any failure → `0225` + buyer SIREN), CII rendering + XSD validation, storage, `RecordSubmissionPrepared`; then the deposit and `RecordSubmissionReceipt`. A credit note whose invoice is not submitted is settled instead (`SettleCreditNote`). |
| `RecordSubmissionPrepared` / `RecordSubmissionReceipt` / `RecordTransmissionBlocked` / `RecordLifecycleStatus` / `RecordUnprocessedFlow` | command | Transactional, idempotent record steps. |
| `SettleCreditNote` | command | Puts a credit note where its invoice allows: waiting, internal, or released and queued. Dispatched for each waiting credit note when its invoice is submitted or reports a status. |
| `OpenPlatformConnection` / `VerifyPlatformConnection` | command | Opens / verifies an organisation's connection; activation releases what waited (`ReleasePendingTransmissions`). Refused when no platform is configured. |
| `SyncPlatformFlows` | external (`async_external_batch`) | Retries unprocessed flows, then reads new flows page by page (50 × 10 max), parses the CDAR, records statuses, advances the cursor to the last processed flow. |
| `SyncAllPlatforms` | command (scheduled every 5 min, schedule `electronic_invoicing`) | One sync per polled organisation, resend of overdue submissions (at most once per 15 min, row-by-row marker), release of orphaned waits. |
| `GetTransmissionStatus` | query | The current view of a document. |

Every change publishes `InvoiceTransmissionStatusChangedIntegrationEvent` (current
view + strictly increasing `sequence`) through the outbox.

## Platforms

`ELECTRONIC_INVOICING_PLATFORM` picks the adapter when a port is first used
(`PlatformSelector`): `disabled` (production default until the real adapter,
T4 — documents wait, no connection can be opened) or `simulator` (dev, tests,
demo; refused in production at runtime).

The **`LocalPlatformSimulator`** implements the four ports without network and
persists its flows in `electronic_invoicing__simulated_flows`. It validates the
deposited file against the XSD (invalid → 501 `IRR_SYNTAX`) and plays a
scenario chosen by the buyer SIREN: `000002139` rejected (213 `REJ_SEMAN`),
`000002105` refused (… 210 `DOUBLON`), `000002071` disputed (… 207
`TX_TVA_ERR`), `000005017` inadmissible (501), `000000000` buyer without a
platform (200 `NON_TRANSMISE`, not reachable in the directory), any other
nominal (200 → 202 → 203 → 205). Each status is its own flow
(`<depositFlowId>-NN`) whose content is a CDAR read back by the real parser.
Steps are played by delayed `AdvanceSimulatedFlow` messages
(`%electronic_invoicing.simulator.step_delay%`: 30 s in dev, 0 in tests).

Console:

- `app:electronic-invoicing:connect <siren> [--simulator]` — opens (and with
  `--simulator` verifies at once) the connection of an organisation.
- `app:electronic-invoicing:simulate <sellerSiren> <documentNumber> <statusCode> [--reason=CODE]`
  appends a status; `--advance-all` plays every pending step now.

## Formats

- `CiiInvoiceRenderer` — CII D22B (`XMLWriter`), amounts from integer minor
  units without float, validated by `XsdValidator` against the vendored
  UN/CEFACT D22B uncoupled XSD (`Infrastructure/Resources/xsd/cii-d22b`).
- `CdarParser` — tolerant (local names), keyed on `ProcessConditionCode`;
  no CDAR XSD is available (T3 reminder).
