# Contributing to Kiveto

Welcome. This document is the source of truth for how we work on Kiveto —
read it before you push your first commit. It covers the git flow, branch
and commit conventions, the local dev/CI workflow and how the BMAD planning
method fits into all of this.

Both human contributors and AI agents (Claude Code, Cursor, etc.) are
expected to follow these rules. AI agents specifically MUST also obey the
directives in `CLAUDE.md`.

---

## 1. Git flow

Kiveto uses **GitHub Flow** with a single long-lived branch and ephemeral
feature branches. There is no `staging` branch — the staging environment
is deployed from the head of `master`.

```
master  ───●───●───●───●───●───●───●───●───●─►   production-ready, always green
            ↑       ↑       ↑       ↑
            │       │       │       │
       feature/ feature/ fix/   chore/
```

- **`master`** is the only long-lived branch. It is always production-ready
  and always green (`make ci` passes). Direct pushes to `master` are
  forbidden — every change goes through a Pull Request.
- **`feature/*`, `fix/*`, `hotfix/*`, `chore/*`** are ephemeral. Created off
  `master`, merged back into `master` via PR, then deleted.
- The **staging environment** is deployed from `master` (not from a branch).
- The **production environment** is also deployed from `master`, gated by
  manual validation after staging.

### Why no `staging` branch?

A long-lived `staging` branch tends to drift from `master` and creates the
"three features merged into staging, I only want to promote one" problem.
By deploying staging directly from `master`, there is exactly one history
and zero divergence risk.

---

## 2. Branch naming

Pattern: `<type>/<short-slug>` or `<type>/<ticket>-<short-slug>` once a
ticket tracker is in place.

| Type       | When to use it                                                          | Example                                       |
|------------|-------------------------------------------------------------------------|-----------------------------------------------|
| `feature/` | A new user-visible capability                                           | `feature/agenda-planning-redesign`            |
| `fix/`     | A bug fix scoped to existing behaviour                                  | `fix/clinic-switcher-overflow`                |
| `hotfix/`  | An urgent production bug needing immediate merge                        | `hotfix/login-500`                            |
| `chore/`   | Infra, dependencies, refactoring, tooling, docs — no user-visible change | `chore/test-coverage-100`, `chore/upgrade-php` |

### Rules

- Slugs are kebab-case, English, short and descriptive.
- One branch = one logical change. If your branch starts doing two things,
  split it.
- Branch off the latest `master`: `git fetch && git checkout -b feature/x origin/master`.
- Delete the branch after the PR is merged.

---

## 3. Commit messages — Conventional Commits

We follow [Conventional Commits](https://www.conventionalcommits.org/).
Format:

```
<type>(<scope>): <short imperative summary>

<optional body explaining the why, wrapping at ~72 chars>

<optional footers (Co-Authored-By, Refs, BREAKING CHANGE, …)>
```

### Allowed types

| Type     | Use it for                                                                |
|----------|---------------------------------------------------------------------------|
| `feat`   | A user-visible feature                                                    |
| `fix`    | A bug fix                                                                 |
| `test`   | Adding or fixing tests (no production code change)                        |
| `refactor` | Code change that neither fixes a bug nor adds a feature                 |
| `chore`  | Tooling, deps, infra, CI, build, generated files                          |
| `docs`   | Documentation only                                                        |
| `style`  | Code style (formatting, semicolons) — almost always done by `cs-fixer`    |
| `perf`   | Performance improvement                                                   |
| `build`  | Build system / packaging                                                  |
| `ci`     | CI configuration only                                                     |

### Scope

Optional but recommended. Use the BC name in lowercase or a short subsystem
name: `ui-kit`, `scheduling`, `consultation`, `clients`, `agenda`, etc.

### Examples

```
feat(scheduling): add WaitingRoomEntry triage update flow
fix(ui-kit): align tooltip arrow with the rectangle
test(consultation): cover Application/Command handlers
chore(ci): get make ci fully green (cs-fixer + phpcs + phpstan + tests)
refactor(shared): rename DbalRow → RowAccessor with explanatory PHPDoc
docs: move per-BC implementation notes to docs/legacy
```

### Body — explain the WHY

The diff already shows *what* changed. The body should explain *why*. If
the change fixes a non-obvious bug or unblocks a downstream task, say so.

---

## 4. Pull Request workflow

```
feature/* ──► PR → master
                    │
                make ci 100% green on the branch (run locally, see §5)
                    │
                Review (human or AI)
                    │
                Squash-merge (or merge commit) → master
                    │
                Auto-deploy to staging environment
                    │
                Manual validation
                    │
                Auto-deploy to production
```

- **PR title** follows the same conventional-commit format as the squash
  commit it will produce.
- **PR body** describes the change, links to related issues / BMAD stories,
  and lists any manual test plan if needed.
- **`make ci` must be green** before merge. Non-negotiable. There is no
  remote CI pipeline yet, so the author runs it locally on the branch
  head. See section 5.
- **Reviews**: at least one approval (human or AI agent) for any PR
  touching production code. `chore/` PRs may self-approve at the author's
  discretion.

---

## 5. Local dev & CI

### One-time setup

```bash
make install         # build containers, install deps
make reset-db        # init database + run migrations
make load-fixtures   # seed dev data
```

### Daily commands

| Goal                                | Command                                  |
|-------------------------------------|------------------------------------------|
| Start the stack                     | `make start`                             |
| Start it with Xdebug step debugging | `XDEBUG_MODE=debug make start`           |
| Stop the stack                      | `make stop`                              |
| Rebuild frontend assets             | `make assets`                            |
| Run all tests                       | `make test`                              |
| Run unit tests only                 | `make test-unit`                         |
| Run integration tests only          | `make test-integration`                  |
| Generate coverage report (HTML)     | `make test-coverage`                     |
| Run static analysis                 | `make phpstan`                           |
| Run code style check (dry-run)      | `make php-cs-fixer.dry-run`              |
| Auto-fix code style                 | `make php-cs-fixer && make phpcbf`       |
| Export the Portal API contract      | `make openapi-export`                    |
| Check the contract matches the code | `make openapi-check`                     |
| **Run the full CI gate locally**    | `make ci`                                |

### CI gate (`make ci`)

`make ci` chains `php-cs-fixer.dry-run → phpcs → phpstan → openapi-check →
tailwind-build → test`.

**It must be 100% green before any commit and before opening any PR.**
This is non-negotiable. If it fails, fix the failures rather than
committing yellow.

`make ci` runs only on the developer's machine: the repository has no
remote CI pipeline (no `.github/workflows/`) and no git hook, so nothing
runs it automatically on a commit or a PR.

What each step fails on:

| Step | Fails on | Does not fail on |
|---|---|---|
| `php-cs-fixer.dry-run` | Any file the fixer would change | — |
| `phpcs` | PSR-1/2/12 and forbidden-function **errors** | **Warnings**: `phpcs.xml` sets `ignore_warnings_on_exit`, so they are printed and the exit code stays 0. Today every warning is `Generic.Files.LineLength` (lines over 120 characters). |
| `phpstan` | Any error at `level: max` in `src/`, `tests/`, `migrations/` and `fixtures/` | — |
| `openapi-check` | `openapi/portal.v1.yaml` differing from what the code exports | A drift hidden by a stale dev cache: the export reads API Platform's metadata from `var/cache/dev`. On 2026-11-02 the check passed on a cache built before the latest resources and failed once the cache was cleared. After changing a resource, run `bin/console cache:clear` before `make openapi-export` or `make openapi-check`. |
| `tailwind-build` | A Tailwind build error | — |
| `test` | A failing test, and any deprecation, notice or warning (`failOnDeprecation`, `failOnNotice`, `failOnWarning`; deprecations raised between two vendor packages are ignored, see `phpunit.xml`) | Coverage: the suite runs with `XDEBUG_MODE=off`, nothing is measured |

### Quality rules: what is automated today

| Rule | Enforced today by | Still to implement |
|---|---|---|
| Code style (PHP-CS-Fixer, PHPCS errors) | `make ci` | — |
| PHPCS line length (120 characters) | Reported by `make phpcs`, not failing | — |
| PHPStan `level: max` | `make ci` | — |
| Portal API contract matches the code | `make ci` (`openapi-check`), provided the dev cache is fresh | An export that does not depend on the cache state |
| Tests green, without deprecations, notices or warnings | `make ci` | — |
| Query budgets of the clinic pages: SQL statements per page, and per patient of the day | `make ci` (`ClinicPageQueryBudgetTest`, counted by `tests/Support/Database/QueryCounter`). A change that makes a page run fewer statements lowers its budget there. | Budgets for the pages it does not cover yet |
| 100% line coverage per BC (excl. Presentation) | The author and the reviewer, with `make test-coverage` | An automated per-BC threshold |
| BC boundaries | `tests/Unit/Architecture/` for a few contexts (§6), review for the others | A check covering every BC |
| `make ci` green before every commit and PR | The author, locally | A remote CI pipeline |
| Conventional Commits and branch names | Review | A commit-message check |

### Test coverage policy

Every Bounded Context except the Presentation layer must stay at **100%
line coverage**. The Presentation layer is excluded because it requires
end-to-end tests that are out of scope for unit/integration tests.

When you add or modify code in a BC, you also add the tests that cover it.
A PR that drops a BC's coverage below 100% will be rejected.

**This is a quality policy, not an automated threshold.** `make ci` does
not measure coverage and no tool checks the 100% per BC. Measure it with
`make test-coverage`, which writes an HTML report to `coverage/`
(git-ignored), and check the BCs you touched before opening the PR.

---

## 6. Architecture

Kiveto is a **Domain-Driven Design** monolith organised by **Bounded
Contexts**. Each BC under `src/Context/` or `src/System/` follows the same
internal layout:

```
src/{Context,System}/<BC>/
├── Domain/              # Aggregates, value objects, domain events, repository interfaces
├── Application/         # Commands, queries, handlers, ports (interfaces)
├── Infrastructure/      # Doctrine entities, mappers, DBAL adapters, port implementations
└── README.md            # Canonical description of the BC
```

The Presentation layer lives separately under `src/Presentation/<App>/`.

Cross-BC communication happens **only** through:
- Application command/query buses
- Application Port interfaces, implemented by adapters in the calling BC's
  `Infrastructure/Adapter/<OtherBC>/` folder

Direct usage of another BC's Domain or Doctrine entities is forbidden.

### What is checked automatically

The rule applies to every BC. The automated checks are the architecture
tests in `tests/Unit/Architecture/` (part of `make test`), and they cover a
few contexts only:

| Test | What it enforces |
|---|---|
| `PortalBoundaryTest` | `ClientPortal` and `ClinicListing` never `use` another context's `Domain` or `Infrastructure` (integration events excepted); `src/Presentation/Api/Portal` uses no context internals except domain exceptions |
| `NotificationBoundaryTest` | Code outside `System/Notification` reaches it only through its Application commands and queries and its integration events; Notification references no `Context`, `Presentation` or other `System` code (imports, inline class names and service ids) |
| `ElectronicInvoicingBoundaryTest` | Code outside `System/ElectronicInvoicing` reaches it only through its Application commands and queries and its integration events; it references no `Context` or `Presentation` code |
| `AppointmentStatusLiteralTest` | Outside `src/Context/Scheduling`, no code writes the `PLANNED`, `PENDING_CLINIC_CONFIRMATION` or `DECLINED` appointment status literals |

For every other BC, the boundary rule is enforced by review only. A check
covering every BC is still to be implemented.

---

## 7. BMAD planning workflow

Kiveto uses the **BMAD method** (`_bmad/`) for product planning, with
artefacts in `_bmad-output/`. Skills are exposed as Claude Code skills
and can be invoked with `/<skill-name>` from a Claude session.

### Typical workflow for a new feature

```
brainstorm  →  product brief  →  PRD  →  architecture  →  epics & stories  →  dev → QA
   ↓              ↓             ↓          ↓                 ↓                  ↓
bmad-       bmad-create-   bmad-     bmad-create-      bmad-create-       bmad-dev-story
brainstorming  product-    create-   architecture      epics-and-          / bmad-quick-dev
               brief       prd                          stories
```

Each step writes its artefact under `_bmad-output/` (PRDs, architecture
docs, story files, sprint plans). Follow-on steps consume the previous
step's artefact.

### Quick reference

| Use case                                    | Skill                              |
|---------------------------------------------|------------------------------------|
| Explore an idea                             | `bmad-brainstorming`               |
| Write a PRD from scratch                    | `bmad-create-prd`                  |
| Capture solution architecture decisions     | `bmad-create-architecture`         |
| Break a PRD into epics and user stories     | `bmad-create-epics-and-stories`    |
| Implement a story                           | `bmad-dev-story`                   |
| Quick spec for a small change               | `bmad-quick-spec` then `bmad-quick-dev` |
| Adversarial code review                     | `bmad-code-review`                 |
| Validate implementation readiness           | `bmad-check-implementation-readiness` |
| Don't know what to do next                  | `bmad-help`                        |

For the full list of skills run any of them with no argument or read the
descriptions in `_bmad/bmm/`.

---

## 8. Definition of Done

A change is "done" only when **all** of the following hold:

- [ ] `make ci` is 100% green locally
- [ ] Tests cover the change (BC coverage stays at 100%, checked with
      `make test-coverage`)
- [ ] Commit messages follow Conventional Commits
- [ ] Branch name follows the conventions in section 2
- [ ] PR opened against `master`, with a descriptive title and body
- [ ] PR references the BMAD story / artefact when applicable
- [ ] Code review approved (at least one reviewer)
- [ ] Remote CI is green on the PR — *not available yet: no CI pipeline is
      configured, so the local `make ci` above is the only gate today*
- [ ] Branch deleted after merge
