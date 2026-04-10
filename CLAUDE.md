# Claude Code instructions for Kiveto

This file is auto-loaded by Claude Code at the start of every session in
this project. It pins the non-negotiable directives so that any agent
working on the repo behaves consistently across sessions and machines.

> **First action of any session:** read `CONTRIBUTING.md` for the full git
> flow, branch and commit conventions, BMAD workflow and architectural
> rules. This file is the short, MUST-OBEY summary; `CONTRIBUTING.md` is
> the human-readable source of truth.

---

## Project at a glance

- **Stack:** Symfony 7 + Doctrine ORM + Tailwind CSS v4 + Stimulus + Twig.
- **Architecture:** DDD monolith organised by Bounded Contexts under
  `src/<BC>/{Domain,Application,Infrastructure}`. Presentation lives under
  `src/Presentation/<App>/`.
- **Bounded Contexts:** AccessControl, Animal, Client, Clinic, Consultation,
  IdentityAccess, Scheduling, Shared, Translation. Each has its canonical
  description in `src/<BC>/README.md`.
- **Planning method:** BMAD (`_bmad/`), artefacts in `_bmad-output/`.
- **Containerised dev:** everything goes through Docker via `make` targets.

---

## Non-negotiable directives

These rules apply to every change you make in this repo. They override any
default behaviour. They are intentionally short and absolute.

### 1. `make ci` must be green before every commit

`make ci` chains `php-cs-fixer.dry-run → phpcs → phpstan → tailwind-build →
test`. Run it before every commit. If it fails, fix the failures rather
than committing yellow. Tests run with `failOnDeprecation`,
`failOnNotice`, `failOnWarning` and PHPStan at `level: max` — warnings are
failures.

### 2. Frontend assets are rebuilt with `make assets`

Never use `php bin/console asset-map:compile` directly — it leaves the
debug-mode warning that prevents fresh assets from being served. `make
assets` handles whatever extra step is needed so the changes show up
immediately on reload.

### 3. Test coverage stays at 100% per BC (excl. Presentation)

Every BC except the Presentation layer must stay at 100% line coverage.
When you modify code in a BC, also add the tests that cover it. A change
that drops a BC's coverage below 100% is rejected.

### 4. Git flow — GitHub Flow with no `staging` branch

- Branch off `master`. Branch names use `<type>/<slug>` where `<type>` is
  one of `feature`, `fix`, `hotfix`, `chore`. See `CONTRIBUTING.md` §2.
- One branch = one logical change. No multi-purpose branches.
- Commit messages follow Conventional Commits (`feat:`, `fix:`,
  `chore(scope):`, `test:`, `refactor:`, `docs:`, `chore(ci):`, …). See
  `CONTRIBUTING.md` §3 for the full type list.
- Merge back to `master` via Pull Request. Direct pushes to `master` are
  forbidden.
- There is no `staging` branch — staging and production are both deployed
  from the head of `master`.
- Never commit changes unless the user explicitly asks you to.

### 5. Code style and language

- All code comments are in **English**. User-facing strings stay in **French**.
- Follow the existing PHP-CS-Fixer + PHPCS configuration. Don't fight the
  rules — if a rule conflicts with what you want to do, the rule wins.
- PHPStan runs at `level: max`. Don't use `mixed` or untyped arrays at API
  boundaries; add proper type annotations.
- **Single-action controllers only.** Every controller has exactly one
  public method (`__invoke()`) handling exactly one route. No multi-action
  controllers — split them into separate classes instead.

### 6. Bounded Context boundaries

Cross-BC communication happens **only** through:
- Application command/query buses
- Application Port interfaces, implemented by adapters in the calling BC's
  `Infrastructure/Adapter/<OtherBC>/` folder

Never `use` another BC's Domain or Infrastructure entities directly. If
you need data from another BC, route it through a port.

### 7. BMAD for planning

For non-trivial work, use BMAD skills (`/bmad-*`) to plan before coding.
Quick reference is in `CONTRIBUTING.md` §7. Typical chain:
`brainstorming → product-brief → prd → architecture → epics-and-stories →
dev-story`. Skip steps for small changes — use `/bmad-quick-spec` then
`/bmad-quick-dev` for tweaks and small features.

---

## Useful pointers

| You want to know…                       | Look at…                              |
|-----------------------------------------|---------------------------------------|
| Full git flow / commit conventions      | `CONTRIBUTING.md`                     |
| BMAD skills available                   | `_bmad/bmm/` and `_bmad-output/`       |
| What a BC does                          | `src/<BC>/README.md`                  |
| UI Kit components and roadmap           | `_bmad-output/ui-kit-roadmap.md`      |
| Current project context                 | `_bmad-output/project-context.md`     |
| Past implementation notes (legacy)      | `docs/legacy/` (untracked, on disk)   |
| Make targets reference                  | `Makefile` (`make help`)              |

---

## Things NOT to do

- Don't run `php bin/console asset-map:compile` — use `make assets`.
- Don't bypass `make ci` "just for this one commit".
- Don't commit a change that drops a BC's coverage below 100%.
- Don't push directly to `master`.
- Don't create a `staging` branch.
- Don't `use` another BC's internals — route through Application ports.
- Don't write code comments in French.
- Don't add fallbacks/feature flags/abstractions that aren't asked for.
- Don't skip pre-commit hooks (`--no-verify`) or sign-off (`--no-gpg-sign`)
  unless the user explicitly tells you to.
- Don't create new documentation files (`*.md`) unless explicitly asked.
- Don't put multiple routes/actions in a single controller — one
  `__invoke()` per controller, no exceptions.
