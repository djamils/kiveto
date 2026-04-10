---
title: 'Reorganize src/ into Context, System, Shared, Presentation'
slug: 'reorganize-src-context-system'
created: '2026-04-09'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine ORM 3 (attribute mapping, **single default EM** with 9 per-BC mappings under `orm.mappings` — verified in adversarial review)
  - Doctrine Migrations Bundle (one path per BC under migrations/<BC>/, namespace DoctrineMigrations\<BC>)
  - Symfony Messenger (multi-bus CQRS + events + integration)
  - Composer PSR-4 (App\ → src/ — stays unchanged)
  - PHPStan max, PHPUnit, PHPCS, php-cs-fixer
  - Zenstruck Foundry (fixtures)
files_to_modify:
  - src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php
  - tests/Unit/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategyTest.php
  - config/packages/doctrine.yaml
  - config/services.yaml
  - src/Shared/README.md
  - src/<all BCs>/** (move + namespace rename)
  - tests/Unit/<all BCs>/**, tests/Integration/<all BCs>/** (mirror move)
  - fixtures/<all BCs>/** (mirror move)
  - migrations/<BC>/*.php (only the `use App\<BC>\…` lines inside file bodies; folders don't move)
  - rector.php (throwaway, added then deleted in same PR)
code_patterns:
  - Per-BC DDD layout: <BC>/{Domain,Application,Infrastructure}
  - Doctrine entities live under <BC>/Infrastructure/Persistence/Doctrine/Entity/
  - Table prefix derived from the 2nd namespace segment by BoundedContextPrefixNamingStrategy
  - Cross-BC communication only via Application Ports (Adapter under <BC>/Infrastructure/Adapter/<OtherBC>/)
test_patterns:
  - tests/Unit/<BC>/... mirrors src/<BC>/...
  - tests/Integration/<BC>/... mirrors src/<BC>/...
  - 100% line coverage per BC except Presentation
---

# Tech-Spec: Reorganize src/ into Context, System, Shared, Presentation

**Created:** 2026-04-09

## Overview

### Problem Statement

All folders currently sit at the root of `src/` with no semantic
distinction: business BCs (Clinic, Animal, Client, ClinicalCare,
Scheduling), cross-cutting DDD-structured domains (IdentityAccess,
AccessControl, Translation), technical building blocks (Shared) and the
HTTP layer (Presentation) all live at the same level despite having very
different natures. This makes the architectural intent invisible from
the file tree and lets unwanted couplings sneak in.

This spec covers **Epic 1 of a 3-epic refactor**. Epics 2 and 3
(extracting `ClinicMembership` out of `AccessControl`, and the
permissions model overhaul) are out of scope here and will be planned
separately.

### Solution

Introduce a four-bucket layout under `src/`:

- `src/Context/<BC>/` — veterinary business BCs
- `src/System/<BC>/` — cross-cutting DDD-structured domains
- `src/Shared/` — technical building blocks (unchanged location)
- `src/Presentation/` — HTTP layer (unchanged location for now)

Move the existing folders into their bucket and rename namespaces
accordingly. **No logic change, no file content change beyond namespace
declarations and `use` statements.** Tests, fixtures and config are
updated to mirror the new structure. `make ci` must be green at the end.

### Scope

**In Scope:**

- Move `src/Clinic`, `src/ClinicalCare`, `src/Animal`, `src/Client`,
  `src/Scheduling` → `src/Context/<BC>/`
- Move `src/IdentityAccess`, `src/AccessControl`, `src/Translation` →
  `src/System/<BC>/` (rationale: `System/` = cross-cutting domains
  with a full DDD structure that are NOT veterinary business)
- Rename namespaces: `App\<BC>\…` → `App\Context\<BC>\…` or
  `App\System\<BC>\…`
- Mirror the move under `tests/Unit/`, `tests/Integration/`,
  `fixtures/`
- Update every `use` statement, FQCN string, and DI/config reference
  across the repo, including `*.php`, `*.yaml`, `*.xml`, `*.neon`,
  PHPDoc annotations, Foundry factory string FQCNs, and existing
  Doctrine migration files under `migrations/` (the product is in
  alpha, no live env, so editing past migrations is acceptable)
- Update `composer.json` autoload (if needed — the `App\` prefix
  stays), `config/services.yaml`, `config/packages/doctrine.yaml`
  (entity manager mappings per BC), PHPStan, PHPUnit, PHPCS,
  php-cs-fixer config
- Audit and, if needed, patch
  `App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy`
  so it produces the **exact same** table prefixes after the namespace
  change (critical: must NOT cause a schema diff). The strategy must
  skip the `Context\` and `System\` segments when deriving the prefix.
- Document in `src/Shared/README.md` the precise rule: "`Shared/` may
  host buses, publishers and abstract event classes, but contains no
  concrete business `DomainEvent` and never `dispatch()`es directly —
  dispatch always originates from an aggregate inside a BC." Verify it
  currently holds.
- `make ci` is green

**Out of Scope:**

- Reorganizing `src/Presentation/` by feature instead of by app/BC
  (deferred)
- Extracting `ClinicMembership` from `AccessControl` to
  `Context/Clinic/Domain/Staff/` (Epic 2)
- The permissions model overhaul: `RoleAssignment`, `RolePermission`,
  Clinic→AccessControl event flow (Epic 3)
- Any change to business logic, public API, DB schema or migrations
- Reorganizing the `Shared/` internal structure

## Context for Development

### Codebase Patterns

- **Per-BC DDD layout.** Each BC follows
  `<BC>/{Domain,Application,Infrastructure}`. Doctrine entities live
  at `<BC>/Infrastructure/Persistence/Doctrine/Entity/`. The naming
  strategy depends on this convention.
- **Multi entity-manager via attribute mapping.** Each BC is
  registered as its own Doctrine mapping in
  `config/packages/doctrine.yaml` with explicit `dir:` and `prefix:`.
  8 entries today: `IdentityAccess`, `Translation`, `Clinic`,
  `AccessControl`, `Client`, `Animal`, `Scheduling`, `ClinicalCare`.
- **`Client` mapping is the odd one out.** Its `prefix:` is
  `App\Client\Infrastructure\Persistence\Doctrine` (no `\Entity`
  suffix) and its `dir:` matches. **Reason confirmed in Step 2:**
  `Client` has a `Doctrine/Embeddable/PostalAddressEmbeddable.php`
  alongside `Doctrine/Entity/`, and Doctrine must scan both
  directories. The new mapping must reproduce this asymmetry exactly:
  `App\Context\Client\Infrastructure\Persistence\Doctrine` /
  `%kernel.project_dir%/src/Context/Client/Infrastructure/Persistence/Doctrine`.
  The naming-strategy regex is unaffected: embeddables produce no
  table of their own (they are inlined into the parent entity), so
  only `ClientEntity` and `ContactMethodEntity` — both under
  `Entity/` — feed the strategy.
- **Migrations are namespaced `DoctrineMigrations\<BC>`**, NOT under
  `App\`. Their physical location is `migrations/<BC>/`, outside of
  `src/`. They do NOT move and their declared namespace does NOT
  change. Only `use App\<BC>\…` references inside the file bodies are
  rewritten by Rector.
- **Messenger routing** in `config/packages/messenger.yaml` uses only
  `App\Shared\…` FQCNs (middlewares) and the two abstract event
  interfaces (`DomainEventInterface`, `IntegrationEventInterface`).
  No BC-specific FQCN appears in messenger config — nothing to update
  there.
- **Composer autoload stays `App\: src/`.** The PSR-4 prefix is
  unchanged because the `App\` root is preserved; only the second
  segment shifts (`Context\` or `System\` is inserted).
- **PHPUnit and PHPStan configs reference `src/`, `tests/`,
  `migrations/`, `fixtures/` as flat roots.** No per-BC paths to
  rename. Coverage thresholds (if any) are not declared per-BC in
  these files.

### THE BLOCKING RISK — investigation result

**`BoundedContextPrefixNamingStrategy::prefixFor()` will silently
break** without a patch.

Current regex (line 82):

```php
$pattern = '/^App\\\\([^\\\\]+)\\\\Infrastructure\\\\Persistence\\\\Doctrine\\\\Entity\\\\/';
```

It captures the **second** namespace segment. For
`App\Clinic\Infrastructure\Persistence\Doctrine\Entity\Foo`, the
captured value is `Clinic` → table prefix `clinic__`. After the
refactor, the FQCN becomes
`App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\Foo`
and the captured value would become `Context` → all 5 business BCs
would collapse onto a single `context__` prefix and all 3 system BCs
onto a single `system__` prefix. Catastrophic schema drift.

**Required patch (mandatory part of this epic):**

```php
$pattern = '/^App\\\\(?:Context|System)\\\\([^\\\\]+)\\\\Infrastructure\\\\Persistence\\\\Doctrine\\\\Entity\\\\/';
```

After the patch, `App\Context\Clinic\…` captures `Clinic` again,
producing `clinic__` exactly as before. The Client BC still works
because its entities live under `Doctrine\Entity\` despite the YAML
`prefix:` quirk (the regex matches the FQCN, not the YAML prefix).

A unit test in
`tests/Unit/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategyTest.php`
must cover at minimum:

- A `Context\<BC>\…` FQCN → expected BC-snake prefix
- A `System\<BC>\…` FQCN → expected BC-snake prefix
- A non-matching FQCN (e.g. `App\Shared\…`) → empty prefix (`''`)

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php` | **Mandatory patch** — regex must accept `Context\` and `System\` |
| `config/packages/doctrine.yaml` | 9 mapping entries — `dir:` and `prefix:` to update for every BC. Watch the `Client` asymmetry. |
| `config/packages/doctrine_migrations.yaml` | **No change** — namespace is `DoctrineMigrations\<BC>` and physical paths are outside `src/` |
| `config/packages/messenger.yaml` | **No change** — only `App\Shared\…` FQCNs |
| `config/services.yaml` | Many `App\<BC>\…` FQCNs and `resource: '../src/<BC>/...'` paths to update |
| `composer.json` | **No change** — `App\: src/` stays |
| `phpunit.xml` | **No change** — flat roots |
| `phpstan.neon` | **No change** — flat roots |
| `migrations/<BC>/*.php` | Folders stay; only `use App\<BC>\…` lines inside file bodies are rewritten |
| `src/Shared/README.md` | Add the precise "no business event dispatch from Shared" rule |

### Technical Decisions

1. **Namespaces are renamed**, not aliased. Every `use App\Clinic\…`
   becomes `use App\Context\Clinic\…`. No backwards-compat shim.
2. **Tests mirror src/ strictly**. `tests/Unit/Clinic/…` →
   `tests/Unit/Context/Clinic/…`, same for `Integration` and
   `fixtures/`.
3. **`Presentation/` stays where it is** for this epic. Reorg by
   feature is deferred.
4. **`Shared/` location is unchanged**, only the README is updated to
   document the no-dispatch rule.
5. **Zero behavior change**. Acceptance is `make ci` green and a clean
   `doctrine:schema:validate` diff (no schema drift from the table
   naming strategy).
6. **Execution via Rector, not sed.** A throwaway `rector.php` config
   using `RenameClassRector` (or `RenameNamespaceRector`) with the full
   `App\<BC>\` → `App\Context\<BC>\` / `App\System\<BC>\` mapping is
   committed in the same PR. Rector handles `use` statements, FQCNs,
   PHPDoc annotations and class-string literals in one pass. The
   reviewer reviews the Rector config + the BC prefix strategy patch;
   the rest of the diff is mechanical and trustable.
7. **Order of operations:** see the numbered Tasks below (Task 0 →
   Task 13). The Tasks list is the authoritative execution order.

## Implementation Plan

### Tasks

Tasks are ordered by dependency. Each must be completed before the
next. The whole sequence lives in **a single PR**. The autoload is
intentionally broken between Task 4 and Task 10 — `master` must never
see those intermediate states. **The PR must be pushed only after
Task 11 (`make ci`) is green locally**, so CI on the remote sees the
final state on first push. Intermediate commits exist for review
clarity but are not individually shippable; this is documented in
Notes ("commit grouping").

All commands below are executed **inside the dev container** (via the
relevant `make` target) with the database **up and migrated to the
latest pre-refactor state**.

- [ ] **Task 0: Capture the schema baseline (pre-refactor proof)**
  - Action: From a clean working tree, **before any other task**,
    run `php bin/console doctrine:schema:create --dump-sql >
    /tmp/schema-before.sql`. Then sanity-check the file is non-empty
    and contains DDL for entities from every BC (`grep -c
    'CREATE TABLE' /tmp/schema-before.sql` should yield a number
    matching the known per-BC entity count).
  - Notes:
    - This file is the golden master for the post-refactor diff
      (Task 11). It MUST be captured before Task 1 — once the
      naming-strategy regex is patched, the baseline is no longer
      representative of the pre-refactor schema.
    - **Limitation (acknowledge honestly):** `schema:create
      --dump-sql` reflects what Doctrine's *mapping* believes the
      schema should be, NOT what is currently in the database. AC-2
      therefore proves "the mapping produces identical DDL before
      and after the rename". If the mapping was already drifted from
      the real DB before this refactor, AC-2 cannot detect that.
      That pre-existing drift is out of scope here — we only assert
      this refactor introduces no NEW drift.
    - Verified in Step 2: the project uses a single default EM with
      9 mappings under `orm.mappings`. A single `--dump-sql` call
      without `--em=…` covers all 9 mappings.
    - Do NOT commit `/tmp/schema-before.sql`.

- [ ] **Task 0bis: Install Rector as a dev dependency (if absent)**
  - Action: `composer require --dev rector/rector` if `vendor/bin/rector`
    does not exist.
  - Notes: This modifies `composer.json` and `composer.lock`. **Decide
    upfront** whether Rector stays as a permanent dev dependency
    after this PR or not:
    - **If permanent:** keep both `composer.json` and `composer.lock`
      changes; mention it in the PR description as an intentional
      addition.
    - **If throwaway:** Task 12 must explicitly revert the
      `composer.json` and `composer.lock` changes (`composer remove
      --dev rector/rector`) in the same commit that deletes
      `rector.php`.
    The default for this epic is **throwaway** unless the reviewer
    decides otherwise. Do not let the dependency leak in by
    accident.

- [ ] **Task 1: Patch `BoundedContextPrefixNamingStrategy`**
  - File: `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php`
  - Action: Replace the regex on line 82 with
    `'/^App\\\\(?:Context|System)\\\\([^\\\\]+)\\\\Infrastructure\\\\Persistence\\\\Doctrine\\\\Entity\\\\/'`.
  - Notes: Defensive whitelist of buckets — adding a future bucket
    requires an explicit code change. No other method needs updating.

- [ ] **Task 2: Update the naming-strategy unit test**
  - File: `tests/Unit/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategyTest.php`
  - Action: Update existing cases so FQCNs use `App\Context\<BC>\…`
    or `App\System\<BC>\…`. Add at least one case per bucket and one
    negative case (`App\Shared\…` → empty prefix). All assertions on
    expected prefix strings (`clinic`, `animal`, `identity_access`,
    etc.) stay identical.
  - Notes: Test must keep 100% line coverage on the strategy.
    Specifically, ensure the `App\Shared\…` (or any non-matching)
    case exercises the `if (1 !== preg_match(…))` branch returning
    `''`. Add the case if the existing test does not already cover
    it. The test file lives under `tests/Unit/Shared/…` and **does
    NOT move** — `Shared` stays where it is.

- [ ] **Task 3: Author the throwaway Rector config**
  - File: `rector.php` (project root, new file)
  - Action: Configure `RenameClassRector` (or
    `Rector\Renaming\Rector\Namespace_\RenameNamespaceRector`) with
    the explicit mapping below. Apply to `src/`, `tests/`,
    `fixtures/`, `migrations/`. **Do NOT include `config/`** —
    Rector only processes `*.php` files and the only PHP files in
    `config/` (`bundles.php`, `preload.php` if present) do not
    contain any `App\<BC>\…` references. The YAML/XML/Neon files in
    `config/` are handled manually in Task 8.
  - Mapping:
    - `App\Clinic\` → `App\Context\Clinic\`
    - `App\ClinicalCare\` → `App\Context\ClinicalCare\`
    - `App\Animal\` → `App\Context\Animal\`
    - `App\Client\` → `App\Context\Client\`
    - `App\Scheduling\` → `App\Context\Scheduling\`
    - `App\IdentityAccess\` → `App\System\IdentityAccess\`
    - `App\AccessControl\` → `App\System\AccessControl\`
    - `App\Translation\` → `App\System\Translation\`
  - Notes: Rector covers `use` statements, FQCN literals, PHPDoc
    annotations and class-string literals. Run `vendor/bin/rector
    process --dry-run` first to inspect.

- [ ] **Task 4: Move BC folders under `Context/` (business BCs)**
  - Action: Use `git mv` to preserve history.
    - `git mv src/Clinic src/Context/Clinic`
    - `git mv src/ClinicalCare src/Context/ClinicalCare`
    - `git mv src/Animal src/Context/Animal`
    - `git mv src/Client src/Context/Client`
    - `git mv src/Scheduling src/Context/Scheduling`
  - Notes: `src/Context/` will not exist yet — `git mv` creates it
    automatically.

- [ ] **Task 5: Move BC folders under `System/` (cross-cutting BCs)**
  - Action:
    - `git mv src/IdentityAccess src/System/IdentityAccess`
    - `git mv src/AccessControl src/System/AccessControl`
    - `git mv src/Translation src/System/Translation`

- [ ] **Task 6: Mirror-move `tests/Unit/`, `tests/Integration/`,
      `fixtures/`**
  - Action: For each BC and each of the three roots, repeat the
    `git mv` pattern. Examples:
    - `git mv tests/Unit/Clinic tests/Unit/Context/Clinic`
    - `git mv tests/Integration/AccessControl tests/Integration/System/AccessControl`
    - `git mv fixtures/Translation fixtures/System/Translation`
  - Notes: 8 BCs × 3 roots = 24 moves max (`Shared` does not move
    and is not a BC). Some BCs may only have a `tests/Unit/` or only
    a `tests/Integration/` folder — move what exists, do not create
    empty mirror folders. The mirror must reflect `src/` exactly so
    test discovery and per-BC coverage reporting keep working.

- [ ] **Task 7: Run Rector**
  - Action: `vendor/bin/rector process` (no `--dry-run`).
  - Notes: This rewrites every `use App\<BC>\…`, FQCN literal, PHPDoc
    annotation and class-string in `src/`, `tests/`, `fixtures/`,
    `migrations/`. It does NOT touch `*.yaml`, `*.xml`, `*.neon`,
    `*.twig` or `Makefile` — those are handled in Task 8. After
    Rector completes, run a sanity grep on Foundry fixtures
    specifically (`rg -P '(?<![A-Za-z\\])App\\\\(Clinic|Animal|Client|ClinicalCare|Scheduling|IdentityAccess|AccessControl|Translation)' fixtures/`)
    — it must return zero matches. Class-string literals in some
    Foundry call shapes are not always covered by `RenameClassRector`,
    and a stray match here means the dev must hand-fix it before
    continuing.

- [ ] **Task 8: Manually patch non-PHP config files**
  - File: `config/packages/doctrine.yaml`
  - Action: For each of the 8 mappings, prefix the `dir:` and
    `prefix:` with the bucket. Examples:
    - `dir: '%kernel.project_dir%/src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity'`
    - `prefix: 'App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity'`
    - For `Client`, keep the trailing-`\Doctrine` form (no `\Entity`)
      and prefix the `dir:` accordingly.
    - For `IdentityAccess`, `AccessControl`, `Translation`: use
      `System\` instead of `Context\`.
  - File: `config/services.yaml`
  - Action: Rewrite every `App\<BC>\…` FQCN and every `resource:
    '../src/<BC>/...'` path so that `<BC>` becomes `Context/<BC>` or
    `System/<BC>`. **Important:** the `Presentation/<App>/Controller`
    blocks (e.g. `App\Presentation\Clinic\Controller\:`,
    `App\Presentation\Portal\Controller\:`,
    `App\Presentation\Backoffice\Controller\:`) **stay as-is**.
    `Presentation/` does not move and the `App\Presentation\<App>\`
    namespace is NOT renamed — only `App\Clinic\`, `App\Animal\`,
    etc. are. Do not let the `sed` loop accidentally rewrite
    `App\Presentation\Clinic\` to `App\Presentation\Context\Clinic\`
    — the loop pattern `App\\Clinic\\` does not match
    `App\Presentation\Clinic\` (different prefix), so it is safe by
    construction, but verify with a grep after the sed.
  - Recommended: drive this with a small `sed` loop rather than
    hand-editing 100+ lines. Example:
    ```bash
    for bc in Clinic ClinicalCare Animal Client Scheduling; do
      sed -i "s|App\\\\${bc}\\\\|App\\\\Context\\\\${bc}\\\\|g" config/services.yaml
      sed -i "s|src/${bc}/|src/Context/${bc}/|g" config/services.yaml
    done
    for bc in IdentityAccess AccessControl Translation; do
      sed -i "s|App\\\\${bc}\\\\|App\\\\System\\\\${bc}\\\\|g" config/services.yaml
      sed -i "s|src/${bc}/|src/System/${bc}/|g" config/services.yaml
    done
    ```
    Same approach for the `dir:` and `prefix:` lines of
    `config/packages/doctrine.yaml` (one `sed` per BC). Manual review
    afterwards is still required, but the substitution itself is
    mechanical.
  - File: `config/packages/messenger.yaml`
  - Action: **No change**. Verified in Step 2 — only `App\Shared\…`
    middlewares are referenced.
  - File: `config/packages/doctrine_migrations.yaml`
  - Action: **No change**. Migrations namespace is
    `DoctrineMigrations\<BC>` and physical paths stay outside `src/`.

- [ ] **Task 9: Update Shared README with the no-dispatch rule**
  - File: `src/Shared/README.md`
  - Action: Add a new short section titled "Event dispatch policy"
    with the exact text:
    > `Shared/` may host buses, publishers and abstract event
    > classes (`AbstractDomainEvent`, `EventBus`,
    > `DomainEventPublisher`, …), but contains no concrete business
    > `DomainEvent` and never `dispatch()`es directly. Dispatch
    > always originates from an aggregate inside a Bounded Context.
  - Notes: A grep proof is part of the AC list (AC-7).

- [ ] **Task 10: Clear caches, refresh autoload, and run the suite**
  - Action:
    1. `rm -rf var/cache/*` — Doctrine and Symfony cache directories
       contain metadata and compiled container references to the
       old FQCNs; running `make ci` without clearing them produces
       confusing failures that look like real bugs but are stale-
       cache artefacts.
    2. `composer dump-autoload`
    3. `make ci`
  - Notes: If anything fails, fix the root cause — never relax the
    rule. PHPStan max + project coverage gates still apply.

- [ ] **Task 11: Schema-equivalence proof**
  - Action:
    ```bash
    php bin/console doctrine:schema:create --dump-sql > /tmp/schema-after.sql
    diff -q /tmp/schema-before.sql /tmp/schema-after.sql && echo "OK: schema unchanged" || { echo "FAIL: schema drifted"; diff /tmp/schema-before.sql /tmp/schema-after.sql; exit 1; }
    ```
    `diff -q` exits 0 if the files are identical, 1 otherwise — the
    `&& / ||` chain makes the result unambiguous.
  - **If the diff is non-empty:** the most likely cause is that the
    regex patch in Task 1 produces a different captured group for at
    least one BC. Rollback path:
    1. Re-read Step 2's analysis of `BoundedContextPrefixNamingStrategy`
       in this spec.
    2. Run the unit test from Task 2 against the *current* state — if
       the test passes but Task 11 still fails, the bug is somewhere
       else (likely a forgotten rename in `doctrine.yaml`).
    3. If unrecoverable: `git reset --hard <baseline-sha>` and start
       over from Task 0.
  - Notes: This is the strongest end-to-end safety net available
    given the limitation already documented in Task 0 (the dump
    reflects the mapping, not the live DB).

- [ ] **Task 12: Delete the throwaway Rector config (and revert the
      dep, if throwaway)**
  - File: `rector.php` (always) + `composer.json` / `composer.lock`
    (if Rector was added as a throwaway dep in Task 0bis)
  - Action: `git rm rector.php`. If Rector is throwaway: `composer
    remove --dev rector/rector` in the same commit. Verify the
    `composer.json` diff is empty for any rector entry.
  - Notes: Reviewer reviews the rule + the deletion in one commit.

### Acceptance Criteria

- [ ] **AC-1 (CI green):** Given the refactor is complete and
  `composer dump-autoload` has been run, when `make ci` is executed,
  then it exits 0 — covering `php-cs-fixer.dry-run`, `phpcs`,
  `phpstan` (level max), `tailwind-build`, and the full PHPUnit
  suite with `failOnDeprecation`, `failOnNotice`, `failOnWarning`.

- [ ] **AC-2 (Mapping-DDL unchanged — load-bearing proof):** Given
  `/tmp/schema-before.sql` was captured before any move (Task 0),
  when `/tmp/schema-after.sql` is captured after Task 10, then
  `diff -q /tmp/schema-before.sql /tmp/schema-after.sql` exits 0
  (files are byte-identical). This proves the Doctrine mapping
  produces identical DDL before and after the rename — i.e. the
  `BoundedContextPrefixNamingStrategy` patch did its job. **Scope
  caveat:** this proves equivalence at the *mapping* level, not
  against the live database. Pre-existing mapping/DB drift (if any)
  is out of scope for this epic.

- [ ] **AC-3 (Doctrine mappings valid):** Given the refactor is
  complete AND the database was up-to-date with `migrations/` before
  the refactor started (no pending migrations), when `php bin/console
  doctrine:schema:validate` is run, then it reports both `[OK] The
  mapping files are correct.` and `[OK] The database schema is in
  sync with the mapping files.`

- [ ] **AC-4 (No legacy namespace roots in code):** Given the
  refactor is complete, when ripgrep is run with a pattern that
  matches `App\<BC>\` ONLY when `App` is at the start of the
  identifier (so it does not catch the legitimate
  `App\Context\Clinic\` form), across `*.php`, `*.yaml`, `*.xml`,
  `*.neon` (excluding `vendor/`, `var/`, `docs/legacy/`,
  `CHANGELOG*`), then it returns zero matches.

  **Calibration step (mandatory before relying on this AC):** before
  starting Task 1, run the same pattern on the current pre-refactor
  codebase. It MUST return a non-zero count (hundreds of matches
  expected). If the calibration count is zero, the regex is broken
  and the post-refactor "zero matches" result would be vacuous. Fix
  the pattern before proceeding.

  The recommended pattern is:
  ```
  rg -P '(?<![A-Za-z\\])App\\(Clinic|Animal|Client|ClinicalCare|Scheduling|IdentityAccess|AccessControl|Translation)\\' \
     -g '!vendor' -g '!var' -g '!docs/legacy' -g '!CHANGELOG*' \
     -g '*.php' -g '*.yaml' -g '*.xml' -g '*.neon'
  ```
  The negative lookbehind `(?<![A-Za-z\\])` ensures matches only fire
  when `App` is the actual root prefix, not a sub-segment of
  `App\Context\…` or `App\System\…`.

- [ ] **AC-5 (BC folders moved):** Given the refactor is complete,
  when listing the first-level directories under `src/`, then the
  only BC-bearing directories are `Context/` and `System/`. None of
  `src/Clinic`, `src/Animal`, `src/Client`, `src/ClinicalCare`,
  `src/Scheduling`, `src/IdentityAccess`, `src/AccessControl`,
  `src/Translation` exists. `Shared/`, `Presentation/`, and
  `Kernel.php` are unaffected. (Other top-level files such as
  `.gitkeep`, `README.md`, etc. are allowed and not asserted on.)

- [ ] **AC-6 (Tests mirror src):** Given the refactor is complete,
  when inspecting `tests/Unit/`, `tests/Integration/`, and
  `fixtures/`, then no top-level BC name (`Clinic`, `Animal`,
  `Client`, `ClinicalCare`, `Scheduling`, `IdentityAccess`,
  `AccessControl`, `Translation`) survives directly under any of
  them — they all live under `Context/` or `System/`. Other
  top-level entries (`bootstrap.php`, `Helper/`, shared `Dataset/`,
  etc.) are allowed and not asserted on.

- [ ] **AC-7 (Shared dispatch rule documented and enforced —
  human-verified):** Given Task 10 is complete, when reading
  `src/Shared/README.md`, then the "Event dispatch policy" section
  is present with the exact wording specified. AND when manually
  reviewing every `->dispatch(` call site under `src/Shared/`, then
  none of them pass a concrete business event — only the generic
  bus/publisher plumbing remains. (The "concrete business event"
  judgement is intentionally human-verified: a pure grep cannot
  distinguish abstract event plumbing from a domain-event dispatch.)

- [ ] **AC-8 (Coverage gates still pass):** Given the refactor is
  complete, when `make ci` runs the project's existing coverage
  gates (whatever they are — per-BC thresholds, global threshold,
  PHPUnit `--coverage-text` minimums, etc.), then those gates still
  pass at the same level as before the refactor. The intent is "no
  BC silently loses coverage because a test file failed to be
  picked up after the move". If the project does NOT currently have
  a mechanically enforced per-BC coverage gate, this AC is verified
  by manually running `make ci` with coverage enabled and comparing
  the per-BC totals before vs. after the refactor.

- [ ] **AC-9 (Rector config removed):** Given the PR is ready to
  merge, when inspecting the file tree at the tip of the branch,
  then `rector.php` does not exist. (Task 12.) AND, if Task 0bis
  installed Rector as a throwaway dep, `composer.json` shows no
  `rector/rector` entry.

- [ ] **AC-10 (`Client` Doctrine mapping preserved):** Given the
  refactor is complete, when reading `config/packages/doctrine.yaml`
  for the `Client` mapping, then its `prefix:` is exactly
  `App\Context\Client\Infrastructure\Persistence\Doctrine` (no
  `\Entity` suffix) and its `dir:` is the matching folder. The
  Embeddable directory still resolves.

## Additional Context

### Dependencies

- **Rector** must be available (`composer require --dev
  rector/rector` if not present). It is installed for the duration of
  the PR. Whether it stays as a dev dependency afterwards is left to
  the reviewer's judgement and is not part of this epic's scope.
- No external services. Pure structural refactor.
- The whole task list must land in **a single PR**: between Task 5
  and Task 11 the autoloader is intentionally broken, and `master`
  must never see that intermediate state.

### Testing Strategy

- The existing test suite is the spec. No new tests are written
  beyond updating the naming-strategy unit test (Task 2). If a test
  fails after the rename, the rename is wrong.
- The naming-strategy unit test must keep its existing assertions
  (same expected prefix strings) — only the FQCN inputs change. This
  guarantees behavioural equivalence at the unit level.
- AC-2 (`diff` of `schema-create --dump-sql` before/after = empty) is
  the strongest possible end-to-end proof and must be performed
  manually as part of finalizing the PR.
- 100% per-BC coverage rule still applies — AC-8 enforces it.

### Notes

- This is Epic 1 of 3. Epics 2 and 3 will assume this layout exists.
- Risk #1 (blocking) to investigate in Step 2:
  `BoundedContextPrefixNamingStrategy` and any Doctrine mapping driver
  that scans by namespace. If they derive anything from the namespace
  path, we patch the strategy to skip `Context\` / `System\` segments
  so prefixes stay identical. No DB migration is acceptable in this
  epic.
- Party-mode panel decision: `Translation` stays in `System/` because
  the agreed definition of `System/` is "cross-cutting domains with a
  full DDD structure that are NOT veterinary business" — Translation
  fits exactly. (Winston raised the question; resolved by clarifying
  the bucket definition.)
- Migrations under `migrations/` will be edited in place. Acceptable
  because the product is in alpha with no live environment.
- **Suggested commit grouping inside the single PR** (helps the
  reviewer follow the logic). The commits are intentionally small
  but **the intermediate states between them are not individually
  shippable** (autoload is broken between commit 3 and commit 4
  until `dump-autoload` runs). Push only after Task 10 is locally
  green so the remote sees the final state on first push and CI
  fires once on a green tip. Commits:
  1. `chore(refactor): patch BoundedContextPrefixNamingStrategy for Context/System buckets`
     (Tasks 1 + 2)
  2. `chore(refactor): add throwaway rector config for namespace migration`
     (Task 3)
  3. `chore(refactor): move BCs into Context/ and System/ buckets`
     (Tasks 4 + 5 + 6 + 7 — mechanical, single commit)
  4. `chore(config): update doctrine.yaml and services.yaml for new layout`
     (Task 8 + cache clear from Task 10)
  5. `docs(shared): document event dispatch policy`
     (Task 9)
  6. `chore(refactor): remove throwaway rector config`
     (Task 12)
  Task 0 (schema baseline) and Task 0bis (Rector install — if kept
  throwaway) leave no commit — they are local prep. Task 10
  (`make ci`) and Task 11 (schema diff) are validation gates, not
  commits. **After commit 6, re-run `make ci` once on the final
  tip** — commits 5 and 6 land after the Task 10 gate, so the tip
  must be re-validated before pushing. Push only after that final
  green.
- Review strategy: the PR will touch hundreds of files but the
  reviewable surface is small — the Rector config, the
  `BoundedContextPrefixNamingStrategy` patch, and the manual
  config-file edits. The rest is mechanically derivable.
- **Future Work (out of scope, recorded for awareness, NOT a
  recommendation):** an alternative to the magical naming strategy
  would be explicit `#[ORM\Table(name: 'clinic__clinics')]` on every
  entity. Pro: zero coupling between namespace and schema, immune to
  any future structural refactor. **Con (and this is the deciding
  factor for now):** the current naming strategy makes it
  *mechanically impossible* to forget or mistype a table prefix —
  consistency is enforced by code, not by reviewer vigilance.
  Switching to explicit attributes would trade refactor-safety for
  human-error risk on every new entity. Decision: **keep the naming
  strategy**, just patch its regex defensively. Revisit only if a
  future refactor makes the namespace coupling painful again.
