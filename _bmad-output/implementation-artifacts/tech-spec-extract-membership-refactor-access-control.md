---
title: 'Extract ClinicMembership to Clinic BC and refactor AccessControl into opaque RBAC'
slug: 'extract-membership-refactor-access-control'
created: '2026-04-10'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3 (attribute mapping, single default EM with per-BC mappings)
  - Doctrine Migrations Bundle (one path per BC under migrations/<BC>/)
  - Symfony Messenger (multi-bus CQRS + events sync)
  - Composer PSR-4 (App\ → src/)
  - PHPStan max, PHPUnit, PHPCS, php-cs-fixer
  - Zenstruck Foundry (fixtures)
files_to_modify:
  - src/System/AccessControl/** (gut and rebuild)
  - src/Context/Clinic/Domain/Staff/** (new — membership aggregate + VOs + events)
  - src/Context/Clinic/Application/Command/Staff/** (new — membership commands)
  - src/Context/Clinic/Application/Query/Staff/** (new — ListAllMemberships, GetUserMembershipInClinic)
  - src/Context/Clinic/Application/Query/ResolveActiveClinic/** (moved from AccessControl, NOT under Staff/)
  - src/Context/Clinic/Application/Query/ListClinicsForUser/** (moved from AccessControl, NOT under Staff/)
  - src/Context/Clinic/Application/Port/UserExistenceCheckerInterface.php (new)
  - src/Context/Clinic/Infrastructure/Adapter/IdentityAccess/DbalUserExistenceChecker.php (new)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php (moved)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php (moved)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/Doctrine*Membership*.php (moved)
  - src/System/AccessControl/Domain/RoleAssignment.php (new projection entity)
  - src/System/AccessControl/Domain/RolePermission.php (new projection entity)
  - src/System/AccessControl/Domain/ValueObject/SubjectId.php (new)
  - src/System/AccessControl/Domain/ValueObject/TenantId.php (new)
  - src/System/AccessControl/Domain/ValueObject/Permission.php (new)
  - src/System/AccessControl/Application/Port/AccessControlServiceInterface.php (new — isGranted)
  - src/System/AccessControl/Application/Listener/** (new event listeners)
  - src/System/AccessControl/Infrastructure/Persistence/Doctrine/Entity/RoleAssignmentEntity.php (new)
  - src/System/AccessControl/Infrastructure/Persistence/Doctrine/Entity/RolePermissionEntity.php (new)
  - config/packages/doctrine.yaml (update Clinic mapping, rebuild AccessControl mapping)
  - config/services.yaml (rewire all services)
  - config/packages/security.yaml (update ContextAuthenticator refs if needed)
  - migrations/AccessControl/** (delete old, create new from scratch)
  - migrations/Clinic/** (new migration: create clinic__clinic_memberships)
  - tests/Unit/System/AccessControl/** (rewrite)
  - tests/Unit/Context/Clinic/Domain/Staff/** (new)
  - tests/Unit/Context/Clinic/Application/Command/Staff/** (new)
  - tests/Unit/Context/Clinic/Application/Query/** (moved + new)
  - tests/Integration/** (update)
  - fixtures/System/AccessControl/** (rewrite — RoleAssignment + RolePermission factories + seed story)
  - fixtures/Context/Clinic/** (add membership fixtures, stories via command bus)
  - fixtures/Dataset/ClinicDataset.php (update import of moved DataStory)
  - src/Presentation/** controllers (update imports)
  - src/Presentation/Backoffice/Controller/ClinicMembershipController.php (update enum case references)
  - templates/backoffice/clinic-memberships/index.html.twig (update enum values)
  - templates/backoffice/clinic-memberships/new.html.twig (update enum values)
  - templates/clinic/select-clinic.html.twig (enum values injected into JS)
  - src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php (update imports)
  - tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php (update imports)
  - src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php (update role strings)
  - src/Context/Scheduling/Infrastructure/Adapter/AccessControl/** (rename to Adapter/Clinic/, update table name)
  - src/Context/ClinicalCare/Infrastructure/Adapter/AccessControl/** (rename to Adapter/Clinic/, update table name)
  - src/Context/ClinicalCare/README.md (update table name references)
code_patterns:
  - Per-BC DDD layout: <BC>/{Domain,Application,Infrastructure}
  - Doctrine entities under <BC>/Infrastructure/Persistence/Doctrine/Entity/
  - Table prefix derived from BC name by BoundedContextPrefixNamingStrategy
  - Cross-BC communication via Application Ports + Infrastructure Adapters
  - Domain events extend AbstractDomainEvent, published via DomainEventPublisher → sync EventBus
  - Commands/Queries dispatched via Messenger buses (command, query, event)
  - Sub-domain grouping via Staff/ sub-namespace when a BC has enough commands/queries
test_patterns:
  - tests/Unit/<bucket>/<BC>/... mirrors src/<bucket>/<BC>/...
  - tests/Integration/<bucket>/<BC>/... mirrors src/<bucket>/<BC>/...
  - 100% line coverage per BC except Presentation
  - Zenstruck Foundry for fixtures, PersistentProxyObjectFactory pattern
  - Integration tests for listeners under tests/Integration/System/AccessControl/Application/Listener/
  - DataStories dispatch via command bus for end-to-end coherence
  - StaffRolePermissionMap tested exhaustively (every role → exact permissions, all enum cases covered)
---

# Tech-Spec: Extract ClinicMembership to Clinic BC and refactor AccessControl into opaque RBAC

**Created:** 2026-04-10

## Overview

### Problem Statement

ClinicMembership — the concept of "who works at which clinic, in which
role" — currently lives in `System/AccessControl/`. This is a veterinary
business concept: it belongs in the Clinic BC. AccessControl today has
no identity of its own; it is nothing more than a wrapper around
membership. Meanwhile, there is no explicit permission model — role
checks are implicit, scattered across controllers.

This epic extracts membership into `Context/Clinic/Domain/Staff/`,
introduces an explicit permission enum and a static role→permission
map inside Clinic, then rebuilds AccessControl as a thin, opaque RBAC
engine that receives permission data via domain events and exposes only
an `isGranted(subject, tenant, permission)` service.

### Solution

1. **Move** ClinicMembership (aggregate, VOs, events, commands, queries,
   repos, fixtures, tests) from `System/AccessControl/` to
   `Context/Clinic/Domain/Staff/` (and corresponding Application /
   Infrastructure layers).
2. **Move** `ResolveActiveClinic` and `ListClinicsForUser` queries into
   `Context/Clinic/Application/Query/` (directly, NOT under Staff/).
3. **Move** `ListAllMemberships` and `GetUserMembershipInClinic` into
   `Context/Clinic/Application/Query/Staff/`.
4. **Rename** enum cases: CLINIC_ADMIN→MANAGER,
   ASSISTANT_VETERINARY→VETERINARY_ASSISTANT; add RECEPTIONIST.
   VETERINARY stays unchanged. No data migration — alpha product,
   DB is reset.
5. **Rename** `MembershipId` → `ClinicMembershipId` for consistency
   with the ClinicId / ClinicGroupId naming family.
6. **Create** `ClinicPermission` enum and `StaffRolePermissionMap` in
   Clinic Domain.
7. **Create** `UserExistenceCheckerInterface` port in Clinic +
   `DbalUserExistenceChecker` adapter (replaces direct cross-BC
   import of `UserRepositoryInterface` from IdentityAccess).
8. **Rebuild** `System/AccessControl/` with two new projection entities
   (`RoleAssignment`, `RolePermission`), new tables, event listeners
   that consume Clinic membership events to sync the RBAC projection,
   and an `AccessControlServiceInterface` port exposing `isGranted()`.
9. **Delete** old migration, create new from-scratch migrations for
   both BCs.

### Scope

**In Scope:**

- Full extraction of ClinicMembership to Context/Clinic/Domain/Staff/
- Sub-namespace `Staff/` under Application/Command/ and Application/Query/
  (for membership CRUD queries only — ResolveActiveClinic and
  ListClinicsForUser live directly under Query/)
- All Application-layer commands and queries follow the aggregate
- New ClinicPermission enum + StaffRolePermissionMap in Clinic
- New RoleAssignment + RolePermission projection entities in AccessControl
- Event listeners in AccessControl (OnCreated, OnDisabled, OnEnabled,
  OnRoleChanged) that sync RBAC projection
- AccessControl exposes `isGranted(SubjectId, TenantId, Permission): bool`
  as an Application Port
- Rename ClinicMemberRole enum cases (CLINIC_ADMIN→MANAGER,
  ASSISTANT_VETERINARY→VETERINARY_ASSISTANT, +RECEPTIONIST)
- Rename MembershipId → ClinicMembershipId
- UserExistenceCheckerInterface port + DbalUserExistenceChecker adapter
- Update all cross-BC consumers (Presentation, IdentityAccess, Scheduling,
  ClinicalCare adapters)
- New Doctrine migrations (delete old, create new from scratch)
- Tests + fixtures (stories via command bus), `make ci` green

**Out of Scope:**

- Business logic changes to membership (isEffectiveAt, validity window, etc.)
- UI/UX changes
- Reorganization of Shared/ or Presentation/
- Async transport for events (stays sync — async+DLQ before prod, not this epic)
- Enforcement of isGranted in controllers (future work — this epic
  only provides the infrastructure)

## Context for Development

### Architectural Decisions (from Party Mode reviews)

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Listeners sync, throw allowed | Membership without RBAC = inconsistent state. Async+DLQ before prod. |
| 2 | `OnEnabled` listener added | Exact inverse of disable — recreates RoleAssignment+RolePermission. |
| 3 | `EngagementChanged` / `ValidityChanged` ignored by RBAC | No permission impact. Engagement is Employee/Contractor. Validity is checked at runtime by `isEffectiveAt()`. |
| 4 | `StaffRolePermissionMap` imported directly by listener | Pure static class, no state, no IO. Port = over-engineering for alpha. |
| 5 | Migrations alpha: delete old, rewrite from scratch | Clean history > faithful history on pre-prod product. |
| 6 | `isGranted` = Application Port in AccessControl | Consumed by other BCs via their own infrastructure adapters. |
| 7 | Eligibility checks (membership) → local Clinic (DBAL direct) | Business concept belongs to Clinic, not a permission question. |
| 8 | Permission checks → `isGranted` via AccessControl | Only for "can this user perform this action". |
| 9 | Sub-namespace `Staff/` in Application Command/Query | 15 commands at flat level = unreadable. Lisibility > uniformity. |
| 10 | Reuse existing `Clinic/Domain/ValueObject/ClinicId` + new `Staff/UserId` | No VO duplication. `Staff/UserId` is an opaque UUID in staff context. |
| 11 | `BOUNDED_CONTEXT = 'clinic-staff'` for events | Precise, won't become ambiguous as Clinic BC grows. |
| 12 | Fixtures via command bus (option c) | Coherence guaranteed, free regression net. Alpha speed not a concern. |
| 13 | `MembershipId` → `ClinicMembershipId` | Consistent family: ClinicId, ClinicGroupId, ClinicMembershipId. |
| 14 | `UserExistenceCheckerInterface` port + DBAL adapter | Established project pattern. DBAL query on `identity_access__users`. |
| 15 | `RoleAssignmentEntityFactory` + `RolePermissionEntityFactory` | For isolated integration tests of `isGranted` reader. |
| 16 | `ResolveActiveClinic` + `ListClinicsForUser` under `Query/` directly | User-access queries, not staff management. |
| 17 | `RoleAssignment` / `RolePermission` = projection entities | No invariants, no events, lifecycle driven by listeners. Not aggregates. |
| 18 | `StaffRolePermissionMap` test exhaustive | Every role → exact permissions. All enum cases covered. Security-critical. |
| 19 | `role_permissions` = **static seed**, NOT managed by listeners | Shared by role_key across users. Dynamic delete/create causes destructive race conditions (F3). Seeded once from `StaffRolePermissionMap` via a fixture story. Listeners only touch `role_assignments`. |
| 20 | `BOUNDED_CONTEXT` change `'clinic-access'` → `'clinic-staff'` is an assumed alpha breaking change | No version bump needed — alpha product, no event consumers persist by bounded context. Documented for awareness. |
| 21 | Adapter dirs renamed: `Adapter/AccessControl/` → `Adapter/Clinic/` | Mandatory, not optional. Reflects the data source change. Both Scheduling and ClinicalCare. |
| 22 | Enum rename propagated to Twig templates + Scheduling handler | F1+F2+F10: hardcoded role strings in templates and handler must be updated in the same PR. |

### Dependency Direction Rule

```
Clinic dispatches domain events  ───►  AccessControl listens and syncs RBAC projection
                                       AccessControl NEVER pulls from Clinic
                                       (except: direct import of StaffRolePermissionMap — static, pure, no IO)
```

### Two Distinct Check Responsibilities (post-refactor)

| Question | Answered by | Mechanism |
|----------|------------|-----------|
| "Is this user an active member of this clinic?" | Clinic BC (local) | DBAL query on `clinic__clinic_memberships` |
| "Can this user perform action X in clinic Y?" | AccessControl BC | `isGranted(SubjectId, TenantId, Permission)` on `role_assignments` + `role_permissions` |

### Target Folder Structure (Clinic Staff sub-domain)

```
src/Context/Clinic/
├── Domain/
│   ├── Staff/
│   │   ├── ClinicMembership.php              (aggregate root)
│   │   ├── ClinicPermission.php              (enum — permission slugs)
│   │   ├── StaffRolePermissionMap.php        (static map: role → permissions)
│   │   ├── Event/
│   │   │   ├── ClinicMembershipCreated.php
│   │   │   ├── ClinicMembershipDisabled.php
│   │   │   ├── ClinicMembershipEnabled.php
│   │   │   ├── ClinicMembershipRoleChanged.php
│   │   │   ├── ClinicMembershipEngagementChanged.php
│   │   │   └── ClinicMembershipValidityChanged.php
│   │   ├── Repository/
│   │   │   └── ClinicMembershipRepositoryInterface.php
│   │   └── ValueObject/
│   │       ├── ClinicMembershipId.php        (renamed from MembershipId)
│   │       ├── UserId.php                    (opaque UUID, staff context)
│   │       ├── ClinicMemberRole.php          (MANAGER, VETERINARY, VETERINARY_ASSISTANT, RECEPTIONIST)
│   │       ├── ClinicMembershipEngagement.php
│   │       └── ClinicMembershipStatus.php
│   ├── Clinic.php
│   ├── ClinicGroup.php
│   └── ValueObject/
│       ├── ClinicId.php                      (reused by Staff/ClinicMembership)
│       └── ...
├── Application/
│   ├── Command/
│   │   ├── Staff/
│   │   │   ├── CreateClinicMembership/
│   │   │   ├── ChangeClinicMembershipRole/
│   │   │   ├── ChangeClinicMembershipEngagement/
│   │   │   ├── ChangeClinicMembershipValidityWindow/
│   │   │   ├── EnableClinicMembership/
│   │   │   └── DisableClinicMembership/
│   │   ├── CreateClinic/
│   │   └── ...
│   ├── Query/
│   │   ├── Staff/
│   │   │   ├── ListAllMemberships/
│   │   │   └── GetUserMembershipInClinic/
│   │   ├── ResolveActiveClinic/              (directly under Query/, NOT Staff/)
│   │   ├── ListClinicsForUser/               (directly under Query/, NOT Staff/)
│   │   ├── GetClinic/
│   │   └── ...
│   ├── Port/
│   │   ├── ClinicMembershipReadRepositoryInterface.php
│   │   ├── MembershipAdminRepositoryInterface.php
│   │   ├── UserExistenceCheckerInterface.php  (new — replaces cross-BC import)
│   │   └── ...
│   └── Exception/
│       ├── ClinicMembershipAlreadyExistsException.php
│       └── ...
└── Infrastructure/
    ├── Adapter/
    │   └── IdentityAccess/
    │       └── DbalUserExistenceChecker.php   (new)
    └── Persistence/Doctrine/
        ├── Entity/
        │   ├── ClinicMembershipEntity.php     (table: clinic__clinic_memberships)
        │   ├── ClinicEntity.php
        │   └── ClinicGroupEntity.php
        ├── Mapper/
        │   ├── ClinicMembershipMapper.php
        │   └── ...
        └── Repository/
            ├── DoctrineClinicMembershipRepository.php
            ├── DoctrineClinicMembershipReadRepository.php
            ├── DoctrineMembershipAdminRepository.php
            └── ...
```

### Target Folder Structure (AccessControl — rebuilt)

```
src/System/AccessControl/
├── Domain/
│   ├── RoleAssignment.php                     (projection entity — SubjectId + TenantId + role_key)
│   ├── RolePermission.php                     (projection entity — role_key + Permission)
│   ├── Repository/
│   │   ├── RoleAssignmentRepositoryInterface.php
│   │   └── RolePermissionRepositoryInterface.php
│   └── ValueObject/
│       ├── SubjectId.php                      (opaque UUID)
│       ├── TenantId.php                       (opaque UUID)
│       └── Permission.php                     (opaque string wrapper)
├── Application/
│   ├── Port/
│   │   └── AccessControlServiceInterface.php  (isGranted method)
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
        └── DbalAccessControlService.php       (implements AccessControlServiceInterface)
```

### Database Schema (post-refactor)

**New table: `clinic__clinic_memberships`** (replaces `access_control__clinic_memberships`)

| Column | Type | Notes |
|--------|------|-------|
| id | BINARY(16) PK | UUID |
| clinic_id | BINARY(16) NOT NULL | FK-like, references clinic__clinics |
| user_id | BINARY(16) NOT NULL | FK-like, references identity_access__users |
| role | VARCHAR(40) NOT NULL | MANAGER, VETERINARY, VETERINARY_ASSISTANT, RECEPTIONIST |
| engagement | VARCHAR(20) NOT NULL | EMPLOYEE, CONTRACTOR |
| status | VARCHAR(20) NOT NULL | ACTIVE, DISABLED |
| valid_from_utc | DATETIME NOT NULL | |
| valid_until_utc | DATETIME NULL | |
| created_at_utc | DATETIME NOT NULL | |
| **UNIQUE** | (clinic_id, user_id) | |
| **INDEX** | idx_user_id, idx_clinic_id, idx_status | |

**New table: `access_control__role_assignments`**

| Column | Type | Notes |
|--------|------|-------|
| id | BINARY(16) PK | UUID |
| subject_id | BINARY(16) NOT NULL | Opaque — maps to a user |
| tenant_id | BINARY(16) NOT NULL | Opaque — maps to a clinic |
| role_key | VARCHAR(60) NOT NULL | Opaque string (e.g. "VETERINARY") |
| **UNIQUE** | (subject_id, tenant_id) | One role per subject per tenant |

**New table: `access_control__role_permissions`**

| Column | Type | Notes |
|--------|------|-------|
| id | BINARY(16) PK | UUID |
| role_key | VARCHAR(60) NOT NULL | Matches role_assignments.role_key |
| permission | VARCHAR(100) NOT NULL | Opaque string (e.g. "create_prescription") |
| **UNIQUE** | (role_key, permission) | No duplicates |
| **INDEX** | idx_role_key | |

**Dropped table:** `access_control__clinic_memberships`

### Codebase Patterns

- **Aggregate + AggregateRoot base class.** `ClinicMembership extends
  AggregateRoot` (from Shared). Domain events are recorded via
  `recordDomainEvent()`, pulled after persistence by
  `DomainEventPublisher::publish()`, and dispatched via sync EventBus
  (Messenger `messenger.bus.event`).
- **Command handlers use `#[AsMessageHandler]`** on
  `messenger.bus.command` with `doctrine_transaction` middleware. This
  means the listener sync (on `messenger.bus.event`) runs **inside**
  the command's DB transaction — listener failure = command rollback.
- **Doctrine entities are pure data bags** with getters/setters.
  Mappers (`toDomain()` / `toEntity()`) handle conversion. No domain
  logic in entities.
- **Read repositories use raw DBAL** for cross-BC joins. Existing
  pattern: `DoctrineClinicMembershipReadRepository` hardcodes table
  name `clinic__clinics` for the JOIN. After the move, this becomes a
  local join (membership and clinic are both in Clinic BC) — the
  pattern stays the same but the coupling semantics improve.
- **`DoctrineMembershipAdminRepository`** joins 3 tables
  (`access_control__clinic_memberships`, `clinic__clinics`,
  `identity_access__users`). After the move: the first table becomes
  `clinic__clinic_memberships`. The cross-BC join to
  `identity_access__users` remains (acceptable at infra layer).
- **Cross-BC adapters follow the Port/Adapter pattern.** Example:
  `Scheduling/Infrastructure/Adapter/AccessControl/DbalMembershipEligibilityChecker`
  implements `Scheduling/Application/Port/MembershipEligibilityCheckerInterface`.
  The adapter does a DBAL query — no import of the other BC's domain.
- **Foundry factories extend `PersistentProxyObjectFactory<T>`** with
  fluent builder methods (`withClinicId()`, `asVeterinary()`, etc.).
  DataStories compose factories and other stories.
- **`AbstractDomainEvent`** has `BOUNDED_CONTEXT` (string constant)
  and `VERSION` (int constant). Events carry a payload dict. Metadata
  (eventId, occurredAt, correlationId, causationId, actorId) is
  auto-completed by `MessageMetadataMiddleware`.
- **`BoundedContextPrefixNamingStrategy`** derives the table prefix
  from the 3rd namespace segment after `App\Context\` or
  `App\System\`. Adding `ClinicMembershipEntity` under
  `App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\`
  will produce table prefix `clinic__` — verified by the regex
  `'/^App\\\\(?:Context|System)\\\\([^\\\\]+)\\\\…/'`.
- **Clinic Doctrine mapping** uses `\Entity` suffix in both `dir:`
  and `prefix:` (unlike Client which omits `\Entity` for
  Embeddables). Adding ClinicMembershipEntity under this same
  `Entity/` dir requires no mapping change — it is auto-discovered.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/System/AccessControl/Domain/ClinicMembership.php` | **Source** aggregate to move. 9 properties, 6 operations, `isEffectiveAt()` logic. |
| `src/System/AccessControl/Domain/ValueObject/*.php` | 6 VOs: ClinicId, UserId, MembershipId, ClinicMemberRole, ClinicMembershipEngagement, ClinicMembershipStatus |
| `src/System/AccessControl/Domain/Event/*.php` | 6 domain events: Created, Disabled, Enabled, RoleChanged, EngagementChanged, ValidityChanged |
| `src/System/AccessControl/Domain/Repository/ClinicMembershipRepositoryInterface.php` | 4 methods: save, findById, findByClinicAndUser, existsByClinicAndUser |
| `src/System/AccessControl/Application/Command/**/*.php` | 6 command pairs (command + handler): Create, ChangeRole, ChangeEngagement, ChangeValidityWindow, Enable, Disable |
| `src/System/AccessControl/Application/Query/**/*.php` | 4 query groups: ResolveActiveClinic, ListClinicsForUser, ListAllMemberships, GetUserMembershipInClinic |
| `src/System/AccessControl/Application/Port/*.php` | 2 read ports: ClinicMembershipReadRepositoryInterface, MembershipAdminRepositoryInterface |
| `src/System/AccessControl/Application/Exception/ClinicMembershipAlreadyExistsException.php` | RuntimeException thrown on duplicate membership |
| `src/System/AccessControl/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php` | ORM entity, table `access_control__clinic_memberships`, uniq(clinic_id, user_id) |
| `src/System/AccessControl/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php` | toDomain() / toEntity() |
| `src/System/AccessControl/Infrastructure/Persistence/Doctrine/Repository/*.php` | 3 implementations: domain repo, read repo (cross-BC join), admin repo (3-table join) |
| `src/Context/Clinic/Domain/ValueObject/ClinicId.php` | **Reuse target** — ClinicMembership will import this instead of Staff/ClinicId |
| `src/Context/Clinic/Domain/Clinic.php` | Existing Clinic aggregate — no change, but membership aggregate lives alongside |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | Base class for aggregates (recordDomainEvent, pullDomainEvents) |
| `src/Shared/Domain/Event/AbstractDomainEvent.php` | Base class for events (BOUNDED_CONTEXT, VERSION, payload) |
| `src/Shared/Application/Event/DomainEventPublisher.php` | Publishes events from aggregate after persistence |
| `src/Shared/Domain/Identifier/AbstractUuidId.php` | Base class for UUID value objects |
| `config/packages/doctrine.yaml` | Clinic mapping (lines 37-41), AccessControl mapping (lines 42-47) |
| `config/services.yaml` | AccessControl service definitions (lines 183-192) |
| `config/packages/security.yaml` | ContextAuthenticator references to AccessControl queries |
| `config/packages/messenger.yaml` | Bus definitions + DomainEventInterface → sync routing |
| `migrations/AccessControl/Version20260111224021.php` | **Delete** — creates old table |
| `src/Presentation/Clinic/Controller/SelectClinicController.php` | Consumer: imports ListClinicsForUser, AccessibleClinic |
| `src/Presentation/Backoffice/Controller/ClinicMembershipController.php` | Consumer: imports all AccessControl commands/queries/VOs |
| `src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php` | Consumer: imports ResolveActiveClinic, ActiveClinicResult |
| `src/Context/Scheduling/Infrastructure/Adapter/AccessControl/DbalMembershipEligibilityChecker.php` | Consumer: DBAL on `access_control__clinic_memberships` — table name changes |
| `src/Context/ClinicalCare/Infrastructure/Adapter/AccessControl/DbalPractitionerEligibilityChecker.php` | Consumer: DBAL on `access_control__clinic_memberships` — table name changes |
| `fixtures/System/AccessControl/Factory/ClinicMembershipEntityFactory.php` | **Move** to Clinic fixtures |
| `fixtures/System/AccessControl/Story/ClinicMembershipDataStory.php` | **Rewrite** to dispatch via command bus |

### Technical Decisions

1. **No `git mv` for AccessControl files.** Unlike Epic 1, this is not
   a mechanical rename. The AccessControl BC is gutted and rebuilt from
   scratch. Old files are deleted, new files are written. Only the
   membership-related files are "moved" (conceptually — implemented as
   delete + create in the new location with modified namespaces).
2. **Clinic Doctrine mapping stays unchanged.** The existing mapping
   entry scans `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/`
   with attribute mapping. Adding `ClinicMembershipEntity.php` to that
   directory is auto-discovered — no `doctrine.yaml` edit needed for
   the Clinic mapping itself. The AccessControl mapping entry is
   rewritten to point at the new entities.
3. **`CreateClinicMembershipHandler` anti-corruption layer changes.**
   Currently it imports `ClinicRepositoryInterface` (cross-BC from
   Clinic) and `UserRepositoryInterface` (cross-BC from IdentityAccess).
   After the move: `ClinicRepositoryInterface` is **local** (same BC).
   `UserRepositoryInterface` is replaced by the new
   `UserExistenceCheckerInterface` port + DBAL adapter.
4. **Eligibility checker adapters in Scheduling and ClinicalCare** only
   need their DBAL queries updated to reference
   `clinic__clinic_memberships` instead of
   `access_control__clinic_memberships`. The port interfaces and adapter
   classes stay in their respective BCs — only the SQL table name
   changes. These adapters remain as "membership eligibility" checks
   (business concept), NOT permission checks.
5. **`ClinicMembershipReadRepository` after the move** becomes a
   Clinic-internal read repo. The JOIN to `clinic__clinics` is now a
   local join (same BC). The query logic is unchanged.
6. **Event listeners are `#[AsMessageHandler]` on
   `messenger.bus.event`** (sync). They receive domain events and
   write to the AccessControl persistence layer. They import
   `StaffRolePermissionMap` directly (decision #4 from party mode).
7. **`isGranted` implementation** is a single DBAL query:
   `SELECT 1 FROM access_control__role_assignments ra JOIN
   access_control__role_permissions rp ON ra.role_key = rp.role_key
   WHERE ra.subject_id = ? AND ra.tenant_id = ? AND rp.permission = ?
   LIMIT 1`. Wrapped in `DbalAccessControlService`.

## Implementation Plan

### Tasks

Tasks are ordered by dependency. The whole sequence lands in **a
single PR**. Between Task 2 and Task 10 the autoloader and test suite
are intentionally broken — push only after Task 12 (`make ci`) is
green.

- [ ] **Task 1: Create Clinic Domain Staff layer (new files)**
  - Files (all new under `src/Context/Clinic/Domain/Staff/`):
    - `ValueObject/ClinicMembershipId.php` — rename from `MembershipId`, extend `AbstractUuidId`
    - `ValueObject/UserId.php` — opaque UUID, extend `AbstractUuidId`
    - `ValueObject/ClinicMemberRole.php` — enum: MANAGER, VETERINARY, VETERINARY_ASSISTANT, RECEPTIONIST
    - `ValueObject/ClinicMembershipEngagement.php` — enum: EMPLOYEE, CONTRACTOR (unchanged)
    - `ValueObject/ClinicMembershipStatus.php` — enum: ACTIVE, DISABLED (unchanged)
    - `ClinicMembership.php` — aggregate root, extends `AggregateRoot`. Copy logic from current `System/AccessControl/Domain/ClinicMembership.php`. Replace `MembershipId` → `ClinicMembershipId`. Import `ClinicId` from `Clinic/Domain/ValueObject/ClinicId` (NOT `Staff/ClinicId`). Import `UserId` from `Staff/ValueObject/UserId`.
    - `Repository/ClinicMembershipRepositoryInterface.php` — same 4 methods, new VO types
    - `Event/ClinicMembershipCreated.php` — change `BOUNDED_CONTEXT` to `'clinic-staff'`
    - `Event/ClinicMembershipDisabled.php` — same change
    - `Event/ClinicMembershipEnabled.php` — same change
    - `Event/ClinicMembershipRoleChanged.php` — same change
    - `Event/ClinicMembershipEngagementChanged.php` — same change
    - `Event/ClinicMembershipValidityChanged.php` — same change
    - `ClinicPermission.php` — new enum with permission slugs (e.g. `CREATE_PRESCRIPTION`, `VIEW_MEDICAL_RECORD`, `MANAGE_APPOINTMENTS`, `MANAGE_STAFF`, `VIEW_DASHBOARD`, `MANAGE_BILLING`)
    - `StaffRolePermissionMap.php` — new static class: `public static function permissionsFor(ClinicMemberRole $role): array<ClinicPermission>`. Returns exact permission list per role. Must cover all `ClinicMemberRole::cases()`.
  - Notes: The `ClinicPermission` slugs are placeholder names — the exact list should be defined based on the actions that will eventually be gated by `isGranted`. For this epic, include a sensible starter set that demonstrates the pattern. The map can be enriched later without structural change.

- [ ] **Task 2: Create Clinic Application layer for Staff (new files)**
  - Files (all new):
    - `Application/Port/UserExistenceCheckerInterface.php` — `public function exists(string $userId): bool;`
    - `Application/Port/ClinicMembershipReadRepositoryInterface.php` — move from AccessControl, update imports
    - `Application/Port/MembershipAdminRepositoryInterface.php` — move from AccessControl, update imports
    - `Application/Exception/ClinicMembershipAlreadyExistsException.php` — move from AccessControl
    - `Application/Command/Staff/CreateClinicMembership/CreateClinicMembership.php` — move, update imports
    - `Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php` — move, replace `UserRepositoryInterface` with `UserExistenceCheckerInterface`, replace `ClinicRepositoryInterface` check with local repo (now same BC), update all VO imports to `Staff/ValueObject/...`
    - `Application/Command/Staff/ChangeClinicMembershipRole/` — move both files, update imports
    - `Application/Command/Staff/ChangeClinicMembershipEngagement/` — move both files, update imports
    - `Application/Command/Staff/ChangeClinicMembershipValidityWindow/` — move both files, update imports
    - `Application/Command/Staff/EnableClinicMembership/` — move both files, update imports
    - `Application/Command/Staff/DisableClinicMembership/` — move both files, update imports
    - `Application/Query/Staff/ListAllMemberships/` — move all 4 files (query, handler, MembershipListItem, MembershipCollection), update imports
    - `Application/Query/Staff/GetUserMembershipInClinic/` — move all 3 files (query, handler, MembershipDetails), update imports
    - `Application/Query/ResolveActiveClinic/` — move all 4 files (query, handler, ActiveClinicResult, ActiveClinicResultType), update imports. Handler calls `ListClinicsForUserHandler` which is now in the same BC.
    - `Application/Query/ListClinicsForUser/` — move all 3 files (query, handler, AccessibleClinic), update imports
  - Notes: The `CreateClinicMembershipHandler` currently validates clinic existence via `ClinicRepositoryInterface::findById()`. After the move, this is a local call (same BC) — no port needed, import the repo interface directly.

- [ ] **Task 3: Create Clinic Infrastructure layer for Staff (new files)**
  - Files (all new):
    - `Infrastructure/Adapter/IdentityAccess/DbalUserExistenceChecker.php` — implements `UserExistenceCheckerInterface`. DBAL query: `SELECT 1 FROM identity_access__users WHERE id = ? LIMIT 1`. Uses `Doctrine\DBAL\Connection`.
    - `Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php` — move from AccessControl. Doctrine attributes produce table `clinic__clinic_memberships` via naming strategy. Update enum type references to new VO paths. Columns unchanged except `role` values now include MANAGER, VETERINARY_ASSISTANT, RECEPTIONIST.
    - `Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php` — move, update all imports to new Staff VOs and new ClinicMembershipId.
    - `Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php` — move, update imports. Implements `Staff/Repository/ClinicMembershipRepositoryInterface`.
    - `Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php` — move, update table name in DBAL queries from `access_control__clinic_memberships` to `clinic__clinic_memberships`. The `clinic__clinics` JOIN is now local (same BC).
    - `Infrastructure/Persistence/Doctrine/Repository/DoctrineMembershipAdminRepository.php` — move, update table name. Cross-BC join to `identity_access__users` remains.
  - Notes: The `Clinic` Doctrine mapping in `doctrine.yaml` already scans `Entity/` under `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/` — adding `ClinicMembershipEntity.php` there is auto-discovered, no mapping edit needed.

- [ ] **Task 4: Delete all old AccessControl files**
  - Action: Delete the entire content of `src/System/AccessControl/` (Domain, Application, Infrastructure — every file).
  - Action: Delete all tests under `tests/Unit/System/AccessControl/` and `tests/Integration/System/AccessControl/`.
  - Action: Delete all fixtures under `fixtures/System/AccessControl/`.
  - Notes: This is the "gut" step. The BC directory structure is preserved (empty dirs are fine); only the files are removed. New files are written in Tasks 5–7.

- [ ] **Task 5: Create new AccessControl Domain layer**
  - Files (all new under `src/System/AccessControl/Domain/`):
    - `ValueObject/SubjectId.php` — extend `AbstractUuidId`
    - `ValueObject/TenantId.php` — extend `AbstractUuidId`
    - `ValueObject/Permission.php` — readonly class wrapping a `string $value`. Factory: `fromString(string): self`. Method: `toString(): string`. Not an enum — opaque.
    - `RoleAssignment.php` — projection entity (NOT aggregate). Properties: `SubjectId`, `TenantId`, `string $roleKey`. Factory: `create(SubjectId, TenantId, string $roleKey): self`. No events, no `AggregateRoot`.
    - `RolePermission.php` — projection entity. Properties: `string $roleKey`, `Permission`. Factory: `create(string $roleKey, Permission $permission): self`. No events.
    - `Repository/RoleAssignmentRepositoryInterface.php` — `save(RoleAssignment): void`, `findBySubjectAndTenant(SubjectId, TenantId): ?RoleAssignment`, `deleteBySubjectAndTenant(SubjectId, TenantId): void`
    - `Repository/RolePermissionRepositoryInterface.php` — `saveAll(string $roleKey, Permission ...$permissions): void`, `findByRoleKey(string $roleKey): array`, `deleteAll(): void` (for seed reset)
  - Notes: RoleAssignment and RolePermission have no domain events. They are projection entities, not aggregates. `RolePermission` is **static reference data** seeded from `StaffRolePermissionMap` — NOT managed dynamically by listeners. Only `RoleAssignment` is managed by listeners at runtime. This avoids the destructive race condition where deleting shared `role_key` rows breaks other users (adversarial review F3).

- [ ] **Task 6: Patch `ClinicMembershipEnabled` event to include role**
  - File: `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipEnabled.php`
  - Action: Add `role` (string) to the constructor and payload. The
    `OnEnabled` listener needs the role to create the correct
    `RoleAssignment.roleKey`.
  - File: `src/Context/Clinic/Domain/Staff/ClinicMembership.php`
  - Action: Update `enable()` method to pass `$this->role->value` to
    the `ClinicMembershipEnabled` event constructor.
  - Notes: Must happen BEFORE Task 7 (listeners) because the listener
    depends on the `role` field existing in the event payload.
    (Adversarial review F5.)

- [ ] **Task 7: Create new AccessControl Application layer**
  - Files (all new under `src/System/AccessControl/Application/`):
    - `Port/AccessControlServiceInterface.php` — `public function isGranted(SubjectId $subject, TenantId $tenant, Permission $permission): bool;`
    - `Listener/OnClinicMembershipCreated.php` — `#[AsMessageHandler(bus: 'messenger.bus.event')]`. Receives `ClinicMembershipCreated` event. Extracts userId→SubjectId, clinicId→TenantId, role→roleKey. Creates `RoleAssignment` + saves. **Does NOT touch `role_permissions`** — those are static seed data.
    - `Listener/OnClinicMembershipDisabled.php` — `#[AsMessageHandler(bus: 'messenger.bus.event')]`. Receives `ClinicMembershipDisabled`. Deletes `RoleAssignment` for subject+tenant. **Does NOT touch `role_permissions`.**
    - `Listener/OnClinicMembershipEnabled.php` — `#[AsMessageHandler(bus: 'messenger.bus.event')]`. Receives `ClinicMembershipEnabled` (now includes `role`). Creates `RoleAssignment` with the role from the event payload.
    - `Listener/OnClinicMembershipRoleChanged.php` — `#[AsMessageHandler(bus: 'messenger.bus.event')]`. Receives `ClinicMembershipRoleChanged`. Updates `RoleAssignment.roleKey` to the new role. **Does NOT touch `role_permissions`** — the new role's permissions already exist in the static seed.
  - Notes: All listeners operate exclusively on `role_assignments`.
    The `role_permissions` table is static reference data seeded from
    `StaffRolePermissionMap` (see Task 15). This design avoids the
    destructive race condition where deleting shared `role_key`
    permission rows breaks other users (adversarial review F3+F4).

- [ ] **Task 8: Create new AccessControl Infrastructure layer**
  - Files (all new under `src/System/AccessControl/Infrastructure/`):
    - `Persistence/Doctrine/Entity/RoleAssignmentEntity.php` — ORM entity, table auto-named `access_control__role_assignments`. Columns: id (BINARY16 PK), subject_id (BINARY16), tenant_id (BINARY16), role_key (VARCHAR 60). UniqueConstraint on (subject_id, tenant_id).
    - `Persistence/Doctrine/Entity/RolePermissionEntity.php` — ORM entity, table auto-named `access_control__role_permissions`. Columns: id (BINARY16 PK), role_key (VARCHAR 60), permission (VARCHAR 100). UniqueConstraint on (role_key, permission). Index on role_key.
    - `Persistence/Doctrine/Mapper/RoleAssignmentMapper.php` — toDomain/toEntity
    - `Persistence/Doctrine/Mapper/RolePermissionMapper.php` — toDomain/toEntity
    - `Persistence/Doctrine/Repository/DoctrineRoleAssignmentRepository.php` — implements `RoleAssignmentRepositoryInterface`
    - `Persistence/Doctrine/Repository/DoctrineRolePermissionRepository.php` — implements `RolePermissionRepositoryInterface`
    - `Persistence/Doctrine/DbalAccessControlService.php` — implements `AccessControlServiceInterface`. Single DBAL query: `SELECT 1 FROM access_control__role_assignments ra JOIN access_control__role_permissions rp ON ra.role_key = rp.role_key WHERE ra.subject_id = :subject AND ra.tenant_id = :tenant AND rp.permission = :permission LIMIT 1`. Returns `bool`.
  - Notes: The `AccessControl` Doctrine mapping in `doctrine.yaml` must be updated to point at the new entity dir (same path, but new entities). Since the old entities are deleted and new ones added, the mapping entry stays the same — it still scans `src/System/AccessControl/Infrastructure/Persistence/Doctrine/Entity/`.

- [ ] **Task 9: Update configuration files**
  - File: `config/packages/doctrine.yaml`
    - **No change needed for Clinic mapping** — `ClinicMembershipEntity` is auto-discovered under the existing `Entity/` dir.
    - **No change needed for AccessControl mapping** — the mapping path stays the same, new entities replace old ones.
    - **Verify** both mappings are correct after the file changes.
  - File: `config/services.yaml`
    - Delete all old AccessControl service definitions (lines ~183-192).
    - Add new Clinic Staff service definitions:
      - `App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface` → `DoctrineClinicMembershipRepository`
      - `App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface` → `DoctrineClinicMembershipReadRepository`
      - `App\Context\Clinic\Application\Port\MembershipAdminRepositoryInterface` → `DoctrineMembershipAdminRepository`
      - `App\Context\Clinic\Application\Port\UserExistenceCheckerInterface` → `DbalUserExistenceChecker`
      - `App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMembershipMapper: ~`
    - Add new AccessControl service definitions:
      - `App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface` → `DoctrineRoleAssignmentRepository`
      - `App\System\AccessControl\Domain\Repository\RolePermissionRepositoryInterface` → `DoctrineRolePermissionRepository`
      - `App\System\AccessControl\Application\Port\AccessControlServiceInterface` → `DbalAccessControlService`
      - Mappers: `RoleAssignmentMapper: ~`, `RolePermissionMapper: ~`
  - File: `config/packages/security.yaml`
    - Update `ContextAuthenticator` references: `ResolveActiveClinic`, `ActiveClinicResult`, `ActiveClinicResultType` now live under `App\Context\Clinic\Application\Query\ResolveActiveClinic\`. Update the import in ContextAuthenticator.php (done in Task 10).

- [ ] **Task 10: Migrations**
  - Action: Delete `migrations/AccessControl/Version20260111224021.php`.
  - Action: Create `migrations/AccessControl/Version<timestamp>.php` — creates `access_control__role_assignments` and `access_control__role_permissions` tables per the schema spec. Down method drops both tables.
  - Action: Create `migrations/Clinic/Version<timestamp>.php` — creates `clinic__clinic_memberships` table per the schema spec. Down method drops the table.
  - Notes: The existing Clinic migrations dir may or may not exist. Create it if needed. The migration namespace is `DoctrineMigrations\Clinic` (per `doctrine_migrations.yaml` convention).

- [ ] **Task 11: Update cross-BC consumers**
  - File: `src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php`
    - Update imports: `ResolveActiveClinic`, `ActiveClinicResult`, `ActiveClinicResultType` → `App\Context\Clinic\Application\Query\ResolveActiveClinic\*`
  - File: `tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php`
    - Update imports: `AccessibleClinic`, `ActiveClinicResult`, `ClinicMemberRole`, `ClinicMembershipEngagement` → new Clinic namespaces. (Adversarial review F6.)
  - File: `src/Presentation/Clinic/Controller/SelectClinicController.php`
    - Update imports: `ListClinicsForUser`, `AccessibleClinic` → `App\Context\Clinic\Application\Query\ListClinicsForUser\*`
  - File: `src/Presentation/Backoffice/Controller/ClinicMembershipController.php`
    - Update ALL imports from `App\System\AccessControl\...` to `App\Context\Clinic\...` for commands, queries, VOs.
    - ClinicMemberRole enum cases change: update references to `CLINIC_ADMIN` → `MANAGER`, `ASSISTANT_VETERINARY` → `VETERINARY_ASSISTANT`.
  - File: `templates/backoffice/clinic-memberships/index.html.twig`
    - Update hardcoded enum values: `'CLINIC_ADMIN'` → `'MANAGER'`, `'ASSISTANT_VETERINARY'` → `'VETERINARY_ASSISTANT'`. Add `'RECEPTIONIST'` option. (Adversarial review F2.)
  - File: `templates/backoffice/clinic-memberships/new.html.twig`
    - Same enum value updates as index template.
  - File: `templates/clinic/select-clinic.html.twig`
    - The `clinic.memberRole.value` injected into JS will change automatically (rendered from enum). Verify no JS code depends on old string values. (Adversarial review F10.)
  - File: `src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php`
    - Update hardcoded role strings: `'ASSISTANT_VETERINARY'` → `'VETERINARY_ASSISTANT'`. (Adversarial review F1.)
  - File: `tests/Unit/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandlerTest.php`
    - Update role string assertions to match new enum values.
  - File: `tests/Integration/Context/Scheduling/Infrastructure/Adapter/AccessControl/DbalMembershipEligibilityCheckerTest.php`
    - Update role strings and table name in test setup.
  - File: `src/Context/Scheduling/Infrastructure/Adapter/AccessControl/DbalMembershipEligibilityChecker.php`
    - Update DBAL table name: `access_control__clinic_memberships` → `clinic__clinic_memberships`.
    - **Rename** adapter dir from `Adapter/AccessControl/` to `Adapter/Clinic/`. Update namespace. (Adversarial review F14.)
  - File: `src/Context/ClinicalCare/Infrastructure/Adapter/AccessControl/DbalPractitionerEligibilityChecker.php`
    - Same table name update. Same dir rename to `Adapter/Clinic/`.
  - File: `fixtures/Dataset/ClinicDataset.php`
    - Update import: `App\Fixtures\System\AccessControl\Story\ClinicMembershipDataStory` → `App\Fixtures\Context\Clinic\Story\ClinicMembershipDataStory`. (Adversarial review F7.)
  - File: `config/services.yaml` (lines ~236 and ~261)
    - Update Scheduling and ClinicalCare adapter service definitions to reflect renamed adapter dirs and new namespaces. (Adversarial review F15.)
  - Notes: The port interfaces in Scheduling and ClinicalCare (`MembershipEligibilityCheckerInterface`, `PractitionerEligibilityCheckerInterface`) do NOT change — only adapter implementations change.

- [ ] **Task 12: Tests — Clinic BC Staff domain + application (+ Scheduling role string updates)**
  - Files (all new, mirroring src structure under `tests/Unit/Context/Clinic/`):
    - `Domain/Staff/ClinicMembershipTest.php` — move from old AccessControl tests, update imports, update VO references
    - `Domain/Staff/ValueObject/ClinicMembershipIdTest.php` — rename from MembershipIdTest
    - `Domain/Staff/ValueObject/UserIdTest.php` — move
    - `Domain/Staff/ValueObject/ClinicMemberRoleTest.php` — move, add RECEPTIONIST case, rename CLINIC_ADMIN→MANAGER, ASSISTANT_VETERINARY→VETERINARY_ASSISTANT
    - `Domain/Staff/ValueObject/ClinicMembershipEngagementTest.php` — move
    - `Domain/Staff/ValueObject/ClinicMembershipStatusTest.php` — move
    - `Domain/Staff/Event/*Test.php` — move all 6 event tests, update BOUNDED_CONTEXT assertion to `'clinic-staff'`
    - `Domain/Staff/ClinicPermissionTest.php` — new, test enum cases exist
    - `Domain/Staff/StaffRolePermissionMapTest.php` — new, **exhaustive**: for each `ClinicMemberRole::cases()`, assert exact `ClinicPermission[]` returned. Assert that the map covers ALL enum cases.
    - `Application/Command/Staff/*/` — move all command + handler tests (12 files), update imports
    - `Application/Query/Staff/*/` — move ListAllMemberships + GetUserMembershipInClinic tests, update imports
    - `Application/Query/ResolveActiveClinic/` — move tests, update imports
    - `Application/Query/ListClinicsForUser/` — move tests, update imports
    - `Application/Exception/ClinicMembershipAlreadyExistsExceptionTest.php` — move
  - Files under `tests/Integration/Context/Clinic/`:
    - Move `DoctrineClinicMembershipRepositoryTest.php`, `DoctrineClinicMembershipReadRepositoryTest.php`, `DoctrineMembershipAdminRepositoryTest.php` — update imports and table name references
    - New: `DbalUserExistenceCheckerTest.php` — integration test for the new adapter

- [ ] **Task 13: Tests — AccessControl BC (new)**
  - Files (all new under `tests/Unit/System/AccessControl/`):
    - `Domain/ValueObject/SubjectIdTest.php`
    - `Domain/ValueObject/TenantIdTest.php`
    - `Domain/ValueObject/PermissionTest.php`
    - `Domain/RoleAssignmentTest.php`
    - `Domain/RolePermissionTest.php`
    - `Application/Listener/OnClinicMembershipCreatedTest.php` — unit test: mock repos, verify RoleAssignment + RolePermission created with correct values
    - `Application/Listener/OnClinicMembershipDisabledTest.php` — unit test: verify deleteBySubjectAndTenant called
    - `Application/Listener/OnClinicMembershipEnabledTest.php` — unit test: verify RoleAssignment recreated
    - `Application/Listener/OnClinicMembershipRoleChangedTest.php` — unit test: verify role_key updated, permissions resynced
    - `Infrastructure/Persistence/Doctrine/Entity/RoleAssignmentEntityTest.php`
    - `Infrastructure/Persistence/Doctrine/Entity/RolePermissionEntityTest.php`
    - `Infrastructure/Persistence/Doctrine/Mapper/RoleAssignmentMapperTest.php`
    - `Infrastructure/Persistence/Doctrine/Mapper/RolePermissionMapperTest.php`
    - `Infrastructure/Persistence/Doctrine/DbalAccessControlServiceTest.php` — unit test with mocked connection
  - Files under `tests/Integration/System/AccessControl/`:
    - `Application/Listener/OnClinicMembershipCreatedIntegrationTest.php` — dispatches event, verifies DB rows in role_assignments + role_permissions
    - `Application/Listener/OnClinicMembershipDisabledIntegrationTest.php`
    - `Application/Listener/OnClinicMembershipEnabledIntegrationTest.php`
    - `Application/Listener/OnClinicMembershipRoleChangedIntegrationTest.php`
    - `Infrastructure/Persistence/Doctrine/DbalAccessControlServiceIntegrationTest.php` — verifies isGranted with real DB data

- [ ] **Task 14: Fixtures**
  - Files under `fixtures/Context/Clinic/`:
    - `Factory/ClinicMembershipEntityFactory.php` — move from AccessControl fixtures. Update namespace, entity class reference, builder methods. Update role enum cases (add `asManager()`, `asReceptionist()`, `asVeterinaryAssistant()`; rename `asClinicAdmin()` → `asManager()`, `asAssistantVeterinary()` → `asVeterinaryAssistant()`).
    - `Story/ClinicMembershipDataStory.php` — **rewrite** to dispatch via command bus instead of factory persist. Use `CommandBusInterface::dispatch(new CreateClinicMembership(...))`. This triggers the full flow: handler → event → listener → RBAC sync.
  - Files under `fixtures/System/AccessControl/`:
    - `Factory/RoleAssignmentEntityFactory.php` — new Foundry factory for `RoleAssignmentEntity`. Builder methods: `withSubjectId()`, `withTenantId()`, `withRoleKey()`.
    - `Factory/RolePermissionEntityFactory.php` — new Foundry factory for `RolePermissionEntity`. Builder methods: `withRoleKey()`, `withPermission()`.
    - `Story/RolePermissionSeedStory.php` — **new**. Seeds `role_permissions` table from `StaffRolePermissionMap`. Iterates all `ClinicMemberRole::cases()`, calls `StaffRolePermissionMap::permissionsFor($role)`, and inserts one `RolePermissionEntity` per `(role.value, permission.value)` pair. This story MUST run before `ClinicMembershipDataStory` (which triggers listeners that create `role_assignments` — the listeners assume `role_permissions` already exist for `isGranted` to work).
  - Notes: The `RolePermissionSeedStory` is the **only** source of truth for `role_permissions` data. Listeners never touch this table. The AccessControl factories (`RoleAssignmentEntityFactory`, `RolePermissionEntityFactory`) are for integration tests only (testing `isGranted` reader in isolation without the full event flow).

- [ ] **Task 15: Clear caches, dump autoload, run `make ci`**
  - Action: `rm -rf var/cache/*`, `composer dump-autoload`, `make ci`
  - Notes: Fix any failures. PHPStan max + 100% coverage gates apply.

- [ ] **Task 16: Verify enum rename propagation**
  - Action: Run a grep across the entire repo (excluding vendor/var) for `CLINIC_ADMIN`, `ASSISTANT_VETERINARY`, and `access_control__clinic_memberships`. Zero matches expected. If any found, fix them.
  - Notes: Safety net for adversarial review F1, F2, F10. Pattern: `rg '(CLINIC_ADMIN|ASSISTANT_VETERINARY|access_control__clinic_memberships)' -g '!vendor' -g '!var'`

- [ ] **Task 17: Update READMEs**
  - File: `src/System/AccessControl/README.md` — rewrite to describe the new opaque RBAC model: projection entities, event listeners, `isGranted` service, static seed for `role_permissions`. Document the dependency direction rule.
  - File: `src/Context/Clinic/README.md` — add a "Staff" sub-domain section describing ClinicMembership, ClinicPermission, StaffRolePermissionMap, and how events feed AccessControl.
  - File: `src/Context/ClinicalCare/README.md` — update any references to old table name `access_control__clinic_memberships`. (Adversarial review F9.)

### Acceptance Criteria

- [ ] **AC-1 (CI green):** Given the refactor is complete, when `make ci`
  is executed, then it exits 0 — covering php-cs-fixer, phpcs, phpstan
  (level max), tailwind-build, and the full PHPUnit suite with
  failOnDeprecation, failOnNotice, failOnWarning.

- [ ] **AC-2 (No legacy AccessControl membership code):** Given the
  refactor is complete, when searching for
  `App\System\AccessControl\Domain\ClinicMembership` or
  `App\System\AccessControl\Application\Command` or
  `App\System\AccessControl\Application\Query` across all non-vendor
  files, then zero matches are found. The old membership code is fully
  removed.

- [ ] **AC-3 (Clinic Staff domain exists):** Given the refactor is
  complete, when listing files under
  `src/Context/Clinic/Domain/Staff/`, then the aggregate
  (`ClinicMembership.php`), 6 events, 5 VOs, 1 repo interface,
  `ClinicPermission.php`, and `StaffRolePermissionMap.php` all exist.

- [ ] **AC-4 (ClinicMemberRole enum cases):** Given the refactor is
  complete, when reading `ClinicMemberRole.php`, then it contains
  exactly 4 cases: MANAGER, VETERINARY, VETERINARY_ASSISTANT,
  RECEPTIONIST. No trace of CLINIC_ADMIN or ASSISTANT_VETERINARY.

- [ ] **AC-5 (RBAC projection works end-to-end):** Given a
  ClinicMembership is created via `CreateClinicMembership` command,
  when the command succeeds, then `access_control__role_assignments`
  contains a row with the correct subject_id, tenant_id, and role_key,
  AND `access_control__role_permissions` contains rows mapping that
  role_key to the permissions from `StaffRolePermissionMap`.

- [ ] **AC-6 (Disable purges RBAC):** Given an active ClinicMembership,
  when `DisableClinicMembership` is dispatched, then the corresponding
  `RoleAssignment` row is deleted from
  `access_control__role_assignments`.

- [ ] **AC-7 (Enable recreates RBAC):** Given a disabled
  ClinicMembership, when `EnableClinicMembership` is dispatched, then
  `RoleAssignment` and `RolePermission` rows are recreated.

- [ ] **AC-8 (Role change resyncs RBAC):** Given an active
  ClinicMembership with role VETERINARY, when
  `ChangeClinicMembershipRole` changes it to MANAGER, then
  `role_assignments.role_key` is updated to MANAGER and
  `role_permissions` rows are resynced to MANAGER's permission set.

- [ ] **AC-9 (isGranted works):** Given RBAC data exists for a
  subject+tenant with permission `create_prescription`, when
  `isGranted(subject, tenant, 'create_prescription')` is called, then
  it returns `true`. When called with `'nonexistent_permission'`, it
  returns `false`.

- [ ] **AC-10 (Old table dropped, new tables exist):** Given the
  migrations are applied, when inspecting the database schema, then
  `access_control__clinic_memberships` does NOT exist,
  `clinic__clinic_memberships` EXISTS,
  `access_control__role_assignments` EXISTS,
  `access_control__role_permissions` EXISTS.

- [ ] **AC-11 (Cross-BC consumers updated):** Given the refactor is
  complete, when using the Scheduling or ClinicalCare BCs, then their
  membership eligibility adapters query `clinic__clinic_memberships`
  (not the old table). When the ContextAuthenticator resolves active
  clinic, it uses `App\Context\Clinic\Application\Query\ResolveActiveClinic`.

- [ ] **AC-12 (StaffRolePermissionMap exhaustive test):** Given the
  test suite passes, when inspecting
  `StaffRolePermissionMapTest.php`, then every `ClinicMemberRole`
  case has an explicit assertion mapping it to exact `ClinicPermission`
  values, and a test verifies all enum cases are covered.

- [ ] **AC-13 (Coverage gates pass):** Given the refactor is complete,
  when `make ci` runs coverage, then both the Clinic BC and the
  AccessControl BC maintain 100% line coverage.

- [ ] **AC-14 (Fixtures coherence):** Given the fixtures are loaded
  via `make fixtures` (or equivalent), when inspecting the database,
  then `clinic__clinic_memberships` has rows AND
  `access_control__role_assignments` / `access_control__role_permissions`
  have corresponding rows — proving the command bus story flow works
  end-to-end.

## Additional Context

### Dependencies

- No new external libraries. Pure structural + model refactor.
- **Epic 1 must be merged first** — this epic assumes the
  `src/Context/Clinic/` and `src/System/AccessControl/` layout from
  the Context/System bucket reorganization.
- The `BoundedContextPrefixNamingStrategy` patched in Epic 1 handles
  the `clinic__` prefix for the new membership entity automatically.

### Testing Strategy

- **Unit tests for the Clinic Staff domain** are moved from
  AccessControl, adapted for new namespaces and VO renames. New tests
  added for `ClinicPermission`, `StaffRolePermissionMap` (exhaustive),
  `ClinicMembershipId`.
- **Unit tests for AccessControl** are written from scratch for the
  new projection entities, listeners, VOs, and `DbalAccessControlService`.
- **Integration tests for listeners** verify the full flow: event →
  listener → DB rows. Placed under
  `tests/Integration/System/AccessControl/Application/Listener/`.
- **Integration tests for `isGranted`** verify the DBAL query against
  real DB data.
- **Existing integration tests** (Scheduling, ClinicalCare adapters)
  are updated to use the new table name.
- **Fixtures** dispatch via command bus to validate the full
  command → event → listener → RBAC flow.
- **100% line coverage** enforced per BC. No exceptions.

### Notes

- **This is NOT a mechanical rename like Epic 1.** The AccessControl BC
  is gutted and rebuilt with a fundamentally different model. The Clinic
  BC gains significant new functionality. Expect ~100 new/modified
  files.
- **Risk: listener transaction coupling.** The sync listeners run inside
  the command handler's DB transaction. If a listener fails, the whole
  command rolls back. This is intentional for alpha (consistency >
  availability). Before production, migrate to async events with SQS
  dead letter queue.
- **Risk: `ClinicPermission` enum is a starter set.** The exact
  permission slugs will evolve as controllers adopt `isGranted`. The
  map is designed to be enriched incrementally without structural change.
- **Future work (out of scope):**
  - Enforce `isGranted` in Presentation controllers (replace implicit
    role checks with explicit permission checks)
  - Async event transport with SQS dead letter queue
  - Dynamic permission mapping (database-backed instead of static map)
  - `isGranted` Symfony Voter integration
- **Suggested commit grouping:**
  1. `feat(clinic): add Staff domain layer (aggregate, VOs, events, permissions)` (Task 1)
  2. `feat(clinic): add Staff application + infrastructure layers` (Tasks 2-3)
  3. `refactor(access-control): gut old membership code` (Task 4)
  4. `feat(access-control): rebuild as opaque RBAC with projection entities` (Tasks 5-8, including Task 6 event patch)
  5. `chore(config): rewire doctrine, services, migrations` (Tasks 9-10)
  6. `refactor: update cross-BC consumers, templates, role strings` (Task 11)
  7. `test: add Clinic Staff + AccessControl RBAC test suites` (Tasks 12-13)
  8. `chore(fixtures): rewrite membership fixtures + RBAC seed story` (Task 14)
  9. `chore: verify enum propagation + make ci green` (Tasks 15-16)
  10. `docs: update READMEs for Clinic Staff and AccessControl RBAC` (Task 17)
