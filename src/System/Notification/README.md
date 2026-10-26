# Notification System Bounded Context

## Purpose

Sends the messages Kiveto writes to people — clients (appointments, invoices,
unpaid reminders, consultation documents) and staff (invitation, password
reset) — by e-mail and SMS, keeps a journal of every outbound message with its
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
| **OutboundMessage** | One e-mail or one SMS to one recipient, with its content frozen at request time, its attachments, the provider message id and its status history. |
| **MessagePurpose** | Why the message is sent: `APPOINTMENT_CONFIRMATION`, `APPOINTMENT_CHANGE`, `APPOINTMENT_CANCELLATION`, `APPOINTMENT_REMINDER`, `INVOICE_SENT`, `INVOICE_REMINDER`, `CONSULTATION_DOCUMENTS`, `STAFF_INVITATION`, `PASSWORD_RESET`, `TEST`. |
| **Criticality** | Fixed per purpose. `NON_CRITICAL` (appointment reminder, unpaid reminder) SMS are subject to the overage cap; everything else is `CRITICAL`. |
| **DeliveryStatus** | `QUEUED` (0) → `SENT` / `DEFERRED` (1) → `DELIVERED` / `BOUNCED` / `REJECTED` / `FAILED` / `BLOCKED_CAP` (2, final). A complaint (spam) is a flag, not a status. |
| **IdempotencyKey** | Opaque string built by the requester (`appointment:<id>:confirmation:1`, `invoice:<id>:send:<dispatchId>`, `invitation:<tokenId>`…), unique across messages. |
| **SenderIdentity** | Clinic purposes: `<Clinic name> <<slug>@mail.kiveto.fr>`, reply-to the letterhead e-mail, SMS sender = clinic short sender (fallback « Kiveto »). Account and test purposes: `Kiveto <no-reply@kiveto.fr>`. |
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
| `RequestNotification` | command | Resolves the sender, renders and freezes the content, applies the SMS cap (non-critical SMS, under the profile lock) with the optional e-mail fallback, saves the message and queues `DispatchOutboundMessage`. Returns the message id. |
| `DispatchOutboundMessage` | external (`async_external_notification`, chosen by `DispatchOutboundMessage::stamps()`: a YAML route would also match the generic `ExternalApiCommandInterface` route) | Hands a QUEUED message to the e-mail or SMS transport (attachments and inline logo read from the file storage, idempotency key sent as Brevo tag) and records the outcome. A 4xx other than 408 / 429 is FAILED at once; 408, 429, 5xx and network errors are retried (`DispatchRetryStrategy`: 5 retries, 30 s × 3, never shorter than Retry-After); retries exhausted → `DispatchFailureListener` records FAILED « Échec après plusieurs tentatives ». Delivery is at-least-once. |
| `RecordDispatchOutcome` | command | Marks the message SENT with the provider id (or FAILED with the reason), then replays the provider events that arrived before that id (`notification__unmatched_delivery_events`). |
| `UpsertClinicMessagingProfile` | command | Clinic pushes the profile of one of its clinics. |
| `RecordVeterinarianCount` | command | Clinic pushes the day's number of active veterinarians (read-only copy used by the quota). |

## Transports

`NOTIFICATION_DELIVERY` picks the delivery adapters: `disabled` (nothing
leaves, the dispatch records `FAILED` « Envoi désactivé »), `simulator` (dev,
tests: e-mail really goes through `MAILER_DSN` — Mailpit in dev — and the
delivery webhook is faked; SMS never leaves the machine; refused in
production) or `brevo` (real transports and webhooks).

Production:

```
MAILER_DSN=brevo+api://KEY@default
NOTIFIER_SMS_DSN=brevo://KEY@default?sender=Kiveto
NOTIFICATION_DELIVERY=brevo
NOTIFICATION_EMAIL_DOMAIN=mail.kiveto.fr
NOTIFICATION_ACCOUNT_SENDER=no-reply@kiveto.fr
BREVO_WEBHOOK_TOKEN=<secret>
```

A developer may put the same values in `.env.local` to test the real Brevo
account. Mailpit (dev): http://localhost:8025 (`docker compose up -d mailer`).

### Brevo webhooks in dev (tunnel)

Brevo must reach the workstation: run `cloudflared tunnel --url http://localhost:80`,
then configure in Brevo the tunnel URL followed by `/webhook/brevo_mailer`
(transactional e-mail events) and `/webhook/brevo_sms` (SMS events), with the
shared secret `BREVO_WEBHOOK_TOKEN`. The tunnel hides Brevo's IP: set
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
journal.
