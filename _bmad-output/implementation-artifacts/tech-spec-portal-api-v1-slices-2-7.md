---
title: 'Portal API v1 — slices 2 to 7 (documents, pets, booking, consents, requests, organic arrival)'
slug: 'portal-api-v1-slices-2-7'
created: '2026-10-31'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7.4', 'PHP 8.5', 'API Platform 4', 'Doctrine ORM + DBAL', 'MySQL 8.4', 'Messenger (Doctrine transport)', 'lexik JWT', 'Gotenberg', 'Twig', 'PHPUnit 12', 'Foundry 2', 'DAMA']
files_to_modify: ['openapi/portal-api-v1.md', 'openapi/portal.v1.yaml', 'src/Presentation/Api/Portal/**', 'src/Context/ClientPortal/**', 'src/Context/Billing/**', 'src/Context/Prescription/**', 'src/Context/Consultation/**', 'src/Context/Animal/**', 'src/Context/Scheduling/**', 'src/Context/Client/**', 'src/Context/ClinicListing/** (new)', 'src/System/IdentityAccess/**', 'src/System/Notification/**', 'src/Presentation/Clinic/**', 'assets/js/pages/**', 'templates/**', 'config/services.yaml', 'config/packages/security.yaml', 'migrations/**', 'fixtures/**', 'tests/**']
code_patterns: ['DTO resources + one Provider/Processor per operation', 'PortalProblemMap single error table', 'ports + QueryBus/CommandBus adapters per BC', 'schema-only entities + DBAL repositories', 'integration event bridge + consumer', 'transactional mutex row', 'single-action controllers + page JS modules']
test_patterns: ['unit with in-memory fakes', 'DBAL repository integration tests', 'pipeline tests', 'PortalApiTestCase API tests with real OTP sign-in', 'Foundry fixtures and stories', '100% coverage per BC']
---

# Tech-Spec: Portal API v1 — slices 2 to 7 (documents, pets, booking, consents, requests, organic arrival)

**Created:** 2026-10-31

## Overview

### Problem Statement

Slice 1 of the Portal API v1 (contract, API Platform foundation, OTP sign-in, JWT + rotating refresh, PORTAL users, clinic-initiated invitation, `ClientLink`, `GET /me`) is implemented and merged on `master` (spec: `tech-spec-portal-api-v1-foundation-invitation.md`). The contract `openapi/portal-api-v1.md` describes slices 2 to 7, but they have no implementation, several contract points are still open decisions, and parts of the contract assume a `Document` bounded context that will not be created. The mobile app (Flutter, `kiveto-mobile`) cannot go past an empty home screen.

### Solution

One spec covering slices 2 to 7, with every decision taken now. Implementation is still delivered as one branch per slice, in contract order (2 → 7), each with `make ci` green; every task and AC is tagged with its slice, and the adversarial review is run slice by slice. The contract is updated first with the decisions below.

### Scope

**In Scope:**
- Contract update (`openapi/portal-api-v1.md` + `openapi/portal.v1.yaml` export): decisions below, open decisions closed, `Document` BC removed from the DDD table, `GET /me/booking-requests` added.
- Slice 2 — Published documents (screen 5): publication registry in `ClientPortal`, read/unread, `GET /clinics/{c}/documents`, `GET /clinics/{c}/documents/{id}/file`, `GET /clinics/{c}/prescriptions/{id}`, `unreadDocuments` in `GET /me`; VetApp « Partager sur le Portal » action on a closed consultation.
- Slice 3 — Pets and history (screens 1, 2): pets, pet detail, care items, timeline, vaccination booklet PDF, full home.
- Slice 4 — Online booking (screens 3, 4, 12, 13, 14, 16): `OnlineBookingSettings`, motifs, practitioners, availability, create (two routes), list, detail, reschedule, cancel, `.ics`; Scheduling integration events (only the three `PendingAppointment*` events for portal bookings, D19).
- Slice 5 — Consents and account (screen 6): per-channel consents and history in `Client`, archived clients receive no optional message, `PATCH /me`, verified email change, data export, account deletion (push devices and notification preferences moved to the open decision « Push »).
- Slice 6 — Requests (screens 2, 15, 17): pre-visit questionnaire, pet change requests, prescription renewal requests, and their queue in VetApp.
- Slice 7 — Organic arrival (screens 7, 9, 10, 11): free registration (`registrationToken`), link suggestions, `ClinicListing`, clinic listing page, new-client booking through the listing, `GET /me/booking-requests`.

**Out of Scope:**
- Flutter app work (`kiveto-mobile`).
- `lab_result` and `certificate` producers (types stay in the enum, nothing publishes them yet).
- Belgian/Swiss mobile prefixes (added to `MobileNumberPolicy` on a pilot clinic's request).
- S3 / pre-signed storage (the signed-route 302 keeps the contract unchanged for a later switch).
- Configurable questionnaire per clinic (v1 fixed; `version` field reserved).
- Unclaimed clinic listings, `POST /clinic-listings/{l}/notify-me` (post-alpha).
- Email verification flow beyond what `PATCH /me` requires (decided in slice 5 tasks).

## Context for Development

### Codebase Patterns

**API (`src/Presentation/Api/Portal/`, slice 1)**
- API Platform 4, mapping `src/Presentation/Api/Portal/Resource` (+ `tests/Fixtures/Api` in `when@test`), `stateless`, `pagination_enabled: false`, `doctrine.enabled: false`, formats `json` + `jsonproblem`, docs off in prod. Routes `config/routes/api.yaml` prefix `/api/portal/v1`, host `portal.kiveto.local` (hardcoded, also in `security.yaml` and `framework.yaml` trusted hosts).
- Firewall `portal_api` (`lazy`), provider `PortalApiUserProvider`, authenticator `Security/PublicRouteAwareJwtAuthenticator`; public paths regex `^/api/portal/v1/(auth/otp|auth/refresh|invitations|legal|docs|_test/)` — public routes added later (`/clinic-listings…`, `/auth/register`) must extend it.
- Resource = `final readonly` class with `#[ApiResource(shortName, operations: [new Get|Post(uriTemplate, openapi: new Operation(tags, summary, responses), provider|processor, input?, output?, status?)])]`, French OpenAPI text, responses list the problem codes. Inputs: public properties + `#[Assert\…]` with French messages. Outputs: `View/*` `final readonly` with `#[ApiProperty(schema: …)]` for enums/formats and static factories.
- Provider `implements ProviderInterface<T>`; Processor `implements ProcessorInterface<In, Out>`; current user: `$this->security->getUser()` asserted `PortalApiUser`, `->id()`. Processors `match` on outcome enums and `throw PortalProblemMap::problem(PortalProblemMap::X)`.
- Errors: single table `Problem/PortalProblemMap` (`CODE` constants + `PROBLEMS[code] => [status, French title]`, `problem()`, `response()`); `Presentation/Api/Shared/Problem/ApiProblem`; `ValidationProblemTypeListener` → `validation_failed`. Gaps: `idempotency_key_*` built inline in `IdempotentProcessor` (not in the map), `cursor_invalid` absent from the map and `InvalidCursorException` uncaught, no `not_found` code.
- Tenant guard: query `ClientPortal\Application\Query\HasConfirmedClientLink(userId, clinicId): bool` exists (README: « the Portal API's tenant guard ») but **nothing in Presentation calls it** — slices 2+ need a guard helper in `Support/` returning 404 (never 403).
- Pagination: `src/Shared/Application/Pagination/{Cursor, CursorPage, InvalidCursorException}` (`fromPosition`, `decode`, `clampLimit` default 20 / max 50, `encode`, `position`); no route uses it yet, no `{items, nextCursor}` view.
- Idempotency: `Presentation/Api/Shared/Idempotency/IdempotentProcessor` decorates `api_platform.state_processor.main`; opt-in `extraProperties: ['idempotent' => true]`; store `shared__idempotency_keys` (24 h). Only the test resource uses it today.
- Rate limits: `limiter.portal_*` services in `config/services.yaml` (~L219–305), DB storage `shared__rate_limiter_cache`; `Support/PortalRateLimits` is OTP-specific (always `otp_rate_limited`).
- `Support/`: `ApiDate`, `PortalAccountViews`, `AcceptanceResponder`, `InvitationReader`, `PhoneMasker`.
- `MeProvider` asks `ListOwnerClinics(userId)`; `tags: []` and `unreadDocuments: 0` hardcoded in the provider.

**Bounded contexts**
- One folder per use case: `Application/Command/<Name>/{<Name>, <Name>Handler}`, `Application/Query/<Name>/{<Name>, <Name>Handler, <Dto>}`; handlers `#[AsMessageHandler]` `final readonly` `__invoke`.
- Ports `Application/Port/*Interface` (+ DTOs alongside); adapters `Infrastructure/Adapter/<OtherBC>/QueryBus<Port>|CommandBus<Port>`; each port aliased explicitly in `config/services.yaml` (~L320–340 for ClientPortal).
- ClientPortal persistence: schema-only Doctrine entities (`Infrastructure/Persistence/Doctrine/Entity/*Entity`, no table name — `BoundedContextPrefixNamingStrategy` gives `client_portal__*`), DBAL repositories `Repository/Dbal*Repository` (raw SQL, `INSERT … AS new ON DUPLICATE KEY UPDATE`, `SqlValues::binary()`, `FOR UPDATE`), migrations `migrations/<BC>/VersionYYYYMMDDHHMMSS.php` (namespace `DoctrineMigrations\<BC>`). Scheduling uses ORM entities + mappers (`AppointmentEntity` with `#[ORM\Version]`).
- Buses (`config/packages/messenger.yaml`): `messenger.bus.command` (validation + `doctrine_transaction`, default), `messenger.bus.query`, `messenger.bus.external_api` (no transaction — network calls such as Gotenberg; routed `async_external`), `messenger.bus.event` (domain events, `sync`), `messenger.bus.integration_event` (`IntegrationEventInterface` → `async` Doctrine transport `shared__messenger_messages`, implicit outbox).
- Integration events: class in the publisher's `Domain/Event/*IntegrationEvent` extending `Shared\Domain\Event\AbstractIntegrationEvent` (`BOUNDED_CONTEXT`, `VERSION`, `aggregateId()`, `payload()`); bridge `Infrastructure/Messaging/Consumer/*IntegrationBridge` (`#[AsMessageHandler(bus: 'messenger.bus.event')]` → `IntegrationEventPublisher::publish()`); consumer `#[AsMessageHandler(bus: 'messenger.bus.integration_event')]` dispatching a local command (pattern: `ClientPortal/…/ClientArchivedIntegrationEventConsumer`; `use` of the other BC's integration event class is the accepted exception).
- Multi-tenancy: no SQL filter; `clinicId` passed explicitly everywhere. `CurrentClinicContext` is session-based → unusable in the API: `{c}` comes from the URI.
- Shared: `FileStorageInterface` (`store`, `storeAt`, `delete`, `getUrl` — public `/storage/…`, never used by the Portal —, `read(): string`, throws `\RuntimeException`), only `LocalFileSystemStorage` (`var/storage`); `DocumentPdfRendererInterface::render(template, context, DocumentFooter)` (Twig + Gotenberg); `ClockInterface` (no `new DateTimeImmutable()` for now). `UriSigner` not used anywhere yet (Symfony ≥ 7.1 supports expiration). File-serving pattern: `Presentation/Clinic/Controller/Stock/StockOrderFileController` (`read()`, `RuntimeException` → 404, `Cache-Control: private, no-store`).

**VetApp**
- Single-action controllers; sheet actions extend `Controller/Shared/AbstractSheetAction` (`guard` CSRF, `clinicId()`, `attempt`, flash) — e.g. `Client/Sheet/SendPortalInvitationController` (PRG + flash). Cockpit JSON endpoints: `Consultation/SendConsultationDocumentsController` (`POST /clinic/consultations/{id}/documents/send`, CSRF via `CockpitEndpoint`, JSON toast).
- Page JS modules (not Stimulus), selected by route name in `assets/app.js` `PAGE_MODULES`, registered in `importmap.php`: `pages/clients/sheet.js`, `pages/consultation.js` (documents panel « hub d'impression » ~L1201–1262, `listeDocs()`, `SENDABLE`), `pages/settings.js`.
- Settings: `Controller/Settings/Show*Controller` + `Update*Controller` pairs, `ClinicPermissionGuard`, sections declared in `ViewModel/Settings/SettingsPageBuilder.php` (`identite, horaires, notifications, agenda, facturation, moi`).
- Dashboard « À traiter »: `ViewModel/Dashboard/DashboardViewBuilder::tasks()` (~L967), `DashboardTask(group, …, audience)`, 4 per source + « +N ». Bell: `PostInAppNotification(clinicId, userIds[], kind, title, detail, route, routeParams, idempotencyKey, ?actorInitials)`, `InAppKind` `NEW_APPOINTMENT|CRITICAL_STOCK|EXPIRING_LOTS|SMS_QUOTA`.

**Domain facts by slice**
- *Slice 2 — documents.*
  - Billing `Invoice` (`InvoiceStatus` DRAFT/ISSUED « Impayée »/PARTIALLY_PAID/PAID/CANCELLED/CREDITED); `InvoiceIssued{invoiceId, clinicId, number, totalGrossMinorUnits, currency, issuedAt, processingRule}` carries no client and no animal; `PaymentRecorded`, `InvoicePaid`, `InvoiceCancelled`, `InvoiceCredited`, `CreditNoteIssued` are domain events only; the only integration event is `ProfessionalDocumentIssuedIntegrationEvent` (B2B). Owner = `Payer::clientId()` (null for counter sales). Animal only through `admissionId` → `GetAdmissionVisit` → `GetPatientAnimalLink`. Durable file: command `EnsureSentInvoiceFile(clinicId, invoiceId)` → `SentDocumentFile{storageKey, filename}` (`billing/sent/invoice/<id>.pdf`, retained). Query `GetInvoiceDetails` (status, payerClientId, admissionId, totals, balance, issuedAt, creditNotes, number).
  - Prescription `Prescription` (DRAFT/ISSUED/CANCELLED, `PatientSnapshot.animalId`, `OwnerSnapshot.clientId` = active primary owner at drafting, `Validity{issuedAt, validUntil}`, `Renewals{allowed, used}` ≤ 12 — pharmacy re-presentations); `PrescriptionIssued`, `PrescriptionRenewed`, `PrescriptionCancelled` domain-only, without client/animal. `renew()` refuses expired, controlled substance, no remaining. `GetPrescriptionDetails` (lines with composed `posology`, `ownerClientId`, `prescriberName`, `isExpired`, renewals…) lacks `patientAnimalId`. `RenderPrescriptionFile(clinicId, prescriptionId, storageKey)` renders to a caller-chosen key (no DB write).
  - Consultation: `close()` → `ConsultationClosed{consultationId, closedByUserId, summary}` (no clinic/patient, never published — the BC publishes no events at all). Report rendered live by `ConsultationReportPdfRendererInterface::report(clinicId, consultationId)`; addenda allowed after close; never stored durably. `SendConsultationDocuments` (sync validation) → `EmailConsultationDocuments` (async render) is the pattern for sharing.
- *Slice 3 — pets.*
  - Animal: `Species`, `Sex`, `ReproductiveStatus` (INTACT/NEUTERED/UNKNOWN = `sterilization`), `isMixedBreed`/`breedName`, `birthDate`, `Identification.microchipNumber`, `LifeCycle.lifeStatus` ALIVE/DECEASED/MISSING, `AnimalStatus` ACTIVE/ARCHIVED, `Ownership` PRIMARY/SECONDARY. `ListAnimalSummariesPerClientIds` (primary, active; does not exclude deceased), `GetAnimalById` → `AnimalView`, `ListAnimalWeightRecords`, `ListAnimalPreventiveCare`. `PreventiveCareRecord{kind vaccination|internal_parasite|external_parasite|other, label, administeredOn, nextDueOn?}` append-only; "latest per kind|lower(label)" rule duplicated in `AnimalSheetBuilder::preventionRows` and `DbalAnimalDirectoryReadRepository::LATEST_ACTS`.
  - Weight: Animal `WeightRecord` + Consultation vitals, merged only in Presentation. Referent vet: does not exist. Patient → Animal: `PatientAnimalLink`, `PatientLinkedToAnimalIntegrationEvent`, `PatientReconciledIntoIntegrationEvent`; Consultation `PatientIdsProviderInterface::findPatientIdsForAnimal(s)`. `SearchConsultations(restrictToAnimalIds, statuses, page, limit)` → `ConsultationListItemView{practitionerUserId, motifs[], startedAtUtc, closedAtUtc, chiefComplaint}`; visit title rule in `DossierVisitReader::visitTitle` (Presentation).
  - Clinic timezone: `GetClinic` → `ClinicDto::$timeZone`. No vaccination booklet template exists.
- *Slice 4 — booking.*
  - Scheduling `Appointment` (ORM `scheduling__appointments`, `ownerId`/`animalId` nullable strings, `PractitionerAssignee` = Scheduling `UserId`, `TimeSlot`, free `reason`, `AppointmentStatus` PLANNED/CANCELLED/NO_SHOW/COMPLETED, `cancel(bool $notifyClient)`); events domain-only (README D1). No motif entity (agenda chip = regex on `reason` in `AgendaViewBuilder::motif`).
  - Agendas are `PlanningBlock`s (type, `TimeRange` local, `capacityPerHour`, `RecurrenceRule`, expanded by `RecurrenceExpander`; `acceptsAppointments()` false for leave/training/admin/break); rule « SOFT: no block = allowed ». No slot/availability computation anywhere.
  - Overlap: `DbalAppointmentConflictChecker::hasOverlap` (`status='PLANNED'`, no lock, no constraint → races); capacity `DbalPlanningBlockAppointmentCounter` same race; `'PLANNED'` literals in ~12 files across Presentation, Consultation and Admission raw SQL.
  - Commands `ScheduleAppointment`, `RescheduleAppointment` (no block/capacity check), `CancelAppointment`; queries `ListAppointmentsForOwner` (DESC, limit 200, no scope/cursor), `ListAppointmentsForAnimal`, `GetAppointmentDetails`. Messages via `ScheduleAppointmentNotificationSync` → `AppointmentNotificationState::nextMessage` (PLANNED → CONFIRMATION/CHANGE, CANCELLED after confirmation → CANCELLATION); purposes `APPOINTMENT_*`. Settings storage `scheduling__clinic_settings`.
  - Clinic: `OpeningHours` JSON (ISO day → `OpeningSlot` local HH:MM + `ExceptionalClosure` whole days), `time_zone`, address in `Letterhead.address` (`PostalAddress`, no lat/lng). Scheduling has no port to opening hours.
- *Slice 5 — consents.*
  - Client `communicationPreferences` JSON on `client__clients` `{preferredChannel, consents[{kind, channels, grantedAt}]}`; `ConsentKind` = the five contract kinds; `Consent` only accepts automated channels (`sms`, `email`); no history; withdraw = delete; `UpdateClientCommunicationPreferences` is a full replace without source/author; `Client::updateCommunicationPreferences` calls `ensureNotArchived()`; event `ClientCommunicationPreferencesUpdated` (state, no diff). Consent semantics (tech-spec-notifications-brevo ~L152): only the SMS appointment reminder is blocked by a missing consent; other kinds pick the channel.
  - Notification: channels `EMAIL`, `SMS` (Brevo) only; no push at all; each sender BC reads `GetClientNotificationContact`.
  - Account: `User::registerPortal(…, ?email)`, `emailVerifiedAt` never set for PORTAL, no email change; `OwnerProfile{firstName, lastName, ?address, termsVersion, termsAcceptedAt}` with only `createFromClient` and `acceptTerms`. No GDPR export/anonymisation anywhere (invoices and prescriptions already snapshot payer/owner).
- *Slice 6 — requests.* No request/inbox aggregate; dashboard tasks + bell are the VetApp surfaces. No questionnaire concept. `RenewPrescription` = pharmacy re-presentation (different meaning from an owner renewal request).
- *Slice 7 — organic arrival.*
  - `VerifyPortalLoginOtpHandler` returns `REGISTRATION_REQUIRED` without token; onboarding grant (`OtpChallenge::grantOnboarding`…) reusable with a distinct purpose; `User::registerPortal` only called from `ClaimPortalIdentityHandler` (invitation-bound); `PortalUserRegistered` has no consumer.
  - `ListClientIdsByPhone(clinicId, phone)` is single-tenant and includes archived; `client__contact_methods` has no index on `value`.
  - `ClientLink` has no `pending` factory.
  - Clinic: status active/suspended/closed, `Letterhead{address, phone, email, website}`; no geo, no `acceptsNewClients`, no tags, no listing opt-in; events domain-only (`ClinicRenamed`, `ClinicOpeningHoursUpdated`, `ClinicLetterheadUpdated`, `ClinicSuspended/Activated/Closed`…). Geocoding adapter to mirror: Client `GeoplateformeAddressDirectory`.
  - `CreateClient(…, animals: list<NewAnimalDto>)` (generated id; `NewAnimalDto` requires sex, reproductiveStatus, isMixedBreed).

**Tests**
- Unit `tests/Unit/Context/<BC>/…` with in-memory fakes (`tests/Unit/Context/ClientPortal/Support/{InMemory*, ClientPortalWorld, SettableClock, AnsweringCommandBus}`); integration `tests/Integration/Context/<BC>/{Infrastructure/Persistence/Doctrine/Repository/Dbal*Test, Pipeline/*PipelineTest}`; API `tests/Integration/Presentation/Api/Portal/*Test` extending `PortalApiTestCase` (`BASE`, `post`, `get`, `json`, `problemType`, `bus()`, `signIn(phone)` through the real OTP flow, `connection()`); VetApp `tests/Integration/Presentation/<App>/…`.
- `phpunit.xml` (`failOn*`, DAMA rollback per test, suites `unit`, `integration`); 100 % per BC is a manual gate (`make test-coverage`). Real rate limiters → unique phones/IPs per test.
- Fixtures `fixtures/<Context|System>/<BC>/{Factory,Story}` (Foundry 2), `fixtures/Dataset/PortalDataset.php` loaded by `fixtures/DevDataset.php`, `ClientPortalStatesStory`, `PortalUserFactory`.
- Contract gate: `make openapi-check` (in `make ci`) diffs `openapi/portal.v1.yaml`; regenerate with `make openapi-export`.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `openapi/portal-api-v1.md` | Portal API v1 contract (source of truth for routes, shapes, errors) |
| `openapi/portal.v1.yaml` | Exported OpenAPI, checked by `make openapi-check` |
| `_bmad-output/implementation-artifacts/tech-spec-portal-api-v1-foundation-invitation.md` | Slice 1 spec: foundation decisions D1–D29 reused here |
| `src/Presentation/Api/Portal/Resource/Me.php`, `State/MeProvider.php`, `State/VerifyOtpProcessor.php` | Resource / provider / processor patterns |
| `src/Presentation/Api/Portal/Problem/PortalProblemMap.php` | Single error map |
| `src/Presentation/Api/Shared/Idempotency/IdempotentProcessor.php` | Idempotency opt-in |
| `src/Shared/Application/Pagination/Cursor.php` | Cursor pagination |
| `src/Context/ClientPortal/Application/Query/HasConfirmedClientLink/` | Tenant guard query |
| `src/Context/ClientPortal/Infrastructure/Messaging/Consumer/ClientArchivedIntegrationEventConsumer.php`, `ClientLinkConfirmedIntegrationBridge.php` | Integration event consume / publish patterns |
| `src/Context/ClientPortal/Application/Query/ListOwnerClinics/` | `GET /me` clinics |
| `src/Context/Billing/Domain/Invoice.php`, `Application/Command/EnsureSentInvoiceFile/`, `Application/Query/GetInvoiceDetails/` | Invoice lifecycle and durable PDF |
| `src/Context/Prescription/Domain/Prescription.php`, `Application/Command/RenderPrescriptionFile/`, `Application/Query/GetPrescriptionDetails/` | Prescription lifecycle, file, detail |
| `src/Context/Consultation/Application/Command/SendConsultationDocuments/`, `EmailConsultationDocuments/`, `Application/Port/ConsultationReportPdfRendererInterface.php` | Report rendering and the share flow pattern |
| `src/Presentation/Clinic/Controller/Consultation/SendConsultationDocumentsController.php`, `View/ConsultationDetailsController.php`, `assets/js/pages/consultation.js` | VetApp consultation documents panel |
| `src/Presentation/Clinic/Controller/Stock/StockOrderFileController.php` | Stored-file serving pattern |
| `src/Context/Animal/Domain/Animal.php`, `PreventiveCareRecord.php`, `Application/Query/{GetAnimalById,ListAnimalPreventiveCare,ListAnimalWeightRecords,ListAnimalSummariesPerClientIds}/` | Pets, care, weight |
| `src/Presentation/Clinic/ViewModel/Animal/AnimalSheetBuilder.php`, `DossierVisitReader` | Existing latest-care, weight merge and visit-title rules |
| `src/Context/Consultation/Application/Query/SearchConsultations/` | Visit list base |
| `src/Context/Scheduling/Domain/{Appointment.php, PlanningBlock.php, Service/RecurrenceExpander.php}`, `README.md` | Booking domain, rule D1 |
| `src/Context/Scheduling/Infrastructure/…/DbalAppointmentConflictChecker.php`, `DbalPlanningBlockAppointmentCounter.php`, `DbalPlanningBlockFinder.php` | Overlap and capacity checks |
| `src/Context/Scheduling/…/AppointmentNotificationState.php`, `ScheduleAppointmentNotificationSync` | Client messages on appointments |
| `src/Presentation/Clinic/Controller/Settings/{ShowAgendaSettingsController,UpdateAgendaSettingsController}.php`, `ViewModel/Settings/SettingsPageBuilder.php` | VetApp settings pattern |
| `src/Context/Clinic/Domain/{Clinic.php, ValueObject/OpeningHours.php, Letterhead}` | Opening hours, timezone, address |
| `src/Context/Client/Domain/ValueObject/{ConsentKind,ContactChannel,CommunicationPreferences}.php`, `Application/Command/UpdateClientCommunicationPreferences/` | Consents |
| `src/Presentation/Clinic/Controller/Client/Sheet/UpdateClientCommunicationPreferencesController.php` | Counter-side consent editing |
| `src/System/IdentityAccess/Domain/User.php`, `Application/Command/{VerifyPortalLoginOtp,ClaimPortalIdentity}/` | Registration hooks |
| `src/Context/ClientPortal/Domain/{Link/ClientLink.php, Profile/OwnerProfile.php}` | Link and profile aggregates |
| `src/Context/Client/Application/Command/CreateClient/`, `Application/Query/ListClientIdsByPhone/` | Clinic-side client creation, phone lookup |
| `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php`, `src/System/Notification/Application/Command/PostInAppNotification/` | VetApp task and bell surfaces |

### Technical Decisions

Decisions taken in step 1 (user, 2026-10-31):

- **Single spec, sliced delivery**: one spec for slices 2–7; one branch per slice in contract order, `make ci` green on each; tasks and ACs tagged per slice; adversarial review per slice.
- **Document types (slice 2)**: `invoice`, `prescription`, `report` are published; `lab_result` and `certificate` stay in the enum without producer.
- **Publication triggers (slice 2)**: invoices and prescriptions are published automatically on issuance; the consultation report is published only through a « Partager sur le Portal » action on a closed consultation in VetApp — never automatically on close (the vet decides what the owner sees).
- **No `Document` bounded context**: the publication registry (what, for whom, file, read/unread) lives in `ClientPortal`, with ports to Billing, Prescription and Consultation. A dedicated BC only if another consumer appears. The contract's DDD table is updated accordingly.
- **Files**: `GET …/documents/{id}/file` (and the other file routes) answer 302 to a Symfony route signed with `UriSigner`, valid 5 min, served by an `__invoke` controller. The contract does not change; a later switch to S3 pre-signed URLs is invisible to the app.
- **Appointment confirmation mode (slice 4)**: configurable per motif and per client type in `OnlineBookingSettings`; default = confirmed directly for a known client, to be validated by the clinic for a new client.
- **Online cancellation window (slice 4)**: configurable per clinic, default 24 h.
- **Revoked link (slice 5)**: superseded by D5 at the slice 3–7 review — no consent is withdrawn at archive or on link revocation; an archived client receives no optional message (send-time rule, S5c); consents stay intact.
- **Questionnaire (slice 6)**: version 1 fixed, with the contract enums; `version` field allows a per-clinic configurable version later.
- **Foreign numbers**: nothing now.
- **New-client booking before link confirmation (slice 7)**: booking through the listing creates a booking request, without `ClientLink`. The owner sees it through `GET /me/booking-requests` (account-scoped, not clinic-scoped, so the « 404 without confirmed link » rule on `/clinics/{c}/…` stays intact). When the clinic confirms, the link is created and the appointment shows up normally in `/clinics/{c}/appointments`.

Decisions from the investigation (step 2, 2026-10-31), **validated by the user** with the party-mode amendments (P1–P7) and the user's precisions folded in below:

*Cross-cutting*
- X1 — **Live queries, no read projections**: drop `client_portal__pets`, `client_portal__timeline_entries`, `client_portal__care_items`, `client_portal__appointments` from the contract. Consultation and Scheduling publish no events, Animal events do not cover the pet card, data volumes are tiny, and VetApp already builds the same screens live. The only ClientPortal table for slices 2–3 is the publication registry. Integration events are added only where a consumer exists (README Scheduling D1 stays as is unless a consumer needs one). The contract's « Vision » states the rule: **the Portal reads other contexts only through their query buses — never their aggregates nor their tables** — and the `client_portal__*` projection list is removed.
- X2 — **Tenant guard**: `Support/PortalClinicGuard` asks `HasConfirmedClientLink` and throws `404 not_found` (new code); every `/clinics/{c}/…` provider/processor calls it first; resources under `{c}` belonging to another client of the same clinic also answer 404. `cursor_invalid` (400) and `idempotency_key_*` move into `PortalProblemMap`; `InvalidCursorException` is mapped.
- X3 — **List envelope**: one `View/PageView` `{items, nextCursor}` reused by every cursor route.
- X4 — **Branches**: `chore/portal-api-contract-v1-1` (contract for slices 2–7) then `feature/portal-documents`, `feature/portal-pets`, `feature/portal-booking-setup` (BookingMotif, OnlineBookingSettings, VetApp settings, new statuses, `occupiesSlot()` audit, locks — useful without the app), `feature/portal-booking` (availability + API routes) (P4), `feature/portal-consents`, `feature/portal-requests`, `feature/portal-organic-arrival`.

*Slice 2*
- S2a — **Registry** `client_portal__published_documents`: `id`, `clinic_id`, `client_id`, `type`, `source_ref` (invoiceId / prescriptionId / consultationId, unique per clinic+type), `consultation_id` nullable (visit key for the timeline), `patient_id` nullable (resolved at publication, for pet backfill), `pet_ids` JSON, `title`, `issued_at`, `practitioner_name` nullable (report: practitioner snapshotted at each share; prescription: prescriber — feeds `referentVet` and the timeline vet even for departed staff, S3), `storage_key` nullable, `file_version` (current served version, 0 before the first file), `pending_file_version` nullable, `render_attempts`, `status` `awaiting_link|pending_file|available|failed|withdrawn`, `published_at`, `updated_at`; reads per owner in `client_portal__document_reads` (`document_id`, `user_id`, `read_at`). A document is listed only when `available` (or `pending_file` with `file_version ≥ 1` during a re-share — the previous version keeps being served). Field sources per type are in Task 7.
- S2a' — **State machine** (F2): `awaiting_link` → `pending_file` (client has a confirmed link) → `available` (file attached for the expected version) or `failed` (5 failed renders); `available` → `pending_file` on re-share (previous version still served); any state → `withdrawn`, which is **terminal** (no later event revives it). `attachFile(version, key)` is a no-op unless `version = pending_file_version`. A withdraw arriving before the publish inserts a `withdrawn` tombstone, so the late publish hits the unique key and is ignored; publish commands also re-read the live source status and skip a cancelled/draft source.
- S2b — **Integration events, thin payloads** (ids + clinicId, the consumer enriches through query ports): Billing `InvoiceIssuedForPortalIntegrationEvent` (all invoices) and `InvoiceCancelledIntegrationEvent` only (F20 — payment changes need no write, status is read live); Prescription `PrescriptionIssuedIntegrationEvent`, `PrescriptionCancelledIntegrationEvent`. `invoice.paymentStatus` and `prescription.renewable` are read live at list time through batch queries (`ListInvoicePortalStatuses`, `ListPrescriptionPortalSummaries` — F7).
- S2c — **Visibility**: invoice → payer `clientId` (counter sales without client never published); prescription → `OwnerSnapshot.clientId` (null → not published); report → primary owner of the animal at share time (recomputed at each share). **The payer sees their invoice; the owner does not, unless they are the payer** (F13). Every registry read is scoped in SQL to the caller's **confirmed** links of that clinic — never to client ids passed from Presentation (F6) — so a revoked link of the same clinic never leaks, and a later link shows past documents.
- S2c' — **Rendering only for linked clients** (F9): the registry row is always created; the PDF is rendered only when the client has a confirmed link (`awaiting_link` otherwise). On `ClientLinkConfirmed`, the client's `awaiting_link` rows move to `pending_file` and render. `ReconcilePublishedDocuments` (scheduled, idempotent, bounded batches) re-dispatches rows stuck in `pending_file` for more than 10 minutes; after 5 failed renders the row becomes `failed` with an error log. Its import mode publishes invoices and prescriptions issued **within the last 24 months** before go-live (never reports — only an explicit share publishes them); a per-clinic limit comes only on request.
- S2d — **Withdrawal**: cancelled invoice and cancelled prescription → `withdrawn` (terminal, disappear from list, detail and file routes); credited invoice stays, `paymentStatus: credited` (credit notes not published in v1); a row whose live invoice status is not mappable (CANCELLED not yet withdrawn, DRAFT) is excluded from the page with a warning log, never a 500 (F8).
- S2e — **Files**: invoice through `EnsureSentInvoiceFile` (Billing key `billing/sent/invoice/<id>.pdf`, durable); prescription and report rendered into `client_portal/documents/<documentId>-v<n>.pdf` by `StorePublishedDocumentFile` (`ExternalApiCommandInterface`, `async_external`) which calls the synchronous render commands (`RenderPrescriptionFile`, `RenderConsultationReportFile` — plain `CommandInterface`, no DB write) and records the outcome through the short transactional `AttachPublishedDocumentFile` (F4). The report reflects the consultation **at render time** (an addendum added between share and render is included); a re-share sets `pending_file_version = n+1`, the render writes `-v<n+1>.pdf`, then `AttachPublishedDocumentFile` swaps the key, deletes the reads (unread again) and only then schedules the deletion of `-v<n>.pdf` by `DeleteStoredDocumentFile` routed to `async` with a `DelayStamp` ≥ 10 min (F1, F3); a failed re-share render keeps v<n> served and never deletes it. `DeleteStoredDocumentFile` refuses keys outside `client_portal/`. `petIds` resolved at publication (prescription `patientAnimalId`; invoice through admission → patient → animal; report through the consultation's patient); an unreconciled patient gives `[]`, backfilled by `patient_id` on `PatientLinkedToAnimalIntegrationEvent` and repointed + backfilled on `PatientReconciledIntoIntegrationEvent` (F5).
- S2e' — **Signed URLs** (F10): signed over `{c, id, v}` (clinic, document, version), 5 min; the download resolves the key from `(id, v)` (versioned ClientPortal key, or the Billing key for invoices), answers 404 when the row is `withdrawn`, the version unknown or the signature invalid/expired; `Cache-Control: private, no-store` on the 302 and on the PDF.
- S2f — **VetApp**: « Partager sur le Portal » in the documents panel of a CLOSED consultation (`ShareConsultationReport`, sync validation: closed, animal with primary owner; disabled with a reason otherwise); same permission as « Envoyer par e-mail »; the registry row is locked `FOR UPDATE` during the share (double click safe); state shown (« Partagé le … »). For an owner without Portal account the button stays active and the toast says « Partagé — visible dès que le client activera son espace Portal » (F18).

*Slice 3*
- S3a — **Pets**:
  - **Visible pets**: animals with status ACTIVE whose **active primary owner** is one of the caller's confirmed-linked clients of that clinic, and whose `transferStatus` is `none`.
  - **Excluded entirely, answering 404** (D4): archived, secondary-owner, sold and given (`transferStatus` `sold|given`) animals.
  - **Deceased and missing pets**: `lifeStatus` `alive|deceased|missing` is added to the contract. These pets stay in the list and the detail, but are excluded from care items, home `pets[]`, home `careItems[]`, booking and `nextAppointment`.
  - **Every pet route checks ownership**: the pet must be visible to the caller, else 404 `not_found`.
- S3b — **Weight** (D3): the most recent of two sources:
  - Animal `WeightRecord`s;
  - vitals of **CLOSED** consultations, shared or not. Open consultations are ignored, since their vitals can still change.
  - **Comparison**: on the clinic-local date. A vitals measurement is dated by the consultation's `startedAtUtc`, converted to the clinic timezone. On the same date the Animal record wins.
  - **Format**: `weightGrams = (int) round(weightKg × 1000)`. `lastWeight` is `{ weightGrams, at: YYYY-MM-DD }`, or null.
- S3c — **`referentVet`**: derived from the practitioner of the most recent **listable report row of that pet scoped to the caller's confirmed clients**; null without one.
  - **Source**: the name comes from a **snapshot stored on the registry row at share time** (`practitioner_name`, slice-2 change). A departed vet keeps their name and no staff lookup happens per request.
  - **Fallback**: a null snapshot gives `referentVet: null`.
- S3d — **Care items** (D2):
  - **New Animal query** `ListLatestPreventiveCare(clinicId, animalIds[])`: the latest act of each protocol `(animal_id, kind, LOWER(label))`. It ranks **all** records by `administered_on DESC, recorded_at DESC` and keeps rank 1, then the caller filters. An older act with a due date never resurfaces once the protocol was renewed without one. It returns `{animalId, kind, label, administeredOn, batchNumber, nextDueOn (nullable)}`.
  - **Portal filtering**: the Portal drops rows with a null `nextDueOn`.
  - **The VetApp copies of the rule stay as they are in slice 3** (explicitly deferred): `AnimalSheetBuilder::preventionRows`, `CockpitContextBuilder::due`, `ClientSheetBuilder` (raw SQL), `DbalAnimalDirectoryReadRepository::LATEST_ACTS`.
  - **Statuses**: `due_soon` window **30 days**, `today` = clinic-local date. Precedence overdue (`dueOn < today`) > due_soon (`dueOn ≤ today + 30 d`) > up_to_date. **Slice 3 never emits `booked`**.
  - **Slice 4b adds `booked`**: an occupying appointment of that pet whose `BookingMotif.preventiveCareKind` equals the item's kind, starting on a clinic-local date in `[today, max(dueOn, today) + 30 d]`. Precedence then becomes booked > overdue > due_soon > up_to_date.
  - **Ordering**: care items are ordered `dueOn ASC`, so overdue items come first. Home items carry `pet { id, name }`.
- S3e — **Timeline** = report rows of the pet in the publication registry:
  - **Scope**: listable rows, `type = report`, `JSON_CONTAINS(pet_ids, petId)`, **scoped to the caller's confirmed clients** (same SQL join as Task 8). A pet re-homed from client A to client B only shows B the reports addressed to B.
  - **Order and cursor**: cursor `(issued_at, id)`, ordered `issued_at DESC, id DESC`, where `issued_at` = consultation close time. `limit` goes through `Cursor::clampLimit`. An invalid cursor gives 400 `cursor_invalid`.
  - **Displayed fields**: `date` = consultation `startedAtUtc` (instant, clinic offset). `vet` = registry snapshot. `title` from the batched Consultation query `GetConsultationPortalSummaries(clinicId, consultationIds[])`, which calls the pure service `VisitTitle::of(motifs, chiefComplaint)`.
  - **`documents[]`**: listable rows with the same `consultation_id`, scoped to the caller's confirmed clients (S2c: an invoice only for its payer).
  - **Index**: the `(clinic_id, client_id, status, issued_at, id)` registry index, with `type` and `JSON_CONTAINS` as residual filters, accepted at Portal volumes.
- S3f — **Vaccination booklet**:
  - **Template**: `templates/portal/document/vaccination_booklet.html.twig`.
  - **Rendering**: by the application service `VaccinationBookletFiles::ensure(clinicId, animalId)`, called directly with no bus. The ownership check (`GetOwnerPet`) runs first.
  - **Cache key**: SHA-256 of the canonical JSON of every render input: vaccination records as returned by `ListAnimalPreventiveCare`, animal identity, clinic header and `TEMPLATE_VERSION`.
  - **Old files**: when a new key is written, the previous booklet of that animal is deleted by a delayed `DeleteStoredDocumentFile` (≥ 10 min, prefix `client_portal/booklets/<animalId>-`).
  - **Zero vaccinations**: a PDF with « Aucune vaccination enregistrée ».
  - **Served by** the signed route.
- S3g — **Timezone and dates**:
  - **`/me`**: `clinics[].timezone` (IANA) is added to `GET /me` through the existing Clinic port. `ClinicLabel` gains `timeZone`, and `ClinicNamesInterface` is renamed `ClinicProfileInterface`, a single port for `/me` and slice 3.
  - **Instants** (`timeline.date`, `nextAppointment.startAt`, documents `issuedAt`) are emitted with the clinic offset (`ApiDate::formatIn($at, $tz)`).
  - **Date-only fields** (`birthDate`, `dueOn`, `lastDoneOn`, `lastWeight.at`) are `YYYY-MM-DD` with **no timezone conversion** (`ApiDate::date()`).
- S3h — **Home `nextAppointment`**:
  - **Query**: new Scheduling query `GetNextAppointmentForAnimals(clinicId, animalIds[], nowUtc)`. It takes the earliest appointment with `starts_at_utc >= now` among the caller's **alive visible pets**, with status PLANNED in slice 3 (`occupiesSlot()` from slice 4a). Appointments without animal or for an invisible pet are never returned.
  - **Shape in slice 3**: `{ id, startAt, pet { id, name }, practitioner (nullable name), motifLabel }`.
  - **`motifLabel`**: a fixed French label from the appointment category, `vaccin` → « Vaccination », `suivi` → « Contrôle », `bilan` → « Bilan », `chir` → « Chirurgie », `urg` → « Urgence », otherwise « Consultation ». The category comes from the classification rule moved from `AgendaViewBuilder::motif` into Scheduling (`AppointmentCategory::fromReason`). The raw `reason` and `notes` are never exposed.
  - **`practitioner`**: from a batched Clinic lookup.
  - **Slice 4b** replaces the shape with the full appointment view.
- S3i — **Domain → contract mapping** (Task 16 table):
  - `breed` = `breedName`, else « Croisé » when `isMixedBreed`, else null;
  - `initial` = first letter of `name`, uppercased;
  - `species` = `dog|cat|nac|other` and `sex` = `male|female|unknown`, values passed through;
  - `sterilization` = `reproductiveStatus` (`intact|neutered|unknown`);
  - `chipNumberMasked` = `•••• ` + last 4 characters, or null.

*Slice 4*
- S4a — **Statuses**:
  - New non-terminal `PENDING_CLINIC_CONFIRMATION` and new terminal `DECLINED`. `AppointmentStatus::isTerminal()` = CANCELLED, NO_SHOW, COMPLETED, DECLINED.
  - Column `status` widened to `VARCHAR(32)` (`AppointmentEntity.php:43` is `length: 20`, and the new value has 27 characters).
  - Portal `confirmed` = PLANNED. A cancellation is CANCELLED + `cancelledBy` (`clinic|client`) + `?cancellationReason`. `source` is `clinic|portal`, NOT NULL DEFAULT `clinic`. Existing CANCELLED rows are backfilled `cancelled_by = 'clinic'`.
  - `AppointmentStatus::occupiesSlot(): bool` (PLANNED, PENDING_CLINIC_CONFIRMATION) and `AppointmentStatus::OCCUPYING_SQL_VALUES` are the **single source of truth** inside Scheduling.
  - Outside Scheduling, nobody re-derives the meaning of a status string. Scheduling's read DTOs (`AppointmentItem`, `OwnerAppointmentItem`, `AnimalAppointmentItem`, the agenda rows, `GetAppointmentDetails`) gain `occupiesSlot: bool` and `isPending: bool`, computed in Scheduling. Presentation reads those flags. No status lists are mirrored into other BCs (S4a-F5).
  - Every Scheduling literal is classified as « occupies the slot » or « confirmed only » in the Task 19 table. Reminders, the « Nouveau RDV » bell and the notification sync stay on PLANNED (S4a-F4).
  - Every UI that negates CANCELLED (`!== 'CANCELLED'`, `r.st !== 'annule'`) treats DECLINED like CANCELLED and shows PENDING as « à confirmer » (S4a-F3).
  - Behaviour of a PENDING appointment (D18):
    - **Check-in at the desk confirms it implicitly**: `confirm(silent: true)` records `confirmedSilently`, so the notification state marks the slot as confirmed without sending CONFIRMATION, because the client is on site.
    - **A clinic cancel of a pending appointment is a `decline()`**, and the `APPOINTMENT_DECLINED` message is sent.
    - `startService()` (consultation started from the appointment, through `MessengerSchedulingServiceCoordinator`) also confirms silently: the client is on site, same reason as check-in.
  - `markNoShow()` is **refused** (`AppointmentNotModifiableException`).
  - Reschedule is allowed on PLANNED and PENDING. On PENDING it recomputes `pending_expires_at` (S4a-F14).
- S4b — **`BookingMotif`** (Scheduling, new tables):
  - Fields: `label`, `description`, `durationMin`, `onlineEnabled`, `confirmationKnownClient` (`auto|manual`, default auto), `allowedBlockTypes[]`, bookable practitioners, optional `preventiveCareKind`, `newClientsAllowed`, `sortOrder`, `archivedAt`.
  - **No `confirmationNewClient`** (D15): a new client always needs the clinic's confirmation.
  - A portal booking writes `reason` = the motif label, so agenda chips and messages keep working.
  - The portal never exposes the raw `reason` of an appointment. It shows `motif {id, label}` from `BookingMotif`, or the fixed label « Rendez-vous » for an appointment without motif (S4b-F13).
- S4c — **Availability (online = HARD)**:
  - Slots exist only inside occurrences of planning blocks whose type the motif allows, for a practitioner bookable for the motif and still eligible (`MembershipEligibilityCheckerInterface`).
  - **Minus every occurrence of any non-accepting block of that practitioner** (LEAVE, TRAINING, ADMIN, BREAK), whatever its anchor date (S4b-F1).
  - Intersected with the clinic's opening hours (adjacent opening slots merged) minus exceptional closures, read through a Scheduling → Clinic port on `GetClinicSettings`. An empty opening week gives no online slot and a VetApp settings warning « Horaires d'ouverture requis » (S4b-F11). A clinic that is not active gives `booking_not_open`.
  - **Capacity**: one domain function `BlockCapacity::of(capacityPerHour, windowMinutes)`, used by `ScheduleAppointment`, `RescheduleAppointment` and the calculator.
    - It keeps today's semantics: `floor(capacityPerHour × max(blockMinutes, slotMinutes) / 60)`, counted per block occurrence over occupying statuses. 0 means unlimited.
    - A full block yields no slot.
    - Block minutes are computed in local minutes, not from a UTC difference (S4b-F2, S4b-F12).
  - **Settings**: grid step, minimum lead time, manual lead time and booking horizon are `OnlineBookingSettings` fields (defaults 15 min, 120 min, 24 h, 60 days), each with validation bounds (S4b-F9).
  - When the booking would be pending (manual mode for a known client, or always for a new client), slots start only at `now + max(minLeadTime, minLeadTimeManualHours)`.
  - **Request shape**:
    - `from` and `to` are clinic-local `YYYY-MM-DD`, inclusive, with `to − from ≤ 13` days and `from ≥` the clinic's local today; otherwise 422.
    - Days beyond today + `horizonDays` are `open: false`.
    - `open` = an opening day, not an exceptional closure, within the horizon.
  - **DST**: candidates are built by iterating local minutes-of-day as integers and creating `DateTimeImmutable::createFromFormat('!Y-m-d H:i', …, $tz)`. A candidate whose `format('H:i')` differs from the requested time does not exist (spring gap) and is dropped. Candidates are deduplicated by UTC instant (autumn repeat). Slots are emitted with their offset (S4b-F12).
  - Unknown, archived or non-online motif, or practitioner not bookable for it → 404 `not_found`. Settings disabled → 422 `booking_not_open` on motifs, practitioners and availability alike (S4b-F10).
  - VetApp keeps the SOFT rule.
- S4d — **Double booking**:
  - A transactional per-practitioner-day mutex row (`scheduling__practitioner_day_locks`, `INSERT … ON DUPLICATE KEY UPDATE`).
  - Taken by `ScheduleAppointment`, `RescheduleAppointment`, the online booking commands, `Confirm`/`Decline`, **and the planning-block mutations** (`CreatePlanningBlock`, `UpdatePlanningBlock`, `DeletePlanningBlock`, S4b-F17).
  - It is taken before the overlap and capacity checks, and **for every clinic-local date the slot `[start, end)` touches**, so 23:30–00:30 locks both days (S4a-F6).
  - Keys are sorted by (practitionerId, localDate). A reschedule locks the union of its old and new keys (P1).
  - **Once the lock is held, the overlap and capacity counts are current reads** (`… FOR SHARE`), through new port methods `hasOverlapLocked` / `countOccupyingInWindowLocked`. Under REPEATABLE READ (MySQL 8.4 default, no override) the snapshot of a plain SELECT taken before the lock would not see a booking committed meanwhile (S4a-F1).
  - The capacity counter gains `?excludeAppointmentId`, as the conflict checker already has, so a move inside a full block is possible (S4b-F3).
  - A `DeadlockException` raised while locking is retried twice by the lock adapter, then surfaces as 409 `slot_taken` (S4a-F13).
  - The lock table keeps one row per practitioner-day. A monthly purge deletes rows older than 30 days (`PurgePractitionerDayLocks`, scheduled).
  - VetApp `RescheduleAppointment` gains the same SOFT block-type and capacity checks as `ScheduleAppointment` (no block = allowed; block of a refusing type or full = refused) (S4a-F18).
- S4e — **Messages and bells**:
  - **Messages**:
    - A pending appointment sends nothing, and gets no reminder and no `NEW_APPOINTMENT` bell. Those stay on PLANNED.
    - `confirm()` → the normal CONFIRMATION (except the silent confirmation at check-in, D18).
    - `decline()` → new purpose `APPOINTMENT_DECLINED` (« Votre demande de rendez-vous du {date} n'a pas pu être confirmée — choisissez un autre créneau dans l'app Kiveto. », P7), sent **once**: new `declineNotifiedAt` on the notification state (S4a-F7).
    - A client-side cancel or reschedule of a confirmed appointment keeps the usual CANCELLATION and CHANGE messages as a receipt.
    - A new client (no `ownerId`) gets its decline from ClientPortal in slice 7, through the integration events of S4j.
  - **Bells**:
    - New `InAppKind::ONLINE_BOOKING_PENDING`, posted to the practitioner on `AppointmentRequested` (key `online-booking-pending:<id>`, title « RDV en ligne à confirmer · <animal> », route `clinic_agenda` at the appointment date). It is withdrawn on Confirmed, Declined and Cancelled (S4a-F11).
    - A confirm posts no `NEW_APPOINTMENT`: the practitioner already knew from the pending bell.
    - New `InAppKind::ONLINE_BOOKING_CHANGED` (« RDV modifié en ligne » / « RDV annulé en ligne ») to the old and new practitioner on a portal cancel or reschedule. It is posted inside Scheduling, with no integration event (S4b-F15).
  - **Auto-decline** (P2): `pending_expires_at` = min(createdAt + 48 h, startsAt − `AUTO_DECLINE_MARGIN`), with the named constant `OnlineBookingSettings::AUTO_DECLINE_MARGIN = 2 h`. `minLeadTimeManualHours` has a lower bound of 3 h, so a pending booking can never expire at creation.
    - The 15-minute scan queues one `DeclineExpiredPendingAppointment(id)` per expired row on `async`. Each handler locks the row `FOR UPDATE` and re-checks status and expiry, so one failure never holds back the others (pattern of `RequestDueRemindersHandler`).
    - Confirm and Decline also load `FOR UPDATE`.
    - A confirm after `pending_expires_at` is refused with « Demande expirée ». A losing concurrent confirm shows a flash, never a 500 (S4a-F8).
- S4f — **Cancellation window** per clinic (default 24 h, bounds 0–168 h), applied to cancel **and** reschedule.
  - `cancellableUntil = max(startsAt − window, createdAt + 15 min)`, only while `now < startsAt`. A booking made inside the window can be undone for 15 minutes (D12).
  - The action is allowed when `now ≤ cancellableUntil`, else 422 `cancellation_window_closed`. `cancellableUntil` is null for non-occupying statuses (S4b-F8).
  - Cancel is naturally idempotent: an appointment already `cancelled_by_client` answers 200 with the same body. Acting on any other terminal status (DECLINED, COMPLETED, NO_SHOW, cancelled by the clinic) → 422 `appointment_not_modifiable` (S4b-F7).
  - Portal reschedule:
    - only for `source = portal` appointments that carry a motif, else 422 `booking_not_open`;
    - the new practitioner must be bookable for the motif;
    - the status is kept;
    - a pending reschedule applies the manual lead time and recomputes `pending_expires_at` (S4b-F3).
- S4g — **`with-new-pet`** (D1): the contract's « Vision » is amended. Creating the owner's own new animal at booking time is an explicit exception to « writes become requests ».
  - The animal is created through the Animal command `CreateAnimal` (`src/Context/Animal/Application/Command/CreateAnimal/CreateAnimal.php`) with these values:
    - `species` ∈ `dog|cat|nac|other` (contract enum);
    - `sex = 'unknown'`, `reproductiveStatus = 'unknown'`, `isMixedBreed = false`, `registryType = 'none'`, `lifeStatus = 'alive'`, `transferStatus = 'none'`;
    - every nullable field null;
    - `primaryOwnerClientId` = the caller's linked client, `secondaryOwnerClientIds = []`.
  - It uses the adapter pattern of `src/Context/Client/Infrastructure/Adapter/Animal/CommandBusClientAnimalRegistry.php`.
  - It runs in the same command-bus transaction as the booking: `slot_taken` leaves no animal.
  - **Retry dedup**: an ACTIVE animal with the same primary owner, case-insensitive name and species, created in the last 24 h, is reused.
  - **On decline or expiry**, the animal created for that booking is archived (`ArchiveAnimal`) when it has no other appointment and no clinical record (no patient link). This runs through `AppointmentDeclined`, inside Scheduling via an Animal port.
- S4h — **VetApp**:
  - **Settings page.** A new settings section « Réservation en ligne » (`reservation-en-ligne`): general settings, a motif list editor and the practitioners per motif. Eligible practitioners come from the Clinic membership query restricted to VETERINARY and VETERINARY_ASSISTANT, as `ScheduleAppointmentHandler` does.
  - **Agenda.** Pending appointments appear in the agenda (`assets/js/pages/scheduling/agenda.js`), as a dashed card with an « À confirmer » badge and Confirm / Decline in the popover. On a pending card, drag-move is allowed; check-in confirms silently; no-show is hidden.
  - **Dashboard.** They also appear as dashboard tasks (« RDV en ligne à confirmer », `group: 'jour'`).
  - **Permission.** Confirm and Decline require the new guard constant `ClinicPermissionGuard::MANAGE_APPOINTMENTS`, mapped to `ClinicPermission::MANAGE_APPOINTMENTS`, which already exists (`src/Context/Clinic/Domain/Staff/ClinicPermission.php:12`) (S4a-F15).
- S4i — **Lists**: a new Scheduling query `ListAppointmentsForOwners` (Task 27) replaces any use of `ListAppointmentsForOwner` by the Portal. `ListAppointmentsForOwner` is left untouched for VetApp.
  - It **never selects `notes` nor `reason`** and joins no other BC table: pet names come from ClientPortal's `PetNamesInterface`.
  - It filters `owner_id IN (linked clients) OR animal_id IN (their primary-owner pets)` and orders by `(starts_at_utc, id)` keyset.
  - `upcoming` = occupying and `startsAt ≥ now`, ascending. `past` = the rest, descending. `limit` is clamped.
- S4j — **Minimal integration events** (D19), slice 4a: Scheduling publishes **only** `PendingAppointmentConfirmedIntegrationEvent`, `PendingAppointmentDeclinedIntegrationEvent` and `PendingAppointmentCancelledIntegrationEvent`, and **only for appointments with `source = portal` that were pending**.
  - Payload: `{appointmentId, clinicId, ownerId?, animalId?, startsAtUtc, reason: declined|expired|clinic_cancelled}` for the declined event.
  - Check-in silent confirmation also publishes Confirmed.
  - README rule D1 is relaxed for these three, with the justification written: slice 7's ClientPortal must learn that a pending new-client booking was confirmed (to create the link), declined or expired (to send `PORTAL_BOOKING_DECLINED`, since the appointment has no owner to message), or acted upon from the agenda.
  - Everything else stays domain-only. The clinic bell for online cancel and reschedule stays inside Scheduling, with no event.
- S4k — **Care-item `booked`** (D2, introduced in slice 4b):
  - `CareItemStatusPolicy` gains `booked` when an occupying appointment of that pet, whose motif carries the item's `preventiveCareKind`, starts in `[today, max(dueOn, today) + 30 d]`. Dates are compared on the clinic-local date of `starts_at_utc`.
  - Precedence: booked > overdue > due_soon > up_to_date.
  - Appointments without a motif never count.

*Slice 5*
- S5a — **Consent channels**: contract fixed — consents only carry `sms|email` (code invariant, `Consent::__construct`); `preferredChannel` keeps the four. **Editable kinds (D9)**: the owner toggles only the optional kinds `marketing`, `appointment_reminders`, `vaccination_reminders` (constant `ConsentKind::OPTIONAL`), whose withdrawal stops a send; `billing` and `clinical_documents` are returned with `effect: channel_preference` and edited as a channel choice only (at least one reachable channel, empty list → 422 `validation_failed`), because those messages are sent anyway and the consent only picks the channel (notifications-brevo spec « Consent rules »).
- S5b — **History** `client__consent_history` (`id`, `clinic_id`, `client_id`, `kind`, `channel`, `action granted|withdrawn`, `at`, `source portal|clinic|system`, `actor_type portal_user|staff|system`, `actor_id` nullable, `author_label`, `wording_version`, `approximate` bool), one row per `(kind, channel)` change, computed as a diff by `CommunicationPreferences::revise()`; an empty diff writes nothing. Existing consents backfilled with `JSON_TABLE` as `granted` / `source: system` / author « Reprise » / `approximate = true` / `at` = `grantedAt` (an upper bound: `revise()` re-dates a consent when a channel is added); NULL or malformed JSON → no row. Every writer records its source and actor: VetApp form and `CreateClient` (all callers) → `clinic` + staff, Portal → `portal` + PORTAL user. The Portal shows staff rows as « La clinique » (no staff name). New per-kind command `SetClientConsent(clinicId, clientId, kind, channels, source, actorType, actorId, authorLabel)`; both consent commands load the client `FOR UPDATE`, and the VetApp form posts the `version` it rendered — a stale save is refused with a flash (« Les consentements ont changé depuis l'ouverture de la fiche »), a residual `OptimisticLockException` maps to 409 on the Portal. `ClientCommunicationPreferencesUpdated` stays v1 (no consumer exists; history is the record).
- S5c — **Archived client = no optional message** (D5, replaces the former « withdrawal on revoked link »): archiving and link revocation **never** withdraw a consent; consents and their history stay intact, so an unarchived client gets them back untouched. The rule « an archived client receives no optional message » is enforced **by the sending contexts at send time**, through the single helper they already read: `ClientNotificationContact::channelsFor(kind)` returns `[]` for an optional kind when `archived` is true (`ClientNotificationContact.php` already exposes `archived`). Today the only optional message sent is the SMS appointment reminder, already gated on `!archived` in `RequestAppointmentReminderHandler.php:114`; no vaccination-reminder or marketing sender exists, so any future one inherits the rule through `channelsFor()`. Adding a consent to an archived client stays refused (`ensureNotArchived()`); no withdrawal exception is introduced. `ClientLinkRevoked` gains no `reason` and no integration event (no consumer needs one).
- S5d — **`/me/notification-preferences` and `/me/devices` (POST, DELETE) leave v1** and become the open decision « Push » in the contract: no push transport exists, no sender would read account-level preferences, and tokens stored now would be stale when push arrives. sms/email stay governed by per-clinic consents.
- S5e — **Profile and email** — `PATCH /me`: `firstName`, `lastName` (1–60 chars, trimmed, non-empty), `address` (structured `PostalAddress`, no geocoding) on `OwnerProfile` only (no propagation to clinic `Client` records). Email change: `POST /me/email-change/otp` → SMS code (OTP purpose `email_change`, bound to the user) → `POST /me/email-change {challengeId, code, email}` → an `email_verification` challenge (bound to the user, carrying the target address, 10 min, 5 attempts) sends a code to the new address and returns its `challengeId` → `POST /me/email-change/verify {challengeId, code}`. The same flow re-verifies the current unverified email. **Collision (D8)** is checked at `/verify`, after the address is proven: a verified holder → 422 `email_taken`; an unverified holder loses the address (its `email` set to NULL); the previous verified address of the caller receives a notice. **Email as login factor (D6)**: once verified, `POST /auth/otp/{id}/resend {channel: email}` is accepted **for `login` challenges only** — this deliberately reverses slice-1 D18 « no email channel »: a verified email becomes a second login factor; action challenges (`email_change`, `account_deletion`, `email_verification`) are never resent by email. A resend that finds no verified email leaves the challenge untouched (the SMS code stays valid) and answers the same 202. Action codes go through `portal_otp_phone`, `portal_otp_phone_daily`, `portal_otp_ip` and use per-purpose SMS wording.
- S5f — **Data export (D7)**: `POST /me/data-export` → 202; built asynchronously (`BuildDataExport`, `ExternalApiCommandInterface` on `async_external`, outcome recorded by a short transactional `RecordDataExport`); content = the canonical table of Task 36 (`schemaVersion: 1`), extended by slices 6 and 7. Downloaded **in the app**: `GET /me/data-exports` (status), `GET /me/data-exports/{id}/file` (authenticated) → 302 to a 5-minute signed URL; the email (when a verified one exists) and the in-app status only notify — no download link in the email; no email requirement (`email_not_verified` dropped). Stored 48 h under `client_portal/exports/<id>.zip`, then deleted by a delayed message plus a nightly sweep of exports older than 48 h; deleted at account deletion. 1 per 24 h per user (`limiter.portal_data_export`, consumed when the request is accepted, refunded if the build fails) → 429 `export_rate_limited`.
- S5g — **Deletion request**: confirmed by an SMS code (`POST /me/deletion-request/otp`, then `POST /me/deletion-request {challengeId, code}`, OTP purpose `account_deletion`). **In the 202 transaction**: user DISABLED (every access token refused at once by `PortalApiUserProvider`), all refresh tokens revoked (`revokeAllForUser`). **Async cascade** (idempotent, under the `identity_access__portal_phone_locks` row lock): revoke every `ClientLink`, pending invitations, onboarding grants and open challenges of the phone; anonymise `OwnerProfile` (« Compte supprimé ») and `User` (`phone`, `email`, `emailVerifiedAt` → NULL, `anonymisedAt` set); purge or null the stores listed in Task 37. It does **not** touch the clinic `Client` records nor their consents. A new invitation to that phone creates a new account (organic registration arrives in slice 7).

*Slice 6*
- S6a — **ClientPortal requests**: three aggregates in `ClientPortal/Domain/Request/` — `PreVisitQuestionnaire` (one per appointment), `PetChangeRequest`, `PrescriptionRenewalRequest`. Each stores `clinicId`, `clientId` (the confirmed link at submit), `userId`, its subject (`appointmentId` / `petId` / `prescriptionId`), `submittedAt`, `updatedAt`; change requests also store `currentValueAtSubmit` (snapshot read from Animal) and renewals `resultingPrescriptionId` (nullable). Statuses: change and renewal requests `submitted → done | rejected | closed` (`closed` = terminal, with a reason `link_revoked|client_archived|account_deleted|prescription_cancelled|pet_archived|pet_deceased`, never notified, hidden from queues — S6-F16); questionnaire `submitted` or `closed` (appointment cancelled/declined). Resolution fields: `resolvedAt`, `resolvedByUserId`, `resolvedByName`, `resolutionMessage` (≤ 280 chars), `notificationMessageId` (S6-F3). Transitions guarded in the domain (`PortalRequestAlreadyResolvedException` unless `submitted`; a questionnaire is never resolved) and resolved under `FOR UPDATE` (S6-F4).
- S6b — **Ownership and lock rules** (S6-F2, S6-F9): every write is scoped to the caller's confirmed links of `{c}` — pet = ACTIVE, alive, primary-owner animal of one of those clients (deceased, missing or archived → 422 `pet_not_eligible`); appointment = owner or animal among those clients; prescription = a listable registry row of those clients (S2c), not merely ISSUED in Prescription. Foreign or unknown subject → 404 `not_found`. The questionnaire is editable iff the appointment `occupiesSlot()`, `startsAt > now` and no consultation was started from it; otherwise 422 `questionnaire_locked` (cancelled/declined appointment → 404). A reschedule never reopens a locked questionnaire (lock is recorded: `lockedAt`).
- S6c — **Renewal** (D10): `renewable` = `renewableByOwner` from slice 2 (ISSUED, no controlled substance, expired allowed) **and** the pet is alive (ClientPortal-side refinement) **and** no request on it was `rejected` in the last 7 days (else 422 `prescription_not_renewable`, `reason: recently_rejected`). « Traiter » is a **status flag**: the vet does the work in VetApp as usual and, optionally, links the resulting prescription (`resultingPrescriptionId`, chosen among that pet's ISSUED prescriptions issued after the request); `RenewPrescription` (pharmacy re-presentation) is never offered from the request. `renewalStatus` on the prescription = `{status, requestId, resultingPrescriptionId?}` of its latest request. At most one `submitted` request per prescription, enforced by a unique key on a generated column (S6-F8); a second submit answers **202 with the existing `{requestId, status}`** (no 409).
- S6d — **Owner read path** (D11): `GET /clinics/{c}/requests` (cursor) lists the caller's change and renewal requests `{id, kind, petId, field?, proposedValue?, prescriptionId?, status, submittedAt, resolvedAt?, resolutionMessage?, resultingPrescriptionId?}`. **No owner cancellation or edit of a submitted change/renewal request in v1** (stated in the contract); the questionnaire stays editable under S6b.
- S6e — **Field validation** (S6-F10): `name` 1–60 chars; `weight` decimal with comma or dot, 0.05–150 kg, normalised to `weightGrams`, optional `measuredOn ≤ today` (default submit date); `sterilization` ∈ `neutered|intact`; `chip_number` `^\d{15}$`; `other` requires `note`. Lengths: `note` ≤ 500, `proposedValue` ≤ 120, questionnaire `ongoingTreatment.details` ≤ 500, `notes` ≤ 1000. Limiter `limiter.portal_requests` (10 / 24 h per user+clinic) and at most 3 open change requests per pet+field → 429 `rate_limited` (S6-F11). Questionnaire `version` must be `1` (else 422).
- S6f — **Owner notification on resolution** (S6-F6): always sent on « Traiter » / « Refuser » with a fixed French sentence (« Votre demande de {kind} pour {petName} a été traitée. » / « … n'a pas pu être acceptée. ») plus the staff message when present; purpose `PORTAL_REQUEST_RESOLVED`, clinic-sent, `NON_CRITICAL`, transactional channel of the client (`ClientNotificationContact::transactionalChannel()` exposed on `PortalClientContact`), idempotency key `portal-request-resolved:<id>`; unreachable client → logged and the request shows « client injoignable ». Never sent for `closed`.
- S6g — **VetApp « Demandes Portal »** (S6-F12…F15, F18): list + detail (current-at-submit vs current vs proposed value), « Traiter » / « Refuser » with optional message; permissions: viewing and change requests `MANAGE_ANIMALS`, renewals `CREATE_PRESCRIPTION` (both from `ClinicPermission`, added to `ClinicPermissionGuard`); dashboard tasks `group: jour` (renewal with `adverse_effects` → `urgent`), new icon `portal`, audience `veto` for renewals and `asv` for change requests, « +N » summary task « N autres demandes Portal »; questionnaires are **not** queue items (shown on the agenda popover and the cockpit, with `updatedAt` and a « peut mordre » chip). Bell `InAppKind::PORTAL_REQUEST`: renewal → the prescription's prescriber (fallback: all vets), change request → `MANAGE_ANIMALS` holders; title/detail only fixed labels + pet and client names, never owner free text; one bell per request, idempotency key `portal-request:<id>`. Owner free text is rendered with `textContent` / `esc()` everywhere, never `|raw` (S6-F13).

*Slice 7*
- S7a — **Registration** (S7-F2, F3, F19):
  - `registrationToken` is an onboarding grant with an explicit purpose. `identity_access__otp_challenges` gains `onboarding_purpose` (`invitation|registration`, NULL when no grant) and `onboarding_consumed_at`.
  - `OtpChallenge::grantOnboarding(hash, purpose, now)` and `consumeRegistrationGrant(now)`. A registration grant is **single use**; an invitation grant keeps its 15-minute multi-invitation reuse.
  - `findByOnboardingHashForUpdate(hash, purpose)` filters on purpose, so neither grant is accepted by the other route.
  - `revokeOnboardingGrantsForPhone` only touches `invitation` grants.
  - `POST /auth/register` is **one command transaction**, orchestrated by the ClientPortal command `CompletePortalRegistration`, which calls IdentityAccess `RegisterPortalUser` through the `PortalIdentityInterface` port. Order:
    1. current terms check (`terms_outdated`);
    2. grant `FOR UPDATE` + consumption;
    3. per-phone mutex (slice 1 D7);
    4. an ACTIVE PORTAL user already exists for the phone → `409 account_exists` (the grant is not consumed; the transaction rolls back), no profile is created or overwritten;
    5. `addPortalIfPhoneFree`;
    6. `OwnerProfile::register()`.
  - `email?` follows the slice-1 `freeEmail` rule: dropped silently when already used by another PORTAL user, never verified.
  - **Replay**: while the grant's 15 minutes run, the same token presented again by the user it created returns the same 201 body with a new token pair (the contract's « Rejeu » convention). After expiry → `401 onboarding_invalid`.
  - Per-IP limiter `limiter.portal_register` (10/h).
  - The `onboarding_invalid` title becomes neutral (« Jeton expiré ou invalide »).
- S7a' — **Unknown-number SMS budget** (S7-F4, D13). Organic registration makes unknown numbers a first-class path, so the budget gets three layers, checked in this order inside `RequestPortalOtpHandler` for login codes to unknown numbers:
  1. a network sub-limit `limiter.portal_otp_unknown_network`, keyed by the client IP's `/24` in IPv4 and `/64` in IPv6 (default 20/h);
  2. a device hint `limiter.portal_otp_unknown_device`, keyed by the optional `X-Device-Id` header (default 5/h). It is **only an extra signal, never the sole guard**: the header is client-controlled and forgeable, and a missing or rotating header still hits the network and global limits;
  3. the global cap `limiter.portal_otp_unknown_global`, raised to **500/h**, still configurable through `PORTAL_OTP_UNKNOWN_HOURLY_CAP`.

  Exhausting any layer keeps the slice-1 behaviour (challenge created, `sms_suppressed`, same 202). Global exhaustion also trips `limiter.portal_otp_unknown_alert` → `logger->critical`. The app shows « Vous n'avez pas reçu de code ? Réessayez dans quelques minutes » after the resend delay (no distinct API signal, to avoid an oracle).
- S7b — **Link suggestions** (S7-F10, F11, F12, D17):
  - Computed on `GET /me/link-suggestions` by a new cross-tenant Client query `ListClientsByMobilePhone(phone)`. The query is `type = 'phone' AND label = 'mobile' AND value = :e164` on `client__contact_methods`, client not archived, clinic `active` (suspended and closed clinics excluded), index `(type, value, client_id)`.
  - **One suggestion per clinic**: family files sharing the mobile are grouped, and accepting opens a `pending` link to every matching client file of that clinic.
  - Excludes clinics where the user already has a link (any status) to every matching file. A client file already confirmed-linked to **another** account is **kept** (families share files) but flagged for staff.
  - Exposes only `clinic { name, city }` and `matchedOn: phone`. The suggestion id is an HMAC-SHA256 of `(userId, clinicId)` keyed by `%kernel.secret%`, so no client id is exposed.
  - Accept → `ClientLink::requestFromSuggestion()` (`pending`, `linkedAt` null) → VetApp « Demandes Portal » request « Rattachement ».
  - The request shows the Portal profile name next to each file's name and animals, with a **mismatch badge** when names differ, and a « déjà relié à un autre compte Portal » badge. Confirm sets `confirmed` (existing link reactivated, never duplicated); reject revokes it (`link_rejected`), and the suggestion for that clinic does not come back.
  - `GET /me` lists `pending` and `confirmed` clinics. When a clinic has both, `confirmed` wins and `linkedAt` is nullable.
  - Risk accepted: a recycled number reveals clinic names only, never data, and nothing is linked without staff confirming against the shown names.
- S7c — **`ClinicListing` BC** (S7-F13, F14, F15, F16, F21):
  - **Two tables**: `clinic_listing__listing_settings` (owned by ClinicListing, edited in VetApp settings « Annuaire »: `listed` default false, `accepts_new_clients`, `tags`) and `clinic_listing__clinic_listings` (the projection).
  - A projection row is **never deleted**: `active = false` when the clinic or its clinic group is suspended or closed.
  - Refreshed by a re-read bridge on Clinic domain events through a thin `ClinicProfileChangedIntegrationEvent` (incl. `ClinicGroupSuspended`/`ClinicGroupActivated` fanned out to member clinics). Also refreshed **synchronously** by `UpdateListingSettings`, and in bulk by the console command `clinic-listing:rebuild` (run at deploy and by the dev dataset).
  - **Geocoding** is a separate `ExternalApiCommandInterface` (`GeocodeClinicListing`, `async_external`) followed by the short transactional `AttachListingCoordinates`. It is skipped when the hash of the normalised address is unchanged. On failure the previous coordinates are kept, with a bounded retry (Messenger's 3). A clinic without letterhead address has null coordinates. It is new code on the Géoplateforme geocoding endpoint: the Client adapter only does autocomplete and discards geometry.
  - `onlineBooking` is computed **at read time** through `OnlineBookingStatusInterface` (batched per page) = settings `enabled` ∧ `newClientBookingEnabled` ∧ `accepts_new_clients` ∧ ∃ online motif with `newClientsAllowed`.
  - Search:
    - `q` ≥ 2 characters, LIKE with `%`/`_` escaped, over `name` and `city`;
    - `lat` ∈ [-90, 90], `lng` ∈ [-180, 180];
    - distance by Haversine after a bounding-box prefilter on indexed `lat`/`lng`; rows with null coordinates are excluded from distance results only;
    - keyset cursor `{d, id, lat, lng}` for distance order, `{name, id}` otherwise; a cursor whose `lat/lng` differ from the request → `400 cursor_invalid`.
  - Unlisted or inactive listing → `404 not_found` on every `/clinic-listings/{l}…` route, booking included.
- S7d — **New-client booking** (S7-F5–F9, F17, F18, D14, D15, D16, D19, S4a-F19):
  - `POST /clinic-listings/{l}/booking/appointments` is **authenticated** and **idempotent** (`Idempotency-Key`).
  - It answers `422 already_linked` when the user already has a confirmed link to that clinic: they book through `/clinics/{c}/booking`.
  - It answers `422 booking_request_limit` beyond **1 pending request per clinic and 3 overall** per account, plus a per-user limiter `limiter.portal_listing_booking` (10/day).
  - It creates a ClientPortal `BookingRequest` (table `client_portal__booking_requests`) and, through a Scheduling port, a `PENDING_CLINIC_CONFIRMATION` appointment (`source=portal`, owner and animal null) that holds the slot. New clients are **always** manual: there is no `confirmationNewClient` setting, and listing availability always applies `minLeadTimeManualHours`.
  - The 201 body is the `BookingRequestView`, not the appointment, which the owner cannot read under `/clinics/{c}`.
  - Staff resolve it **only** from « Demandes Portal » « Nouveau client ». The agenda's Confirm action refuses a `source=portal` pending appointment with a null owner and redirects there.
  - « Rattacher à un client existant » proposes the matches of `ListClientsByMobilePhone` in that clinic (non-archived mobiles), with name-mismatch badges, and lets staff pick an existing animal of that client or create `newPet`.
  - « Créer » runs `CreateClient` **without** animals (contact = the account phone, labelled mobile; names from the Portal profile).
  - The animal is then registered through the S4g Animal port (`CreateAnimal`: `sex=unknown`, `reproductiveStatus=unknown`, `isMixedBreed=false`, other identity fields null), which returns its id.
  - The confirmed `ClientLink` is created, or an existing one reactivated. Then the appointment's owner and animal are set through `changeOwnerAndAnimal`, and `confirm()` is called. All of this runs in one command transaction.
  - The user's other pending requests at the same clinic are attached to the same client when confirmed.
  - **Integration events (D19)**: Scheduling publishes only `PendingAppointmentConfirmedIntegrationEvent`, `PendingAppointmentDeclinedIntegrationEvent` and `PendingAppointmentCancelledIntegrationEvent`, `{appointmentId, clinicId, occurredAt}`, for `source=portal` appointments. They are created in slice 4a; README rule D1 is relaxed for these three, with the justification written there.
  - The ClientPortal consumer keeps the `BookingRequest` in sync and is idempotent against an already resolved request:
    - confirmed → `confirmed` + `appointmentId`;
    - declined (staff, auto-decline P2, or account deletion) → `declined`;
    - cancelled by the clinic → `declined` with reason `cancelled`.
  - On decline or cancel it sends the new purpose `PORTAL_BOOKING_DECLINED` (Kiveto-sent, SMS to the **account phone**) through `NotificationRequesterInterface`. The Scheduling owner-addressed `APPOINTMENT_DECLINED` cannot reach an ownerless appointment.
  - `DeletePortalAccount` declines pending requests (no message), frees their slots, and scrubs the request snapshots. `GET /me/booking-requests` is cursor-paginated.

- P6 (OTP rate limits) — the `email_change` and `account_deletion` codes go through the same per-phone limiters as slice 1 (`portal_otp_phone`, `portal_otp_phone_daily`, `portal_otp_ip`).
- P5 (tests) — the S4d lock is tested outside DAMA with two connections, including a concurrent VetApp booking; slot computation tested on both DST days (end of March, end of October) and for an overseas clinic without DST; one test per screen touched by the `occupiesSlot()` audit (agenda, dashboard, client sheet, animal sheet) shows a pending appointment as « à confirmer » and busy.

**Review amendments (adversarial review slices 3–7, 2026-10-31)** — 120 findings (S3-F1…F17, S4a-F1…F19, S4b-F1…F19, S5-F1…F24, S6-F1…F20, S7-F1…F21) applied in their slice's decisions, tasks and ACs, except S5-F6/S5-F7 (dropped with D5). Arbitrations: D1 with-new-pet allowed by the Vision, created animal archived on decline/expiry without record; D2 `booked` from slice 4b only; D3 weight from closed consultations; D4 sold/given pets excluded; **D5 no consent withdrawal — archived clients receive no optional message, enforced at send time (S5c), consents intact on unarchive**; D6 email resend for login codes only, a verified email becomes a login factor; D7 in-app export download; D8 email collision at `/verify`; D9 only optional kinds toggleable; D10 renewal « Traiter » = status + optional `resultingPrescriptionId`; D11 `GET /clinics/{c}/requests`, no owner cancellation; D12 cancellation grace 15 min; D13 unknown-number sub-limit /24 IPv4 and /64 IPv6, device id as a hint only, cap 500/h; D14 1 pending new-client request per clinic, 3 overall; D15 no `confirmationNewClient`; D16 `already_linked`; D17 mismatch badge, no exclusion; D18 check-in confirms a pending appointment implicitly without SMS; **D19 Scheduling publishes only `PendingAppointmentConfirmed|Declined|Cancelled` for portal bookings (slice 4a), README D1 relaxed for them**. New ACs: 110–118 [3], 140–156 [4a/4b], 200–213 [5], 230–244 [6], 260–271 [7]; AC 28 and AC 59 removed.

**Review amendments (adversarial review contract + slice 2, 2026-10-31)** — F1 → S2e, Task 7, AC 11, 93; F2 → S2a', Tasks 6–7, AC 94; F3 → Task 7, AC 11, 95; F4 → S2e, Tasks 5, 7; F5 → S2a, S2e, Task 7, AC 96; F6 → S2c, Tasks 2, 8, AC 97; F7 → Tasks 3, 4, 8; F8 → S2d, Task 8, AC 99; F9 → S2c', Tasks 3, 4, 7, AC 100–102 (render only for linked clients, 24-month import, reports never imported, 10-min / 5-failure retry bounds); F10 → S2e', Task 9, AC 14, 15, 103; F11 → Task 4, AC 6; F12 → Task 7 table; F13 → S2c, Task 14, AC 98 (payer sees, non-payer owner does not); F14 → Task 2, AC 3; F15 → Tasks 2, 28; F16 → Task 2, AC 15; F17 → Task 2; F18 → S2f, Task 10, AC 104 (share never blocked for an owner without account); F19 → Task 10, AC 105; F20 → S2b, Task 3; F21 → AC 1, 9, 86, 89; F22 → Task 8; F23 → Task 8, AC 16.

**Review amendments (party mode on the full spec, 2026-10-31)** — M1 → Task 28, AC 45 (idempotency replays); M2 → S4c, Tasks 21, 25, AC 87 (`minLeadTimeManualHours`); M3 → superseded by D5 (no consent withdrawal at archive or revocation; send-time rule); M4 → Task 15 (booklet service, no bus); M5 → Task 19, AC 89 (mirror test); M6 → Task 4, Task 40 (`renewableByOwner` from slice 2); M7 → Task 48, AC 90. User precisions: export link 48 h + delayed deletion + `no-store` (Task 36, AC 63, AC 91); consents written to every linked record of the clinic (Task 33, AC 92); booklet cache key = hash of every render input (Task 15, AC 25).

**Review amendments (party mode, 2026-10-31)** — P1 → S4d (lock order, local day); P2 → S7d (auto-decline); P3 → S2e (versioned key, delayed deletion ≥ 10 min); P4 → X4 (slice 4 in two branches); P5 → tests; P6 → S5e, S5g + limiters; P7 → S4e. User precisions: X1 (Vision rule), S5d (push out of v1), S4a (`occupiesSlot()`), S4c (settings, not constants), S5c (withdraw-only on archived, AC), S5g (no consent change).

## Implementation Plan

### Tasks

Every task is tagged with its slice and branch. Branches are cut from `master` in this order, each merged before the next starts, each with `make ci` green (incl. `make openapi-check`) and 100 % coverage per touched BC:

| Branch | Slice | Tasks |
| --- | --- | --- |
| `chore/portal-api-contract-v1-1` | contract | 1 |
| `feature/portal-documents` | 2 | 2–10 |
| `feature/portal-pets` | 3 | 11–17 |
| `feature/portal-booking-setup` | 4a | 18–24 |
| `feature/portal-booking` | 4b | 25–30 |
| `feature/portal-consents` | 5 | 31–38 |
| `feature/portal-requests` | 6 | 39–43 |
| `feature/portal-organic-arrival` | 7 | 44–51 |

Each branch also regenerates `openapi/portal.v1.yaml` (`make openapi-export`) for its routes and updates the README of every BC it touches.

#### Contract — `chore/portal-api-contract-v1-1`

- [x] Task 1: Update the Portal API contract for slices 2–7
  - File: `openapi/portal-api-v1.md`
  - Action:
    - « Vision »: the rule « le Portal ne lit les autres contextes que par leurs bus de requêtes, jamais leurs agrégats ni leurs tables »; remove the `client_portal__*` projection list (X1).
    - « Découpage DDD »: remove `Context/Document`; ClientPortal gains the publication registry (`PublishedDocument`, reads), `PreVisitQuestionnaire`, `PetChangeRequest`, `PrescriptionRenewalRequest`, `BookingRequest`, `LinkSuggestion` (computed); events `DocumentPublished`, `DocumentWithdrawn`; Scheduling gains `BookingMotif`, `OnlineBookingSettings`, statuses; Billing/Prescription publish their integration events (S2b); Clinic publishes `ClinicProfileChangedIntegrationEvent`; ClinicListing as decided (S7c). Remove `Device` (S5d).
    - Errors: add `not_found` 404, `export_rate_limited` 429, `booking_not_open` 422, `pet_not_bookable` 422, `email_taken` 422; `cursor_invalid` 400 confirmed.
    - Slice 2 table: shapes of S2a/S2c/S2d (`paymentStatus` enum `unpaid|partially_paid|paid|credited`; `renewable`, `renewalStatus` nullable until slice 6); file routes answer 302 to a 5-min signed URL.
    - Slice 3 table: `lifeStatus`, `lastWeight` source, `referentVet` nullable (S3c), care-item status rule and 30-day window (S3d), timeline = published visits (S3e), booklet (S3f); `GET /me` `clinics[].timezone` (S3g); `nextAppointment` minimal shape (S3h).
    - Slice 4 table: statuses `confirmed|pending_clinic_confirmation|cancelled_by_client|cancelled_by_clinic|declined|completed|no_show`; `cancellableUntil`; settings-driven grid/lead time/horizon (S4c); auto-decline (S7d/P2); with-new-pet behaviour (S4g).
    - Slice 5: consent channels `sms|email` only (S5a); `PATCH /me`; email change and deletion routes with SMS confirmation (S5e, S5g); export (S5f); remove `/me/devices*` and `/me/notification-preferences` from the endpoint tables and the screen map; add open decision « Push » (device registration, preferences and transport together).
    - Slice 6: request statuses and renewal predicate (S6a–S6c).
    - Slice 7: `registrationToken` purpose, `POST /auth/register`, link suggestions (S7b), listing fields, `GET /me/booking-requests` (`[{ id, clinic { name, city }, pet { name, species }, motif, startAt, status: pending|confirmed|declined, appointmentId? }]`).
    - « Décisions ouvertes »: close the six (confirmation mode, cancellation window, consents on revoked link, questionnaire, foreign numbers, new-client visibility) with the decisions of step 1; keep only « Push ».
    - Update the screen → endpoint map and the route count.
    - **Review deltas (slices 3–7, 2026-10-31)** — apply these too; where they conflict with a bullet above, the delta wins:
      - *Slice 3:*
        - Pet list/detail: add `lifeStatus` (`alive|deceased|missing`); `breed` rule (breedName, else « Croisé » when mixed, else null); `initial`; enums `species` `dog|cat|nac|other`, `sex` `male|female|unknown`, `sterilization` `intact|neutered|unknown`; sold/given/archived/secondary-owner pets are never exposed (404).
        - `lastWeight`: `{ weightGrams, at: YYYY-MM-DD }` from Animal weight records and CLOSED consultations only; nullable.
        - `referentVet`: nullable string, derived from the latest report visible to the caller (name snapshotted at share time).
        - Care items: statuses `up_to_date|due_soon|overdue` in slice 3; `booked` documented as « à partir de la tranche 4 » (motif `preventiveCareKind`, window `[today, max(dueOn, today) + 30 d]`); ordered `dueOn` ASC; 30-day `due_soon` window; date-only fields.
        - Home `careItems[]` gains `pet { id, name }`; `nextAppointment` slice-3 shape `{ id, startAt, pet { id, name }, practitioner?, motifLabel }` (fixed labels), full appointment shape from slice 4.
        - Timeline: `date` = consultation start (instant with clinic offset), ordered by close time descending; `cursor` + `limit`; `cursor_invalid`; only reports visible to the caller; `documents[]` follows S2c (invoice only for its payer).
        - `GET /me` `clinics[].timezone` (IANA). Conventions table: instants carry the clinic offset; date-only fields are `YYYY-MM-DD` without conversion (replace the « Tranche 1 : UTC » note for clinical data).
        - Booklet: 302 to a signed 5-min URL; PDF « Aucune vaccination enregistrée » when empty.
      - *Slice 4:*
  
        - **Vision (D1)**: add an explicit exception to « les écritures du propriétaire deviennent des demandes ». Next to booking, rescheduling, cancelling, consents and profile, the owner may create **his own new animal when booking** (`with-new-pet`). It is created with unknown sex and sterilisation, and archived automatically if the booking is declined or expires before any clinical record.
        - **Appointment statuses** (portal values): `confirmed`, `pending_clinic_confirmation`, `cancelled_by_client`, `cancelled_by_clinic`, `declined`, `completed`, `no_show`. A pending appointment cancelled by the clinic is `declined`.
        - **Appointment shape**:
          - `motif {id, label}`. For a clinic appointment without online motif: `{id: null, label: "Rendez-vous"}`. Never the internal reason or notes.
          - `cancellableUntil` = `max(startAt − délai, createdAt + 15 min)`, null for non-occupying statuses.
          - `relatedCareItem {kind, label, dueOn, status}` or null.
          - Reschedule and cancel return the full appointment.
        - **Availability parameters**:
          - `from`, `to` = clinic-local dates `YYYY-MM-DD`, inclusive, at most 14 days, `from` ≥ local today.
          - Days beyond the horizon are `open: false`.
          - `open` = opening day, not an exceptional closure, within the horizon.
          - Settings-driven grid, lead time, manual lead time (default 24 h, applied to bookings needing confirmation) and horizon.
          - Unknown, archived or non-online motif → 404 `not_found`. Disabled booking → 422 `booking_not_open` on motifs, practitioners and availability too.
        - **`suggested`**: derived from overdue **or due-soon** care items of the motif's kind.
        - **`newPet.species`** enum `dog|cat|nac|other`.
        - **Idempotency**: `Idempotency-Key` only on the two create routes. Cancel is naturally idempotent (repeat → 200 same body). A retry of a create whose response was lost returns the existing appointment (201).
        - **`.ics`**: `METHOD:PUBLISH`, `TENTATIVE` while pending. 404 for withdrawn or terminal appointments.
        - **Errors**: add `appointment_not_modifiable` (422, terminal or declined appointment). Confirm `slot_taken` (409, also for an off-grid or unlisted `startAt`), `cancellation_window_closed` (422), `booking_not_open` (422, also clinic not active), `pet_not_bookable` (422, deceased or missing). Another client's pet or a sold or given pet → 404 `not_found`.
        - **Confirmation mode** (closed open decision): set per motif for known clients (`auto|manual`, default auto). **A new client is always validated by the clinic** (no per-motif setting). A pending request not answered is declined automatically 48 h after booking or 2 h before its start, whichever comes first.
        - **Cancellation window**: per clinic, 24 h by default, with a 15-minute grace after booking.
        - **DDD table, Scheduling row**: « publie, pour les seuls RDV en ligne en attente (`source = portal`) : `PendingAppointmentConfirmedIntegrationEvent`, `PendingAppointmentDeclinedIntegrationEvent`, `PendingAppointmentCancelledIntegrationEvent` ; tous les autres événements restent de domaine (règle D1 assouplie pour ces trois) ». Replace the current « publie AppointmentScheduled, AppointmentRescheduled, AppointmentCancelled » and « La règle D1 évolue » wording.
      - *Slice 5:*
        - « Décisions ouvertes » / consents: replace « Consentements quand un lien est révoqué → retrait automatique » by the closed decision « aucun retrait ; un client archivé ne reçoit aucun message optionnel (contrôle à l'envoi) ; les consentements sont intacts au désarchivage ».
        - Consents table: channels `sms|email` only; each kind returns `effect: send_control|channel_preference`; `PUT …/consents/{kind}` on `billing`/`clinical_documents` refuses an empty list; history items gain `source: portal|clinic|system`, author « La clinique » for staff rows, `approximate` flag for backfilled rows; read = union across the user's linked records of the clinic; 409 `conflict` added to the error table.
        - `PATCH /me`: field rules (names 1–60, structured address, no geocoding).
        - Email change: three routes `POST /me/email-change/otp`, `POST /me/email-change {challengeId, code, email}` → `{challengeId}`, `POST /me/email-change/verify {challengeId, code}`; `email_taken` only at `/verify` and only against a verified holder; notice to the previous address.
        - `POST /auth/otp/{challengeId}/resend`: enum `[sms, email]`; `email` only for login challenges and only when the phone's account has a verified email; otherwise same 202, nothing changed. Note: this reverses slice-1 D18 — a verified email becomes a login factor (record it in the auth conventions).
        - Export: `POST /me/data-export` (202), `GET /me/data-exports`, `GET /me/data-exports/{id}/file` (302 → 5-min signed URL); email is a notice only; remove `email_not_verified` from the error table; content table with `schemaVersion: 1`.
        - Deletion: `POST /me/deletion-request/otp`, `POST /me/deletion-request {challengeId, code}`; tokens refused immediately after the 202; clinic records and consents untouched.
        - DDD table: remove `ClientLinkRevokedIntegrationEvent{reason}` and the Client consumer; `ClientLinkRevoked` stays a domain event without reason.
      - *Slice 6:*
          - Add `GET /clinics/{c}/requests` (`cursor`, `limit`) → `items[{ id, kind: pet_change|prescription_renewal, petId, field?, proposedValue?, prescriptionId?, status: submitted|done|rejected|closed, submittedAt, resolvedAt?, resolutionMessage?, resultingPrescriptionId? }]`, `nextCursor`.
          - State: « Pas d'annulation ni de modification d'une demande envoyée en v1 » (questionnaire excepted, editable until the appointment starts).
          - `POST …/change-requests`: field rules (`name` 1–60, `weight` 0,05–150 kg with optional `measuredOn`, `sterilization` `neutered|intact`, `chip_number` 15 digits, `other` requires `note`), `note` ≤ 500, `proposedValue` ≤ 120; errors 404 `not_found`, 422 `pet_not_eligible`, 422 `validation_failed`, 429 `rate_limited`.
          - `POST …/renewal-requests`: a second request while one is open → 202 with the same `{ requestId, status }` (no 409); `prescription_not_renewable` also covers a pet not alive and a refusal less than 7 days old.
          - `PUT …/questionnaire`: `version` must be 1; locked when the appointment started, a consultation began, or the appointment is not active (cancelled/declined → 404); `ongoingTreatment.details` ≤ 500, `notes` ≤ 1000; `GET` returns `updatedAt`.
          - Documents list: `prescription.renewalStatus` becomes `{ status, requestId, resultingPrescriptionId? }` or null.
          - Error table: add `rate_limited` 429 (with `Retry-After`), `pet_not_eligible` 422; `questionnaire_locked`, `prescription_not_renewable` confirmed.
          - Owner notification on resolution (fixed sentence + optional staff message) mentioned in the slice-6 notes.
        -
      - *Slice 7:*
        - Errors: add
          - `account_exists` 409 (`POST /auth/register`, an active account already holds the phone);
          - `already_linked` 422 (listing booking while a confirmed link to that clinic exists — use `/clinics/{c}/booking`);
          - `booking_request_limit` 422 (more than 1 pending new-client request per clinic or 3 overall).
          `onboarding_invalid` gets a neutral title (« Jeton expiré ou invalide »).
        - `POST /auth/otp/verify` `registration_required`: `registrationToken` (15 min, single use, purpose `registration`, refused by `/invitations/{id}/accept`).
        - `POST /auth/register` `{registrationToken, firstName, lastName, email?, termsVersion}`:
          - success: 201 `{accessToken, …, account}`;
          - replay of the same token within 15 min → same account, new token pair;
          - errors: 401 `onboarding_invalid`, 409 `account_exists`, 422 `terms_outdated`;
          - `email` dropped silently if already used;
          - per-IP limit.
        - Unknown-number SMS budget: network sub-limit (/24 IPv4, /64 IPv6), optional `X-Device-Id` header used as a hint only, global cap 500/h (configurable); exhaustion is silent (same 202, no SMS).
        - `GET /me`: `clinics[].linkStatus` `pending|confirmed`, `linkedAt` nullable (null when pending).
        - `GET /me/link-suggestions`: one suggestion per clinic, `{ id (opaque), clinic { name, city }, matchedOn: phone }`; `POST /me/link-suggestions/{id}/accept` → 202 `linkStatus: pending` (all matching files of that clinic).
        - `GET /clinic-listings`:
          - `q` ≥ 2 characters, over name and city;
          - `lat` ∈ [-90, 90], `lng` ∈ [-180, 180];
          - `cursor` tied to `lat/lng` (else 400 `cursor_invalid`);
          - items without coordinates are absent from distance results only;
          - `onlineBooking` computed live;
          - `nextAvailableAt` always null in v1.
        - `GET /clinic-listings/{l}` and every `/clinic-listings/{l}/…` route: 404 `not_found` when unlisted or inactive. Listing ids are not clinic ids.
        - `POST /clinic-listings/{l}/booking/appointments`:
          - authenticated (the only non-GET under `/clinic-listings`) and idempotent (`Idempotency-Key`);
          - success: 201 `BookingRequestView` `{ id, clinic { name, city }, pet { name, species }, motif, startAt, status: pending }`;
          - errors: 409 `slot_taken`, 422 `already_linked`, 422 `booking_request_limit`, 422 `booking_not_open`, 404 `not_found`;
          - new clients are always confirmed manually and only see slots beyond the manual lead time.
        - `GET /me/booking-requests`: cursor-paginated `{items[{ id, clinic { name, city }, pet { name, species }, motif, startAt, status: pending|confirmed|declined, declineReason?, appointmentId? }], nextCursor}`.
        - Notifications: new purpose `PORTAL_BOOKING_DECLINED` (sent by Kiveto, SMS to the account phone) on decline, expiry or clinic cancellation of a new-client request.
        - DDD table:
          - ClientPortal gains `BookingRequest` (`client_portal__booking_requests`) and pending `ClientLink`.
          - Scheduling publishes only `PendingAppointmentConfirmed/Declined/CancelledIntegrationEvent` for `source=portal`, with README D1 relaxed for them.
          - Clinic publishes `ClinicProfileChangedIntegrationEvent`.
          - ClinicListing owns `clinic_listing__listing_settings` and `clinic_listing__clinic_listings`.
        - Remove any mention of `confirmationNewClient` (D15).
  - Notes: no code; `openapi/portal.v1.yaml` is regenerated by each implementation branch, not here.

#### Slice 2 — published documents — `feature/portal-documents`

- [ ] Task 2: API foundations missing from slice 1 (X2, X3)
  - Files: `src/Presentation/Api/Portal/Support/PortalClinicGuard.php` (new), `src/Presentation/Api/Portal/Problem/PortalProblemMap.php`, `src/Presentation/Api/Portal/Problem/InvalidCursorListener.php` (new), `src/Presentation/Api/Portal/View/PageView.php` (new), `src/Presentation/Api/Portal/Support/SignedFileUrls.php` (new), `src/Presentation/Api/Portal/OpenApi/PortalOpenApiFactory.php` (new, decorates `api_platform.openapi.factory`), `config/routes/api.yaml`, `config/packages/security.yaml`, `tests/Architecture/PortalBoundaryTest.php` (new)
  - Action:
    - `PortalClinicGuard::require(string $userId, string $clinicId): void` asks `HasConfirmedClientLink` and throws `PortalProblemMap::problem(NOT_FOUND)`. It is only the route gate: **row scoping is done by the ClientPortal queries themselves**, which take `(userId, clinicId)` and join `client_portal__client_links` on `status = confirmed` in SQL (F6) — no `clientIds` passed from Presentation.
    - `PortalProblemMap` gains `not_found`, `cursor_invalid` and the later codes of Task 1 as each branch needs them. (The `idempotency_key_*` move is done in Task 28, the first branch with an idempotent route, through a `ProblemFactoryInterface` in `Api/Shared` implemented by the Portal map — F15.)
    - `InvalidCursorException` → 400 `cursor_invalid`. Each paginated query validates its cursor position against its own schema (required keys, ISO date, UUID) and throws `InvalidCursorException` otherwise (F14); `limit` goes through `Cursor::clampLimit`.
    - `PageView{items, nextCursor}` generic list envelope (`@template T`).
    - `SignedFileUrls::sign(string $routeName, array $params): string` and `verify(Request): void` using `Symfony\Component\HttpFoundation\UriSigner` with a 5-minute expiration; `UnsignedUriException`, `UnverifiedSignedUriException`, `ExpiredSignedUriException` → 404 `not_found`. `UriSigner` reads the PSR `clock` service: tests move time with `Symfony\Component\Clock\Test\ClockSensitiveTrait::mockTime()` (F16).
    - Controller routes: explicit attribute import `api_portal_controllers` (`resource: ../../src/Presentation/Api/Portal/Controller/`, `type: attribute`, `prefix: /api/portal/v1`, `host: portal.kiveto.local`); the firewall public regex gains the anchored `files/` prefix (`^/api/portal/v1/(auth/otp|auth/refresh|invitations|legal|docs|_test/|files/)`); errors raised in these controllers are returned with `PortalProblemMap::response()` (API Platform does not wrap them). `PortalOpenApiFactory` documents every `__invoke` route of the Portal (file, booklet, `.ics`, `/files/*`) so `make openapi-export` contains them (F17).
    - `PortalBoundaryTest` (PHPUnit, scans `src/Context/ClientPortal` and `src/Presentation/Api/Portal`): no `use App\Context\<Other>\(Domain|Infrastructure)\…` except `*IntegrationEvent` classes (F21, AC 86); the scanned namespaces are a list constant so slice 7 adds `src/Context/ClinicListing`.
- [ ] Task 3: Billing integration events and batch query (S2b)
  - Files: `src/Context/Billing/Domain/Event/{InvoiceIssuedForPortalIntegrationEvent, InvoiceCancelledIntegrationEvent}.php`, `src/Context/Billing/Infrastructure/Messaging/Consumer/{InvoiceIssuedForPortalIntegrationBridge, InvoiceCancelledIntegrationBridge}.php`, `src/Context/Billing/Application/Query/ListInvoicePortalStatuses/{ListInvoicePortalStatuses, ListInvoicePortalStatusesHandler, InvoicePortalStatus}.php`, `src/Context/Billing/Application/Query/ListPortalPublishableInvoices/…` (import mode, F9), `src/Context/Billing/README.md`
  - Action: thin payloads `{invoiceId, clinicId}` (`BOUNDED_CONTEXT = 'billing'`, `VERSION = 1`); the issued bridge listens to `InvoiceIssued` for **all** invoices (the existing B2B `InvoiceIssuedIntegrationBridge` stays untouched); the cancelled bridge listens to `InvoiceCancelled`. `ListInvoicePortalStatuses(clinicId, invoiceIds[])` → `{invoiceId, status, totalGrossMinorUnits, currency}` (one SQL). `ListPortalPublishableInvoices(issuedFrom, afterId, limit)` → issued invoices with a payer client, for the 24-month import.
- [ ] Task 4: Prescription integration events, predicate and batch query (S2b, M6)
  - Files: `src/Context/Prescription/Domain/Event/{PrescriptionIssuedIntegrationEvent, PrescriptionCancelledIntegrationEvent}.php`, `src/Context/Prescription/Infrastructure/Messaging/Consumer/{PrescriptionIssuedIntegrationBridge, PrescriptionCancelledIntegrationBridge}.php`, `src/Context/Prescription/Domain/Prescription.php` (`isRenewableByOwner()`), `Application/Query/GetPrescriptionDetails/PrescriptionDetails.php` (+ read model), `Application/Query/{ListPrescriptionPortalSummaries, ListPortalPublishablePrescriptions}/…`, `src/Context/Prescription/README.md`
  - Action: thin payloads `{prescriptionId, clinicId}`. `PrescriptionDetails` already exposes `patientAnimalId` (`PrescriptionDetails.php:50`) and `ownerClientId`; it gains `bool renewableByOwner` = `Prescription::isRenewableByOwner()` (ISSUED and no controlled substance — expired allowed; the S6c predicate, defined here once and reused unchanged by slice 6). `ListPrescriptionPortalSummaries(clinicId, ids[])` → `{id, status, validUntil, renewableByOwner}`; `ListPortalPublishablePrescriptions(issuedFrom, afterId, limit)` for the import.
- [ ] Task 5: Consultation report file and share check (S2e, S2f)
  - Files: `src/Context/Consultation/Application/Query/GetConsultationShareability/{GetConsultationShareability, …Handler, ConsultationShareability}.php` (new), `src/Context/Consultation/Application/Command/RenderConsultationReportFile/{RenderConsultationReportFile, …Handler, ConsultationReportFile}.php` (new, plain `CommandInterface`, synchronous, no DB write), `src/Context/Consultation/README.md`
  - Action:
    - `ConsultationShareability{shareable: bool, reason: ?string (not_closed|no_animal|no_primary_owner), patientId, animalId, primaryOwnerClientId, startedAtUtc, closedAtUtc}` — closed status, patient linked to an animal, animal with an active primary owner.
    - `RenderConsultationReportFile(clinicId, consultationId, storageKey)` renders `ConsultationReportPdfRendererInterface::report()` and `storeAt()` under the given key, returns `ConsultationReportFile{storageKey, filename}` — exact mirror of `RenderPrescriptionFile` (F4).
- [ ] Task 6: Publication registry domain and persistence (S2a, S2a', S2d)
  - Files: `src/Context/ClientPortal/Domain/Document/{PublishedDocument, PublishedDocumentId, DocumentType (invoice|prescription|report|lab_result|certificate), PublicationStatus (awaiting_link|pending_file|available|failed|withdrawn), PublishedDocumentRepositoryInterface, DocumentReadRepositoryInterface}.php`, `Domain/Event/{DocumentPublished, DocumentWithdrawn}.php`, `Infrastructure/Persistence/Doctrine/Entity/{PublishedDocumentEntity, DocumentReadEntity}.php`, `Repository/{DbalPublishedDocumentRepository, DbalDocumentReadRepository}.php`, `migrations/ClientPortal/Version…php`
  - Action: tables as in S2a, unique `(clinic_id, type, source_ref)`, indexes `(clinic_id, client_id, status, issued_at, id)`, `(clinic_id, patient_id)`, `(status, updated_at)`; `client_portal__document_reads` PK `(document_id, user_id)`. Aggregate: `publish(…, linked: bool)` (→ `pending_file` or `awaiting_link`; invoices with an existing Billing file go straight to `pending_file`), `tombstone(…)` (withdrawn without prior publish), `linkConfirmed()` (awaiting_link → pending_file), `requestFile(): int` (sets `pending_file_version = file_version + 1`), `attachFile(version, key): ?string previousKey` (no-op unless `version = pending_file_version` and not withdrawn; returns the previous key to delete), `renderFailed(maxAttempts = 5)` (→ `failed` at 5), `withdraw()` (terminal), `assignPets(petIds)`, `repointPatient(patientId)`. Repository `findForUpdate(clinicId, type, sourceRef)`.
- [ ] Task 7: Publication commands and consumers (S2a'–S2e)
  - Files: `src/Context/ClientPortal/Application/Command/{PublishInvoice, PublishPrescription, ShareConsultationReport, StorePublishedDocumentFile, AttachPublishedDocumentFile, RecordDocumentRenderFailure, WithdrawPublishedDocument, DeleteStoredDocumentFile, ReleaseAwaitingDocuments, BackfillDocumentPets, RepointDocumentPatient, ReconcilePublishedDocuments, MarkDocumentRead}/…`, `Application/Port/{InvoiceDocumentsInterface, PrescriptionDocumentsInterface, ConsultationDocumentsInterface, PatientAnimalsInterface, AdmissionVisitsInterface, DocumentFilesInterface}.php`, `Infrastructure/Adapter/{Billing/QueryBusInvoiceDocuments, Billing/CommandBusInvoiceFiles, Prescription/QueryBusPrescriptionDocuments, Prescription/CommandBusPrescriptionFiles, Consultation/QueryBusConsultationDocuments, Consultation/CommandBusConsultationReportFiles, Admission/QueryBusAdmissionVisits, Patient/QueryBusPatientAnimals}.php`, `Infrastructure/Messaging/Consumer/{InvoiceIssuedForPortalIntegrationEventConsumer, InvoiceCancelledIntegrationEventConsumer, PrescriptionIssuedIntegrationEventConsumer, PrescriptionCancelledIntegrationEventConsumer, PatientLinkedToAnimalIntegrationEventConsumer, PatientReconciledIntoIntegrationEventConsumer, ClientLinkConfirmedDocumentsConsumer}.php`, `Infrastructure/Scheduler/ClientPortalScheduleProvider.php` (new, `ReconcilePublishedDocuments` every 5 min), `src/Context/ClientPortal/Infrastructure/Console/ImportPortalDocumentsCommand.php` (console, `--since` capped at 24 months), `config/packages/messenger.yaml` (route `DeleteStoredDocumentFile` and `ReconcilePublishedDocuments` to `async`), `config/services.yaml`
  - Action:
    - **Field sources per type** (F12):

      | Type | `client_id` | `title` (French) | `issued_at` | `consultation_id` | `patient_id` / `pet_ids` |
      | --- | --- | --- | --- | --- | --- |
      | invoice | payer client | « Facture {number} » | invoice `issuedAt` | consultation of the invoice's admission when exactly one (Consultation port), else null | admission → patient → animal |
      | prescription | `ownerClientId` (null → not published) | « Ordonnance {number} » | `issuedAt` | `Prescription::consultationId()` | consultation's patient / `patientAnimalId` |
      | report (+ `practitioner_name` snapshotted at each share) | primary owner at share time (recomputed at each share) | « Compte rendu du {date de consultation} » | consultation `closedAtUtc` (unchanged by a re-share) | the consultation | consultation's patient / animal |

    - `PublishInvoice` / `PublishPrescription`: re-read the live source (skip unless invoice ISSUED/PARTIALLY_PAID/PAID/CREDITED or prescription ISSUED), insert with `INSERT IGNORE` on the unique key (a tombstone or an existing row wins), status `pending_file` when the client has a confirmed link, else `awaiting_link`; dispatch `StorePublishedDocumentFile(documentId, version)` after commit when `pending_file`.
    - `ShareConsultationReport(clinicId, consultationId, actorUserId)`: `GetConsultationShareability` → refuse with the reason; `findForUpdate`; first share → publish (v1); later share → `requestFile()` (v+1, previous version keeps being served), owner recomputed; returns `{linked: bool}` for the toast.
    - `StorePublishedDocumentFile(documentId, version)` (`ExternalApiCommandInterface`, `async_external`): invoice → `EnsureSentInvoiceFile`; prescription → `RenderPrescriptionFile`; report → `RenderConsultationReportFile`, key `client_portal/documents/<id>-v<version>.pdf`; then dispatches `AttachPublishedDocumentFile(documentId, version, key)` (short transaction: `attachFile`, deletes the reads on version ≥ 2, then — after commit — dispatches `DeleteStoredDocumentFile(previousKey)` with `DelayStamp(600_000)`). A thrown render dispatches `RecordDocumentRenderFailure` (attempts+1, `failed` at 5 with `logger->error`) and rethrows for Messenger's own retries.
    - `DeleteStoredDocumentFile(key)` (routed `async`): refuses (LogicException) any key not starting with `client_portal/`.
    - `InvoiceCancelled` / `PrescriptionCancelled` consumers → `WithdrawPublishedDocument` (row → `withdraw()`; no row → `tombstone`).
    - `ClientLinkConfirmed` consumer → `ReleaseAwaitingDocuments(clinicId, clientId)` (bounded batches) → `pending_file` + `StorePublishedDocumentFile`.
    - `PatientLinkedToAnimal` → `BackfillDocumentPets(clinicId, patientId, animalId)` on rows with that `patient_id` and `pet_ids = []`; `PatientReconciledInto` → `RepointDocumentPatient(source → target)` then backfill from the target's animal.
    - `ReconcilePublishedDocuments` (scheduled, idempotent, batches of 100): re-dispatches `StorePublishedDocumentFile` for rows in `pending_file` with `updated_at < now − 10 min` and `render_attempts < 5`; import mode (`ImportPortalDocumentsCommand`) walks `ListPortalPublishableInvoices` / `ListPortalPublishablePrescriptions` since `max(--since, now − 24 months)` and calls the publish commands (reports never imported).
- [ ] Task 8: Document read queries (S2a, S2c, S2d)
  - Files: `src/Context/ClientPortal/Application/Query/{ListPublishedDocuments, GetPublishedDocumentFile, CountUnreadDocumentsPerClinic, GetPortalPrescription}/…`
  - Action:
    - All queries take `(userId, clinicId, …)` and join confirmed links in SQL (F6).
    - `ListPublishedDocuments(userId, clinicId, ?type, ?petId, ?cursor, ?limit)` → listable rows (S2a) ordered `issued_at DESC, id DESC`, cursor `(issued_at, id)` validated; `unread` = no read row for the user; one batch call per page to `ListInvoicePortalStatuses` (ISSUED → `unpaid`, PARTIALLY_PAID → `partially_paid`, PAID → `paid`, CREDITED → `credited`; anything else → row excluded + warning log, F8) and to `ListPrescriptionPortalSummaries` (`{id, validUntil, renewable: renewableByOwner, renewalStatus: null}`; a non-ISSUED prescription is excluded the same way).
    - `GetPublishedDocumentFile(userId, clinicId, documentId)` → `{documentId, version, key}` of the served version, null when not listable or withdrawn.
    - `CountUnreadDocumentsPerClinic(userId)` → `array<clinicId, int>` in one query (F22).
    - `GetPortalPrescription(userId, clinicId, prescriptionId)` (lines with `posologySummary` = `posology`, vet, pet) only for a listable, non-withdrawn registry row of one of the user's confirmed clients (F23).
- [ ] Task 9: Portal API — documents (slice 2 routes)
  - Files: `src/Presentation/Api/Portal/Resource/{DocumentList, PortalPrescription}.php`, `State/{DocumentListProvider, PortalPrescriptionProvider}.php`, `View/{DocumentView, InvoiceSummaryView, PrescriptionSummaryView, PrescriptionView, PrescriptionLineView}.php`, `src/Presentation/Api/Portal/Controller/{DocumentFileController, SignedDocumentDownloadController}.php` (new `__invoke`), `State/MeProvider.php`
  - Action:
    - `GET /clinics/{c}/documents` (guard, filters, `limit`, `PageView`), `GET /clinics/{c}/prescriptions/{id}` (404 when withdrawn).
    - `GET /clinics/{c}/documents/{id}/file` (authenticated): guard, `GetPublishedDocumentFile`, `MarkDocumentRead`, 302 to `SignedFileUrls::sign('portal_document_download', {c, id, v})` with `Cache-Control: private, no-store`.
    - `GET /files/documents/{c}/{id}/{v}` (public, signature required, 5 min): verifies the signature, re-checks the row is not `withdrawn` and the version exists (`-v<v>.pdf`, or the Billing key for invoices), streams `FileStorageInterface::read()` as `application/pdf`, `Content-Disposition: inline`, `Cache-Control: private, no-store`; any failure → 404 `not_found` via `PortalProblemMap::response()`.
    - `MeProvider`: `unreadDocuments` from `CountUnreadDocumentsPerClinic` (one call).
- [ ] Task 10: VetApp « Partager sur le Portal » + fixtures (S2f)
  - Files: `src/Presentation/Clinic/Controller/Consultation/ShareConsultationReportOnPortalController.php` (new, `POST /clinic/consultations/{id}/documents/portal-share`, CSRF via `CockpitEndpoint`, same permission check as `SendConsultationDocumentsController`, JSON toast), `src/Presentation/Clinic/Controller/Consultation/View/ConsultationDetailsController.php` (`DOCS.portalShare {url, shareable, reason, sharedAt}` + endpoint), new ClientPortal query `GetReportShareState(clinicId, consultationId)`, `assets/js/pages/consultation.js` (button in the documents panel, disabled with the French reason, « Partagé le … » + « Partager à nouveau »), `fixtures/Context/ClientPortal/Story/PortalDocumentsStory.php`, `fixtures/Dataset/PortalDataset.php`
  - Action: messages « Compte rendu partagé sur le Portal. », « Partagé — visible dès que le client activera son espace Portal » (owner without confirmed link; the share is not blocked), « La consultation doit être clôturée. », « Le patient n'est rattaché à aucun animal. », « L'animal n'a pas de propriétaire principal. ». Fixtures (CLAUDE.md §7, F19): owners with 0, 2 and > 20 documents; unread + read; invoices unpaid / partially paid / paid / credited; a withdrawn invoice; a renewable and an expired prescription; a re-shared report (v2); a `pending_file` row; an `awaiting_link` row; a `failed` row; a document without pet; an invoice paid by client B for client A's animal; an owner with a revoked link that has documents. The story writes real small PDFs through `FileStorageInterface::storeAt()` under the registry keys so every demo download works.

#### Slice 3 — pets and history — `feature/portal-pets`

- [ ] Task 11: Animal queries (S3a, S3d, D4)
  - Files:
    - `src/Context/Animal/Application/Query/ListPortalPets/{ListPortalPets, ListPortalPetsHandler, PortalPetView}.php` (new)
    - `src/Context/Animal/Application/Query/ListLatestPreventiveCare/{ListLatestPreventiveCare, ListLatestPreventiveCareHandler, LatestPreventiveCareView}.php` (new)
    - `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php` (new methods)
    - `src/Context/Animal/README.md`
  - Action:
    - `ListPortalPets(clinicId, clientIds[])`: one SQL on `animal__ownerships` ⋈ `animal__animals`.
      - Filter: `o.role = 'primary'`, `o.status = 'active'`, `a.status = 'active'`, `a.transfer_status = 'none'`.
      - Returns `{id, primaryOwnerClientId, name, species, breedName, isMixedBreed, sex, reproductiveStatus, birthDate, lifeStatus, microchipNumber}`, ordered alive first, then by name.
    - `ListLatestPreventiveCare(clinicId, animalIds[])`: `ROW_NUMBER() OVER (PARTITION BY animal_id, kind, LOWER(label) ORDER BY administered_on DESC, recorded_at DESC)`, keeping rank 1 with **no** `next_due_on` filter.
      - Returns `{animalId, kind, label, administeredOn, batchNumber, nextDueOn}`.
      - Callers filter null `nextDueOn` themselves.
      - `LATEST_ACTS` and its `:dueBy` semantics in `DbalAnimalDirectoryReadRepository` are left untouched.
    - **The VetApp copies are explicitly deferred** and not touched in this branch: `AnimalSheetBuilder::preventionRows`, `CockpitContextBuilder::due`, `ClientSheetBuilder` raw SQL. Note this in the Animal README as a known duplication.
  - Tests:
    - `tests/Integration/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepositoryPortalTest.php`. Cases:
      - secondary owner, archived, sold, given excluded;
      - deceased and missing kept;
      - ranking: the latest act without `nextDueOn` hides an older one that has it;
      - same label with different case;
      - two kinds with the same label.
    - Unit tests of both handlers in `tests/Unit/Context/Animal/Application/Query/`.
- [ ] Task 12: Consultation portal summaries, title rule and weight (S3b, S3e, D3)
  - Files:
    - `src/Context/Consultation/Application/Service/VisitTitle.php` (new pure service)
    - `src/Context/Consultation/Application/Query/GetConsultationPortalSummaries/{GetConsultationPortalSummaries, GetConsultationPortalSummariesHandler, ConsultationPortalSummary}.php` (new)
    - `src/Context/Consultation/Application/Query/GetLatestClosedConsultationWeight/{GetLatestClosedConsultationWeight, GetLatestClosedConsultationWeightHandler, ConsultationWeight}.php` (new)
    - `src/Presentation/Clinic/ViewModel/Shared/DossierVisitReader.php` (`visitTitle()` delegates to `VisitTitle::of()`)
    - `src/Context/Consultation/README.md`
  - Action:
    - `VisitTitle::of(list<string> $motifs, ?string $chiefComplaint): string` keeps today's exact rule (`DossierVisitReader.php:470-478`):
      - the first motif is kept as is;
      - each following motif has its first letter lowercased, and they are joined with « et »;
      - with no motif, `rtrim($chiefComplaint, '. ')`;
      - otherwise « Consultation ».
    - `DossierVisitReader` keeps its own SQL and calls the service, with no bus call. Its three callers are unchanged: `DossierVisitReader.php:388`, `AnimalSheetBuilder.php:587`, `ClientSheetBuilder` via `consultationVisits`.
    - `GetConsultationPortalSummaries(clinicId, consultationIds[])`: one SQL for motifs, chief complaint and `startedAtUtc`. Returns `{consultationId, startedAtUtc, title}`, with no practitioner (the name comes from the registry snapshot).
    - `GetLatestClosedConsultationWeight(clinicId, animalId)`:
      - patients from `PatientIdsProviderInterface::findPatientIdsForAnimal`;
      - latest **CLOSED** consultation with a non-null `weight_kg`, ordered `started_at_utc DESC`;
      - returns `{weightGrams: (int) round(kg × 1000), measuredAtUtc: startedAtUtc}` or null.
  - Tests:
    - `tests/Unit/Context/Consultation/Application/Service/VisitTitleTest.php`: motifs, casing, trailing « . », null complaint.
    - `tests/Integration/Context/Consultation/Application/Query/{GetConsultationPortalSummariesTest, GetLatestClosedConsultationWeightTest}.php`: open consultation ignored, reconciled patients (two patient rows).
    - VetApp regression: `tests/Integration/Presentation/Clinic/…` asserting the visit titles of the animal sheet and the client sheet on an existing fixture.
- [ ] Task 13: Scheduling queries for the home (S3h)
  - Files:
    - `src/Context/Scheduling/Domain/ValueObject/AppointmentCategory.php` (new, `fromReason(?string)`: the rule moved from `AgendaViewBuilder::motif`)
    - `src/Presentation/Clinic/ViewModel/Agenda/AgendaViewBuilder.php` (`motif()` delegates)
    - `src/Context/Scheduling/Application/Query/GetNextAppointmentForAnimals/{GetNextAppointmentForAnimals, GetNextAppointmentForAnimalsHandler, NextAppointmentItem}.php` (new)
    - `src/Context/Scheduling/README.md`
  - Action:
    - `GetNextAppointmentForAnimals(clinicId, animalIds[], nowUtc)`:
      - filter `animal_id IN (…)`, `status = 'PLANNED'` (switched to `occupiesSlot()` in Task 19), `starts_at_utc >= :now`;
      - order `starts_at_utc ASC, id ASC`, `LIMIT 1`;
      - returns `{id, animalId, startsAtUtc, practitionerUserId, category}`.
    - It never selects `reason` or `notes`.
  - Tests: `tests/Unit/Context/Scheduling/Domain/ValueObject/AppointmentCategoryTest.php` (parity with the old regex cases), `tests/Integration/Context/Scheduling/Application/Query/GetNextAppointmentForAnimalsTest.php`. Cases:
    - start exactly at now;
    - past appointment;
    - CANCELLED;
    - appointment without animal;
    - tie on the same start, broken by id.
- [ ] Task 14: ClientPortal pet read models (S3a–S3e, S3g, S3h)
  - Files:
    - `src/Context/ClientPortal/Application/Query/{ListOwnerPets, GetOwnerPet, ListOwnerCareItems, GetOwnerHome, ListPetTimeline}/…`
    - `Application/Port/{PetDirectoryInterface, PreventiveCareInterface, VisitSummariesInterface, ConsultationWeightsInterface, AnimalWeightsInterface, OwnerAppointmentsInterface, PractitionerNamesInterface}.php`
    - `Application/Port/ClinicLabel.php` (+ `timeZone`)
    - `Application/Port/ClinicNamesInterface.php` → renamed `ClinicProfileInterface.php`
    - `Infrastructure/Adapter/Clinic/QueryBusClinicNames.php` → `QueryBusClinicProfile.php`, still on `GetClinic`, now also `ClinicDto::$timeZone`
    - `Infrastructure/Adapter/Animal/{QueryBusPetDirectory, QueryBusPreventiveCare, QueryBusAnimalWeights}.php`
    - `Infrastructure/Adapter/Consultation/{QueryBusVisitSummaries, QueryBusConsultationWeights}.php`
    - `Infrastructure/Adapter/Scheduling/QueryBusOwnerAppointments.php`
    - `Infrastructure/Adapter/Clinic/QueryBusPractitionerNames.php` (one `ListClinicVeterinarians` per call, batched lookup)
    - `Application/Query/ListOwnerClinics/{ListOwnerClinicsHandler, OwnerClinic}.php` (+ `timeZone`)
    - `Application/Service/CareItemStatusPolicy.php`
    - `src/Context/ClientPortal/Infrastructure/Persistence/Doctrine/Repository/DbalPublishedDocumentRepository.php` (timeline / latest-report reads)
    - `config/services.yaml` (aliases), `src/Context/ClientPortal/README.md`
  - Action:
    - **Ownership**: visible pets = `ListPortalPets` over the caller's confirmed client ids of the clinic, read by the query itself from `client_portal__client_links`. A pet id outside that set → `PetNotFound`, which becomes 404.
    - **`CareItemStatusPolicy::statusOf(\DateTimeImmutable $dueOn, \DateTimeImmutable $todayLocal): CareItemStatus`**:
      - overdue (`dueOn < today`) > due_soon (`dueOn ≤ today + 30 d`) > up_to_date;
      - `todayLocal` = `clock->now()` in the clinic timezone, date only;
      - no `booked` in slice 3; Task 27 extends the policy.
      - Deceased or missing pets are excluded before the policy runs.
    - **Weight**: compare the latest `ListAnimalWeightRecords` entry (`measuredOn`, date) with `GetLatestClosedConsultationWeight`, whose `measuredAtUtc` is converted to a clinic-local date. Most recent date wins; on the same date the Animal record wins.
    - **`referentVet`**: `practitioner_name` of the latest listable `report` row containing the pet, scoped to the caller's confirmed clients, else null.
    - **Timeline**: as S3e, cursor validated (`issued_at` ISO date, `id` UUID) and `limit` clamped. One `GetConsultationPortalSummaries` call per page. `documents[]` comes from one registry query per page over the page's consultation ids, scoped to the caller.
    - **Home**:
      - `nextAppointment` (S3h) over the alive visible pets, with practitioner names in one batch;
      - `pets[]` = alive visible pets;
      - `careItems[]` = the 3 smallest `dueOn` across alive pets, each with `pet {id, name}`;
      - `latestDocuments[]` = the first 2 rows of `ListPublishedDocuments` (same scoping and exclusion rules as slice 2, so it can return fewer than 2).
  - Tests:
    - `tests/Unit/Context/ClientPortal/Application/{Service/CareItemStatusPolicyTest, Query/*HandlerTest}.php` with in-memory ports (Noumea and Martinique boundaries);
    - `tests/Integration/Context/ClientPortal/Infrastructure/Adapter/**` for each adapter;
    - `tests/Integration/Context/ClientPortal/Infrastructure/Persistence/Doctrine/Repository/DbalPublishedDocumentRepositoryTimelineTest.php`.
- [ ] Task 15: Vaccination booklet (S3f)
  - Files:
    - `templates/portal/document/vaccination_booklet.html.twig` (new)
    - `src/Context/ClientPortal/Application/Service/VaccinationBookletFiles.php` (new; no command, no bus)
    - `Application/Port/{VaccinationRecordsInterface, BookletRendererInterface}.php`
    - `Infrastructure/Adapter/Animal/QueryBusVaccinationRecords.php` (on `ListAnimalPreventiveCare`, kind `vaccination`)
    - `Infrastructure/Document/TwigVaccinationBookletRenderer.php` (uses `Shared\Application\Document\DocumentPdfRendererInterface`)
    - Clinic header through `ClinicProfileInterface` (name, address, logo key)
  - Action:
    - `ensure(clinicId, animalId): string $key`, called only after `GetOwnerPet` succeeded.
    - Key = `client_portal/booklets/<animalId>-<sha256(canonical JSON)>.pdf`. The JSON holds the records `{id, label, administeredOn, batchNumber, nextDueOn}`, the identity `{name, species, breed, sex, birthDate, chip}`, the header and `TEMPLATE_VERSION`.
    - An existing key is returned as is.
    - Otherwise: render, `storeAt`, upsert the current key in the new table `client_portal__booklet_files` (`animal_id` PK, `clinic_id`, `storage_key`, `updated_at`; migration `migrations/ClientPortal/Version…php`, `DbalBookletFileRepository`), then dispatch `DeleteStoredDocumentFile` with a `DelayStamp` ≥ 600 000 ms for the previous key. `FileStorageInterface` gets no listing method.
    - Zero vaccinations → a PDF with « Aucune vaccination enregistrée ».
    - Writing the cache from a read path is a technical side effect (accepted). Gotenberg runs outside any DB transaction because the query bus has none.
  - Tests: `tests/Unit/Context/ClientPortal/Application/Service/VaccinationBookletFilesTest.php` with a fake renderer and in-memory storage. Cases:
    - unchanged inputs → same key, no render;
    - new record, rename, chip change, `TEMPLATE_VERSION` bump → new key and deletion scheduled;
    - zero vaccinations.
- [ ] Task 16: Portal API — pets and home (slice 3 routes)
  - Files:
    - `src/Presentation/Api/Portal/Resource/{Home, PetList, Pet, PetCareItems, PetTimeline}.php`
    - `State/{HomeProvider, PetListProvider, PetProvider, PetCareItemsProvider, PetTimelineProvider, MeProvider}.php`
    - `View/{PetSummaryView, PetView, CareItemView, HomeCareItemView, TimelineEntryView, NextAppointmentView, ClinicLinkView (+ timezone)}.php`
    - `Controller/{VaccinationBookletController, SignedBookletDownloadController}.php`
    - `Support/ApiDate.php` (+ `formatIn(\DateTimeImmutable, \DateTimeZone)` and `date(\DateTimeImmutable): string` → `Y-m-d`, without conversion)
    - `OpenApi/PortalOpenApiFactory.php` (booklet routes)
  - Action:
    - Routes of the slice-3 table, each guarded by `PortalClinicGuard`.
    - Mapping table S3i.
    - Field formats as S3g.
    - Booklet: `GET /clinics/{c}/pets/{id}/vaccination-booklet` → 302 (`Cache-Control: private, no-store`) to `GET /files/booklets/{c}/{animalId}/{hash}`, signed over `{c, animalId, hash}` for 5 min. The download re-checks the signature and serves the key with `Cache-Control: private, no-store`. Failures → 404 `not_found`.
  - Tests: `tests/Integration/Presentation/Api/Portal/{PetsApiTest, HomeApiTest, PetTimelineApiTest, VaccinationBookletApiTest}.php`.
- [ ] Task 17: Fixtures for slice 3
  - Files:
    - `fixtures/Context/Animal/Story/PortalPetsAnimalStory.php`
    - `fixtures/Context/Scheduling/Story/PortalPetsAppointmentsStory.php`
    - `fixtures/Context/ClientPortal/Story/PortalPetsStory.php` (builds on `PortalDocumentsStory`, real PDFs for the published visits)
    - Patient factory usage for a reconciled patient (`fixtures/Context/Patient/Factory/…`)
    - `fixtures/Dataset/PortalDataset.php`
  - Action: all dates relative to the fixture clock. States to cover:
    - owners with 0, 1 and 4 pets;
    - a deceased pet, a missing pet, an archived pet, a secondary-owner pet, a sold pet;
    - an owner linked to two client records of the same clinic;
    - a pet with a reconciled patient (two patient rows);
    - care items overdue, due soon and up to date;
    - a pet whose latest protocol act has no next due date;
    - more than 3 care items across pets (home truncation);
    - a pet without vaccination (booklet);
    - a pet without visit;
    - a pet with more than 20 published visits;
    - a report shared by a vet who has since left the clinic;
    - weights from both sources, including a same-day tie and an open consultation's weight (ignored);
    - a home with and without `nextAppointment`.

#### Slice 4a — booking setup — `feature/portal-booking-setup`

- [ ] Task 18: Appointment statuses, fields and pending lifecycle (S4a, S4f, D18)
  - Files:
    - `src/Context/Scheduling/Domain/ValueObject/{AppointmentStatus, AppointmentSource (clinic|portal), CancellationActor (clinic|client), CancellationReason (unavailable|no_longer_needed|seen_elsewhere|other), DeclineReason (declined|expired|clinic_cancelled)}.php`
    - `src/Context/Scheduling/Domain/Appointment.php`
    - `src/Context/Scheduling/Domain/Event/{AppointmentRequested, AppointmentConfirmed, AppointmentDeclined}.php`
    - `src/Context/Scheduling/Domain/Exception/AppointmentNotModifiableException.php`
    - `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/AppointmentEntity.php` and its mapper
    - `migrations/Scheduling/Version…php`
    - callers of `cancel()`: `src/Context/Scheduling/Application/Command/CancelAppointment/CancelAppointmentHandler.php:44`, `src/Presentation/Clinic/Controller/Animal/Sheet/DeclareAnimalDeathController.php:97`, `src/Presentation/Clinic/Controller/Scheduling/Appointment/CancelAppointmentController.php:55`
    - `src/Context/Scheduling/Application/Command/CheckInAppointment/CheckInAppointmentHandler.php`
    - `fixtures/Context/Scheduling/Factory/AppointmentEntityFactory.php`
    - `src/Context/Scheduling/README.md`
  - Action:
    - Statuses: `PENDING_CLINIC_CONFIRMATION` (non-terminal) and `DECLINED` (terminal; `isTerminal()` includes it). `occupiesSlot()` and `OCCUPYING_SQL_VALUES`.
    - `Appointment::request(id, clinicId, assignee, timeSlot, reason, notes, createdAt, ownerId?, animalId?, bookingMotifId, source, pendingExpiresAt)` → PENDING and records `AppointmentRequested` (payload as `AppointmentScheduled` + `bookingMotifId`, `source`). The caller computes `pendingExpiresAt` with `OnlineBookingSettings::pendingExpiry(createdAt, startsAt)`, and the domain asserts `pendingExpiresAt > createdAt`.
    - `confirm(bool $silent = false)`: PENDING → PLANNED, records `AppointmentConfirmed{silent}`.
    - `decline(DeclineReason)`: PENDING → DECLINED, records `AppointmentDeclined`.
    - `cancel(CancellationActor, ?CancellationReason, bool notifyClient)`: on PENDING it delegates to `decline(clinic_cancelled)` for a clinic actor, and stays a client cancellation for a client actor.
    - `markNoShow()` on PENDING throws `AppointmentNotModifiableException`. `complete()` / `startService()` on PENDING first `confirm(silent: true)`.
    - `reschedule()` on PENDING recomputes `pendingExpiresAt` (the new value is passed in).
    - Migration:
      - `status` → `VARCHAR(32)`, entity `length: 32`;
      - new columns `source VARCHAR(16) NOT NULL DEFAULT 'clinic'`, `booking_motif_id BINARY(16) NULL`, `cancelled_by VARCHAR(16) NULL`, `cancellation_reason VARCHAR(32) NULL`, `pending_expires_at DATETIME NULL`, `confirmed_silently TINYINT(1) NOT NULL DEFAULT 0`;
      - `UPDATE scheduling__appointments SET cancelled_by = 'clinic' WHERE status = 'CANCELLED'`;
      - index `(status, pending_expires_at)`.
    - `CheckInAppointmentHandler` keeps its flow; the domain now confirms a PENDING silently before completing.
    - The death flow passes `CancellationActor::CLINIC` with `notifyClient: false`, so a pending one is declined silently (D3 kept).
  - Tests: `tests/Unit/Context/Scheduling/Domain/{AppointmentPendingLifecycleTest, AppointmentStatusTest}.php`; `tests/Integration/Context/Scheduling/Infrastructure/Persistence/AppointmentRoundTripTest.php` (persist and reload a pending appointment with every new field; backfill of `cancelled_by`).
- [ ] Task 19: `occupiesSlot()` audit — every status literal classified (S4a, P5, S4a-F3/F4/F5)
  - Files: the files of the table below, `src/Context/Scheduling/Application/Query/{GetAgendaForClinicDateRange, ListAppointmentsForAnimal, ListAppointmentsForOwner, GetAppointmentDetails}/*` (DTOs gain `bool occupiesSlot`, `bool isPending`, `bool isWithdrawn` = CANCELLED|DECLINED, computed in Scheduling), `tests/Architecture/AppointmentStatusLiteralTest.php` (new)
  - Action: apply the table. Outside `src/Context/Scheduling`, no code compares an appointment status string. Presentation reads the DTO flags. Other BCs keep only status columns they do not interpret.

    | File:line (master) | Today | Meaning | Change |
    | --- | --- | --- | --- |
    | `src/Context/Scheduling/Infrastructure/Adapter/DbalAppointmentConflictChecker.php:35` | `status = 'PLANNED'` | occupies | `status IN (OCCUPYING_SQL_VALUES)` + new `hasOverlapLocked` with `FOR SHARE` (Task 20) |
    | `src/Context/Scheduling/Infrastructure/Adapter/DbalPlanningBlockAppointmentCounter.php:31` | `status = 'PLANNED'` | occupies | `IN (OCCUPYING_SQL_VALUES)`, `?excludeAppointmentId`, `FOR SHARE` variant |
    | `src/Context/Scheduling/Domain/ValueObject/AppointmentStatus.php:16` | `isTerminal()` | terminal | add DECLINED |
    | `src/Context/Scheduling/Domain/Appointment.php:62` | `schedule()` sets PLANNED | confirmed | keep; `request()` sets PENDING |
    | `src/Context/Scheduling/Domain/Appointment.php:205,213` | `cancel()` | — | PENDING branch (Task 18) |
    | `src/Context/Scheduling/Domain/Appointment.php:224,232,242,250` | no-show / complete guards | — | PENDING rules (Task 18) |
    | `src/Context/Scheduling/Application/Command/PostNewAppointmentNotice/PostNewAppointmentNoticeHandler.php:42` | `PLANNED !==` | confirmed | keep (pending gets no « Nouveau RDV ») |
    | `src/Context/Scheduling/Application/Command/RequestAppointmentReminder/RequestAppointmentReminderHandler.php:100` | `PLANNED ===` | confirmed | keep (no reminder while pending) |
    | `src/Context/Scheduling/Application/Command/SyncAppointmentNotification/SyncAppointmentNotificationHandler.php:70` | `PLANNED ===` | confirmed | keep; the silent-confirm path marks the slot notified without a message (Task 22) |
    | `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Repository/DbalAppointmentNotificationStateRepository.php:110` | `a.status = 'PLANNED'` | confirmed | keep (reminder replanning) |
    | `src/Context/Scheduling/Domain/Notification/AppointmentNotificationState.php:71,106` | `PLANNED ===` | confirmed | keep; new DECLINED branch (Task 22) |
    | `src/Context/Scheduling/Domain/Notification/AppointmentNotificationState.php:121` | `CANCELLED ===` | cancellation | keep; DECLINED handled separately, once (`declineNotifiedAt`) |
    | `src/Context/Scheduling/Infrastructure/Calendar/IcsWriter.php:50` | `STATUS:CONFIRMED/CANCELLED` | — | publish mode, `TENTATIVE` for pending (Task 28) |
    | `src/Presentation/Clinic/ViewModel/Agenda/AgendaViewBuilder.php:58` | `STATUS` map | display | add `PENDING_CLINIC_CONFIRMATION => 'attente'`, `DECLINED => 'annule'` |
    | `assets/js/pages/scheduling/agenda.js:204,260,425,433,445,681` | `r.st !== 'annule'` | occupies | unchanged logic (DECLINED maps to `annule`, pending stays busy); `'attente'` counted busy |
    | `assets/js/pages/scheduling/agenda.js:221,228,229,496,509` | card class / label / popover | display | `attente` → dashed card, « À confirmer » label, Confirm / Decline buttons (Task 23) |
    | `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php:164` | `'PLANNED' ===` upcoming | occupies | `$item->occupiesSlot` |
    | `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php:236` | `'CANCELLED' !==` | not withdrawn | `!$item->isWithdrawn` |
    | `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php:410` | lateness `'PLANNED' !==` | confirmed | keep explicit PLANNED via `!$item->isPending && $item->occupiesSlot` |
    | `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php:688,689,1191` | COMPLETED / NO_SHOW | display | unchanged; a pending item renders state `pending` (« à confirmer ») |
    | `src/Presentation/Clinic/ViewModel/Flux/FluxBoardViewBuilder.php:611` | `'PLANNED' ===` | occupies | `$item->occupiesSlot` (pending shown in « planifié » with a badge) |
    | `src/Presentation/Clinic/ViewModel/Flux/FluxBoardViewBuilder.php:613` | `'NO_SHOW'` | display | unchanged |
    | `src/Presentation/Clinic/ViewModel/Shell/ShellViewBuilder.php:218` | `'CANCELLED' !==` (count « RDV aujourd'hui ») | not withdrawn | `!$appointment->isWithdrawn` |
    | `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php:315` | `'PLANNED' ===` upcoming | occupies | `occupiesSlot`; pending labelled « à confirmer » |
    | `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php:316,552,553` | NO_SHOW / COMPLETED | display | unchanged |
    | `src/Presentation/Clinic/ViewModel/Animal/AnimalSheetBuilder.php:270` | `'PLANNED' ===` upcoming | occupies | `occupiesSlot`; pending labelled « à confirmer » |
    | `src/Presentation/Clinic/ViewModel/Animal/AnimalSheetBuilder.php:271` | NO_SHOW | display | unchanged |
    | `src/Presentation/Clinic/Controller/Animal/Sheet/DeclareAnimalDeathController.php:94` | `'PLANNED' ===` upcoming | occupies | `occupiesSlot` (pending ones are declined silently by the death flow) |
    | `src/Context/Consultation/Application/Port/AppointmentContextDTO.php:12` + `Infrastructure/Adapter/Scheduling/DbalSchedulingAppointmentContextProvider.php:27` | `status` string selected, never compared | — | unchanged (documented: not interpreted) |
    | `src/Context/Admission/Infrastructure/Persistence/Doctrine/DoctrineAdmissionReadRepository.php:242` | join by `linked_admission_id`, no status | — | unchanged |

    Not appointment statuses (untouched): `FluxBoardViewBuilder.php:514` (invoice), `ConsultationRegisterSheetBuilder.php:98`, `BillingRegisterViewBuilder.php:138`, `StockPageBuilder.php:335`, Procurement and ElectronicInvoicing enums.
  - Tests:
    - `AppointmentStatusLiteralTest`: scans `src/Presentation` and `src/Context/*` except Scheduling for `'PLANNED'`, `'PENDING_CLINIC_CONFIRMATION'` and `'DECLINED'` literals. It fails if any appears, and replaces the mirrored-list idea (AC 89).
    - One VetApp integration test per screen (agenda range JSON, dashboard, flux board, shell, client sheet, animal sheet): a pending appointment shows « à confirmer » and busy, a declined one shows as cancelled and is not counted.
- [ ] Task 20: Per-practitioner-day lock with current reads (S4d, P1, S4a-F1/F6/F13)
  - Files:
    - `src/Context/Scheduling/Application/Port/PractitionerDayLockInterface.php`
    - `src/Context/Scheduling/Infrastructure/Adapter/DbalPractitionerDayLock.php`
    - `src/Context/Scheduling/Domain/Service/LockKeys.php` (pure: slot + clinic tz → sorted local dates)
    - `src/Context/Scheduling/Application/Port/{AppointmentConflictCheckerInterface, PlanningBlockAppointmentCounterInterface}.php` (+ `…Locked` methods, `?excludeAppointmentId` on the counter)
    - adapters `DbalAppointmentConflictChecker.php`, `DbalPlanningBlockAppointmentCounter.php`
    - `src/Context/Scheduling/Domain/Service/BlockCapacity.php` (shared formula)
    - `src/Context/Scheduling/Application/Command/{ScheduleAppointment, RescheduleAppointment, CreatePlanningBlock, UpdatePlanningBlock, DeletePlanningBlock}/…Handler.php`
    - `src/Context/Scheduling/Application/Command/PurgePractitionerDayLocks/…`
    - `src/Context/Scheduling/Infrastructure/Scheduler/SchedulingScheduleProvider.php`
    - `migrations/Scheduling/Version…php` (table `scheduling__practitioner_day_locks` `(clinic_id BINARY(16), practitioner_user_id BINARY(16), local_date DATE, PRIMARY KEY(...))`)
  - Action:
    - `lock(clinicId, list<{practitionerId, localDate}>)` sorts the keys and runs `INSERT … ON DUPLICATE KEY UPDATE local_date = local_date` per key, held until commit.
    - The keys are every clinic-local date touched by `[start, end)`. A reschedule locks the union of old and new keys. A block mutation locks the practitioner's dates of the occurrence(s) touched (one-off: its date; recurring: every date up to `until`, or 60 days ahead when open-ended).
    - On `Doctrine\DBAL\Exception\DeadlockException` the adapter retries the insert loop up to 2 times, then throws `SlotTakenException`.
    - After locking, the handlers call `hasOverlapLocked` / `countOccupyingInWindowLocked` (`… FOR SHARE`).
    - Capacity uses `BlockCapacity::of()` (current formula of `ScheduleAppointmentHandler.php:92-97`, 0 = unlimited) in both handlers.
    - VetApp reschedule gains the SOFT block-type and capacity checks (no block = allowed).
    - The monthly purge deletes rows with `local_date < today − 30 d`.
    - Rejections throw typed exceptions (`SlotTakenException` for overlap/capacity, `BlockRefusesAppointmentsException`, `PractitionerNotBookableException`) instead of bare `\DomainException`. The VetApp controllers map them to the same French flashes as today.
  - Tests:
    - `tests/Unit/Context/Scheduling/Domain/Service/{LockKeysTest, BlockCapacityTest}.php`
    - `tests/Integration/Context/Scheduling/Concurrency/PractitionerDayLockTest.php`, **outside DAMA, two connections**: each transaction runs a plain SELECT on `scheduling__appointments` before locking, to reproduce the REPEATABLE READ snapshot. Cases: concurrent schedule, midnight-spanning slots, crossed reschedules, deadlock retry.
- [ ] Task 21: `BookingMotif` and `OnlineBookingSettings` (S4b, S4c, S4f, D15)
  - Files:
    - `src/Context/Scheduling/Domain/Booking/{BookingMotif, BookingMotifId, ConfirmationMode (auto|manual), OnlineBookingSettings, BookingMotifRepositoryInterface, OnlineBookingSettingsRepositoryInterface}.php`
    - ORM entities and mappers under `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/`
    - `migrations/Scheduling/Version…php` (`scheduling__booking_motifs`, `scheduling__booking_motif_practitioners`, `scheduling__online_booking_settings`)
    - `src/Context/Scheduling/Application/Command/{SaveBookingMotif, ArchiveBookingMotif, UpdateOnlineBookingSettings}/…`
    - `src/Context/Scheduling/Application/Query/{ListBookingMotifs, GetOnlineBookingSettings}/…`
  - Action:
    - Motif:
      - `label` 1–80, `description` ≤ 500, `durationMin` 5–240 and a multiple of 5;
      - `onlineEnabled`, `confirmationKnownClient` (default auto);
      - `allowedBlockTypes[]` (non-empty subset of the types where `acceptsAppointments()` holds);
      - `practitionerIds[]` (non-empty, each eligible through `MembershipEligibilityCheckerInterface`);
      - `?preventiveCareKind` (`vaccination|internal_parasite|external_parasite|other`), `newClientsAllowed`, `sortOrder`, `archivedAt`.
      - **No `confirmationNewClient`.**
    - Settings, with bounds and French validation messages:
      - `enabled` (false);
      - `gridStepMinutes` ∈ {5, 10, 15, 20, 30} (15);
      - `minLeadTimeMinutes` 0–2880 (120);
      - `minLeadTimeManualHours` 3–168 (24);
      - `horizonDays` 1–180 (60);
      - `cancellationWindowHours` 0–168 (24);
      - `newClientBookingEnabled` (false).
    - Constant `AUTO_DECLINE_MARGIN = 2 h` and `pendingExpiry(createdAt, startsAt) = min(createdAt + 48 h, startsAt − AUTO_DECLINE_MARGIN)`.
    - `isOpenForBooking()` = enabled and the clinic is active (status read through the Clinic port of Task 25).
  - Tests: `tests/Unit/Context/Scheduling/Domain/Booking/{BookingMotifTest, OnlineBookingSettingsTest}.php` (bounds, `pendingExpiry`), DBAL/ORM round-trip integration tests.
- [ ] Task 22: Confirm, decline, auto-decline, messages, bells and integration events (S4e, S4j, P2, P7, D18)
  - Files:
    - `src/Context/Scheduling/Application/Command/{ConfirmAppointment, DeclineAppointment, ScanExpiredPendingAppointments, DeclineExpiredPendingAppointment}/…`
    - `src/Context/Scheduling/Infrastructure/Scheduler/SchedulingScheduleProvider.php` (scan every 15 min)
    - `config/packages/messenger.yaml` (route `DeclineExpiredPendingAppointment` to `async`)
    - `src/Context/Scheduling/Domain/Notification/{AppointmentNotificationState, AppointmentMessageKind (+ DECLINED)}.php`
    - `migrations/Scheduling/Version…php` (`scheduling__appointment_notifications.decline_notified_at`)
    - `src/Context/Scheduling/Application/Service/AppointmentMessageComposer.php` and the labels
    - `src/Context/Scheduling/Infrastructure/Adapter/Notification/{CommandBusNotificationRequester, CommandBusPractitionerBell}.php`
    - `src/Context/Scheduling/Application/Port/PractitionerBellInterface.php` (+ `announcePendingBooking`, `announceOnlineChange`)
    - `src/Context/Scheduling/Application/EventSubscriber/{ScheduleAppointmentNotificationSync, NotifyPractitionerOnAppointmentScheduled}.php` (subscribe `AppointmentRequested`, `AppointmentConfirmed`, `AppointmentDeclined`)
    - `src/System/Notification/Domain/Message/MessagePurpose.php` (`APPOINTMENT_DECLINED` + `label()` « Demande de RDV non confirmée »)
    - `src/System/Notification/Infrastructure/Rendering/TemplateCatalog.php`
    - `templates/notification/sms/appointment_declined.txt.twig`, `templates/notification/email/appointment_declined.{html,txt}.twig`
    - `src/System/Notification/Domain/Inbox/InAppKind.php` (`ONLINE_BOOKING_PENDING`, `ONLINE_BOOKING_CHANGED`)
    - `src/Context/Scheduling/Domain/Event/{PendingAppointmentConfirmedIntegrationEvent, PendingAppointmentDeclinedIntegrationEvent, PendingAppointmentCancelledIntegrationEvent}.php`
    - `src/Context/Scheduling/Infrastructure/Messaging/Consumer/PendingAppointmentIntegrationBridge.php`
    - `src/Context/Scheduling/README.md` (D1 relaxed for these three events, with the justification of S4j)
  - Action:
    - `ConfirmAppointment` / `DeclineAppointment` load `FOR UPDATE`. A confirm after `pending_expires_at` throws `PendingAppointmentExpiredException` (« Demande expirée »).
    - The scan queues `DeclineExpiredPendingAppointment(id)` per row with `status = PENDING AND pending_expires_at <= now`. Each handler re-checks under `FOR UPDATE` and declines with reason `expired`.
    - `nextMessage`: pending → null; PLANNED after a silent confirmation → the slot is marked confirmed with no message; PLANNED otherwise → CONFIRMATION; DECLINED with an owner and `declineNotifiedAt` null → `APPOINTMENT_DECLINED`, then the timestamp is set.
    - Bells:
      - `ONLINE_BOOKING_PENDING` (key `online-booking-pending:<id>`) is posted on `AppointmentRequested` and withdrawn on Confirmed, Declined and Cancelled.
      - `ONLINE_BOOKING_CHANGED` goes to the old and new practitioner on a portal cancel or reschedule (actor = client).
    - The bridge publishes the three integration events only when `source = portal` and the appointment was pending. Payload `{appointmentId, clinicId, ownerId, animalId, startsAtUtc, reason?}`.
  - Tests:
    - unit tests for the state transitions, `nextMessage` (one decline message across two syncs, silent confirmation) and the bridge filter;
    - an integration test for the scan + per-row decline;
    - a race test (confirm vs auto-decline, two connections);
    - a Notification `TemplateCatalog` test for the new purpose.
- [ ] Task 23: VetApp — online booking settings, agenda and dashboard (S4h)
  - Files:
    - Settings:
      - `src/Presentation/Clinic/Controller/Settings/{ShowOnlineBookingSettingsController, UpdateOnlineBookingSettingsController, SaveBookingMotifController, ArchiveBookingMotifController}.php`
      - `src/Presentation/Clinic/ViewModel/Settings/SettingsPageBuilder.php` (section `reservation-en-ligne`, `ROUTES` + `can` entries)
      - `templates/clinic/settings/page.html.twig` (section partial)
      - `assets/js/pages/settings.js`
    - Agenda:
      - `src/Presentation/Clinic/Controller/Scheduling/Appointment/{ConfirmAppointmentController, DeclineAppointmentController}.php`
      - `src/Presentation/Clinic/Security/ClinicPermissionGuard.php` (+ `MANAGE_APPOINTMENTS = 'manage_appointments'`)
      - `src/Presentation/Clinic/ViewModel/Agenda/AgendaViewBuilder.php`
      - `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaRangeController.php` (row `st`, `pend`, `exp`)
      - `assets/js/pages/scheduling/agenda.js`
    - Dashboard: `src/Presentation/Clinic/ViewModel/Dashboard/DashboardViewBuilder.php` (task source « RDV en ligne à confirmer », `group: 'jour'`, 4 + « N autres RDV en ligne à confirmer »)
  - Action:
    - JSON payloads:
      - settings: `{enabled, gridStepMinutes, minLeadTimeMinutes, minLeadTimeManualHours, horizonDays, cancellationWindowHours, newClientBookingEnabled}`;
      - motif: `{id?, label, description, durationMin, onlineEnabled, confirmationKnownClient, allowedBlockTypes[], practitionerIds[], preventiveCareKind?, newClientsAllowed, sortOrder}`.
    - Eligible practitioners come from the Clinic membership query (VETERINARY, VETERINARY_ASSISTANT).
    - Warning « Horaires d'ouverture requis pour la réservation en ligne » when the opening week is empty.
    - Settings guard `MANAGE_STAFF`; Confirm/Decline guard `MANAGE_APPOINTMENTS`.
    - Pending card: dashed, badge « À confirmer », popover Confirm / Refuser, drag-move allowed, « Non venu » hidden.
    - Flashes: « RDV confirmé. », « Demande refusée — le client est prévenu. », « Demande expirée. », « Ce RDV a déjà été traité. »
  - Tests: `tests/Integration/Presentation/Clinic/{OnlineBookingSettingsTest, ConfirmDeclineAppointmentTest, AgendaPendingAppointmentTest}.php` (incl. a role without `manage_appointments` → refused).
- [ ] Task 24: Fixtures for slice 4a
  - Files: `fixtures/Context/Scheduling/Story/OnlineBookingStory.php`, `fixtures/Context/Scheduling/Factory/{BookingMotifFactory, AppointmentEntityFactory}.php`, `fixtures/Dataset/DevDataset.php`
  - Action:
    - All dates are relative to the fixture clock (today + N in the clinic timezone).
    - Clinics: one with online booking enabled (4 motifs: one archived, one manual for known clients, one with `preventiveCareKind: vaccination`, one with `newClientsAllowed`); one disabled (no pending appointment); one with an empty opening week.
    - Appointments:
      - ≥ 5 pending appointments with `pending_expires_at` in the future (dashboard « +N »), one of them expiring within the hour;
      - one declined and one auto-declined (reason `expired`);
      - one client-cancelled and one confirmed.
    - The pending ones are created through `Appointment::request()` so that the notification state and the bells exist.
  - Tests: covered by AC 85.

#### Slice 4b — online booking — `feature/portal-booking`

- [ ] Task 25: Availability computation (S4c, S4b-F1/F2/F10/F11/F12/F18)
  - Files:
    - `src/Context/Scheduling/Domain/Booking/{AvailabilityCalculator, BookableInterval, BusyInterval, OpeningWindow, DaySlots}.php` (pure, Domain value objects only)
    - `src/Context/Scheduling/Application/Query/GetOnlineAvailability/{GetOnlineAvailability, GetOnlineAvailabilityHandler, OnlineAvailability}.php`
    - Ports `src/Context/Scheduling/Application/Port/{ClinicOpeningHoursInterface, PlanningBlockOccurrencesReaderInterface, OccupyingAppointmentsReaderInterface}.php`
    - Adapters `src/Context/Scheduling/Infrastructure/Adapter/Clinic/QueryBusClinicOpeningHours.php` (on `GetClinicSettings` → `week`, `closures`, clinic status), `Infrastructure/Adapter/{DbalPlanningBlockOccurrencesReader, DbalOccupyingAppointmentsReader}.php` (expansion through `RecurrenceExpander`, all block types)
  - Action:
    - The handler builds the value objects:
      - accepting occurrences of allowed types → `BookableInterval`;
      - non-accepting occurrences of the practitioner (any anchor date) and occupying appointments → `BusyInterval`;
      - merged opening slots minus closures → `OpeningWindow`.
    - The calculator iterates local minutes and keeps a candidate when all of these hold:
      - the candidate is on the grid from the block start;
      - `[t, t + duration)` fits inside one bookable interval and one opening window;
      - it overlaps no busy interval;
      - `BlockCapacity` has room;
      - `t ≥ now + leadTime`, where `leadTime` is the manual lead time when pending.
    - Local → UTC conversion uses the method of S4c (gap skipped, repeat deduplicated).
    - Validation of `from`/`to`, horizon, `open` and 404 / `booking_not_open` per S4c.
    - Empty opening week → every day `open: false`.
  - Tests: `tests/Unit/Context/Scheduling/Domain/Booking/AvailabilityCalculatorTest.php`, table-driven:
    - blocks: recurring block + one-off LEAVE; block spanning noon split by an opening gap; adjacent opening slots; motif longer than the block;
    - capacity: 0, full;
    - grid steps;
    - lead times: normal and manual;
    - DST: `Europe/Paris` both DST Sundays incl. a block crossing 02:00; `America/Martinique` constant; `Pacific/Noumea` date boundary.

    Plus DBAL integration tests for both readers.
- [ ] Task 26: Portal booking commands (S4a, S4b, S4f, S4g, D1)
  - Files:
    - `src/Context/Scheduling/Application/Command/{BookOnlineAppointment, RescheduleOnlineAppointment, CancelOnlineAppointment}/…`
    - `src/Context/ClientPortal/Application/Command/{BookPortalAppointment, BookPortalAppointmentWithNewPet, ReschedulePortalAppointment, CancelPortalAppointment}/…`
    - ClientPortal ports `src/Context/ClientPortal/Application/Port/{OnlineBookingInterface, PetRegistrationInterface}.php` with adapters `Infrastructure/Adapter/Scheduling/CommandBusOnlineBooking.php`, `Infrastructure/Adapter/Animal/CommandBusPetRegistration.php` (pattern of `src/Context/Client/Infrastructure/Adapter/Animal/CommandBusClientAnimalRegistry.php`)
    - Scheduling port `src/Context/Scheduling/Application/Port/BookedPetArchiverInterface.php` + `Infrastructure/Adapter/Animal/CommandBusBookedPetArchiver.php` (`ArchiveAnimal` when the pet created for the booking has no other appointment and no patient link)
    - `src/Context/Scheduling/Application/EventSubscriber/ArchivePetCreatedForDeclinedBooking.php`
  - Action:
    - **Ownership (S4b-F4).** `petId` must be an ACTIVE, alive, not transferred animal whose primary owner is one of the caller's confirmed-linked clients in `{c}`, else 404 `not_found` (deceased or missing → 422 `pet_not_bookable`). `ownerId` = that primary owner. For with-new-pet with more than one confirmed link in the clinic, `ownerId` = the client of the earliest confirmed link.
    - **`BookOnlineAppointment`.** It locks (Task 20), recomputes the slot with the same calculator for that single candidate, and throws `SlotTakenException` when it is off-grid, unlisted or taken.
      - Status: from the motif's `confirmationKnownClient`; always PENDING for a new client (slice 7).
      - `source: portal`, `reason` = motif label, `pending_expires_at` when pending.
      - **Natural idempotency (S4b-F6).** An occupying appointment with the same owner, animal, motif and start created in the last 24 h is returned as the result (201 body) instead of 409.
    - **with-new-pet (D1).**
      - `CreateAnimal` with the exact defaults of S4g; then the booking, in one command-bus transaction.
      - An ACTIVE animal with the same primary owner, case-insensitive name and species created in the last 24 h is reused.
      - The appointment stores `created_pet_id` (new nullable column), so the archiver knows which animal to archive on decline or expiry.
    - **Reschedule** per S4f: only `source = portal` with a motif; new practitioner ∈ the motif's practitioners; both lock sets; the counter excludes the moved appointment; status kept; a pending one gets the manual lead time and a new `pending_expires_at`.
    - **Cancel** per S4f (`cancellableUntil`, natural idempotency, `appointment_not_modifiable`).
    - `booking_not_open` when the settings are disabled, the clinic is not active, the motif is not online or the practitioner is not bookable.
  - Tests:
    - unit tests of each handler branch;
    - `tests/Integration/Context/Scheduling/Concurrency/OnlineBookingRaceTest.php` (two owners, one slot);
    - an integration test that with-new-pet + `slot_taken` leaves no animal;
    - the archive-on-decline test.
- [ ] Task 27: Portal appointment queries and `booked` care status (S4i, S4k, D2)
  - Files:
    - `src/Context/Scheduling/Application/Query/{ListAppointmentsForOwners, GetOwnerAppointment, ListBookablePractitioners}/…`
    - `src/Context/ClientPortal/Application/Query/{ListOwnerAppointments, GetOwnerAppointmentDetail, ListBookableMotifs}/…`
    - `src/Context/ClientPortal/Application/Service/CareItemStatusPolicy.php` (+ `booked`)
    - `src/Context/ClientPortal/Application/Port/OwnerAppointmentsInterface.php` (+ motif `preventiveCareKind`)
  - Action:
    - The query shape of S4i, cursor `(starts_at_utc, id)` validated, `limit` clamped.
    - Portal status mapping: PLANNED → `confirmed`; pending → `pending_clinic_confirmation`; CANCELLED + client → `cancelled_by_client`; CANCELLED + clinic → `cancelled_by_clinic`; DECLINED → `declined`; COMPLETED → `completed`; NO_SHOW → `no_show`.
    - `motif` = `{id, label}`, or `{id: null, label: « Rendez-vous »}`. The raw `reason` and `notes` are never exposed.
    - `cancellableUntil` per S4f. `questionnaireStatus = not_started` until slice 6.
    - `relatedCareItem` = `{kind, label, dueOn, status}` of the pet's care item whose kind equals the motif's `preventiveCareKind`, else null.
    - Motifs are `suggested` when the pet has an **overdue or due-soon** care item of the motif's kind, with `suggestionReason` « Rappel de vaccin en retard » / « Rappel de vaccin à prévoir » (label by kind).
    - `ListBookablePractitioners` filters the motif's practitioners by current eligibility.
    - `CareItemStatusPolicy` applies S4k.
  - Tests: unit tests for the mapping and the policy (booked for an overdue item, a non-matching motif, a Noumea date boundary); integration tests for the queries (no `notes` / `reason` column read, keyset paging).
- [ ] Task 28: Portal API — booking routes (slice 4 routes)
  - Files:
    - `src/Presentation/Api/Portal/Resource/{BookingMotifs, BookingPractitioners, BookingAvailability, AppointmentBooking, AppointmentBookingWithNewPet, AppointmentList, Appointment, AppointmentReschedule, AppointmentCancellation}.php` + providers/processors
    - `src/Presentation/Api/Portal/View/{AppointmentView, BookingMotifView, PractitionerView, AvailabilityDayView, RelatedCareItemView}.php`
    - `src/Presentation/Api/Portal/Controller/AppointmentCalendarController.php`
    - `src/Context/Scheduling/Infrastructure/Calendar/IcsWriter.php` (+ publish mode)
    - `src/Context/Scheduling/Application/Query/GetAppointmentCalendar/…`
    - `src/Presentation/Api/Shared/Problem/ProblemFactoryInterface.php`
    - `src/Presentation/Api/Portal/Problem/PortalProblemMap.php` (`appointment_not_modifiable`, `slot_taken`, `cancellation_window_closed`, `booking_not_open`, `pet_not_bookable`, `idempotency_key_*`)
  - Action:
    - `idempotency_key_missing` / `idempotency_key_reused` are built through `ProblemFactoryInterface` implemented by the Portal map (F15).
    - **Only the two create routes** have `extraProperties: ['idempotent' => true]`. Reschedule and cancel are not idempotent-keyed: cancel is naturally idempotent, and reschedule to the same slot is a no-op 200.
    - Reschedule and cancel return the full `AppointmentView`.
    - `IdempotentProcessor` stores only responses, so the natural idempotency of Task 26 covers the crash window between commit and key write.
    - `.ics` publish mode: `METHOD:PUBLISH`, no ATTENDEE, ORGANIZER omitted, `STATUS:TENTATIVE` when pending, same UID as the e-mailed invitation, `SEQUENCE` read from the notification state. Withdrawn or terminal appointment → 404.
  - Tests: `tests/Integration/Presentation/Api/Portal/{BookingAvailabilityTest, AppointmentBookingTest, AppointmentManagementTest, AppointmentCalendarTest}.php` (every error code, tenant 404, another client's pet 404).
- [ ] Task 29: Contract export and README
  - Files: `openapi/portal.v1.yaml`, `src/Context/Scheduling/README.md` (SOFT for VetApp, HARD online; lock with current reads; statuses and pending rules; auto-decline; D1 exception for the three events)
  - Action: `make openapi-export`; `make ci`.
  - Tests: `make openapi-check`.
- [ ] Task 30: Fixtures for slice 4b
  - Files: `fixtures/Context/Scheduling/Story/OnlineBookingStory.php`, `fixtures/Context/ClientPortal/Story/PortalBookingStory.php`
  - Action:
    - All dates are relative to the fixture clock.
    - Owner appointments: upcoming (confirmed + pending), past (completed, cancelled by client, cancelled by clinic, declined, no-show) and more than 20 past appointments.
    - Appointment cases: one already inside the cancellation window; one booked inside the window less than 15 minutes ago.
    - Clinic and practitioner cases: a full day and a closed day; a practitioner on a one-off LEAVE inside a weekly block; a clinic with an empty opening week.
    - Owner cases: a deceased pet; an owner with two linked client records in the same clinic.
  - Tests: covered by AC 85.

#### Slice 5 — consents and account — `feature/portal-consents`

- [ ] Task 31: Consent history, sources and per-kind command (S5a, S5b)
  - Files: `src/Context/Client/Domain/ValueObject/{CommunicationPreferences, ConsentChange, ConsentSource (portal|clinic|system), ConsentActor, ConsentKind (+ OPTIONAL)}.php`, `src/Context/Client/Domain/Client.php`, `Domain/Repository/ConsentHistoryRepositoryInterface.php`, `Infrastructure/Persistence/Doctrine/{Entity/ConsentHistoryEntity, Repository/DbalConsentHistoryRepository}.php`, `Infrastructure/Persistence/Doctrine/Repository/DoctrineClientRepository.php` (`findForUpdate`), `migrations/Client/Version…php` (table + `JSON_TABLE` backfill), `Application/Command/{SetClientConsent, UpdateClientCommunicationPreferences, CreateClient}/…` (both consent commands implement `CommandInterface`; `UpdateClientCommunicationPreferences` gains `source`, `actorType`, `actorId`, `authorLabel`, `expectedVersion`), `Application/Query/{ListClientConsentHistory, GetClientConsents}/…`, `src/Presentation/Clinic/Controller/Client/Sheet/UpdateClientCommunicationPreferencesController.php` (+ form `version` field, stale-save flash), callers of `CreateClient`: `src/Context/Billing/Infrastructure/Adapter/Client/CommandBusCounterClientRegistry.php` (+ its Billing port DTO), `src/Presentation/Clinic/ViewModel/Directory/DirectoryRegistrar.php`, `src/Presentation/Clinic/ViewModel/Flux/DeskRegistration.php`, fixtures `DirectoryCrowdStory`, `FluxDayStory`, `ConsultationDemoStory`, `NotificationDemoStory`, `src/Context/Client/README.md`
  - Action:
    - `revise()` returns `list<ConsentChange{kind, channel, action}>`; the aggregate records them, the repository appends one history row per change in the same transaction; an empty diff writes nothing.
    - `SetClientConsent` changes one kind only and keeps `ensureNotArchived()`; for `billing` / `clinical_documents` an empty channel list is refused (`validation_failed`).
    - Both handlers load the client `FOR UPDATE`; `UpdateClientCommunicationPreferences` refuses when `expectedVersion` differs (`StaleConsentsException` → VetApp flash « Les consentements ont changé depuis l'ouverture de la fiche »).
    - `CreateClient` writes `granted` rows with `source: clinic`, actor = staff (or `system` for fixtures).
    - Backfill: `INSERT … SELECT` over `JSON_TABLE(communication_preferences, '$.consents[*]' …)` exploding channels; NULL / invalid JSON skipped; `approximate = 1`.
    - `ClientCommunicationPreferencesUpdated` stays v1 (no consumer).
  - Tests: `tests/Unit/Context/Client/Domain/{CommunicationPreferencesDiffTest, ClientConsentTest}.php`, `tests/Unit/Context/Client/Application/Command/{SetClientConsentHandlerTest, UpdateClientCommunicationPreferencesHandlerTest, CreateClientHandlerTest}.php`, `tests/Integration/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DbalConsentHistoryRepositoryTest.php`, `tests/Integration/Context/Client/Migration/ConsentHistoryBackfillTest.php` (multi-channel, NULL column), `tests/Integration/Presentation/Clinic/Client/UpdateCommunicationPreferencesTest.php` (source/author, stale save).
- [ ] Task 32: Archived client receives no optional message (S5c)
  - Files: `src/Context/Client/Application/Query/GetClientNotificationContact/ClientNotificationContact.php` (`channelsFor()`), `src/Context/Client/Domain/ValueObject/ConsentKind.php` (`OPTIONAL`, `isOptional()`), `src/Context/Client/README.md` (rule « un client archivé ne reçoit aucun message optionnel »), `src/Context/Scheduling/Application/Command/RequestAppointmentReminder/RequestAppointmentReminderHandler.php` (keeps its `!archived` gate, comment pointing to the rule)
  - Action: `channelsFor(kind)` returns `[]` when `archived` and `ConsentKind::from(kind)->isOptional()`; non-optional kinds unchanged (billing and clinical documents still pick their channel). No consent is withdrawn anywhere: no `withdrawOptionalConsents`, no `ClientLinkRevokedIntegrationEvent`, no reason on `ClientLinkRevoked`. Senders of vaccination reminders or marketing do not exist today; any future one must use `channelsFor()` (stated in the README).
  - Tests: `tests/Unit/Context/Client/Application/Query/ClientNotificationContactTest.php` (archived × optional / non-optional), `tests/Unit/Context/Scheduling/Application/Command/RequestAppointmentReminderHandlerTest.php` (archived client with active consent → no reminder), `tests/Integration/Context/Client/ArchiveRoundTripTest.php` (archive then unarchive keeps consents and history).
- [ ] Task 33: Portal consents API (slice 5 routes)
  - Files: `src/Context/ClientPortal/Application/Command/SetPortalConsent/{SetPortalConsent, SetPortalConsentHandler}.php` (new), `src/Context/ClientPortal/Application/Query/GetPortalConsents/…`, `ListPortalConsentHistory/…`, `src/Context/ClientPortal/Application/Port/ClientConsentsInterface.php` + `Infrastructure/Adapter/Client/{QueryBusClientConsents, CommandBusClientConsents}.php`, `config/services.yaml` (alias), `src/Presentation/Api/Portal/Resource/{Consents, ConsentUpdate, ConsentHistory}.php` + providers/processor, `View/{ConsentView, ConsentHistoryItemView}.php`
  - Action:
    - Read: the **union** of channels per kind across every confirmed-linked, non-archived client record of the user in that clinic; each kind returned with `effect: send_control` (optional kinds) or `channel_preference` (billing, clinical documents).
    - Write: `SetPortalConsent(userId, clinicId, kind, channels)` — one ClientPortal command whose handler loops through the port inside **its single** command-bus transaction, applying `SetClientConsent(source: portal, actorType: portal_user, actorId: userId, authorLabel: OwnerProfile full name)` to every confirmed-linked record of the clinic, skipping archived ones; one history row per record and channel.
    - History: rows of those records, grouped by `(kind, channel, at, source)` (one line for a change applied to two records), newest first, cursor `(at, id)`; staff authors shown as « La clinique ».
    - channels outside `sms|email` → 422; a residual lock conflict → 409 `conflict`; no linked active record → 404.
  - Tests: `tests/Unit/Context/ClientPortal/Application/Command/SetPortalConsentHandlerTest.php` (two records, one archived), `tests/Unit/Context/ClientPortal/Application/Query/GetPortalConsentsHandlerTest.php` (divergent records), `tests/Integration/Presentation/Api/Portal/ConsentsTest.php`.
- [ ] Task 34: Profile update (S5e)
  - Files: `src/Context/ClientPortal/Domain/Profile/OwnerProfile.php` (`rename`, `changeAddress`), `Application/Command/UpdateOwnerProfile/…`, `src/Presentation/Api/Portal/Resource/MeUpdate.php` + processor
  - Action: `PATCH /me` with `firstName?`, `lastName?` (1–60, trimmed, non-empty), `address?` (`streetLine1`, `postalCode?`, `city`, `countryCode`; no geocoding); returns `account`; clinic `Client` records untouched.
  - Tests: `tests/Unit/Context/ClientPortal/Domain/Profile/OwnerProfileTest.php`, `tests/Integration/Presentation/Api/Portal/MeUpdateTest.php` (validation).
- [ ] Task 35: OTP subjects, email change and email login factor (S5e, D6, D8)
  - Files: `src/System/IdentityAccess/Domain/Otp/{OtpChallenge, OtpPurpose (+ email_change, account_deletion, email_verification), OtpChannel (+ EMAIL), OtpSubjectType (invitation|user)}.php`, `Infrastructure/Persistence/Doctrine/Entity/OtpChallengeEntity.php`, `Infrastructure/Persistence/Doctrine/Repository/DbalOtpChallengeRepository.php`, `migrations/IdentityAccess/Version…php` (`invitation_ref` → `subject_ref`, + `subject_type`, + `target_email` nullable), `src/System/IdentityAccess/Domain/User.php` (`confirmEmail(email, now)`, `releaseUnverifiedEmail()`), `Application/Command/{RequestPortalActionOtp, StartPortalEmailChange, ConfirmPortalEmailChange, ResendPortalOtp}/…`, `src/System/Notification/Domain/Message/MessagePurpose.php` (`PORTAL_EMAIL_VERIFICATION`, `PORTAL_EMAIL_CHANGED_NOTICE`; `isSentByKiveto`, `linkValidity` PT10M, `label()`), `src/System/Notification/Infrastructure/Rendering/TemplateCatalog.php`, templates `templates/notification/sms/portal_otp_{email_change,account_deletion}.txt.twig`, `templates/notification/email/{portal_email_verification,portal_email_changed_notice}.{html,txt}.twig`, `src/System/Notification/README.md` (body erased after hand-over for `PORTAL_EMAIL_VERIFICATION`), `src/System/IdentityAccess/Application/Port/…PortalOtpRequest` (+ purpose), resources `{EmailChangeOtp, EmailChange, EmailChangeVerification}.php` + processors, `src/Presentation/Api/Portal/Resource/OtpResend.php` (enum `[sms, email]`)
  - Action:
    - `OtpChallenge::start(…)`: purpose → required subject (`invitation` → invitation, action purposes → user, `login` → none); `sameTarget` compares `(subject_type, subject_ref)`; a code of user A is refused for user B.
    - `email_verification` carries `target_email`; `/verify {challengeId, code}` verifies (10 min, 5 attempts), then under a lock on the target address: verified holder → 422 `email_taken`; unverified holder → `releaseUnverifiedEmail()`; caller → `confirmEmail()`; previous verified address → `PORTAL_EMAIL_CHANGED_NOTICE`.
    - `ResendPortalOtp`: `channel: email` only for `login` challenges whose phone owns an ACTIVE PORTAL user with a verified email; otherwise the challenge is left untouched (SMS code still valid) and the same 202 is returned; action challenges are never sent by email.
    - Action OTPs consume `portal_otp_phone`, `portal_otp_phone_daily`, `portal_otp_ip`; SMS text per purpose (« Code de confirmation de suppression de votre compte Kiveto : … »).
    - Record in the IdentityAccess README: a verified email is a login factor (reverses slice-1 D18).
  - Tests: `tests/Unit/System/IdentityAccess/Domain/Otp/OtpChallengeSubjectTest.php`, `tests/Unit/System/IdentityAccess/Application/Command/{StartPortalEmailChangeHandlerTest, ConfirmPortalEmailChangeHandlerTest, ResendPortalOtpHandlerTest}.php`, `tests/Integration/System/IdentityAccess/Infrastructure/Persistence/Doctrine/Repository/DbalOtpChallengeRepositoryTest.php` (migration round trip), `tests/Integration/Presentation/Api/Portal/EmailChangeTest.php`.
- [ ] Task 36: Data export (S5f, D7)
  - Files: `src/Context/ClientPortal/Domain/Export/{DataExport, DataExportStatus (pending|ready|failed|expired), DataExportRepositoryInterface}.php` + entity/DBAL repository/migration (`client_portal__data_exports`), `Application/Command/{RequestDataExport, BuildDataExport (ExternalApiCommandInterface), RecordDataExport, DeleteDataExportFile, SweepExpiredDataExports}/…`, `Application/Query/{ListDataExports, GetDataExportFile}/…`, `Infrastructure/Export/DataExportBuilder.php`, `templates/portal/export/summary.html.twig`, `MessagePurpose::PORTAL_DATA_EXPORT_READY` + templates (notice only, no link), `config/packages/messenger.yaml` (route `DeleteDataExportFile` → `async`), `Infrastructure/Scheduler/ClientPortalScheduleProvider.php` (nightly sweep), `config/services.yaml` (`limiter.portal_data_export`), resources `{DataExportRequest, DataExportList}.php` + processor/provider, `src/Presentation/Api/Portal/Controller/{DataExportFileController, SignedDataExportDownloadController}.php`
  - Action:
    - Content (canonical, `schemaVersion: 1`): `account.json` (phone, email, profile, terms acceptance), `clinics/<clinicId>.json` (`clinic {name, city}`, link status and dates, consents, consent history, appointments `{startAt, pet, motif, status}`, published documents `{type, title, issuedAt}` — no files), `summary.pdf`; a user with zero links gets `account.json` and `summary.pdf` only. Slices 6 and 7 add their requests and booking requests (Tasks 40, 48).
    - The limiter is consumed when the request is accepted and refunded when the build fails; over the limit → 429 `export_rate_limited`.
    - `GET /me/data-exports/{id}/file` (authenticated, own export, `ready`) → 302 to a 5-min signed `/files/exports/{c?}/{id}` (`Cache-Control: private, no-store`); expired/deleted → 404.
    - At `ready`: `DeleteDataExportFile` delayed 48 h; nightly sweep deletes any `client_portal/exports/*` older than 48 h; deletion cascade (Task 37) deletes all exports of the user.
  - Tests: `tests/Unit/Context/ClientPortal/Infrastructure/Export/DataExportBuilderTest.php` (content, zero links), handler tests, `tests/Integration/Presentation/Api/Portal/DataExportTest.php` (limiter refund, signed URL, 48 h with `mockTime()`).
- [ ] Task 37: Account deletion (S5g)
  - Files: `src/Context/ClientPortal/Application/Command/{RequestAccountDeletion, DeletePortalAccount}/…`, `src/System/IdentityAccess/Domain/User.php` (`disablePortal(now)`, `anonymisePortal(now)` → phone/email/emailVerifiedAt NULL, `anonymisedAt`), `UserEntity` + migration (`anonymised_at`), `Domain/Session/RefreshTokenRepositoryInterface.php` + `DbalRefreshTokenRepository.php` (`revokeAllForUser(userId, at)`), `Application/Command/{DisablePortalUser, AnonymisePortalUser, PurgePortalPhoneTraces}/…`, `src/Context/ClientPortal/Domain/Profile/OwnerProfile.php` (`anonymise()`: names « Compte supprimé », address NULL), resources `{DeletionOtp, DeletionRequest}.php` + processors, migration check against slice-1 `migrations/IdentityAccess/Version20261029130000.php` (PORTAL users with NULL phone are DISABLED — consistent)
  - Action:
    - 202 transaction: `DisablePortalUser` + `revokeAllForUser`, then dispatch `DeletePortalAccount` (async, idempotent, under the per-phone lock row).
    - Store inventory (each with its action): refresh tokens → revoked; `ClientLink`s → revoked; pending `PortalInvitation`s of the phone → revoked; onboarding grants and open OTP challenges of the phone → revoked, `identity_access__otp_challenges.phone` of the user's challenges → NULL-able purge (delete rows); `shared__idempotency_keys` of the principal → deleted; phone-keyed `shared__rate_limiter_cache` rows → deleted; `identity_access__portal_phone_locks` row → deleted after the cascade; data exports and their files → deleted; `notification__messages` of Kiveto-sent purposes to that phone → recipient masked (bodies already erased); clinic `Client` records, their consents and clinic-sent messages → kept (clinic is the controller, legal retention).
  - Tests: `tests/Unit/Context/ClientPortal/Application/Command/DeletePortalAccountHandlerTest.php` (retry halfway), `tests/Unit/System/IdentityAccess/Domain/UserAnonymiseTest.php`, `tests/Integration/Presentation/Api/Portal/AccountDeletionTest.php`.
- [ ] Task 38: Fixtures for slice 5
  - Files: `fixtures/Context/Client/Story/ConsentsStory.php`, `fixtures/Context/ClientPortal/Story/PortalAccountStatesStory.php`, `fixtures/Dataset/PortalDataset.php`
  - Action: history rows written through the domain diff (or `ConsentHistoryRepositoryInterface`), never raw SQL. States: clients with no, partial and all consents; history mixing `portal` and `clinic` sources and a backfilled (`approximate`) row; an **archived client with active optional consents** (and its unarchived twin); a user linked to two records of one clinic with divergent consents; a PORTAL user with verified email, one with a pending email change, one anonymised (deleted); exports pending, ready and expired.
  - Tests: covered by AC 85.

#### Slice 6 — requests — `feature/portal-requests`

- [ ] Task 39: Request aggregates and persistence (S6a, S6c, S6e)
  - Files: `src/Context/ClientPortal/Domain/Request/{PreVisitQuestionnaire, PetChangeRequest, PrescriptionRenewalRequest, PortalRequestId, RequestStatus (submitted|done|rejected|closed), ClosureReason, Symptom, Temperament, PetCondition, ChangeField, ProposedChange, RequestResolution}.php`, `Domain/Exception/{PortalRequestAlreadyResolvedException, QuestionnaireLockedException, PrescriptionNotRenewableException, PetNotEligibleException}.php`, `Domain/Request/{PreVisitQuestionnaireRepositoryInterface, PetChangeRequestRepositoryInterface, PrescriptionRenewalRequestRepositoryInterface}.php`, `Infrastructure/Persistence/Doctrine/Entity/{PreVisitQuestionnaireEntity, PetChangeRequestEntity, PrescriptionRenewalRequestEntity}.php`, `Infrastructure/Persistence/Doctrine/Repository/Dbal{PreVisitQuestionnaire, PetChangeRequest, PrescriptionRenewalRequest}Repository.php`, `migrations/ClientPortal/Version…php`
  - Action:
    - Tables `client_portal__pre_visit_questionnaires` (unique `(clinic_id, appointment_id)`, `version`, `answers` JSON, `locked_at`, `status`, `closed_reason`), `client_portal__pet_change_requests` (`field`, `proposed_value`, `normalised_value` JSON, `current_value_at_submit`, `note`, resolution columns, index `(clinic_id, status, submitted_at)`, `(clinic_id, pet_id, field, status)`), `client_portal__prescription_renewal_requests` (`prescription_id`, `pet_condition`, `note`, `resulting_prescription_id`, resolution columns, generated column `open_key = IF(status = 'submitted', prescription_id, NULL)` with UNIQUE `(clinic_id, open_key)`).
    - Common fields of S6a; `resolve(done|rejected, byUserId, byName, ?message ≤ 280, now)`, `close(reason, now)`, `attachNotification(messageId)`; `PetChangeRequest::eraseFreeText()` and `PrescriptionRenewalRequest::eraseFreeText()` (account deletion); `PreVisitQuestionnaire::answer(…)` refuses when locked; `symptoms` with `none` exclusive; `version` must be 1.
    - Validation per field (S6e) in `ProposedChange::of(field, raw, ?measuredOn, ?note)` producing the normalised value (`weightGrams` int, `reproductiveStatus`, 15-digit chip).
  - Tests: `tests/Unit/Context/ClientPortal/Domain/Request/*Test.php` (transitions, exclusivity, validation table), `tests/Integration/Context/ClientPortal/Infrastructure/Persistence/Doctrine/Repository/Dbal*RequestRepositoryTest.php` (incl. the `open_key` unique key with two connections, outside DAMA).
- [ ] Task 40: Request commands, queries and ports (S6a–S6f)
  - Files: `src/Context/ClientPortal/Application/Command/{SubmitQuestionnaire, SubmitPetChangeRequest, SubmitRenewalRequest, ResolvePortalRequest, ClosePortalRequestsForSubject}/…`, `Application/Query/{GetQuestionnaire, ListQuestionnaireSummaries, ListOwnerPortalRequests, ListClinicPortalRequests, GetPortalRequest, GetLatestRenewalStatuses}/…`, `Application/Port/{OwnerAppointmentsInterface (extended: status, startsAt, ownerId, animalId, occupiesSlot), ConsultationStartsInterface, PetDirectoryInterface (extended: current name, weight, reproductive status, chip, life status), PortalRequestBellInterface, PrescriberDirectoryInterface, NotificationRequesterInterface (new `requestPortalRequestResolved`), ClientDirectoryInterface}.php`, `Application/Port/PortalClientContact.php` (+ `?string transactionalChannel`), adapters `Infrastructure/Adapter/{Scheduling/QueryBusOwnerAppointments, Consultation/QueryBusConsultationStarts, Animal/QueryBusPetDirectory, Notification/CommandBusPortalRequestBell, Clinic/QueryBusPrescriberDirectory, Notification/CommandBusNotificationRequester, Client/QueryBusClientDirectory}.php`, consumers `Infrastructure/Messaging/Consumer/{ClientArchivedIntegrationEventConsumer (extend), PrescriptionCancelledIntegrationEventConsumer (extend)}.php`, `config/services.yaml` (port aliases), `config/packages/messenger.yaml` (nothing new: commands are sync)
  - Action:
    - Submit commands apply S6b (ownership, lock) and S6e (limiter checked in the processor, open-per-pet+field cap in the handler); `SubmitRenewalRequest` checks S6c and returns the existing request when one is `submitted`.
    - `ResolvePortalRequest(clinicId, requestId, outcome, actorUserId, actorName, ?message, ?resultingPrescriptionId)`: `FOR UPDATE`, domain guard, `resultingPrescriptionId` must be an ISSUED prescription of the same pet issued after `submittedAt` (Prescription port), then notification S6f (idempotency key `portal-request-resolved:<id>`), store the message id or flag « client injoignable ».
    - `ClosePortalRequestsForSubject` is dispatched on: link revoked / client archived (`client_archived`), account deleted (`account_deleted`, also `eraseFreeText()`), prescription cancelled (`prescription_cancelled`), pet archived or deceased (Animal events through the existing consumers or a query at read time — choose the consumer when an integration event exists, else filter at read time and close lazily), appointment cancelled/declined (questionnaire, via `PendingAppointmentCancelled/Declined` for portal appointments and a read-time check otherwise).
    - Queries: `ListOwnerPortalRequests(userId, clinicId, cursor)` scoped in SQL to confirmed links; `ListClinicPortalRequests(clinicId, status, kind, cursor)` excludes `closed` and questionnaires; `ListQuestionnaireSummaries(clinicId, appointmentIds[])` → `{appointmentId, updatedAt, mayBite}` for the agenda badge; `GetLatestRenewalStatuses(clinicId, prescriptionIds[])` for the slice-2 list (`renewalStatus`).
    - Slice-4 views: `questionnaireStatus` = `not_started|submitted` from `ListQuestionnaireSummaries`.
    - Slice-5 export (`DataExportBuilder`) and deletion (`DeletePortalAccount`) are extended: export lists the user's requests and questionnaires; deletion closes them with `account_deleted` and erases free text.
  - Tests: `tests/Unit/Context/ClientPortal/Application/Command/{Submit*, ResolvePortalRequest, ClosePortalRequestsForSubject}HandlerTest.php`, `Application/Query/*HandlerTest.php`, adapter tests under `tests/Unit/Context/ClientPortal/Infrastructure/Adapter/`, `tests/Integration/Context/ClientPortal/Pipeline/PortalRequestClosurePipelineTest.php`.
- [ ] Task 41: Portal API — requests (slice 6 routes)
  - Files: `src/Presentation/Api/Portal/Resource/{Questionnaire, QuestionnaireUpdate, PetChangeRequestCreation, RenewalRequestCreation, OwnerRequestList}.php`, `State/{QuestionnaireProvider, QuestionnaireUpdateProcessor, PetChangeRequestProcessor, RenewalRequestProcessor, OwnerRequestListProvider}.php`, `View/{QuestionnaireView, PortalRequestView}.php`, `Support/PortalRateLimits.php` (or a new `Support/PortalRequestLimits.php` with code `rate_limited`), `config/services.yaml` (`limiter.portal_requests`), `src/Presentation/Api/Portal/Problem/PortalProblemMap.php` (`questionnaire_locked`, `prescription_not_renewable`, `pet_not_eligible`, `rate_limited`)
  - Action: routes of the contract plus `GET /clinics/{c}/requests`; every provider/processor calls `PortalClinicGuard` first; POSTs `extraProperties: ['idempotent' => true]`; `PUT …/questionnaire` is naturally idempotent; Assert constraints with French messages for S6e (`validation_failed` pointers on `proposedValue`, `measuredOn`, `note`, `symptoms`, `version`).
  - Tests: `tests/Integration/Presentation/Api/Portal/{PortalQuestionnaireApiTest, PortalChangeRequestApiTest, PortalRenewalRequestApiTest, PortalOwnerRequestsApiTest}.php`.
- [ ] Task 42: VetApp « Demandes Portal », agenda and cockpit (S6g)
  - Files: `src/Presentation/Clinic/Controller/PortalRequest/{ListPortalRequestsController, ShowPortalRequestController, ResolvePortalRequestController}.php` (resolve extends `Controller/Shared/AbstractSheetAction`: CSRF, session clinic, PRG + flash « Demande déjà traitée par {name} » on `PortalRequestAlreadyResolvedException`), `src/Presentation/Clinic/Security/ClinicPermissionGuard.php` (constants `MANAGE_ANIMALS`, `CREATE_PRESCRIPTION`), `templates/clinic/portal_requests/{index,show}.html.twig`, `assets/js/pages/portal-requests.js` + `assets/app.js` `PAGE_MODULES` + `importmap.php`, `src/Presentation/Clinic/ViewModel/Dashboard/{DashboardViewBuilder, DashboardTask}.php` (icon `portal` + its SVG in the dashboard template), `src/System/Notification/Domain/Inbox/InAppKind.php` (`PORTAL_REQUEST` + label/icon), `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaRangeController.php` + `src/Presentation/Clinic/ViewModel/Agenda/AgendaViewBuilder.php` (questionnaire badge from `ListQuestionnaireSummaries`), `src/Presentation/Clinic/Controller/Scheduling/Appointment/ShowAppointmentQuestionnaireController.php` (new JSON `__invoke`, `GET /clinic/agenda/appointments/{id}/questionnaire`), `assets/js/pages/scheduling/agenda.js` (popover section, `textContent` only), `src/Presentation/Clinic/Controller/Consultation/View/ConsultationDetailsController.php` + `assets/js/pages/consultation.js` (questionnaire block when `ConsultationDetailsDTO::appointmentId` is not null, asked directly to ClientPortal `GetQuestionnaire` — no Consultation port), `src/Presentation/Clinic/ViewModel/Shared/ShellViewBuilder.php` (menu entry + count)
  - Action: S6g; the « Traiter » form of a renewal offers a select of the pet's ISSUED prescriptions issued after the request (optional) — no `RenewPrescription` action; the change-request detail shows « Valeur à la demande / Valeur actuelle / Valeur proposée » and a weight request offers a one-click link to the animal sheet weight form prefilled (staff still apply manually, author « Propriétaire (Portal) » suggested).
  - Tests: `tests/Integration/Presentation/Clinic/PortalRequest/{PortalRequestsControllerTest, ResolvePortalRequestControllerTest}.php`, `tests/Integration/Presentation/Clinic/Scheduling/AgendaQuestionnaireTest.php`, `tests/Integration/Presentation/Clinic/Consultation/ConsultationQuestionnaireTest.php`, XSS test (a note `<img src=x onerror=alert(1)>` renders inert on the three screens).
- [ ] Task 43: Fixtures for slice 6
  - Files: `fixtures/Context/ClientPortal/Story/PortalRequestsStory.php`, `fixtures/Dataset/PortalDataset.php`
  - Action: a clinic with no request (empty queue); change requests of each `ChangeField` incl. `other` with a long note (truncation); submitted, done (with and without staff message, with a `resultingPrescriptionId`) and rejected renewals; a renewal on an expired prescription; a renewal closed because its prescription was cancelled; > 4 open requests (dashboard « N autres demandes Portal »); questionnaires: one `submitted` with `may_bite` and long `notes` on an upcoming appointment, one locked (past), one on a consultation started from its appointment (cockpit demo). Dates relative to the fixture clock.

#### Slice 7 — organic arrival — `feature/portal-organic-arrival`

- [ ] Task 44: Registration and unknown-number budget (S7a, S7a')
  - Files:
    - `src/System/IdentityAccess/Domain/Otp/{OtpChallenge, OnboardingPurpose (invitation|registration), OtpChallengeRepositoryInterface}.php`
    - `Infrastructure/Persistence/Doctrine/Entity/OtpChallengeEntity.php` and `Repository/DbalOtpChallengeRepository.php`
    - `migrations/IdentityAccess/Version…php`: `onboarding_purpose` VARCHAR(16) NULL, `onboarding_consumed_at` DATETIME NULL; existing grants backfilled as `invitation`
    - `Application/Command/{VerifyPortalLoginOtp, RegisterPortalUser, RequestPortalOtp}/…`
    - `src/System/IdentityAccess/Domain/Exception/PortalAccountExistsException.php`
    - `src/Context/ClientPortal/Domain/Profile/OwnerProfile.php` (`register(userId, firstName, lastName, termsVersion, now)`)
    - `src/Context/ClientPortal/Application/Command/CompletePortalRegistration/…`
    - `src/Context/ClientPortal/Application/Port/PortalIdentityInterface.php` (`registerPortalUser(…)`) and its adapter
    - `src/Presentation/Api/Portal/Resource/Registration.php` + `State/RegisterProcessor.php`
    - `src/Presentation/Api/Portal/Support/PortalRateLimits.php`
    - `src/Presentation/Api/Portal/Problem/PortalProblemMap.php` (`account_exists` 409; neutral `onboarding_invalid` title)
    - `config/packages/security.yaml`: public `auth/register`, anchored as `auth/register$`
    - `config/services.yaml`: `limiter.portal_register`, `limiter.portal_otp_unknown_network`, `limiter.portal_otp_unknown_device`
    - `.env` (`PORTAL_OTP_UNKNOWN_HOURLY_CAP=500`)
    - `src/System/IdentityAccess/README.md`
  - Action:
    - `VerifyPortalLoginOtp`: `registration_required` now mints a `registration` grant (15 min) and returns `registrationToken`.
    - `invitation` grants keep slice-1 behaviour. `revokeOnboardingGrantsForPhone` only touches `invitation` grants.
    - `CompletePortalRegistration` runs the S7a order in one command transaction. On a replay by the user it created, it returns the same response with a new token pair. Otherwise:
      - a consumed grant → `onboarding_invalid`;
      - an existing ACTIVE user → `PortalAccountExistsException` → 409.
    - Unknown-number budget in `RequestPortalOtpHandler::isKnown` branch, in this order: network key (IPv4 → `a.b.c.0/24`, IPv6 → first 64 bits), then device hint (only when `X-Device-Id` is present, never as the only check), then global. The IP and the device id come from a new optional `RequestPortalOtp::$requestContext` filled by `RequestOtpProcessor`. Any exhaustion → `sms_suppressed`. Global exhaustion consumes `portal_otp_unknown_alert` → `logger->critical`.
  - Tests:
    - `tests/Unit/System/IdentityAccess/Domain/Otp/OtpChallengeOnboardingPurposeTest.php`
    - `tests/Unit/System/IdentityAccess/Application/Command/{RegisterPortalUserHandlerTest, RequestPortalOtpUnknownBudgetTest}.php`
    - `tests/Unit/Context/ClientPortal/Application/Command/CompletePortalRegistrationHandlerTest.php`
    - `tests/Integration/System/IdentityAccess/…/DbalOtpChallengeRepositoryTest.php` (purpose filter)
    - `tests/Integration/Presentation/Api/Portal/RegistrationTest.php`
    - the AC 74 race outside DAMA
- [ ] Task 45: Link suggestions (S7b)
  - Files:
    - `src/Context/Client/Application/Query/ListClientsByMobilePhone/{ListClientsByMobilePhone, …Handler, MobileClientMatch}.php`: `{clinicId, clientId, firstName, lastName, animalNames[]}`; excludes archived clients and non-active clinics, the latter through the Client read model's join on clinic status exposed by a Clinic query
    - `migrations/Client/Version…php`: index `idx_contact_method_type_value (type, value, client_id)` on `client__contact_methods`
    - `src/Context/ClientPortal/Domain/Link/{ClientLink, ClientLinkRepositoryInterface}.php`: `requestFromSuggestion()` → pending with null `linkedAt`; `confirmPending(now)`; `reject(now)`; `findActiveByUser(userId)` (pending + confirmed)
    - `Application/Command/{AcceptLinkSuggestion, ConfirmClientLink, RejectClientLink}/…`
    - `Application/Query/{ListLinkSuggestions, ListOwnerClinics}/…`: `OwnerClinic::$linkStatus`, nullable `linkedAt`, confirmed wins over pending
    - `Application/Port/ClientDirectoryInterface.php` (+ `mobileMatches(phone)`) and its adapter
    - `Infrastructure/Security/SuggestionIdSigner.php`: HMAC-SHA256 keyed by `%kernel.secret%`
    - resources `{LinkSuggestions, LinkSuggestionAcceptance}.php` + provider/processor
    - `src/Presentation/Api/Portal/State/MeProvider.php` and `View/ClinicLinkView.php`
    - VetApp « Demandes Portal » type « Rattachement »: `src/Presentation/Clinic/Controller/PortalRequest/{ConfirmClientLinkController, RejectClientLinkController}.php`, the detail template and the JS from Task 42
  - Action:
    - The suggestion groups the matching files per clinic. Accepting creates one pending link per matching file of that clinic.
    - A clinic already linked (any status) to every matching file is excluded.
    - A file confirmed-linked to another account stays suggested.
    - The staff detail shows:
      - the Portal profile name;
      - per file: its name, animals, mismatch badge (case- and accent-insensitive comparison of last names), and the « déjà relié à un autre compte Portal » badge.
    - Confirm reactivates an existing link, never inserts a duplicate (`uniq_client_link`).
    - Reject revokes with reason `link_rejected` (no consent effect — slice 5 no longer withdraws consents on revocation).
  - Tests:
    - `tests/Integration/Context/Client/…/ListClientsByMobilePhoneTest.php` (label `mobile` only, archived and suspended excluded, family grouping input)
    - `tests/Unit/Context/ClientPortal/Domain/Link/ClientLinkPendingTest.php`
    - handler tests
    - `tests/Integration/Presentation/Api/Portal/LinkSuggestionsTest.php`
    - `tests/Integration/Presentation/Clinic/PortalRequest/ClientLinkRequestTest.php`
- [ ] Task 46: Clinic integration event (S7c)
  - Files: `src/Context/Clinic/Domain/Event/ClinicProfileChangedIntegrationEvent.php`, `src/Context/Clinic/Infrastructure/Messaging/Consumer/ClinicProfileChangedIntegrationBridge.php`, `src/Context/Clinic/README.md`
  - Action: thin `{clinicId}` payload, bridged from `ClinicCreated`, `ClinicRenamed`, `ClinicOpeningHoursUpdated`, `ClinicLetterheadUpdated`, `ClinicTimeZoneChanged`, `ClinicSuspended`, `ClinicActivated`, `ClinicClosed`. `ClinicGroupSuspended` and `ClinicGroupActivated` publish one event per member clinic, read through the Clinic repository.
  - Tests: `tests/Unit/Context/Clinic/Infrastructure/Messaging/ClinicProfileChangedIntegrationBridgeTest.php` (every bridged event, group fan-out).
- [ ] Task 47: `ClinicListing` bounded context (S7c)
  - Files:
    - `src/Context/ClinicListing/Domain/{ClinicListing, ListingSettings, ListingCoordinates}.php` and repositories
    - `Application/Command/{RefreshClinicListing, RebuildClinicListings, UpdateListingSettings, GeocodeClinicListing (ExternalApiCommandInterface), AttachListingCoordinates}/…`
    - `Application/Query/{SearchClinicListings, GetClinicListing}/…`
    - `Application/Port/{ClinicProfileInterface, AddressGeocoderInterface, OnlineBookingStatusInterface}.php`
    - `Infrastructure/Adapter/{Clinic/QueryBusClinicProfile, Scheduling/QueryBusOnlineBookingStatus}.php`
    - `Infrastructure/Geocoding/{GeoplateformeAddressGeocoder, FakeAddressGeocoder (when@dev/when@test)}.php`
    - `Infrastructure/Persistence/…`
    - `Infrastructure/Messaging/Consumer/ClinicProfileChangedIntegrationEventConsumer.php`
    - `Infrastructure/Console/RebuildClinicListingsCommand.php` (`clinic-listing:rebuild`)
    - `src/Context/ClinicListing/README.md`, `migrations/ClinicListing/Version…php`
    - `config/packages/{doctrine,doctrine_migrations,messenger}.yaml`: mapping, migrations path, and routing `GeocodeClinicListing` → `async_external`
    - `config/services.yaml`: port aliases, geocoder binding
    - VetApp « Annuaire »: `src/Presentation/Clinic/Controller/Settings/{ShowDirectoryListingSettingsController, UpdateDirectoryListingSettingsController}.php`, `SettingsPageBuilder` section key `annuaire`, guard `ClinicPermissionGuard::MANAGE_STAFF`
    - `tests/Architecture/PortalBoundaryTest.php`: add `src/Context/ClinicListing` to the scanned namespaces (no `use` of Clinic or Scheduling Domain or Infrastructure)
  - Action:
    - Projection columns: `clinic_id` PK, `name`, `address_line`, `postal_code`, `city`, `phone`, `lat` / `lng` DECIMAL nullable, `address_hash`, `timezone`, `opening_hours` JSON, `active`, `refreshed_at`. Indexes `(active, city)`, `(lat)`, `(lng)`.
    - Listing filter = `active` and `listed`.
    - `RefreshClinicListing` re-reads `GetClinic` and dispatches `GeocodeClinicListing` after commit only when `address_hash` changed.
    - Search rules as in S7c. `onlineBooking` is computed at read time in a batch. `nextAvailableAt` is null in v1.
  - Tests:
    - unit: domain and handlers;
    - `tests/Integration/Context/ClinicListing/…/{DbalClinicListingRepositoryTest, SearchClinicListingsTest}.php` (LIKE escaping, bounding box, cursor mismatch);
    - consumer pipeline test;
    - console command test;
    - VetApp settings test.
- [ ] Task 48: New-client booking (S7d)
  - Files:
    - `src/Context/ClientPortal/Domain/Booking/{BookingRequest, BookingRequestId, BookingRequestStatus (pending|confirmed|declined), DeclineReason (declined|expired|cancelled|account_deleted)}.php`
    - `Infrastructure/Persistence/…`
    - `migrations/ClientPortal/Version…php`: table `client_portal__booking_requests` with `id`, `user_id`, `clinic_id`, `listing_id`, `appointment_id` UNIQUE, `motif_label`, `start_at`, `new_pet_name`, `new_pet_species`, `requester_first_name`, `requester_last_name`, `requester_phone`, `status`, `decline_reason`, `client_id` nullable, `created_at`, `resolved_at`; index `(user_id, created_at, id)`, `(clinic_id, status)`
    - `Application/Command/{RequestListingBooking, ConfirmBookingRequest, DeclineBookingRequest, SyncBookingRequestFromAppointment}/…`
    - `Application/Query/{ListOwnerBookingRequests, ListClinicBookingRequests, GetBookingRequest}/…`
    - `Application/Port/{OnlineBookingInterface (+ requestNewClientSlot), PetRegistrationInterface, ClientRegistrationInterface, NotificationRequesterInterface (+ requestBookingDeclined)}.php` and their `CommandBus*` / `QueryBus*` adapters
    - `Infrastructure/Messaging/Consumer/{PendingAppointmentConfirmedConsumer, PendingAppointmentDeclinedConsumer, PendingAppointmentCancelledConsumer}.php`, consuming the Scheduling events created in slice 4a
    - `src/System/Notification/Domain/Message/MessagePurpose.php` (`PORTAL_BOOKING_DECLINED`: Kiveto-sent, label « Demande de RDV non confirmée », NON_CRITICAL)
    - `Infrastructure/Rendering/TemplateCatalog.php`, `templates/notification/sms/portal_booking_declined.txt.twig` (clinic name, local date and time, the Task 22 sentence)
    - Scheduling `BookOnlineAppointment` new-client mode (owner and animal null, always pending, manual lead time)
    - `src/Presentation/Clinic/Controller/Scheduling/Appointment/ConfirmAppointmentController.php`: refuses `source=portal` with a null owner, flash « À traiter dans Demandes Portal »
    - VetApp « Demandes Portal » type « Nouveau client »: `src/Presentation/Clinic/Controller/PortalRequest/{ConfirmBookingRequestController, DeclineBookingRequestController}.php` and the detail template (matches, animal choice, mismatch badges)
    - resources `{ListingBooking, BookingRequests}.php` + processor/provider, `View/BookingRequestView.php`
    - `src/Context/ClientPortal/Application/Command/DeletePortalAccount/…` (Task 37 extension)
    - `config/services.yaml`: `limiter.portal_listing_booking`
  - Action:
    - `RequestListingBooking` checks, in this order: listing active and listed (404), `already_linked`, `booking_request_limit` (count pending `FOR UPDATE` per user), limiter, availability. It then books through the port and inserts the request in the same command transaction.
    - The route is `extraProperties: ['idempotent' => true]`.
    - `ConfirmBookingRequest(requestId, clientChoice existing|new, ?clientId, animalChoice existing|new, ?animalId, actorUserId)`:
      - `FOR UPDATE` on the request; status must be `pending`;
      - client: existing (must be a non-archived match) or `CreateClient` without animals;
      - animal: existing of that client, or `CreateAnimal` via the S4g port;
      - link: confirmed or reactivated;
      - Scheduling `changeOwnerAndAnimal` + `confirm()`;
      - the user's other pending requests at that clinic get the same `client_id`;
      - all in one transaction.
    - The consumers are idempotent: an already resolved request is left untouched. On decline or cancel they send `PORTAL_BOOKING_DECLINED` to `requester_phone` with idempotency key `portal-booking-declined:<requestId>`. On `account_deleted` nothing is sent.
    - `DeletePortalAccount`:
      1. declines pending requests;
      2. cancels their appointments through the port;
      3. nulls `requester_*` and `new_pet_*` on every request of the account.
  - Tests:
    - unit: aggregate and handlers;
    - `tests/Integration/Context/ClientPortal/Pipeline/BookingRequestPipelineTest.php` (confirm, decline, expiry, clinic cancel, duplicate delivery);
    - `tests/Integration/Presentation/Api/Portal/ListingBookingTest.php`;
    - `tests/Integration/Presentation/Clinic/PortalRequest/BookingRequestTest.php`;
    - `tests/Integration/Presentation/Clinic/Scheduling/ConfirmPortalNewClientRefusedTest.php`;
    - Notification `TemplateCatalog` test.
- [ ] Task 49: Public listing API (slice 7 routes)
  - Files:
    - resources `{ClinicListings, ClinicListingDetail, ListingMotifs, ListingPractitioners, ListingAvailability}.php` + providers
    - `config/packages/security.yaml`: an explicit `IS_AUTHENTICATED_FULLY` rule for `^/api/portal/v1/clinic-listings/[^/]+/booking/appointments$` placed **before** the public rule, then a public rule `^/api/portal/v1/clinic-listings` restricted to `methods: [GET]`
    - `config/services.yaml`: `limiter.portal_public_listing`, per IP, 120/min
  - Action:
    - Listing ids are ClinicListing ids (the clinic id is never exposed).
    - Only motifs with `newClientsAllowed`. Availability always uses `minLeadTimeManualHours`.
    - Unlisted or inactive → 404.
    - Errors through `PortalProblemMap`.
  - Tests: `tests/Integration/Presentation/Api/Portal/{ClinicListingsTest, ListingBookingSecurityTest}.php` (booking POST without token → 401, with token → 201; GET without token → 200).
- [ ] Task 50: Fixtures for slice 7
  - Files: `fixtures/Context/ClinicListing/Story/ClinicListingStory.php` (coordinates seeded directly, no network), `fixtures/Context/ClientPortal/Story/PortalOrganicArrivalStory.php`, `fixtures/Dataset/PortalDataset.php`, `fixtures/DevDataset.php` (runs the rebuild with the fake geocoder)
  - Action:
    - listings: 3 listed clinics (one without online booking, one not accepting new clients); one unlisted; one suspended that was listed; one without coordinates; one with more than 4 tags (« +N »);
    - suggestions: a PORTAL user with 2 suggestions (one grouping two family files); a pending « Rattachement » request with a name mismatch; a rejected suggestion;
    - booking requests: pending, confirmed, declined, and one auto-expired;
    - all dates relative to the fixture clock.
- [ ] Task 51: Final contract export and docs
  - Files: `openapi/portal.v1.yaml`, BC READMEs (IdentityAccess, Client, ClientPortal, Clinic, ClinicListing, Scheduling, Notification), `CLAUDE.md` (ClinicListing in the BC list)
  - Action: `make openapi-export`, `make ci`.

### Acceptance Criteria

ACs are tagged `[slice]`; the branch of each slice is the one in the task table.

**Contract `[C]`**
- [ ] AC 1 [C]: Given the updated `openapi/portal-api-v1.md`, when checked against each bullet of Task 1 (one checklist line per bullet, ticked in the PR description), then each bullet is reflected, the « Décisions ouvertes » list only « Push », `/me/devices*` and `/me/notification-preferences` are absent from the endpoint tables and the screen map, no `client_portal__*` projection and no `Context/Document` remain, and every route in the screen map is detailed in the tables and vice versa.

**Slice 2 — documents `[2]`**
- [ ] AC 2 [2]: Given a user without a confirmed link to clinic C, when they call any `/clinics/C/…` route, then the answer is 404 `not_found` (never 403), and the same holds for a document of C belonging to another client of C.
- [ ] AC 3 [2]: Given a malformed `cursor`, or a well-formed cursor with missing keys or a non-date `issued_at`, when listing documents, then the answer is 400 `cursor_invalid` in problem+json; `limit` above 50 is clamped to 50.
- [ ] AC 4 [2]: Given the Portal problem map, then `not_found` and `cursor_invalid` are produced with the same problem+json shape and French titles as the slice-1 codes.
- [ ] AC 5 [2]: Given a B2C invoice issued to a client with a confirmed link, when `InvoiceIssuedForPortalIntegrationEvent` is consumed, then a registry row `invoice` (title « Facture {number} », `patient_id` and `pet_ids` resolved through the admission) is created, becomes `available` once `EnsureSentInvoiceFile` stored the file, and is listed with `invoice.total` and `invoice.paymentStatus: unpaid`.
- [ ] AC 6 [2]: Given a counter-sale invoice without payer client, or a prescription without `ownerClientId`, when issued, then nothing is published.
- [ ] AC 7 [2]: Given a published invoice, when it is partially then fully paid, then the list shows `partially_paid` then `paid` (live status, no registry write); when credited it shows `credited`; when cancelled it disappears from the list, the file route and the unread count.
- [ ] AC 8 [2]: Given an issued prescription of a linked client, when consumed, then it is published with `petIds = [patientAnimalId]` and `consultation_id`, rendered to `client_portal/documents/<id>-v1.pdf`, and listed with `prescription { id, validUntil, renewable, renewalStatus: null }`; when cancelled it disappears and its detail and file routes answer 404.
- [ ] AC 9 [2]: Given a fake renderer asserting `!connection->isTransactionActive()`, when invoices and prescriptions are issued and published, then no render happens inside the issuing command and the render failure of a document leaves it `pending_file` and unlisted.
- [ ] AC 10 [2]: Given a CLOSED consultation whose patient is linked to an animal with a primary owner, when the vet clicks « Partager sur le Portal », then a `report` row (title « Compte rendu du {date} », `issued_at` = close time) is published for that owner and the toast « Compte rendu partagé sur le Portal. » appears; given an OPEN consultation, an unlinked patient or an animal without primary owner, the button is disabled with the matching French reason and the endpoint refuses with the same message.
- [ ] AC 11 [2]: Given a report shared then amended by an addendum, when it is shared again, then v1 keeps being served until `-v2.pdf` is stored; then the row serves v2, the document is unread again for every owner, `-v1.pdf` still exists after the swap, and a `DeleteStoredDocumentFile` message with a `DelayStamp` ≥ 600 000 ms sits on the `async` transport.
- [ ] AC 12 [2]: Given an invoice published while the patient was not linked to an animal (`petIds: []`), when `PatientLinkedToAnimalIntegrationEvent` is consumed, then its `petIds` is filled through `patient_id`.
- [ ] AC 13 [2]: Given `GET /clinics/C/documents?type=prescription&petId=P`, then only listable prescriptions concerning P are returned, newest first, paginated (`{items, nextCursor}`, 20 by default); a document with no pet is excluded by a `petId` filter and included without it.
- [ ] AC 14 [2]: Given an unread document, when `GET /clinics/C/documents/{id}/file` is called, then the answer is 302 (with `Cache-Control: private, no-store`) to a URL signed over `{c, id, v}`, the document becomes read for that user only, and `GET /me` `unreadDocuments` decreases by one for that clinic.
- [ ] AC 15 [2]: Given a signed download URL, when it is used within 5 minutes (time moved with `mockTime()`), then the PDF is served with `Cache-Control: private, no-store`; after 5 minutes, with a tampered signature, or with another clinic id in the path, then 404 `not_found`; the route needs no Bearer token.
- [ ] AC 16 [2]: Given `GET /clinics/C/prescriptions/{id}` for a listed prescription, then `{ id, issuedAt, vet, pet, lines[{ label, quantity, posologySummary }] }`; for an unpublished, withdrawn or other client's prescription, 404.
- [ ] AC 17 [2]: Given the same issued event delivered twice (Messenger retry), then a single registry row exists (unique `(clinic_id, type, source_ref)`).
- [ ] AC 93 [2]: Given a failed render during a re-share (renderer throws until the retries and 5 reconcile attempts are exhausted), then v1 keeps being served and is never deleted, and the row ends `failed` with an error log.
- [ ] AC 94 [2]: Given the cancel event of an invoice consumed **before** its issued event, then a `withdrawn` tombstone exists and the late issued event publishes nothing; given a withdraw while the render is in flight, then the late `AttachPublishedDocumentFile` leaves the row `withdrawn`; given renders of v1 and v2 completing out of order, then the row ends on v2.
- [ ] AC 95 [2]: Given `DeleteStoredDocumentFile` with a key outside `client_portal/` (e.g. `billing/sent/invoice/…`), then it refuses and deletes nothing.
- [ ] AC 96 [2]: Given an unreconciled patient reconciled into an animal's patient (`PatientReconciledIntoIntegrationEvent` only), then the registry rows are repointed to the target patient and their `petIds` filled.
- [ ] AC 97 [2]: Given a user with a confirmed link to client A and a revoked link to client B in the same clinic, then B's documents never appear in the list, the file route, the prescription detail or the unread count.
- [ ] AC 98 [2]: Given an invoice paid by client B for client A's animal, then B (linked) sees the invoice in list, file route and unread count, and A (linked, not payer) sees it nowhere — neither in the documents list, nor the unread count, nor the file route.
- [ ] AC 99 [2]: Given a published invoice whose live status is CANCELLED before the withdraw is consumed, then the page is returned without that row (warning logged), never a 500.
- [ ] AC 100 [2]: Given a client without confirmed link, when its invoice and prescription are issued, then the rows are `awaiting_link` and no render happens; when a link is confirmed, they render and become `available`.
- [ ] AC 101 [2]: Given a row stuck in `pending_file` for 11 minutes, when `ReconcilePublishedDocuments` runs, then it re-dispatches its render once (a second run within the same window does not dispatch twice); a row stuck for 5 minutes is untouched; after the 5th failure the row is `failed`, an error is logged and it is no longer retried.
- [ ] AC 102 [2]: Given the import command run at go-live, then invoices and prescriptions issued within the last 24 months are published (older ones ignored) and no report is imported; running it twice creates no duplicate.
- [ ] AC 103 [2]: Given a re-share URL signed for v1 before the swap, when it is used within 5 minutes after the swap, then it still serves v1; given the document withdrawn after signing, then 404.
- [ ] AC 104 [2]: Given an owner without Portal account (no confirmed link), when the vet shares the report, then the share succeeds, the row is `awaiting_link`, and the toast says « Partagé — visible dès que le client activera son espace Portal »; two concurrent clicks produce a single version increment.
- [ ] AC 105 [2]: Given the demo dataset, then every fixture document listed in Task 10 downloads a real PDF.

**Slice 3 — pets `[3]`**
- [ ] AC 18 [3]: Given a linked owner, when `GET /clinics/C/pets`, then only the ACTIVE animals whose active primary owner is a confirmed-linked client and whose `transferStatus` is `none` are returned, with `sterilization` mapped from `reproductiveStatus`, `lifeStatus`, `breed` and `initial` per the S3i mapping; secondary-owner, archived, sold and given animals are absent, and `GET /clinics/C/pets/{id}` for any of them answers 404.
- [ ] AC 19 [3]: Given a deceased pet and a missing pet, then both appear in the list and detail with their `lifeStatus`, but never in `…/care-items`, home `pets[]`, home `careItems[]` or `nextAppointment`.
- [ ] AC 20 [3]: Given a pet with a weight record of 1 March, a CLOSED consultation weight of 10 March (12,4 kg) and an OPEN consultation weight of 15 March, when `GET /clinics/C/pets/{id}`, then `lastWeight` is `{ weightGrams: 12400, at: "<year>-03-10" }`; given a weight record and a closed-consultation weight on the same local date, the weight record wins; `chipNumberMasked` shows only the last 4 characters (null without chip).
- [ ] AC 21 [3]: Given care records with `nextDueOn` (a) yesterday, (b) in 20 days, (c) in 60 days, then statuses are `overdue`, `due_soon`, `up_to_date`, ordered by `dueOn` ascending; slice 3 never returns `booked`, even when an appointment of that pet exists; a protocol whose latest act has no `nextDueOn` is absent even if an older act of it has one; for the same kind and label (case-insensitive) only the latest act counts.
- [ ] AC 22 [3]: Given a clinic in `Pacific/Noumea`, when « today » is computed for care statuses at 23:30 UTC, then the clinic's local date (next day) is used; given a clinic in `America/Martinique`, then a `dueOn` of `2026-11-05` is emitted as `"2026-11-05"` (no timezone shift).
- [ ] AC 23 [3]: Given a pet with 3 closed consultations of which 2 have a shared report, when `GET …/timeline`, then 2 items are returned ordered by close time descending, each with `date` (consultation start, clinic offset), `vet` (name snapshotted at share time), `title` and `documents[]` (report and the prescriptions of that consultation, plus its invoice only if the caller's client is the payer); the third consultation never appears. `title` follows `VisitTitle::of`: motifs « Vaccination », « Bilan annuel » → « Vaccination et bilan annuel »; no motif and complaint « Toux. » → « Toux »; neither → « Consultation ».
- [ ] AC 24 [3]: Given `GET /clinics/C/home`, then `nextAppointment` is the earliest upcoming PLANNED appointment of an alive visible pet (`{ id, startAt, pet { id, name }, practitioner, motifLabel }`, `motifLabel` a fixed French label, never the free-text reason), `pets[]` lists alive visible pets, `careItems[]` the 3 smallest `dueOn` across alive pets each with `pet { id, name }`, `latestDocuments[]` at most 2 listable documents under the slice-2 rules; empty sections are empty arrays and `nextAppointment` is null when none.
- [ ] AC 25 [3]: Given `GET /clinics/C/pets/{id}/vaccination-booklet`, then 302 (`Cache-Control: private, no-store`) to a signed URL serving a PDF listing the vaccinations with identity, chip and clinic header; a second call with unchanged inputs reuses the cached file without rendering; after a new vaccination record, a renamed animal, a changed chip number or a bumped `TEMPLATE_VERSION`, a new file is rendered under a new key and the previous key's deletion is scheduled with a delay ≥ 10 minutes.
- [ ] AC 26 [3]: Given another client's pet id, an archived pet or a sold pet, then the booklet route answers 404 and no render happens; given a deceased pet, the booklet is still served.
- [ ] AC 27 [3]: Given `GET /me`, then each `clinics[]` entry has `timezone` (IANA); instants of slice-3 routes (`timeline.date`, `nextAppointment.startAt`) carry the clinic offset, and date-only fields (`birthDate`, `dueOn`, `lastDoneOn`, `lastWeight.at`) are `YYYY-MM-DD`.
- [ ] AC 28 [3]: (removed — `AnimalSheetBuilder::preventionRows` is not migrated in slice 3; the VetApp copies of the latest-act rule are explicitly deferred, S3d)
- [ ] AC 110 [3]: Given a pet re-homed from client A to client B (primary ownership replaced), when B calls `…/timeline` and `GET …/pets/{id}`, then only the reports addressed to B are listed and `referentVet` derives from them (null if none); A gets 404 on the pet and no longer sees it in `/pets`.
- [ ] AC 111 [3]: Given an owner linked to two client records of clinic C, each primary owner of one pet, then `/pets`, the home and the care items list both pets; a pet of a third, unlinked client of C answers 404.
- [ ] AC 112 [3]: Given a report shared by a vet who later left the clinic, then the timeline item and `referentVet` still show that vet's name.
- [ ] AC 113 [3]: Given an upcoming appointment of a secondary-owner pet booked under the caller's client, or an appointment without animal, then the home's `nextAppointment` never returns it.
- [ ] AC 114 [3]: Given a malformed or wrong-shape timeline cursor, then 400 `cursor_invalid`; `limit` above 50 is clamped to 50; with more than 20 published visits, `nextCursor` paginates without duplicates or gaps.
- [ ] AC 115 [3]: Given a pet with no vaccination record, then the booklet is a PDF containing « Aucune vaccination enregistrée ».
- [ ] AC 116 [3]: Given a booklet signed URL, when used after 5 minutes, with a tampered signature or with another clinic id in the path, then 404 `not_found`; the route needs no Bearer token.
- [ ] AC 117 [3]: Given the VetApp animal sheet, client sheet and dossier after Tasks 12–13, then visit titles and agenda motif chips are unchanged on the existing fixtures (regression).
- [ ] AC 118 [3]: Given the demo dataset, then every state listed in Task 17 exists and every published visit downloads a real PDF.

**Slice 4a — booking setup `[4a]`**
- [ ] AC 29 [4a]: Given `AppointmentStatus`, then `occupiesSlot()` is true exactly for PLANNED and PENDING_CLINIC_CONFIRMATION, `OCCUPYING_SQL_VALUES` lists the same values (a unit test ties both), and `isTerminal()` is true for CANCELLED, NO_SHOW, COMPLETED and DECLINED.
- [ ] AC 30 [4a]: Given a pending appointment, when a VetApp user books the same practitioner over the same time, then the overlap check refuses it; the pending appointment also counts in block capacity.
- [ ] AC 31 [4a]: Given a pending appointment and a declined one, then the agenda (range JSON + `agenda.js` card), the dashboard, the flux board, the shell « RDV aujourd'hui » count, the client sheet and the animal sheet show the pending one as « à confirmer » and busy, and the declined one as cancelled and not counted (one test per screen).
- [ ] AC 32 [4a]: Given two concurrent `ScheduleAppointment` (one VetApp, one online-like) for the same practitioner and overlapping times, run outside DAMA on two connections where **each transaction first runs a plain SELECT on `scheduling__appointments`** (REPEATABLE READ snapshot), then exactly one succeeds and the other gets the overlap error.
- [ ] AC 33 [4a]: Given a clinic in `Europe/Paris` and an appointment at 00:30 local on 12 March, then the lock row's `local_date` is 12 March; given an appointment 23:30–00:30 on 11–12 March, then both 11 and 12 March are locked, and a concurrent 00:00–00:20 booking on 12 March for the same practitioner is refused.
- [ ] AC 34 [4a]: Given two concurrent reschedules swapping practitioner A day 1 ↔ practitioner B day 2, then both succeed, or one gets the overlap error after at most two deadlock retries; no deadlock exception reaches the caller.
- [ ] AC 35 [4a]: Given a pending appointment, while it is pending then no SMS or email, no reminder and no `NEW_APPOINTMENT` bell is sent; when the clinic confirms it, then it becomes PLANNED, the CONFIRMATION message is sent once, and the `ONLINE_BOOKING_PENDING` bell entry is withdrawn.
- [ ] AC 36 [4a]: Given a pending appointment with an owner, when the clinic declines it (button or clinic cancel), then it becomes DECLINED, frees the slot, and the owner receives `APPOINTMENT_DECLINED` (« … choisissez un autre créneau dans l'app Kiveto. ») exactly once even when two syncs run.
- [ ] AC 37 [4a]: Given a pending appointment created 49 h ago (or starting in 1 h 50), when the 15-minute scan runs, then a `DeclineExpiredPendingAppointment` message is queued for it and it is declined with reason `expired` and the same message; a pending appointment created 1 h ago starting in 3 days is untouched; one failing decline does not prevent the others.
- [ ] AC 38 [4a]: Given the VetApp settings section « Réservation en ligne », when an admin creates a motif (30 min, consultation blocks, practitioners A and B, manual for known clients) and changes grid step, lead times, horizon and cancellation window, then the values persist and `GetOnlineBookingSettings` returns them; defaults are 15 min, 120 min, 24 h manual, 60 days, 24 h window, disabled; out-of-bounds values (e.g. `minLeadTimeManualHours = 2`) are refused with a French message; the motif form has no new-client confirmation setting.
- [ ] AC 39 [4a]: Given a pending portal appointment, then the practitioner receives an `ONLINE_BOOKING_PENDING` bell entry (key `online-booking-pending:<id>`), the dashboard source « RDV en ligne à confirmer » lists it with Confirm / Decline (4 shown + « N autres » beyond), and the entry disappears on confirm, decline or cancel.
- [ ] AC 87 [4a]: Given `OnlineBookingSettings::pendingExpiry(createdAt, startsAt)` with `startsAt ≥ createdAt + minLeadTimeManualHours` (≥ 3 h), then the result is always later than `createdAt` (unit test over the bounds); the availability half of the former AC 87 moves to AC 147 [4b].
- [ ] AC 89 [4a]: Given `tests/Architecture/AppointmentStatusLiteralTest.php`, then it fails if any file outside `src/Context/Scheduling` contains an appointment status literal (`'PLANNED'`, `'PENDING_CLINIC_CONFIRMATION'`, `'DECLINED'`); Presentation reads `occupiesSlot` / `isPending` / `isWithdrawn` from Scheduling DTOs.
- [ ] AC 140 [4a]: Given a pending appointment, when it is checked in at the desk (or a consultation is started from it), then it becomes PLANNED then follows its normal flow, **no confirmation SMS or email is sent**, and `PendingAppointmentConfirmedIntegrationEvent` is published when `source = portal`.
- [ ] AC 141 [4a]: Given a pending appointment, when « Non venu » is attempted, then it is refused (`AppointmentNotModifiableException`, French flash), and the action is hidden on the agenda card.
- [ ] AC 142 [4a]: Given a pending appointment rescheduled by the clinic to a later day, then `pending_expires_at` is recomputed (`min(createdAt + 48 h, newStart − 2 h)`).
- [ ] AC 143 [4a]: Given the migration on a database with existing CANCELLED appointments, then every one has `cancelled_by = 'clinic'`, every appointment has `source = 'clinic'`, and a pending appointment persists and reloads with all new fields (`status` column ≥ 27 characters).
- [ ] AC 144 [4a]: Given a confirm and an auto-decline of the same pending appointment running concurrently, then exactly one wins; a confirm after `pending_expires_at` is refused with « Demande expirée. »; the losing VetApp action shows a flash, never a 500.
- [ ] AC 145 [4a]: Given a staff role without `manage_appointments`, when it posts Confirm or Decline, then it is refused; a role holding it succeeds.
- [ ] AC 146 [4a]: Given portal-sourced pending appointments, when they are confirmed, declined (button, clinic cancel, expiry) or cancelled by the client, then exactly one of `PendingAppointmentConfirmed|Declined|CancelledIntegrationEvent` is published per transition with `{appointmentId, clinicId, ownerId, animalId, startsAtUtc, reason?}`; a clinic-sourced appointment publishes none.
- [ ] AC 148 [4a]: Given a VetApp reschedule into a block of a refusing type or a full block, then it is refused like a new booking would be; with no block at all it is allowed (SOFT rule).
- [ ] AC 149 [4a]: Given a planning block deleted while an online booking for that practitioner and day is in flight (two connections), then the booking either sees the block or fails cleanly — it never commits into a deleted block (block mutations take the practitioner-day lock).

**Slice 4b — online booking `[4b]`**
- [ ] AC 40 [4b]: Given a practitioner with a consultation block Mon 09:00–12:00 (capacity 0 = unlimited), opening hours 08:30–12:30, a 30-min motif, grid 15 min, lead time 2 h, then availability for that Monday lists 09:00 … 11:30 every 15 min, minus slots overlapping occupying appointments and those before now + 2 h; a surgery block (type not allowed by the motif) yields nothing; a block full per `BlockCapacity` yields nothing.
- [ ] AC 41 [4b]: Given an exceptional closure, then that day is `open: false` with no slots; given a practitioner without any block that day, then no slot (HARD rule) while VetApp still accepts a manual booking outside blocks (SOFT rule); given a clinic with an empty opening week, then every day is `open: false` and the VetApp settings page shows « Horaires d'ouverture requis pour la réservation en ligne ».
- [ ] AC 42 [4b]: Given a clinic in `Europe/Paris`, when availability is requested across the last Sunday of March and of October with a block crossing 02:00, then slots are generated in local time, emitted with the right offset (`+01:00`/`+02:00`), no slot falls in the non-existent hour, the repeated hour yields no duplicate instant, and the block's capacity uses its local duration; given `America/Martinique`, the offsets are constant.
- [ ] AC 43 [4b]: Given `from/to` spanning 15 days, `from` before the clinic's local today or `from > to`, then 422 `validation_failed`; days beyond `horizonDays` are `open: false`; unknown or archived motif, or a practitioner not bookable for it → 404 `not_found`; online booking disabled or clinic not active → 422 `booking_not_open` on motifs, practitioners and availability.
- [ ] AC 44 [4b]: Given a known client and an auto-confirm motif, when `POST /clinics/C/appointments` with a listed slot, then 201 with the full appointment, `status: confirmed`, `source: portal`, `motif {id, label}`, agenda `reason` = motif label; with a manual motif, `status: pending_clinic_confirmation`, no message sent, and only slots from now + `minLeadTimeManualHours` are offered.
- [ ] AC 45 [4b]: Given two owners booking the same slot concurrently, then one gets 201 and the other 409 `slot_taken`; replaying the winning request with its `Idempotency-Key` returns the same 201 without a second appointment; replaying the losing one runs the booking again (409 while the slot is taken).
- [ ] AC 46 [4b]: Given `POST /clinics/C/appointments/with-new-pet {newPet: {name, species}}`, then an Animal is created through `CreateAnimal` with the linked client as primary owner and the defaults of S4g (`sex`/`reproductiveStatus` `unknown`, `registryType` `none`, `lifeStatus` `alive`, `transferStatus` `none`) and booked in the same transaction; if booking fails with `slot_taken`, no animal remains; `species` outside `dog|cat|nac|other` → 422.
- [ ] AC 47 [4b]: Given a deceased or missing pet, when booking for it, then 422 `pet_not_bookable`; given another client's pet id (same clinic) or a sold/given pet, then 404 `not_found`; given online booking disabled or a non-online motif, then 422 `booking_not_open`.
- [ ] AC 48 [4b]: Given an appointment created 3 days ago starting in 30 h and a 24 h window, when the owner cancels with `reason: unavailable`, then 200 with the full appointment `status: cancelled_by_client`, the slot is freed, the usual cancellation message is sent and the practitioner gets an `ONLINE_BOOKING_CHANGED` bell entry; given one starting in 20 h created 3 days ago, cancel and reschedule answer 422 `cancellation_window_closed`; at exactly `now = cancellableUntil` it is still allowed.
- [ ] AC 49 [4b]: Given a portal appointment rescheduled to another practitioner of the motif, then availability is rechecked under both lock sets (the moved appointment excluded from overlap and capacity) and the appointment moves keeping its status; to a taken slot → 409 `slot_taken`; to a practitioner not bookable for the motif → 404 `not_found`; a clinic-created appointment (no motif) → 422 `booking_not_open`.
- [ ] AC 50 [4b]: Given `GET /clinics/C/appointments?scope=upcoming`, then occupying future appointments of the linked clients' primary pets, ascending by `(startsAt, id)`, with `cancellableUntil`; `scope=past` returns the others descending; `limit` is clamped to 50; no response contains the appointment's `notes` or raw `reason` (an appointment without motif shows `motif.label` « Rendez-vous »).
- [ ] AC 51 [4b]: Given a pet with an overdue vaccination and another with a vaccination due in 20 days, when `GET /clinics/C/booking/motifs?petId=P`, then the motif with `preventiveCareKind: vaccination` is `suggested: true` with the matching French `suggestionReason` in both cases; `GET …/appointments/{id}` returns `relatedCareItem {kind, label, dueOn, status}` for such a motif and null otherwise.
- [ ] AC 52 [4b]: Given `GET …/appointments/{id}/calendar.ics`, then a `text/calendar` with `METHOD:PUBLISH`, no ATTENDEE, the right instant (UTC `DTSTART`), the clinic name and address, the same UID as the e-mailed invitation, and `STATUS:TENTATIVE` for a pending appointment; another client's, withdrawn or terminal appointment → 404.
- [ ] AC 147 [4b]: Given a motif in manual mode for known clients, `minLeadTime` 2 h and `minLeadTimeManualHours` 24 h, then availability offers no slot before now + 24 h, and a booked pending appointment has `pending_expires_at` later than its creation (no decline at creation).
- [ ] AC 150 [4b]: Given a weekly consultation block of practitioner V and a one-off LEAVE block of V on one occurrence day (different anchor date), then availability offers no slot during the leave, and a direct `POST` for such a slot answers 409 `slot_taken`.
- [ ] AC 151 [4b]: Given a booking whose 201 was committed but whose idempotency record was not written (simulated failure), when the app retries with the same key, then the existing appointment is returned as 201 and no second appointment exists; given a with-new-pet retry with a new key within 24 h, then the existing animal is reused.
- [ ] AC 152 [4b]: Given an appointment already `cancelled_by_client`, when cancel is called again, then 200 with the same body; given a DECLINED, COMPLETED, NO_SHOW or clinic-cancelled appointment, cancel or reschedule → 422 `appointment_not_modifiable`.
- [ ] AC 153 [4b]: Given an appointment booked 2 h before its start with a 24 h window, then it can be cancelled online during the 15 minutes after its creation (`cancellableUntil = createdAt + 15 min`), and not after.
- [ ] AC 154 [4b]: Given a with-new-pet pending booking declined or expired, when the created animal has no other appointment and no patient link, then it is archived; when it already has a patient link or another appointment, it is kept.
- [ ] AC 155 [4b]: Given a care item due in 10 days and an occupying appointment of that pet in 5 days whose motif carries the item's `preventiveCareKind`, then the item is `booked`; with an overdue item and such an appointment next week → `booked`; with an appointment whose motif has another kind or no motif → not booked; the comparison uses the clinic-local date (Noumea boundary case).
- [ ] AC 156 [4b]: Given opening slots 08:00–12:30 and 12:30–18:00 (adjacent), then a 30-min slot 12:15–12:45 is offered (slots merged); given a clinic suspended, then 422 `booking_not_open`.

**Slice 5 — consents and account `[5]`**
- [ ] AC 53 [5]: Given the backfill migration, then each existing consent channel has one `granted` history row (`source: system`, author « Reprise », `approximate = true`, `at` = `grantedAt`), a multi-channel consent gives one row per channel, and a NULL preferences column gives none.
- [ ] AC 54 [5]: Given `PUT /clinics/C/consents/marketing {channels: [sms]}` on a client consenting `[sms, email]`, then the consent keeps `sms`, one `withdrawn` row (`email`, `source: portal`, `actor_type: portal_user`, author = owner name) is appended, and the other kinds are untouched.
- [ ] AC 55 [5]: Given a VetApp edit of the consents at the counter, then the history rows carry `source: clinic` and the staff member as actor; `GET /clinics/C/consents/history` lists both sources newest first, paginated, with staff rows shown as « La clinique ».
- [ ] AC 56 [5]: Given `channels: [phone]`, then 422 `validation_failed`.
- [ ] AC 57 [5]: Given a client with active consents `marketing`, `appointment_reminders`, `vaccination_reminders`, when the client is archived, then no consent is withdrawn and no history row is written, and `ClientNotificationContact::channelsFor()` returns `[]` for those three kinds while `billing` still returns its channel.
- [ ] AC 58 [5]: Given an archived client, when anyone tries to add a consent (VetApp form or `SetClientConsent`), then it is refused (`ClientArchivedCannotBeModifiedException`).
- [ ] AC 59 [5] (removed — no consent is withdrawn on link revocation; see AC 57 and AC 200–201).
- [ ] AC 60 [5]: Given `PATCH /me {firstName, address}`, then the `OwnerProfile` changes and the clinic `Client` records do not; an empty or 61-character `firstName` → 422 `validation_failed`.
- [ ] AC 61 [5]: Given an authenticated user, when `POST /me/email-change/otp`, then an SMS code (purpose `email_change`, wording « confirmation de changement d'e-mail ») is sent and consumes `portal_otp_phone` / `portal_otp_phone_daily` / `portal_otp_ip`; `POST /me/email-change {challengeId, code, email}` with a valid code sends a code to the new email and returns an `email_verification` `challengeId`; `POST /me/email-change/verify {challengeId, code}` sets the email with `emailVerifiedAt` and the previous verified address receives a notice.
- [ ] AC 62 [5]: Given a user with a verified email, then `POST /auth/otp/{id}/resend {channel: email}` on a `login` challenge sends the code to that email; for a phone without verified email it answers 202, sends nothing, and the previous SMS code still verifies.
- [ ] AC 63 [5]: Given a user (with or without email), when `POST /me/data-export`, then 202; asynchronously a ZIP with the canonical content (`schemaVersion: 1`) is built and `GET /me/data-exports` shows it `ready`; `GET /me/data-exports/{id}/file` → 302 to a 5-minute signed URL; a verified email only receives a notice without link; a second request within 24 h → 429 `export_rate_limited`.
- [ ] AC 64 [5]: Given `POST /me/deletion-request` without a valid `account_deletion` code (wrong code, login-purpose code, another user's code), then it is refused (422 `otp_invalid`) and nothing changes.
- [ ] AC 65 [5]: Given a valid deletion code, then right after the 202 the access token is refused (401) and refresh answers 401; after the async job every `ClientLink`, pending invitation, onboarding grant and open challenge of the phone is revoked, `OwnerProfile` reads « Compte supprimé » and `User` has NULL phone and email with `anonymisedAt`; clinic `Client` records and their consents are unchanged; a new invitation sent to that phone creates a new account.
- [ ] AC 91 [5]: Given a ready export, when its signed URL is used after 5 minutes, or the file route is called after 48 h, then 404 `not_found`; after 48 h the ZIP no longer exists in storage (delayed message, or the nightly sweep if the message was lost); within 5 minutes the signed URL serves it with `Cache-Control: private, no-store`.
- [ ] AC 92 [5]: Given a user linked to two client records in clinic C, when `PUT /clinics/C/consents/marketing {channels: []}`, then the consent is withdrawn on both records in one transaction with one history row per record, and `GET /clinics/C/consents` returns the union of channels per kind across both records.
- [ ] AC 200 [5]: Given an archived client with active `vaccination_reminders` and `marketing` consents, when any sender asks its contact, then neither a vaccination reminder nor a marketing message can be addressed (`channelsFor()` → `[]`), and an SMS appointment reminder due for that client is not sent.
- [ ] AC 201 [5]: Given an archived client with consents, when the client is unarchived, then `GET /clinics/C/consents` (and the VetApp sheet) shows the same consents and history as before archiving, without anything re-entered, and optional messages are addressable again.
- [ ] AC 202 [5]: Given a Portal withdrawal of `marketing` on a client, when a VetApp user saves the consent form rendered before that withdrawal, then the save is refused with « Les consentements ont changé depuis l'ouverture de la fiche » and `marketing` stays withdrawn.
- [ ] AC 203 [5]: Given a client created at the desk with consents, then one `granted` history row per channel exists with `source: clinic` and the staff actor.
- [ ] AC 204 [5]: Given `GET /clinics/C/consents`, then `marketing`, `appointment_reminders`, `vaccination_reminders` have `effect: send_control` and `billing`, `clinical_documents` have `effect: channel_preference`; `PUT …/consents/billing {channels: []}` → 422 `validation_failed`.
- [ ] AC 205 [5]: Given two client records of clinic C linked to the user, one archived (link revocation not yet processed), when the user changes a consent, then the active record is updated and the archived one is skipped without error.
- [ ] AC 206 [5]: Given an `email_change` challenge issued for user A, when user B submits its id and code, then 422 `otp_invalid`; an `email_verification` code wrong 5 times → `otp_attempts_exceeded`; after 10 minutes → `otp_expired`.
- [ ] AC 207 [5]: Given an address held **unverified** by another PORTAL user, when the caller verifies it, then the caller gets it and the other account's email becomes NULL; given an address held **verified** by another user, then 422 `email_taken` at `/verify` (never earlier).
- [ ] AC 208 [5]: Given an `account_deletion` or `email_change` challenge, when `resend {channel: email}` is called, then no code is ever sent by email (same 202, challenge untouched).
- [ ] AC 209 [5]: Given the build of an export fails, then the export is `failed` and a new request is accepted immediately (limiter refunded).
- [ ] AC 210 [5]: Given a user with zero links, then the export contains `account.json` and `summary.pdf` only.
- [ ] AC 211 [5]: Given the deletion job interrupted after revoking the links and retried, then it completes without error or duplicate effect; the user's idempotency keys, phone-keyed limiter rows, exports and phone-lock row are gone, and Kiveto-sent message recipients for that phone are masked.
- [ ] AC 212 [5]: Given an export requested, then account deletion removes it and its file; its file route answers 404.
- [ ] AC 213 [5]: Given the SMS of an `account_deletion` code, then its text names the deletion (« Code de confirmation de suppression de votre compte Kiveto »), not a login.

**Slice 6 — requests `[6]`**
- [ ] AC 66 [6]: Given an upcoming confirmed appointment of the caller's pet, when `PUT …/questionnaire` with `symptoms: [none, cough]`, then 422 `validation_failed` (`none` exclusive); with valid answers and `version: 1`, then 200 and `questionnaireStatus: submitted` in the appointment views; with `version: 2`, then 422 `validation_failed`.
- [ ] AC 67 [6]: Given `POST …/pets/{id}/change-requests {field: weight, proposedValue: "12,4"}`, then 202 `requestId`, the request stores `normalised_value.weightGrams = 12400` and `currentValueAtSubmit`, a « Demandes Portal » item shows « Valeur à la demande / Valeur actuelle / Valeur proposée », one `PORTAL_REQUEST` bell is posted to the `MANAGE_ANIMALS` holders with fixed labels only, and the animal is unchanged until staff edit it.
- [ ] AC 68 [6]: Given a prescription ISSUED and expired, without controlled substance, of an alive pet, when `POST …/renewal-requests`, then 202 and the documents list shows `renewalStatus: { status: submitted, requestId }`; given a controlled-substance or cancelled prescription, or a pet not alive, then 422 `prescription_not_renewable`; a second request while one is submitted returns 202 with the same `requestId`.
- [ ] AC 69 [6]: Given a submitted renewal, when a user with `CREATE_PRESCRIPTION` resolves it « Traiter » with a message and a linked prescription issued after the request, then status `done`, `resolvedByName` and `resultingPrescriptionId` are stored, and `renewalStatus` shows them.
- [ ] AC 70 [6]: Given a consultation started from an appointment with a submitted questionnaire, then the cockpit shows the answers with their `updatedAt` and a « peut mordre » chip when `temperament: may_bite`.
- [ ] AC 71 [6]: Given more than 4 open requests, then the dashboard shows 4 tasks and a fifth task « N autres demandes Portal » linking to the queue; questionnaires are never counted.
- [ ] AC 230 [6]: Given a resolved request, then the owner receives exactly one message (fixed French sentence + staff message when present) on the client's transactional channel with purpose `PORTAL_REQUEST_RESOLVED` (non-critical), even if the resolve endpoint is called twice; given a client without reachable channel, the request shows « client injoignable » and nothing is sent.
- [ ] AC 231 [6]: Given two concurrent resolutions of the same request (« Traiter » and « Refuser »), then exactly one succeeds and the other user gets the flash « Demande déjà traitée par {name} »; resolving a questionnaire is impossible.
- [ ] AC 232 [6]: Given an appointment cancelled, declined, or whose consultation started before `startsAt`, when `PUT …/questionnaire`, then 404 (cancelled/declined) or 422 `questionnaire_locked` (consultation started); after a reschedule of a locked questionnaire's appointment to a later time, it stays locked.
- [ ] AC 233 [6]: Given another client's pet, appointment or prescription id of the same clinic, when any slice-6 route is called, then 404 `not_found`; given a deceased, missing or archived pet, change requests answer 422 `pet_not_eligible`.
- [ ] AC 234 [6]: Given `GET /clinics/{c}/requests`, then the caller's change and renewal requests are returned with status, resolution message and `resultingPrescriptionId`, newest first, paginated; there is no cancellation route.
- [ ] AC 235 [6]: Given field validation, then `name` > 60 chars, weight outside 0.05–150 kg, `chip_number` not 15 digits, `sterilization` outside `neutered|intact`, `other` without `note`, or `note` > 500 chars answer 422 `validation_failed` with the matching `propertyPath`.
- [ ] AC 236 [6]: Given 10 requests in 24 h by one user in one clinic, or 3 open change requests on the same pet and field, then the next submit answers 429 `rate_limited`.
- [ ] AC 237 [6]: Given a renewal rejected less than 7 days ago, then a new request on that prescription answers 422 `prescription_not_renewable`; after 7 days it is accepted.
- [ ] AC 238 [6]: Given open requests, when the client is archived, the account deleted, the prescription cancelled or the pet archived/deceased, then the requests become `closed` with the matching reason, leave the queue and send nothing; after an account deletion their free-text fields are empty.
- [ ] AC 239 [6]: Given an owner note `<img src=x onerror=alert(1)>`, then it renders as inert text in « Demandes Portal », the agenda popover and the cockpit, and never appears in a bell title or detail.
- [ ] AC 240 [6]: Given a user without `MANAGE_ANIMALS`, then the change-request queue and resolution are refused; given a user without `CREATE_PRESCRIPTION`, renewal resolution is refused; another clinic's request id → 404.
- [ ] AC 241 [6]: Given the agenda of a day with appointments having questionnaires, then each card shows a questionnaire badge (one batch query for the range), and the popover loads the answers from `GET /clinic/agenda/appointments/{id}/questionnaire`.
- [ ] AC 242 [6]: Given a renewal with `petCondition: adverse_effects`, then its dashboard task is in the `urgent` group with audience `veto`.
- [ ] AC 243 [6]: Given two concurrent renewal submits with different `Idempotency-Key`s on the same prescription (outside DAMA, two connections), then a single submitted request exists and both answers carry its `requestId`.
- [ ] AC 244 [6]: Given `GET …/questionnaire` before any answer, then `{ version: 1, status: not_started }`; after submit, `status: submitted` with `answers` and `updatedAt`.

**Slice 7 — organic arrival `[7]`**
- [ ] AC 72 [7]: Given an unknown phone without invitation, when `otp/verify` succeeds, then `registration_required` returns a non-null `registrationToken`; `POST /auth/register` with it, names and current terms returns 201 with tokens and `account`; presenting the same token again within 15 minutes returns the same account with a new token pair; after expiry → 401 `onboarding_invalid`.
- [ ] AC 73 [7]: Given an invitation grant (`onboardingToken`), when used on `/auth/register`, then 401 `onboarding_invalid`; given a registration token used on `/invitations/{id}/accept`, then 401 `onboarding_invalid`; `revokeOnboardingGrantsForPhone` leaves a registration grant usable.
- [ ] AC 74 [7]: Given two concurrent `POST /auth/register` with tokens for the same phone (outside DAMA, two connections), then exactly one user and one profile are created and the loser answers 409 `account_exists`.
- [ ] AC 75 [7]: Given a registered user whose phone is the `mobile`-labelled number of non-archived clients in clinics A (two family files) and B, not linked, when `GET /me/link-suggestions`, then exactly two suggestions (one per clinic) with only `clinic { name, city }` and `matchedOn: phone`; a client where the number is labelled `home`, an archived client, an already linked clinic, and a suspended or closed clinic are excluded.
- [ ] AC 76 [7]: Given a suggestion accepted, then 202 `linkStatus: pending`, `GET /me` shows the clinic with `linkStatus: pending` and `linkedAt: null`, `/clinics/A/…` still answer 404, and the clinic sees one « Rattachement » request listing both family files; on confirmation the links become `confirmed` and `/clinics/A/…` opens; on rejection the links are revoked and the suggestion does not come back.
- [ ] AC 77 [7]: Given `GET /clinic-listings?q=lyon` without token, then only listed, active Kiveto clinics matching name or city are returned with `onKiveto: true`, `onlineBooking`, `tags[]`, `phone`; with `lat/lng` they carry `distanceM`, are ordered by distance, and a clinic without coordinates is absent from that result only; `q` of 1 character or `q=100%` (escaped) behave as specified.
- [ ] AC 78 [7]: Given a clinic renamed or its letterhead address changed in VetApp, then after the async consumer the listing shows the new name and re-geocoded coordinates; a change that leaves the normalised address unchanged triggers no geocoding call; a suspended clinic (or a suspended clinic group) makes the listing inactive (404 on its routes) and reactivation restores it with its settings.
- [ ] AC 79 [7]: Given a listed clinic with new-client booking enabled, when an authenticated user without link books `POST /clinic-listings/{l}/booking/appointments` with an `Idempotency-Key`, then 201 with the `BookingRequestView` (`status: pending`), the slot is held, no `ClientLink` is created, `GET /me/booking-requests` lists it, and a replay with the same key creates no second request.
- [ ] AC 80 [7]: Given the clinic confirms a booking request from « Demandes Portal » by matching an existing client (same mobile) or creating one, and choosing an existing animal or creating `newPet`, then in one transaction the link is `confirmed`, the appointment gets owner and animal and becomes PLANNED, the request shows `confirmed` with `appointmentId`, and the appointment appears in `/clinics/{c}/appointments`.
- [ ] AC 81 [7]: Given the clinic declines, the request expires (P2) or the clinic cancels the appointment, then the request shows `declined` (with its reason), the slot is freed, and `PORTAL_BOOKING_DECLINED` is sent once to the account phone (twice-delivered event → one message).
- [ ] AC 82 [7]: Given a clinic not accepting new clients, without new-client booking, or without any online motif allowing new clients, then its listing has `onlineBooking: false` and the booking route answers 422 `booking_not_open`.
- [ ] AC 83 [7]: Given more than 120 public listing requests per minute from one IP, then 429 with `Retry-After`.
- [ ] AC 260 [7]: Given no Bearer token, when `POST /clinic-listings/{l}/booking/appointments`, then 401 `unauthorized`; `GET /clinic-listings/{l}` without token answers 200.
- [ ] AC 261 [7]: Given unknown-number login codes from 21 phones of the same IPv4 /24 (or IPv6 /64) within an hour, then the 21st challenge is `sms_suppressed` (same 202) while a phone from another network still receives its SMS; changing `X-Device-Id` on every call does not bypass the network limit; when the global cap (500/h) is reached, a critical log is written once.
- [ ] AC 262 [7]: Given an ACTIVE PORTAL user for the phone (e.g. created by an invitation acceptance after the registration token was issued), when `POST /auth/register`, then 409 `account_exists`, the existing profile is unchanged and the token stays unconsumed.
- [ ] AC 263 [7]: Given `terms_outdated` on register, then no user and no profile exist and the token is still usable.
- [ ] AC 264 [7]: Given a user with a confirmed link to clinic C, when booking through C's listing, then 422 `already_linked`.
- [ ] AC 265 [7]: Given a user with one pending request at clinic C, when booking a second slot at C, then 422 `booking_request_limit`; with three pending requests across clinics, a fourth anywhere → 422 `booking_request_limit`.
- [ ] AC 266 [7]: Given a pending new-client appointment in the agenda, when staff click Confirm there, then it is refused with « À traiter dans Demandes Portal » and nothing changes; a clinic cancel from the agenda declines the request (AC 81).
- [ ] AC 267 [7]: Given a « Rattachement » or « Nouveau client » request whose file name differs from the Portal profile name, or whose file is already confirmed-linked to another account, then the detail shows the mismatch and « déjà relié à un autre compte Portal » badges.
- [ ] AC 268 [7]: Given a user with two pending requests at the same clinic, when the first is confirmed by creating a client, then the second request carries the same `client_id` and its confirmation creates no second client.
- [ ] AC 269 [7]: Given an account deleted with pending booking requests, then the requests are `declined` (`account_deleted`), no message is sent, their slots are freed, and `requester_*` / `new_pet_*` are null.
- [ ] AC 270 [7]: Given `GET /clinic-listings?lat=…&lng=…&cursor=X` where X was issued for other coordinates, then 400 `cursor_invalid`; `lat=95` → 422 `validation_failed`.
- [ ] AC 271 [7]: Given `clinic-listing:rebuild` on a database with existing clinics and no projection, then every clinic gets a projection row and listed ones appear in the search.

Replacement lines for the block after AC 83 (AC 88 and AC 90 currently sit between AC 87 and AC 91, before `**Cross-cutting`):
- [ ] AC 88 [7]: Given a client with optional consents, when the clinic rejects a link suggestion for that client (`link_rejected`), then the client's consents and history are unchanged (no consent is ever withdrawn on link revocation — D5).
- [ ] AC 90 [7]: Given an account with a pending booking request and a confirmed appointment, when the account is deleted, then the request is declined (`account_deleted`, no message) and its slot freed, its snapshots are scrubbed, and the confirmed appointment stays.

**Cross-cutting `[all]`**
- [ ] AC 84 [all]: Given each branch, when `make ci` runs, then it is green (incl. `make openapi-check`) and every touched BC keeps 100 % line coverage (excl. Presentation).
- [ ] AC 85 [all]: Given each branch's demo fixtures, then the UI states listed in its fixture task exist (CLAUDE.md §7).
- [ ] AC 86 [all]: Given any new cross-BC read, then it goes through a query bus port/adapter — no Portal code reads another BC's tables or `use`s its Domain or Infrastructure (enforced by `tests/Architecture/PortalBoundaryTest.php`, `*IntegrationEvent` classes allowlisted).

## Additional Context

### Dependencies

- **Slice 1** merged on `master` (foundation, OTP, JWT, `ClientLink`, `OwnerProfile`, `PortalApiTestCase`, limiters, idempotency store, cursor helper).
- **Order**: contract → 2 → 3 → 4a → 4b → 5 → 6 → 7. Slice 3 depends on slice 2 (timeline and `referentVet` read the registry); slice 4b on 4a; slice 6 on 2 (renewal on published prescriptions) and 4 (questionnaire per appointment); slice 7 on 4 (pending status, availability) and 6 (« Demandes Portal » screen hosts link and booking confirmations); slice 5's `email` resend channel depends on slice 5's email verification only.
- **Infrastructure**: Gotenberg (already used); `symfony/scheduler` (already used by `SchedulingScheduleProvider`); Messenger `DelayStamp` on the Doctrine transport (supported); Géoplateforme geocoding API (already used by Client's `GeoplateformeAddressDirectory`); no new Composer package expected (`UriSigner` is in `symfony/http-foundation`). If a ZIP library is needed for the export, `ext-zip` (`ZipArchive`) is used — check it is in the PHP image, else add it to the Dockerfile in Task 36.
- **Existing BC changes** (each with its README): Billing, Prescription, Consultation (Tasks 3–5, 12), Animal (11), Scheduling (13, 18–29), Client (31–32, 45), IdentityAccess (35, 37, 44), Notification (22, 35, 36, 40), Clinic (46); new BC ClinicListing (47).

### Testing Strategy

- **Unit** (in-memory fakes, `SettableClock`): every new aggregate and value object (`PublishedDocument`, `BookingMotif`, `OnlineBookingSettings`, `AppointmentStatus::occupiesSlot`, `Appointment::confirm/decline/request`, `CommunicationPreferences` diff, `Client::withdrawOptionalConsents`, `User::anonymisePortal`, request aggregates, `BookingRequest`, `ClinicListing`), every handler branch, `CareItemStatusPolicy`, `AvailabilityCalculator` (table-driven: blocks, capacity, closures, grid, lead time, horizon, DST days in `Europe/Paris`, constant offset in `America/Martinique`, `Pacific/Noumea` date boundary), portal status mapping, `AppointmentNotificationState` new transitions.
- **Integration (DBAL)**: each new repository; the `client__consent_history` backfill migration; `ListClientsByMobilePhone` index use; the per-practitioner-day lock and the concurrent booking/registration ACs (32, 34, 45, 74) **outside DAMA with two connections**, like slice 1's AC 55.
- **Pipeline tests** (event → bridge → async consumer → registry): AC 5, 7, 8, 12, 17, 78, 81, 94, 96, 100 and the slice-7 consumers of the `PendingAppointment*` events.
- **API tests** (`PortalApiTestCase`, real OTP sign-in, unique phones/IPs): every route of slices 2–7, every documented error code, tenant 404 on each `/clinics/{c}/…` family, signed URL expiry (clock), idempotent replays.
- **VetApp integration tests**: share button states and endpoint (AC 10), settings section (AC 38), confirm/decline (AC 35–36), the four screens of the `occupiesSlot()` audit (AC 31), « Demandes Portal » (AC 67–71, 76, 80), consent form source/author (AC 55), animal-sheet prevention regression (AC 28).
- **Coverage**: `make test-coverage` per branch, 100 % for every touched BC (excl. Presentation).
- **Manual**: per branch, a Playwright smoke of the touched VetApp screens with artefacts in `docs/playwright/runs/<date>-<slug>/`; the API walked through with the demo dataset (`make fixtures` then the Flutter app or HTTP calls).

### Notes

- **High-risk items**:
  1. `occupiesSlot()` audit (Task 19): ~12 files across 3 BCs; a missed literal makes pending appointments invisible or bookable twice — mitigated by AC 29–31 and a final `grep` listed in the task log.
  2. Per-practitioner-day lock now on every VetApp booking (Task 20) — a regression would block the agenda; AC 32–34 and a manual smoke of the agenda.
  3. Availability correctness across DST and overseas timezones (AC 42).
  4. Async publication: files rendered outside transactions, retries, idempotent consumers (AC 9, 17).
  5. Consent withdrawal on archived clients — the one deliberate exception to `ensureNotArchived()` (AC 57–58).
  6. Account deletion is irreversible — SMS code required (AC 64–65).
  7. Cross-tenant phone lookup for suggestions — only clinic name and city revealed, clinic confirmation required (AC 75–76).
- **Known limitations**: the VetApp copies of the latest-care rule (`AnimalSheetBuilder::preventionRows`, `CockpitContextBuilder::due` with its server-time window, `ClientSheetBuilder` raw SQL on `animal__preventive_care_records`, `LATEST_ACTS`) are left as they are (deferred, S3-F7); credit notes not published; `lab_result`/`certificate` without producer; `nextAvailableAt` null in the listing; owner requests applied manually by staff; an already logged-in user sees new link suggestions on demand only; files fully loaded in memory (`read()`), acceptable for PDFs; `ListAppointmentsForOwner` and other existing cross-BC SQL reads predate the rule and are left as is (not Portal code).
- **Future (out of scope)**: push (transport, devices, preferences — open decision « Push »), S3 pre-signed URLs, configurable questionnaire, unclaimed listings and `notify-me`, Belgian/Swiss prefixes, auto-applied change requests.
