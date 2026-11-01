# Scheduling Bounded Context

The **Scheduling** Bounded Context manages planning blocks and appointments for veterinary clinics. It owns the practitioner agenda and enforces scheduling invariants.

## Ubiquitous Language

- **PlanningBlock** — a time window reserved on a practitioner's agenda for a specific activity type (e.g. surgery, consultation, leave).
- **PlanningBlockType** — enum of activity types: `CONSULTATION | SURGERY | HEALTH_CHECK | EMERGENCY | ON_CALL | LEAVE | TRAINING | ADMIN`.
- **RecurrenceRule** — describes how a planning block repeats: none, daily, weekdays, weekly.
- **Appointment** — a scheduled visit for an animal with a practitioner within a clinic time slot.
- **PractitionerAssignee** — the veterinary professional assigned to an appointment.
- **TimeSlot** — start time (UTC) and duration in minutes defining when an appointment occurs.
- **TimeRange** — a date + start time + end time defining a planning block's active window.
- **AppointmentCategory** — the category of a booking read from its free-text reason (`AppointmentCategory::fromReason`): `urg`, `vaccin`, `suivi`, `bilan`, `chir`, else `consult` (blank reason included), tested in that order (« Contrôle post-stérilisation » is a follow-up). It is the agenda chip and the Portal's motif label.

## Aggregates

### PlanningBlock
Represents a reserved time window in a practitioner's agenda. Carries: clinic ID, staff user ID, type, time range, capacity per hour, recurrence rule, optional note. Capacity is enforced at appointment scheduling time. Blocks of type `LEAVE`, `TRAINING`, or `ADMIN` do not accept appointments and have no capacity limit.

### Appointment
Represents a scheduled patient visit. Carries: clinic ID, practitioner assignee, time slot, reason, notes, linked admission ID (optional), owner ID (optional), animal ID (optional), `source` (`clinic` | `portal`), booking motif ID (optional, a raw id), who cancelled it and why (`cancelledBy` `clinic` | `client`, `cancellationReason` `unavailable` | `no_longer_needed` | `seen_elsewhere` | `other`), `pendingExpiresAt` and `confirmedSilently`.

Status lifecycle:
- `schedule()` → `PLANNED → CANCELLED | NO_SHOW | COMPLETED`;
- `request()` (online booking) → `PENDING_CLINIC_CONFIRMATION → PLANNED` (`confirm(silent)`, `AppointmentConfirmed`) `| DECLINED` (`decline(reason)`, `AppointmentDeclined`: `declined` | `expired` | `clinic_cancelled`) `| CANCELLED` (withdrawn by the client).

Terminal statuses: `CANCELLED`, `DECLINED`, `NO_SHOW`, `COMPLETED`; once terminal, an appointment cannot be modified.

**One source of truth on statuses.** `AppointmentStatus::occupiesSlot()` (PLANNED, PENDING_CLINIC_CONFIRMATION) and `AppointmentStatus::OCCUPYING_SQL_VALUES` decide what holds a slot: overlap, block capacity and the Portal's next appointment use them. The read models (`AppointmentItem`, `OwnerAppointmentItem`, `AnimalAppointmentItem`, `AppointmentDetails`) carry `occupiesSlot`, `isPending` and `isWithdrawn` (CANCELLED or DECLINED), computed here; outside Scheduling no code compares a status string (`tests/Unit/Architecture/AppointmentStatusLiteralTest`). Messages, reminders, the « Nouveau RDV » bell and the notification sync stay on PLANNED: a pending request gets none of them.

**A pending request (D18):**
- the domain refuses `request()` when `pendingExpiresAt` is not after `createdAt`; the caller computes it with `OnlineBookingSettings::pendingExpiry()`;
- a clinic `cancel()` of it is a `decline(clinic_cancelled)` (silent when `notifyClient` is false — death of the animal); a client `cancel()` stays a cancellation (`AppointmentCancelled.wasPending`);
- check-in (`complete()`) and `startService()` confirm it silently first (the client is on site): no confirmation message;
- `markNoShow()` throws `AppointmentNotModifiableException`;
- `reschedule()` takes its new expiry (`RescheduleAppointment` recomputes it from the clinic's `OnlineBookingSettings`); a move that would expire at once is refused.

## Business Rules

1. A practitioner cannot have overlapping appointments (enforced by `AppointmentConflictCheckerInterface`).
2. Appointments cannot be scheduled inside a block that does not accept appointments.
3. Appointments cannot exceed the block's capacity: capacity per hour × length of the block occurrence.
4. Appointment status transitions are enforced by the domain (no arbitrary jumps).
5. Rescheduling (time, practitioner, patient, motif and note) is only allowed on `PLANNED` and `PENDING_CLINIC_CONFIRMATION` appointments.
6. A booking can only be marked no-show once it has started, and never while pending.
7. Overlap and capacity count every status that occupies the slot (`OCCUPYING_SQL_VALUES`): a pending request is busy.
8. Rescheduling in VetApp follows the same SOFT block rule as a new booking (`PlanningBlockGate`): no block = allowed; a block of a refusing type or a full block = refused; the moved booking does not count against its new block.

## Double Booking: the Practitioner-Day Lock (S4d)

Every write that books or moves an appointment, or changes a planning block, first takes a transactional mutex per practitioner and clinic-local date, then checks overlap and capacity with **current reads**.

- **Port:** `PractitionerDayLockInterface::lock(ClinicId $clinicId, list<PractitionerDay> $keys): void` (adapter `DbalPractitionerDayLock`): one row per key in `scheduling__practitioner_day_locks (clinic_id, practitioner_user_id, local_date)`, upserted in key order (`INSERT … ON DUPLICATE KEY UPDATE`), held until the transaction ends. Take it before any other write of the transaction. A `DeadlockException` (MySQL rolled the whole transaction back) reopens the transaction and retries, at most twice, then throws `SlotTakenException` (`contended`). `purgeBefore(localDate)` deletes old rows.
- **Keys:** `LockKeys` (pure, deduplicated, sorted by practitioner then date):
  - `forSlot(UserId, startUtc, endUtc, \DateTimeZone $clinicTz)`: every clinic-local date `[start, end)` touches — 23:30–00:30 locks both days, 23:00–00:00 only the first;
  - `forBlock(UserId, TimeRange, RecurrenceRule, todayLocal)`: a one-off block's date; a recurring block's occurrences from today up to `until`, or 60 days ahead when open-ended;
  - `merge(...$sets)`: the union, e.g. a reschedule locks the days it leaves and the days it takes (P1).
- **Who locks:** `ScheduleAppointment`, `RescheduleAppointment`, `CreatePlanningBlock`, `UpdatePlanningBlock` (old ∪ new staff and dates), `DeletePlanningBlock`, `ConfirmAppointment`, `DeclineAppointment` and `DeclineExpiredPendingAppointment` (through `PendingAppointmentLoader`: plain read for the slot's days, lock, then `findByIdForUpdate`); the online booking commands are to take it the same way. The clinic time zone comes from `ClinicTimezoneResolverInterface`.
- **Current reads under the lock** (`… FOR SHARE`): `AppointmentConflictCheckerInterface::hasOverlapLocked`, `PlanningBlockAppointmentCounterInterface::countOccupyingInWindowLocked(…, ?excludeAppointmentId)`, and `PlanningBlockFinderInterface::findActiveBlockFor(…, ?excludeAppointmentId)` (blocks and their count). Under REPEATABLE READ, a plain read after the lock would still use the snapshot of the transaction's first read and miss a booking committed meanwhile.
- **Capacity:** `BlockCapacity::of(capacityPerHour, windowMinutes)` = `floor(capacityPerHour × window / 60)`, 0 = unlimited; the window is the block's length in local minutes, or the slot's when longer.
- **Refusals:** `SlotTakenException` (overlap, capacity, contended lock), `BlockRefusesAppointmentsException`, `PractitionerNotBookableException` (all `\DomainException`). The VetApp agenda controllers map them to `APPOINTMENT_CONFLICT` / `VALIDATION_FAILED`, with the same French toasts as before.
- **Purge:** the `scheduling` schedule runs `PurgePractitionerDayLocks` on the first of every month at 04:17 (Paris): rows older than 30 days go.

## Application Ports

| Interface | Purpose |
|-----------|---------|
| `MembershipEligibilityCheckerInterface` | Verifies practitioner has an active membership at the clinic (Clinic BC) |
| `ClinicTimezoneResolverInterface` | Resolves clinic timezone from clinic ID for local-time calculations (Clinic BC) |
| `PlanningBlockFinderInterface` | Finds the active planning block covering a given time slot |
| `AppointmentConflictCheckerInterface` | Checks for overlapping appointments |
| `PlanningBlockAppointmentCounterInterface` | Counts appointments in a block for capacity enforcement |
| `PractitionerDayLockInterface` | Transactional per-practitioner-day mutex of every booking and block write (see below) |

## Queries for the Client Portal

- **`GetNextAppointmentForAnimals(clinicId, animalIds, nowUtc)`** → `?NextAppointmentItem{id, animalId, startsAtUtc, practitionerUserId, category}`: the earliest appointment of the clinic that occupies its slot (planned or pending) for one of the given animals with `starts_at_utc >= now`, ties broken by id. An empty animal list returns null without a query; appointments without animal never match. The reason is read only to compute the category; neither the reason nor the notes leave Scheduling.

## Online Booking Settings

What a client can book on the Portal, set in VetApp « Paramètres › Réservation en ligne ». Domain in `Domain/Booking/`; out-of-bounds values throw `InvalidBookingConfigurationException` (`field` + French message, shown as is).

- **`BookingMotif`** (`scheduling__booking_motifs`, practitioners in `scheduling__booking_motif_practitioners`): `label` (1–80), `description` (≤ 500, blank = none), `durationMin` (5–240, multiple of 5), `onlineEnabled`, `confirmationKnownClient` (`auto` default | `manual`), `allowedBlockTypes` (non-empty, only types where `acceptsAppointments()` holds), `practitionerIds` (non-empty, each eligible at the clinic through `MembershipEligibilityCheckerInterface` — checked by `SaveBookingMotif`), optional `preventiveCareKind` (`vaccination|internal_parasite|external_parasite|other`), `newClientsAllowed`, `sortOrder`, `archivedAt`. There is **no new-client confirmation mode**: a new client always waits for the clinic (D15). Archiving hides the motif from the Portal; booked appointments keep it.
- **`OnlineBookingSettings`** (`scheduling__online_booking_settings`, one row per clinic, none = defaults): `enabled` (false), `gridStepMinutes` ∈ {5, 10, 15, 20, 30} (15), `minLeadTimeMinutes` 0–2880 (120), `minLeadTimeManualHours` 3–168 (24), `horizonDays` 1–180 (60), `cancellationWindowHours` 0–168 (24), `newClientBookingEnabled` (false).
  - `pendingExpiry(createdAt, startsAt)` = min(createdAt + 48 h, startsAt − `AUTO_DECLINE_MARGIN` (2 h)). As a pending booking starts at least `minLeadTimeManualHours` (≥ 3 h) after its creation, it never expires at creation.
  - `pendingMinLeadTimeMinutes()` = max(`minLeadTimeMinutes`, `minLeadTimeManualHours` × 60): earliest start of a booking that would be pending.
  - `isOpenForBooking(clinicActive)` = enabled and the clinic active.
- **Commands / queries:** `SaveBookingMotif` (create when `motifId` is null, else update a motif of the clinic; returns the id), `ArchiveBookingMotif`, `UpdateOnlineBookingSettings`; `ListBookingMotifs(clinicId, includeArchived = false)` → `list<BookingMotifView>` by sort order then label; `GetOnlineBookingSettings(clinicId)` → `OnlineBookingSettingsView` (defaults when no row). A motif of another clinic is `BookingMotifNotFoundException`.

## Confirming and Declining a Pending Request (S4e, P2, D18)

- **`ConfirmAppointment(appointmentId, clinicId)`** → PLANNED, then the usual CONFIRMATION. **`DeclineAppointment(appointmentId, clinicId)`** → DECLINED (`declined`), the slot is free, the client gets `APPOINTMENT_DECLINED`. Both lock the slot's practitioner-days, then read the row `FOR UPDATE`; refusals (`\DomainException`, French message shown as is): `AppointmentAlreadyHandledException` « Ce RDV a déjà été traité. » (no longer pending: confirmed, declined, expired, withdrawn — the loser of a race), `PendingAppointmentExpiredException` « Demande expirée. » (confirm at or after `pendingExpiresAt`). An appointment of another clinic or unknown: `\InvalidArgumentException`.
- **Auto-decline:** the `scheduling` schedule runs `ScanExpiredPendingAppointments` every 15 minutes; it queues one `DeclineExpiredPendingAppointment(id)` on `async` per row `status = PENDING AND pending_expires_at <= now`. Each handler locks like a decline and re-checks status and expiry (confirmed or moved later meanwhile: left alone), then declines with reason `expired`. One failure holds back no other.
- **Silent confirmation:** check-in (`CheckInAppointment`, `CompleteAppointment`) confirms a pending request silently and publishes its events (`AppointmentConfirmed{silent: true}`, then `AppointmentCompleted`): the notification state marks the slot as told, without message.
- **Bell:** `AppointmentRequested` queues `PostPendingBookingNotice` (« RDV en ligne à confirmer · <animal> », owner and slot, `ONLINE_BOOKING_PENDING`, key `online-booking-pending:<id>`, agenda on the appointment's day) to the practitioner; Confirmed, Declined and a client cancel of a pending request queue `WithdrawPendingBookingNotice` (unread entry removed). A confirmation posts no « Nouveau RDV ». `PractitionerBellInterface::announceOnlineChange()` posts `ONLINE_BOOKING_CHANGED` to the old and new practitioner (once each) of a client's cancel or move on the Portal.

## Client Messages and Reminders

Scheduling decides which appointment messages a client gets; Notification only sends them.

- **Domain events; integration events only for pending portal bookings (D1, relaxed by S4j).** `ScheduleAppointment`, `RescheduleAppointment` and `CancelAppointment` publish `AppointmentScheduled` (now with `ownerId`, `animalId`, `actorUserId`), `AppointmentRescheduled`, `AppointmentPractitionerAssigneeChanged`, `AppointmentOwnerChanged` (owner set later, corrected or removed) and `AppointmentCancelled` after save; `ScheduleAppointment` and `RescheduleAppointment` take an optional `actorUserId` (the bell names who booked). Only subscribers inside Scheduling react — except three integration events, published by `Infrastructure/Messaging/Consumer/PendingAppointmentIntegrationBridge` (outbox, transaction of the change) **only for `source = portal` appointments that were pending**: `PendingAppointmentConfirmedIntegrationEvent` (also on a silent confirmation), `PendingAppointmentDeclinedIntegrationEvent` (`reason`: `declined` | `expired` | `clinic_cancelled`) and `PendingAppointmentCancelledIntegrationEvent` (withdrawn by the client); payload `{appointmentId, clinicId, ownerId, animalId, startsAtUtc, reason?}`. Why D1 gives way here: ClientPortal (slice 7) must learn that a pending new-client booking was confirmed (to create the link), declined or expired (to send `PORTAL_BOOKING_DECLINED` — the appointment has no owner to message), or acted upon from the agenda. Everything else stays domain-only; the clinic bell of an online cancel or move stays inside Scheduling.
- **Debounced sync (D2).** `ScheduleAppointmentNotificationSync` queues `SyncAppointmentNotification(appointmentId)` on `async`, `scheduling.notification.sync_delay` (120 000 ms) later, for every appointment event. The handler locks the appointment's notification state (`scheduling__appointment_notifications`, `SELECT … FOR UPDATE`, row created first if absent) and compares the appointment with the last state actually notified (`AppointmentNotificationState::nextMessage()`): confirmation (planned, to come, never confirmed), change (another start time or veterinarian — a new duration or motif alone sends nothing), cancellation (cancelled after a confirmation, notified slot still to come), decline (`APPOINTMENT_DECLINED`: a declined request, not silenced, sent once — `decline_notified_at`), or nothing. A pending request is told nothing; one confirmed silently is marked as told (`markConfirmedSilently`) without message. A booking moved within the window gets one confirmation with the final slot; cancelled within the window, nothing; duplicate or concurrent syncs find nothing left to send. Idempotency key `appointment:<id>:<kind>:<n>` (`n` = counter incremented under the lock). **Owner change:** the state records the client told (`notified_owner_id`); when another client (or none) takes a planned booking over, the client who was told gets the cancellation of the slot they knew (without the new animal's name) and the new owner a confirmation — two messages to two clients from one sync, each numbered; a change undone within the window says nothing; a cancellation always goes to the client who was told. A reminder waits until the new owner has been confirmed.
- **One channel.** Confirmation, change and cancellation go on the owner's transactional channel (preferred channel when its address exists, else e-mail, else SMS — never blocked by a consent); an archived or unreachable owner gets nothing (logged). The e-mail carries `rendez-vous.ics` (`IcsWriter`, D4: `METHOD:REQUEST` with a stable `UID` and growing `SEQUENCE`, `METHOD:CANCEL` for a cancellation), stored under `notification/attachments/<sha256 of the key>/` (owner `NOTIFICATION`).
- **Death of the animal (D3).** `CancelAppointment(notifyClient: false)` (sent by the death declaration) silences the state: no « RDV annulé » message.
- **Reminder.** SMS only, one per appointment, ever. `ReminderDelay` per clinic (`scheduling__clinic_settings.reminder_delay`): `H24` (default), `H48` — same local wall-clock time one / two days before, in the clinic time zone, so DST never shifts it — or `SAME_DAY_8` (08:00 local on the day). No reminder when the due time falls less than 30 minutes before the start (an 08:15 appointment with `SAME_DAY_8`) or is already past when planned (booked too late: the confirmation suffices). The sync plans or drops it; the `scheduling` schedule (every 15 min, DB-locked) runs `RequestDueReminders`, which queues one `RequestAppointmentReminder` per due appointment. That handler re-checks under the lock: the due time recomputed from the current start and delay (a move not synced yet only replans it; a pending reminder whose new due time is past goes at once unless the appointment starts within 30 minutes — likewise when the clinic changes the delay), still planned and to come, owner not archived, a mobile, `appointment_reminders` consent containing `sms`. The SMS is non-critical (subject to the SMS cap); its e-mail fallback is passed only when the same consent contains `email` and an e-mail exists. Key `appointment:<id>:reminder`.
- **Paramètres › Agenda (D14).** `ChangeReminderDelay` saves the delay and replans the reminders not yet requested of the clinic's appointments to come; `GetReminderDelay` reads it.
- **Bell « Nouveau RDV » (D13).** `NotifyPractitionerOnAppointmentScheduled` queues `PostNewAppointmentNotice` on `async` when the author is not the veterinarian; the worker posts « Nouveau RDV · <animal> » to the appointment's current veterinarian (key `new-appointment:<id>`), unless cancelled in between. A cancellation queues `WithdrawNewAppointmentNotice` (removes the entry while unread).

| Port | Adapter (`Infrastructure/…`) | Counterpart |
|------|------------------------------|-------------|
| `ClientContactInterface` | `Adapter/Client/QueryBusClientContact` | Client `GetClientNotificationContact` (consent rules applied) |
| `ClinicDirectoryInterface` | `Adapter/Clinic/QueryBusClinicDirectory` | Clinic (time zone, name, address, `GetStaffPrintedName`) |
| `AnimalDirectoryInterface` | `Adapter/Animal/QueryBusAnimalDirectory` | Animal (name) |
| `NotificationRequesterInterface` | `Adapter/Notification/CommandBusNotificationRequester` | Notification `RequestNotification` |
| `PractitionerBellInterface` | `Adapter/Notification/CommandBusPractitionerBell` | Notification `PostInAppNotification` / `WithdrawInAppNotification` |
| `AttachmentStoreInterface` | `Adapter/Notification/FileStorageAttachmentStore` | Shared file storage (`storeAt`) |
| `CalendarInvitationWriterInterface` | `Calendar/IcsWriter` | — |
| `NotificationJobsInterface` | `Messaging/MessengerNotificationJobs` | Messenger `async` |

## Cross-BC Notes

All external BC IDs (`ClinicId`, `UserId`, `OwnerId`, `AnimalId`) are encapsulated in BC-local value objects. No Doctrine relations cross BC boundaries.
