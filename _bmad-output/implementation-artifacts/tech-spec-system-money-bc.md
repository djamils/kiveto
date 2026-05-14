---
title: 'System/Money — Universal Multi-Currency Monetary Foundation'
slug: 'system-money-bc'
created: '2026-05-13'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'PHP 8.5'
  - 'Symfony 7.4'
  - 'Doctrine ORM 3.5'
  - 'MySQL/MariaDB'
  - 'ext-bcmath'
  - 'Zenstruck Foundry'
files_to_modify: []
code_patterns:
  - 'AggregateRoot extends with create()/reconstitute() named constructors'
  - 'Doctrine Entity separated from Domain aggregate, bridged by final readonly Mapper'
  - 'BoundedContextPrefixNamingStrategy auto-generates table names'
  - 'CommandInterface / QueryInterface marker interfaces with #[AsMessageHandler] handlers'
  - 'UUIDs as BINARY(16) via Symfony UuidType'
  - 'Foundry fixtures use PersistentProxyObjectFactory (v2 API) — NOT PersistentObjectFactory'
  - 'Story+Factory pattern for Foundry fixtures (see fixtures/System/Translation/)'
  - 'Command handlers publish domain events via DomainEventPublisher::publish($aggregate) — NOT via EventBusInterface directly (CreateAnimalHandler is a legacy outlier to clean up, not a model to follow)'
test_patterns:
  - 'Unit: PHPUnit + createMock() for interfaces, no framework'
  - 'Integration: KernelTestCase + Foundry + MockHttpClient for HTTP providers'
  - '100% line coverage required on all non-Presentation code'
---

# Tech-Spec: System/Money — Universal Multi-Currency Monetary Foundation

**Created:** 2026-05-13

## Overview

### Problem Statement

The codebase has no shared monetary precision primitive. Any bounded context that needs amounts (sale prices, purchase prices, VAT, margins, conversions) would have to reinvent storage and arithmetic, risking float-precision errors and inconsistent rounding behaviour across BCs.

### Solution

Implement a `src/System/Money/` bounded context that provides: an immutable `Money` value object backed by integer minor units (zero floats), `Currency` and `ExchangeRate` aggregates persisted in Doctrine, four rounding policies (accounting, commercial, Swiss-cash, psychological), a `ConversionService` using historical rates, a full CQRS application layer, an ECB exchange-rate provider, 100% test coverage, Foundry fixtures, and a bootstrap console command.

### Scope

**In Scope:**
- Domain layer: `Money` VO, `Currency` aggregate, `ExchangeRate` aggregate, `MoneyCalculator`, `MoneyComparator`, `ConversionService`, `CurrencyRegistry` interface, `RoundingPolicyRegistry`, all 4 `RoundingPolicy` implementations, all domain events and exceptions
- Application layer: `RegisterCurrency` + `ImportExchangeRates` commands, `ConvertMoney` + `GetExchangeRate` + `ListSupportedCurrencies` queries, `ExchangeRateProvider` port
- Infrastructure layer: Doctrine entities + mappers + repositories, `DoctrineCurrencyRegistry`, `EcbExchangeRateProvider`, `FixedExchangeRateProvider`, `CachedExchangeRateProvider`, `LoadCurrenciesCommand`
- Doctrine migration, `doctrine.yaml` + `doctrine_migrations.yaml` config additions
- `composer.json` `ext-bcmath` requirement
- Foundry fixtures: `CurrencyEntityFactory`, `ExchangeRateEntityFactory`, `CurrenciesStory`
- Unit tests (100%) + Integration tests (100%)

**Out of Scope:**
- Accounting ledger / journal entries → future BC Accounting
- Payment methods (card, wire) → future BC Payment
- Invoicing → future BC Billing
- Tenant-level currency preference → owned by Clinic BC
- Embedding `Money` VO inside other BC entities (deferred to first consumer, e.g. Billing/Consultation)

---

## Context for Development

### Codebase Patterns

**General class rules** (from `project-context.md`):
- Every file: `<?php` + `declare(strict_types=1);`
- All classes `final` by default; VOs use `private __construct()` + named factory methods
- Handlers: `final readonly class` (all deps injected, no state mutation)
- Domain exceptions: `new FooException($params)` only — no static factory methods
- No `match` `default` branch on exhaustive enums (PHPStan enforces)
- No constructor promotion in Domain classes (aggregates, VOs) — explicit property declarations

**Aggregate pattern** (confirmed from `src/Shared/Domain/Aggregate/AggregateRoot.php`):
- Extends `App\Shared\Domain\Aggregate\AggregateRoot`
- Named constructor `create(...)` records domain events via `$this->recordDomainEvent()`
- Named constructor `reconstitute(...)` does NOT record events
- Domain events pulled by application layer via `$aggregate->pullDomainEvents()`

**Domain events** (confirmed from `src/Context/Clinic/Domain/Event/ClinicCreated.php` + `AbstractDomainEvent`):
- `final readonly class FooEvent extends AbstractDomainEvent`
- Required constants: `protected const string BOUNDED_CONTEXT = 'money';` and `protected const int VERSION = 1;`
- Required methods: `aggregateId(): string` (returns PK as string) and `payload(): array` (scalar values only)
- **All properties are scalar strings** — no VOs, no objects, no arrays of objects — events must be serializable for Messenger
- Events in Money BC use `BOUNDED_CONTEXT = 'money'`

**Handler write invariant order** (confirmed from `project-context.md` + `CreateClinicHandler`):
1. Load/check via repository
2. Call domain method (`create()`, `updateDisplay()`, etc.)
3. `$repository->save($aggregate)`
4. `$domainEventPublisher->publish($aggregate)`
- `flush()` happens inside the repository, never in handlers

**Doctrine Entity/Mapper/Repository pattern** (confirmed from `src/Context/Clinic/Infrastructure/Persistence/Doctrine/`):
- `*Entity` class: plain Doctrine DTO, `#[ORM\Entity]`, `#[ORM\Table]`, PHP attribute mapping, NO domain types
- `*Mapper` class: `final readonly class`, methods `toDomain(Entity): Aggregate` and `toEntity(Aggregate): Entity`
- `Doctrine*Repository`: class name prefix `Doctrine`, implements domain repository interface
- UUIDs stored as `BINARY(16)` via `Symfony\Bridge\Doctrine\Types\UuidType`, accessed as `Symfony\Component\Uid\Uuid`
- **Repository `save()` pattern**: `$existing = $em->find(Entity::class, $pk); if (null === $existing) { $em->persist($entity); } else { $existing->setFoo(...); } $em->flush();` — prevents Doctrine identity map conflicts (confirmed from `DoctrineClinicRepository`)

**Table naming** (confirmed from `BoundedContextPrefixNamingStrategy`):
- Regex matches `App\System\Money\Infrastructure\Persistence\Doctrine\Entity\*Entity`
- Extracts BC name `Money` → snake_case prefix `money`
- Strips `_entity` suffix from class name, pluralises remainder
- `CurrencyEntity` → `money__currencies` ✓
- `ExchangeRateEntity` → `money__exchange_rates` ✓
- **Do NOT add `#[ORM\Table(name: ...)]`** — let the naming strategy work

**Currency FK in ExchangeRateEntity** (design decision):
- `currency_from` / `currency_to` columns are plain `string` (CHAR 3) — no `#[ORM\ManyToOne]` to `CurrencyEntity`
- Consistent with `ClinicEntity::$currencyCode` being a plain string
- DB-level FK constraint added manually in the migration
- Reason: cross-aggregate ORM relations violate aggregate boundary rules in DDD

**Currency aggregate PK exception**:
- `Currency` uses `CurrencyCode` (CHAR 3) as PK instead of UUIDv7
- This is a documented exception: it is a reference/dictionary aggregate, referenced by FK in `money__exchange_rates`
- Must be documented in `src/System/Money/README.md`

**Shared VOs to reuse** (do NOT redeclare):
- `App\Shared\Domain\ValueObject\CurrencyCode` — confirmed at `src/Shared/Domain/ValueObject/CurrencyCode.php`
- `App\Shared\Domain\Aggregate\AggregateRoot`
- `App\Shared\Domain\Identifier\UuidGeneratorInterface`
- `App\Shared\Domain\Time\ClockInterface`
- `App\Shared\Application\Bus\CommandInterface` / `QueryInterface`

**`bcmath` arithmetic**:
- All arithmetic uses `bcmath` string-based functions — never `float`
- `bcround()` is available from PHP 8.4 (project targets PHP 8.5) ✓
- **`bcround()` mode argument is `\RoundingMode` enum, NOT int constants** — `PHP_ROUND_HALF_EVEN` / `PHP_ROUND_HALF_UP` are for `round()` only and will cause `TypeError` in `bcround()`:
  - `AccountingRounding` → `bcround($amount, $decimals->value(), \RoundingMode::HalfEven)`
  - `CommercialRounding` → `bcround($amount, $decimals->value(), \RoundingMode::HalfAwayFromZero)`
- Scale for intermediate calculations: use `$decimals->value() + 4` for intermediate precision, round at final step

**Console command pattern** (confirmed from `CheckRoleCredentialsConsistencyCommand`):
- `final class FooCommand extends Command` with `#[AsCommand(name: 'app:money:...', description: '...')]`
- `execute(InputInterface $input, OutputInterface $output): int`
- `LoadCurrenciesCommand` injects `CommandBusInterface` + `KernelInterface` (or `string $projectDir`) to locate the YAML; dispatches `RegisterCurrency` per entry

**Fixture pattern** (confirmed from `fixtures/System/Translation/`):
- `PersistentProxyObjectFactory` in `fixtures/System/Money/Factory/` (Foundry v2 API)
- `Story` class in `fixtures/System/Money/Story/`
- Stories are for dev/staging fixtures only — in tests use factories directly (`CurrencyEntityFactory::createOne([...])`)
- Story registered in the main `AppFixtures` or called directly

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Domain/ValueObject/CurrencyCode.php` | Reuse as-is — ISO 4217 validation included |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | Base class for Currency and ExchangeRate |
| `src/Shared/Domain/Identifier/UuidGeneratorInterface.php` | ID generation contract |
| `src/Shared/Domain/Time/ClockInterface.php` | Clock abstraction |
| `src/Shared/Application/Bus/CommandInterface.php` | Command marker |
| `src/Shared/Application/Bus/QueryInterface.php` | Query marker |
| `src/Shared/Application/Event/DomainEventPublisher.php` | **Canonical event dispatch** — inject in all command handlers, call `$this->publisher->publish($aggregate)` after `$repo->save()` |
| `src/Shared/Domain/Event/AbstractDomainEvent.php` | Base class for all domain events — `BOUNDED_CONTEXT`, `VERSION`, `aggregateId()`, `payload()` required |
| `src/Context/Clinic/Domain/Event/ClinicCreated.php` | Reference domain event: `readonly class` (Money BC events add `final` per project default) — scalar props, `aggregateId()` returns string PK |
| `src/Context/Clinic/Application/Command/Clinic/CreateClinic/CreateClinicHandler.php` | Reference handler: `final readonly class`, handler write invariant order |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicRepository.php` | Reference repo: `save()` find+update-or-persist+flush pattern |
| `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php` | Understand auto table naming |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php` | Entity attribute pattern |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMapper.php` | Mapper pattern |
| `config/packages/doctrine.yaml` | Add Money mapping block |
| `config/packages/doctrine_migrations.yaml` | Add Money migration path |
| `composer.json` | Add `ext-bcmath` to require |
| `fixtures/System/Translation/Story/` | Story fixture pattern reference |

### Technical Decisions

1. **No float arithmetic** — all amounts stored as `int` (minor units), all intermediate arithmetic via `bcmath` string functions.
2. **`CurrencyCode` reused from Shared** — not duplicated in Money BC.
3. **`Currency` PK = CHAR(3)** — documented exception to UUIDv7 rule; it is a static reference dictionary.
4. **`ExchangeRateEntity` FK as plain strings** — `currency_from`/`currency_to` are `string` columns, not `#[ORM\ManyToOne]` relations. DB FK added in migration.
5. **`ExchangeRateProvider` is Application Port** — not Domain — because it calls an external HTTP gateway. `ConversionService` reads only from the Domain repository.
6. **`HistoricalRate` lives in Domain** — it is a pure data VO; the fact that it is returned by an Application Port does not make it Application-layer.
7. **`RoundingPolicy::round()` receives `CurrencyCode`** — allows `SwissCashRounding` to validate the currency without coupling policy implementations to the registry.
8. **`PsychologicalRounding` is one class** — configured via `PsychologicalStrategy` enum in constructor; not split into three subclasses.
9. **`CachedExchangeRateProvider` is a Decorator** — wraps any `ExchangeRateProvider`, cache key = `(pair, date)`, TTL configurable.
10. **`ConversionService` staleness check** — throws `StaleExchangeRateException` if best matching rate is more than 7 days before requested date. TTL is `int $maxStalenessInDays = 7` constructor parameter (not a magic constant).
11. **`ConversionService` in Domain/Service/ with repository dependency** — deliberate DDD choice: a domain service may depend on a Domain repository interface (`Domain/Repository/ExchangeRateRepository`). The interface is domain-layer; no framework or Infrastructure import. Consistent with how aggregate factories sometimes depend on domain specifications. This is NOT a cross-layer violation.
12. **`CurrencyRegistry` interface in Domain/Service/** — deliberate: `MoneyCalculator` and `ConversionService` are domain services that need currency metadata. Moving `CurrencyRegistry` to `Application/Port/` would force Domain services to depend on an Application interface, which violates DDD layering (Domain must not know Application). `DoctrineCurrencyRegistry` (Infrastructure) implements the Domain interface — Infrastructure implementing Domain interfaces is standard and correct.
13. **`ExchangeRatePair.of()` throws `\InvalidArgumentException`** when `from == to` — no separate exception class; the Shared `CurrencyCode` already uses `\InvalidArgumentException` for validation.
14. **`InvalidCurrencyCodeException` removed** — `CurrencyCode::fromString()` in Shared already throws `\InvalidArgumentException`. A duplicate exception class is dead code. Removed from the exceptions list.
15. **`ConversionService::convert()` same-currency short-circuit** — if `$from->currency()->equals($to)`, return the input `Money` unchanged with zero repository lookups. Invariant: no rate is needed for same-currency amounts.

---

## Implementation Plan

### Tasks

#### Phase 1 — Shared infrastructure setup

- [ ] Add `ext-bcmath` to `composer.json` `require`
- [ ] Add `Money` mapping block to `config/packages/doctrine.yaml`
- [ ] Add `DoctrineMigrations\Money` path to `config/packages/doctrine_migrations.yaml`
- [ ] Create `src/System/Money/README.md` documenting BC purpose and the CHAR(3) PK exception

#### Phase 2 — Domain layer (no framework dependencies)

**Exceptions (implement first — no dependencies):**
- [ ] `src/System/Money/Domain/Exception/CurrencyMismatchException.php` — thrown by `MoneyCalculator::add/subtract` and `MoneyComparator::isGreaterThan/isLessThan/isGreaterThanOrEqual/isLessThanOrEqual/max/min` when currencies differ
- [ ] `src/System/Money/Domain/Exception/AllocationException.php` — thrown by `MoneyCalculator::allocate` when ratios array is empty or all ratios are zero
- [ ] `src/System/Money/Domain/Exception/UnknownCurrencyException.php` — thrown by `CurrencyRegistry::get()` when currency code not found
- [ ] `src/System/Money/Domain/Exception/ExchangeRateNotFoundException.php` — thrown by `ConversionService::convert()` and `GetExchangeRateHandler` when no rate exists for the pair
- [ ] `src/System/Money/Domain/Exception/StaleExchangeRateException.php` — thrown by `ConversionService::convert()` when best available rate is older than `$maxStalenessInDays`
- [ ] `src/System/Money/Domain/Exception/SwissCashRoundingRequiresChfException.php` — thrown by `SwissCashRounding::round()` when `$currency` is not CHF
  - Note: `InvalidCurrencyCodeException` is NOT created — `CurrencyCode::fromString()` in Shared already throws `\InvalidArgumentException`

**Value Objects:**
- [ ] `src/System/Money/Domain/ValueObject/CurrencyDecimals.php` — `of(int): self`, range 0–4
- [ ] `src/System/Money/Domain/ValueObject/CurrencySymbol.php` — `fromString(string): self`, non-empty, max 8 chars
- [ ] `src/System/Money/Domain/ValueObject/Money.php` — `fromMinorUnits`, `zero`, `fromDecimalString`, accessors, `equals`
- [ ] `src/System/Money/Domain/ValueObject/ExchangeRateId.php` — `fromString` + `generate(UuidGeneratorInterface)`
- [ ] `src/System/Money/Domain/ValueObject/ExchangeRatePair.php` — `of(from, to)`, `inverse()`, throws if `from == to`
- [ ] `src/System/Money/Domain/ValueObject/HistoricalRate.php` — props: `string $rate` (bcmath decimal, e.g. `"0.9523"`), `\DateTimeImmutable $effectiveDate`, `string $source` (e.g. `"ECB"`, `"MANUAL"`, `"FIXED"`); `final readonly class` with named constructor `of(string, \DateTimeImmutable, string): self`
- [ ] `src/System/Money/Domain/ValueObject/RoundingPolicyId.php` — enum: `ACCOUNTING, COMMERCIAL, SWISS_CASH, PSYCHOLOGICAL`
- [ ] `src/System/Money/Domain/ValueObject/PsychologicalStrategy.php` — enum: `NINETY_NINE, NINETY, TEN_CENTS`
  - Note: DO NOT create `RoundingMode.php` — use PHP built-in `\RoundingMode` enum directly (PHP 8.4+)

**Events:**
- [ ] `src/System/Money/Domain/Event/CurrencyRegistered.php`
- [ ] `src/System/Money/Domain/Event/CurrencyActivated.php`
- [ ] `src/System/Money/Domain/Event/CurrencyDeactivated.php`
- [ ] `src/System/Money/Domain/Event/ExchangeRateImported.php`

**Rounding policies:**
- [ ] `src/System/Money/Domain/RoundingPolicy/RoundingPolicy.php` — interface with `id()` + `round(string, CurrencyCode, CurrencyDecimals): string`
- [ ] `src/System/Money/Domain/RoundingPolicy/AccountingRounding.php` — HALF_EVEN via `bcround()`
- [ ] `src/System/Money/Domain/RoundingPolicy/CommercialRounding.php` — HALF_UP via `bcround()`
- [ ] `src/System/Money/Domain/RoundingPolicy/SwissCashRounding.php` — nearest 0.05, throws `SwissCashRoundingRequiresChfException` if `$currency` ≠ CHF
  - bcmath-only algorithm (no floats): `bcmul(bcround(bcdiv($amount, '0.05', 4), 0, \RoundingMode::HalfAwayFromZero), '0.05', $decimals->value())`
  - Examples: `1.425` → `1.40`, `1.426` → `1.45`, `1.475` → `1.50`
- [ ] `src/System/Money/Domain/RoundingPolicy/PsychologicalRounding.php` — `__construct(PsychologicalStrategy $strategy)`; algorithms:
  - `NINETY_NINE`: `bcadd(bcfloor($amount), '0.99', $decimals->value())` — e.g. `19.47` → `19.99`, `19.99` → `19.99`, `20.00` → `20.99`
  - `NINETY`: `bcadd(bcfloor($amount), '0.90', $decimals->value())` — e.g. `19.47` → `19.90`, `19.90` → `19.90`, `20.00` → `20.90`
  - `TEN_CENTS`: round up to nearest 0.10 — `bcmul(bcceil(bcdiv($amount, '0.10', 4)), '0.10', $decimals->value())` — e.g. `19.47` → `19.50`, `19.50` → `19.50`, `19.51` → `19.60`
  - All algorithms are bcmath-only (no floats)

**Domain services:**
- [ ] `src/System/Money/Domain/Service/CurrencyRegistry.php` — interface: `get`, `listActive`, `has`
- [ ] `src/System/Money/Domain/Service/RoundingPolicyRegistry.php` — `get`, `accounting`, `commercial`, `swissCash`, `psychological`
- [ ] `src/System/Money/Domain/Service/MoneyCalculator.php` — `add`, `subtract`, `multiply`, `divide`, `percentage`, `applyCoefficient`, `allocate`, `abs`, `neg`; depends on `CurrencyRegistry`
- [ ] `src/System/Money/Domain/Service/MoneyComparator.php` — cross-currency behavior:
  - `equals(Money $a, Money $b): bool` — returns `false` silently when currencies differ (no throw)
  - `isGreaterThan/isLessThan/isGreaterThanOrEqual/isLessThanOrEqual(Money $a, Money $b): bool` — throws `CurrencyMismatchException` when currencies differ
  - `max(Money ...$amounts): Money` and `min(Money ...$amounts): Money` — throw `\InvalidArgumentException` if empty or mixed currencies
- [ ] `src/System/Money/Domain/Service/ConversionService.php` — `convert`, depends on `ExchangeRateRepository + RoundingPolicyRegistry + CurrencyRegistry + ClockInterface + int $maxStalenessInDays = 7`; staleness check: `$effectiveDate < $requestedDate - $maxStalenessInDays days`; same-currency short-circuit before any lookup

**Repository interfaces:**
- [ ] `src/System/Money/Domain/Repository/CurrencyRepository.php` — `save(Currency): void` + `findByCode(CurrencyCode): ?Currency` (needed by `RegisterCurrencyHandler` for idempotent upsert — consistent with `ClinicRepositoryInterface::findById`)
- [ ] `src/System/Money/Domain/Repository/ExchangeRateRepository.php` — `save(ExchangeRate): void` + `findRateAt(ExchangeRatePair, DateTimeImmutable): ?ExchangeRate` — SQL semantics: most recent row where `effective_date ≤ $date ORDER BY effective_date DESC LIMIT 1`

**Aggregates:**
- [ ] `src/System/Money/Domain/Currency.php` — `create` / `reconstitute`, `activate`, `deactivate`, `updateDisplay`
  - Properties: `CurrencyCode $code` (PK), `CurrencySymbol $symbol`, `CurrencyDecimals $decimals`, `string $displayName`, `bool $active`, `\DateTimeImmutable $createdAt`, `\DateTimeImmutable $updatedAt`
  - `create(CurrencyCode, CurrencySymbol, CurrencyDecimals, string $displayName, ClockInterface $clock): self` — records `CurrencyRegistered`; `active = true`
  - `activate()` — idempotent: only records `CurrencyActivated` if currently inactive
  - `deactivate()` — idempotent: only records `CurrencyDeactivated` if currently active
  - `updateDisplay(CurrencySymbol, CurrencyDecimals, string $displayName)` — updates without event (used for idempotent bootstrap)
- [ ] `src/System/Money/Domain/ExchangeRate.php` — `create` / `reconstitute`
  - Properties: `ExchangeRateId $id`, `ExchangeRatePair $pair`, `string $rate` (decimal string e.g. `"0.9523"`), `\DateTimeImmutable $effectiveDate`, `string $source`, `\DateTimeImmutable $createdAt`
  - `create(ExchangeRateId, ExchangeRatePair, string $rate, \DateTimeImmutable $effectiveDate, string $source, \DateTimeImmutable $createdAt): self` — validates `bccomp($rate, '0') > 0`; records `ExchangeRateImported`

#### Phase 3 — Application layer

- [ ] `src/System/Money/Application/Port/ExchangeRateProvider.php` — interface with full signatures:
  ```php
  interface ExchangeRateProvider {
      public function getCurrentRate(ExchangeRatePair $pair): HistoricalRate;
      public function getRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): HistoricalRate;
      /** @return list<HistoricalRate> */
      public function fetchSnapshot(\DateTimeImmutable $date): array;
  }
  ```
- [ ] `src/System/Money/Application/Command/RegisterCurrency/RegisterCurrency.php` — `implements CommandInterface`; props: `public readonly string $code`, `public readonly string $symbol`, `public readonly int $decimals`, `public readonly string $displayName`
- [ ] `src/System/Money/Application/Command/RegisterCurrency/RegisterCurrencyHandler.php` — `final readonly class`, `#[AsMessageHandler]`; inject `CurrencyRepository + DomainEventPublisher + ClockInterface`; logic: `findByCode()` → if null: `Currency::create(..., $clock)` + `save` + `publish`; if exists: `updateDisplay(...)` + `save` (no event for update)
- [ ] `src/System/Money/Application/Command/ImportExchangeRates/ImportExchangeRates.php` — props: `public readonly \DateTimeImmutable $date`, `public readonly string $source`
- [ ] `src/System/Money/Application/Command/ImportExchangeRates/ImportExchangeRatesHandler.php` — `final readonly class`; inject `ExchangeRateProvider + ExchangeRateRepository + DomainEventPublisher + UuidGeneratorInterface + ClockInterface`; call `provider->fetchSnapshot($date)`, create one `ExchangeRate` per `HistoricalRate`, save, publish
- [ ] `src/System/Money/Application/Query/ConvertMoney/ConvertMoney.php` — props: `public readonly Money $amount`, `public readonly CurrencyCode $targetCurrency`, `public readonly \DateTimeImmutable $date`, `public readonly RoundingPolicy $rounding`; `ConvertMoneyHandler` calls `ConversionService::convert`; returns `Money`
- [ ] `src/System/Money/Application/Query/GetExchangeRate/GetExchangeRate.php` — props: `public readonly ExchangeRatePair $pair`, `public readonly \DateTimeImmutable $date`; `GetExchangeRateHandler` calls `ExchangeRateRepository::findRateAt`; throws `ExchangeRateNotFoundException` if null; returns `ExchangeRate`
- [ ] `src/System/Money/Application/Query/ListSupportedCurrencies/ListSupportedCurrencies.php` — no properties; `ListSupportedCurrenciesHandler` calls `CurrencyRegistry::listActive()`; returns `list<Currency>`

#### Phase 4 — Infrastructure layer

**Doctrine entities:**
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Entity/CurrencyEntity.php` — `#[ORM\Entity]`, CHAR(3) PK, all columns
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Entity/ExchangeRateEntity.php` — `#[ORM\Entity]`, BINARY(16) PK, DECIMAL(20,10) rate, string currency columns, indexes

**Mappers:**
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Mapper/CurrencyMapper.php` — `final readonly`, `toDomain` + `toEntity`
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Mapper/ExchangeRateMapper.php`

**Repositories:**
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Repository/DoctrineCurrencyRepository.php`
- [ ] `src/System/Money/Infrastructure/Persistence/Doctrine/Repository/DoctrineExchangeRateRepository.php`

**Registry:**
- [ ] `src/System/Money/Infrastructure/Registry/DoctrineCurrencyRegistry.php` — implements `CurrencyRegistry`; inject `DoctrineCurrencyRepository + #[Autowire(service: 'cache.app')] CacheInterface` (`Symfony\Contracts\Cache\CacheInterface` — uniquely autowireable with `#[Autowire]`, no `services.yaml` entry needed)

**Exchange rate providers:**
- [ ] `src/System/Money/Infrastructure/ExchangeRate/FixedExchangeRateProvider.php` — fixed rates array (`array<string, string>` keyed by `"FROM/TO"` e.g. `"EUR/CHF" => "0.9523"`), for tests/bootstrap; `getCurrentRate` and `getRateAt` return same fixed value; `fetchSnapshot` returns all pairs as `HistoricalRate` with `$date` and source `"FIXED"`
- [ ] `src/System/Money/Infrastructure/ExchangeRate/EcbExchangeRateProvider.php` — fetches ECB daily XML feed:
  - URL: `https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml`
  - Method: GET, response: `application/xml`
  - Response format: `<gesmes:Envelope><Cube><Cube time="YYYY-MM-DD"><Cube currency="USD" rate="1.0985"/>…</Cube></Cube></gesmes:Envelope>`
  - Base currency is always EUR; all rates are EUR→X
  - `fetchSnapshot(\DateTimeImmutable $date)`: calls URL, parses XML with `SimpleXMLElement`, returns `list<HistoricalRate>` for all currencies in the feed, source = `"ECB"`
  - `getCurrentRate` / `getRateAt`: call `fetchSnapshot` and filter by pair — throws `ExchangeRateNotFoundException` if pair not in feed
  - Integration test mock: use `MockResponse` with sample XML (include 3+ currencies); verify parsing and `HistoricalRate` construction
- [ ] `src/System/Money/Infrastructure/ExchangeRate/CachedExchangeRateProvider.php` — Decorator wrapping `ExchangeRateProvider`; inject `CacheInterface` + `int $ttl = 3600`; cache key: `"ecb_rate_{from}_{to}_{date}"` for `getRateAt`, `"ecb_snapshot_{date}"` for `fetchSnapshot`

**Resources & console:**
- [ ] `src/System/Money/Infrastructure/Resources/currencies.yaml` — EUR, CHF, GBP, USD entries
- [ ] `src/System/Money/Infrastructure/Console/LoadCurrenciesCommand.php` — `#[AsCommand(name: 'app:money:load-currencies')]`; inject `CommandBusInterface` + `#[Autowire(param: 'kernel.project_dir')] string $projectDir`; reads `$projectDir/src/System/Money/Infrastructure/Resources/currencies.yaml`; dispatches `RegisterCurrency` for each entry; idempotent

#### Phase 5 — Migration

- [ ] `migrations/Money/Version<TS>.php` — creates `money__currencies`, `money__exchange_rates` with:
  - `money__currencies`: `code CHAR(3) PK`, `symbol VARCHAR(8)`, `decimals SMALLINT`, `display_name VARCHAR(64)`, `active TINYINT(1) DEFAULT 1`, `created_at DATETIME`, `updated_at DATETIME`
  - `money__exchange_rates`: `id BINARY(16) PK`, `currency_from CHAR(3) NOT NULL`, `currency_to CHAR(3) NOT NULL`, `rate DECIMAL(20,10) NOT NULL`, `effective_date DATE NOT NULL`, `source VARCHAR(32) NOT NULL`, `created_at DATETIME NOT NULL`
  - FK: `currency_from → money__currencies(code)`, `currency_to → money__currencies(code)`
  - `UNIQUE INDEX uniq_money_xr_from_to_date_src (currency_from, currency_to, effective_date, source)`
  - `INDEX idx_money_xr_from_to_date (currency_from, currency_to, effective_date)` — supports `findRateAt` ORDER BY DESC LIMIT 1

#### Phase 6 — Fixtures

- [ ] `fixtures/System/Money/Factory/CurrencyEntityFactory.php` — `PersistentProxyObjectFactory` (Foundry v2)
- [ ] `fixtures/System/Money/Factory/ExchangeRateEntityFactory.php` — `PersistentProxyObjectFactory` (Foundry v2)
- [ ] `fixtures/System/Money/Story/CurrenciesStory.php` — creates EUR, CHF, GBP, USD currency entries
- [ ] `fixtures/System/Money/Story/ExchangeRatesStory.php` — seeds representative exchange rates (at minimum EUR→CHF, EUR→GBP, EUR→USD, one historical date + one recent date per pair) to exercise the `money__exchange_rates` table in dev fixtures (CLAUDE.md §7)

#### Phase 7 — Tests

**Unit (`tests/Unit/System/Money/`):**
- [ ] `Domain/ValueObject/MoneyTest.php` — all factories, accessors, `equals`, `fromDecimalString` edge cases
- [ ] `Domain/ValueObject/CurrencyDecimalsTest.php`
- [ ] `Domain/ValueObject/CurrencySymbolTest.php`
- [ ] `Domain/ValueObject/ExchangeRateIdTest.php`
- [ ] `Domain/ValueObject/ExchangeRatePairTest.php`
- [ ] `Domain/ValueObject/HistoricalRateTest.php`
- [ ] `Domain/CurrencyTest.php` — invariants, events recorded by `create`, none by `reconstitute`
- [ ] `Domain/ExchangeRateTest.php` — invariants, `ExchangeRateImported` event
- [ ] `Domain/Service/MoneyCalculatorTest.php` — all operations + `allocate` residual distribution + `CurrencyMismatchException`
- [ ] `Domain/Service/MoneyComparatorTest.php` — all comparisons including mixed-currency throws/false
- [ ] `Domain/RoundingPolicy/AccountingRoundingTest.php` — HALF_EVEN table
- [ ] `Domain/RoundingPolicy/CommercialRoundingTest.php` — HALF_UP table
- [ ] `Domain/RoundingPolicy/SwissCashRoundingTest.php` — 0.05 rounding + CHF guard
- [ ] `Domain/RoundingPolicy/PsychologicalRoundingTest.php` — all 3 strategies
- [ ] `Domain/Service/RoundingPolicyRegistryTest.php`
- [ ] `Domain/Service/ConversionServiceTest.php` — same currency, found rate, stale rate, not found
- [ ] `Application/Command/RegisterCurrencyHandlerTest.php` — create + idempotent update
- [ ] `Application/Command/ImportExchangeRatesHandlerTest.php`
- [ ] `Application/Query/ConvertMoneyHandlerTest.php`
- [ ] `Application/Query/GetExchangeRateHandlerTest.php` — found + not found
- [ ] `Application/Query/ListSupportedCurrenciesHandlerTest.php`

**Unit — Mappers (project-context.md: mappers → unit tests, NOT integration):**
- [ ] `tests/Unit/System/Money/Infrastructure/Persistence/Doctrine/Mapper/CurrencyMapperTest.php` — `toDomain(toEntity($aggregate))` symmetry
- [ ] `tests/Unit/System/Money/Infrastructure/Persistence/Doctrine/Mapper/ExchangeRateMapperTest.php`

**Integration (`tests/Integration/System/Money/`):**
- [ ] `Infrastructure/Persistence/Doctrine/Repository/DoctrineCurrencyRepositoryTest.php` — `KernelTestCase` + Foundry
- [ ] `Infrastructure/Persistence/Doctrine/Repository/DoctrineExchangeRateRepositoryTest.php`
- [ ] `Infrastructure/Registry/DoctrineCurrencyRegistryTest.php` — cache + DB
- [ ] `Infrastructure/ExchangeRate/EcbExchangeRateProviderTest.php` — `MockHttpClient`
- [ ] `Infrastructure/Console/LoadCurrenciesCommandTest.php` — idempotence (two runs → same state)

---

### Acceptance Criteria

#### AC-01: Money VO precision
**Given** a decimal string `"18.50"` and `CurrencyDecimals::of(2)` and `CurrencyCode::fromString("EUR")`  
**When** `Money::fromDecimalString("18.50", EUR, decimals2)` is called  
**Then** `$money->minorUnits()` returns `1850` and `$money->toDecimalString(decimals2)` returns `"18.50"`

**Given** a decimal string with more decimal places than `CurrencyDecimals` allows  
**When** `Money::fromDecimalString("18.505", EUR, decimals2)` is called  
**Then** `\InvalidArgumentException` is thrown

#### AC-02: No floats anywhere
**Verified by PHPStan level max** (not a runtime assertion — PHPStan will catch `float` type usage at analysis time).
Rule: all intermediate and final values computed with `bcmath` string functions. No `float` type in any Money BC class. A custom PHPStan rule is not required — `level: max` with `no-mixed` coverage is sufficient to surface any float escape.

#### AC-03: Currency aggregate lifecycle
**Given** a new currency code `"EUR"`  
**When** `Currency::create(...)` is called  
**Then** one `CurrencyRegistered` event is recorded and the currency is active by default

**Given** an active currency  
**When** `deactivate()` is called twice  
**Then** only one `CurrencyDeactivated` event is recorded (idempotent)

#### AC-04: Rounding policies
**Given** the amount `"1.245"` and `CommercialRounding`  
**When** `round("1.245", EUR, decimals2)` is called  
**Then** result is `"1.25"` (HALF_UP)

**Given** the amount `"1.245"` and `AccountingRounding`  
**When** `round("1.245", EUR, decimals2)` is called  
**Then** result is `"1.24"` (HALF_EVEN / banker's rounding)

**Given** `SwissCashRounding` and `CurrencyCode::fromString("EUR")`  
**When** `round(...)` is called  
**Then** `SwissCashRoundingRequiresChfException` is thrown

**Given** `PsychologicalRounding(NINETY_NINE)` and amount `"19.47"`  
**When** `round(...)` is called  
**Then** result is `"19.99"`

#### AC-05: MoneyCalculator allocate
**Given** `Money(100, EUR)` and ratios `[1, 1, 1]`  
**When** `allocate(money, [1,1,1], rounding)` is called  
**Then** result is 3 Money values summing exactly to `100` minor units (no penny lost or created)

**Given** `Money::zero(EUR)` and ratios `[1, 1, 1]`  
**When** `allocate(zero, [1,1,1], rounding)` is called  
**Then** result is `[zero, zero, zero]` — no throw, sum is still `0`

#### AC-05b: Money equals cross-currency
**Given** `Money(100, EUR)` and `Money(100, CHF)`  
**When** `MoneyComparator::equals($a, $b)` is called  
**Then** returns `false` — no exception thrown (strict silent comparison)

#### AC-05c: MoneyComparator max/min empty
**Given** an empty array passed to `MoneyComparator::max()` or `min()`  
**When** the method is called  
**Then** `\InvalidArgumentException` is thrown

#### AC-05d: Money fromDecimalString invalid format
**Given** the string `"abc"` passed to `Money::fromDecimalString()`  
**When** the factory is called  
**Then** `\InvalidArgumentException` is thrown

#### AC-05e: ConversionService same-currency short-circuit
**Given** `Money(1850, EUR)` and `targetCurrency = CurrencyCode::fromString("EUR")`  
**When** `ConversionService::convert(money, EUR, date, rounding)` is called  
**Then** the input `Money` is returned unchanged and NO repository lookup is performed

#### AC-06: ConversionService staleness
**Given** the best available rate is 8 days older than the requested date  
**When** `ConversionService::convert(...)` is called  
**Then** `StaleExchangeRateException` is thrown

#### AC-06b: ConversionService no rate found
**Given** no exchange rate exists in the repository for the requested pair  
**When** `ConversionService::convert(...)` is called  
**Then** `ExchangeRateNotFoundException` is thrown (not `StaleExchangeRateException`)

#### AC-06c: Currency activate idempotence
**Given** a currency that is already active  
**When** `activate()` is called  
**Then** no `CurrencyActivated` event is recorded and the currency remains active (idempotent no-op)

**Given** an inactive currency → `activate()` → `activate()` again  
**When** the second `activate()` is called  
**Then** only one `CurrencyActivated` event total across both calls

#### AC-07: Table naming
**Given** Doctrine ORM is booted  
**When** schema is generated  
**Then** `CurrencyEntity` maps to table `money__currencies` and `ExchangeRateEntity` maps to `money__exchange_rates` without any `#[ORM\Table(name: ...)]` override

#### AC-08: Bootstrap idempotence
**Given** `app:money:load-currencies` has been run once  
**When** it is run a second time  
**Then** the database still contains exactly 4 currencies (EUR, CHF, GBP, USD) with no duplicates and updated symbol/decimals/displayName if they differed

#### AC-09: 100% coverage
**Given** all unit and integration tests pass  
**When** PHPUnit coverage report is generated for `src/System/Money/`  
**Then** line coverage is 100% (excluding no Presentation layer exists in this BC)

---

## Additional Context

### Dependencies

- `ext-bcmath` (add to `composer.json`)
- `symfony/http-client` (already in project for ECB provider)
- `psr/cache` (for `CachedExchangeRateProvider` — already in project via Symfony Cache)
- Existing Shared interfaces: `AggregateRoot`, `UuidGeneratorInterface`, `ClockInterface`, `CurrencyCode`, `CommandInterface`, `QueryInterface`

### Testing Strategy

- **Unit tests**: `final class FooTest extends TestCase` with `declare(strict_types=1)`. `createMock()` for interfaces. No container, no real services.
- **Test method naming**: `testVerbNoun()` — e.g. `testAddThrowsCurrencyMismatchExceptionOnMixedCurrencies()`
- **Assertions**: `self::assertSame()` everywhere — never `assertEquals()`
- **VOs in mock expectations**: `self::callback(static fn($vo) => $vo->toString() === 'expected')` since `===` on VOs is meaningless
- **Domain aggregate tests**: instantiate directly (`Currency::create(...)`) — no mocks; assert `pullDomainEvents()` result
- **Integration tests**: `KernelTestCase` + Foundry factories (not Stories) for DB tests. `MockHttpClient` for ECB provider. `CommandTester` for console command. After `getContainer()->get()`, add `\assert($service instanceof Interface)`.
- **Integration tests require the test DB schema to exist** — `doctrine:migrations:migrate` or equivalent must have been run on the test DB before integration tests execute (handled by the project's existing test setup).
- PHPStan level max must pass — use proper generics/types, no `mixed`.
- PHP-CS-Fixer + PHPCS must pass.

### Notes

- `Currency::$code` (CHAR 3) as PK is a documented exception to the UUIDv7 rule. Document it in `src/System/Money/README.md`.
- `HistoricalRate` is a Domain VO even though it surfaces through an Application Port — it carries no framework dependency, just data.
- `ConversionService` staleness TTL (default 7 days) should be a constructor parameter, not a magic constant.
- `EcbExchangeRateProvider` has no DB fallback — the caller (`ImportExchangeRatesHandler`) is responsible for deciding what to do on HTTP failure.
- All user-facing strings (currency display names in `currencies.yaml`) are in French per project convention.
