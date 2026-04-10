---
title: 'Rename ClinicalCare BC to Consultation'
slug: 'rename-clinical-care-to-consultation'
created: '2026-04-10'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3 (attribute mapping, single default EM with per-BC mappings)
  - Doctrine Migrations Bundle (one path per BC under migrations/<BC>/)
  - Composer PSR-4 (App\ → src/)
  - PHPStan max, PHPUnit, PHPCS, php-cs-fixer
files_to_modify:
  - src/Context/ClinicalCare/** (62 files — rename dir to Consultation/)
  - tests/Unit/Context/ClinicalCare/** (16 files)
  - tests/Integration/Context/ClinicalCare/** (6 files)
  - fixtures/Context/ClinicalCare/Factory/ConsultationEntityFactory.php
  - migrations/ClinicalCare/Version20260201191325.php (delete + recreate under migrations/Consultation/)
  - config/packages/doctrine.yaml (lines 66-71, including alias)
  - config/packages/doctrine_migrations.yaml (line 11, namespace + path)
  - config/services.yaml (lines 273-296)
  - Makefile (lines 254, 291-293)
  - CLAUDE.md (line 20 — BC list)
  - CONTRIBUTING.md (lines 98, 105)
  - src/Context/ClinicalCare/README.md
  - src/Presentation/Clinic/Controller/Consultation/** (8 controllers — use statements)
  - src/Context/Scheduling/README.md (ClinicalCare reference)
code_patterns:
  - BoundedContextPrefixNamingStrategy derives table prefix from 3rd namespace segment — only hardcoded SQL needs manual update, ORM-managed tables change automatically
  - BOUNDED_CONTEXT string constant in 8 domain event classes
  - Hardcoded SQL table names in DoctrineConsultationReadRepository (3 queries referencing 3 distinct tables)
  - Makefile targets use hyphenated BC name (clinical-care-migrations)
test_patterns:
  - tests/Unit/<bucket>/<BC>/... mirrors src/<bucket>/<BC>/...
  - tests/Integration/<bucket>/<BC>/... mirrors src/<bucket>/<BC>/...
  - Foundry factories in fixtures/Context/<BC>/Factory/
---

# Tech-Spec: Rename ClinicalCare BC to Consultation

**Created:** 2026-04-10

## Overview

### Problem Statement

The bounded context is named `ClinicalCare` but its central domain
concept is the Consultation aggregate. The name is verbose and
inconsistent with the Presentation layer controllers already reorganized
under `Consultation/`. Renaming aligns the BC name with the ubiquitous
language.

### Solution

Mechanical rename of the entire BC: directory, namespace, table prefix,
Doctrine mapping, services config, migration, event BOUNDED_CONTEXT
constant, tests, fixtures, Presentation imports, and READMEs. Alpha
product — tables are recreated from scratch (delete old migration,
create new).

### Scope

**In Scope:**

- Rename directory `src/Context/ClinicalCare/` → `src/Context/Consultation/`
- Rename namespace `App\Context\ClinicalCare` → `App\Context\Consultation` in all files
- Rename table prefix `clinical_care__` → `consultation__` (3 tables: `consultations`, `clinical_notes`, `performed_acts`)
- Update `BOUNDED_CONTEXT` constant from `'clinical-care'` to `'consultation'` in all 8 domain events
- Update `config/packages/doctrine.yaml` mapping entry (key, dir, prefix, alias)
- Update `config/services.yaml` service definitions (13 entries)
- Update `config/packages/doctrine_migrations.yaml` namespace + path
- Delete old migration, create new from-scratch migration under `migrations/Consultation/`
- Move and update all tests under `tests/Unit/Context/ClinicalCare/` and `tests/Integration/Context/ClinicalCare/`
- Move and update fixtures under `fixtures/Context/ClinicalCare/`
- Update hardcoded SQL table names in `DoctrineConsultationReadRepository` (3 queries, 3 distinct table names)
- Update `use` statements in 8 Presentation controllers under `src/Presentation/Clinic/Controller/Consultation/` (F1/F6)
- Update `src/Context/ClinicalCare/README.md` (s/ClinicalCare/Consultation/ + table names)
- Update `CLAUDE.md` and `CONTRIBUTING.md` BC references
- Update `src/Context/Scheduling/README.md` ClinicalCare reference (F2)
- Update Makefile migration targets

**Out of Scope:**

- Renaming domain sub-concepts (ClinicalNote, PerformedAct stay as-is)
- Functional changes to consultation logic
- Rewrite of README content
- Legacy docs under `docs/legacy/ClinicalCare/` (left as-is)
- BMAD planning artifacts under `_bmad-output/` (left as-is)

## Context for Development

### Architectural Notes (from adversarial review)

| # | Finding | Resolution |
|---|---------|------------|
| F1 | Presentation controllers import `ClinicalCare` namespace — missed in original scope | Added to scope: update `use` statements in 8 controllers |
| F6 | Spec claimed "no cross-BC dependencies" — false, Presentation is a consumer | Corrected: Presentation controllers are explicit in scope |
| F3 | AC-2 grep scope too narrow | Expanded AC-2 to include CLAUDE.md, CONTRIBUTING.md, READMEs, Makefile |
| F7 | Migration directory `migrations/Consultation/` not explicit | Made explicit in Task 8 |
| F9 | Task ordering creates broken intermediate state | Clarified: all renames done atomically before config switch |
| F13 | Doctrine alias change not called out | Explicit in Task 4: alias `ClinicalCare` → `Consultation` |
| F14 | Only hardcoded SQL needs manual update, ORM tables change auto | Documented in code_patterns and Technical Decisions |
| F12 | AC-6 hardcodes test count 1134 — fragile | Changed to "same count as before rename" |
| F2 | Scheduling/README.md references ClinicalCare | Added to scope |
| F15 | Makefile `.PHONY` list missing migration target | Added to Task 6: register `consultation-migrations` in `.PHONY` |
| F16 | services.yaml section comment `# CLINICAL CARE` not caught by find/replace | Added explicit comment rename to Task 5 |
| F17 | "13 entries" count wrong in scope | Corrected to "10 service definitions" |
| F18 | "66 files" count wrong | Corrected to "62 files" |
| F20 | AC-2 should list intentional exclusions | Added `docs/legacy/` and `_bmad-output/` as explicit exclusions |

### Codebase Patterns

- `BoundedContextPrefixNamingStrategy` derives table prefix from the 3rd namespace segment — renaming the directory/namespace automatically changes the prefix for all ORM-managed entities. **Only hardcoded SQL in `DoctrineConsultationReadRepository` needs manual update.** No Doctrine attribute/annotation changes needed.
- Domain events use `BOUNDED_CONTEXT` string constant for event naming
- Presentation controllers under `Consultation/` were moved in a previous refactor but their `use` statements still point at `App\Context\ClinicalCare`
- Makefile has per-BC migration targets using hyphenated names

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/ClinicalCare/` (62 files) | Entire BC source to rename |
| `tests/Unit/Context/ClinicalCare/` (16 files) | Unit tests to move |
| `tests/Integration/Context/ClinicalCare/` (6 files) | Integration tests to move |
| `fixtures/Context/ClinicalCare/Factory/ConsultationEntityFactory.php` | Fixture factory to move |
| `src/Presentation/Clinic/Controller/Consultation/` (8 controllers) | `use` statements to update (F1) |
| `config/packages/doctrine.yaml` (lines 66-71) | Doctrine mapping entry + alias |
| `config/packages/doctrine_migrations.yaml` (line 11) | Migration namespace + path |
| `config/services.yaml` (lines 273-296) | 10 service definitions |
| `Makefile` (lines 254, 291-293) | Migration targets |
| `migrations/ClinicalCare/Version20260201191325.php` | Old migration to delete |
| `CLAUDE.md` (line 20) | BC list |
| `CONTRIBUTING.md` (lines 98, 105) | Naming convention examples |
| `src/Context/Scheduling/README.md` | ClinicalCare reference (F2) |

### Technical Decisions

1. **No `git mv`** — use `cp -r` + update namespaces + delete old dir for clean result.
2. **Table prefix changes automatically** via `BoundedContextPrefixNamingStrategy`. No ORM attribute changes needed — only the 3 hardcoded SQL queries in `DoctrineConsultationReadRepository` need manual update (F14).
3. **Alpha product** — delete old migration, create new from scratch under `migrations/Consultation/`. No data migration needed.
4. **`BOUNDED_CONTEXT = 'consultation'`** — consistent with `'clinic-staff'` convention.
5. **Legacy docs untouched** — `docs/legacy/ClinicalCare/` and `_bmad-output/` left as-is.
6. **Doctrine alias** must change from `ClinicalCare` to `Consultation` in doctrine.yaml (F13).
7. **Presentation controllers** must have their `use` statements updated — they are consumers of the ClinicalCare namespace (F1/F6).
8. **`composer dump-autoload` is critical** — must run before any verification since PSR-4 maps namespaces to directories.
9. **`messenger.yaml`: no changes needed** — routing is interface-based, not namespace-based (F22).
10. **`phpunit.xml`: no changes needed** — test suites scan `tests/Unit` and `tests/Integration` broadly (F23).

## Implementation Plan

### Tasks

Tasks are ordered by dependency. The whole sequence lands in a single
commit. All renames are done before the config switch to avoid broken
intermediate state (F9). Run `make ci` only after Task 10.

- [ ] **Task 1: Copy source directory with new namespace**
  - Action: `cp -r src/Context/ClinicalCare src/Context/Consultation`
  - Action: Find/replace `App\Context\ClinicalCare` → `App\Context\Consultation` in all files under `src/Context/Consultation/`
  - Action: Find/replace `'clinical-care'` → `'consultation'` in BOUNDED_CONTEXT constants (8 event files)
  - Action: Find/replace `clinical_care__` → `consultation__` in hardcoded SQL (3 queries in `DoctrineConsultationReadRepository`: `clinical_care__consultations`, `clinical_care__clinical_notes`, `clinical_care__performed_acts`)
  - Action: Update `README.md` (s/ClinicalCare/Consultation/ and table name prefix)
  - Notes: Do NOT delete `src/Context/ClinicalCare/` yet.

- [ ] **Task 2: Copy and update tests**
  - Action: `cp -r tests/Unit/Context/ClinicalCare tests/Unit/Context/Consultation`
  - Action: `cp -r tests/Integration/Context/ClinicalCare tests/Integration/Context/Consultation`
  - Action: Find/replace `App\Tests\Unit\Context\ClinicalCare` → `App\Tests\Unit\Context\Consultation` in all copied test files
  - Action: Find/replace `App\Tests\Integration\Context\ClinicalCare` → `App\Tests\Integration\Context\Consultation`
  - Action: Find/replace `App\Context\ClinicalCare` → `App\Context\Consultation` in `use` statements

- [ ] **Task 3: Copy and update fixtures**
  - Action: `cp -r fixtures/Context/ClinicalCare fixtures/Context/Consultation`
  - Action: Find/replace `App\Fixtures\Context\ClinicalCare` → `App\Fixtures\Context\Consultation`
  - Action: Find/replace `App\Context\ClinicalCare` → `App\Context\Consultation` in `use` statements

- [ ] **Task 4: Update Presentation controllers (F1/F6)**
  - Action: Find/replace `App\Context\ClinicalCare` → `App\Context\Consultation` in all `use` statements under `src/Presentation/Clinic/Controller/Consultation/` (8 controller files)
  - Notes: Only `use` statements change — the controller namespaces and file locations stay as-is.

- [ ] **Task 5: Update configuration files**
  - File: `config/packages/doctrine.yaml` — rename mapping key from `ClinicalCare` to `Consultation`, update `dir`, `prefix`, and `alias` (F13)
  - File: `config/packages/doctrine_migrations.yaml` — rename `DoctrineMigrations\ClinicalCare` → `DoctrineMigrations\Consultation`, update path to `migrations/Consultation` (F7)
  - File: `config/services.yaml` — rename section comment `# BOUNDED CONTEXT: CLINICAL CARE` → `# BOUNDED CONTEXT: CONSULTATION` (F16), then find/replace all `ClinicalCare` → `Consultation` in service definitions

- [ ] **Task 6: Update Makefile**
  - Rename target `clinical-care-migrations` → `consultation-migrations`
  - Update the target's migration namespace to `DoctrineMigrations\Consultation`
  - Update filter expression from `/^clinical_care__/` to `/^consultation__/`
  - Update the `migrations` meta-target dependency
  - Add `consultation-migrations` to the `.PHONY` declaration (F15)

- [ ] **Task 7: Update documentation**
  - File: `CLAUDE.md` — update BC list (ClinicalCare → Consultation)
  - File: `CONTRIBUTING.md` — update naming convention examples
  - File: `src/Context/Scheduling/README.md` — update ClinicalCare reference (F2)

- [ ] **Task 8: Delete old directories and migration**
  - Action: `rm -rf src/Context/ClinicalCare`
  - Action: `rm -rf tests/Unit/Context/ClinicalCare`
  - Action: `rm -rf tests/Integration/Context/ClinicalCare`
  - Action: `rm -rf fixtures/Context/ClinicalCare`
  - Action: `rm -rf migrations/ClinicalCare`

- [ ] **Task 9: Create new migration**
  - Action: Create `migrations/Consultation/Version20260410000001.php`
  - Namespace: `DoctrineMigrations\Consultation`
  - Tables: `consultation__consultations`, `consultation__clinical_notes`, `consultation__performed_acts`
  - Copy SQL structure from old migration, replace `clinical_care__` prefix with `consultation__`

- [ ] **Task 10: Clear caches, dump autoload, run `make ci`**
  - Action: `rm -rf var/cache/*`
  - Action: `composer dump-autoload` (**critical** — PSR-4 must discover new namespace, F11)
  - Action: `make ci`
  - Notes: Fix any failures before proceeding.

- [ ] **Task 11: Verify no residual references**
  - Action: `grep -rn 'ClinicalCare\|clinical_care\|clinical-care' --include='*.php' --include='*.yaml' --include='*.twig' --include='*.md' src/ tests/ fixtures/ config/ Makefile CLAUDE.md CONTRIBUTING.md`
  - Expected: Zero matches (except `docs/legacy/` and `_bmad-output/` which are out of scope)

### Acceptance Criteria

- [ ] **AC-1 (CI green):** Given the rename is complete, when `make ci`
  is executed, then it exits 0 — all checks pass with zero warnings.

- [ ] **AC-2 (No residual ClinicalCare references):** Given the rename
  is complete, when searching for `ClinicalCare`, `clinical_care`, or
  `clinical-care` in `src/`, `tests/`, `fixtures/`, `config/`,
  `Makefile`, `CLAUDE.md`, `CONTRIBUTING.md`, and all BC `README.md`
  files, then zero matches are found. Intentional exclusions:
  `docs/legacy/ClinicalCare/` (historical) and `_bmad-output/`
  (planning artifacts). (F3, F20)

- [ ] **AC-3 (Namespace correct):** Given the rename is complete, when
  listing files under `src/Context/Consultation/`, then all PHP files
  have namespace `App\Context\Consultation\...`.

- [ ] **AC-4 (Tables renamed):** Given the new migration is applied,
  when inspecting the database schema, then `consultation__consultations`,
  `consultation__clinical_notes`, and `consultation__performed_acts`
  exist. No `clinical_care__*` tables exist.

- [ ] **AC-5 (Events updated):** Given the rename is complete, when
  reading all domain event files, then `BOUNDED_CONTEXT = 'consultation'`
  in all 8 events.

- [ ] **AC-6 (Tests pass):** Given the rename is complete, when running
  the full test suite, then all tests pass with the same count as before
  the rename. (F12)

- [ ] **AC-7 (Old directories gone):** Given the rename is complete,
  when checking the filesystem, then `src/Context/ClinicalCare/`,
  `tests/*/Context/ClinicalCare/`, `fixtures/Context/ClinicalCare/`,
  and `migrations/ClinicalCare/` do not exist.

## Additional Context

### Dependencies

- No external dependencies. Pure mechanical rename.
- Must be done on a clean branch from master (current: `chore/rename-clinical-care-to-consultation`).

### Testing Strategy

- No new tests needed — existing tests are moved and their namespaces updated.
- `make ci` validates everything: cs-fixer, phpcs, phpstan, tests.
- Test count must remain identical before and after rename.

### Notes

- **Risk: low.** This is a mechanical find/replace with no logic changes.
- **Presentation controllers (F1/F6)** are the most likely source of bugs — their `use` statements must be updated even though their files/namespaces don't move.
- **`composer dump-autoload` (F11)** is critical and must run before verification.
- **Makefile targets** are easy to miss — explicit task for them.
- **Legacy docs** under `docs/legacy/ClinicalCare/` and BMAD artifacts under `_bmad-output/` are intentionally left untouched (F10).
- **Suggested single commit:** `refactor: rename ClinicalCare BC to Consultation`
