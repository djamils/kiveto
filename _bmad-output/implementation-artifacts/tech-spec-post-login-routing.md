---
title: 'Post-Login Routing Logic (Clinic Firewall)'
slug: 'post-login-routing'
created: '2026-04-10'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3 (attribute mapping)
  - Doctrine Migrations Bundle
files_to_modify:
  - src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php (lines 125-144)
  - src/Context/Clinic/Domain/Staff/ClinicMembership.php (add isDefault field + domain methods)
  - src/Context/Clinic/Domain/Staff/Event/ (new ClinicMembershipDefaultChanged event)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php (add is_default column)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php (map isDefault)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php (add is_default to SQL)
  - src/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/AccessibleClinic.php (add isDefault property)
  - src/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandler.php (default clinic resolution)
  - migrations/Clinic/ (new migration for is_default column)
  - tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php
  - tests/Unit/Context/Clinic/Domain/Staff/ClinicMembershipTest.php
  - tests/Unit/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandlerTest.php
  - tests/Unit/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ActiveClinicResultTest.php (F1 — AccessibleClinic constructor)
  - tests/Unit/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/AccessibleClinicTest.php (F1 — AccessibleClinic constructor)
  - tests/Unit/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/ListClinicsForUserHandlerTest.php (F1 — AccessibleClinic constructor)
  - tests/Unit/Context/Clinic/Application/Command/Staff/DisableClinicMembership/DisableClinicMembershipHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Application/Command/Staff/EnableClinicMembership/EnableClinicMembershipHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipEngagement/ChangeClinicMembershipEngagementHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipValidityWindow/ChangeClinicMembershipValidityWindowHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Application/Query/Staff/GetUserMembershipInClinic/GetUserMembershipInClinicHandlerTest.php (F2 — reconstitute)
  - tests/Unit/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapperTest.php (F2/F6 — reconstitute + symmetry)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php (S3 — save() update branch)
  - tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepositoryTest.php
  - tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php (S5 — write repo round-trip)
  - fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php
code_patterns:
  - ClinicMembership aggregate uses named constructors (create/reconstitute), private __construct, domain events
  - Read repository uses raw DBAL SQL with BIN_TO_UUID, ClockInterface for NOW
  - ContextAuthenticator.onAuthenticationFailure currently differentiates AuthenticationDeniedException subtypes (must unify)
  - AccessibleClinic is a readonly DTO constructed from read repository results
  - ResolveActiveClinicHandler delegates to ListClinicsForUser query via QueryBus
test_patterns:
  - Unit tests mock QueryBusInterface, repositories via createStub/createMock
  - ContextAuthenticatorTest uses inline UrlGeneratorInterface stub, builds AuthenticateUserHandler with real User::reconstitute
  - Integration tests use Foundry factories + KernelTestCase
  - Domain tests instantiate aggregates directly via create()
---

# Tech-Spec: Post-Login Routing Logic (Clinic Firewall)

**Created:** 2026-04-10

## Overview

### Problem Statement

The Clinic firewall post-login flow has two gaps:

1. **Error information leakage:** `ContextAuthenticator.onAuthenticationFailure()` (lines 125-144) returns different error codes and messages depending on the `AuthenticationDeniedException` subtype (`INVALID_CREDENTIALS`, `ACCOUNT_NOT_ALLOWED`, `EMAIL_VERIFICATION_REQUIRED`, `CONTEXT_MISMATCH`). This exposes the failure reason to the client — an attacker can enumerate valid accounts or probe account state.

2. **No default clinic concept:** Users with multiple clinic memberships always land on the clinic selection screen, even if they work primarily at one clinic. There is no `isDefault` field on `ClinicMembership` to express a preferred clinic.

### Solution

1. **Unify error response:** Replace the `AuthenticationDeniedException`-specific branch in `onAuthenticationFailure()` with the generic response. All failures return the same code (`AUTHENTICATION_FAILED`), message (`Authentication failed.`), and HTTP 401 — regardless of the underlying reason.

2. **Add `isDefault` to ClinicMembership:** New boolean field on the domain aggregate, Doctrine entity, and read query. Domain invariant: at most one `isDefault = true` per user (enforced at the command handler level when setting default — out of scope for this spec, but the domain method must support it). `ResolveActiveClinicHandler` uses it: when MULTIPLE clinics are accessible and exactly one has `isDefault = true`, resolve as SINGLE with that clinic.

### Scope

**In Scope:**

- Unify `ContextAuthenticator.onAuthenticationFailure()` to return a single generic error
- Add `isDefault: bool` property to `ClinicMembership` aggregate (create, reconstitute, getter)
- Add `ClinicMembershipDefaultChanged` domain event
- Add `setAsDefault()` and `clearDefault()` domain methods on `ClinicMembership`
- Add `is_default` column to `ClinicMembershipEntity` (ORM attribute)
- Update `ClinicMembershipMapper` (both directions)
- Add `is_default` to read repository SQL + `AccessibleClinic` DTO
- Update `ResolveActiveClinicHandler` to resolve default clinic when multiple memberships
- Migration for `is_default` column (NOT NULL DEFAULT false)
- Update all tests: ContextAuthenticator, ClinicMembership domain, ResolveActiveClinicHandler, read repository integration, mapper
- Update Foundry factory to include `is_default`

**Out of Scope:**

- UI for setting the default clinic (separate ticket)
- Command/handler for `SetDefaultClinic` (will consume the domain method later)
- Portal / Backoffice firewall routing
- Template changes (no-access, select-clinic stay as-is)
- Login form template (already shows generic error from the Stimulus controller)

## Context for Development

### Architectural Notes

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | Remove the `AuthenticationDeniedException` branch entirely, not just change the message | Simpler code, no risk of future leakage if new exception subtypes are added |
| D2 | `isDefault` defaults to `false` on `create()` — no parameter needed | New memberships are never default; setting default is an explicit subsequent action |
| D3 | Domain does NOT enforce uniqueness of `isDefault` per user | The aggregate only knows about itself. Uniqueness is a cross-aggregate concern enforced by the handler that calls `setAsDefault()` (out of scope). The domain method is idempotent. |
| D4 | `ResolveActiveClinicHandler`: if multiple clinics and one is default → resolve as SINGLE | The `ContextAuthenticator` match block doesn't change — it already handles SINGLE correctly by setting clinic context and redirecting to dashboard |
| D5 | `ResolveActiveClinicHandler`: if multiple clinics and default is NOT in the accessible list (e.g., expired membership) → resolve as MULTIPLE | Default clinic must be effective; stale default doesn't shortcut. Automatically satisfied by the read repo's existing filter — no extra logic needed |
| D6 | No new `ActiveClinicResultType` enum case needed | DEFAULT resolves to SINGLE — the authenticator doesn't need to know *why* it's single |

### Adversarial Review Resolutions

| # | Finding | Resolution |
|---|---------|------------|
| F1 | 4 test files constructing `AccessibleClinic` not listed | Added to files_to_modify + new Task 7b for cascade fix |
| F2 | 6 test files calling `reconstitute()` not listed | Added to files_to_modify + new Task 6b for cascade fix |
| F3 | `BOUNDED_CONTEXT = 'clinic'` wrong | Fixed to `'clinic-staff'` in Task 4 |
| F4 | Migration `down()` SQL syntax wrong | Fixed — `DROP is_default` (Doctrine syntax) |
| F5 | `resolveMultipleClinics()` PHPStan type annotation missing | Added `@param AccessibleClinic[]` in Task 9 |
| F6 | Task 14 didn't name mapper test file | Mapper test addressed in Task 6b, Task 14 now explicit for integration repo test only |
| F7 | AC-6 has no test | AC-6 is automatically satisfied by read repo filter — added note in D5. Distinct from no-default test |
| F8 | Two authenticator tests become identical | Task 2 updated: rename one test, verify generic response from domain exception path |
| F9 | No fluent factory method | Dismissed — `create(['isDefault' => true])` is sufficient |
| F10 | Task ordering risk for intermediate `make ci` | All tasks are done before `make ci` (Task 15). Note: Tasks 3→6b and 7→7b must be done atomically |
| S1 | All 6 Task 6b file paths had wrong directory (missing `/Staff/`) | Fixed all paths to include `/Staff/` segment |
| S2 | Task 2 self-contradictory (remove vs rename) | Rewritten: rename test and change assertion, keep both tests |
| S3 | Write repo `save()` UPDATE branch won't persist `isDefault` | Added Task 6c + file to `files_to_modify` |
| S4 | Task 8 missing `\assert()` for `is_default` row value | Added assert instruction following existing pattern |
| S5 | Write repo integration test not listed | Added to Task 14 + `files_to_modify` |
| S6 | Mapper `toDomain` tests need `setIsDefault()` on inline entities | Added explicit instruction in Task 6b |

### Codebase Patterns

- `ClinicMembership` aggregate: private `__construct()`, named constructors `create()` / `reconstitute()`, domain events via `recordDomainEvent()`, getters only (no public properties)
- Domain events carry scalars only — `ClinicMembershipDefaultChanged` takes `membershipId`, `clinicId`, `userId`, `isDefault`
- `ContextAuthenticator` is in `System/IdentityAccess` (not a BC) — tested with unit tests using stubs
- Read repository: raw DBAL SQL, table name resolved from ORM metadata, `ClockInterface` for time
- `AccessibleClinic` DTO: `final readonly class` with public constructor promotion

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php` | Authenticator — `onAuthenticationFailure()` lines 125-144 to simplify |
| `src/Context/Clinic/Domain/Staff/ClinicMembership.php` | Aggregate — add `isDefault` field, `setAsDefault()`, `clearDefault()` |
| `src/Context/Clinic/Domain/Staff/Event/` | Domain events — add `ClinicMembershipDefaultChanged` |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php` | Doctrine entity — add `is_default` column |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php` | Mapper — add `isDefault` mapping in both directions |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php` | Read repo — add `m.is_default` to SELECT (line 42-49) |
| `src/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/AccessibleClinic.php` | DTO — add `bool $isDefault` property |
| `src/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandler.php` | Handler — add default clinic resolution logic |
| `src/Context/Clinic/Application/Port/ClinicMembershipReadRepositoryInterface.php` | Port — no change (returns `AccessibleClinic[]`) |
| `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php` | Factory — add `is_default` default value |
| `tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php` | Test — update `testOnAuthenticationFailureForDomainException` |
| `tests/Unit/Context/Clinic/Domain/Staff/ClinicMembershipTest.php` | Test — add `isDefault` tests |
| `tests/Unit/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandlerTest.php` | Test — add default clinic resolution tests |

### Technical Decisions

1. **`onAuthenticationFailure()` simplification:** Delete lines 127-136 entirely. The remaining lines 138-143 become the only path — all exceptions return `AUTHENTICATION_FAILED` / 401.
2. **`isDefault` on aggregate:** `private bool $isDefault`, initialized to `false` in `create()`, passed as parameter in `reconstitute()`. Getter: `isDefault(): bool`.
3. **`setAsDefault()` method:** Sets `$this->isDefault = true`, records `ClinicMembershipDefaultChanged` event. Idempotent: no-op if already default.
4. **`clearDefault()` method:** Sets `$this->isDefault = false`, records `ClinicMembershipDefaultChanged` event. Idempotent: no-op if already not default.
5. **Migration:** `ALTER TABLE clinic__clinic_memberships ADD is_default TINYINT(1) NOT NULL DEFAULT 0` — all existing rows start as non-default.
6. **`AccessibleClinic` DTO:** Add `public bool $isDefault` as last constructor parameter.
7. **`ResolveActiveClinicHandler` logic change:** After getting the accessible clinics list, if count >= 2, find the one with `isDefault === true`. If exactly one found → `ActiveClinicResult::single($default)`. Otherwise → `ActiveClinicResult::multiple($clinics)`.

## Implementation Plan

### Tasks

- [x] **Task 1: Unify `onAuthenticationFailure()`**
  - File: `src/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticator.php`
  - Action: Remove lines 127-136 (the `AuthenticationDeniedException` branch). Keep only the generic response (current lines 138-143).
  - Result: `onAuthenticationFailure()` becomes a simple method that always returns `AUTHENTICATION_FAILED` / 401.

- [x] **Task 2: Update `ContextAuthenticatorTest`**
  - File: `tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php`
  - Action: Rename `testOnAuthenticationFailureForDomainException()` (line 104) to `testOnAuthenticationFailureReturnsGenericErrorForDomainException`. Keep the test setup (it sends an `AuthenticationDeniedException` wrapped in `AuthenticationException`), but change the assertion from `INVALID_CREDENTIALS` to `AUTHENTICATION_FAILED` and assert HTTP 401. This verifies the branch removal in Task 1 produces a generic response even for domain exceptions. `testOnAuthenticationFailureDefault()` (line 117) stays as-is — it covers the non-domain-exception path.

- [x] **Task 3: Add `isDefault` to `ClinicMembership` aggregate**
  - File: `src/Context/Clinic/Domain/Staff/ClinicMembership.php`
  - Action: Add `private bool $isDefault` property after line 30
  - Action: In `create()` — set `$membership->isDefault = false` (hardcoded, no parameter)
  - Action: In `reconstitute()` — add `bool $isDefault` parameter, set `$membership->isDefault = $isDefault`
  - Action: Add `public function isDefault(): bool` getter
  - Action: Add `public function setAsDefault(): void` — if already true, return. Set `$this->isDefault = true`, record `ClinicMembershipDefaultChanged` event.
  - Action: Add `public function clearDefault(): void` — if already false, return. Set `$this->isDefault = false`, record `ClinicMembershipDefaultChanged` event.

- [x] **Task 4: Create `ClinicMembershipDefaultChanged` domain event**
  - File: `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipDefaultChanged.php`
  - Pattern: Follow `ClinicMembershipRoleChanged` as template. Extends `AbstractDomainEvent`. Properties: `string $membershipId`, `string $clinicId`, `string $userId`, `bool $isDefault`. Constant: `BOUNDED_CONTEXT = 'clinic-staff'` (matches all existing events in this directory).

- [x] **Task 5: Update `ClinicMembershipEntity` (Doctrine)**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php`
  - Action: Add `#[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]` `private bool $isDefault;`
  - Action: Add `getIsDefault(): bool` and `setIsDefault(bool $isDefault): void`

- [x] **Task 6: Update `ClinicMembershipMapper`**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php`
  - Action: In `toDomain()` — add `isDefault: $entity->getIsDefault()` to `reconstitute()` call
  - Action: In `toEntity()` — add `$entity->setIsDefault($membership->isDefault())` after line 42

- [x] **Task 6b: Fix all `reconstitute()` callers (F2 cascade)**
  - These test files call `ClinicMembership::reconstitute()` with named arguments — add `isDefault: false` to each call:
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/DisableClinicMembership/DisableClinicMembershipHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/EnableClinicMembership/EnableClinicMembershipHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipEngagement/ChangeClinicMembershipEngagementHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipValidityWindow/ChangeClinicMembershipValidityWindowHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Query/Staff/GetUserMembershipInClinic/GetUserMembershipInClinicHandlerTest.php`
  - File: `tests/Unit/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapperTest.php` — add `isDefault: false` to both `reconstitute()` calls, add `$entity->setIsDefault(false)` on inline entity construction in `testToDomainReconstitutes*` tests (S6), and add symmetry assertions for `isDefault`

- [x] **Task 6c: Update write repo `save()` UPDATE branch (S3)**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php`
  - Action: In the `save()` method's UPDATE branch (where existing entity fields are copied), add `$entity->setIsDefault($membership->isDefault())` alongside the other field updates. Without this, `setAsDefault()` / `clearDefault()` changes would not be persisted on update.

- [x] **Task 7: Update `AccessibleClinic` DTO**
  - File: `src/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/AccessibleClinic.php`
  - Action: Add `public bool $isDefault` as the last constructor parameter

- [x] **Task 7b: Fix all `AccessibleClinic` constructor calls (F1 cascade)**
  - These files construct `AccessibleClinic` without the new `isDefault` param — add `isDefault: false` to each call:
  - File: `tests/Unit/System/IdentityAccess/Infrastructure/Security/Symfony/ContextAuthenticatorTest.php` — lines 274, 319, 330 (3 calls in `testOnAuthenticationSuccessWithSingleClinic` and `testOnAuthenticationSuccessWithMultipleClinics`)
  - File: `tests/Unit/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ActiveClinicResultTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/AccessibleClinicTest.php`
  - File: `tests/Unit/Context/Clinic/Application/Query/Clinic/ListClinicsForUser/ListClinicsForUserHandlerTest.php`

- [x] **Task 8: Update read repository SQL**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php`
  - Action: Add `m.is_default` to the SELECT clause (after line 49)
  - Action: In the `array_map` callback, add `\assert(\is_int($row['is_default']) || \is_bool($row['is_default']));` before the constructor call, then add `isDefault: (bool) $row['is_default']` to the `AccessibleClinic` constructor (follows existing pattern for PHPStan level max compliance)

- [x] **Task 9: Update `ResolveActiveClinicHandler`**
  - File: `src/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandler.php`
  - Action: In the `default` branch of the match (line 30), replace direct `ActiveClinicResult::multiple(...)` with logic:
    ```php
    default => $this->resolveMultipleClinics($this->filterAccessibleClinics($accessibleClinics)),
    ```
  - Action: Add private method with PHPStan-compliant signature:
    ```php
    /**
     * @param AccessibleClinic[] $clinics
     */
    private function resolveMultipleClinics(array $clinics): ActiveClinicResult
    ```
    - Find clinics where `$clinic->isDefault === true`
    - If exactly one default found → `ActiveClinicResult::single($default)`
    - Otherwise → `ActiveClinicResult::multiple($clinics)`

- [x] **Task 10: Create migration**
  - File: `migrations/Clinic/Version20260411000001.php`
  - Namespace: `DoctrineMigrations\Clinic`
  - SQL up: `ALTER TABLE clinic__clinic_memberships ADD is_default TINYINT(1) NOT NULL DEFAULT 0`
  - SQL down: `ALTER TABLE clinic__clinic_memberships DROP is_default`

- [x] **Task 11: Update Foundry factory**
  - File: `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php`
  - Action: Add `'isDefault' => false` to the `defaults()` method

- [x] **Task 12: Update domain tests**
  - File: `tests/Unit/Context/Clinic/Domain/Staff/ClinicMembershipTest.php`
  - Add tests:
    - `testCreateSetsIsDefaultToFalse` — `create()` returns membership with `isDefault() === false`
    - `testSetAsDefaultRecordsEvent` — call `setAsDefault()`, assert `isDefault() === true` and `ClinicMembershipDefaultChanged` event with `isDefault: true`
    - `testSetAsDefaultIsIdempotent` — call twice, only one event recorded
    - `testClearDefaultRecordsEvent` — call `setAsDefault()` then `clearDefault()`, assert `isDefault() === false` and event with `isDefault: false`
    - `testClearDefaultIsIdempotent` — `clearDefault()` on a non-default membership records no event
    - `testReconstituteWithIsDefaultTrue` — reconstitute with `isDefault: true`, assert getter returns true
    - `testReconstituteWithIsDefaultTrueThenClearDefaultRecordsEvent` — reconstitute with `isDefault: true`, call `clearDefault()`, assert `isDefault() === false` and `ClinicMembershipDefaultChanged` event with `isDefault: false` (distinct from create→set→clear path)

- [x] **Task 13: Update ResolveActiveClinicHandler tests**
  - File: `tests/Unit/Context/Clinic/Application/Query/Clinic/ResolveActiveClinic/ResolveActiveClinicHandlerTest.php`
  - Add tests:
    - `testReturnsDefaultClinicWhenMultipleClinicsAndOneIsDefault` — 3 clinics, one with `isDefault: true` → result type is `SINGLE`, `clinic` is the default one
    - `testReturnsMultipleWhenMultipleClinicsAndNoDefault` — 2 clinics, both `isDefault: false` → result type is `MULTIPLE`
    - `testReturnsMultipleWhenMultipleClinicsAndMultipleDefaults` — edge case: 2 clinics both `isDefault: true` → result type is `MULTIPLE` (safety — shouldn't happen but don't assume)
  - Update: `buildClinic()` helper needs `isDefault` parameter (default `false`)

- [x] **Task 14: Update integration repository tests**
  - File: `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepositoryTest.php`
  - Action: Update existing test to verify `isDefault` is returned correctly from the SQL query
  - File: `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php` (S5 — write repo)
  - Action: Add assertion for `isDefault()` on the reconstituted aggregate in the round-trip test to maintain 100% BC coverage
  - Note: Mapper test file is already addressed in Task 6b.

- [x] **Task 15: Run `make ci`**
  - Action: `rm -rf var/cache/*`, `composer dump-autoload`, `make reset-test-db`, `make ci`
  - All checks must pass.

### Acceptance Criteria

- [x] **AC-1 (Generic error):** Given any login failure (bad credentials, disabled account, unverified email, context mismatch), when the client receives the response, then it contains `{"error":{"code":"AUTHENTICATION_FAILED","message":"Authentication failed."}}` with HTTP 401. No error code or message varies by failure reason.

- [x] **AC-2 (isDefault property):** Given a `ClinicMembership` created via `create()`, when checking `isDefault()`, then it returns `false`. Given a reconstituted membership with `isDefault: true`, when checking `isDefault()`, then it returns `true`.

- [x] **AC-3 (Domain events):** Given a non-default membership, when `setAsDefault()` is called, then `ClinicMembershipDefaultChanged` event is recorded with `isDefault: true`. Given a default membership, when `clearDefault()` is called, then event is recorded with `isDefault: false`. Both methods are idempotent — no event on no-op.

- [x] **AC-4 (Default clinic routing):** Given a user with 3 accessible clinics where exactly one has `isDefault = true`, when login succeeds, then `ResolveActiveClinicHandler` returns `SINGLE` with the default clinic. The `ContextAuthenticator` sets the clinic context and redirects to dashboard.

- [x] **AC-5 (No default fallback):** Given a user with 2+ accessible clinics and none is default, when login succeeds, then `ResolveActiveClinicHandler` returns `MULTIPLE` and user lands on the clinic selection screen.

- [x] **AC-6 (Stale default):** Given a user with a default clinic whose membership has expired (not in accessible list), when login succeeds with other accessible clinics, then the expired default is ignored and `MULTIPLE` is returned.

- [x] **AC-7 (CI green):** `make ci` exits 0 — all checks pass with zero warnings.

## Additional Context

### Dependencies

- No external dependencies. Changes span Clinic BC domain/infra/application layers + System/IdentityAccess authenticator.
- The `SetDefaultClinic` command/handler (to actually set defaults via UI) is a separate future ticket that will consume the `setAsDefault()` / `clearDefault()` domain methods added here. **Critical implementation note for that handler:** it MUST `clearDefault()` on the previously-default membership before calling `setAsDefault()` on the new one. Per-user uniqueness of `isDefault` is enforced at the application level (handler), not in the database — MySQL does not support partial unique indexes. The safety net in `resolveMultipleClinics()` (multiple defaults → MULTIPLE) exists as a fallback but should never be the primary guard.

### Testing Strategy

- **Domain:** Unit tests for `isDefault` property, `setAsDefault()`, `clearDefault()`, idempotency, and event recording
- **Handler:** Unit tests with mocked QueryBus for default resolution logic (default found, no default, multiple defaults edge case)
- **Authenticator:** Update existing unit test to verify generic error response
- **Integration:** Update read repository test to verify `isDefault` column is read correctly
- **Mapper:** Update symmetry test to include `isDefault`
- `make ci` validates everything

### Notes

- **Risk: low.** Two isolated changes: (1) deleting code in the authenticator, (2) additive field on an existing aggregate.
- The 4 `AuthenticationDeniedException` subtypes are: `InvalidCredentialsException`, `AccountStatusNotAllowedException`, `EmailVerificationRequiredException`, `AuthenticationContextMismatchException`. All will now produce the same generic response.
- The `authenticate()` method (line 84-85) wraps `AuthenticationDeniedException` into `CustomUserMessageAuthenticationException` — this passes the original message to Symfony's security layer, but `onAuthenticationFailure()` is what the client sees. After this change, that wrapping is harmless since the failure handler ignores the specific message.
- **Suggested commits:**
  - `fix(identity-access): unify login error response to generic message`
  - `feat(clinic): add isDefault field to ClinicMembership`
  - `feat(clinic): resolve default clinic in post-login routing`
