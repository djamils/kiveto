---
title: 'Set Default Clinic on Selection'
slug: 'set-default-clinic'
created: '2026-04-10'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3 (attribute mapping)
files_to_modify:
  - src/Context/Clinic/Domain/Staff/Repository/ClinicMembershipRepositoryInterface.php (add findDefaultForUser, saveAll)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php (implement findDefaultForUser, saveAll)
  - src/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinic.php (new command)
  - src/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicHandler.php (new handler)
  - src/Presentation/Clinic/Controller/Auth/SubmitSelectClinicController.php (dispatch SetDefaultClinic command)
  - tests/Unit/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicTest.php (new — command DTO)
  - tests/Unit/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicHandlerTest.php (new)
  - tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php (add findDefaultForUser tests)
  - fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php (add asDefault helper)
code_patterns:
  - Command is readonly class implementing CommandInterface with public constructor promotion
  - Handler is final readonly with #[AsMessageHandler], single __invoke() method
  - Handler uses DomainEventPublisher to publish events from aggregates after saveAll
  - Write repository uses Doctrine EntityManager + findOneBy for lookups
  - Controller is single-action __invoke() with CSRF validation
  - EventBusInterface::publish(array $stamps, DomainEventInterface ...$events) — first arg is always []
test_patterns:
  - Handler tests use createStub/createMock for repositories, EventBusInterface
  - DomainEventPublisher is instantiated with mocked EventBusInterface
  - Integration repo tests use Foundry factories + KernelTestCase
  - No existing controller tests for SubmitSelectClinicController (Presentation layer excluded from 100% coverage)
  - EventBusInterface mock assertions must include [] as first argument
---

# Tech-Spec: Set Default Clinic on Selection

**Created:** 2026-04-10

## Overview

### Problem Statement

`SubmitSelectClinicController` only sets the session clinic context (`setCurrentClinicId`) without persisting the choice. On the next login, the user sees the selection screen again instead of being redirected directly to their preferred clinic's dashboard.

The domain foundation is already in place: `ClinicMembership` has `isDefault` field, `setAsDefault()` / `clearDefault()` methods, and `ResolveActiveClinicHandler` resolves a default clinic when one exists. What's missing is the command that wires the controller to the domain.

### Solution

Create a `SetDefaultClinic` command + handler that finds the user's current default membership (if any), calls `clearDefault()` on it, then calls `setAsDefault()` on the selected membership. Update `SubmitSelectClinicController` to dispatch this command before setting the session context and redirecting to the dashboard.

### Scope

**In Scope:**

- `findDefaultForUser(UserId): ?ClinicMembership` on write repository interface + Doctrine implementation (filtered by ACTIVE status)
- `saveAll(ClinicMembership ...$memberships): void` on write repository interface + Doctrine implementation (single flush)
- Command `SetDefaultClinic` + `SetDefaultClinicHandler`
- Update `SubmitSelectClinicController` to dispatch the command
- Unit tests for the handler (4 cases including no-previous-default)
- Unit test for the command DTO
- Integration test for `findDefaultForUser`
- `asDefault()` helper on Foundry factory
- `make ci` green

**Out of Scope:**

- Template/JS changes (UI already says "Définir et accéder" and "clinique par défaut")
- Access to select-clinic from settings (future ticket)
- Changes to `ShowSelectClinicController`
- Changes to `ResolveActiveClinicHandler` (already handles default resolution)
- Controller tests (Presentation layer excluded from 100% BC coverage requirement)

## Context for Development

### Architectural Notes

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | Add `findDefaultForUser(UserId)` to write repository | The handler needs to load the previous default aggregate to mutate it. This is a read-for-write, not a projection — belongs on the write repo. |
| D2 | `findDefaultForUser` filters by `status = ACTIVE` AND `isDefault = true` | A disabled membership that was previously default must not be returned — only active memberships can be effective defaults. |
| D3 | Handler tolerates no existing default | `clearDefault()` is only called if `findDefaultForUser` returns non-null — no error on first-time set |
| D4 | Controller dispatches command OUTSIDE the try/catch | The command dispatch must not be swallowed by the generic `\Throwable` catch. The handler's `\InvalidArgumentException` should propagate as a 500 (it means a programming error — the controller already pre-validated access). |
| D5 | Handler throws if target membership not found | Even though the controller pre-validates access, the handler must self-protect — `findByClinicAndUser` returning null is an `\InvalidArgumentException` |
| D6 | `saveAll()` instead of two `save()` calls | A single `flush()` ensures atomicity — if the second aggregate write fails, the first is rolled back. Two separate `save()` calls would produce two flushes with a partial-write risk. |
| D7 | Events published AFTER `saveAll()`, not between saves | Both aggregates are persisted first, then events are published for both. This eliminates the consistency window where an event subscriber could see a state with no default. |

### Adversarial Review Resolutions

| # | Finding | Resolution |
|---|---------|------------|
| F1 | Command dispatch inside try/catch swallows handler exceptions | Moved dispatch OUTSIDE the try/catch block (D4) |
| F2 | Two flushes are not transactional | Added `saveAll()` method with single flush (D6) |
| F3 | Idempotent test asserts EventBus::publish() called once; actually called zero times | Fixed: `DomainEventPublisher::publish()` returns early on empty events list → `self::never()` |
| F4 | Missing SetDefaultClinicTest.php for command DTO | Added to files_to_modify and Task 3b |
| F6 | Ambiguous dependency branch reference | Clarified: "already merged to master" |
| F7 | findDefaultForUser does not filter by ACTIVE status | Added status filter (D2) |
| F9 | Events published between saves create consistency window | Events published after saveAll (D7) |
| F10 | Foundry factory lacks asDefault() helper | Added Task 7b for factory helper |
| F11 | EventBusInterface::publish() first arg [] omitted in test assertions | Added explicit note in test patterns and Task 6 |

### Codebase Patterns

- **Command pattern:** `final readonly class` implementing `CommandInterface`, public constructor promotion with scalar/VO params
- **Handler pattern:** `final readonly class`, `#[AsMessageHandler]`, dependencies via constructor DI, single `__invoke()` method
- **Event publishing:** Handler calls `$this->domainEventPublisher->publish($aggregate)` after persistence — publisher calls `pullDomainEvents()` and dispatches via `EventBusInterface::publish([], ...$events)`. If no events recorded, publisher returns early without calling EventBusInterface.
- **Repository findOneBy pattern:** `$repository->findOneBy([...])` returns `?Entity`, mapped to domain via `$this->mapper->toDomain($entity)`
- **Controller CSRF:** Manual `CsrfTokenManagerInterface::isTokenValid()` check, throws `AccessDeniedException`

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Clinic/Domain/Staff/Repository/ClinicMembershipRepositoryInterface.php` | Write repo interface — add `findDefaultForUser`, `saveAll` |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php` | Doctrine implementation — implement `findDefaultForUser`, `saveAll` |
| `src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembership.php` | Command pattern reference |
| `src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php` | Handler pattern reference (DI, save, publish) |
| `src/Presentation/Clinic/Controller/Auth/SubmitSelectClinicController.php` | Controller to update — add CommandBus dispatch |
| `src/Context/Clinic/Domain/Staff/ClinicMembership.php` | Domain aggregate with `setAsDefault()`, `clearDefault()`, `isDefault()` |
| `src/Shared/Application/Event/DomainEventPublisher.php` | Event publisher — `publish(AggregateRoot)` pulls and dispatches events. Returns early if no events. |
| `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php` | Foundry factory — add `asDefault()` helper |

### Technical Decisions

1. **`findDefaultForUser` implementation:** `findOneBy(['userId' => ..., 'isDefault' => true, 'status' => ClinicMembershipStatus::ACTIVE])` on the Doctrine entity repo, mapped to domain. Returns null if no active default exists.
2. **`saveAll()` implementation:** Iterates over memberships, for each: find existing entity or create new via mapper (same logic as `save()`), update fields. Single `$this->entityManager->flush()` at the end.
3. **Handler flow:** (1) load target membership via `findByClinicAndUser`, throw if null, (2) load current default via `findDefaultForUser`, (3) if current default exists and is different from target → `clearDefault()`, (4) `setAsDefault()` on target, (5) `saveAll(...)` with both aggregates (or just target if no previous default), (6) `publish()` on old default (if it was cleared), (7) `publish()` on target.
4. **Command params:** `string $clinicId`, `string $userId` — both scalars, VOs created in the handler.

## Implementation Plan

### Tasks

- [x] **Task 1: Add `findDefaultForUser` and `saveAll` to repository interface**
  - File: `src/Context/Clinic/Domain/Staff/Repository/ClinicMembershipRepositoryInterface.php`
  - Action: Add method signature `public function findDefaultForUser(UserId $userId): ?ClinicMembership;`
  - Action: Add method signature `public function saveAll(ClinicMembership ...$memberships): void;`

- [x] **Task 2: Implement `findDefaultForUser` and `saveAll` in Doctrine repository**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php`
  - Action: Implement `findDefaultForUser` using `findOneBy(['userId' => Uuid::fromString($userId->toString()), 'isDefault' => true, 'status' => ClinicMembershipStatus::ACTIVE])`. If entity found, return `$this->mapper->toDomain($entity)`. If null, return null. Follow existing `findByClinicAndUser` pattern. Import `ClinicMembershipStatus`.
  - Action: Implement `saveAll` — loop over each `$membership`, apply the same find-or-create + update logic as `save()` (find existing entity by ID, if null → `toEntity()` + `persist()`, if found → update fields). After the loop, single `$this->entityManager->flush()`.

- [x] **Task 3: Create `SetDefaultClinic` command**
  - File: `src/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinic.php` (new)
  - Action: Create `final readonly class SetDefaultClinic implements CommandInterface` with constructor params `public string $clinicId, public string $userId`.

- [x] **Task 3b: Create `SetDefaultClinicTest`**
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicTest.php` (new)
  - Action: Test that the command stores and exposes `clinicId` and `userId` properties correctly. Follow existing `CreateClinicMembership` test pattern if one exists, otherwise a simple construction + assertion test.

- [x] **Task 4: Create `SetDefaultClinicHandler`**
  - File: `src/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicHandler.php` (new)
  - Action: Create `#[AsMessageHandler] final readonly class SetDefaultClinicHandler` with dependencies:
    - `ClinicMembershipRepositoryInterface $membershipRepository`
    - `DomainEventPublisher $domainEventPublisher`
  - `__invoke(SetDefaultClinic $command): void` flow:
    1. Build VOs: `$clinicId = ClinicId::fromString($command->clinicId)`, `$userId = UserId::fromString($command->userId)`
    2. Load target: `$target = $this->membershipRepository->findByClinicAndUser($clinicId, $userId)` — throw `\InvalidArgumentException` if null
    3. Load current default: `$currentDefault = $this->membershipRepository->findDefaultForUser($userId)`
    4. If `$currentDefault !== null` and `$currentDefault->id()->toString() !== $target->id()->toString()`: call `$currentDefault->clearDefault()`
    5. Call `$target->setAsDefault()`
    6. Persist: if `$currentDefault` was cleared → `$this->membershipRepository->saveAll($currentDefault, $target)`, otherwise → `$this->membershipRepository->saveAll($target)`
    7. Publish events AFTER saveAll: if `$currentDefault` was cleared → `$this->domainEventPublisher->publish($currentDefault)`, then `$this->domainEventPublisher->publish($target)`

- [x] **Task 5: Update `SubmitSelectClinicController`**
  - File: `src/Presentation/Clinic/Controller/Auth/SubmitSelectClinicController.php`
  - Action: Add `CommandBusInterface $commandBus` to constructor DI (add as 4th parameter after `$csrfTokenManager`).
  - Action: Add `use` imports for `App\Shared\Application\Bus\CommandBusInterface` and `App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinic`.
  - Action: Move the command dispatch OUTSIDE the existing try/catch block. Place it AFTER the try/catch block (which validates access), BEFORE `$this->currentClinicContext->setCurrentClinicId(...)`. The dispatch must NOT be inside the `try { ... } catch (\Throwable $e)`:
    ```php
    // After the try/catch access validation block:
    $this->commandBus->dispatch(new SetDefaultClinic(
        clinicId: $clinicId,
        userId: $user->id(),
    ));

    $this->currentClinicContext->setCurrentClinicId(ClinicId::fromString($clinicId));

    return $this->redirectToRoute('clinic_dashboard');
    ```
  - Note: The existing try/catch handles access validation. The command dispatch is a separate concern — its exceptions (programming errors) should propagate as 500s, not be caught.

- [x] **Task 6: Create handler unit tests**
  - File: `tests/Unit/Context/Clinic/Application/Command/Staff/SetDefaultClinic/SetDefaultClinicHandlerTest.php` (new)
  - Note: All events are `ClinicMembershipDefaultChanged` — `clearDefault()` records one with `isDefault: false`, `setAsDefault()` records one with `isDefault: true`. The `DomainEventPublisher::publish()` calls `pullDomainEvents()` on the aggregate and dispatches them via `EventBusInterface::publish([], ...$events)`. If no events are recorded (idempotent case), `DomainEventPublisher` returns early and does NOT call `EventBusInterface::publish()` at all.
  - Note: `EventBusInterface::publish(array $stamps, DomainEventInterface ...$events)` — the first argument is always `[]` (empty stamps array). All mock assertions on `EventBusInterface::publish()` must include `[]` as the first expected argument: `->with([], self::isInstanceOf(ClinicMembershipDefaultChanged::class))`.
  - Tests:
    - `testSetsDefaultOnFirstTimeSelection` — `findDefaultForUser` returns null. Assert: `saveAll()` called once with target only, `EventBusInterface::publish()` called once with `([], ClinicMembershipDefaultChanged)` where payload has `isDefault === true`.
    - `testChangesDefaultFromPreviousToNew` — `findDefaultForUser` returns a different membership (reconstituted with `isDefault: true`). Assert: `saveAll()` called once with both aggregates, `EventBusInterface::publish()` called exactly twice — first with `([], ClinicMembershipDefaultChanged)` payload `isDefault: false` for old default, then with `([], ClinicMembershipDefaultChanged)` payload `isDefault: true` for new default.
    - `testIdempotentWhenSameClinicReselected` — `findDefaultForUser` returns the same membership as target (same ID, already `isDefault: true`). Assert: `setAsDefault()` is idempotent (no event recorded), `saveAll()` called once with target only, `EventBusInterface::publish()` called with `self::never()` (publisher returns early on empty event list).
    - `testThrowsWhenMembershipNotFound` — `findByClinicAndUser` returns null. Assert: `\InvalidArgumentException` thrown, `saveAll()` never called, `EventBusInterface::publish()` never called.

- [x] **Task 7: Add `findDefaultForUser` integration tests**
  - File: `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php`
  - Tests:
    - `testFindDefaultForUserReturnsActiveDefaultMembership` — Create a membership with `isDefault: true` via Foundry using `->asDefault()`, assert `findDefaultForUser` returns it with `isDefault() === true`.
    - `testFindDefaultForUserReturnsNullWhenNoDefault` — Create a membership with `isDefault: false`. Assert: returns null.
    - `testFindDefaultForUserIgnoresDisabledMembership` — Create a membership with `isDefault: true` AND `status: DISABLED` via `->asDefault()->disabled()`. Assert: returns null (disabled memberships are not effective defaults).

- [x] **Task 7b: Add `asDefault()` helper to Foundry factory**
  - File: `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php`
  - Action: Add method following existing pattern (`asEmployee()`, `asVeterinary()`, etc.):
    ```php
    public function asDefault(): self
    {
        return $this->with(['isDefault' => true]);
    }
    ```

- [x] **Task 8: Run `make ci`**
  - Action: `rm -rf var/cache/*`, `composer dump-autoload`, `make reset-test-db`, `make ci`
  - All checks must pass.

### Acceptance Criteria

- [x] **AC-1 (First-time default):** Given a user with multiple clinics and no existing default, when the user clicks "Définir et accéder" on a clinic, then `ClinicMembership.isDefault` is set to `true` for that clinic, session context is set, and user is redirected to `clinic_dashboard`.

- [x] **AC-2 (Change default):** Given a user with an existing default clinic A, when the user selects clinic B on the select-clinic screen, then clinic A's `isDefault` is cleared, clinic B's `isDefault` is set to `true`, both changes are persisted atomically (single flush), and user lands on clinic B's dashboard.

- [x] **AC-3 (Idempotent reselection):** Given a user whose default is already clinic A, when the user selects clinic A again, then no domain event is recorded (idempotent), `isDefault` remains `true`, and user lands on the dashboard.

- [x] **AC-4 (Membership not found):** Given a `SetDefaultClinic` command with a `clinicId`/`userId` pair that has no membership, when the handler executes, then `\InvalidArgumentException` is thrown.

- [x] **AC-5 (No previous default — no crash):** Given a user with no membership marked as default, when `SetDefaultClinicHandler` runs, then `findDefaultForUser` returns null, `clearDefault()` is never called, and `setAsDefault()` succeeds on the target.

- [x] **AC-6 (Disabled default ignored):** Given a user with a disabled membership marked as `isDefault = true`, when `findDefaultForUser` is called, then it returns null (only ACTIVE memberships are considered).

- [x] **AC-7 (CI green):** `make ci` exits 0 — all checks pass with zero warnings.

## Additional Context

### Dependencies

- Depends on domain foundation from `tech-spec-post-login-routing` (already merged to master): `ClinicMembership.isDefault`, `setAsDefault()`, `clearDefault()`, `ClinicMembershipDefaultChanged` event, `ResolveActiveClinicHandler` default resolution logic.
- No external dependencies.

### Testing Strategy

- **Handler:** Unit tests with mocked repository and event bus — 4 test cases covering happy path (no previous default), change default, idempotent, and membership-not-found.
- **Command DTO:** Unit test for construction and property access.
- **Repository:** Integration tests with Foundry factories for `findDefaultForUser` — 3 test cases covering active default found, no default, and disabled default ignored.
- **Controller:** No unit tests (Presentation layer excluded from 100% coverage). Functional validation via manual testing: login → select clinic → verify redirect + verify next login auto-redirects.
- `make ci` validates everything.

### Notes

- **Risk: low.** Additive command/handler + controller wiring. No schema migration needed (column already exists).
- `saveAll()` uses a single `flush()` ensuring atomicity. Events are published after persistence, not between saves.
- **Future ticket:** Accessing the select-clinic screen from user settings (e.g., a "Change default clinic" link in the profile/settings area). This will reuse the same `SetDefaultClinic` command — no domain changes needed.
