# Clinic Bounded Context

The **Clinic** Bounded Context is the multi-tenant root of the platform. It manages veterinary clinics (tenants), clinic groups, and the staff who work within them.

## Ubiquitous Language

- **Clinic** — a single veterinary practice; the unit of tenancy for all other BCs.
- **ClinicGroup** — optional grouping of multiple clinics under one organisational entity.
- **ClinicMembership** — the formal relationship between a user and a clinic, carrying role, engagement type, validity window, and status.
- **StaffProfile** — professional metadata for a member (titles, registration numbers, agenda display colour, specialties).
- **ClinicMemberRole** — `MANAGER | VETERINARY | VETERINARY_ASSISTANT | RECEPTIONIST`.

## Aggregates

### Clinic
Core tenant aggregate. Carries: slug (unique URL identifier), name, timezone, locale, country code, jurisdiction code, currency code, status (`ACTIVE | SUSPENDED | CLOSED`), optional postal address and contact details. Status transitions are enforced by the domain; a closed clinic cannot be reactivated. `countryCode`, `currencyCode`, and `jurisdictionCode` are write-once at creation (alpha decision).

### ClinicGroup
Lightweight aggregate linking clinics under a shared brand or owner entity.

### Staff Sub-domain (`Domain/Staff/`)
Manages who works at which clinic and in which capacity.

- **ClinicMembership** — aggregate root. Tracks role, engagement type (`EMPLOYED | CONTRACTOR`), validity period, and lifecycle status (`PENDING | ACTIVE | INACTIVE | SUSPENDED`). Role changes and status transitions raise domain events consumed by the AccessControl BC to sync its RBAC projection.
- **StaffProfile** — value object embedded in ClinicMembership. Holds professional titles, national registration numbers (e.g. RPPS, ORDRE), and agenda colour code.
- **ClinicPermission** — enum of fine-grained permission slugs (e.g. `create_prescription`, `view_medical_record`).
- **StaffRolePermissionMap** — static map: role → list of permissions. Read by AccessControl BC at membership creation to seed role assignments.

## Business Invariants

- Clinic slug must be globally unique and match `[a-z0-9-]+`.
- A closed clinic cannot be reactivated (terminal status).
- A membership requires a verified user identity (validated via `UserExistenceCheckerInterface`).

## Cross-BC Dependencies

| Direction | Counterpart | Mechanism |
|-----------|-------------|-----------|
| Produces | AccessControl BC | `ClinicMembershipCreated/Disabled/Enabled/RoleChanged` domain events → AccessControl syncs its RBAC projection |
| Reads | IdentityAccess BC | `UserExistenceCheckerInterface` port (implemented by `IdentityAccessUserExistenceAdapter`) — verifies a user UUID exists before creating a membership |

## Known Debt

- `ClinicController` has multiple public action methods (pre-existing violation of the single-`__invoke` controller rule). Tracked as a separate chore.
