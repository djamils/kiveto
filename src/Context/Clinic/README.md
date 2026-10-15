# Clinic Bounded Context

The **Clinic** Bounded Context is the multi-tenant root of the platform. It manages veterinary clinics (tenants), clinic groups, and the staff who work within them.

## Ubiquitous Language

- **Clinic** — a single veterinary practice; the unit of tenancy for all other BCs.
- **ClinicGroup** — optional grouping of multiple clinics under one organisational entity.
- **ClinicMembership** — the formal relationship between a user and a clinic, carrying role, engagement type, validity window, and status.
- **StaffProfile** — professional metadata for a member (titles, registration numbers, agenda display colour, specialties).
- **ClinicMemberRole** — `MANAGER | VETERINARY | VETERINARY_ASSISTANT | RECEPTIONIST`. The clinical job, not the administrative one.
- **Clinic administrator** — a membership carrying the `isClinicAdministrator` flag. It grants `manage_staff` and `manage_billing` on top of whatever the clinical role grants, and is **independent of that role**: the lead veterinarian of an independent practice wears the hat without ceasing to be a veterinarian, and a manager does not wear it by virtue of being a manager. In French UI copy the capability is *la gestion* — never *la gérance*, which names the `MANAGER` role.

## Aggregates

### Clinic
Core tenant aggregate. Carries: slug (unique URL identifier), name, timezone, locale, country code, jurisdiction code, currency code, status (`ACTIVE | SUSPENDED | CLOSED`), optional postal address and contact details. Status transitions are enforced by the domain; a closed clinic cannot be reactivated. `countryCode`, `currencyCode`, and `jurisdictionCode` are write-once at creation (alpha decision).

### ClinicGroup
Lightweight aggregate linking clinics under a shared brand or owner entity.

### Staff Sub-domain (`Domain/Staff/`)
Manages who works at which clinic and in which capacity.

- **ClinicMembership** — aggregate root. Tracks role, engagement type (`PARTNER | EMPLOYEE | FIXED_TERM | CONTRACTOR | REPLACEMENT | INTERN` — the last three end by themselves, `isTemporary()`), work time (percent of a full-time week, 100 by default), validity period, and lifecycle status (`ACTIVE | DISABLED`). Role changes and status transitions raise domain events consumed by the AccessControl BC to sync its RBAC projection.
- **StaffProfile** — aggregate linked to a ClinicMembership. Holds the member's name, phone, agenda colour and visibility, and — for a veterinarian only — the credentials (registration number with the Ordre, title, signature). Every role gets one through `OnboardStaffMember`, because the team page names everyone; `CreateClinicMembership` (back office) still creates a non-practitioner membership without one. Registering a new number or title keeps the signature on file.
- **ClinicPermission** — enum of fine-grained permission slugs (e.g. `create_prescription`, `view_medical_record`).
- **StaffRolePermissionMap** — static map: role → list of permissions, plus `administratorPermissions()` for the hat. Read by AccessControl BC at membership creation to seed role assignments; note that the AccessControl projection is keyed by role and therefore cannot express the per-membership hat.
- **`ClinicMembership::permissions()`** — the single seam where a member's rights are resolved: the union of the role's permissions and, when the hat is worn, the administrator's.

## Business Invariants

- Clinic slug must be globally unique and match `[a-z0-9-]+`.
- A closed clinic cannot be reactivated (terminal status).
- A membership requires a verified user identity (validated via `UserExistenceCheckerInterface`).
- **No write may leave a clinic without an effective administrator.** Stated as a
  no-worsening rule, not as "a clinic always has one": a validity window closes
  on its own, so a clinic can reach zero with no write to refuse. Three writes
  are guarded — revoking the hat, disabling the membership, closing its validity
  window — and each is refused only when *that write* takes the clinic from
  having an effective administrator to having none. A clinic already at zero is
  never blocked, or nobody could repair it. Two ways back: a membership created
  into an administrator-less clinic receives the hat (best-effort — one created
  already out of window is hatted but not effective), and the publisher
  backoffice can always grant it on any existing membership, running outside
  `ClinicPermissionGuard`.

## Cross-BC Dependencies

| Direction | Counterpart | Mechanism |
|-----------|-------------|-----------|
| Produces | AccessControl BC | `ClinicMembershipCreated/Disabled/Enabled/RoleChanged` domain events → AccessControl syncs its RBAC projection |
| Reads | IdentityAccess BC | `UserExistenceCheckerInterface` port (implemented by `IdentityAccessUserExistenceAdapter`) — verifies a user UUID exists before creating a membership |

## Known Debt

- `ClinicController` has multiple public action methods (pre-existing violation of the single-`__invoke` controller rule). Tracked as a separate chore.
