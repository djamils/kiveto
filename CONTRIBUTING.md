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
                CI run (make ci must be 100% green)
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
- **CI must be green** before merge. Non-negotiable. See section 5.
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
| Stop the stack                      | `make stop`                              |
| Rebuild frontend assets             | `make assets`                            |
| Run all tests                       | `make test`                              |
| Run unit tests only                 | `make test-unit`                         |
| Run integration tests only          | `make test-integration`                  |
| Generate coverage report (HTML)     | `make test-coverage`                     |
| Run static analysis                 | `make phpstan`                           |
| Run code style check (dry-run)      | `make php-cs-fixer.dry-run`              |
| Auto-fix code style                 | `make php-cs-fixer && make phpcbf`       |
| **Run the full CI gate locally**    | `make ci`                                |

### CI gate (`make ci`)

`make ci` chains `php-cs-fixer.dry-run → phpcs → phpstan → tailwind-build → test`.

**It must be 100% green before any commit and before opening any PR.**
This is non-negotiable. If it fails, fix the failures rather than
committing yellow. PHPStan runs at `level: max`, tests run with
`failOnDeprecation`, `failOnNotice` and `failOnWarning` enabled, so
warnings are treated as failures.

### Test coverage policy

Every Bounded Context except the Presentation layer must stay at **100%
line coverage**. The Presentation layer is excluded because it requires
end-to-end tests that are out of scope for unit/integration tests.

When you add or modify code in a BC, you also add the tests that cover it.
A PR that drops a BC's coverage below 100% will be rejected.

---

## 6. Architecture

Kiveto is a **Domain-Driven Design** monolith organised by **Bounded
Contexts**. Each BC under `src/` follows the same internal layout:

```
src/<BC>/
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
- [ ] Tests cover the change (BC coverage stays at 100%)
- [ ] Commit messages follow Conventional Commits
- [ ] Branch name follows the conventions in section 2
- [ ] PR opened against `master`, with a descriptive title and body
- [ ] PR references the BMAD story / artefact when applicable
- [ ] Code review approved (at least one reviewer)
- [ ] CI in CI/CD platform is green
- [ ] Branch deleted after merge
