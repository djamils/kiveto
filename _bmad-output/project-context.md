---
project_name: 'kiveto'
user_name: 'Djamil'
date: '2026-03-29'
sections_completed: ['technology_stack', 'php_rules', 'symfony_ddd_rules', 'testing_rules', 'code_quality_rules', 'critical_rules']
status: 'complete'
rule_count: 62
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

- **PHP** >= 8.5 (strict_types=1 on every file; `\NoDiscard` is a native PHP 8.5 attribute used in this codebase)
- **Symfony** 7.4.* — Framework, Security, Messenger, AssetMapper, Twig, UX Turbo, Stimulus
  - **AssetMapper only** — no Webpack Encore, no `node_modules`, no `webpack.config.js`
- **Doctrine ORM** ^3.5 + Doctrine Migrations Bundle ^3.7
  - `UuidType` comes from `Symfony\Bridge\Doctrine\Types\UuidType`, NOT from Doctrine directly
- **Symfony UID** 7.4.* — UUIDs generated via `SymfonyUuidV7Generator` (UUIDv7)
- **PHPUnit** ^12.5 — `failOnDeprecation`, `failOnNotice`, `failOnWarning` all set to `true`; deprecated API calls = test failure
- **Zenstruck Foundry** ^2.8 — API uses `PersistentProxyObjectFactory`; Foundry 1.x API is invalid here
- **DAMA DoctrineTestBundle** ^8.4 — wraps each test in a rolled-back transaction; never add manual `tearDown()` with TRUNCATE/DELETE
- **PHPStan** ^2.1 at **level max** — no `mixed`, no untyped `array`, no missing return types; use generics (`list<Foo>`, `array<string, Bar>`)
- **PHP-CS-Fixer** ^3.92 + **PHP_CodeSniffer** ^4.0 (PSR-1/2/12)

---

## Critical Implementation Rules

### PHP Rules

- Every file must start with `<?php` + `declare(strict_types=1);`
- All classes are `final` by default unless explicitly designed for extension
- Aggregate named constructors: `create()` records domain events; `reconstitute()` restores state only — **never call `recordDomainEvent()` inside `reconstitute()`**
- Value Objects: `final` class with `private function __construct()` and named factory methods (`fromString()`, etc.)
- Value Object equality: always use `equals(self $other)` which checks `static::class === $other::class` AND value — never use `==` or `===` directly on VOs
- IDs: always via `UuidGeneratorInterface::generate()`, never `Uuid::v7()->toString()` or `new SomeId(Uuid::...)` directly — preserves testability
- Domain exceptions: `new FooException($params)` constructor only — no factory methods (`forId()`, `create()`)
- `match` on exhaustive enums: no `default` branch — PHPStan enforces exhaustiveness
- Repository interface location: **write** repository in `Domain/Repository/`; **read** repository in `Application/Port/`
- Handlers: use `final readonly class` when all dependencies are injected; `final class` (without `readonly`) when state mutation is needed
- Use `\DateTimeImmutable` always, never `DateTime`; enums are backed (`string` or `int`)
- Forbidden (phpcs enforced): `dd`, `var_dump`, `dump`, `exit`, `die`, `eval`, `is_null()` — use `null ===` instead
- Short array syntax `[]` only, never `array()`; named arguments preferred for multi-param constructors

### Symfony / DDD / CQRS Rules

- **Architecture per Bounded Context**: `Domain/` → `Application/` → `Infrastructure/` → `Presentation/`
- **Commands**: implement `CommandInterface`; handlers tagged with `#[AsMessageHandler]`; dispatch via `CommandBusInterface`
- **Queries**: implement `QueryInterface`; handlers tagged with `#[AsMessageHandler]`; dispatch via `QueryBusInterface`
- **Domain Events**: extend `AbstractDomainEvent`; published via `DomainEventPublisher::publish($aggregate)` AFTER `$repository->save()`
- **Doctrine persistence**: Entities (`*Entity`) are infrastructure-only DTOs; never inject or use them in Domain/Application layers
- **Repository save() pattern**: `em->find()` first; if exists → update fields on existing entity; if null → `em->persist(new entity)`; then `em->flush()` — prevents Doctrine identity map conflicts
- **Table naming**: `BoundedContextPrefixNamingStrategy` auto-generates `{bc_snake_case}__{pluralized_entity}` (e.g. `clinic__clinics`, `access_control__clinic_memberships`)
- **Mapper pattern**: `*Mapper` converts between Domain Aggregate and Doctrine Entity; always `final readonly class`
- **Clock**: always inject `ClockInterface`; use `$this->clock->now()` — never `new \DateTimeImmutable()`
- **Current clinic context**: `CurrentClinicContextInterface` provides the active clinic ID in HTTP requests; inject in controllers only, not in domain
- **Controllers**: single-action `__invoke()` controllers extending `AbstractController`; route defined via `#[Route]` attribute
- **Messenger routing**: configured in `config/packages/messenger.yaml` by message type; no manual YAML handler registration needed — `#[AsMessageHandler]` is sufficient
- **Commands/Queries are immutable DTOs**: `public readonly` properties or `readonly` class — no setters
- **Four distinct buses** — never mix them:
  - `CommandBusInterface` — dispatches commands, returns `mixed` (often a string ID)
  - `QueryBusInterface` — dispatches queries, returns query result
  - `EventBusInterface` — dispatches domain events (intra-BC)
  - `IntegrationEventBusInterface` — dispatches integration events (cross-BC); these extend `AbstractIntegrationEvent` and are published via `IntegrationEventPublisher`
- **Handler write invariant order**: (1) load aggregate from repo → (2) call domain method → (3) `$repo->save()` → (4) `$domainEventPublisher->publish($aggregate)`
- **`flush()` in repository only** — never call `$em->flush()` in a handler
- **Handler return type**: `void` or a scalar (`string` ID) — never an Aggregate or Entity
- **`Security` in Presentation layer only** — use `ActorContext` in Application layer handlers when actor identity is needed

### Testing Rules

- **Two test suites**: `tests/Unit/` (`TestCase`) and `tests/Integration/` (`KernelTestCase`)
- **Unit tests mirror source structure**: e.g. `tests/Unit/Clinic/Application/Command/CreateClinic/CreateClinicHandlerTest.php`
- **Required coverage by component type**:
  - **Aggregate** → unit test on domain invariants and recorded events
  - **Command Handler** → unit test with mocked repos/services
  - **Query Handler** → unit test with mocked read repo
  - **Doctrine Repository** → integration test with Foundry factory + `KernelTestCase`
  - **Mapper** → unit test verifying symmetry: `toDomain(toEntity($aggregate))` reconstitutes an equivalent aggregate
- **Domain aggregate tests**: instantiate directly (`Clinic::create(...)`) — no mocks, assert state and `pullDomainEvents()`
- **Handler write tests**: assert `$aggregate->pullDomainEvents()` to verify domain events were recorded — do not rely on mock `save()` alone
- **Integration repo tests**: assert **every property** of the reconstituted aggregate, not just non-null
- **`self::assertSame()` everywhere** — never `assertEquals()`; strict type safety required
- **Unit tests use `createMock()`** for interfaces — no container, no real services
- **Integration tests**: `static::getContainer()->get(SomeInterface::class)` — add `\assert($service instanceof SomeInterface)` after retrieval
- **Foundry factories in tests** (`ClinicEntityFactory::createOne([...])`) — Stories (`*Story`) are for dev/staging fixtures only
- **DAMA auto-rollback**: no manual DB cleanup needed; never add `tearDown()` with TRUNCATE/DELETE
- **VO matching in mock expectations**: use `self::callback(static fn($vo) => $vo->toString() === 'expected')` since `===` on VOs is meaningless
- **Test class**: always `final class FooTest extends TestCase` with `declare(strict_types=1)`
- **Test method naming**: `testVerbNoun()` — e.g. `testFindByIdReconstitutesClinicFromDoctrineEntity()`

### Code Quality & Style Rules

- **All comments and documentation in code must be written in English** — no French comments in source files (CSS, JS, PHP, Twig). User-facing strings (labels, messages) remain in French via translations.
- **PSR-1/2/12** enforced by phpcs; auto-fixed by php-cs-fixer
- **Naming conventions**:
  - BC-prefix on all domain class names: `ClinicId`, `ClinicSlug`, `ClinicStatus` (never generic `Id`, `Slug`, `Status`)
  - Doctrine entities: `*Entity` suffix (e.g. `ClinicEntity`)
  - Doctrine repositories: `Doctrine*Repository` prefix (e.g. `DoctrineClinicRepository`)
  - Mappers: `*Mapper` suffix; always `final readonly class`
  - Domain exceptions: `*Exception` suffix always
  - Read repository interfaces: `*ReadRepositoryInterface`; write: `*RepositoryInterface`
  - Query DTOs: `*Dto` with `public readonly` properties; collections: `*Collection` with `public readonly array $items` and `public readonly int $total`
- **Shared Value Objects**: check `src/Shared/Domain/` before creating a new VO — `Locale`, `TimeZone`, `EmailAddress`, `PhoneNumber`, `PostalAddress` already exist there
- **Cross-BC IDs**: each BC defines its own `*Id` VO even for the same concept (e.g. `Clinic\Domain\ValueObject\ClinicId` and `Scheduling\Domain\ValueObject\ClinicId` are distinct by design — BC isolation)
- **Constructor promotion**: allowed in `final readonly` handlers and mappers; **not used in Domain** (aggregates, Value Objects) — explicit property declarations preferred
- **Vertical alignment of `=`** in aggregate named constructors — follow the existing style:
  ```php
  $clinic->id   = $id;
  $clinic->name = $name;
  ```
- **BC README**: must be maintained when creating a new BC or significantly extending one — include Ubiquitous Language, business invariants, use cases, fixture examples
- **No static factory methods on exceptions** — `new FooException(...)` only
- **Spaces after casts** enforced: `(string) $value`, not `(string)$value`

### Critical Don't-Miss Rules

- **Domain layer has zero framework dependencies** — no `#[ORM\...]`, no Symfony attributes in `Domain/`; Doctrine and Symfony belong in `Infrastructure/` only
- **No Doctrine relations between BCs** — cross-BC references are raw UUID strings only; never `#[ORM\ManyToOne]` to another BC's entity
- **Aggregates reference other aggregates by ID only** — `ClinicGroupId` not `ClinicGroup`; no direct aggregate-to-aggregate references
- **Write repositories expose write + targeted reads only** — `save()`, `findById()`, `findBySlug()`, `existsBySlug()`; global queries (`findAll()`, `findByStatus()`) belong in the read repository (`Application/Port/`)
- **Never expose Aggregate state via public properties** — only through explicit getter methods (`id()`, `name()`, `status()`)
- **`pullDomainEvents()` is destructive** (marked `#[\NoDiscard]`) — it clears the internal event list; call it only once, after `$repo->save()`, inside `DomainEventPublisher::publish()`
- **Domain events carry scalars only** — no Value Objects, no Aggregates, no objects in event properties; events must be serializable for Symfony Messenger
- **No `try/catch` in handlers** unless rethrowing with added context — domain exceptions (`DomainException`, `InvalidArgumentException`) must bubble up
- **VOs validate at construction** — do not re-validate in handlers what the Value Object already enforces
- **Never call `$em->flush()` outside a repository**
- **Never put business logic in controllers or event listeners** — controllers dispatch commands/queries only; event handlers dispatch commands only
- **Never instantiate aggregates with `new Aggregate()`** — always use named constructors (`create()`, `reconstitute()`)
- **Never mock Value Objects in tests** — instantiate them directly: `ClinicId::fromString('018f1b1e-...')`
- **Foundry factories target Doctrine Entities only** — never create a Foundry factory for a Domain Aggregate

---

## Usage Guidelines

**For AI Agents:**

- Read this file before implementing any code in this project
- Follow ALL rules exactly as documented — these are non-negotiable project standards
- When in doubt, prefer the more restrictive option
- Check `src/Shared/Domain/` for existing VOs before creating new ones
- Update this file if new patterns emerge during implementation

**For Humans:**

- Keep this file lean and focused on agent needs — no obvious rules
- Update when technology stack changes or new patterns are established
- Review periodically for outdated rules
- The AccessControl BC README is the reference template for BC documentation

_Last Updated: 2026-03-29_
