# ClientPortal Bounded Context

## Purpose

Connects pet owners to the clinics that treat their animals. A clinic invites
one of its clients to the Portal (the owner-facing mobile app, later the web
Portal); the owner proves the client's mobile phone and accepts; from then on
the owner account (an IdentityAccess `User` of type PORTAL) reaches that
client file through a **client link**. The context also keeps the owner's
Portal **profile** (name, postal address, accepted terms of use).

## Guiding rules

- **The phone is the identity.** An invitation is bound to the client's
  mobile; it can only be accepted by proving that phone — the one-time code
  sent to it (link flow) or the onboarding grant earned by signing in with it
  (phone flow). The proof is verified by IdentityAccess (`ClaimPortalIdentity`)
  inside the acceptance transaction; ClientPortal never resolves a code or a
  grant itself.
- **One phone rule.** Only numbers accepted by Client's `MobileNumberPolicy`
  (`IsPortalMobileNumber`) can be invited — the rule the Portal API applies to
  sign-in codes, so a clinic never invites a number the app would refuse.
- **Failures that cost an attempt are results.** The acceptance commands answer
  an `AcceptOutcome` and commit, so a wrong code keeps its attempt; the only
  exception, `TermsOutdatedException`, is thrown before any write.
- **Secrets are never stored.** The invitation token is a 256-bit secret
  (`OpaqueTokenHasherInterface`); only its sha256 is kept. It travels in the
  URL fragment (`…/invitation#t=<token>`), never in a path.
- **Cross-context access through ports only**: Client (`GetClientNotificationContact`,
  `GetClientById`, `IsPortalMobileNumber`), Clinic (`GetClinic` through
  `ClinicProfileInterface`, `ListClinicVeterinarians`), Animal
  (`ListAnimalSummariesPerClientIds`, `ListPortalPets`, `GetAnimalById`,
  `ListLatestPreventiveCare`, `ListAnimalWeightRecords`,
  `ListAnimalPreventiveCare`), Consultation (`GetConsultationPortalSummaries`,
  `GetLatestClosedConsultationWeight`), Scheduling
  (`GetNextAppointmentForAnimals`), IdentityAccess (`ClaimPortalIdentity`,
  `RevokeOnboardingGrantsForPhone`), Notification (`RequestNotification`).

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **PortalInvitation** | A clinic's invitation of one client: client's mobile (E.164) and e-mail at send time, delivery channel (`sms` \| `email`, link delivery only — codes always go to the mobile), token digest, `sentAt`, `expiresAt` (+7 days). Statuses `pending` / `accepted` / `revoked`; « expired » is derived (pending past `expiresAt`). |
| **Reissue** | « Renvoyer l'invitation »: a pending invitation (expired or not) keeps its id and gets a new token (the old link is dead), a new 7-day expiry and the client's current addresses. Codes and grants bound to the id stay valid. |
| **ClientLink** | Owner account ↔ clinic client. `confirmed` (access), `revoked` (access withdrawn) or `pending` (reserved for link suggestions, slice 7). One per (user, clinic, client): a revoked link is reactivated with a new `linkedAt`, never duplicated. |
| **OwnerProfile** | One per owner account: first name, last name, postal address copied from the client file of the first accepted invitation (never overwritten), plus the terms version and date of the latest acceptance. |
| **Onboarding grant** | A 15-minute token IdentityAccess hands out when a phone that has pending invitations signs in; it accepts those invitations without a second code. Revoked when the phone's last pending invitation is accepted. |
| **Portal status** (clinic sheet) | `not_invited`, `invited`, `expired`, `active`, `revoked` (« Accès retiré »). |

## Invariants

1. An invitation always has a mobile phone accepted by `MobileNumberPolicy`; the
   e-mail channel requires an e-mail.
2. At most one pending invitation per clinic client: sending again reissues it.
3. Only a pending, unexpired invitation can be accepted, and only by proving its
   phone; acceptance confirms (or reactivates) the link and creates the profile
   if absent, in one transaction — any failure after the account claim rolls
   everything back, the claim included.
4. A retry of an acceptance whose answer was lost (same invitation, same
   account proven again) answers `accepted` without writing anything.
5. The grant route checks the grant at use time: on an invitation no longer
   pending it answers `onboarding_invalid` when the grant's phone has nothing
   left to accept, `invitation_invalid` otherwise. A grant never accepts an
   invitation of another phone.
6. A disabled owner account accepts nothing: no link, no profile, the
   invitation stays pending.
7. An archived client loses its access (pending invitations revoked, links
   revoked); unarchiving restores nothing — the clinic invites again and the
   new acceptance reactivates the same account's link.

## Use Cases

| Command / Query | Bus | What it does |
|---|---|---|
| `SendPortalInvitation(clinicId, clientId, channel, actorUserId)` | command | Checks the client (`ClientNotInvitableException::reason`: `unknown_client`, `archived`, `no_mobile`, `mobile_not_supported`, `no_email`), creates or reissues the invitation, requests `PORTAL_INVITATION` last (`{clinicName, ownerFirstName, link, expiresOn}`, key `portal-invitation:<id>:<sentAt unix>`, `clientId` set so Notification journals it in the client file). Returns the invitation id. |
| `AcceptPortalInvitationWithCode(token, challengeId, code, termsVersion)` | command | Link flow. `AcceptOutcome{result, userId}`; `invitation_invalid` (unknown or replaced token), `invitation_expired`, `invitation_already_used` (revoked, or accepted by another account), the OTP results, `account_disabled`. |
| `AcceptPortalInvitationWithGrant(invitationId, onboardingToken, termsVersion)` | command | Phone flow. Same outcome; `onboarding_invalid` / `invitation_invalid` per invariant 5. |
| `RevokeClientPortalAccess(clinicId, clientId)` | command | Invariant 7 (dispatched by the `ClientArchived` consumer). |
| `GetPortalInvitationByToken(token)` | query | `PortalInvitationDetails` (clinic name and city, e-mail, phone, active pet names, expiry); `InvitationInvalidException` / `InvitationExpiredException` / `InvitationAlreadyUsedException`. |
| `ListPendingInvitationsForPhone(phone)` | query | Pending, unexpired invitations of a phone (`invitationId`, clinic name, city) — IdentityAccess's pending-invitation port. |
| `GetClientPortalStatus(clinicId, clientId)` | query | The sheet state: a confirmed link → `active`; else the pending invitation → `invited` / `expired`, unless it predates the access withdrawal → `revoked`; else `not_invited`. |
| `ListOwnerClinics(userId)` | query | Confirmed links, one entry per clinic (name, city, IANA time zone, earliest `linkedAt`, pet names). |
| `GetOwnerProfile(userId)` | query | `?OwnerProfileDetails{firstName, lastName, address: ?{line1, line2, postalCode, city, country}, termsVersion, termsAcceptedAt}`. |
| `HasConfirmedClientLink(userId, clinicId)` | query | The Portal API's tenant guard (false for a missing, pending or revoked link). |

## Published documents (slice 2)

The publication registry (`PublishedDocument`, table
`client_portal__published_documents`, one row per clinic + type + source) says
which clinic document a client's Portal accounts see. There is no Document
context: the Portal reads the sources live through query-bus ports.

- **Triggers.** Invoices and prescriptions are published when issued
  (`InvoiceIssuedForPortalIntegrationEvent`, `PrescriptionIssuedIntegrationEvent`
  → `PublishInvoice` / `PublishPrescription`, which re-read the source and skip a
  draft, a cancelled document, a counter sale or a prescription without owner);
  a consultation report only through the vet's « Partager sur le Portal »
  (`ShareConsultationReport`).
- **Visibility.** An invoice is shown to its payer only; a prescription to the
  owner it was written for; a report to the animal's primary owner at share time.
  Every read joins the account's **confirmed** links of the clinic in SQL
  (`DbalPublishedDocumentReadModel`): no client id is ever passed in.
- **States.** `awaiting_link` (no confirmed link yet: nothing rendered) →
  `pending_file` → `available`, or `failed` after 5 renders; any state →
  `withdrawn`, terminal (a cancel delivered first leaves a tombstone, so the late
  publication is ignored). Listed: a served file (version ≥ 1) that is neither
  withdrawn nor awaiting a link — a re-share keeps serving the previous version.
- **Files.** `StorePublishedDocumentFile` (`async_external`, no transaction)
  uses Billing's durable invoice PDF, or renders a prescription / report under
  `client_portal/documents/<id>-v<n>.pdf`, then `AttachPublishedDocumentFile`
  swaps the version (reads forgotten from v2, previous file deleted 10 minutes
  later by `DeleteStoredDocumentFile`, which refuses keys outside
  `client_portal/`). A failure is counted by `RecordDocumentRenderFailure`.
- **Repair.** `ReconcilePublishedDocuments` (schedule `client_portal`, every 5
  minutes) asks again for files expected for more than 10 minutes;
  `app:client-portal:import-documents [--since=Y-m-d]` publishes the invoices and
  prescriptions of the last 24 months at go-live (never reports).
- **Live states.** The invoice payment status and the prescription state
  (`renewable` = issued, no controlled substance) are read per page; a row whose
  source is no longer listable is left out with a warning.

| Command / Query | Bus | What it does |
|---|---|---|
| `PublishInvoice(clinicId, invoiceId)`, `PublishPrescription(clinicId, prescriptionId)` | command | Insert-if-absent; file requested when the client is linked. |
| `ShareConsultationReport(clinicId, consultationId, actorUserId)` | command | `ReportShareResult{documentId, linked}`; `ReportNotShareableException::reason` `unknown`, `not_closed`, `no_animal`, `no_primary_owner`. |
| `WithdrawPublishedDocument(clinicId, type, sourceRef)` | command | Cancelled source (consumers of the cancel events). |
| `ReleaseAwaitingDocuments(clinicId, clientId)` | command | A confirmed link releases the documents waiting for it. |
| `BackfillDocumentPets`, `RepointDocumentPatient` | command | Patient linked to an animal / reconciled into another patient. |
| `MarkDocumentRead(userId, documentId)` | command | Read for that account only. |
| `ListPublishedDocuments(userId, clinicId, type?, petId?, cursor?, limit?)` | query | `CursorPage<PortalDocument>`, newest first, cursor `(issued_at, id)`. |
| `GetPublishedDocumentFile(userId, clinicId, documentId)` | query | Served version and key, to sign a download. |
| `GetPublishedDocumentVersionFile(clinicId, documentId, version)` | query | Key of a signed download; null once withdrawn. |
| `CountUnreadDocumentsPerClinic(userId)` | query | `GET /me` badge. |
| `GetPortalPrescription(userId, clinicId, prescriptionId)` | query | Lines of a listed prescription. |

## Pets (slice 3)

Read-only: the Portal shows the animals through Animal, Consultation and
Scheduling queries, and the registry above for the visits.

- **Visible pets** (`OwnerPets`). Animal's `ListPortalPets` over the client
  files the account reaches through a **confirmed** link of the clinic, read
  from `client_portal__client_links`: active animals of an active primary
  owner, never sold or given. Any other pet id is `PetNotFoundException` (404).
  Deceased and missing pets stay in the list, the detail and the timeline,
  but get no care item and never appear on the home.
- **Latest weight.** The most recent of the animal file's weight records and
  the vitals of CLOSED consultations, compared on the clinic-local day (a
  visit is dated by its start); on the same day the animal file wins.
- **Referent vet.** The practitioner snapshotted on the latest report shared
  with the account; null without one.
- **Care items.** The latest act of each protocol with a next due date
  (`ListLatestPreventiveCare`), `CareItemStatusPolicy`: overdue before the
  clinic-local today, due soon within 30 days, else up to date; nearest due
  date first.
- **Timeline.** The pet's listable report rows addressed to the account,
  cursor `(issued_at, id)`; per page one `GetConsultationPortalSummaries`
  (start, title) and one registry read of the visits' documents.
- **Home.** Alive pets, their 3 nearest care items, the next PLANNED
  appointment of an alive pet (fixed French label of its category,
  practitioner from one `ListClinicVeterinarians`), the first 2 documents of
  `ListPublishedDocuments`.
- **Vaccination booklet** (`VaccinationBookletFiles::ensure(clinicId,
  animalId)`, called without a bus once `GetOwnerPet` succeeded). The PDF is
  rendered from `templates/portal/document/vaccination_booklet.html.twig` and
  cached under `client_portal/booklets/<animalId>-<sha256>.pdf`, the hash of
  every render input (vaccinations, identity, clinic header,
  `TEMPLATE_VERSION`); the current key per animal is kept in
  `client_portal__booklet_files`, a replaced booklet is deleted 10 minutes
  later.

Instants are returned in the clinic's time zone; date-only values are dates.

| Query | What it answers |
|---|---|
| `ListOwnerPets(userId, clinicId)` | `list<OwnerPet>`, alive first, then by name. |
| `GetOwnerPet(userId, clinicId, petId)` | `OwnerPetDetails{pet, lastWeight?, chipNumberMasked?, referentVet?}`, null when not visible. |
| `ListOwnerCareItems(userId, clinicId, petId)` | `list<OwnerCareItem>`; `PetNotFoundException`. |
| `ListPetTimeline(userId, clinicId, petId, cursor?, limit?)` | `CursorPage<TimelineEntry>`; `PetNotFoundException`, `InvalidCursorException`. |
| `GetOwnerHome(userId, clinicId)` | `OwnerHome{nextAppointment?, pets, careItems, latestDocuments}`. |

## Events

- `PortalInvitationSent` (`reissued` on a resend), `PortalInvitationAccepted`,
  `ClientLinkConfirmed`, `ClientLinkRevoked` — domain events.
- `ClientLinkConfirmedIntegrationEvent` (linkId, userId, clinicId, clientId) —
  bridged from `ClientLinkConfirmed`.
- `DocumentPublished`, `DocumentWithdrawn` — domain events of the registry.

## Consumers

- `ClientArchivedIntegrationEventConsumer` (Client) → `RevokeClientPortalAccess`.
- Billing `InvoiceIssuedForPortal` / `InvoiceCancelled`, Prescription
  `PrescriptionIssued` / `PrescriptionCancelled`, Patient `PatientLinkedToAnimal`
  / `PatientReconciledInto` and our own `ClientLinkConfirmed` integration events
  → the publication commands above.
- Clients of this context: the Portal API (lookup, acceptances, `/me`, tenant
  guard), the clinic client sheet (send, status) and IdentityAccess
  (`ListPendingInvitationsForPhone`).

## Persistence

Raw DBAL repositories (`FOR UPDATE` reads, upserts) over schema-only Doctrine
entities: `client_portal__portal_invitations` (`token_hash` unique, index on
`phone`), `client_portal__client_links` (unique `user_id, clinic_id,
client_id`), `client_portal__owner_profiles` (PK `user_id`),
`client_portal__published_documents` (unique `clinic_id, type, source_ref`),
`client_portal__document_reads` (PK `document_id, user_id`) and
`client_portal__booklet_files` (PK `animal_id`). Migrations in
`migrations/ClientPortal/`.

## Configuration

`PORTAL_PUBLIC_BASE_URL` (invitation links), `PORTAL_TERMS_VERSION` (the only
version an acceptance may carry).

## Fixtures

`fixtures/Context/ClientPortal/Story/ClientPortalStatesStory.php` (loaded by
the `clinic` and `portal` datasets) writes every sheet state in the Paris
clinic (Clinique Vétérinaire de Paris, `ClinicDataStory::INDEPENDENT_CLINIC_ID`):

| Client | Mobile | Sheet |
|---|---|---|
| Mme Camille Fontaine | +33611223344 | « Portal : non invité » · « Inviter au Portal » |
| M. Antoine Roussel | +33622334455 | invited yesterday · « Renvoyer l'invitation » |
| Mme Laura Perrin | +33633445566 | « Invitation expirée » · « Renvoyer l'invitation » |
| M. Julien Girard (Oslo) | +33612345678 | « Actif depuis le … » |
| Mme Élise Moreau (archived) | +33644556677 | « Accès retiré » · « Réinviter » (disabled while archived) |
| M. Bernard Giraud | landline only | disabled: no mobile |
| Mme Chloé Lambert | +33655667788 | E-mail segment disabled (no e-mail) |
| M. Lukas Weber | +4915112345678 (« mobile ») | disabled: number not supported by the Portal |

Demo phones on the Portal API:

- **+33612345678** — the demo owner account, linked to two clinics (Paris:
  Oslo; Lyon: Pixel), profile and current terms accepted.
- **+33622334455** — a pending invitation and no account: signing in shows
  the phone onboarding (`invitation_pending`). Its link token is
  `ClientPortalStatesStory::ROUSSEL_TOKEN`; `PERRIN_TOKEN` is expired.
- **+33644556677** — an account whose only link was revoked (no clinic).

**Reading the one-time code in dev**: with `NOTIFICATION_DELIVERY=simulator`
no SMS leaves; the notification worker (`external-worker`, started by `make
worker-start`) logs each SMS body on the monolog channel `dev_sms`, in
`var/log/dev.log` (the project directory is mounted in the worker
container): `grep dev_sms var/log/dev.log | tail -n 5`.
