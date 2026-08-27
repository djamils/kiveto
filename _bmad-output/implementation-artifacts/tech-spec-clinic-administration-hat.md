---
title: 'Clinic administration as a hat, not a role'
slug: 'clinic-administration-hat'
created: '2026-08-25'
revised: '2026-08-27'
status: 'completed'
stepsCompleted: [1, 2, 3, 4, 5]
reviewRound: 5
batch: '1 of 2'
companion: 'tech-spec-clinic-administration-invariant.md'
tech_stack:
  - 'PHP 8.5 (strict_types, final by default)'
  - 'Symfony 7.4 (Messenger buses, Security, Twig, AssetMapper)'
  - 'Doctrine ORM 3.5 + Doctrine Migrations 3.7'
  - 'MySQL 8.4.7 — pinned in compose.yaml:3 and .env:34'
  - 'Binary UUIDv7 ids (SymfonyUuidV7Generator), byte order = time order'
  - 'PHPUnit 12.5 (failOnDeprecation/Notice/Warning) — suites `unit` and `integration` only'
  - 'PHPStan level max, PHP-CS-Fixer, PHPCS PSR-12'
  - 'Zenstruck Foundry 2.8 + DAMA DoctrineTestBundle'
  - 'Stimulus 3.2 / Turbo 7.3 / Tailwind v4.2 (no Node)'
files_to_modify:
  - 'src/Context/Clinic/Domain/Staff/ClinicMembership.php'
  - 'src/Context/Clinic/Domain/Staff/StaffRolePermissionMap.php'
  - 'src/Context/Clinic/Domain/Staff/Event/ClinicMembershipCreated.php'
  - 'src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembership.php'
  - 'src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMember.php'
  - 'src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php'
  - 'src/Context/Clinic/Application/Query/Staff/ListPermissionsForUserInClinic/ListPermissionsForUserInClinicHandler.php'
  - 'src/Context/Clinic/Application/Query/Staff/ListAllMemberships/MembershipListItem.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapper.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepository.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineMembershipAdminRepository.php'
  - 'migrations/Clinic/VersionYYYYMMDDHHMMSS.php (new)'
  - 'src/Presentation/Clinic/Controller/Settings/AddTeamMemberController.php'
  - 'src/Presentation/Backoffice/Controller/ClinicMembershipController.php'
  - 'templates/clinic/settings/team.html.twig'
  - 'templates/components/layout/sidebar.html.twig'
  - 'templates/backoffice/clinic-memberships/new.html.twig'
  - 'assets/styles/pages/settings.css'
  - 'fixtures/Context/Clinic/Story/ClinicStaffProfileDataStory.php'
  - 'fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php'
  - 'fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php'
  - 'src/Context/Clinic/README.md'
  - 'tests/Unit/Context/Clinic/Domain/Staff/ClinicMembershipTest.php'
  - 'tests/Unit/Context/Clinic/Domain/Staff/StaffRolePermissionMapTest.php'
  - 'tests/Unit/Context/Clinic/Domain/Staff/Event/ClinicMembershipCreatedTest.php'
  - 'tests/Unit/Context/Clinic/Application/Query/Staff/ListPermissionsForUserInClinic/ListPermissionsForUserInClinicHandlerTest.php (new)'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandlerTest.php'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php'
  - 'tests/Integration/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php'
  - 'tests/Unit/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMembershipMapperTest.php'
  - 'tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php'
  - 'tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineMembershipAdminRepositoryTest.php'
code_patterns:
  - 'Flag declared beside isDefault; set at construction, read by a getter'
  - 'Domain events carry scalars only; payload() returns an array of scalars'
  - 'Write repo in Domain/Repository, read repo in Application/Port'
  - 'save()/saveAll() update branch enumerates setters explicitly (two call sites)'
  - 'One __invoke() per controller'
  - 'Migration: addSql() with raw SQL and an explanatory comment on any backfill'
  - 'Vertical alignment of = in named constructors; no constructor promotion in Domain'
test_patterns:
  - 'tests/Unit/Context/Clinic/... mirrors src/Context/Clinic/...'
  - 'Aggregate tests instantiate directly and assert pullDomainEvents()'
  - 'Handler tests use createMock() for repos/ports, no container'
  - 'Integration repo tests via KernelTestCase + Foundry ClinicMembershipEntityFactory'
  - 'self::assertSame() everywhere, never assertEquals()'
  - 'Mapper tests assert toDomain(toEntity($aggregate)) symmetry'
---

# Tech-Spec: Clinic administration as a hat, not a role

**Created:** 2026-08-25 · **Revised:** 2026-08-27 (round 5 — split into two batches)

> **Batch 1 of 2.** This spec makes clinic administration a flag that a
> membership *carries*. Its companion,
> `tech-spec-clinic-administration-invariant.md`, makes that flag *changeable
> after the fact* and adds the guard that stops a clinic locking itself out.
>
> The two are separable because of one property, stated here and relied on
> throughout: **batch 1 ships no way to remove the hat from a membership.** The
> flag is set when the membership is created, by the add-member form, by the
> publisher backoffice, by the fixtures, or by the migration — and never
> changed afterwards. A guard against "no write may take the last key" is
> unnecessary when no such write exists. See **Why the invariant is not here**.

> **Revision history.** Rounds 1–4 built a single 33-task spec: the flag, an
> inter-aggregate invariant, a window-function backfill with role-preference
> fallback, grant/revoke row actions on two surfaces, CSRF and tenant scoping.
> Round 5 established that **the application has no production deployment and
> no production data**. That removes the justification for roughly half of it —
> not by deferring the work, but by deleting the part that existed solely to
> preserve and protect data that does not exist. What remains splits cleanly at
> the line drawn above. Deleted outright: the migration's second statement and
> its `ROW_NUMBER()`/`FIELD()` machinery, the role-preference fallback, the
> production rehearsal query, and the three acceptance criteria that tested
> them.

## Overview

### Problem Statement

`ClinicMemberRole` conflates two independent axes: the clinical job (who treats
animals and signs prescriptions) and clinic administration (who configures the
clinic). Today the two are welded together:

- `StaffRolePermissionMap` grants `MANAGE_STAFF` and `MANAGE_BILLING` to
  `MANAGER` only — so only a `MANAGER` can open `/settings`.
- `ClinicMemberRole::canHoldVeterinaryCredentials()` returns `true` for
  `VETERINARY` only, and `OnboardStaffMemberHandler` drops the credentials when
  the role cannot hold them.

The consequence is that the lead veterinarian of an independent practice — the
most common real-world arrangement, and the account the demo walks through
(`vet@kiveto.local`, Dr Éric Rousseau, `FR-12345`) — cannot both prescribe and
configure the clinic:

- Promoting them to `MANAGER` costs them the registration number and
  professional title that `DbalPrescriberDirectory` reads onto every
  prescription.
- Giving them a second `MANAGER` membership is impossible: the
  `uniq_clinic_user (clinic_id, user_id)` constraint on
  `ClinicMembershipEntity` allows one membership — hence one role — per clinic.

The model is wrong, not the demo data.

### Solution

Make clinic administration a hat that any membership can wear, independent of
its clinical role. Add an `isClinicAdministrator` flag to `ClinicMembership`;
resolve permissions as the union of the role's permissions and the
administrator's permissions. `MANAGER` loses `MANAGE_STAFF` / `MANAGE_BILLING`
from the permission map so the flag is the single source of truth.

The flag is set **at creation only**: on the add-member form, on the publisher
backoffice creation form, in the fixtures, and by the migration for rows that
already exist. Granting and revoking it on an existing membership is batch 2.

### Why the invariant is not here

Removing `MANAGE_STAFF` from `MANAGER` removes the only existing path to clinic
administration. Rounds 1–4 concluded the change was therefore only safe behind
an inter-aggregate invariant — *no write may leave a clinic without an effective
administrator* — enforced on three handlers, with a backoffice recovery path,
CSRF and tenant scoping behind it. That reasoning was sound for the spec as it
then stood. It does not apply to this batch, for two reasons:

1. **This batch adds no write that can remove the hat.** There is no
   `revokeAdministration()`, no `ChangeClinicMembershipAdministration` command,
   no grant/revoke route. The aggregate gains a flag and a getter, nothing that
   sets it after construction. An invariant guarding writes that do not exist
   is dead code.

2. **The two ways a clinic can still lose its administrator are both
   reversible from outside the guard.** A hat-wearing membership can become
   ineffective by being disabled, or by having its validity window closed.
   Neither clears the flag — the row keeps it — and both are undone by the
   publisher backoffice's existing `enable` and `changeValidity` row actions,
   which run outside `ClinicPermissionGuard` and therefore cannot lock
   themselves out. The clinic becomes temporarily unreachable, not permanently
   stuck.

   In development, `make load-fixtures` is a third way out.

Batch 2 introduces the writes that genuinely can take the last key away, and
introduces the guard in the same change. That is the correct place for it.

### Scope

**In Scope:**

- **Domain** — `isClinicAdministrator` on `ClinicMembership`, set at
  construction, read by a getter, and permission resolution moved onto the
  aggregate.
- **Permission map** — remove `MANAGE_STAFF` and `MANAGE_BILLING` from
  `MANAGER`, and expose the administrator's permission set separately.
- **Persistence** — `is_clinic_administrator` column, a two-statement
  migration, the mapper, both write-repository update branches, and the admin
  read SQL.
- **Application** — the flag threaded through `CreateClinicMembership` and
  `OnboardStaffMember`; the permission query reading the membership rather than
  the role.
- **Presentation** — a read-only "Gestion" column on the clinic team page, a
  checkbox on the add-member form, and the same checkbox on the publisher
  backoffice creation form.
- **Styling** — the `.tag` / `.tone-*` rules the team page already uses but does
  not load.
- **Fixtures** — `vet@kiveto.local` becomes the owner-manager; `manager@` and
  `admin.clinic@` keep the non-clinical manager case; the Foundry factory learns
  the new field.
- **Tests** — every new branch covered, plus the existing test files whose call
  sites or outcomes change.

**Out of Scope — deferred to batch 2:**

- `grantAdministration()` / `revokeAdministration()` and the
  `ClinicMembershipAdministrationChanged` event.
- `isEffectiveAdministratorAt()`, `countEffectiveAdministrators()`,
  `ClinicAdministrationInvariant`, `LastClinicAdministratorException`.
- The `ChangeClinicMembershipAdministration` command and its two clinic-side
  row-action controllers, with their CSRF and tenant checks.
- The publisher backoffice grant/revoke row actions.
- The rule that re-seeds a clinic with no effective administrator on creation.

**Out of Scope — not planned:**

- Splitting `manage_staff` and `manage_billing` into two independent rights.
- Revisiting the permissions of `VETERINARY`, `VETERINARY_ASSISTANT` or
  `RECEPTIONIST`.
- Restructuring `Presentation/Backoffice/Controller/ClinicMembershipController`,
  a pre-existing multi-action controller. Add to it in its own style; do not
  rewrite it.
- Teaching the AccessControl projection about the flag (Technical Decision 6).
- Fixing the pre-existing BC boundary violation in
  `src/System/AccessControl/Application/Listener/*`.

### Open decision — answer before this branch merges

**Does `ClinicMemberRole` still earn its place as a taxonomy, or has it become a
`canPrescribe` flag wearing four names?**

This is not a follow-up line. It is a decision that is **free today and
expensive later**, and this change is what makes it visible.

After Task 1, `MANAGER` and `VETERINARY` hold **byte-for-byte identical**
permission sets — verified: `StaffRolePermissionMap.php:32-42` minus the two
removed entries equals `:43-51` exactly. What still separates the four roles is
three predicates on the enum — `canHoldVeterinaryCredentials()`,
`canBePractitionerOfRecord()`, `appearsInMedicalAgendaByDefault()` — and all
three answer variations of one question: may this person act as a
veterinarian? Everything else the enum used to carry has now moved onto the
membership.

Three ways forward:

| | Option | Cost now | Cost in six months |
|---|---|---|---|
| **A** | **Leave the enum as it is.** Accept that two of its four cases are permission-identical. *Recommended for this branch.* | Zero | Zero, unless it misleads someone |
| **B** | **Trim `MANAGER`'s clinical permissions**, so a non-clinical manager can no longer open a medical record or sign a prescription. | Small — one map entry, a handful of tests | A data migration and a conversation with users about lost access |
| **C** | **Collapse the enum**, replacing four cases with a job title plus a `canPrescribe` capability. | Large — touches the agenda, the practitioner of record, prescriptions, fixtures | Very large |

**Recommendation: A for this branch, and decide B on its own branch before the
first customer.** B is a genuine product question — should a practice manager
be able to read a medical record? — and answering it inside a change that
promises not to alter what today's managers can do would break that promise.
But it should be answered while the answer costs a map entry rather than a
migration and a support conversation. C is not warranted by anything observed
so far; record it and move on.

Djamil's answer goes here before merge: **__________**

## Context for Development

### Codebase Patterns

Verified in the code, and binding on this change:

- **Flag beside `isDefault`.** `ClinicMembership` declares `private bool
  $isDefault;` at l.33 and exposes `isDefault()`. Copy the declaration and the
  getter. Do **not** copy `setAsDefault()` / `clearDefault()` — post-creation
  mutation is batch 2.
- **The flag must be settable at creation**, because `OnboardStaffMember` must
  onboard an owner-manager in one call.
- **Domain events carry scalars only** — `role->value`, never the enum.
- **Handler write order** — `findById()` → domain method → `$repo->save()` →
  `publish()`. Never `flush()` in a handler.
- **One `__invoke()` per controller.**
- **`match` on exhaustive enums has no `default`** (PHPStan level max).
- **Migration style** — `addSql()` with raw SQL and a comment on any backfill.
  **The reference is `migrations/Clinic/Version20260411000001.php`**, which
  added `is_default` to this same table.
- **The table is `clinic__clinic_memberships`**, written literally in
  `Version20260410000001.php:20`, `Version20260411000001.php:19` and
  `Version20260502194059.php:34`.
- **`ClinicMembershipStatus` has exactly two cases**, `ACTIVE` and `DISABLED`,
  so `isEffectiveAt()` testing `DISABLED === $status` and a query testing
  `status = 'ACTIVE'` are equivalent. Verified; noted because batch 2 depends
  on it.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Clinic/Domain/Staff/ClinicMembership.php` | The aggregate — `isDefault` at l.33 and `isDefault()` at l.188 are the pattern; `isEffectiveAt()` at l.225-240 is the definition of "effective" |
| `src/Context/Clinic/Domain/Staff/StaffRolePermissionMap.php` | `MANAGER` holds nine permissions at l.32-42; `VETERINARY` holds seven at l.43-51 |
| `src/Context/Clinic/Domain/Staff/ClinicPermission.php` | The permission enum |
| `src/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRole.php` | `canHoldVeterinaryCredentials()` — blocks the naive fix, and is what still separates `MANAGER` from `VETERINARY` after Task 1 |
| `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipCreated.php` | `payload()` is `@return array<string, string\|null>` at l.30 — nullable because of `validUntil` |
| `.../Query/Staff/ListPermissionsForUserInClinic/ListPermissionsForUserInClinicHandler.php` | The single seam where permissions resolve, l.41 — **has no test today** |
| `.../Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php` | Drops credentials when the role cannot hold them, l.108-110 |
| `.../Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php` | **Refuses `VETERINARY`/`VETERINARY_ASSISTANT`** at l.37-39 |
| `.../Doctrine/Entity/ClinicMembershipEntity.php` | `uniq_clinic_user`; `is_default` at l.50-51 is the column pattern |
| `.../Doctrine/Mapper/ClinicMembershipMapper.php` | Domain↔Entity; the one non-test caller of `reconstitute()`, l.18 |
| `.../Doctrine/Repository/DoctrineClinicMembershipRepository.php` | **Two update branches**: `save()` l.35-40, `saveAll()` l.114-119 |
| `.../Doctrine/Repository/DoctrineClinicMembershipReadRepository.php` | The `is_default` read pattern at l.52, l.81, l.98 — copy it exactly |
| `.../Doctrine/Repository/DoctrineMembershipAdminRepository.php` | Raw SQL `listAll()` — SELECT, `\assert`, DTO all need the new column |
| `migrations/Clinic/Version20260411000001.php` | **The reference migration** — added `is_default` to this table, l.19-24 |
| `src/Presentation/Clinic/Security/ClinicPermissionGuard.php` | Consumes the permission list; no change |
| `src/Presentation/Clinic/Controller/Settings/AddTeamMemberController.php` | Branches vet→`OnboardStaffMember` l.82-96, other→`CreateClinicMembership` l.98-103 |
| `src/Presentation/Backoffice/Controller/ClinicMembershipController.php` | Multi-action legacy. `CreateClinicMembership` at l.120 |
| `templates/backoffice/clinic-memberships/new.html.twig` | The backoffice creation form |
| `templates/clinic/settings/team.html.twig` | `roleLabels` l.10-15, `roleTones` l.16, row actions l.89-94, add form l.111-182, edit modal l.187-226 |
| `templates/components/layout/sidebar.html.twig` | l.219 heads this capability **"Gestion"**; l.230 says *« Réservé à la gérance »*, which Decision 22 makes false |
| `assets/styles/tailwind.css` | The `@import` list, l.121-148 — `pages/catalog.css` and `pages/stock.css` are **not** in it |
| `assets/styles/pages/catalog.css` | Where `.tag` and `.tone-*` actually live, l.220-241 |
| `assets/styles/pages/settings.css` | Imported at `tailwind.css:141`; has no `.tag` rule |
| `fixtures/Context/Clinic/Story/ClinicStaffProfileDataStory.php` | `$vetUser` l.77-90 and l.114-127; `$managerUser` l.92-97 and l.106-111 |
| `fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php` | **`admin.clinic@kiveto.local` as `MANAGER` on Lyon**, l.24 and l.33 |
| `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php` | `asManager()` l.44-47, `defaults()` l.110-124 enumerating `isDefault` |
| `fixtures/System/AccessControl/Story/RolePermissionSeedStory.php` | **Derives its seed from `StaffRolePermissionMap`** — Technical Decision 6 |

### Technical Decisions

Numbering is preserved from rounds 1–4 so that review history stays traceable.
Decisions 3, 5, 10, 11, 12, 13, 15, 16 and 18 belong to batch 2 and are stated
in its spec. Decisions 9 and 17 were deleted with the machinery they justified.

1. **`MANAGER` loses `MANAGE_STAFF` / `MANAGE_BILLING` from the permission map —
   and nothing else.** It keeps the other **seven** of its nine permissions,
   including `VIEW_MEDICAL_RECORD`, `CREATE_PRESCRIPTION` and
   `CREATE_CONSULTATION`. See the **Open decision** for what that implies.

2. **One flag, not two.** `isClinicAdministrator` carries `MANAGE_STAFF` and
   `MANAGE_BILLING` together.

4. **`vet@kiveto.local` becomes the owner-manager in the fixtures.** Role stays
   `VETERINARY`, so `FR-12345` and the `DR` title survive onto prescriptions.

6. **The AccessControl projection becomes role-only, knowingly.**
   `RolePermissionSeedStory` seeds `access_control__role_permissions` from
   `StaffRolePermissionMap`, so Decision 1 removes `manage_staff` /
   `manage_billing` from it entirely, and the projection — keyed by role —
   cannot express a per-membership flag. Nothing reads it outside its own BC
   today. Recorded because routing `ClinicPermissionGuard` through AccessControl
   later, without teaching it about memberships, would lock every clinic out.

7. **The read model must carry the flag**, or the team page cannot show who
   holds it.

8. **Two write paths, both easy to miss.** `DoctrineClinicMembershipRepository`
   enumerates setters explicitly in the update branch of *both* `save()` and
   `saveAll()`.

9bis. **The backfill is one statement, because there is nothing to preserve.**
   Rounds 1–4 specified a second statement — a `ROW_NUMBER()` window query with
   a `FIELD()` role preference over a nested derived table — whose entire
   purpose was to guarantee that *no existing clinic* was left without an
   administrator. **There are no existing clinics.** The application has never
   been deployed; every database that exists is a developer's, rebuilt by
   `make load-fixtures`. The remaining `UPDATE ... WHERE role = 'MANAGER'`
   serves exactly one case: a developer who migrates without reloading
   fixtures. That is worth two lines and no more.

   Deleted with it: the MySQL 8.4 dependency on `ROW_NUMBER()`, the production
   rehearsal query, and rounds 1–4's AC 17/18/19.

14. **After Task 1, `MANAGER` and `VETERINARY` hold identical permission sets.**
    Byte for byte. They are **not** interchangeable — `ClinicMemberRole`
    still separates them by `canHoldVeterinaryCredentials()`,
    `canBePractitionerOfRecord()` and `appearsInMedicalAgendaByDefault()`, which
    is what protects `FR-12345`. But anything reasoning purely from the
    permission map can no longer tell them apart, including
    `RolePermissionSeedStory`. This is now the **Open decision** above rather
    than a follow-up line.

19. **The add-member checkbox is safe; an edit-form checkbox would not be.**
    A checkbox cannot express "leave unchanged" — an unticked box is simply
    absent from the POST. On *creation* there is no prior state to lose, so
    "absent means false" is the correct and only reading. On *edit* it is not,
    which is why this batch adds the flag to the add form and the backoffice
    creation form and **deliberately leaves the edit modal untouched**. Batch 2
    adds mutation through dedicated routes rather than through that form.

20. **The aggregate ships no method this batch does not call.** No
    `grantAdministration()`, no `isEffectiveAdministratorAt()`, no
    `countEffectiveAdministrators()`. Each arrives in batch 2 alongside its
    first caller. Writing them now would mean production code with no
    production caller, tested only to satisfy the coverage rule — which is the
    shape of code that quietly drifts out of sync with what actually needs it.

22. **One French word for the capability, and it is "gestion" — never
    "gérance".** Rounds 1–4 used three words for one thing: a column headed
    *Gestion*, a badge reading *Administrateur*, buttons offering *la gérance*,
    and a checkbox about *gérer la clinique*. That is not merely untidy.

    **"Gérance" is the noun of "Gérant", and `roleLabels` already spends
    "Gérant" on the `MANAGER` role** (`team.html.twig:11`) — displayed in the
    *Rôle* column, immediately beside the one this change adds. A button reading
    *« Donner la gérance »* on a row whose Rôle pill reads *Vétérinaire* re-welds
    in words the two axes the whole change exists to separate in the model. A
    reader is entitled to conclude the button changes the role. It does not.

    **"Administrateur" is no better**: it is a job title, and the premise here is
    that this is a capability rather than a job. It also collides with the
    *publisher* backoffice's notion of an administrator, who is a Kiveto operator
    and not a clinic member at all.

    **"Gestion" is already the app's word for exactly this capability.**
    `sidebar.html.twig:219` heads the `clinic_can('manage_staff')` block
    *« Gestion »*, over precisely the two links this permission unlocks. The
    vocabulary was already chosen; the spec simply had not noticed.

    So, everywhere: column header **Gestion**, badge **« Gère la clinique »**,
    buttons **« Donner la gestion »** / **« Retirer la gestion »** (batch 2),
    checkbox **« Peut gérer la clinique (équipe, facturation, réglages) »**,
    refusal **« Impossible : plus personne ne pourrait gérer la clinique. »**

    The badge is a verb phrase, not a noun, on purpose — and it is the one place
    this costs something, since it is wider than the four role pills beside it.
    That width is the point: a title in that column would read as a fifth role.

    This decision also invalidates an existing string. `sidebar.html.twig:230`
    tells a refused user *« Réservé à la gérance »*, which stops being true the
    moment a veterinarian can hold the hat. Task 17 fixes it.

## Implementation Plan

### Tasks

#### Domain

- [x] **Task 1: Strip administration out of the role map, name it separately**
  - File: `src/Context/Clinic/Domain/Staff/StaffRolePermissionMap.php`
  - Action: The `MANAGER` entry lists **nine** permissions (l.32-42). Remove
    exactly two — `MANAGE_STAFF` and `MANAGE_BILLING` — leaving these **seven**,
    in this order: `VIEW_DASHBOARD`, `MANAGE_APPOINTMENTS`,
    `VIEW_MEDICAL_RECORD`, `CREATE_PRESCRIPTION`, `CREATE_CONSULTATION`,
    `MANAGE_CLIENTS`, `MANAGE_ANIMALS`. Then add:
    ```php
    /** @return list<ClinicPermission> */
    public static function administratorPermissions(): array
    {
        return [ClinicPermission::MANAGE_STAFF, ClinicPermission::MANAGE_BILLING];
    }
    ```
  - Notes: Do **not** remove the clinical permissions — that is the **Open
    decision**, option B, and it is not this branch. Be aware this makes the
    `MANAGER` list identical to `VETERINARY` (l.43-51); that is Decision 14, and
    it is accepted, not accidental.

- [x] **Task 2: Carry the flag on the creation event**
  - File: `src/Context/Clinic/Domain/Staff/Event/ClinicMembershipCreated.php`
  - Action: Add a trailing `private bool $isClinicAdministrator = false`
    constructor parameter and the matching `payload()` key. The docblock at l.30
    is currently `@return array<string, string|null>`; it becomes
    **`@return array<string, bool|string|null>`**.
  - Notes: Keep the `null` — `validUntil` is nullable, and dropping it fails
    PHPStan level max. **Leave `VERSION` at 1**: `AbstractDomainEvent` documents
    that adding an optional field is backward-compatible. `OnClinicMembershipCreated`
    in AccessControl reads named keys and is unaffected.

- [x] **Task 3: Give the membership its hat**
  - File: `src/Context/Clinic/Domain/Staff/ClinicMembership.php`
  - Action:
    - Declare `private bool $isClinicAdministrator;` next to `$isDefault` (l.33).
    - `create()` and `reconstitute()`: add `bool $isClinicAdministrator` as the
      **last** parameter, **no default**, assigned with the surrounding vertical
      `=` alignment. `create()` passes it to `ClinicMembershipCreated`.
    - Add `isClinicAdministrator(): bool` beside `isDefault()`.
    - Add the permission resolver:
      ```php
      /** @return list<ClinicPermission> */
      public function permissions(): array
      {
          $permissions = [];

          foreach (StaffRolePermissionMap::permissionsFor($this->role) as $permission) {
              $permissions[$permission->value] = $permission;
          }

          if ($this->isClinicAdministrator) {
              foreach (StaffRolePermissionMap::administratorPermissions() as $permission) {
                  $permissions[$permission->value] = $permission;
              }
          }

          return array_values($permissions);
      }
      ```
  - Notes: Key the accumulator by `->value` rather than `array_unique()` on enum
    instances — deterministic and PHPStan-clean. **No default on the new
    parameters is deliberate**: it forces every call site to be visited, which is
    what Task 21 enumerates. All existing calls use named arguments, so adding a
    trailing parameter is otherwise invisible — and invisible is exactly what
    went wrong in round 1.

    **No mutators, no `isEffectiveAdministratorAt()`** — Decision 20. Batch 2
    adds both.

#### Persistence

- [x] **Task 4: Add the column to the Doctrine entity**
  - File: `.../Doctrine/Entity/ClinicMembershipEntity.php`
  - Action: Copy the `is_default` declaration (l.50-51):
    ```php
    #[ORM\Column(name: 'is_clinic_administrator', type: 'boolean', options: ['default' => false])]
    private bool $isClinicAdministrator = false;
    ```
    plus getter and setter.
  - Notes: No new index; `idx_clinic_id` already serves the only query, and
    clinics have a handful of members.

- [x] **Task 5: Map the flag both ways**
  - File: `.../Doctrine/Mapper/ClinicMembershipMapper.php`
  - Action: `isClinicAdministrator: $entity->getIsClinicAdministrator()` in
    `toDomain()`; `$entity->setIsClinicAdministrator($membership->isClinicAdministrator())`
    in `toEntity()`.

- [x] **Task 6: Persist the flag on update, in both write paths**
  - File: `.../Doctrine/Repository/DoctrineClinicMembershipRepository.php`
  - Action: Add the setter call to the update branch of `save()` (l.35-40)
    **and** of `saveAll()` (l.114-119).
  - Notes: Decision 8. Miss one and the flag saves on insert but never on update.
    Batch 2's grant/revoke depends entirely on this, so it is worth getting right
    now rather than discovering it there.

- [x] **Task 7: Surface the flag in the team read model**
  - Files: `.../ListAllMemberships/MembershipListItem.php` and
    `.../Doctrine/Repository/DoctrineMembershipAdminRepository.php`
  - Action: Add `public bool $isClinicAdministrator` as the last constructor
    parameter. In `listAll()`, add `m.is_clinic_administrator` to the SELECT, add
    `\assert(\is_int($row['is_clinic_administrator']) || \is_bool($row['is_clinic_administrator']));`
    and pass `isClinicAdministrator: (bool) $row['is_clinic_administrator'],`.
  - Notes: `DoctrineClinicMembershipReadRepository` does this dance for
    `is_default` at l.52, l.81, l.98. MySQL returns `TINYINT(1)` as `int` or
    `bool` depending on driver settings, hence the two-branch assert.

- [x] **Task 8: Write the migration — two lines**
  - File: `migrations/Clinic/Version<YYYYMMDDHHMMSS>.php` (new — timestamp after `20260823140000`)
  - Action:
    ```php
    public function getDescription(): string
    {
        return 'Running a clinic is a hat a member wears, not the job they do.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships ADD is_clinic_administrator TINYINT(1) NOT NULL DEFAULT 0');

        // Until now, holding the manager role WAS holding manage_staff. Move
        // that source onto the flag for rows that already exist, so a developer
        // who migrates without reloading fixtures keeps the access they had.
        // No status or window filter: the old right was gated at read time, not
        // stored, and a manager who is later reactivated must regain it.
        $this->addSql("UPDATE clinic__clinic_memberships SET is_clinic_administrator = 1 WHERE role = 'MANAGER'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships DROP is_clinic_administrator');
    }
    ```
  - Notes: Decision 9bis. A clinic with no `MANAGER` row gets no administrator
    from this migration, and that is accepted: no such database exists outside a
    developer's machine, and `make load-fixtures` seeds the flag properly
    (Tasks 18, 19). Nothing here needs `ROW_NUMBER()`, so nothing here depends
    on MySQL 8.4.

- [x] **Task 9: Teach the Foundry factory the new field**
  - File: `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php`
  - Action: Add `'isClinicAdministrator' => false` to `defaults()` (l.110-124,
    which already enumerates `isDefault`). Add `asClinicAdministrator(): self`
    returning `$this->with(['isClinicAdministrator' => true])`. Leave
    `asManager()` (l.44-47) granting the role only.
  - Notes: `asManager()` deliberately no longer implies administration — that is
    the change, and integration tests needing an administrator must now say so.

#### Application

- [x] **Task 10: Accept the flag when a membership is created**
  - Files: `.../CreateClinicMembership/CreateClinicMembership.php` and its handler
  - Action: Add `public bool $isClinicAdministrator = false` as the last command
    parameter, and pass `$command->isClinicAdministrator` into
    `ClinicMembership::create()`.
  - Notes: A plain pass-through — **no re-seeding rule**. Rounds 1–4 had this
    handler call `countEffectiveAdministrators()` and force the flag on when the
    clinic had none; that rule belongs with the invariant it supports, and is
    batch 2. Keeping it out here also keeps the existing handler tests honest:
    a mocked repository auto-returns `0` for an `int` return type, so that rule
    would have fired silently in every test that did not stub it.

    The handler still refuses `VETERINARY` / `VETERINARY_ASSISTANT` at l.37-39.

- [x] **Task 11: Same for practitioner onboarding**
  - Files: `.../OnboardStaffMember/OnboardStaffMember.php` and its handler
  - Action: Add `bool $isClinicAdministrator = false` to the command (trailing,
    so existing call sites keep working) and pass it through to `create()`.
  - Notes: This is the path that makes `vet@kiveto.local` an owner-manager in one
    call. The credentials branch at l.108-110 is untouched — the role stays
    `VETERINARY`, so `FR-12345` survives.

- [x] **Task 12: Resolve permissions from the membership, not the role**
  - File: `.../ListPermissionsForUserInClinic/ListPermissionsForUserInClinicHandler.php`
  - Action: Replace `StaffRolePermissionMap::permissionsFor($membership->role())`
    (l.41) with `$membership->permissions()`. Drop the unused import.
  - Notes: The `isEffectiveAt()` early return above stays. This is the single
    seam: `ClinicPermissionGuard` and all **thirteen** settings controllers
    (eleven `MANAGE_STAFF`, two `MANAGE_BILLING`) need no change.

#### Presentation

- [x] **Task 13: Read the checkbox when a member joins**
  - File: `src/Presentation/Clinic/Controller/Settings/AddTeamMemberController.php`
  - Action: Read
    `$isClinicAdministrator = $request->request->getBoolean('isClinicAdministrator');`
    and pass it into **both** dispatch branches — `OnboardStaffMember` (l.82-96)
    and `CreateClinicMembership` (l.98-103).
  - Notes: Both branches, or a new veterinarian owner-manager silently loses the
    hat — which is exactly the case this change exists to serve. Decision 19.

- [x] **Task 14: Show the hat on the team page**
  - File: `templates/clinic/settings/team.html.twig`
  - Action:
    - Add a **"Gestion"** column: header, plus a cell rendering
      `{% if membership.isClinicAdministrator %}<span class="tag tone-amber">Gère la clinique</span>{% else %}<span class="cell-muted">—</span>{% endif %}`.
    - Change `roleTones` (**l.16**) from `'MANAGER': 'purple'` to
      `'MANAGER': 'violet'` — see Notes.
    - Add the administration checkbox to the **add form only**
      (near `isVisibleInAgenda`, l.172-175):
      `<input type="checkbox" name="isClinicAdministrator" value="1">` labelled
      *« Peut gérer la clinique (équipe, facturation, réglages) »*.
    - **Do not** touch the edit modal, which runs **l.187-226** — l.227 is the
      `{% endblock %}`, and deleting it breaks the template. Do **not** add a
      `data-administrator` attribute, and do **not** add a checkbox there
      (Decision 19).
    - **No grant/revoke buttons in this batch.** The column is read-only; batch 2
      adds the two POST forms beside the existing Désactiver/Réactiver form
      (l.89-94).
  - Notes: The new badge is `tone-amber`, not purple, so it does not collide with
    the role pill. But the role pill has a problem of its own: `roleTones` emits
    `tone-purple`, and **there has never been a `.tone-purple` rule anywhere in
    `assets/`** — `catalog.css` names that same colour `tone-violet` (l.235). So
    the `MANAGER` badge is unstyled today and would stay unstyled even after
    Task 16 copies catalog's rules. Renaming the token here is what actually
    fixes it, and it aligns the two pages on one vocabulary.

    The label on the checkbox must say what the hat *grants*, not name a role.
    Nothing in this task depends on `settings.js`.

- [x] **Task 15: Same checkbox in the publisher backoffice**
  - Files: `src/Presentation/Backoffice/Controller/ClinicMembershipController.php`
    and `templates/backoffice/clinic-memberships/new.html.twig`
  - Action: In the create action (route l.95, dispatch l.120), read
    `$request->request->getBoolean('isClinicAdministrator')` and pass it to
    `CreateClinicMembership`. Add the matching checkbox to `new.html.twig`.
  - Notes: Creation only — the grant/revoke row actions on the listing are
    batch 2, where they matter, because that is where they become the recovery
    path for a clinic that has lost its administrator.

    **Do not restructure this controller.** It holds several routes in one class
    against the project rule, but that is pre-existing.

- [x] **Task 16: Make the badge visible**
  - File: `assets/styles/pages/settings.css`
  - Action: Copy the `.tag` base rule and the nine `.tone-*` variants from
    `assets/styles/pages/catalog.css:220-241` into `pages/settings.css`. The
    tones this page needs are `tone-violet` (role `MANAGER`, after Task 14's
    rename), `tone-blue` (`VETERINARY`), `tone-green` (`VETERINARY_ASSISTANT`
    and the "Actif" status), `tone-slate` (`RECEPTIONIST` and "Désactivé") and
    `tone-amber` (the new administrator badge). Copy the whole set rather than
    cherry-picking — it is one vocabulary.

    Both files wrap their whole contents in `@layer components { … }`
    (`catalog.css:9-482`, `settings.css:7-214`), so paste **inside** that block,
    keeping the existing indentation. Dropped at the top level of the file the
    rules land in a different cascade layer and lose to Tailwind's utilities.
  - Notes: **There is no `tone-purple` rule and there never has been**:
    ```
    $ grep -rn "tone-purple" assets/   →  no output
    ```
    `catalog.css:235` calls that colour `tone-violet`. Task 14 renames the token
    in `roleTones`; without that rename, copying these rules still leaves the
    `MANAGER` badge unstyled and AC 15 still fails.

    The rules live only in `pages/catalog.css` and `pages/stock.css`, loaded by
    their own shells and **not** among the `@import`s in
    `assets/styles/tailwind.css:121-148`. `pages/settings.css` *is* imported
    (l.141) but defines no `.tag`. So every role and status badge on
    `/settings/team` renders unstyled today — a pre-existing bug this task fixes
    rather than inherits. Run `make assets` afterwards.

- [x] **Task 17: Repair the sidebar's refusal message**
  - File: `templates/components/layout/sidebar.html.twig`
  - Action: l.230 renders `<p class="popover-empty">Réservé à la gérance</p>` in
    the `{% else %}` branch of `{% if clinic_can('manage_staff') %}`. Replace the
    wording with *« Réservé aux membres qui gèrent la clinique »*. Leave the
    `clinic_can('manage_staff')` condition and the *« Gestion »* heading at l.219
    exactly as they are.
  - Notes: Decision 22. This string is not new work this change invents — it is
    an existing string that this change makes **false**. Until now, "la gérance"
    and "whoever holds `manage_staff`" were the same set of people, so the
    sentence was accurate. After Task 1 they are different sets, and a
    veterinarian who has just been given the hat would otherwise be told the
    section is reserved to managers on any clinic where they do not hold it.

    One line, and it is the only user-facing string in the app that this change
    silently invalidates. Both earlier drafts of this spec missed it, because
    they were looking at `/settings/team` and this sentence lives in the clinic
    switcher popover.

#### Fixtures and documentation

- [x] **Task 18: Make the demo look like a real independent practice**
  - File: `fixtures/Context/Clinic/Story/ClinicStaffProfileDataStory.php`
  - Action: Pass `isClinicAdministrator: true` on the two `OnboardStaffMember`
    dispatches for `$vetUser` (Paris l.77-90, Lyon l.114-127) and both
    `CreateClinicMembership` dispatches for `$managerUser` (l.92-97, l.106-111).
    Leave the six others without it. Rewrite the comment above `$managerUser`
    (**l.49-51** — l.48 is blank), which claims the manager is the only one who
    can open settings.
  - Notes: Satisfies CLAUDE.md §7 — both states of the flag, on both clinics,
    across several roles. `contractor@` and `vet2@` stay hatless on purpose:
    they are the "veterinarian who is not the owner" case.

- [x] **Task 19: Do not forget the other manager**
  - File: `fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php`
  - Action: `admin.clinic@kiveto.local` (l.24) is created as `MANAGER` on Lyon
    (l.33) and loaded by `fixtures/Dataset/ClinicDataset.php`. Give it
    `isClinicAdministrator: true`.
  - Notes: Miss this and Lyon loses an administrator the moment Decision 1 lands,
    because `MANAGER` no longer implies one.

- [x] **Task 20: Record the concept in the BC README**
  - File: `src/Context/Clinic/README.md`
  - Action: Add "clinic administrator" to Ubiquitous Language: *a membership that
    carries the `isClinicAdministrator` flag; grants `manage_staff` and
    `manage_billing` on top of whatever the clinical role grants, and is
    independent of that role.*
  - Notes: Do **not** yet state the business invariant *no write may leave a
    clinic without an effective administrator* — no such rule is enforced until
    batch 2, and a README that promises one is worse than one that says nothing.

#### Tests

- [x] **Task 21: Repair the call sites the change breaks**
  - Action: Tasks 3 and 7 add non-defaulted parameters. Verified counts at the
    time of writing: **5** calls to `ClinicMembership::create()`, **15** to
    `::reconstitute()` (fourteen in tests; the one production caller is
    `ClinicMembershipMapper.php:18`), **3** to `new MembershipListItem(...)`.
    Re-derive rather than trusting these:
    ```
    grep -rn 'ClinicMembership::\(create\|reconstitute\)(\|new MembershipListItem(' src tests fixtures --include=*.php
    ```
  - Notes: Rounds 1–4 also had a second, nastier breakage here — the creation
    rule calling `countEffectiveAdministrators()` on mocked repositories that
    auto-return `0`, silently forcing the flag on in tests that never mentioned
    it. Task 10 deliberately does not add that rule, so this batch has only the
    signature kind, which the compiler finds for you.

- [x] **Task 22: Domain tests**
  - Files: `ClinicMembershipTest`, `StaffRolePermissionMapTest`,
    `Event/ClinicMembershipCreatedTest`
  - Action: Cover `isClinicAdministrator()` and `permissions()` across four
    role/hat combinations — veterinarian with and without the hat, manager with
    and without — asserting the union contains no duplicates and that a hatted
    veterinarian holds `manage_staff` **and** `create_prescription`. Cover the
    creation event's new key and its `null`-preserving docblock.

    **Two existing assertions in `StaffRolePermissionMapTest` must be reworked,
    not silenced:**
    - `testManagerHasAllPermissions` (l.14-22) asserts `assertCount(9)` → becomes
      `7`, and asserts `assertContains(CREATE_PRESCRIPTION)`, which must **stay**
      true. Rename it; "all permissions" is no longer what it means.
    - `testEveryPermissionIsMappedToAtLeastOneRole` (l.63-79) becomes false.
      Rewrite it to assert every `ClinicPermission` case is granted by at least
      one role **or** by `administratorPermissions()`, preserving the design
      invariant it actually protects: no permission is unreachable.
  - Notes: `pullDomainEvents()` is destructive and `#[\NoDiscard]` — once per test.

- [x] **Task 23: Application tests**
  - Files: `Query/Staff/ListPermissionsForUserInClinic/…HandlerTest`
    (**new — none exists**), `CreateClinicMembership/…HandlerTest`,
    `OnboardStaffMember/…HandlerTest` (unit and integration)
  - Action: For the permission query, all four branches: no membership → `[]`;
    membership not effective → `[]`; veterinarian without the hat → no
    `manage_staff`; veterinarian with the hat → `manage_staff` **and**
    `create_prescription`, no duplicates. For the two creation handlers: the
    flag is passed through when set, and defaults to `false` when omitted.
  - Notes: `createStub()` for repositories and `DomainEventPublisher`.

    `ListPermissionsForUserInClinicHandler` has **no test today** — this is the
    gap that keeps the Clinic BC off 100%, and this task closes it. Its four
    branches are the contract the entire settings section rests on.

- [x] **Task 24: Persistence tests**
  - Files: `ClinicMembershipMapperTest`, `DoctrineClinicMembershipRepositoryTest`,
    `DoctrineMembershipAdminRepositoryTest`
  - Action: Mapper symmetry both ways. In the repository integration test, assert
    the flag survives an **update**, not only an insert — save → mutate the
    entity → save → reload. In the admin repository test, assert the DTO carries
    the flag.
  - Notes: The update assertion catches Decision 8 and is not reachable from a
    mocked unit test. Batch 2's grant/revoke is worthless without it.

- [x] **Task 25: Rebuild assets and go green**
  - Action: In order — `make assets` (never `asset-map:compile`), then
    **`make migrate-test-db`**, then `make ci` until green. Then walk the demo by
    hand — see Testing Strategy.
  - Notes: The migration step is not optional and `make ci` does **not** do it:
    `Makefile:414` chains only `php-cs-fixer.dry-run phpcs phpstan
    tailwind-build test`, and `test` (l.441-445) runs `bin/phpunit` alone. DAMA
    wraps each integration test in a rolled-back transaction; it does not create
    schema. Skip this and every integration test fails on
    `Unknown column 'is_clinic_administrator'`, which reads like a broken
    implementation rather than an unmigrated database. That `ci` does not chain
    it is a pre-existing gap — see Follow-ups.

    Do not commit. Djamil validates first.

### Acceptance Criteria

**How to read the markers.** `phpunit.xml` declares exactly two suites, `unit`
(l.23-25) and `integration` (l.26-28). There is no functional suite and no
`WebTestCase` anywhere in the project, so **nothing that goes through HTTP has
an automated home**. Each criterion below is therefore marked with where it is
actually verified. Treat `[manual]` as a step in the demo walk, not as a test
someone forgot to write.

- `[unit]` — `tests/Unit`, runs in `make ci`
- `[integration]` — `tests/Integration`, runs in `make ci`
- `[manual]` — the demo walk in Testing Strategy; nobody automates this today
- `[review]` — checked by reading the code or the pipeline output

Count: **8 automated, 12 manual, 2 review.**

**The change of purpose**

- [ ] **AC 1** `[manual]` Given `vet@kiveto.local` (`VETERINARY`, hat on) on the
  Paris clinic, when they load any page, then the "Paramètres" sidebar entry is
  present and `/settings` returns 200.
- [ ] **AC 2** `[manual]` Given the same account, when a prescription is
  prepared, then the prescriber still carries `FR-12345` and the `DR` title.
- [ ] **AC 3** `[manual]` Given `contractor@kiveto.local` (`VETERINARY`, no hat),
  then no sidebar entry is rendered and a direct GET to `/settings` returns 403.
- [ ] **AC 4** `[manual]` Given `accueil@kiveto.local` (receptionist), when they
  request `/settings/team`, then access is refused — unchanged behaviour,
  verified as a regression.

**Permission resolution**

- [ ] **AC 5** `[unit]` Given an effective membership with role `VETERINARY` and
  the hat, when `ListPermissionsForUserInClinic` runs, then the result contains
  `manage_staff`, `manage_billing` **and** `create_prescription`, with no
  duplicates.
- [ ] **AC 6** `[unit]` Given an effective `MANAGER` without the hat, then the
  result contains neither `manage_staff` nor `manage_billing`, **and still
  contains** `create_prescription`, `create_consultation` and
  `view_medical_record`.
- [ ] **AC 7** `[unit]` Given a membership that is disabled, not yet started, or
  expired, then the result is empty regardless of the hat.
- [ ] **AC 8** `[unit]` Given every case of `ClinicPermission`, then each is
  granted by at least one role **or** by `administratorPermissions()` — no
  permission is unreachable.

**Persistence and migration**

- [ ] **AC 9** `[integration]` Given a persisted membership, when the flag is
  changed on the entity and `save()` is called on the existing row, then
  reloading shows the new value. Both `save()` and `saveAll()`.
- [ ] **AC 10** `[unit]` Given a membership created with the flag, when
  `ClinicMembershipCreated` is inspected, then its payload carries
  `isClinicAdministrator`, and the payload still admits `null` for `validUntil`.
- [ ] **AC 11** `[unit]` Given the mapper, then `toDomain(toEntity($m))`
  preserves the flag in both directions.
- [ ] **AC 12** `[manual]` Given a database migrated without reloading fixtures,
  when the migration runs, then every `MANAGER` row carries the flag and no
  other row does; and `down()` removes the column cleanly.
- [ ] **AC 13** `[integration]` Given the admin read repository, then
  `MembershipListItem` carries the flag for each row.

**The team page and the backoffice**

- [ ] **AC 14** `[manual]` Given a manager on `/settings/team`, when the page
  renders, then each row shows whether that member holds the hat.
- [ ] **AC 15** `[manual]` Given the same page, then **every** badge on it —
  role, status and administration — is styled rather than bare text. Verified in
  a browser, not by reading the template.
- [ ] **AC 16** `[manual]` Given the add-member form with the administration box
  ticked, when a new veterinarian is submitted, then they are created with role
  `VETERINARY`, credentials intact, and the hat on.
- [ ] **AC 17** `[manual]` Given the ordinary edit modal submitted for a member —
  role, contract and phone — when it succeeds, then that member's hat is
  unchanged, and `isClinicAdministrator` appears nowhere in that form's payload.
- [ ] **AC 18** `[manual]` Given the publisher backoffice creation form with the
  box ticked, when a membership is created, then it holds the hat.
- [ ] **AC 19** `[manual]` Given a member who does **not** hold the hat, when
  they open the clinic switcher popover, then the refused branch reads
  *« Réservé aux membres qui gèrent la clinique »* — not *« Réservé à la
  gérance »*, which names a role rather than the capability.

**Fixtures and pipeline**

- [ ] **AC 20** `[manual]` Given rebuilt fixtures, then `vet@`, `manager@` and
  `admin.clinic@` hold the hat where they are members, and `contractor@`,
  `vet2@`, `assistant@`, `accueil@`, `vet.lyon@` and `assistant.lyon@` do not.
- [ ] **AC 21** `[review]` Given a freshly migrated test database, when `make ci`
  runs, then `php-cs-fixer.dry-run`, `phpcs`, `phpstan` (level max),
  `tailwind-build` and the full test suite all pass, with no deprecation, notice
  or warning.
- [ ] **AC 22** `[review]` Given the Clinic bounded context, when coverage is
  measured, then every line added or modified is covered, and
  `ListPermissionsForUserInClinicHandler` — untested before this change — is
  fully covered.

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

Branch `feature/clinic-settings-and-staff`, on top of `1dd48cee`
("feat(clinic): put the settings where they can be found"), which introduced the
`/settings` pages, `ClinicPermissionGuard` and the sidebar entry this change
makes reachable to the lead veterinarian.

Nothing here depends on a MySQL version beyond what Doctrine already requires.
The `ROW_NUMBER()` dependency of rounds 1–4 was deleted with Decision 9bis.

**Batch 2** (`tech-spec-clinic-administration-invariant.md`) depends on this one
and must not be started before it is merged.

### Testing Strategy

**Unit — domain.** `ClinicMembershipTest` gains `permissions()` across four
role/hat combinations. `StaffRolePermissionMapTest` needs two tests reworked,
not silenced — Task 22.

**Unit — application.** `ListPermissionsForUserInClinicHandler`, untested today,
whose four branches are the contract the entire settings section rests on. This
is the highest-value test in the batch.

**Integration.** Two things unit tests cannot reach: the flag surviving an
update through both write paths, and the admin read repository carrying it into
the DTO. DAMA rolls each test back.

**Manual — walking the demo.** Rebuild fixtures, then:

1. Sign in as `vet@kiveto.local` / `vet` → "Paramètres" in the sidebar;
   `/settings` and `/settings/billing` open. *(AC 1)*
2. Still as `vet@`, prepare a prescription in a consultation → prescriber shows
   `Dr` and `FR-12345`. **This is the regression that matters** — exactly what
   the rejected `MANAGER` promotion would have broken. *(AC 2)*
3. As `contractor@kiveto.local` / `contractor` → no sidebar entry; `/settings`
   returns 403. *(AC 3)*
4. As `accueil@kiveto.local` / `accueil` → `/settings/team` returns 403. *(AC 4)*
5. As `vet@` on `/settings/team` → the badges are legible, not unstyled text,
   and the "Gestion" column shows the hat on `vet@` and `manager@` only.
   *(AC 14, 15)*
6. Add a new veterinarian through the form with the box ticked → created with
   role `VETERINARY`, credentials intact, hat on. *(AC 16)*
7. Edit `vet@` through the ordinary edit modal, changing the phone → saved, hat
   untouched. *(AC 17)*
8. Switch to Lyon → `vet@`, `manager@` and `admin.clinic@` hold the hat there;
   `vet.lyon@` does not. *(AC 20)*
9. In the publisher backoffice, create a membership with the box ticked → it
   holds the hat. *(AC 18)*
10. On a database that was migrated rather than reloaded, confirm every
    `MANAGER` row carries the flag; roll the migration back and confirm the
    column is gone. *(AC 12)*

### Notes

The originating question, 2026-08-24: after the settings pages shipped,
`vet@kiveto.local` could not see the "Paramètres" entry, and the account that
could — `manager@kiveto.local` — is not who runs an independent practice. The
first analysis proposed promoting `vet@` to `MANAGER`; checking it against the
code showed that would strip the veterinary credentials.

`make ci` does **not** measure coverage — the 100%-per-BC rule is enforced by
review. The Clinic BC is **not** at 100% today:
`ListPermissionsForUserInClinicHandler` has no test. This change closes that gap
rather than inheriting it.

#### Where this is most likely to go wrong

Ranked by how quietly each one fails:

1. **The forgotten update branch** (Task 6). Setters are enumerated by hand in
   both `save()` and `saveAll()`. Miss one and the hat persists on creation but
   never on edit — and nothing in *this* batch edits it, so the defect stays
   invisible until batch 2's grant button silently does nothing. AC 9 exists for
   this, and it must cover both methods.
2. **The unread read model** (Task 7). Field added to the DTO but not to the
   SQL — the badge column shows "—" for everyone, and nobody can tell who holds
   the hat.
3. **The one-branch controller** (Task 13). `AddTeamMemberController` dispatches
   two different commands; wiring the checkbox into only one drops the hat for
   exactly the case this change exists to serve.
4. **The forgotten second manager** (Task 19). `admin.clinic@` lives in a
   different story file from the rest of the staff. Miss it and Lyon has one
   administrator instead of two, which is only noticed in batch 2 when the
   invariant starts refusing things.
5. **The unstyled badge** (Tasks 14, 16). Copying catalog's `.tone-*` rules
   without renaming `roleTones`' `purple` to `violet` leaves the `MANAGER` pill
   exactly as broken as it is today, and AC 15 fails for a reason that looks
   like a CSS build problem.

#### Known limitations, deliberately accepted

- **A clinic can be made temporarily unreachable** by disabling its only
  hat-wearing membership, or by closing that membership's validity window.
  Neither clears the flag, and both are undone from the publisher backoffice's
  existing `enable` / `changeValidity` row actions, which run outside
  `ClinicPermissionGuard`. Batch 2 refuses the write instead of relying on the
  recovery.
- **The AccessControl projection** is now role-only (Decision 6). Nothing reads
  it today.
- **`MANAGER` and `VETERINARY` become permission-identical** (Decision 14) — see
  the **Open decision**.
- **The hat is per-membership**, so a user at two clinics is granted or refused
  it independently at each. Intended, but a group operator ticks the box twice.
- **A migrated-but-not-reloaded database with no `MANAGER` row** ends up with no
  administrator (Decision 9bis). Accepted: `make load-fixtures` is the answer,
  and no such database exists outside a developer's machine.

#### Follow-ups (not in this change)

- **Batch 2**, `tech-spec-clinic-administration-invariant.md` — grant and revoke
  after creation, the no-worsening invariant, and the backoffice recovery path.
  It also carries the pre-existing security work below, which it is adjacent to.
- **Security: IDOR and missing CSRF on the existing team row actions.** The
  `disable` and `enable` actions, and the backoffice's `role`, `engagement` and
  `validity` actions, take `{membershipId}` off the URL with **no check that it
  belongs to the caller's clinic**, and the clinic-side forms carry **no CSRF
  token** (`templates/clinic/settings/team.html.twig:89-94`;
  `config/packages/csrf.yaml` sets no `stateless_token_ids`). A manager of one
  clinic can disable a member of another by editing a URL, and any page a
  manager visits can trigger it. Batch 2 adds both protections to the routes it
  creates and deliberately does not touch these — that is a security branch of
  its own, and it should be scheduled rather than left implicit.
- **`make ci` does not migrate the test database.** `Makefile:414` chains
  `php-cs-fixer.dry-run phpcs phpstan tailwind-build test`, and `test`
  (l.441-445) runs `bin/phpunit` alone, so any schema change makes every
  integration test fail in a way that looks like broken code. Task 25 works
  around it with an explicit `make migrate-test-db`; chaining it into `ci` would
  fix it for everyone.
- **There is no functional test suite.** Twelve of this batch's twenty-two criteria
  are verified by hand because nothing else can verify them. A `WebTestCase`
  suite would move AC 1, 3, 4, 14, 16, 17, 18 and 19 into `make ci`. Worth
  scheduling before the surface grows further.
- Every `src/Context/Clinic/Application/Exception/*` extends `\RuntimeException`
  and therefore escapes the team controllers' `catch (\DomainException |
  \InvalidArgumentException)` clauses, producing a 500 where a flash is
  intended. Batch 2 hits this immediately and adds explicit catches for its own
  exception; the pattern deserves its own branch.
- `Presentation/Backoffice/Controller/ClinicMembershipController` holds several
  routes in one class, against the single-action rule.
- `src/System/AccessControl/Application/Listener/*` imports Clinic domain events
  directly, crossing a bounded-context boundary that should go through a port.
- `.tag` / `.tone-*` are defined per-page in `catalog.css` and `stock.css` and
  duplicated by Task 16. They belong in `assets/styles/components/`.
