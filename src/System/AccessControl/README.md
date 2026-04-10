# AccessControl Bounded Context

The **AccessControl** BC is a thin, opaque RBAC engine. It does not own
business concepts — it receives permission data from other BCs via domain
events and exposes a single `isGranted(subject, tenant, permission)` check.

## Responsibilities

- Maintain **role assignments** (`RoleAssignment`) — which subject has which
  role in which tenant. Managed at runtime by event listeners that react to
  Clinic Staff domain events.
- Maintain **role permissions** (`RolePermission`) — which role grants which
  permissions. Static reference data seeded from `StaffRolePermissionMap`.
- Expose `isGranted(SubjectId, TenantId, Permission): bool` via the
  `AccessControlServiceInterface` application port.

## Dependency Direction

```
Clinic BC dispatches domain events  ───►  AccessControl listens and syncs RBAC projection
                                          AccessControl NEVER pulls from Clinic
```

## Architecture

```
src/System/AccessControl/
├── Domain/
│   ├── RoleAssignment.php              (projection entity — subject + tenant + role_key)
│   ├── RolePermission.php              (projection entity — role_key + permission)
│   ├── Repository/
│   │   ├── RoleAssignmentRepositoryInterface.php
│   │   └── RolePermissionRepositoryInterface.php
│   └── ValueObject/
│       ├── SubjectId.php               (opaque UUID)
│       ├── TenantId.php                (opaque UUID)
│       └── Permission.php              (opaque string wrapper)
├── Application/
│   ├── Port/
│   │   └── AccessControlServiceInterface.php  (isGranted)
│   └── Listener/
│       ├── OnClinicMembershipCreated.php
│       ├── OnClinicMembershipDisabled.php
│       ├── OnClinicMembershipEnabled.php
│       └── OnClinicMembershipRoleChanged.php
└── Infrastructure/
    └── Persistence/Doctrine/
        ├── Entity/
        │   ├── RoleAssignmentEntity.php       (table: access_control__role_assignments)
        │   └── RolePermissionEntity.php       (table: access_control__role_permissions)
        ├── Mapper/
        │   ├── RoleAssignmentMapper.php
        │   └── RolePermissionMapper.php
        ├── Repository/
        │   ├── DoctrineRoleAssignmentRepository.php
        │   └── DoctrineRolePermissionRepository.php
        └── DbalAccessControlService.php       (implements isGranted via DBAL join)
```

## Key Design Decisions

- `RoleAssignment` and `RolePermission` are **projection entities**, not
  aggregates. No domain events, no invariants — lifecycle driven by listeners.
- `role_permissions` is **static seed data** from `StaffRolePermissionMap`.
  Listeners only touch `role_assignments` — this avoids race conditions.
- Listeners are sync (`#[AsMessageHandler(bus: 'messenger.bus.event')]`).
  Failure = command rollback. Async + DLQ planned before production.
- `isGranted` is a single DBAL join query — no ORM hydration overhead.

## Database Schema

**Table: `access_control__role_assignments`**

| Column     | Type       | Notes                        |
|------------|------------|------------------------------|
| id         | BINARY(16) | UUID PK                      |
| subject_id | BINARY(16) | Maps to a user               |
| tenant_id  | BINARY(16) | Maps to a clinic             |
| role_key   | VARCHAR(60)| Opaque string (e.g. "VETERINARY") |
| UNIQUE     | (subject_id, tenant_id) |               |

**Table: `access_control__role_permissions`**

| Column     | Type        | Notes                         |
|------------|-------------|-------------------------------|
| id         | BINARY(16)  | UUID PK                       |
| role_key   | VARCHAR(60) | Matches role_assignments      |
| permission | VARCHAR(100)| Opaque string (e.g. "create_prescription") |
| UNIQUE     | (role_key, permission) |                |
