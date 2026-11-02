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
  `ClinicProfileInterface`, `GetStaffPrintedName`), Animal
  (`ListAnimalSummariesPerClientIds`, `ListPortalPets`, `GetAnimalById`,
  `ListLatestPreventiveCare`, `ListAnimalWeightRecords`,
  `ListAnimalPreventiveCare`), Consultation (`GetConsultationPortalSummaries`,
  `GetLatestClosedConsultationWeight`), Scheduling
  (`GetNextAppointmentForAnimals`, `ListAppointmentsForOwners`,
  `GetOwnerAppointment`, `ListCareBookingsForAnimals`, `ListBookingMotifs`),
  IdentityAccess (`ClaimPortalIdentity`, `RevokeOnboardingGrantsForPhone`,
  `GetPortalAccount`; `VerifyPortalActionOtp`, `DisablePortalUser`,
  `PurgePortalPhoneTraces`, `AnonymisePortalUser` for the account deletion), Client (`GetClientConsents`, `ListClientConsentHistory`
  for the consents screen and the data export; `SetClientConsent` through
  `ClientConsentWriterInterface`), Notification (`RequestNotification`,
  `MaskPortalRecipient`, `PostInAppNotification`); slice 6 adds Consultation
  `ListAppointmentsWithConsultation` and Clinic `ListClinicMembersForNotification`
  / `ListPermissionsForUserInClinic` (who is told of a request).

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **PortalInvitation** | A clinic's invitation of one client: client's mobile (E.164) and e-mail at send time, delivery channel (`sms` \| `email`, link delivery only — codes always go to the mobile), token digest, `sentAt`, `expiresAt` (+7 days). Statuses `pending` / `accepted` / `revoked`; « expired » is derived (pending past `expiresAt`). |
| **Reissue** | « Renvoyer l'invitation »: a pending invitation (expired or not) keeps its id and gets a new token (the old link is dead), a new 7-day expiry and the client's current addresses. Codes and grants bound to the id stay valid. |
| **ClientLink** | Owner account ↔ clinic client. `confirmed` (access), `revoked` (access withdrawn, or a pending link rejected by the clinic) or `pending` (an accepted link suggestion waiting for the clinic: no access, `linkedAt` null, `requestedAt` set). One per (user, clinic, client): a revoked link is reactivated with a new `linkedAt`, never duplicated. |
| **Link suggestion** | A clinic holding a non-archived client file whose `mobile` is the account's phone (S7b). One per clinic (family files grouped); its id is opaque. Accepted → a « Rattachement » request in VetApp « Demandes Portal ». |
| **OwnerProfile** | One per owner account: first name, last name, postal address copied from the client file of the first accepted invitation (`createFromClient`), or the names entered at registration without invitation (`register`, no address) — never overwritten by a later one; the owner edits them through `PATCH /me` — plus the terms version and date of the latest acceptance. |
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
| `CompletePortalRegistration(registrationToken, firstName, lastName, ?email, termsVersion)` | command | Organic arrival (S7a), one transaction: terms check (`TermsOutdatedException`, nothing written) → IdentityAccess `RegisterPortalUser` through `PortalIdentityInterface::registerPortalUser` (grant, per-phone mutex; an active account of the phone → IdentityAccess's `PortalAccountExistsException` goes through) → `OwnerProfile::register`. `RegistrationOutcome{result, userId}`: `registered`, `replayed` (same grant again while it lives: the account it created, nothing written), `onboarding_invalid`, `account_disabled`. |
| `RevokeClientPortalAccess(clinicId, clientId)` | command | Invariant 7 (dispatched by the `ClientArchived` consumer). |
| `GetPortalInvitationByToken(token)` | query | `PortalInvitationDetails` (clinic name and city, e-mail, phone, active pet names, expiry); `InvitationInvalidException` / `InvitationExpiredException` / `InvitationAlreadyUsedException`. |
| `ListPendingInvitationsForPhone(phone)` | query | Pending, unexpired invitations of a phone (`invitationId`, clinic name, city) — IdentityAccess's pending-invitation port. |
| `GetClientPortalStatus(clinicId, clientId)` | query | The sheet state: a confirmed link → `active`; else the pending invitation → `invited` / `expired`, unless it predates the access withdrawal → `revoked`; else `not_invited`. |
| `ListOwnerClinics(userId)` | query | Confirmed and pending links, one entry per clinic (name, city, IANA time zone, `linkStatus`, earliest confirmed `linkedAt`, pet names of the confirmed files). A confirmed link wins; a pending-only clinic has no `linkedAt` nor pets. Oldest confirmed first, pending last. |
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
  (`ListLatestPreventiveCare`), `CareItemStatusPolicy`: booked when an
  appointment holding its slot, for a motif of the item's kind
  (`ListCareBookingsForAnimals`), falls on a clinic-local day of
  `[today, max(dueOn, today) + 30 d]`; else overdue before the clinic-local
  today, due soon within 30 days, else up to date (booked > overdue > due
  soon > up to date); nearest due date first.
- **Timeline.** The pet's listable report rows addressed to the account,
  cursor `(issued_at, id)`; per page one `GetConsultationPortalSummaries`
  (start, title) and one registry read of the visits' documents.
- **Home.** Alive pets, their 3 nearest care items, the next appointment
  holding its slot of an alive pet (the full `PortalAppointment`, as
  `GetOwnerAppointmentDetail` gives it), the first 2 documents of
  `ListPublishedDocuments`.
- **Practitioner names.** Every appointment names its practitioner with
  Clinic's printed name (`GetStaffPrintedName`, « Dr Prénom Nom »), the name
  online booking lists the practitioners with.
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

## Appointments and booking (slice 4)

Read through Scheduling's `ListAppointmentsForOwners`, `GetOwnerAppointment`
and `ListBookingMotifs` (port `OwnerAppointmentsInterface`,
`BookingMotifsInterface`): never the appointment's free-text reason or notes.
The Portal status (`confirmed`, `pending_clinic_confirmation`,
`cancelled_by_client`, `cancelled_by_clinic`, `declined`, `completed`,
`no_show`) and `cancellableUntil` come mapped from Scheduling. An appointment
without online motif shows `motif {id: null, label: « Rendez-vous »}`;
`relatedCareItem` is the pet's nearest care item of the motif's kind;
`questionnaireStatus` is `submitted` when the appointment has a submitted
pre-visit questionnaire, else `not_started`.

| Query | What it answers |
|---|---|
| `ListOwnerAppointments(userId, clinicId, scope, cursor?, limit?)` | `CursorPage<PortalAppointment>` of the confirmed-linked clients and of the pets the account sees; `upcoming` soonest first, `past` latest first; `InvalidCursorException`. |
| `GetOwnerAppointmentDetail(userId, clinicId, appointmentId)` | `?PortalAppointment`, null when not the account's. |
| `ListBookableMotifs(userId, clinicId, petId?)` | `list<BookableMotif>` open online, `suggested` with a French `suggestionReason` (« Rappel de vaccin en retard » / « … à prévoir », by kind) when the pet has an overdue or due-soon item of the motif's kind; `PetNotFoundException`. |

Bookings go through Scheduling's online commands (port `OnlineBookingInterface`,
adapter `CommandBusOnlineBooking`), whose refusals pass through unchanged
(`BookingNotOpenException`, `OnlineBookingNotFoundException`,
`SlotTakenException`, `CancellationWindowClosedException`,
`AppointmentNotModifiableException`). Every command answers the appointment id.
`PortalBookingGuard` decides what the account may touch (S4b-F4): a pet it sees
and that is alive, booked for the pet's primary owner; an appointment of its
client files or of the pets it sees.

| Command | What it does |
|---|---|
| `BookPortalAppointment(userId, clinicId, petId, motifId, practitionerId, startsAt)` | Books the pet; `PetNotFoundException` (not seen: unknown, sold, given, another client's), `PetNotBookableException` (deceased, missing). |
| `BookPortalAppointmentWithNewPet(userId, clinicId, petName, petSpecies, motifId, practitionerId, startsAt)` | D1, S4g: registers the animal (port `PetRegistrationInterface`, Animal `CreateAnimal`: sex and reproductive status `unknown`, registry `none`, alive, not transferred) for the client of the oldest confirmed link, then books it with `createdPetId`, in the command's transaction (a refused booking leaves no animal). An active animal of that client with the same name (any case) and species created in the last 24 h is reused. `InvalidNewPetException` (`name`: blank or over 80 characters; `species` ∉ `dog|cat|nac|other`), `ClinicNotLinkedException`. |
| `ReschedulePortalAppointment(userId, clinicId, appointmentId, practitionerId, startsAt)` | `AppointmentNotFoundException` when not the account's. |
| `CancelPortalAppointment(userId, clinicId, appointmentId, reason?)` | `AppointmentNotFoundException` when not the account's. |

## Profile and data export (slice 5)

**Profile** (`PATCH /me`). The owner edits the name (each 1 to 60 characters
after trimming) and the postal address (street, postal code, city, country;
stored as typed, no geocoding) of the `OwnerProfile` only: the clinics'
`Client` files never change. An acceptance still never overwrites the
identity (`save()` moves the terms only); the owner's edit goes through
`updateIdentity()`.

**Data export** (S5f, GDPR access and portability). `DataExport` (table
`client_portal__data_exports`): `pending` → `ready` (archive downloadable 48 h)
or `failed`; `ready` → `expired` once the archive is deleted (a ready export
past its 48 h already reads `expired`).

- **Quota.** One export per account per 24 h (`limiter.portal_data_export`,
  port `DataExportQuotaInterface`), consumed when the request is accepted,
  given back when the build fails → `DataExportRateLimitedException` (429
  `export_rate_limited`).
- **Build.** `BuildDataExport` (`async_external`, no transaction) runs
  `DataExportBuilder` and stores `client_portal/exports/<id>.zip`; its outcome
  is recorded by the short `RecordDataExport`. Content `schemaVersion: 1`:
  `account.json` (phone, e-mail and whether it is verified, profile, terms
  accepted), one `clinics/<clinicId>.json` per clinic the account was ever
  linked to (clinic name and city, links' status and dates; through the
  confirmed links only: consents merged per kind and their history — port
  `ClientConsentsInterface` —, appointments `{startAt, pet, motif, status}`,
  published documents `{type, title, issuedAt}`, no file), and `summary.pdf`
  (`templates/portal/export/summary.html.twig` through the shared document
  renderer). An account without link gets `account.json` and `summary.pdf`
  only. A failed build is not retried.
- **Notice.** When ready, a **verified** e-mail receives
  `PORTAL_DATA_EXPORT_READY` (no link: the archive is downloaded in the app).
- **Deletion.** `DeleteDataExportFile` (`async`, delayed 48 h after the
  export is ready), the nightly `SweepExpiredDataExports` (schedule
  `client_portal`, 03:23 Europe/Paris) for a lost message, and
  `DeleteUserDataExports(userId)` at account deletion (every export and its
  archive; an archive stored by a build still running is deleted when its
  outcome finds no export).

| Command / Query | Bus | What it does |
|---|---|---|
| `UpdateOwnerProfile(userId, firstName?, lastName?, address?)` | command | Null parts unchanged; `OwnerProfileNotFoundException` before any acceptance. |
| `RequestDataExport(userId)` | command | Consumes the quota, queues the build; returns the export id. |
| `BuildDataExport(exportId)` | external | Builds and stores the archive, then `RecordDataExport(exportId, built)`. |
| `RecordDataExport(exportId, built)` | command | `ready` (deletion queued, notice) or `failed` (quota refunded). |
| `DeleteDataExportFile(exportId)`, `SweepExpiredDataExports()` | command (`async`) | Delete the archive, the export turns `expired`. |
| `DeleteUserDataExports(userId)` | command | Account deletion: exports and archives removed. |
| `ListDataExports(userId)` | query | `list<DataExportSummary>`, latest first, status as of now. |
| `GetDataExportFile(userId, exportId)` | query | `?DataExportFile` (key to sign) for the account's export still downloadable. |

## Consents (slice 5)

The owner's consents of a clinic are those of the **confirmed-linked client
files of that clinic that are not archived** (`PortalConsentFiles`; none →
`ClinicNotLinkedException`, a 404). An archived file is never read nor
written: its consents stay intact (Client S5c).

- **Read** (`GetPortalConsents`). The five kinds always (`PortalConsentKind`,
  screen order), each with `effect`: `send_control` for the optional kinds
  (`marketing`, `appointment_reminders`, `vaccination_reminders`),
  `channel_preference` for `billing` and `clinical_documents`. Channels = the
  **union** across the files, `sms` then `email`; `grantedAt` = the **latest**
  date among the files holding the kind (the date from which every channel
  shown is consented), null without channel; `preferredChannel` = the one of
  the oldest linked file that has one.
- **Write** (`SetPortalConsent`). One command, one transaction: the handler
  loops through `ClientConsentWriterInterface` (Client's `SetClientConsent`,
  source `portal`, actor `portal_user` + user id, author = the profile's full
  name) over every file; Client writes one history row per file and channel
  changed. Refusals before any write: unknown kind
  (`UnknownConsentKindException`), a channel other than `sms`/`email` or no
  channel on `billing`/`clinical_documents` (`InvalidConsentChoiceException`).
  A lock that still fails (version mismatch, deadlock, lock wait timeout) →
  `ConsentConflictException` (409 `conflict`).
- **History** (`ListPortalConsentHistory`). The files' rows, newest first,
  grouped by `(kind, channel, action, at, source)`: a change applied to two
  files is one line (`approximate` if any row is). Rows of one instant are
  contiguous on `(at DESC, id DESC)`: lines are built per instant, ordered by
  their greatest row id; the cursor is `(at, id)` of the last line shown and
  the next page re-reads that instant from its start, dropping the lines
  already shown, so a line never splits across pages. A `clinic` row shows
  « La clinique » as author, never the staff member.

| Command / Query | Bus | What it does |
|---|---|---|
| `SetPortalConsent(userId, clinicId, kind, channels)` | command | Writes the kind's channels on every active linked file of the clinic. |
| `GetPortalConsents(userId, clinicId)` | query | `PortalConsents` (`preferredChannel`, `list<PortalConsent>`). |
| `ListPortalConsentHistory(userId, clinicId, cursor?, limit?)` | query | `CursorPage<PortalConsentHistoryItem>`. |

## Account deletion (slice 5)

S5g, GDPR erasure. The owner asks for a code (`POST /me/deletion-request/otp`:
IdentityAccess's `RequestPortalActionOtp`, purpose `account_deletion`), then
confirms it (`RequestAccountDeletion`).

- **At once, in the request's transaction.** The code is verified (a wrong
  code, a sign-in code or another user's code is `OTP_INVALID` and changes
  nothing but its attempt); then IdentityAccess disables the account and
  revokes every refresh token (`DisablePortalUser`): from the 202 on, its
  access tokens and refreshes answer 401. `DeletePortalAccount(userId)` is
  queued on `async` in the same transaction (port
  `AccountDeletionJobsInterface`).
- **The erasure** (`DeletePortalAccount`, one transaction, idempotent: a retry
  after any failure completes it without duplicate effect), in order:
  1. IdentityAccess `PurgePortalPhoneTraces` takes the per-phone lock row,
     deletes the challenges of the phone and of the user (onboarding grants
     with them), the phone's code quotas and the user's idempotency keys, and
     answers the phone and e-mail the account still has (none once anonymised);
  2. every `ClientLink` of the user is revoked (no consent changes, D5);
  3. the pending invitations of the phone (expired ones included) are revoked;
  4. the `AccountErasureStepInterface` services (tagged, autoconfigured) run —
     the extension point of slices 6 and 7 (requests closed, booking requests
     declined); each must be idempotent;
  5. `DeleteUserDataExports` (exports and archives);
  6. Notification `MaskPortalRecipient(phone, email)`: Kiveto's own messages
     to the owner (codes and account notices) lose recipient and bodies; those
     still queued (codes, export notice, booking decline) are cancelled and
     never sent — only an e-mail change security notice still goes out; the
     clinics' messages, the Portal invitation included, stay;
  7. `OwnerProfile::anonymise()` (« Compte supprimé », address erased; the
     terms acceptance stays);
  8. IdentityAccess `AnonymisePortalUser` (phone, e-mail and their
     verification dates NULL, `anonymised_at`) deletes the phone's lock row.
- **Kept**: the clinics' `Client` records, their consents and history, the
  documents and appointments (the clinic is the controller of its records).
  The phone is free: a new invitation to it creates a new account.

| Command | Bus | What it does |
|---|---|---|
| `RequestAccountDeletion(userId, challengeId, code)` | command | `DeletionCodeCheck` (`verified`, `otp_invalid`, `otp_expired`, `otp_attempts_exceeded`); verified → account disabled, erasure queued. |
| `DeletePortalAccount(userId)` | command (`async`) | The erasure above. |

## Link suggestions (slice 7)

S7b. An owner who registered without invitation (or whose phone is on file
elsewhere) is shown the clinics that already know the number, and asks to
be linked; the clinic decides.

- **Computed on read** (`LinkSuggestions`): Client's cross-clinic
  `ListClientsByMobilePhone` (port `ClientDirectoryInterface::mobileMatches`:
  label `mobile` only, archived clients and suspended or closed clinics
  excluded), grouped per clinic. A clinic is left out once the account has a
  link of **any** status to every matching file there — a rejected (revoked)
  link keeps it out, so a rejected suggestion never comes back. A file
  confirmed-linked to another account is kept (families share files) and
  flagged for staff.
- **Opaque id**: HMAC-SHA256 of `(userId, clinicId)` keyed by
  `%kernel.secret%` (`SuggestionIdSigner`). Only the clinic's name and city
  and `matchedOn: phone` leave the context.
- **Accept** → one `pending` link per matching file without a link
  (`ClientLink::requestFromSuggestion`), then the bell of the
  `MANAGE_ANIMALS` holders (`PORTAL_REQUEST`, « Demande de rattachement
  Portal », the files' names, key `portal-link-request:<first link id>`,
  opening `clinic_portal_link_requests_show`). A replay for a clinic the
  account already waits for answers `pending` without writing.
- **VetApp « Rattachement »** (`ListClinicLinkRequests`, `GetClinicLinkRequest`):
  one request per account and clinic while something is pending, the Portal
  profile name next to each file's name and animals, a mismatch flag (last
  names compared without case nor accents, `PersonName`) and the « déjà relié
  à un autre compte Portal » flag (D17: flags, never exclusions).
- **Confirm** (`confirmPending`) / **reject** (`reject`, event reason
  `link_rejected`) only the files staff were shown, each read `FOR UPDATE`;
  a link no longer pending is left as it is (the command answers 0). Reject
  changes no consent (D5).
- **Decision record** (S6-F3 audit trail): an answer that decided at least
  one file is recorded as a **`LinkDecision`** (`Domain/Link/`, table
  `client_portal__link_decisions`): clinic, account, outcome
  `confirmed|rejected`, the client files it decided (`client_ids`, JSON),
  `decided_at`, `decided_by_user_id`, `decided_by_name`. Written once by
  `LinkDecisionRecorder`; an answer that found nothing pending writes
  nothing, so a request is listed once. The links keep their own status;
  the decision is what « Demandes » lists under « Traitées » / « Refusées »
  (`ListClinicLinkRequests(clinicId, decided: true)`, `GetClinicLinkDecision`).

| Command / Query | Bus | What it does |
|---|---|---|
| `ListLinkSuggestions(userId)` | query | `list<LinkSuggestion{id, clinicName, city, matchedOn}>`, by clinic name. |
| `AcceptLinkSuggestion(userId, suggestionId)` | command | `pending`; `LinkSuggestionNotFoundException`. |
| `ConfirmClientLink(clinicId, userId, clientIds, actorUserId, actorName)` | command | How many pending links were confirmed (`ClientLinkConfirmed` each); the decision recorded. |
| `RejectClientLink(clinicId, userId, clientIds, actorUserId, actorName)` | command | How many pending links were revoked (`link_rejected`); the decision recorded. |
| `ListClinicLinkRequests(clinicId, decided = false)` | query | `list<ClinicLinkRequest>`: pending, oldest first; or (`decided`) one per decision, latest first (500 at most), with `decisionId`, `outcome`, `decidedAt`, `decidedByName`. |
| `GetClinicLinkRequest(clinicId, userId)` | query | `?ClinicLinkRequest` (null when nothing is pending). |
| `GetClinicLinkDecision(clinicId, decisionId)` | query | `?ClinicLinkRequest` of one answered request (null: unknown or another clinic's). |

## New-client booking (slice 7)

S7d. An account without link to a clinic listed in the directory asks it
for a first appointment (`POST /clinic-listings/{l}/booking/appointments`,
authenticated, idempotent). The request is a **`BookingRequest`**
(`Domain/Booking/`, table `client_portal__booking_requests`): `pending` →
`confirmed` (with the client file) | `declined` (`DeclineReason`:
`declined`, `expired`, `cancelled`, `account_deleted`). Scheduling holds the
slot with a pending `source=portal` appointment **without owner nor
animal** (new-client mode of `BookOnlineAppointment`: always pending, manual
lead time). No client link exists before the clinic confirms; the owner
follows the request through `GET /me/booking-requests` (account-scoped).

- **Request** (`RequestListingBooking`), checks in this order: the listing
  listed and active (port `ClinicListingsInterface`, ClinicListing
  `GetClinicListing`; `ListingNotFoundException`, 404); no confirmed link to
  the clinic (`AlreadyLinkedException`, 422 `already_linked`); at most 1
  pending request per clinic and 3 in all, counted under `FOR UPDATE`
  (`BookingRequestLimitException`, 422 `booking_request_limit`);
  `limiter.portal_listing_booking` (10 per account per 24 h, port
  `ListingBookingQuotaInterface`; 429 `rate_limited`); the listing's
  `onlineBooking` (`ListingBookingNotOpenException`) and Scheduling's
  refusals (`booking_not_open`, `not_found`, `slot_taken`). The requester
  (Portal profile names, account phone) and the new pet are snapshotted.
- **Confirm** (`ConfirmBookingRequest`, VetApp « Demandes Portal »
  « Nouveau client »), one transaction: the request `FOR UPDATE` and
  pending; the client file — a non-archived file of the clinic whose
  `mobile` is the account's phone (`ClientDirectoryInterface::mobileMatches`),
  or a new file without animals (port `ClientRegistrationInterface`, Client
  `CreateClient`: profile names, the phone as primary `mobile`), or the file
  a sibling request was confirmed for (AC 268); the animal — one of that
  client's (`PetDirectoryInterface::visiblePets`) or the new pet
  (`PetRegistrationInterface`, S4g); the `ClientLink` confirmed or
  reactivated; Scheduling `ConfirmNewClientAppointment` (owner and animal
  set, then confirmed); the account's other pending requests at the clinic
  attached to the same file. `InvalidBookingChoiceException` for a file or
  animal staff were not offered.
- **Decline** (`DeclineBookingRequest`): Scheduling `DeclineAppointment`,
  then `PORTAL_BOOKING_DECLINED`.
- **Who answered** (S6-F3): a staff confirm or decline stores
  `resolved_by_user_id` and `resolved_by_name` with `resolved_at`; the
  agenda, the expiry, a clinic cancel and the account deletion leave them
  null (VetApp shows « Agenda » / « Système »).
- **Sync** (`SyncBookingRequestFromAppointment`, dispatched by the
  consumers of Scheduling's `PendingAppointmentConfirmed|Declined|Cancelled`
  integration events): idempotent, a resolved request is left untouched;
  confirmed → `confirmed`; declined / expired / cancelled by the clinic →
  `declined` with that reason. `BookingDeclineNotice` sends Kiveto's SMS
  `PORTAL_BOOKING_DECLINED` (`{clinicName, slotLabel}`) to the request's
  phone, key `portal-booking-declined:<requestId>` — once per request, never
  for `account_deleted`.
- **Account deletion** (`BookingRequestAccountErasure`, an
  `AccountErasureStepInterface`): pending requests declined
  (`account_deleted`, no message), their appointments withdrawn in
  Scheduling (`WithdrawPendingOnlineAppointment`, whatever the cancellation
  window), then `requester_*` and `new_pet_*` nulled on every request of
  the account. Confirmed appointments stay.
- **Export**: `account.json` lists `bookingRequests` (any clinic).

| Command / Query | Bus | What it does |
|---|---|---|
| `RequestListingBooking(userId, listingId, petName, petSpecies, motifId, practitionerId, startsAt)` | command | The request id. |
| `ConfirmBookingRequest(clinicId, requestId, clientChoice existing\|new, ?clientId, animalChoice existing\|new, ?animalId, actorUserId, actorName)` | command | See above. |
| `DeclineBookingRequest(clinicId, requestId, actorUserId, actorName)` | command | `BookingRequestNotFoundException`, `BookingRequestAlreadyResolvedException`. |
| `SyncBookingRequestFromAppointment(appointmentId, outcome, ?ownerId)` | command | Consumers only. |
| `ListOwnerBookingRequests(userId, ?cursor, ?limit)` | query | `CursorPage<OwnerBookingRequest>`, newest first, cursor `(created_at, id)`. |
| `GetOwnerBookingRequest(userId, requestId)` | query | `?OwnerBookingRequest` (the 201 of the booking route). |
| `ListClinicBookingRequests(clinicId, decided = false)` | query | Pending `list<ClinicBookingRequest>` (matches, mismatch and « déjà relié » flags), oldest first; or (`decided`) the confirmed and declined ones, latest answered first (500 at most), with `resolvedAt`, `resolvedByName`. |
| `GetBookingRequest(clinicId, requestId)` | query | `?ClinicBookingRequest`, any status (matches only while pending). |

## Requests (slice 6)

What the owner asks the clinic to change is a **request** the clinic answers
in VetApp; the Portal never writes the animal or the prescription itself.
Three aggregates under `Domain/Request/` (tables
`client_portal__pre_visit_questionnaires`, `client_portal__pet_change_requests`,
`client_portal__prescription_renewal_requests`), each with the clinic, the
client file reached at submit, the account, its subject, `submittedAt` and
`updatedAt`.

- **Life cycle.** Change and renewal requests: `submitted` → `done`
  (« Traiter ») | `rejected` (« Refuser ») | `closed` (subject gone; reason
  `link_revoked`, `client_archived`, `account_deleted`,
  `prescription_cancelled`, `pet_archived`, `pet_deceased`; never notified,
  out of the queue). Only a submitted request moves
  (`PortalRequestAlreadyResolvedException`, with the name of whoever answered);
  answers are taken under `FOR UPDATE`. A questionnaire is `submitted` or
  `closed` (`appointment_cancelled`, `appointment_declined`,
  `client_archived`, `account_deleted`) and never answered.
- **Questionnaire** (`PreVisitQuestionnaire`, one per appointment, version
  1): symptoms (`none` alone), ongoing treatment (details ≤ 500), temperament,
  notes (≤ 1000). An appointment of the account's client files or of the
  pets it sees, neither cancelled nor declined (else not found); editable
  while it holds its slot, has not started and no consultation began from it
  (Consultation `ListAppointmentsWithConsultation`). A write on a frozen
  appointment records `lockedAt` and answers `LOCKED`: a reschedule never
  reopens it.
- **Pet change** (`PetChangeRequest`): `ProposedChange::of` validates and
  normalises per field — `name` 1–60, `weight` 0.05–150 kg (comma or dot) →
  `{weightGrams, measuredOn ≤ today}`, `sterilization` `neutered|intact`,
  `chip_number` 15 digits, `other` needs a note; value ≤ 120, note ≤ 500. The
  pet must be one the account sees and alive (`PetNotEligibleException` for
  a deceased, missing or archived pet of its clients). The pet's value at
  submit is snapshotted (weight in grams). At most 3 open per pet and field
  (`PortalRequestRateLimitedException`).
- **Renewal** (`PrescriptionRenewalRequest`, whole prescription): a listed
  prescription of the account (a cancelled one of the account →
  `not_renewable`, else not found), renewable by its owner (issued, no
  controlled substance, expired allowed), its pet alive, no refusal in the
  last 7 days (`PrescriptionNotRenewableException::reason`). A second submit
  answers the open request: MySQL's generated `open_key` (the prescription
  while submitted) carries a unique key, so concurrent submits leave one.
  « Traiter » may link an issued prescription of the same pet issued after
  the request (`resultingPrescriptionId`). `renewalStatus` of the documents
  list is its latest request.
- **Quota.** 10 change or renewal requests per 24 h per account and clinic
  (`limiter.portal_requests`, port `PortalRequestQuotaInterface`), consumed by
  the Portal API before it dispatches a submit.
- **Bell.** A new change request rings the `MANAGE_ANIMALS` holders, a
  renewal its prescriber (else every veterinarian): `PORTAL_REQUEST`, fixed
  labels with the pet and client names only, one entry per request
  (`portal-request:<id>`), opening `clinic_portal_requests_show`.
- **Answer to the owner.** On « Traiter » / « Refuser », Notification
  `PORTAL_REQUEST_RESOLVED` (clinic-sent, non-critical) on the client's
  transactional channel, key `portal-request-resolved:<id>`: « Votre demande
  de {modification | renouvellement d'ordonnance} pour {pet} a été traitée. »
  / « … n'a pas pu être acceptée. » and the team's message (≤ 280). A client
  without reachable address is flagged (« client injoignable »).
- **Closures.** Client archived (`ClientArchived` consumer), prescription
  cancelled (`PrescriptionCancelled` consumer), pending booking withdrawn or
  declined (Scheduling `PendingAppointmentCancelled/Declined`), account
  deleted (`PortalRequestAccountErasure`, an `AccountErasureStepInterface`:
  closed and every request's free text erased). Animal publishes no archive
  or death event: a request of an archived or deceased pet reads `closed` and
  leaves the queue at read time, and is closed for good when someone answers
  it. Other appointment cancellations hide the questionnaire at read time.
- **Export.** Each `clinics/<clinicId>.json` lists the account's requests and
  questionnaires in that clinic.

| Command / Query | Bus | What it does |
|---|---|---|
| `SubmitQuestionnaire(userId, clinicId, appointmentId, version, symptoms, hasOngoingTreatment, ?ongoingTreatmentDetails, temperament, ?notes)` | command | `SubmitQuestionnaireOutcome` (`SUBMITTED`, `LOCKED`). |
| `SubmitPetChangeRequest(userId, clinicId, petId, field, proposedValue, ?measuredOn, ?note)` | command | The request id. |
| `SubmitRenewalRequest(userId, clinicId, prescriptionId, petCondition, ?note)` | command | The request id (the open one when there is one). |
| `ResolvePortalRequest(clinicId, requestId, outcome, actorUserId, actorName, ?message, ?resultingPrescriptionId)` | command | The status afterwards (`done`, `rejected`, `closed`). |
| `ClosePortalRequestsForSubject(subject, subjectId, reason, ?clinicId)` | command | How many requests were closed. |
| `GetQuestionnaire(clinicId, appointmentId, ?userId)` | query | `PortalQuestionnaire` (owner-scoped with a user id). |
| `ListQuestionnaireSummaries(clinicId, appointmentIds)` | query | `list<QuestionnaireSummary>` (agenda badge). |
| `ListOwnerPortalRequests(userId, clinicId, ?cursor, ?limit)` | query | `CursorPage<OwnerPortalRequest>`, newest first. |
| `ListClinicPortalRequests(clinicId, ?status, ?kind, ?cursor, ?limit)` | query | `CursorPage<ClinicPortalRequest>`, never closed ones. |
| `GetPortalRequest(clinicId, requestId)` | query | `?PortalRequestDetails`. |
| `GetLatestRenewalStatuses(clinicId, prescriptionIds)` | query | `array<string, RenewalStatus>`. |

## Events

- `PortalInvitationSent` (`reissued` on a resend), `PortalInvitationAccepted`,
  `ClientLinkConfirmed`, `ClientLinkRevoked` (`reason`: `access_withdrawn`
  or `link_rejected`) — domain events.
- `ClientLinkConfirmedIntegrationEvent` (linkId, userId, clinicId, clientId) —
  bridged from `ClientLinkConfirmed`.
- `DocumentPublished`, `DocumentWithdrawn` — domain events of the registry.

## Consumers

- `ClientArchivedIntegrationEventConsumer` (Client) → `RevokeClientPortalAccess`,
  `ClosePortalRequestsForSubject` (`client_archived`).
- `PrescriptionCancelledIntegrationEventConsumer` also closes the open renewal
  (`prescription_cancelled`); Scheduling `PendingAppointmentCancelled` /
  `PendingAppointmentDeclined` close the questionnaire.
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
client_id`, index `clinic_id, status`; `requested_at` for pending links), `client_portal__owner_profiles` (PK `user_id`),
`client_portal__published_documents` (unique `clinic_id, type, source_ref`),
`client_portal__document_reads` (PK `document_id, user_id`),
`client_portal__booklet_files` (PK `animal_id`) and
`client_portal__data_exports` (indexes `user_id, requested_at` and
`status, expires_at`), `client_portal__booking_requests` (unique
`appointment_id`, indexes `user_id, created_at, id` and `clinic_id, status`),
`client_portal__link_decisions` (index `clinic_id, decided_at, id`), the three request tables (unique `clinic_id,
appointment_id` for questionnaires; unique `clinic_id, open_key` on the
generated column of renewals). Migrations in
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

Slice 5 (`portal` dataset): `fixtures/Context/Client/Story/ConsentsStory.php`
writes the consents and their history (Girard Paris: all kinds; Girard Lyon:
none; Rey: partial; Marchand's two Paris files diverge; Nathalie Caron
archived with optional consents, her twin Pierre unarchived; « Reprise »
approximate rows), and `PortalAccountStatesStory.php` the account states:
Marchand (+33612345680) verified e-mail, exports ready (real ZIP) and
expired; Rey (+33612345681) verified e-mail, pending change to
sophie.rey@laposte.net, export pending; Nicolas Lefèvre's account deleted
(anonymised, his client file kept).

**Reading the one-time code in dev**: with `NOTIFICATION_DELIVERY=simulator`
no SMS leaves; the notification worker (`external-worker`, started by `make
worker-start`) logs each SMS body on the monolog channel `dev_sms`, in
`var/log/dev.log` (the project directory is mounted in the worker
container): `grep dev_sms var/log/dev.log | tail -n 5`.
