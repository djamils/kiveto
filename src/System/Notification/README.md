# Notification System Bounded Context

## Purpose

Sends the messages Kiveto writes to people — clients (appointments, invoices,
unpaid reminders, consultation documents, Portal invitation and one-time
login code) and staff (invitation, password reset) — by e-mail and SMS, keeps a journal of every outbound message with its
delivery status, and hosts the in-app notification feed (the « cloche »).

Messages are rendered from **Twig templates owned by Kiveto**
(`templates/notification/**`); Brevo is used as a plain transport through the
official Symfony bridges (`symfony/brevo-mailer` API transport,
`symfony/brevo-notifier`) and reports delivery through webhooks.

## Guiding rules

- **A dumb pipe.** Notification never reads another context's data and never
  decides who may receive what: the requesting context resolves the recipient,
  applies the client's consents and passes ready-to-print parameters. The only
  clinic data it knows is its own read-only copy, the *clinic messaging
  profile*, pushed by Clinic.
- **One entry point**: `RequestNotification` (command bus). It is idempotent on
  an opaque key built by the requester; a replayed request never sends twice.
- **Network outside transactions.** The provider is called by
  `DispatchOutboundMessage` (`ExternalApiCommandInterface`, transport
  `async_external_notification`), which records its outcome through a short
  transactional command.
- **Dependency direction**: Contexts → Notification, never the reverse. Code
  outside this BC may import only its `Application\Command\…`,
  `Application\Query\…` and `Domain\Event\*IntegrationEvent`; this BC imports
  no `App\Context\…` and no `App\Presentation\…` (guarded by
  `NotificationBoundaryTest`).

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **OutboundMessage** | One e-mail or one SMS to one recipient, with its content frozen at request time, its attachments, the provider message id and its status history. The bodies of `STAFF_INVITATION`, `PASSWORD_RESET`, `PORTAL_INVITATION` (one-time link), `PORTAL_OTP` and `PORTAL_EMAIL_VERIFICATION` (one-time codes) are erased once the message is handed over or fails (subject and metadata stay); such a message whose link or code expired while it waited (30 min / 72 h / 7 days / 10 min after the request) is not sent: FAILED « Lien expiré avant l'envoi ». |
| **MessagePurpose** | Why the message is sent: `APPOINTMENT_CONFIRMATION`, `APPOINTMENT_CHANGE`, `APPOINTMENT_CANCELLATION`, `APPOINTMENT_REMINDER`, `APPOINTMENT_DECLINED` (« Demande de RDV non confirmée »), `INVOICE_SENT`, `INVOICE_REMINDER`, `CONSULTATION_DOCUMENTS`, `STAFF_INVITATION`, `PASSWORD_RESET`, `TEST`, `PORTAL_OTP` (« Code de connexion Portal »), `PORTAL_INVITATION` (« Invitation au Portal »), `PORTAL_EMAIL_VERIFICATION` (« Vérification de l'adresse e-mail Portal »), `PORTAL_EMAIL_CHANGED_NOTICE` (« Adresse e-mail Portal modifiée »), `PORTAL_DATA_EXPORT_READY` (« Export de données Portal prêt »), `PORTAL_REQUEST_RESOLVED` (« Réponse à une demande Portal »), `PORTAL_BOOKING_DECLINED` (« Demande de RDV Portal non confirmée »). |
| **Criticality** | Fixed per purpose. `NON_CRITICAL` (appointment reminder, unpaid reminder) SMS are subject to the overage cap; everything else is `CRITICAL`. |
| **DeliveryStatus** | `QUEUED` (0) → `SENT` / `DEFERRED` (1) → `DELIVERED` / `BOUNCED` / `REJECTED` / `FAILED` / `BLOCKED_CAP` / `CANCELLED` (2, final). `CANCELLED` (« Annulé ») is a QUEUED message withdrawn before its dispatch claimed it, because its Portal account was deleted: never sent, never billed, never reported by a provider. A complaint (spam) is a flag, not a status. |
| **IdempotencyKey** | Opaque string built by the requester (`appointment:<id>:confirmation:1`, `invoice:<id>:send:<dispatchId>`, `invitation:<tokenId>`…), unique across messages. |
| **SenderIdentity** | Clinic purposes: `<Clinic name> <<slug>@mail.kiveto.app>`, reply-to the letterhead e-mail, SMS sender = clinic short sender (fallback « Kiveto »). Account, Portal and test purposes: `Kiveto <no-reply@kiveto.app>`, SMS sender « Kiveto » (a Portal invitation still carries its `clinicId` and `clientId`, for the client journal). |
| **ClinicMessagingProfile** | Notification's copy of what it needs from a clinic (name, e-mail local part, reply-to, SMS sender, overage cap, address, phone, brand colour, logo), upserted by Clinic. |
| **Segment** | The unit an SMS is billed in: GSM-7 160 characters (153 per part when split), UCS-2 70 (67); `€`, `[`, `]`, `{`, `}`, `^`, `~`, `\`, `|` count 2. |
| **SMS quota** | 400 segments × the maximum number of active veterinarians of the month (Europe/Paris), pooled per clinic, never below 400. |
| **Overage cap** | Segments allowed beyond the quota (default 500). Reached → non-critical SMS fall back to e-mail when the requester allowed it, else `BLOCKED_CAP`. |
| **Attachment owner** | `REQUESTER` (the file belongs to the requesting context, e.g. Billing's invoice PDF — never touched) or `NOTIFICATION` (produced for the send only, purged with the message content). |

## Business Invariants

1. One message per idempotency key.
2. A message is never sent with a wrong identity: a clinic purpose requested
   for a clinic without messaging profile is recorded `FAILED` « Profil
   d'envoi de la clinique absent » and never handed to a transport.
3. A final status (rank 2) is never overwritten; a lower rank never replaces a
   higher one; within a rank an older event never replaces a newer one; a
   provider event already in the history (same status and time) is recorded once.
4. SMS usage is counted from the journal in **billed segments**: only SMS
   handed to the provider (`sent_at` set) count — never `QUEUED`,
   `BLOCKED_CAP` or an SMS failed before hand-over.
5. The cap check of a non-critical SMS counts sent **and queued** segments and
   is serialised per clinic by a row lock on the clinic messaging profile
   (`SELECT … FOR UPDATE`, held until COMMIT): a bulk send cannot jump over it.
6. Every status a message takes, including the one it is created in, is
   published as `OutboundMessageStatusChangedIntegrationEvent` (strictly
   increasing `sequence`).
7. SMS templates are pure GSM-7; variable data is transliterated to GSM-7
   (« Chloë » → « Chloe »), accented letters of the GSM-7 table (é è à ù…) are kept.

## Use Cases

| Command / Query | Bus | What it does |
|---|---|---|
| `RequestNotification` | command | Resolves the sender, renders and freezes the content, applies the SMS cap (non-critical SMS, under the profile lock) with the optional e-mail fallback, saves the message and queues `DispatchOutboundMessage`. Returns the message id. The cap check holds the profile row lock and shared locks on the clinic's SMS rows until the caller's transaction commits: dispatch it last in that transaction. |
| `DispatchOutboundMessage` | external (`async_external_notification`, chosen by `DispatchOutboundMessage::stamps()`: a YAML route would also match the generic `ExternalApiCommandInterface` route) | Only a QUEUED message is handed over, and it is first **claimed** (`claimForDispatch()`: a short transaction, `SELECT … FOR UPDATE`, `dispatch_claimed_at` set): a replayed dispatch, or one still on the transport when its message was `CANCELLED`, sends nothing; once claimed, an account deletion masks the message but no longer cancels it (see *Account deletion*). A claimed message masked before a retry is not sent: FAILED « Compte Portal supprimé avant l'envoi ». Hands a QUEUED message to the e-mail or SMS transport (attachments and inline logo read from the file storage, idempotency key sent as Brevo tag) and records the outcome. A 4xx other than 401 / 403 / 408 / 429, or an address the MIME layer refuses, is FAILED at once (French reason « Refusé par le fournisseur (HTTP 400) », the provider's message in the `external_api` log); 401 / 403 (key or authorised IPs wrong: error log), 408, 429, 5xx and network errors are retried (`DispatchRetryStrategy`: 5 retries, 30 s × 3, never shorter than Retry-After); retries exhausted → `DispatchFailureListener` records FAILED « Échec après plusieurs tentatives ». Once the provider has answered, the send is never repeated: an outcome that cannot be recorded at once goes to `async` as its own retried `RecordDispatchOutcome` (error log; critical log with the provider id if even that fails). Delivery is at-least-once (a worker dying mid-call). |
| `RecordDispatchOutcome` | command | Re-reads the message under its row lock, marks it SENT with the provider id (or FAILED with the reason) — a message flagged at its account's deletion is masked with this outcome —, then replays the provider events that arrived before that id (`notification__unmatched_delivery_events`). |
| `RecordDeliveryEvent` | command | A provider event (`kind` = a status name or `COMPLAINT`) for a provider message id: applied with the status precedence, or kept as unmatched when no message carries that id yet. Sent by the webhook consumers and the local simulator. |
| `GetSmsUsage` | query | Month (Europe/Paris), quota, used, queued, overage, overage amount (cents HT), cap, percent, cap reached. |
| `PreviewSmsSegments` | query | Segments that `count` SMS of a purpose would consume for a clinic (bulk reminder confirmation); 0 without messaging profile. |
| `GetMessageStatuses` | query | Current status and French label of many messages, by id (invoice history). |
| `MaskPortalRecipient(userId, ?phone, ?email)` | command | An owner account was deleted (ClientPortal): the messages of `MessagePurpose::isPortalAccountMessage()` (`PORTAL_OTP`, `PORTAL_EMAIL_VERIFICATION`, `PORTAL_EMAIL_CHANGED_NOTICE`, `PORTAL_DATA_EXPORT_READY`, `PORTAL_BOOKING_DECLINED`) of that account (`account_id`, set by the requester: e-mail verification codes, e-mail change notices, export notices — whatever address they went to) or to that phone or e-mail lose recipient and bodies like the 24-month purge (`purgeContent()`). Each is re-read under its row lock, so a dispatch worker never makes the erasure fail. One still QUEUED and not claimed by its dispatch whose purpose `isCancelledAtAccountDeletion()` is also `CANCELLED` (« Compte Portal supprimé avant l'envoi »), published like any status, and its dispatch sends nothing; one already claimed is only masked; a queued `PORTAL_EMAIL_CHANGED_NOTICE` keeps its recipient for its dispatch and is masked with its outcome (see *Account deletion* below). The clinics' messages, `PORTAL_INVITATION` included (journaled in the client file), are kept. |
| `PostInAppNotification` | command | One bell entry per user of a clinic (kinds `NEW_APPOINTMENT`, `CRITICAL_STOCK`, `EXPIRING_LOTS`, `SMS_QUOTA`, `ONLINE_BOOKING_PENDING` « RDV en ligne à confirmer », `ONLINE_BOOKING_CHANGED` « RDV modifié / annulé en ligne », `PORTAL_REQUEST` « Demande Portal », all under « Activité »), at most once per user and idempotency key (INSERT IGNORE). |
| `MarkInAppNotificationRead` / `MarkAllInAppNotificationsRead` | command | The user reads one of their entries / all their entries of the current clinic. |
| `WithdrawInAppNotification` | command | Deletes the unread entries posted under a key (e.g. a new appointment cancelled before being seen). |
| `ListInAppNotifications` / `CountUnreadInAppNotifications` | query | The user's entries of the current clinic over the last 30 days (newest first, optional limit) / their unread count. |
| `UpsertClinicMessagingProfile` | command | Clinic pushes the profile of one of its clinics. |
| `RecordVeterinarianCount` | command | Clinic pushes the day's number of active veterinarians (read-only copy used by the quota). |

## Account deletion

ClientPortal's erasure (`DeletePortalAccount`) ends with `MaskPortalRecipient`
for the account (its user id) and its phone and e-mail. What is still waiting for the provider is
cancelled when sending it after the deletion is pointless
(`MessagePurpose::isCancelledAtAccountDeletion()` — not the SMS-cap
`criticality()`):

| Purpose | Queued at deletion | Why |
|---|---|---|
| `PORTAL_OTP`, `PORTAL_EMAIL_VERIFICATION` | cancelled | a one-time code for a disabled account whose challenges are deleted opens nothing |
| `PORTAL_DATA_EXPORT_READY` | cancelled | the export and its archive are deleted by the same erasure |
| `PORTAL_BOOKING_DECLINED` | cancelled | non-critical; the deletion closes the booking requests without message |
| `PORTAL_EMAIL_CHANGED_NOTICE` | sent, then masked | security alert to the *previous* address: after a takeover followed by a deletion it is that address's only warning; flagged at the deletion (`erasure_pending`), it is masked as soon as its outcome (sent or failed) is recorded |
| clinic purposes (`PORTAL_INVITATION`, `PORTAL_REQUEST_RESOLVED`, appointments…) | untouched | the clinic's messages to its client file |

A message the provider already accepted is masked, never cancelled. The
dispatch and the erasure meet on the message's row lock: the dispatch claims
the message (short transaction) before calling the provider, the erasure
re-reads it `FOR UPDATE`. A cancel committed first leaves the claim nothing
to send; a claim committed first turns the cancel into a mask — the message
may be with the provider — and its outcome (provider id, billing) is still
recorded. Should a provider id ever arrive for a `CANCELLED` message, it is
recorded without changing the status (`DispatchClaimRaceTest`).

**Acceptance criterion.** Given a deleted Portal account with messages still
queued to its phone, then non-critical ones are cancelled and never sent, and
every message to that phone has its recipient masked
(`MaskPortalRecipientHandlerTest`,
`AccountDeletionTest::testMessagesStillQueuedToTheDeletedAccountAreNeverSent`).

## Consent table

Consents are applied by the **requesting context**, never here: it reads
Client's `GetClientNotificationContact` through its own port + QueryBus
adapter and passes a resolved recipient (and, for a non-critical SMS, the
optional e-mail fallback). `ClientNotificationContact::channelsFor(kind)`
returns the channels a consent kind allows **and** for which an address
exists; `transactionalChannel()` returns the preferred channel when its
address exists, else e-mail, else SMS, else none. An archived client gets
nothing automated.

| Purpose | Requester | Channel(s) | Consent used |
|---|---|---|---|
| `APPOINTMENT_CONFIRMATION` / `_CHANGE` / `_CANCELLATION` / `_DECLINED` | Scheduling (`SyncAppointmentNotification`) | one: `transactionalChannel()` | none — never blocked by a consent |
| `APPOINTMENT_REMINDER` | Scheduling (`RequestAppointmentReminder`) | SMS only, when `appointment_reminders` contains `sms` and a mobile exists; e-mail fallback (cap reached) only when the same consent also contains `email` and an e-mail exists | `appointment_reminders` (« Rappels de rendez-vous ») |
| `INVOICE_SENT` | Billing (`SendInvoiceEmail`) | e-mail on file, whatever the consents (explicit staff action) | none |
| `INVOICE_REMINDER` | Billing (`SendUnpaidReminder`) | the `billing` consent's reachable channels (SMS and / or e-mail), else `transactionalChannel()`; SMS e-mail fallback only when e-mail is not already a channel | `billing` (« Factures et relances de règlement ») |
| `CONSULTATION_DOCUMENTS` | Consultation (`EmailConsultationDocuments`) | e-mail on file, whatever the consents (explicit staff action) | none |
| `STAFF_INVITATION`, `PASSWORD_RESET` | IdentityAccess | e-mail | none (staff accounts) |
| `PORTAL_OTP` | IdentityAccess (`RequestPortalOtp`, `RequestPortalActionOtp`, `ResendPortalOtp`) | SMS to the phone being verified (`{code, validityMinutes, purpose}`: the wording follows the OTP purpose, `portal_otp_<purpose>.txt.twig` for `email_change` and `account_deletion`); e-mail (`{code, validityMinutes}`) only for a sign-in code resent to the verified address of the account | none |
| `PORTAL_EMAIL_VERIFICATION` | IdentityAccess (`StartPortalEmailChange`) | e-mail, to the address being verified (`{code, validityMinutes}`) | none |
| `PORTAL_EMAIL_CHANGED_NOTICE` | IdentityAccess (`ConfirmPortalEmailChange`) | e-mail, to the previous verified address (`{maskedNewEmail, changedAtLabel}`) | none |
| `PORTAL_INVITATION` | ClientPortal (`SendPortalInvitation`) | the channel the staff chose, SMS or e-mail (`{clinicName, ownerFirstName, link, expiresOn}` on both; the SMS takes two segments, the link being long) | none (explicit staff action) |
| `PORTAL_REQUEST_RESOLVED` | ClientPortal (`ResolvePortalRequest`) | the client's transactional channel (preferred, else e-mail, else SMS), sent by the clinic, **non-critical** (an SMS over the overage cap falls back to e-mail); `{addressee, petName, sentence, staffMessage}` by e-mail, `{sentence, staffMessage}` by SMS — a fixed French sentence (« Votre demande de … pour … a été traitée. » / « … n'a pas pu être acceptée. ») and the team's message, '' when none; key `portal-request-resolved:<requestId>` | none (answers the owner's own request) |
| `PORTAL_DATA_EXPORT_READY` | ClientPortal (`RecordDataExport`) | e-mail only, to the account's **verified** address, when it has one (`{ownerFirstName, expiresAtLabel}`); a notice without link — the archive is downloaded in the app | none (requested by the owner) |
| `PORTAL_BOOKING_DECLINED` | ClientPortal (`DeclineBookingRequest`, the pending-appointment consumers) | SMS only, **sent by Kiveto** to the phone of a new client's booking request (no client file to message yet), **non-critical** (Kiveto's messages are never capped); `{clinicName, slotLabel}` (« jeudi 22/10 à 9h30 », clinic time zone), GSM-7, one segment; key `portal-booking-declined:<requestId>`; erased at account deletion | none (answers the owner's own request) |

## SMS quota

- **Quota** = `notification.sms.segments_per_veterinarian` (400) × the highest
  number of active veterinarians over the quota month, pooled per clinic.
  « Active » = an effective VETERINARY membership whose user account is
  ACTIVE (an invited, never-activated vet does not count). The counts are
  Clinic's (`clinic__veterinarian_daily_counts`), pushed by
  `RecordVeterinarianCount` into the read-only copy
  `notification__veterinarian_counts`; a month without a row uses the last
  known count; the count is never below 1, so the quota never below 400.
- **Month** = Kiveto billing month, always Europe/Paris, whatever the clinic
  time zone (`SmsQuotaPolicy::TIME_ZONE`).
- **Usage** = billed segments of the SMS handed to the provider in the month
  (`sent_at` set), read from the journal — no separate counter.
- **Overage** = segments beyond the quota, counted at
  `notification.sms.overage_unit_price` (`'0.079'` € HT per segment). Alpha:
  counted and shown, never invoiced automatically.
- **Overage cap** = per clinic, default `notification.sms.default_overage_cap`
  (500), set in Paramètres › Notifications (Clinic `MessagingSettings`). A
  non-critical SMS (appointment reminder, unpaid reminder) that would take
  used + queued + its segments beyond quota + cap falls back to e-mail when
  the requester passed a fallback, else is recorded `BLOCKED_CAP`
  (« Bloqué (plafond) »). Critical SMS (appointment confirmation, change,
  cancellation) always go out.
- **Thresholds** `80`, `100` (percent of the quota) and `CAP` (used ≥ quota +
  cap) are announced once per clinic and month
  (`SmsUsageThresholdCrossedIntegrationEvent`); Clinic's
  `SmsUsageThresholdCrossedConsumer` posts a `SMS_QUOTA` bell entry to the
  clinic administrators (« Quota SMS à 80 % », « Quota SMS atteint »,
  « Plafond de dépassement atteint — rappels basculés sur l’e-mail »).
  Paramètres › Notifications shows the usage (`GetSmsUsage`) with the 80 % /
  100 % banners.

## Retention (schedule `notification`, DB-locked, Europe/Paris)

| When | Command | What |
|---|---|---|
| 03:41 daily | `PurgeInAppNotifications` | Bell entries older than 30 days are deleted. |
| 03:47 daily | `PurgeMessageContents` | Messages handed over (or, never handed over, created) more than 24 months ago lose bodies and recipient; `NOTIFICATION`-owned files are deleted, `REQUESTER` files never touched; metadata stays. The run only selects (20 000 at most, index `idx_notif_purge`) and queues `PurgeMessageContentBatch` jobs of 200 on `async`, each its own transaction; the files go with `DeleteMessageFiles`, queued in the batch transaction so they are deleted only once the purge is committed. A batch that keeps failing is picked up the next night. |
| every 61 s | `RematchDeliveryEvents` | Kept webhook events whose provider id is now recorded are applied (race with `RecordDispatchOutcome`), one `ReplayDeliveryEvents` job (`async`) per provider id so that one failure never rolls back the others. 61 s, not 60: the worker's time limit is a multiple of 60. |
| 03:53 daily | `PurgeUnmatchedDeliveryEvents` | Events kept more than 24 h are matched one last time, then dropped and logged (`external_api`). |

## Events

- `OutboundMessageStatusChangedIntegrationEvent` — every status a message takes.
- `SmsUsageThresholdCrossedIntegrationEvent` (clinicId, month, threshold `80` | `100` | `CAP`, used, quota, cap) —
  published by `OnSmsSent` when an SMS handed over makes the billed usage reach a threshold for the first time
  in the quota month (`notification__sms_thresholds_crossed`, INSERT IGNORE: announced once).

## Transports

`NOTIFICATION_DELIVERY` picks the delivery adapters: `disabled` (nothing
leaves, the dispatch records `FAILED` « Envoi désactivé »), `simulator` (dev,
tests: e-mail really goes through `MAILER_DSN` — Mailpit in dev — and the
delivery webhook is faked; SMS never leaves the machine; refused in
production) or `brevo` (real transports and webhooks).
`DeliverySelector` resolves the e-mail and SMS senders when a message is
first dispatched, so the production guard (`LogicException`) holds even if
the variable changes after the build. The simulator schedules
`AdvanceSimulatedDelivery` (`async`, `%notification.simulator.delivery_delay%`
ms, 0 in tests): an address containing `bounce@` bounces, an SMS number
ending in `0000` is rejected, anything else is delivered.

Production:

```
MAILER_DSN=brevo+api://KEY@default
NOTIFIER_SMS_DSN=brevo://KEY@default?sender=Kiveto
NOTIFICATION_DELIVERY=brevo
NOTIFICATION_EMAIL_DOMAIN=mail.kiveto.app
NOTIFICATION_ACCOUNT_SENDER=no-reply@kiveto.app
BREVO_WEBHOOK_TOKEN=<secret>
```

Production refuses the `.env` placeholder `BREVO_WEBHOOK_TOKEN=change-me`
(or an empty one): the webhooks answer 500 until a real secret is set.
With `NOTIFICATION_DELIVERY=brevo`, a channel whose DSN is still `null://`
refuses every message (FAILED « Transport e-mail non configuré » /
« Transport SMS non configuré », error log) instead of recording it SENT.

### Brevo setup (production)

1. **Sending domains** — authenticate in Brevo the sending sub-domain
   `mail.kiveto.app` (clinic e-mails, `<clinic-slug>@mail.kiveto.app`) and
   `kiveto.app` (`no-reply@kiveto.app`, account e-mails): SPF, DKIM and DMARC
   records in DNS, until Brevo shows both domains authenticated.
2. **API key** — create a Brevo API key and put it in `MAILER_DSN` and
   `NOTIFIER_SMS_DSN` (values above); set `NOTIFICATION_DELIVERY=brevo`.
3. **SMS senders** — register the alphanumeric senders with Brevo: « Kiveto »
   and each clinic's sender (Paramètres › Notifications, derived from the
   clinic name by default). Until a clinic's sender is registered, set it to
   « Kiveto ».
4. **Webhooks** — in Brevo, transactional e-mail events →
   `https://hooks.kiveto.app/webhook/brevo_mailer`, SMS events →
   `https://hooks.kiveto.app/webhook/brevo_sms`, with the secret
   `BREVO_WEBHOOK_TOKEN` sent as `Authorization: Bearer <secret>` when Brevo
   allows a header, else appended to the URL as `?token=<secret>`.
   `hooks.kiveto.app` points to the application (`NOTIFICATION_WEBHOOK_HOST`).
5. **IP ranges** — check that Brevo's published webhook ranges still match
   `notification.brevo.webhook_ips` (`config/services.yaml`) and that
   `SYMFONY_TRUSTED_PROXIES` lists the reverse proxies, so the client IP is
   Brevo's.
6. **After deployment** — run `bin/console app:clinic:sync-messaging-profiles`
   (every clinic's messaging profile and today's veterinarian count pushed to
   Notification); the `external-worker` must consume
   `async_external_notification`.

### Development

Default: `NOTIFICATION_DELIVERY=simulator`. E-mails land in Mailpit,
http://localhost:8025 (`docker compose up -d mailer`); SMS stay local and the
delivery events are faked. The workers must run (`make worker-start`):
`messenger-worker` (`async`: debounced appointment syncs, reminders, invoice
and documents e-mails, webhook events, simulator), `external-worker`
(`async_external_notification`: the dispatch) and `scheduler-worker`
(schedules `notification`, `clinic`, `scheduling`, `inventory`). The dev
fixtures (`make reset-db`, `NotificationDemoStory`) seed messages in every
status, a clinic near and one over its SMS quota, bell entries and
invitations.

Reading an SMS in dev (a Portal one-time code, erased from the journal once
sent): in `when@dev` only, `SmsSenderInterface` is decorated by
`DevLoggingSmsSender`, which writes the recipient and the body of every SMS
on the monolog channel `dev_sms` before handing it over. The SMS is sent by
the `external-worker`, so the line lands in that container's
`var/log/dev.log` (`grep dev_sms var/log/dev.log`). The class is excluded from
the `App\` auto-registration: production has neither the service nor the
decoration, and no fixed code exists anywhere.

Real Brevo from a workstation: put in `.env.local`
`MAILER_DSN=brevo+api://KEY@default`,
`NOTIFIER_SMS_DSN=brevo://KEY@default?sender=Kiveto`,
`NOTIFICATION_DELIVERY=brevo` and `BREVO_WEBHOOK_TOKEN=<secret>`, then open
the tunnel below so the delivery statuses come back.

### Brevo webhooks

`POST /webhook/brevo_mailer` (transactional e-mail events) and
`POST /webhook/brevo_sms` (SMS events), served by `symfony/webhook` on any host
(production: `hooks.kiveto.app`, allowed by `NOTIFICATION_WEBHOOK_HOST` in
`framework.trusted_hosts`), behind the stateless `webhook` firewall
(`security: false`). Both parsers are Kiveto's (`Infrastructure/Webhook`):
`BrevoRequestGuard` accepts a call only from Brevo's ranges
(`notification.brevo.webhook_ips`, client IP read through
`SYMFONY_TRUSTED_PROXIES`) carrying `BREVO_WEBHOOK_TOKEN` (`Authorization:
Bearer` header, else `?token=`; masked in the Nginx and Monolog logs). The
e-mail payload goes through the bridge's `BrevoPayloadConverter`, the SMS
payload through `BrevoSmsPayloadConverter`. The parsed event is queued
(`ConsumeRemoteEventMessage` → `async`) and Brevo gets a 200; the worker's
`MailerEventConsumer` / `SmsEventConsumer` dispatch `RecordDeliveryEvent`:

| Brevo e-mail event | Kind | Brevo SMS status | Kind |
|---|---|---|---|
| `request` | SENT | `sent`, `accepted` | SENT |
| `delivered` | DELIVERED | `delivered` | DELIVERED |
| `deferred`, `soft_bounce` | DEFERRED | soft bounce | DEFERRED |
| `hard_bounce` | BOUNCED | hard bounce | BOUNCED |
| `invalid_email`, `blocked`, `error` | REJECTED | `rejected`, `blocked`, `skip` | REJECTED |
| `spam` | COMPLAINT | `error` | FAILED |
| opens, clicks, unsubscriptions | ignored | replies, (un)subscriptions | ignored |

The SMS field names (`messageId`, `msg_status`, `ts_event` else `date`,
`description`, `to`) come from Brevo's documentation and are checked against
a live event at J1; SMS statuses are compared without case or separators
(`hard_bounce` = `hardBounce`).

### Brevo webhooks in dev (tunnel)

Brevo must reach the workstation: run `cloudflared tunnel --url http://localhost:80`,
then configure in Brevo the tunnel URL followed by `/webhook/brevo_mailer`
(transactional e-mail events) and `/webhook/brevo_sms` (SMS events), with the
shared secret `BREVO_WEBHOOK_TOKEN` (the dev `NOTIFICATION_WEBHOOK_HOST` allows
`*.trycloudflare.com`). The tunnel hides Brevo's IP: set
`NOTIFICATION_WEBHOOK_SKIP_IP_CHECK=1` in `.env.local` — it disables the IP
check only (the secret stays mandatory) and is refused when `APP_ENV=prod`.

## Templates

`templates/notification/email/<purpose>.html.twig` (+ `.txt.twig` text part)
and `templates/notification/sms/<purpose>.txt.twig`. `TemplateCatalog`
declares, per purpose and channel, the template and the exact parameter names
(scalars or lists of scalars): a missing or extra parameter is refused at
request time. Clinic e-mails extend `_layout` (clinic card with logo or
initial and city, detail table, button in the clinic brand colour, footer
« Nom · adresse · téléphone »); account e-mails extend `_account_layout`
(« Kiveto » card, « Kiveto · logiciel de gestion vétérinaire » footer). Every
clinic value comes from the clinic messaging profile, never from the request.
CSS is inlined (`inline_css`); the clinic logo is referenced as `cid:logo`
and embedded by the dispatch from the profile's logo storage key.

Console: `app:notification:send-test <clinicId> <email|+33…>` requests a
`TEST` message (« Message de test Kiveto ») for a clinic.

## Consumers

Business contexts request messages (`RequestNotification`) and Client projects
`OutboundMessageStatusChangedIntegrationEvent` into the client communication
journal; Clinic turns `SmsUsageThresholdCrossedIntegrationEvent` into a `SMS_QUOTA`
bell entry for the clinic administrators.
