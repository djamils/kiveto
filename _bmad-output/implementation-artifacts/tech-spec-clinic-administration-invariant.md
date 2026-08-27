---
title: 'Granting and revoking clinic administration, without locking a clinic out'
slug: 'clinic-administration-invariant'
created: '2026-08-27'
revised: '2026-08-27'
status: 'completed'
stepsCompleted: [1, 2, 3, 4, 5]
reviewRound: 5
batch: '2 of 2'
depends_on: 'tech-spec-clinic-administration-hat.md (must be merged first)'
tech_stack:
  - 'PHP 8.5 (strict_types, final by default)'
  - 'Symfony 7.4 (Messenger buses, Security, Twig, AssetMapper)'
  - 'Doctrine ORM 3.5 + Doctrine Migrations 3.7'
  - 'MySQL 8.4.7 — pinned in compose.yaml:3 and .env:34'
  - 'PHPUnit 12.5 (failOnDeprecation/Notice/Warning) — suites `unit` and `integration` only'
  - 'PHPStan level max, PHP-CS-Fixer, PHPCS PSR-12'
  - 'Zenstruck Foundry 2.8 + DAMA DoctrineTestBundle'
  - 'Stimulus 3.2 / Turbo 7.3 / Tailwind v4.2 (no Node)'
files_to_modify:
  - 'src/Context/Clinic/Domain/Staff/ClinicMembership.php'
  - 'src/Context/Clinic/Domain/Staff/Event/ClinicMembershipAdministrationChanged.php (new)'
  - 'src/Context/Clinic/Domain/Staff/Repository/ClinicMembershipRepositoryInterface.php'
  - 'src/Context/Clinic/Application/Exception/LastClinicAdministratorException.php (new)'
  - 'src/Context/Clinic/Application/Service/ClinicAdministrationInvariant.php (new)'
  - 'src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipAdministration/ChangeClinicMembershipAdministration.php (new)'
  - 'src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipAdministration/ChangeClinicMembershipAdministrationHandler.php (new)'
  - 'src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/DisableClinicMembership/DisableClinicMembershipHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipValidityWindow/ChangeClinicMembershipValidityWindowHandler.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php'
  - 'src/Presentation/Clinic/Controller/Settings/GrantTeamAdministrationController.php (new)'
  - 'src/Presentation/Clinic/Controller/Settings/RevokeTeamAdministrationController.php (new)'
  - 'src/Presentation/Clinic/Controller/Settings/DisableTeamMemberController.php'
  - 'src/Presentation/Backoffice/Controller/ClinicMembershipController.php'
  - 'templates/clinic/settings/team.html.twig'
  - 'templates/backoffice/clinic-memberships/index.html.twig'
  - 'src/Context/Clinic/README.md'
  - 'tests/Unit/Context/Clinic/Domain/Staff/ClinicMembershipTest.php'
  - 'tests/Unit/Context/Clinic/Domain/Staff/Event/ClinicMembershipAdministrationChangedTest.php (new)'
  - 'tests/Unit/Context/Clinic/Application/Service/ClinicAdministrationInvariantTest.php (new)'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipAdministration/ChangeClinicMembershipAdministrationHandlerTest.php (new)'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/DisableClinicMembership/DisableClinicMembershipHandlerTest.php'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipValidityWindow/…HandlerTest.php'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandlerTest.php'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php'
  - 'tests/Integration/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php'
  - 'tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php'
code_patterns:
  - 'Flag + paired mutators + single bool event (isDefault / ClinicMembershipDefaultChanged)'
  - 'Idempotent mutators: early return when unchanged, before recording the event'
  - 'Domain events carry scalars only; payload() returns an array of scalars'
  - 'Handler order: findById -> capture pre-state -> mutate -> assert invariant -> save -> publish'
  - 'Write repo in Domain/Repository, read repo in Application/Port'
  - 'Application exceptions extend \RuntimeException with an English message'
  - 'User-facing French strings live in Presentation, not in the exception'
  - 'One __invoke() per controller; state-changing row actions are POST + their own controller'
test_patterns:
  - 'tests/Unit/Context/Clinic/... mirrors src/Context/Clinic/...'
  - 'Aggregate tests instantiate directly and assert pullDomainEvents()'
  - 'Handler tests use createMock() for repos/ports, no container'
  - 'Clock doubles must stub now() with a fixed instant — see Task 15'
  - 'Integration repo tests via KernelTestCase + Foundry ClinicMembershipEntityFactory'
  - 'self::assertSame() everywhere, never assertEquals()'
---

# Tech-Spec: Granting and revoking clinic administration, without locking a clinic out

**Created:** 2026-08-27 · **Batch 2 of 2**

> **Read `tech-spec-clinic-administration-hat.md` first, and merge it first.**
> That batch made clinic administration a flag a membership *carries*, set once
> at creation. It ships no way to change the flag afterwards, which is precisely
> why it needed no guard.
>
> This batch adds the changing. The moment a hat can be taken off, a clinic can
> be left with nobody who can open its settings — so the guard arrives in the
> same change as the writes it guards, not before them and not after.

## Overview

### Problem Statement

After batch 1, `isClinicAdministrator` is set when a membership is created and
never again. Three consequences:

- **A clinic cannot promote from within.** A practice that hires a manager, or
  whose owner-veterinarian wants to hand over the administrative side, has no
  way to say so. The only lever is creating a new membership — and
  `uniq_clinic_user (clinic_id, user_id)` allows exactly one membership per user
  per clinic, while `CreateClinicMembershipHandler` refuses `VETERINARY` and
  `VETERINARY_ASSISTANT` outright (l.37-39). For an existing member, there is no
  lever at all.
- **A clinic cannot demote.** An administrator who leaves keeps the flag on their
  row forever.
- **Recovery is by side effect.** Batch 1's answer to "a clinic lost its only
  administrator" is that the flag is still on the row, so re-enabling the
  membership or reopening its validity window from the publisher backoffice
  restores access. That works, but it depends on the clinic never actually
  losing the *flag* — which stops being true the moment revocation exists.

### Solution

Add grant and revoke, on both surfaces, and make it impossible for any single
write to take a clinic from *has an effective administrator* to *has none*.

Three writes can remove the last key, and all three are guarded: **revoking the
hat**, **disabling the membership**, and **closing its validity window**.
Changing a role cannot — the hat is independent of the role (Decision 5).

### Invariant: nobody takes the last key away

**Definitions.** A membership is *effective* when
`ClinicMembership::isEffectiveAt($now)` is true: status `ACTIVE`, `validFrom`
reached, `validUntil` not passed. An *effective administrator* is an effective
membership holding the flag. These two words mean exactly this everywhere in
this document — in the domain, in the query, and in the tests.

**The rule is a no-worsening rule, not a no-zero rule.** A write is refused only
when *that write* takes a clinic from "has an effective administrator" to "has
none". A clinic that already has none is never blocked — which matters, because:

- **Time is a fourth force, and no handler mediates it.** `isEffectiveAt()`
  turns false when `validUntil` passes. The set of effective administrators can
  shrink with no write at all. Any rule phrased as "this state must never exist"
  is therefore unenforceable; a rule phrased as "no write may cause this state"
  is enforceable and honest.
- **Blocking writes in an already-empty clinic turns a protection into a trap.**
  If every guarded command refused whenever the count is zero, then a clinic
  whose administrator's window quietly expired could not disable a departing
  receptionist, could not fix the very validity window that caused the problem,
  and could not reach `/settings` either. An earlier round of this design
  shipped exactly that, and it is the single defect this section exists to
  prevent recurring.

So the invariant is enforced by comparing the pre-state to the post-state:

```
if the membership is STILL an effective administrator after the mutation  -> allow
if SOMEONE ELSE is an effective administrator                             -> allow
if the membership was NOT an effective administrator before the mutation  -> allow  (we did not cause this)
otherwise                                                                 -> refuse
```

**Reaching the empty state is still possible; being stuck there is not.** Two
independent ways out:

1. **Creation re-seeds it** (Tasks 6): a membership created into a clinic with
   no effective administrator receives the hat. This is best-effort — a
   membership created already out of window is hatted but not effective — and
   that is acceptable precisely because of (2).
2. **The publisher backoffice can always grant the hat** (Task 12), by dedicated
   row actions on any existing membership. It runs outside
   `ClinicPermissionGuard`, so it cannot lock itself out. This is the guarantee;
   (1) is the convenience that keeps it from being needed. Because everything
   else leans on it, it gets the *same* treatment as the clinic side — value in
   the route, explicit refusal catch, CSRF — and not a cheaper variant.

### Scope

**In Scope:**

- **Domain** — `grantAdministration()` / `revokeAdministration()`,
  `isEffectiveAdministratorAt()`, and a `ClinicMembershipAdministrationChanged`
  event.
- **Persistence** — `countEffectiveAdministrators()` on the write repository.
- **Application** — a `ClinicAdministrationInvariant` collaborator; a new
  `ChangeClinicMembershipAdministration` command with a tenant check; the
  re-seeding rule on both creation handlers; the invariant asserted in
  `DisableClinicMembership` and `ChangeClinicMembershipValidityWindow`.
- **Presentation** — administration as a **row action** on the clinic team page
  (two new single-action controllers, with CSRF and tenant scoping), the refusal
  caught and worded in French wherever it can surface, and the same capability
  in the publisher backoffice listing.
- **Tests** — every new branch covered, plus the existing tests whose outcomes
  change.

**Out of Scope:**

- Everything already delivered by batch 1.
- Fixing scoping and CSRF on the **pre-existing** `disable`, `enable`, `role`,
  `engagement` and `validity` actions. This batch protects the routes it creates
  and deliberately leaves those alone — see Decision 18 and Follow-ups.
- Splitting `manage_staff` and `manage_billing` into two independent rights.
- Restructuring `Presentation/Backoffice/Controller/ClinicMembershipController`,
  a pre-existing multi-action controller.
- The **Open decision** carried by batch 1 — whether `ClinicMemberRole` still
  earns its place as a taxonomy. Unrelated to this batch; answer it there.

## Context for Development

### Codebase Patterns

- **Flag + paired mutators + one bool event.** `ClinicMembership` already does
  this with `isDefault` / `setAsDefault()` / `clearDefault()` /
  `ClinicMembershipDefaultChanged`. Copy that shape, including
  `BOUNDED_CONTEXT = 'clinic-staff'` and `VERSION = 1`.
- **Idempotent mutators** — early return when unchanged, *before* recording.
- **Handler write order** — `findById()` → **capture pre-state** → domain method
  → **assert invariant** → `$repo->save()` → `publish()`. Never `flush()` in a
  handler.
- **Application exceptions extend `\RuntimeException`** with an **English**
  message; all five existing ones in
  `src/Context/Clinic/Application/Exception/` do. French user-facing wording
  belongs in Presentation.
- **One `__invoke()` per controller.** State-changing row actions are POST to
  their own controller class — `DisableTeamMemberController` /
  `EnableTeamMemberController` are the pattern to copy.
- **Write vs read repository split.** `countEffectiveAdministrators()` goes on
  the Domain write repository, because it enforces a write invariant.
- **`match` on exhaustive enums has no `default`** (PHPStan level max).
- **QueryBuilder auto-parenthesises `OR`.**
  `Doctrine\ORM\Query\Expr\Composite::processQueryPart()` wraps any part matching
  `/\s(OR|AND)\s/i` (the DDC-1237 fix), so `andWhere('a IS NULL OR a >= :x')` is
  safe. An earlier round claimed the opposite and ranked it as a top hazard; it
  is not one. Write the parentheses anyway for readability, not for correctness.
- **`ClinicMembershipStatus` has exactly two cases**, `ACTIVE` and `DISABLED`,
  so `isEffectiveAt()` testing `DISABLED === $status` and a query testing
  `status = :active` are equivalent. Verified.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Clinic/Domain/Staff/ClinicMembership.php` | The aggregate — `setAsDefault()` / `clearDefault()` (l.188-223) are the mutator pattern; **`isEffectiveAt()` at l.225-240 is the definition of "effective"**; `isClinicAdministrator()` arrived with batch 1 |
| `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipDefaultChanged.php` | Exact event shape to mirror |
| `src/Context/Clinic/Application/Exception/CannotChangeRoleWhileVeterinaryCredentialsExist.php` | Exception convention: `\RuntimeException`, English message |
| `.../Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandler.php` | Handler shape to follow |
| `.../Command/Staff/CreateClinicMembership/CreateClinicMembership.php` | Exposes `validFrom` / `validUntil` at l.18-19 — why the re-seeding rule is best-effort |
| `.../Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php` | **Refuses `VETERINARY`/`VETERINARY_ASSISTANT`** at l.37-39 |
| `.../Command/Staff/ChangeClinicMembershipValidityWindow/ChangeClinicMembershipValidityWindowHandler.php` | Currently unguarded — the third way to remove the last key |
| `.../Command/Staff/DisableClinicMembership/DisableClinicMembershipHandler.php` | Gains the invariant assertion |
| `.../Doctrine/Repository/DoctrineClinicMembershipRepository.php` | Where the count goes |
| `src/Presentation/Clinic/Security/ClinicPermissionGuard.php` | Consumes the permission list; no change |
| `src/Presentation/Clinic/Controller/Settings/DisableTeamMemberController.php` | Row-action pattern to copy — and it catches `\DomainException` only, l.34 |
| `src/Presentation/Clinic/Controller/Settings/EnableTeamMemberController.php` | The other half of the row-action pattern |
| `src/Presentation/Clinic/Controller/Settings/AddTeamMemberController.php` | How `CurrentClinicContextInterface` is injected and asserted |
| `src/Presentation/Backoffice/Controller/ClinicMembershipController.php` | Multi-action legacy. Five row actions to copy at l.139, 155, 171, 196, 221; all use `catch (\Throwable)` + `'Erreur: ' . $e->getMessage()`; `assertCsrf()` at l.98, 142, 158, 174, 199, 224 |
| `templates/backoffice/clinic-memberships/index.html.twig` | The backoffice listing, where the row actions live; `_token` already rendered at l.139 and l.144 |
| `templates/clinic/settings/team.html.twig` | Row actions l.89-94; the "Gestion" column arrived with batch 1 |
| `config/packages/csrf.yaml` | Sets no `stateless_token_ids` — relevant to the Follow-ups |

### Technical Decisions

Numbering is continuous with `tech-spec-clinic-administration-hat.md`, which
carries Decisions 1, 2, 4, 6, 7, 8, 9bis, 14, 19 and 20. Decisions 9 and 17 of
rounds 1–4 were deleted with the production-backfill machinery they justified.

3. **No write may take the last key.** See the **Invariant** section;
   `LastClinicAdministratorException` is the refusal.

5. **Role changes cannot remove the last key.** The hat is independent of the
   role, so `ChangeClinicMembershipRoleHandler` is untouched. Stated explicitly
   because it is the obvious fourth candidate and guarding it would be dead code.

10. **The publisher backoffice comes into scope, with a row action — not just a
    create field.** Recovery by creating a new membership is not general:
    `uniq_clinic_user` requires a user with no membership in that clinic, and
    `CreateClinicMembershipHandler` refuses `VETERINARY` /
    `VETERINARY_ASSISTANT`. A grant/revoke action on an existing row is
    unconditional, and the listing already has five row actions to copy. This is
    the guarantee the whole risk model leans on, so it is mandatory, not
    conditional.

11. **The invariant compares pre-state to post-state, in one collaborator.**
    Asserting on the post-state alone refuses every guarded command in a clinic
    that is already empty, trapping it. Each handler captures
    `$wasEffectiveAdministrator` before mutating and passes it in. The call is
    still uniform across handlers.

12. **"Effective" is defined once and applied everywhere.**
    `countEffectiveAdministrators(ClinicId, \DateTimeImmutable $now,
    ?ClinicMembershipId $excluding)` filters status **and** window, mirroring
    `isEffectiveAt()`. A status-only filter would let an expired administrator
    keep a clinic looking reachable when it is not.

13. **The read-then-write race is accepted, not solved.** Two concurrent
    revocations can both pass the guard. Locking every membership write to close
    a race between two humans in the same second is not a trade worth making,
    and Decision 10 bounds the damage. Recorded so it is not rediscovered as a
    surprise.

15. **Administration is a row action, not a form field — on every surface.** A
    checkbox cannot express "leave unchanged": an unticked box is simply absent
    from the POST. Pairing it with a hidden `0` makes an unticked box revoke —
    and makes a *failed page script* revoke too, silently, on every edit.
    Guarding with `has()` instead makes revocation impossible from the UI. Two
    dedicated POST routes with the target value **in the route** remove the
    ambiguity entirely, need no JavaScript, and match the `Désactiver` /
    `Réactiver` buttons already on this page. It also keeps administration out of
    the multi-command edit form, so a refusal can no longer arrive after the role
    and phone have been flushed.

    The rule is not "prefer routes here"; it is "a boolean carried in a request
    body cannot distinguish *revoke* from *field missing*", and that is true
    wherever it is written. **Both surfaces get two routes** — including, and
    especially, the backoffice, which Decision 10 calls the guarantee. An earlier
    round applied this to the clinic side only, and that is the mistake this
    sentence exists to stop repeating.

16. **The refusal is caught and worded in French at every surface it can reach.**
    `LastClinicAdministratorException` extends `\RuntimeException` per the folder
    convention, so it does **not** fall into the team controllers' existing
    `catch (\DomainException | \InvalidArgumentException)`, and the backoffice's
    universal `catch (\Throwable)` prints `'Erreur: ' . $e->getMessage()` raw.
    Six actions can raise it, and all six get an explicit catch with French
    wording: the two new clinic controllers (Task 9),
    `DisableTeamMemberController` (Task 10), and three backoffice actions —
    `revokeAdministration`, `disable` and `changeValidity` (Task 12). The count
    is stated per task so it cannot drift.

18. **The two new privilege-granting routes get CSRF and a tenant check; the
    pattern they copy has neither.** `DisableTeamMemberController` takes
    `{membershipId}` straight off the URL, and neither it nor
    `DisableClinicMembershipHandler` checks that the membership belongs to the
    caller's current clinic; `templates/clinic/settings/team.html.twig:89-94`
    posts with no `_token`. That is a pre-existing weakness on *disabling a
    member*. Reproducing it for *granting `MANAGE_STAFF` and `MANAGE_BILLING`*
    would turn it into cross-tenant privilege escalation, triggerable by CSRF
    from any page a manager visits.

    So the new controllers do not copy that part: they validate a CSRF token, and
    `ChangeClinicMembershipAdministration` carries a `clinicId` the handler
    checks against the membership's own. The backoffice passes each row's real
    clinic id, which `MembershipListItem::$clinicId` already provides — so the
    check is uniform rather than a special case, and the backoffice stays
    legitimately cross-tenant without a nullable field.

    **The existing gap is left in place and recorded in Follow-ups.** Fixing
    scoping and CSRF across `disable`, `enable`, `role`, `engagement` and
    `validity` is a security branch of its own, not a rider on this one — but it
    is a real hole and should be scheduled.

21. **The re-seeding rule on creation is a convenience, not a guarantee, and is
    documented as such.** `CreateClinicMembership` exposes `validFrom` and
    `validUntil` (l.18-19), so a membership can be created already out of window:
    hatted, but not effective, and therefore not actually re-seeding anything.
    Decision 10 is what makes that acceptable. Do not present this rule as a
    guarantee anywhere — README included.

## Implementation Plan

### Tasks

#### Domain

- [x] **Task 1: Let the hat be put on and taken off**
  - Files: `src/Context/Clinic/Domain/Staff/ClinicMembership.php` and
    `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipAdministrationChanged.php` (new)
  - Action:
    - New event: copy `ClinicMembershipDefaultChanged` verbatim, renaming the
      last constructor parameter and payload key to `isClinicAdministrator`.
      Keep `BOUNDED_CONTEXT = 'clinic-staff'`, `VERSION = 1`, `aggregateId()`
      returning `$this->membershipId`, and a `@return array<string, bool|string>`
      docblock — correct here because nothing in this payload is nullable.
      `readonly class`, not `final readonly class` — match the siblings.
    - On the aggregate, add `grantAdministration(): void` /
      `revokeAdministration(): void`, mirroring `setAsDefault()` /
      `clearDefault()`: early-return when already in the target state, then set
      the field and record the event.
    - Add:
      ```php
      public function isEffectiveAdministratorAt(\DateTimeImmutable $now): bool
      {
          return $this->isClinicAdministrator && $this->isEffectiveAt($now);
      }
      ```
  - Notes: The field, the getter and `permissions()` already exist — batch 1
    added them. Do not re-declare them.

- [x] **Task 2: Let a use case count effective administrators**
  - File: `src/Context/Clinic/Domain/Staff/Repository/ClinicMembershipRepositoryInterface.php`
  - Action: Add
    ```php
    public function countEffectiveAdministrators(
        ClinicId $clinicId,
        \DateTimeImmutable $now,
        ?ClinicMembershipId $excluding = null,
    ): int;
    ```
  - Notes: `$excluding` lets a handler ask "would anyone be left besides this
    one?" before saving.

#### Persistence

- [x] **Task 3: Implement the effective-administrator count**
  - File: `.../Doctrine/Repository/DoctrineClinicMembershipRepository.php`
  - Action:
    ```php
    public function countEffectiveAdministrators(
        ClinicId $clinicId,
        \DateTimeImmutable $now,
        ?ClinicMembershipId $excluding = null,
    ): int {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(ClinicMembershipEntity::class, 'm')
            ->where('m.clinicId = :clinicId')
            ->andWhere('m.isClinicAdministrator = true')
            ->andWhere('m.status = :active')
            ->andWhere('m.validFrom <= :now')
            ->andWhere('(m.validUntil IS NULL OR m.validUntil >= :now)')
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()), UuidType::NAME)
            ->setParameter('active', ClinicMembershipStatus::ACTIVE)
            ->setParameter('now', $now);

        if (null !== $excluding) {
            $qb->andWhere('m.id <> :excluded')
               ->setParameter('excluded', Uuid::fromString($excluding->toString()), UuidType::NAME);
        }

        $count = $qb->getQuery()->getSingleScalarResult();
        \assert(\is_int($count) || \is_string($count));

        return (int) $count;
    }
    ```
  - Notes: `getSingleScalarResult()` is typed `mixed`; PHPStan level max rejects
    a bare cast, hence the assert. The conditions must mirror
    `ClinicMembership::isEffectiveAt()` (l.225-240) exactly — if that method
    changes, this query changes with it (Decision 12). Import `UuidType` from
    `Symfony\Bridge\Doctrine\Types\UuidType`. The parentheses around the `OR` are
    for readability; `Expr\Composite` would add them anyway.

#### Application

- [x] **Task 4: Add the exception and the invariant**
  - Files: `.../Application/Exception/LastClinicAdministratorException.php` (new)
    and `.../Application/Service/ClinicAdministrationInvariant.php` (new)
  - Action: The exception follows the folder convention —
    `final class … extends \RuntimeException`, plain constructor, no static
    factory, **English** message. French wording lives in Presentation.

    The invariant implements the four-line rule from the **Invariant** section:
    ```php
    final readonly class ClinicAdministrationInvariant
    {
        public function __construct(
            private ClinicMembershipRepositoryInterface $memberships,
        ) {
        }

        /**
         * @param bool               $wasEffectiveAdministrator the membership's state BEFORE the mutation
         * @param \DateTimeImmutable $now                       the instant the caller judged that state at
         *
         * @throws LastClinicAdministratorException
         */
        public function assertNoKeyLost(
            ClinicMembership $mutated,
            bool $wasEffectiveAdministrator,
            \DateTimeImmutable $now,
        ): void {
            // We cannot take away what this membership never held.
            if (!$wasEffectiveAdministrator) {
                return;
            }

            if ($mutated->isEffectiveAdministratorAt($now)) {
                return;
            }

            $others = $this->memberships->countEffectiveAdministrators(
                $mutated->clinicId(),
                $now,
                $mutated->id(),
            );

            if (0 === $others) {
                throw new LastClinicAdministratorException($mutated->clinicId()->toString());
            }
        }
    }
    ```
  - Notes: Decisions 11, 12, 16. The `$wasEffectiveAdministrator` guard is what
    makes this a no-worsening rule and must come **first** — without it, a clinic
    already at zero refuses every guarded command and can never be repaired.

    `$now` is a **parameter, not a second clock read**. The caller judged the
    pre-state at some instant; the post-state must be judged at the same one.
    `SystemClock::now()` returns a fresh `\DateTimeImmutable` per call, so
    reading it twice lets a membership whose `validUntil` falls between the two
    reads be refused a write it did not cause — a small violation of the
    no-worsening contract, and free to avoid. The invariant therefore takes no
    `ClockInterface` at all; the handlers own the clock.

    Do **not** extend `\DomainException` to piggyback on existing catch clauses;
    the controllers gain explicit catches instead (Tasks 9, 10, 12).

- [x] **Task 5: Add the grant/revoke use case**
  - Files: `.../Command/Staff/ChangeClinicMembershipAdministration/`
    - `ChangeClinicMembershipAdministration.php` (new) — `final readonly class`
      implementing `CommandInterface`, `public string $clinicId`,
      `public string $membershipId`, `public bool $isClinicAdministrator`.
    - `ChangeClinicMembershipAdministrationHandler.php` (new)
  - Action: Follow `ChangeClinicMembershipRoleHandler` for shape:
    ```php
    $membership = $this->membershipRepository->findById($membershipId);
    if (null === $membership) {
        throw new \InvalidArgumentException(...);
    }

    // The membership id comes off a URL. Refuse to touch one that belongs to
    // another clinic, whatever the caller's permissions say about their own.
    if (!$membership->clinicId()->equals(ClinicId::fromString($command->clinicId))) {
        throw new \InvalidArgumentException(\sprintf(
            'Membership "%s" does not belong to clinic "%s".',
            $command->membershipId,
            $command->clinicId,
        ));
    }

    $now                       = $this->clock->now();
    $wasEffectiveAdministrator = $membership->isEffectiveAdministratorAt($now);

    if ($command->isClinicAdministrator) {
        $membership->grantAdministration();
    } else {
        $membership->revokeAdministration();
    }

    $this->invariant->assertNoKeyLost($membership, $wasEffectiveAdministrator, $now);

    $this->membershipRepository->save($membership);
    $this->domainEventPublisher->publish($membership);
    ```
  - Notes: Inject `ClockInterface`. Read `$now` **once** and pass it to both the
    pre-state judgement and the invariant. Revoking from a member who never held
    the hat short-circuits on the `$wasEffectiveAdministrator` guard — no special
    case. The clinic check is Decision 18: it is what stops a manager of clinic A
    from granting administration in clinic B by editing a URL. `ClinicId` has an
    `equals()` method; do not compare value objects with `===`.

- [x] **Task 6: Re-seed a clinic that has nobody, on creation**
  - Files: `.../CreateClinicMembership/CreateClinicMembershipHandler.php` and
    `.../OnboardStaffMember/OnboardStaffMemberHandler.php`
  - Action: In each handler, before `ClinicMembership::create()`:
    ```php
    $isClinicAdministrator = $command->isClinicAdministrator
        || 0 === $this->membershipRepository->countEffectiveAdministrators($clinicId, $createdAt);
    ```
    and pass `$isClinicAdministrator` instead of `$command->isClinicAdministrator`.
  - Notes: The command field itself already exists — batch 1 added it. This task
    adds only the `||` clause.

    Best-effort by design (Decision 21). **This changes the outcome of existing
    tests silently** — see Task 17, which is the one to read before starting.

- [x] **Task 7: Refuse to disable the last administrator**
  - File: `.../DisableClinicMembership/DisableClinicMembershipHandler.php`
  - Action: Inject `ClinicAdministrationInvariant` and `ClockInterface`. Read
    `$now = $this->clock->now()` **once**, capture
    `$wasEffectiveAdministrator = $membership->isEffectiveAdministratorAt($now)`
    before `$membership->disable()`, then call
    `$this->invariant->assertNoKeyLost($membership, $wasEffectiveAdministrator, $now)`
    before `save()`.
  - Notes: No clinic check here — this handler is reached from both the clinic
    team page and the backoffice, and adding one would change existing
    behaviour. The pre-existing scoping gap is Decision 18 and Follow-ups.

- [x] **Task 8: Refuse to expire the last administrator**
  - File: `.../ChangeClinicMembershipValidityWindow/ChangeClinicMembershipValidityWindowHandler.php`
  - Action: The same additions as Task 7, around
    `$membership->changeValidityWindow(...)`, with the single `$now` read.
  - Notes: The third write that can remove the last key, and it removes it two
    ways: `validUntil` moved into the **past**, or `validFrom` moved into the
    **future**. Either makes a membership ineffective without touching its
    status, so a status-based check would never see it. Both branches need a
    test (Task 15) and both are asserted by AC 3 and AC 4.

#### Presentation

- [x] **Task 9: Two row-action controllers for the hat**
  - Files (new):
    - `src/Presentation/Clinic/Controller/Settings/GrantTeamAdministrationController.php`
    - `src/Presentation/Clinic/Controller/Settings/RevokeTeamAdministrationController.php`
  - Action: Copy the **shape** of `DisableTeamMemberController` /
    `EnableTeamMemberController` — single `__invoke()`, POST route
    (`/settings/team/members/{membershipId}/administration/grant`, route name
    `clinic_settings_team_administration_grant`, and the `/revoke` counterpart),
    `$this->guard->require(ClinicPermissionGuard::MANAGE_STAFF)`, dispatch
    `ChangeClinicMembershipAdministration` with the value fixed by the route,
    flash, redirect to `clinic_settings_team`.

    Then add the two things that pattern does **not** have, per Decision 18:
    1. **CSRF.** Validate a token before dispatching —
       `$this->isCsrfTokenValid('team_administration', $request->request->getString('_token'))`,
       throwing `createAccessDeniedException()` when it fails. Task 11 renders
       the matching `_token` field.
    2. **Tenant scope.** Pass
       `clinicId: $this->currentClinicContext->getCurrentClinicId()->toString()`
       into the command; the handler (Task 5) refuses a membership from another
       clinic. Inject `CurrentClinicContextInterface` as
       `AddTeamMemberController` does, and `\assert(null !== $clinicId)` as it does.

    The revoke controller adds `catch (LastClinicAdministratorException)` with a
    French flash — e.g. *« Impossible : plus personne ne pourrait
    gérer la clinique. »* — before any other catch.
  - Notes: Decisions 15 and 18. The target value is in the route, so a missing or
    mis-rendered field cannot silently revoke, and no JavaScript is involved.
    **Do not copy the missing CSRF and missing scope check**: they are tolerable
    on the pre-existing disable button and are not tolerable on a route that
    grants `MANAGE_STAFF` and `MANAGE_BILLING`.

- [x] **Task 10: Catch the refusal on the disable button**
  - File: `src/Presentation/Clinic/Controller/Settings/DisableTeamMemberController.php`
  - Action: Add `catch (LastClinicAdministratorException $exception)` **before**
    the existing `catch (\DomainException …)` at l.34, with the same French
    flash.
  - Notes: Without this, Task 7 turns an ordinary "Désactiver" click into a
    500 — the exception extends `\RuntimeException` and falls straight through.

- [x] **Task 11: Offer the two buttons on the team page**
  - File: `templates/clinic/settings/team.html.twig`
  - Action: Beside the existing Désactiver/Réactiver form (**l.89-94**, not
    l.83-88 — those are the edit button's `data-*` attributes), add a second POST
    form to `clinic_settings_team_administration_revoke` or `…_grant` depending
    on `membership.isClinicAdministrator`, labelled *« Retirer la gestion »* /
    *« Donner la gestion »*, and carrying
    `<input type="hidden" name="_token" value="{{ csrf_token('team_administration') }}">`
    to match the check Task 9 adds.
  - Notes: The "Gestion" column and its badge already exist — batch 1 added them.
    The wording is fixed by **batch 1's Decision 22**: the capability is called
    *gestion* throughout, never *gérance* — that word is the noun of *Gérant*,
    which `roleLabels` already spends on the `MANAGER` role in the adjacent
    column. A button offering *« la gérance »* on a row whose Rôle reads
    *Vétérinaire* says the opposite of what this change means.
    **Do not** touch the edit modal (l.187-226) and do **not** add a
    `data-administrator` attribute: administration travels only through its own
    two routes (Decision 15). Nothing here depends on `settings.js`.

- [x] **Task 12: Give the publisher backoffice the same capability**
  - Files:
    - `src/Presentation/Backoffice/Controller/ClinicMembershipController.php`
    - `templates/backoffice/clinic-memberships/index.html.twig`
  - Action: Two changes, both mandatory.
    1. Add **two row actions**, not one — mirroring the clinic side exactly:
       `#[Route('/clinic-memberships/{id}/administration/grant', name: 'backoffice_memberships_administration_grant', methods: ['POST'])]`
       and the `/revoke` counterpart. Copy the shape of `disable()` (l.139-153):
       `assertCsrf($request)`, try, dispatch
       `ChangeClinicMembershipAdministration` with the boolean **fixed by the
       route** and `clinicId` taken from the posted row, French success flash.
       Render both buttons in `index.html.twig` beside the existing ones, each
       form carrying `<input type="hidden" name="_token" value="{{ csrf_token }}">`
       (as l.139 and l.144 already do) **and** a hidden `clinic_id` field —
       `MembershipListItem::$clinicId` is already available in that loop.
    2. Add `catch (LastClinicAdministratorException $e)` with a French flash,
       **before** the existing `catch (\Throwable $e)`, in **three** actions:
       the new `revoke` action from (1), `disable()` (l.139) and
       `changeValidity()` (l.221). Without it the universal handler prints
       `'Erreur: ' . $e->getMessage()` — an English sentence in a French UI.
  - Notes: Decision 10 — these row actions are the guarantee the entire risk
    model leans on, so they are not optional and not a cheaper variant.

    Two routes rather than one boolean field, because Decision 15 is not a
    clinic-side preference: a boolean read from a request body cannot tell
    *revoke* from *field missing*, and here that would silently strip the last
    administrator on the very surface meant to restore them.

    `assertCsrf()` is already the convention here (l.98, 142, 158, 174, 199, 224)
    and the template already renders `_token`; omit either half and the action
    returns *« Jeton CSRF invalide »* on every click.

    The creation checkbox already exists — batch 1, Task 15. Do not add it twice.

    **Do not restructure this controller.** It holds several routes in one class
    against the project rule, but that is pre-existing.

#### Documentation

- [x] **Task 13: Record the invariant in the BC README**
  - File: `src/Context/Clinic/README.md`
  - Action: Add to business invariants: *no write may leave a clinic without an
    effective administrator* — stated as the no-worsening rule, **not** as "a
    clinic always has one", with the two recovery paths named.
  - Notes: "clinic administrator" is already in Ubiquitous Language — batch 1,
    Task 19. Phrasing it as "a clinic always has one" would be false: time can
    empty a clinic with no write at all, and Decision 21 makes the creation rule
    best-effort.

#### Tests

- [x] **Task 14: Domain tests**
  - Files: `ClinicMembershipTest`,
    `Event/ClinicMembershipAdministrationChangedTest` (new)
  - Action: Cover `grantAdministration()` / `revokeAdministration()` with both
    idempotent no-op paths; `isEffectiveAdministratorAt()` across flag × status ×
    window (including `validFrom` in the future and `validUntil` in the past);
    the new event's `payload()` and `aggregateId()`.
  - Notes: `pullDomainEvents()` is destructive and `#[\NoDiscard]` — once per test.

- [x] **Task 15: Application tests**
  - Files: `Service/ClinicAdministrationInvariantTest` (new),
    `Command/Staff/ChangeClinicMembershipAdministration/…HandlerTest` (new),
    `DisableClinicMembership/…`, `ChangeClinicMembershipValidityWindow/…`,
    `CreateClinicMembership/…`, `OnboardStaffMember/…`
  - Action: For the invariant, all four branches, and the third one explicitly:
    still an effective administrator → allowed; someone else is → allowed;
    **was not one before → allowed even when the clinic has zero** (the
    anti-trap case); otherwise → throws. For the handlers: grant; revoke with
    others remaining; revoke refused on the last, asserting `save()` is **never**
    called; revoke as a no-op on a member without the hat; grant as a no-op on a
    member who already has it; disable refused on the last; validity window
    refused when it would expire the last, **both branches**; **disable allowed
    in a clinic that already has zero effective administrators**; creation
    forcing the flag when the clinic has none, and respecting an explicit `false`
    when one exists; a membership belonging to another clinic refused with
    `\InvalidArgumentException` and `save()` never called.
  - Notes: `createStub()` for repositories and `DomainEventPublisher`.

    **The clock double must have `now()` stubbed explicitly** with a fixed
    instant:
    ```php
    $clock = self::createStub(ClockInterface::class);
    $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-25 10:00:00'));
    ```
    An unstubbed double auto-returns an *incomplete* `\DateTimeImmutable`, and
    the first comparison inside `isEffectiveAt()` (`ClinicMembership.php:231`)
    raises `DateObjectError: Trying to compare an incomplete DateTime`. Every
    test in this task reaches that line. A fixed instant is required anyway to
    make the window cases deterministic.

    The anti-trap case and the both-branches validity case are the two that prove
    this design.

- [x] **Task 16: Persistence tests**
  - File: `DoctrineClinicMembershipRepositoryTest`
  - Action: `countEffectiveAdministrators()` ignores a disabled administrator, an
    expired one (`validUntil` past), a not-yet-started one (`validFrom` future),
    and one from another clinic. Cover `$excluding`.
  - Notes: The expired-member assertion is what catches Decision 12 — a
    status-only filter passes every other case. Not reachable from a mocked unit
    test. Use `ClinicMembershipEntityFactory::asClinicAdministrator()`, added by
    batch 1.

- [x] **Task 17: Repair the outcomes Task 6 silently changes**
  - Action: Task 6 makes both creation handlers call
    `countEffectiveAdministrators()`. In handler unit tests the repository is a
    `createMock()`/`createStub()`, and **PHPUnit auto-returns `0` for an `int`
    return type** — so the re-seeding rule fires and the flag is forced on in
    every existing test that does not stub it. Nothing goes red; the tests just
    quietly stop testing what they claim.

    Visit and make the intent explicit in `CreateClinicMembershipHandlerTest`
    (see l.45), the unit `OnboardStaffMemberHandlerTest`, and the **integration**
    `tests/Integration/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php`,
    where the onboarded member really is the clinic's first and now genuinely
    receives the hat.
  - Notes: Read this task **before** starting Task 6, not after. It is the most
    likely thing in this batch to go wrong without anyone noticing.

- [x] **Task 18: Go green**
  - Action: `make assets` if any template changed, then **`make migrate-test-db`**
    if the schema moved (it does not in this batch — the column arrived with
    batch 1), then `make ci` until green. Then walk the demo by hand — see
    Testing Strategy.
  - Notes: Do not commit. Djamil validates first.

### Acceptance Criteria

**How to read the markers.** `phpunit.xml` declares exactly two suites, `unit`
(l.23-25) and `integration` (l.26-28). There is no functional suite and no
`WebTestCase` anywhere in the project, so **nothing that goes through HTTP has
an automated home**. Each criterion below is marked with where it is actually
verified. Treat `[manual]` as a step in the demo walk, not as a test someone
forgot to write.

- `[unit]` — `tests/Unit`, runs in `make ci`
- `[integration]` — `tests/Integration`, runs in `make ci`
- `[manual]` — the demo walk in Testing Strategy; nobody automates this today
- `[review]` — checked by reading the code or the pipeline output

Count: **16 automated, 9 manual, 2 review.** The invariant itself — the part
that is genuinely hard to get right — is almost entirely automated. What is
manual is the plumbing between an HTTP request and it.

**The invariant**

- [ ] **AC 1** `[unit]` Given a clinic whose only effective administrator is *M*,
  when the hat is revoked from *M*, then `LastClinicAdministratorException` is
  thrown and `save()` is never called.
- [ ] **AC 2** `[unit]` Given that clinic, when *M* is disabled, then the same
  exception is thrown and *M* stays `ACTIVE`.
- [ ] **AC 3** `[unit]` Given that clinic, when *M*'s `validUntil` is moved into
  the past, then the same exception is thrown and the window is unchanged.
- [ ] **AC 4** `[unit]` Given that clinic, when *M*'s `validFrom` is moved into
  the future, then the same exception is thrown and the window is unchanged.
  Stated separately from AC 3 because a status-based check would miss both and a
  `validUntil`-only check would miss this one.
- [ ] **AC 5** `[unit]` Given a clinic with **zero** effective administrators —
  its only one expired without any write — when an unrelated receptionist is
  disabled, then it **succeeds**. The invariant refuses to worsen, not to exist.
- [ ] **AC 6** `[unit]` Given that same empty clinic, when the expired
  administrator's window is reopened, then it succeeds.
- [ ] **AC 7** `[unit]` Given a clinic with two effective administrators, when
  the hat is revoked from one, then it succeeds and exactly one
  `ClinicMembershipAdministrationChanged` event is recorded.
- [ ] **AC 8** `[unit]` Given a clinic with two administrators of which one is
  expired, when the hat is revoked from the effective one, then the exception is
  thrown — an expired administrator does not keep a clinic reachable.
- [ ] **AC 9** `[unit]` Given a membership without the hat, when revocation is
  dispatched for it, then it is a silent no-op — no exception, no event, and the
  clinic's count is never queried.
- [ ] **AC 10** `[unit]` Given a membership that already holds the hat, when a
  grant is dispatched for it, then it is a silent no-op — no second event.
- [ ] **AC 11** `[unit]` Given a clinic with no effective administrator, when a
  membership is created in it with the flag unset, then that membership receives
  the hat.
- [ ] **AC 12** `[unit]` Given a clinic that has one, when a membership is
  created with the flag explicitly unset, then it does **not** receive the hat.
- [ ] **AC 13** `[unit]` Given a command whose `membershipId` belongs to a
  different clinic than its `clinicId`, when it is handled, then
  `\InvalidArgumentException` is thrown and `save()` is never called.
- [ ] **AC 14** `[unit]` Given the new event, then `payload()` carries
  `isClinicAdministrator` and `aggregateId()` returns the membership id.

**Persistence**

- [ ] **AC 15** `[integration]` Given `countEffectiveAdministrators()`, then it
  ignores a disabled administrator, one whose `validUntil` has passed, one whose
  `validFrom` has not arrived, and one belonging to another clinic.
- [ ] **AC 16** `[integration]` Given `$excluding`, then the named membership is
  excluded from the count.

**The team page and the backoffice**

- [ ] **AC 17** `[manual]` Given a member without the hat, when "Donner la
  gestion" is clicked, then they hold it and the row updates; and symmetrically
  for "Retirer la gestion".
- [ ] **AC 18** `[manual]` Given the last administrator, when "Retirer la
  gestion" is clicked, then the page returns a redirect with a **French** error
  flash — not an HTTP 500.
- [ ] **AC 19** `[manual]` Given the last administrator, when "Désactiver" is
  clicked, then the same — a French flash, not a 500.
- [ ] **AC 20** `[manual]` Given a POST to either administration route **without
  a valid `_token`**, when it is processed, then it is refused with 403 and the
  membership is unchanged.
- [ ] **AC 21** `[manual]` Given a manager of clinic A, when they POST an
  administration route with a `membershipId` belonging to clinic B, then it is
  refused and the clinic-B membership is unchanged.
- [ ] **AC 22** `[manual]` Given the ordinary edit modal submitted for a member —
  role, contract and phone — when it succeeds, then that member's hat is
  unchanged; administration travels only through its own two routes and appears
  nowhere in that form's payload.
- [ ] **AC 23** `[manual]` Given the publisher backoffice listing and a clinic
  with **no** administrator at all, when the grant row action is used on one of
  its memberships, then that membership receives the hat — this is the recovery
  path, and it must work in exactly that situation.
- [ ] **AC 24** `[manual]` Given the backoffice revoke action on the last
  administrator, when it is refused, then the flash is **French**, not the
  exception's English message.
- [ ] **AC 25** `[manual]` Given the backoffice validity action closing the last
  administrator's window, when it is refused, then the flash is **French**.

**Pipeline**

- [ ] **AC 26** `[review]` When `make ci` runs, then `php-cs-fixer.dry-run`,
  `phpcs`, `phpstan` (level max), `tailwind-build` and the full test suite all
  pass, with no deprecation, notice or warning.
- [ ] **AC 27** `[review]` Given the Clinic bounded context, when coverage is
  measured, then every line added or modified is covered.

## Review Notes

- Adversarial review completed (`bmad-review-adversarial-general`) against the
  full diff from `1dd48cee`.
- Findings: 13 total, 4 fixed, 9 acknowledged and left in place.
- Resolution approach: auto-fix of real findings in newly written code; Djamil
  was away, so findings touching pre-existing code or requiring a functional
  test suite were recorded rather than acted on.
- Fixed: no-op writes on `ChangeClinicMembershipAdministrationHandler` (it now
  returns before touching the repository when the hat is already where the
  caller wants it); the two no-op handler tests, which asserted `save()` was
  called while their names claimed nothing happened; unvalidated `clinic_id` on
  the two backoffice row actions; and a new integration test binding
  `countEffectiveAdministrators()` to `isEffectiveAdministratorAt()` so the two
  predicates cannot drift apart silently.
- Acknowledged, not fixed: the two new clinic-side controllers carry the CSRF
  and tenant checks with no automated coverage (the project has no functional
  suite — see Follow-ups); the pre-existing IDOR/CSRF gap on the five adjacent
  row actions; the migration granting the hat to disabled managers by design;
  re-enabling a membership silently restoring administration; the shared French
  message for "membership gone" and "membership belongs elsewhere"; and the
  creation event gaining a payload key while VERSION stays 1.

## Additional Context

### Dependencies

**`tech-spec-clinic-administration-hat.md` must be merged first.** This batch
assumes the column, the mapper, both write paths, the read model, the aggregate
field and getter, `permissions()`, the command fields, the "Gestion" column, the
creation checkboxes, the fixtures and
`ClinicMembershipEntityFactory::asClinicAdministrator()` all exist.

No schema change here — the column arrived with batch 1.

### Testing Strategy

**Unit — application.** This is where the weight sits, and it is where it should
sit:

- `ClinicAdministrationInvariantTest` — four branches, and the anti-trap one
  (`$wasEffectiveAdministrator === false` in an empty clinic → allowed) is the
  one that encodes the defect this design exists to avoid;
- the refusal on all three write paths, each asserting `save()` is **never**
  called;
- the validity-window refusal on **both** branches — `validUntil` into the past
  and `validFrom` into the future;
- the re-seeding rule forcing the flag when a clinic has nobody.

**Unit — domain.** Grant/revoke with both no-op paths;
`isEffectiveAdministratorAt()` across flag × status × window.

**Integration.** `countEffectiveAdministrators()` ignoring disabled, expired,
not-yet-started and other-clinic administrators, and `$excluding` actually
excluding. DAMA rolls each test back.

**Manual — walking the demo.** Rebuild fixtures, then:

1. As `vet@kiveto.local` on `/settings/team`, "Retirer la gestion" on
   `manager@` → succeeds; `vet@` still holds it. *(AC 17)*
2. "Donner la gestion" back to `manager@` → succeeds. *(AC 17)*
3. "Retirer la gestion" on `vet@` while they are the only administrator →
   refused with a French flash. *(AC 18)*
4. "Désactiver" on `vet@` in the same situation → refused with a French flash,
   not a 500. *(AC 19)*
5. Edit `vet@` through the ordinary edit modal, changing the phone → saved, hat
   untouched. *(AC 22)*
6. Replay the grant POST with the `membershipId` of a **Lyon-only** membership
   (`vet.lyon@`) while on Paris → refused, and `vet.lyon@` is unchanged. This is
   the check the pattern copied from `DisableTeamMemberController` deliberately
   does not have. *(AC 21)*
7. Replay the same POST with `_token` removed → 403. *(AC 20)*
8. In the publisher backoffice, use the grant and revoke row actions on a
   membership → each works, with the CSRF token in place. *(AC 17)*
9. In the backoffice, take a clinic to zero administrators — disable the
   administrator's membership from there, which is allowed because it does not
   clear the flag — then grant the hat to a different member. It works. *(AC 23)*
10. In the backoffice, revoke from the last administrator of a clinic → refused
    with a **French** flash, not `Erreur: Clinic "…" would be left without…`.
    *(AC 24)*
11. In the backoffice, close the last administrator's validity window → same.
    *(AC 25)*

### Notes

#### Where this is most likely to go wrong

Ranked by how quietly each one fails:

1. **The anti-trap guard dropped or reordered** (Task 4). If
   `$wasEffectiveAdministrator` is not checked *first*, every guarded command
   refuses in a clinic that already has no administrator, and that clinic can
   never be repaired from inside. Nothing fails at build time; it surfaces as a
   support ticket nobody can resolve. AC 5 and AC 6 are the only guards.
2. **The row-action redesign applied to one surface only** (Tasks 9, 12). An
   earlier round did exactly this: two routes on the clinic side, a body-carried
   boolean on the backoffice — on the surface Decision 10 calls the guarantee.
   If either surface reads the target value out of the request body, a missing
   field silently revokes, and it revokes on the page that exists to restore
   access. Check both.
3. **Silent test drift from the re-seeding rule** (Task 17). Mocked repositories
   return `0` for `int`, so the rule fires everywhere and existing tests stop
   testing what they claim. Nothing goes red.
4. **Two clock reads instead of one** (Tasks 4, 5, 7, 8). `SystemClock::now()`
   returns a fresh instant per call. Judge the pre-state at one instant and the
   post-state at another, and a membership whose window expires between them is
   refused a write it did not cause. It will never reproduce on demand.
5. **The `validFrom`-into-the-future branch untested** (Task 8). Everyone tests
   `validUntil` into the past and stops. Both make a membership ineffective; a
   test that covers one covers neither honestly. AC 4 exists for this.
6. **The exception escaping its catch** (Tasks 10, 12).
   `LastClinicAdministratorException` extends `\RuntimeException`, so the team
   controllers' `catch (\DomainException | \InvalidArgumentException)` does not
   see it and the click becomes a 500. Six actions can raise it; count them.

#### Known limitations, deliberately accepted

- **The count/save race** (Decision 13), bounded by the backoffice row action.
- **The re-seeding rule is best-effort** (Decision 21): a membership created
  already out of window is hatted but not effective.
- **The hat is per-membership**, so a user at two clinics is granted or refused
  it independently at each. Intended, but a group operator clicks twice.
- **Nine of twenty-seven criteria are verified by hand**, because the project
  has no functional test suite. The invariant itself is fully automated; the
  HTTP plumbing around it is not.

#### Follow-ups (not in this change)

- **Security: IDOR and missing CSRF on the existing team row actions.** The
  `disable` and `enable` actions, and the backoffice's `role`, `engagement` and
  `validity` actions, take `{membershipId}` off the URL with **no check that it
  belongs to the caller's clinic**, and the clinic-side forms carry **no CSRF
  token** (`templates/clinic/settings/team.html.twig:89-94`;
  `config/packages/csrf.yaml` sets no `stateless_token_ids`). A manager of one
  clinic can disable a member of another by editing a URL, and any page a
  manager visits can trigger it. This batch adds both protections to the two
  routes it creates (Decision 18) and deliberately does not touch the existing
  ones — that is a security branch of its own, and it should be scheduled rather
  than left implicit. **It is the most urgent item on this list.**
- **`make ci` does not migrate the test database.** `Makefile:414` chains
  `php-cs-fixer.dry-run phpcs phpstan tailwind-build test`, and `test`
  (l.441-445) runs `bin/phpunit` alone, so any schema change makes every
  integration test fail in a way that looks like broken code.
- **There is no functional test suite.** A `WebTestCase` suite would move nine of
  this batch's criteria into `make ci`, including the two CSRF and tenant checks
  that are the security-relevant ones.
- Every `src/Context/Clinic/Application/Exception/*` extends `\RuntimeException`
  and therefore escapes the team controllers' `catch (\DomainException |
  \InvalidArgumentException)` clauses, producing a 500 where a flash is
  intended. This change adds explicit catches for its own exception only; the
  pattern deserves its own branch.
- **The Open decision from batch 1** — whether `ClinicMemberRole` still earns its
  place as a taxonomy now that `MANAGER` and `VETERINARY` are
  permission-identical. Free to answer today, expensive after the first customer.
- `Presentation/Backoffice/Controller/ClinicMembershipController` holds several
  routes in one class, against the single-action rule.
- `src/System/AccessControl/Application/Listener/*` imports Clinic domain events
  directly, crossing a bounded-context boundary that should go through a port.
