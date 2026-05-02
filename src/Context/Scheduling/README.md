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
3. Appointments cannot exceed the block's capacity for the time slot.
4. Appointment status transitions are enforced by the domain (no arbitrary jumps).
5. Rescheduling is only allowed on `PLANNED` appointments.

## Application Ports

| Interface | Purpose |
|-----------|---------|
| `MembershipEligibilityCheckerInterface` | Verifies practitioner has an active membership at the clinic (Clinic BC) |
| `ClinicTimezoneResolverInterface` | Resolves clinic timezone from clinic ID for local-time calculations (Clinic BC) |
| `PlanningBlockFinderInterface` | Finds the active planning block covering a given time slot |
| `AppointmentConflictCheckerInterface` | Checks for overlapping appointments |
| `PlanningBlockAppointmentCounterInterface` | Counts appointments in a block for capacity enforcement |

## Cross-BC Notes

All external BC IDs (`ClinicId`, `UserId`, `OwnerId`, `AnimalId`) are encapsulated in BC-local value objects. No Doctrine relations cross BC boundaries.
