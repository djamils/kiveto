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

## Aggregates

### PlanningBlock
Represents a reserved time window in a practitioner's agenda. Carries: clinic ID, staff user ID, type, time range, capacity per hour, recurrence rule, optional note. Capacity is enforced at appointment scheduling time. Blocks of type `LEAVE`, `TRAINING`, or `ADMIN` do not accept appointments and have no capacity limit.

### Appointment
Represents a scheduled patient visit. Carries: clinic ID, practitioner assignee, time slot, reason, notes, linked admission ID (optional), owner ID (optional), animal ID (optional). Status lifecycle: `PLANNED → CANCELLED | NO_SHOW | COMPLETED`. Once in a terminal status, an appointment cannot be modified.

## Business Rules

1. A practitioner cannot have overlapping appointments (enforced by `AppointmentConflictCheckerInterface`).
2. Appointments cannot be scheduled inside a block that does not accept appointments.
3. Appointments cannot exceed the block's capacity: capacity per hour × length of the block occurrence.
4. Appointment status transitions are enforced by the domain (no arbitrary jumps).
5. Rescheduling (time, practitioner, patient, motif and note) is only allowed on `PLANNED` appointments.
6. A booking can only be marked no-show once it has started.

## Application Ports

| Interface | Purpose |
|-----------|---------|
| `MembershipEligibilityCheckerInterface` | Verifies practitioner has an active membership at the clinic (Clinic BC) |
| `ClinicTimezoneResolverInterface` | Resolves clinic timezone from clinic ID for local-time calculations (Clinic BC) |
| `PlanningBlockFinderInterface` | Finds the active planning block covering a given time slot |
| `AppointmentConflictCheckerInterface` | Checks for overlapping appointments |
| `PlanningBlockAppointmentCounterInterface` | Counts appointments in a block for capacity enforcement |

## Client Messages and Reminders

Scheduling decides which appointment messages a client gets; Notification only sends them.

- **Domain events, no integration events (D1).** `ScheduleAppointment`, `RescheduleAppointment` and `CancelAppointment` publish `AppointmentScheduled` (now with `ownerId`, `animalId`, `actorUserId`), `AppointmentRescheduled`, `AppointmentPractitionerAssigneeChanged`, `AppointmentOwnerChanged` (owner set later, corrected or removed) and `AppointmentCancelled` after save; `ScheduleAppointment` and `RescheduleAppointment` take an optional `actorUserId` (the bell names who booked). Only subscribers inside Scheduling react.
- **Debounced sync (D2).** `ScheduleAppointmentNotificationSync` queues `SyncAppointmentNotification(appointmentId)` on `async`, `scheduling.notification.sync_delay` (120 000 ms) later, for every appointment event. The handler locks the appointment's notification state (`scheduling__appointment_notifications`, `SELECT … FOR UPDATE`, row created first if absent) and compares the appointment with the last state actually notified (`AppointmentNotificationState::nextMessage()`): confirmation (planned, to come, never confirmed), change (another start time or veterinarian — a new duration or motif alone sends nothing), cancellation (cancelled after a confirmation, notified slot still to come), or nothing. A booking moved within the window gets one confirmation with the final slot; cancelled within the window, nothing; duplicate or concurrent syncs find nothing left to send. Idempotency key `appointment:<id>:<kind>:<n>` (`n` = counter incremented under the lock). **Owner change:** the state records the client told (`notified_owner_id`); when another client (or none) takes a planned booking over, the client who was told gets the cancellation of the slot they knew (without the new animal's name) and the new owner a confirmation — two messages to two clients from one sync, each numbered; a change undone within the window says nothing; a cancellation always goes to the client who was told. A reminder waits until the new owner has been confirmed.
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
