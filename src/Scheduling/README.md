# Scheduling Bounded Context

## Overview

The **Scheduling** Bounded Context manages appointments and waiting room operations for veterinary clinics.

## Ubiquitous Language

- **Appointment**: A scheduled time slot for a client's animal with optional practitioner assignment
- **PractitionerAssignee**: A veterinary professional assigned to an appointment
- **TimeSlot**: Start time (UTC) and duration defining when an appointment occurs
- **WaitingRoomEntry**: Real-time queue entry for patients arriving at the clinic
- **Origin**: Whether an entry came from a scheduled appointment (SCHEDULED) or walk-in (WALK_IN)
- **ArrivalMode**: STANDARD or EMERGENCY (emergency takes priority)
- **Check-in**: The process of creating a waiting room entry from an appointment

## Aggregates

### Appointment
- Manages scheduled visits with optional practitioner assignment
- Status lifecycle: PLANNED → CANCELLED/NO_SHOW/COMPLETED
- Supports rescheduling, practitioner assignment/reassignment/unassignment
- Domain enforces valid time slots and status transitions

### WaitingRoomEntry
- Manages real-time patient queue at the clinic
- Origin: SCHEDULED (linked to appointment) or WALK_IN
- Status lifecycle: WAITING → CALLED → IN_SERVICE → CLOSED
- Supports triage updates (priority, notes, arrival mode)
- Emergency entries have higher priority in queue

## Business Rules

1. **No Overlaps**: A practitioner cannot have overlapping appointments (enforced at application layer)
2. **Terminal Status**: Once an appointment reaches CANCELLED, NO_SHOW, or COMPLETED, it cannot be modified
3. **Unique Check-in**: Only one active waiting room entry per appointment
4. **Status Transitions**: Enforced by domain (e.g., WAITING can go to CALLED or IN_SERVICE)
5. **Origin Constraint**: SCHEDULED entries must have linkedAppointmentId; WALK_IN entries must not

## Integration Points

- **AccessControl BC**: Validates practitioner membership and roles
- **Client BC**: Validates owner existence
- **Animal BC**: Validates animal existence
- **ClinicalCare BC** (future): Consultation start triggers service start in scheduling

## Anti-Corruption Layer

All external BC IDs (UserId, ClinicId, OwnerId, AnimalId) are encapsulated in local Value Objects. The domain is fully autonomous.
