---
title: 'System/Taxation Bounded Context — MVP (FR only)'
slug: 'system-taxation-bc'
created: '2026-05-15'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4, 5]
tech_stack:
  - 'PHP 8.5 / Symfony 7.4 / Doctrine ORM 3.5'
  - 'ext-bcmath'
  - 'Symfony YAML Component'
  - 'Foundry v2 (PersistentProxyObjectFactory)'
files_to_modify:
  - 'config/packages/doctrine.yaml'
  - 'config/packages/doctrine_migrations.yaml'
code_patterns:
  - 'AggregateRoot with create()/reconstitute() named constructors'
  - 'DomainEventPublisher::publish($aggregate) after repo->save()'
  - 'Doctrine Entity ↔ Domain Mapper (final readonly class)'
  - 'find-or-persist save() pattern in Doctrine repositories'
  - 'BoundedContextPrefixNamingStrategy auto table naming'
test_patterns:
  - 'Unit: domain aggregate invariants + pullDomainEvents()'
  - 'Unit: handlers with createMock() for all interfaces'
  - 'Integration: KernelTestCase + Foundry v2 + DAMA auto-rollback'
  - 'self::assertSame() everywhere, never assertEquals()'
---

# Tech-Spec: System/Taxation Bounded Context — MVP (FR only)

**Created:** 2026-05-15

## Overview

### Problem Statement

The platform needs a universal fiscal engine to resolve applicable tax rates for
any product/service sale. No bounded context currently handles tax classification,
rate lookup, temporal rate history, or legal mention generation. Without this
foundation, Catalog cannot display TTC prices, and future Billing cannot snapshot
tax data on invoices.

### Solution

Implement a greenfield `System/Taxation` DDD bounded context that:
1. Classifies products/services via `TaxCategory` (stable business language)
2. Resolves `(TaxCategory + TaxRegime + FiscalContext) → TaxApplication` via a
   deterministic specificity-scoring `TaxResolver`
3. Loads fiscal regimes from YAML files (one country = one file), supporting
   temporal rate history (rate validity periods)
4. Produces `TaxApplication` VOs ready to be snapshotted on future invoice lines

### Scope

**In Scope (MVP — FR only):**
- Full domain model: `TaxRegime`, `TaxCategory`, `TaxRate`, `MentionTemplate`
  aggregates/entities + all supporting VOs
- France regime only (`regimes/fr.yaml`): standard 20%, reduced 10%,
  super-reduced 5.5% (drug.companion + food.therapeutic), particular 2.1%
  (drug.livestock), intracom/export legal mentions
- 19 veterinary TaxCategories loaded via `categories.yaml`
- `TaxResolver` with specificity-scoring algorithm (basis-point scoring per
  dimension)
- `MentionTemplateResolver` with whitelist placeholder substitution
- All Application commands/queries: LoadTaxRegime, ReloadAllRegimes,
  RegisterTaxCategory, DeactivateTaxRegime, ResolveTax, GetTaxRegime,
  ListTaxCategories, ListSupportedRegimes, GetEffectiveRateForUI
- 5 console commands: `app:taxation:load-categories`, `app:taxation:load-regime`,
  `app:taxation:reload-all`, `app:taxation:list-regimes`,
  `app:taxation:resolve-test`
- Doctrine persistence (4 entities, 3 mappers, 3 repositories)
- DB migration (`migrations/Taxation/`)
- Foundry v2 factories + `TaxonomyBootstrapStory` (FR only)
- 100% test coverage: unit + integration

**Out of Scope:**
- Regimes CH, BE, ES, UK, DE — deferred (architecture ready, no code needed)
- US sales tax (per state/county)
- External rate providers (Avalara, TaxJar)
- VAT declarations / accounting regularisation (future Accounting BC)
- Clinic::taxRegimeId and Clinic::isLiableForTax fields (dedicated story)
- Multi-locale legal mentions (1 locale per template at MVP)
- Redis/memory cache on TaxResolver (deferred for Billing batch)
- Polymorphic TaxResolver interface (deferred until non-YAML use case)

---

## Context for Development

### Codebase Patterns

**All patterns confirmed by scanning `src/System/Money/`** — use it as the
primary reference BC for Taxation.

1. **Handler write invariant order** (from `RegisterCurrencyHandler`):
   load aggregate → call domain method → `$repo->save()` → `$domainEventPublisher->publish($aggregate)`
   **Transaction**: `messenger.bus.command` has `doctrine_transaction` middleware — no manual
   `wrapInTransaction()` needed. The entire `__invoke()` is one atomic transaction.

2. **Idempotent save pattern** (from `DoctrineCurrencyRepository::save()`):
   ```php
   $entity   = $this->mapper->toEntity($currency);
   $existing = $this->em->find(CurrencyEntity::class, $entity->getCode());
   if (null === $existing) {
       $this->em->persist($entity);
   } else {
       $existing->setField(...); // update each field manually
   }
   $this->em->flush();
   ```

3. **Doctrine Entity** (from `CurrencyEntity`): `#[ORM\Entity]` + `#[ORM\Table]`
   (no name= argument — BoundedContextPrefixNamingStrategy derives it).
   No Domain classes inside entities.

4. **Mapper** (from `CurrencyMapper`): `final readonly class`, `toDomain()` calls
   `Aggregate::reconstitute(...)`, `toEntity()` calls `new *Entity()` + setters.

5. **DomainEventPublisher**: injected as concrete class (not interface), called
   once after `$repo->save()`.

6. **TaxRateCondition serialization**: stored as `condition_json` (JSON column).
   Serialization handled in mapper: `json_encode` / `json_decode` with typed
   reconstruction.

7. **TaxRate as domain entity within TaxRegime aggregate**: `TaxRate` lives in
   `Domain/Entity/TaxRate.php` — has identity (`TaxRateId`) but no lifecycle outside
   its parent `TaxRegime`. Not a Doctrine entity. Persistence via `TaxRateEntity`
   (separate Doctrine entity, `regime_id` raw string FK). The mapper reconstructs
   `TaxRate[]` from `TaxRateEntity[]` when loading `TaxRegime`.

8. **doctrine_migrations.yaml entry position**: `Taxation` goes alphabetically
   between `Shared` and `Translation` (Ta < Tr).

9. **doctrine.yaml Taxation mapping**: insert after the `Money` block in the
   `orm.mappings` section (no strict alphabetical requirement in doctrine.yaml
   per existing code).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/System/Money/Infrastructure/Persistence/Doctrine/Repository/DoctrineCurrencyRepository.php` | find-or-persist save() pattern |
| `src/System/Money/Infrastructure/Persistence/Doctrine/Mapper/CurrencyMapper.php` | Mapper final readonly class pattern |
| `src/System/Money/Infrastructure/Persistence/Doctrine/Entity/CurrencyEntity.php` | ORM\Entity + ORM\Table pattern (no name= arg) |
| `src/System/Money/Application/Command/RegisterCurrency/RegisterCurrencyHandler.php` | DomainEventPublisher usage, final readonly handler |
| `src/System/Money/Domain/ValueObject/Money.php` | Money::zero(), Money::fromMinorUnits() |
| `src/System/Money/Domain/Service/MoneyCalculator.php` | percentage(Money, string, RoundingPolicy): Money |
| `src/System/Money/Domain/Service/RoundingPolicyRegistry.php` | accounting(): AccountingRounding |
| `src/System/Money/Domain/ValueObject/RoundingPolicyId.php` | RoundingPolicyId::ACCOUNTING constant |
| `src/Shared/Domain/ValueObject/CountryCode.php` | Reuse directly — do NOT recreate |
| `src/Shared/Domain/ValueObject/CurrencyCode.php` | Reuse directly — do NOT recreate |
| `src/Shared/Domain/Identifier/AbstractUuidId.php` | ->toString() (never ->value()) |
| `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php` | Table naming algorithm |
| `config/packages/doctrine.yaml` | Add Taxation mapping block |
| `config/packages/doctrine_migrations.yaml` | Add Taxation migrations path |
| `tests/Unit/System/Money/Application/Command/RegisterCurrencyHandlerTest.php` | Unit handler test pattern |
| `tests/Integration/System/Money/Infrastructure/Registry/DoctrineCurrencyRegistryTest.php` | Integration registry test pattern |

### Technical Decisions

1. **`TaxRateCondition` serialized as JSON in DB** (`condition_json` column). The
   condition struct has no identity and no lifecycle — it's a pure configuration
   snapshot. Storing as JSON avoids a 5th join table with no business value.
   Reconstruction is done in `TaxRateMapper` with typed `json_decode`.

2. **`MentionTemplateCondition` serialized as JSON** (`condition_json`) — same
   rationale as TaxRateCondition.

3. **`TaxRate` as Domain child VO (not Doctrine entity in Domain)**: `TaxRate`
   carries no identity in the Domain sense beyond its `TaxRateId` VO. It is
   loaded with its parent `TaxRegime` and never navigated independently from
   outside the aggregate. `TaxRateEntity` is the Doctrine-side projection.

4. **`ch.yaml` wildcard category `['*']`** removed from MVP — the `ch.exempt`
   rate that matches all categories (for NOT_LIABLE clinics) is not needed at
   MVP. If needed later, the pattern `categoriesMatching: ['*']` must be
   handled in `TaxRateCondition::matches()` as a universal wildcard.

5. **Currency validation in `ResolveTaxHandler`** (not in TaxResolver): keeps
   Money concerns out of the pure fiscal domain logic. Convention: FR/BE/ES/DE
   → EUR, CH → CHF, UK → GBP. Implemented via a private `deriveExpectedCurrency`
   method in the handler.

6. **`GetEffectiveRateForUI` derives currency from regimeId** before constructing
   `Money::zero($currency)`, so it can call TaxResolver without a real netAmount.
   Returns only the `toPercentString()` of the applied rate.

7. **`TaxCategoryCode` as PK** (exception to UUIDv7 rule): it's a stable
   dictionary referenced by external BCs. String PKs are appropriate for lookup
   tables. `TaxCategoryEntity.code` is `VARCHAR(64) PRIMARY KEY`.

8. **`TaxRegimeId` as string PK** (exception to UUIDv7): same rationale — short
   human-readable codes (`FR`, `FR-DOM`) needed by the YAML loader and CLI.

9. **`MentionTemplateId` IS UUIDv7** (via `AbstractUuidId`) — MentionTemplate is
   an internal entity with no external referencing requirement, so UUID is fine.

10. **Handler unit tests**: instantiate `DomainEventPublisher` directly with a mocked
    `EventBusInterface` (not via container). Pattern confirmed from `RegisterCurrencyHandlerTest`:
    ```php
    $eventBus = $this->createMock(EventBusInterface::class);
    $publisher = new DomainEventPublisher($eventBus);
    $handler = new LoadTaxRegimeHandler($regimeRepo, $mentionRepo, $loader, $registry, $publisher, $clock);
    ```
    When aggregate has no events (reconstituted path), `eventBus->publish` expects `never()`.

11. **Test scope**: Only FR scenarios. ClinicLiabilityStatus / NOT_LIABLE / RegionCode
    are tested via unit tests on VOs (TaxRateCondition.matches) not via
    full TaxResolver integration — those paths exist for future CH support.

---

## Implementation Plan

### Tasks

Tasks are ordered by dependency (lowest-level first). Each task is self-contained.

---

#### PHASE 1 — Domain Layer

**Task 1.1 — Domain Exceptions (10 files)**
Path: `src/System/Taxation/Domain/Exception/`
Create:
- `UnknownTaxRegimeException.php` — `new UnknownTaxRegimeException(string $regimeId)`
- `UnknownTaxCategoryException.php` — `new UnknownTaxCategoryException(string $code)`
- `InvalidTaxCategoryFormatException.php` — `new InvalidTaxCategoryFormatException(string $code)`
- `NoApplicableRateException.php` — `new NoApplicableRateException(string $category, string $regimeId, \DateTimeImmutable $date)`
- `AmbiguousRateException.php` — `new AmbiguousRateException(string $category, string $regimeId, array $rateIds)` where `array $rateIds` is `list<string>`
- `InvalidTaxRateValueException.php` — `new InvalidTaxRateValueException(int $basisPoints)`
- `TaxResolutionException.php` — base exception class
- `CurrencyMismatchForRegimeException.php` — `new CurrencyMismatchForRegimeException(string $regimeId, string $expected, string $actual)`
- `UnknownCategoryPatternException.php` — `new UnknownCategoryPatternException(string $regimeId, string $pattern)`
- `EmptyTaxRegimeCannotBeActivatedException.php` — `new EmptyTaxRegimeCannotBeActivatedException(string $regimeId)`
- `OverlappingValidityPeriodsException.php` — `new OverlappingValidityPeriodsException(string $regimeId, string $rateId1, string $rateId2)` — thrown by `YamlTaxRegimeLoader` when two rates have overlapping validity periods AND identical `TaxRateCondition`; detected at load time, not at resolution

All exceptions: `final class`, extend `\DomainException`, constructor-only.

---

**Task 1.2 — Enums (3 files)**
Path: `src/System/Taxation/Domain/ValueObject/`
Create:
- `CustomerTaxStatus.php` — `enum CustomerTaxStatus: string { case B2C = 'b2c'; case B2B = 'b2b'; case INTRACOM = 'intracom'; case EXPORT = 'export'; }`
- `AnimalUsage.php` — `enum AnimalUsage: string { case COMPANION = 'companion'; case LIVESTOCK = 'livestock'; case EQUINE = 'equine'; }`
- `ClinicLiabilityStatus.php` — `enum ClinicLiabilityStatus: string { case LIABLE = 'liable'; case NOT_LIABLE = 'not_liable'; }`

All backed enums. No `default` in `match` expressions (PHPStan exhaustiveness).

---

**Task 1.3 — Primitive VOs (7 files)**
Path: `src/System/Taxation/Domain/ValueObject/`
Create:

`TaxCategoryCode.php`:
```php
final class TaxCategoryCode {
    private function __construct(private readonly string $value) {}
    public static function fromString(string $code): self
    // regex: ^[a-z]+(\.[a-z_]+)+$
    // throw InvalidTaxCategoryFormatException if invalid
    public function toString(): string
    public function equals(self $other): bool
}
```

`TaxRegimeId.php`:
```php
final class TaxRegimeId {
    private function __construct(private readonly string $value) {}
    public static function fromString(string $id): self
    // regex: ^[A-Z]{2}(-[A-Z0-9]{1,8})?$
    // throw \InvalidArgumentException if invalid
    public function toString(): string
    public function equals(self $other): bool
}
```

`TaxRateId.php`:
```php
final class TaxRateId {
    private function __construct(private readonly string $value) {}
    public static function fromString(string $id): self
    // regex: ^[a-z]{2,8}\.[a-z_]+(\.[a-z0-9]+)*$
    public function toString(): string
    public function equals(self $other): bool
}
```

`TaxRateValue.php`:
```php
final class TaxRateValue {
    private function __construct(private readonly int $basisPoints) {}
    public static function ofBasisPoints(int $bp): self
    // throw InvalidTaxRateValueException if $bp < 0 || $bp > 10000
    public static function ofPercentString(string $percent): self
    // Validates: regex ^\d+(\.\d{1,2})?$ — rejects "-5", "5.555", "100.01", ""
    // throw InvalidTaxRateValueException if regex fails
    // Conversion: (int) bcmul($percent, '100', 0) — e.g. "20.5" → 2050, "5.5" → 550, "2.1" → 210
    // Then range-check the result via ofBasisPoints()
    public function toPercentString(): string  // "20.00", "5.50", "0.00"
    public function toBcmathFactor(): string   // "0.2000", "0.0550"
    public function basisPoints(): int
    public function equals(self $other): bool
}
```

`RegionCode.php`:
```php
final class RegionCode {
    private function __construct(private readonly string $value) {}
    public static function fromString(string $code): self
    // regex: ^[A-Z0-9-]{1,16}$; throw \InvalidArgumentException if invalid
    public function toString(): string
    public function equals(self $other): bool
}
```

`MentionTemplateId.php`: extends `AbstractUuidId` — no extra methods needed.
- Construction: `new MentionTemplateId($uuidGeneratorInterface->generate())`
- `AbstractUuidId` has `public function __construct(protected string $value)` — no factory method needed
- UUID string provided by `UuidGeneratorInterface::generate()` (returns string, injected in YamlTaxRegimeLoader)

`ValidityPeriod.php`:
```php
final class ValidityPeriod {
    private function __construct(
        private readonly \DateTimeImmutable $validFrom,
        private readonly ?\DateTimeImmutable $validTo,
    ) {}
    public static function between(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    // throw \InvalidArgumentException if $from > $to
    public static function openEndedFrom(\DateTimeImmutable $from): self
    public function contains(\DateTimeImmutable $date): bool
    // open-ended: $date >= $validFrom
    // closed: $date >= $validFrom && $date <= $validTo
    public function validFrom(): \DateTimeImmutable
    public function validTo(): ?\DateTimeImmutable
}
```

---

**Task 1.4 — TaxRateCondition VO**
Path: `src/System/Taxation/Domain/ValueObject/TaxRateCondition.php`

```php
final class TaxRateCondition {
    // $categoriesMatching: string[] (glob patterns, e.g. "veterinary.act.*")
    // $regionsMatching: RegionCode[]
    // $customerStatusesMatching: CustomerTaxStatus[]
    // $animalUsagesMatching: AnimalUsage[]
    // $clinicLiability: ClinicLiabilityStatus|null

    public static function of(
        array $categoriesMatching,
        array $regionsMatching,
        array $customerStatusesMatching,
        array $animalUsagesMatching,
        ?ClinicLiabilityStatus $clinicLiability,
    ): self

    public function matches(TaxCategoryCode $category, FiscalContext $context): bool
    // - categories: empty = wildcard; otherwise fnmatch() each pattern vs category->toString()
    // - regions: empty = wildcard; otherwise check $context->region() in list
    // - customerStatuses: empty = wildcard; otherwise check $context->customerTaxStatus() in list
    // - animalUsages: empty = wildcard; otherwise check $context->animalUsage() in list
    // - clinicLiability: null = wildcard; otherwise check $context->clinicLiability() === $this->clinicLiability
    // ALL conditions must match (AND logic)

    public function specificityScore(TaxCategoryCode $cat, FiscalContext $ctx): int
    // Scoring (cumulative):
    // +100 if exact match on category (fnmatch() with no wildcard)
    // +10 per non-wildcard segment if glob wildcard match
    //     e.g. "veterinary.drug.*" matching "veterinary.drug.companion" → +20
    // +5 if $regionsMatching non-empty and matched
    // +4 if $customerStatusesMatching non-empty and matched
    // +3 if $animalUsagesMatching non-empty and matched
    // +2 if $clinicLiability non-null and matched

    // Explicit getters (all required by TaxRateMapper for JSON serialization):
    public function categoriesMatching(): array    // string[]
    public function regionsMatching(): array       // RegionCode[]
    public function customerStatusesMatching(): array  // CustomerTaxStatus[]
    public function animalUsagesMatching(): array  // AnimalUsage[]
    public function clinicLiability(): ?ClinicLiabilityStatus

    public function equals(self $other): bool
    // Required for overlap detection in YamlTaxRegimeLoader.
    // Compares all 5 fields structurally:
    // - categoriesMatching: same elements same order (sorted before comparison)
    // - regionsMatching: compare toString() of each RegionCode, sorted
    // - customerStatusesMatching: compare ->value of each enum, sorted
    // - animalUsagesMatching: compare ->value of each enum, sorted
    // - clinicLiability: both null OR both same value
}
```

Note: `fnmatch()` is available in PHP and handles `*` glob patterns natively.
Platform note: `fnmatch()` is POSIX-only and unavailable on Windows without emulation.
The project runs exclusively on Linux/Docker — this is not an issue at MVP, but any
future Windows CI/dev setup would require a fallback (e.g. `Symfony\Component\Finder\Glob`).

---

**Task 1.5 — FiscalContext VO**
Path: `src/System/Taxation/Domain/ValueObject/FiscalContext.php`

```php
final class FiscalContext {
    private function __construct(
        private readonly CountryCode $country,
        private readonly ?RegionCode $region,
        private readonly ?CustomerTaxStatus $customerTaxStatus,
        private readonly ?AnimalUsage $animalUsage,
        private readonly ClinicLiabilityStatus $clinicLiability,
        private readonly \DateTimeImmutable $saleDate,
    ) {}

    public static function of(
        CountryCode $country,
        \DateTimeImmutable $saleDate,
        ClinicLiabilityStatus $clinicLiability = ClinicLiabilityStatus::LIABLE,
        ?RegionCode $region = null,
        ?CustomerTaxStatus $customerTaxStatus = null,
        ?AnimalUsage $animalUsage = null,
    ): self

    public static function minimal(CountryCode $country, \DateTimeImmutable $saleDate): self
    // equivalent to of($country, $saleDate) with LIABLE + all null

    public function country(): CountryCode
    public function region(): ?RegionCode
    public function customerTaxStatus(): ?CustomerTaxStatus
    public function animalUsage(): ?AnimalUsage
    public function clinicLiability(): ClinicLiabilityStatus
    public function saleDate(): \DateTimeImmutable
}
```

Uses `CountryCode` from `App\Shared\Domain\ValueObject\CountryCode`.

---

**Task 1.6 — Domain Entities and Complex VOs (TaxRate, TaxableItem, AppliedTaxRate, TaxApplication)**

`TaxRate.php` (`Domain/Entity/TaxRate.php` — domain entity within TaxRegime aggregate; has identity via TaxRateId, no independent lifecycle; NOT a Doctrine entity):
```php
final class TaxRate {
    private function __construct(
        private readonly TaxRateId $id,
        private readonly TaxRateValue $value,
        private readonly ValidityPeriod $validityPeriod,
        private readonly TaxRateCondition $appliesTo,
    ) {}
    public static function of(TaxRateId, TaxRateValue, ValidityPeriod, TaxRateCondition): self
    public function id(): TaxRateId
    public function value(): TaxRateValue
    public function validityPeriod(): ValidityPeriod
    public function appliesTo(): TaxRateCondition
    public function isValidOn(\DateTimeImmutable $date): bool  // delegates to validityPeriod->contains()
    public function matchesContext(TaxCategoryCode $cat, FiscalContext $ctx): bool  // delegates to appliesTo->matches()
}
```

`TaxableItem.php`:
```php
final class TaxableItem {
    private function __construct(
        private readonly Money $netAmount,
        private readonly TaxCategoryCode $category,
    ) {}
    public static function of(Money $netAmount, TaxCategoryCode $category): self
    public function netAmount(): Money
    public function category(): TaxCategoryCode
}
```

`AppliedTaxRate.php`:
```php
final class AppliedTaxRate {
    private function __construct(
        private readonly TaxRateId $rateId,
        private readonly TaxRegimeId $regimeId,
        private readonly TaxRateValue $value,
        private readonly \DateTimeImmutable $effectiveFrom,
        private readonly string $source,
    ) {}
    public static function of(TaxRateId, TaxRegimeId, TaxRateValue, \DateTimeImmutable $effectiveFrom, string $source): self
    public function rateId(): TaxRateId
    public function regimeId(): TaxRegimeId
    public function value(): TaxRateValue
    public function effectiveFrom(): \DateTimeImmutable
    public function source(): string
}
```

`TaxApplication.php`:
```php
final class TaxApplication {
    private function __construct(
        private readonly TaxRegimeId $taxRegimeId,
        private readonly TaxCategoryCode $taxCategoryCode,
        private readonly AppliedTaxRate $appliedRate,
        private readonly Money $netAmount,
        private readonly Money $taxAmount,
        private readonly Money $grossAmount,
        private readonly RoundingPolicyId $roundingPolicyId,
        private readonly LegalMentions $legalMentions,
        private readonly FiscalContext $fiscalContext,
        private readonly \DateTimeImmutable $resolvedAt,
    ) {}
    public static function of(...): self
    // validates: netAmount->currency()->equals(taxAmount->currency()) AND taxAmount->currency()->equals(grossAmount->currency())
    // validates: grossAmount == netAmount + taxAmount
    //   → integer arithmetic on minor units: $netAmount->minorUnits() + $taxAmount->minorUnits() === $grossAmount->minorUnits()
    //   → NOT bcmath (Money stores int $minorUnits — simple int addition is correct and sufficient)
    //   → throw \LogicException if invariant violated (internal consistency check)
    // All getters
}
```

---

**Task 1.7 — LegalMention VOs (3 files)**

`LegalMention.php`:
```php
final class LegalMention {
    public function __construct(
        public readonly string $code,
        public readonly string $text,
        public readonly string $locale,
    ) {}
}
```

`LegalMentions.php`:
```php
final class LegalMentions {
    /** @param list<LegalMention> $items */
    private function __construct(private readonly array $items) {}
    public static function empty(): self
    public static function fromList(LegalMention ...$items): self
    public function isEmpty(): bool
    public function count(): int
    public function byCode(string $code): ?LegalMention
    /** @return list<LegalMention> */
    public function toArray(): array
}
```

`MentionTemplateCondition.php`:
```php
final class MentionTemplateCondition {
    // $regionsMatching: RegionCode[]
    // $customerStatusesMatching: CustomerTaxStatus[]
    // $animalUsagesMatching: AnimalUsage[]
    // $clinicLiability: ClinicLiabilityStatus|null
    public static function of(...): self
    public function matches(FiscalContext $context): bool
    // same AND logic as TaxRateCondition (without category dimension)
}
```

---

**Task 1.8 — MentionTemplate Domain Entity**
Path: `src/System/Taxation/Domain/Entity/MentionTemplate.php`

```php
final class MentionTemplate {
    private MentionTemplateId $id;
    private TaxRegimeId $regimeId;
    private string $code;
    private MentionTemplateCondition $condition;
    private string $template;  // may contain {{rate}}, {{regime}}, {{country}}
    private string $locale;
    private bool $active;

    // No Doctrine attributes — this is a Domain entity
    public static function create(
        MentionTemplateId $id,
        TaxRegimeId $regimeId,
        string $code,
        MentionTemplateCondition $condition,
        string $template,
        string $locale,
        ClockInterface $clock,       // required for createdAt
    ): self

    public static function reconstitute(
        MentionTemplateId $id,
        TaxRegimeId $regimeId,
        string $code,
        MentionTemplateCondition $condition,
        string $template,
        string $locale,
        bool $active,
        \DateTimeImmutable $createdAt,
    ): self

    // Getters: id(), regimeId(), code(), condition(), template(), locale(), isActive(), createdAt()
    // Note: YamlTaxRegimeLoader must inject ClockInterface to call MentionTemplate::create()
}
```

---

**Task 1.9 — TaxCategory Aggregate**
Path: `src/System/Taxation/Domain/TaxCategory.php`

```php
final class TaxCategory extends AggregateRoot {
    private TaxCategoryCode $code;
    private string $displayName;
    private ?string $description;
    private bool $active;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public static function create(
        TaxCategoryCode $code,
        string $displayName,
        ?string $description,
        ClockInterface $clock,
    ): self
    // Records TaxCategoryRegistered event

    public static function reconstitute(
        TaxCategoryCode $code,
        string $displayName,
        ?string $description,
        bool $active,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self
    // No event recorded

    public function activate(): void   // idempotent
    public function deactivate(): void // idempotent
    public function updateDisplay(string $displayName, ?string $description, ClockInterface $clock): void

    // Getters: code(), displayName(), description(), isActive(), createdAt(), updatedAt()
}
```

---

**Task 1.10 — TaxRegime Aggregate**
Path: `src/System/Taxation/Domain/TaxRegime.php`

```php
final class TaxRegime extends AggregateRoot {
    private TaxRegimeId $id;
    private string $name;
    private CountryCode $country;
    /** @var list<TaxRate> */  // PHPStan level:max requires this annotation — never plain array
    private array $rates = [];
    private bool $active;
    private \DateTimeImmutable $loadedAt;
    private \DateTimeImmutable $updatedAt;

    public static function create(
        TaxRegimeId $id,
        string $name,
        CountryCode $country,
        ClockInterface $clock,
        bool $active = false,
    ): self
    // Records TaxRegimeLoaded event

    public static function reconstitute(
        TaxRegimeId $id,
        string $name,
        CountryCode $country,
        array $rates,  // list<TaxRate>
        bool $active,
        \DateTimeImmutable $loadedAt,
        \DateTimeImmutable $updatedAt,
    ): self
    // No event recorded

    public function addRate(TaxRate $rate, ClockInterface $clock): void
    // Records TaxRateAdded; updates updatedAt

    public function replaceAllRates(array $rates, ClockInterface $clock): void
    // Replaces internal $rates; updates updatedAt (no individual events)

    public function activate(ClockInterface $clock): void
    // throw EmptyTaxRegimeCannotBeActivatedException if $this->rates === []
    // Records TaxRegimeActivated if not already active; updates updatedAt

    public function deactivate(ClockInterface $clock): void
    // Records TaxRegimeDeactivated if active; updates updatedAt

    /** @return list<TaxRate> */
    public function findCandidateRates(
        TaxCategoryCode $category,
        FiscalContext $context,
    ): array
    // Uses $context->saleDate() internally for validity check — no separate $date param.
    // Filters $this->rates where:
    //   $rate->isValidOn($context->saleDate()) AND $rate->matchesContext($category, $context)
    // Returns ALL matches; NO scoring; NO throw

    // Getters: id(), name(), country(), rates(), isActive(), loadedAt(), updatedAt()
}
```

---

**Task 1.11 — Domain Events (6 files)**
Path: `src/System/Taxation/Domain/Event/`

Each event: `final readonly class`, extends `AbstractDomainEvent`, carries **scalars only** (no VOs, no aggregates).

**Required structure** (confirmed from `CurrencyRegistered`):
```php
final readonly class TaxRegimeLoaded extends AbstractDomainEvent {
    protected const string BOUNDED_CONTEXT = 'taxation';  // MUST be 'taxation'
    protected const int    VERSION         = 1;

    public function __construct(
        private string $regimeId,
        private string $name,
        private string $countryCode,
    ) {}

    public function aggregateId(): string { return $this->regimeId; }

    public function payload(): array {
        return ['regimeId' => $this->regimeId, 'name' => $this->name, 'countryCode' => $this->countryCode];
    }
}
```

Apply same structure to all 6 events:
- `TaxRegimeLoaded.php` — `string $regimeId, string $name, string $countryCode`; `aggregateId() = $regimeId`
- `TaxRegimeActivated.php` — `string $regimeId`; `aggregateId() = $regimeId`
- `TaxRegimeDeactivated.php` — `string $regimeId`; `aggregateId() = $regimeId`
- `TaxRateAdded.php` — `string $regimeId, string $rateId, int $valueBasisPoints`; `aggregateId() = $regimeId`
- `TaxRateUpdated.php` — `string $regimeId, string $rateId, int $valueBasisPoints`; `aggregateId() = $regimeId`
- `TaxCategoryRegistered.php` — `string $code, string $displayName`; `aggregateId() = $code`

---

**Task 1.12 — Repository Interfaces (3 files)**
Path: `src/System/Taxation/Domain/Repository/`

```php
interface TaxRegimeRepositoryInterface {
    public function save(TaxRegime $regime): void;
    public function findById(TaxRegimeId $id): ?TaxRegime;
    /** @return list<TaxRegime> */
    public function findAllActive(): array;  // PHPStan: @return list<TaxRegime> required on implementation too
}

interface TaxCategoryRepositoryInterface {
    public function save(TaxCategory $category): void;
    public function findByCode(TaxCategoryCode $code): ?TaxCategory;
    /** @return list<TaxCategory> */
    public function findAllActive(): array;  // Required by DoctrineTaxCategoryRegistry::listActive()
}

interface MentionTemplateRepositoryInterface {
    public function save(MentionTemplate $template): void;
    public function deleteByRegime(TaxRegimeId $regimeId): void;  // Used by LoadTaxRegimeHandler for idempotent reload
    /** @return list<MentionTemplate> */
    public function findActiveByRegime(TaxRegimeId $regimeId): array;
}
```

---

**Task 1.13 — TaxCategoryRegistryInterface**
Path: `src/System/Taxation/Domain/Service/TaxCategoryRegistryInterface.php`

```php
interface TaxCategoryRegistryInterface {
    public function get(TaxCategoryCode $code): TaxCategory;  // throw UnknownTaxCategoryException
    /** @return list<TaxCategory> */
    public function listActive(): array;
    public function has(TaxCategoryCode $code): bool;
}
```

---

**Task 1.14 — MentionTemplateResolver Service**
Path: `src/System/Taxation/Domain/Service/MentionTemplateResolver.php`

```php
final readonly class MentionTemplateResolver {
    public function __construct(private MentionTemplateRepositoryInterface $repo) {}

    public function findApplicable(
        TaxRegimeId $regimeId,
        FiscalContext $context,
        AppliedTaxRate $rate,
    ): LegalMentions
    // 1. Load all active templates for regimeId via $this->repo->findActiveByRegime()
    // 2. Filter: $template->condition()->matches($context)
    // 3. For each matching template, apply placeholders:
    //    $text = str_replace('{{rate}}', $rate->value()->toPercentString(), $template->template())
    //    $text = str_replace('{{regime}}', $regimeId->toString(), $text)
    //    $text = str_replace('{{country}}', $context->country()->toString(), $text)
    //    (unknown {{...}} left as-is)
    // 4. Return LegalMentions::fromList(...constructed LegalMentions)
}
```

---

**Task 1.15 — TaxResolver Service**
Path: `src/System/Taxation/Domain/Service/TaxResolver.php`

```php
final readonly class TaxResolver {
    public function __construct(
        private TaxRegimeRepositoryInterface $regimeRepo,
        private TaxCategoryRegistryInterface $categoryRegistry,
        private MoneyCalculator $calculator,
        private RoundingPolicyRegistry $roundingRegistry,
        private MentionTemplateResolver $mentionResolver,
        private ClockInterface $clock,
    ) {}

    public function resolve(
        TaxableItem $item,
        TaxRegimeId $regimeId,
        FiscalContext $context,
    ): TaxApplication
    // ALGORITHM:
    // 1. Load regime via $this->regimeRepo->findById($regimeId)
    //    → throw UnknownTaxRegimeException if null OR !$regime->isActive()
    // 2. Verify category via $this->categoryRegistry->has($item->category())
    //    → throw UnknownTaxCategoryException if false
    // 3. $candidates = $regime->findCandidateRates($item->category(), $context)
    // 4. count($candidates) === 0 → throw NoApplicableRateException
    // 5. count($candidates) > 1:
    //    a. $scored = array_map(fn($r) => [$r, $r->appliesTo()->specificityScore($item->category(), $context)], $candidates)
    //    b. sort by score desc
    //    c. if top 2 have equal score → throw AmbiguousRateException with rateIds
    //    d. else: $winner = $scored[0][0]
    //    (if count = 1: $winner = $candidates[0])
    // 6. $roundingPolicy = $this->roundingRegistry->accounting()
    // 7. $taxAmount = $this->calculator->percentage($item->netAmount(), $winner->value()->toPercentString(), $roundingPolicy)
    // 8. $grossAmount = $this->calculator->add($item->netAmount(), $taxAmount)
    // 9. $appliedRate = AppliedTaxRate::of($winner->id(), $regimeId, $winner->value(), $winner->validityPeriod()->validFrom(), 'yaml.'.$winner->id()->toString())
    // 10. $mentions = $this->mentionResolver->findApplicable($regimeId, $context, $appliedRate)
    // 11. return TaxApplication::of($regimeId, $item->category(), $appliedRate, $item->netAmount(), $taxAmount, $grossAmount, RoundingPolicyId::ACCOUNTING, $mentions, $context, $this->clock->now())
}
```

---

#### PHASE 2 — Application Layer

**Task 2.1 — Application Port**
Path: `src/System/Taxation/Application/Port/TaxRegimeLoaderInterface.php`

```php
interface TaxRegimeLoaderInterface {
    public function load(TaxRegimeId $regimeId): TaxRegimeBlueprint;
}
```

`TaxRegimeBlueprint.php` (same directory — a DTO):
```php
final readonly class TaxRegimeBlueprint {
    /**
     * @param list<TaxRate>           $rates
     * @param list<MentionTemplate>   $mentionTemplates
     */
    public function __construct(
        public readonly TaxRegimeId $regimeId,
        public readonly string $name,
        public readonly CountryCode $country,
        public readonly bool $active,
        public readonly array $rates,
        public readonly array $mentionTemplates,
    ) {}
}
```

---

**Task 2.2 — Commands (8 files)**

`LoadTaxRegime.php` + `LoadTaxRegimeHandler.php`:
```php
final readonly class LoadTaxRegime implements CommandInterface {
    public function __construct(public readonly TaxRegimeId $regimeId) {}
}

final readonly class LoadTaxRegimeHandler {
    #[AsMessageHandler]
    public function __invoke(LoadTaxRegime $command): void
    // NO explicit wrapInTransaction needed: messenger.bus.command has doctrine_transaction middleware
    // that automatically wraps the entire __invoke() in a DB transaction.
    // 1. $blueprint = $this->loader->load($command->regimeId)
    // 2. $existing = $this->regimeRepo->findById($command->regimeId)
    // 3. if null: $regime = TaxRegime::create(...)
    //    else: $existing->replaceAllRates($blueprint->rates, $this->clock)
    //    (idempotent: replaceAllRates clears in-memory rates + sets new ones; repo save() handles DB)
    // 4. if $blueprint->active && !$regime->isActive(): $regime->activate($this->clock)
    // 5. $this->regimeRepo->save($regime)  ← DQL DELETE old rates + INSERT new (see repo Task 3.3)
    // 6. $this->mentionRepo->deleteByRegime($command->regimeId)  ← DELETE all existing templates
    // 7. For each $blueprint->mentionTemplates: $this->mentionRepo->save($template)  ← INSERT fresh
    //    (UUIDs are generated fresh at parse time — delete+insert is the correct idempotence strategy)
    // 8. $this->domainEventPublisher->publish($regime)  ← last; if regime has no events (reconstituted), noop
}
```

`ReloadAllRegimes.php` + `ReloadAllRegimesHandler.php`:
```php
// Handler uses RegimeFileLocator to get all *.yaml files in regimes/ dir
// Dispatches LoadTaxRegime command for each via CommandBusInterface
```

`RegisterTaxCategory.php` + `RegisterTaxCategoryHandler.php`:
```php
final readonly class RegisterTaxCategory implements CommandInterface {
    public function __construct(
        public readonly TaxCategoryCode $code,
        public readonly string $displayName,
        public readonly ?string $description,
    ) {}
}
// Handler: findByCode → if null: create → else: updateDisplay. save. publish.
```

`DeactivateTaxRegime.php` + `DeactivateTaxRegimeHandler.php`:
```php
// Handler: findById (throw if null), deactivate($clock), save, publish
```

---

**Task 2.3 — Queries (10 files)**

`ResolveTax.php` + `ResolveTaxHandler.php`:
```php
final readonly class ResolveTax implements QueryInterface {
    public function __construct(
        public readonly Money $netAmount,
        public readonly TaxCategoryCode $categoryCode,
        public readonly TaxRegimeId $regimeId,
        public readonly FiscalContext $fiscalContext,
    ) {}
}
// Handler __invoke(ResolveTax $query): TaxApplication
// 1. Validate currency vs regime: deriveExpectedCurrency($regimeId)
//    Convention: FR/BE/ES/DE → EUR, CH → CHF, UK/GB → GBP
//    if $command->netAmount->currency()->toString() !== $expected: throw CurrencyMismatchForRegimeException
// 2. TaxableItem::of($command->netAmount, $command->categoryCode)
// 3. return $this->taxResolver->resolve(...)
```

`GetTaxRegime/GetTaxRegime.php` + `GetTaxRegimeHandler.php`:
```php
// __invoke(GetTaxRegime $query): TaxRegime
// Returns TaxRegime domain aggregate directly (stable reference, no dedicated read DTO needed at MVP)
```

`ListTaxCategories/ListTaxCategories.php` + `ListTaxCategoriesHandler.php`:
```php
// __invoke(ListTaxCategories $query): array  // @return list<TaxCategory>
// Optional $prefix: string|null for filtering
// Returns TaxCategoryRepositoryInterface::findAllActive() (filtered by prefix if provided)
```

`ListSupportedRegimes/ListSupportedRegimes.php` + `ListSupportedRegimesHandler.php`:
```php
// __invoke(ListSupportedRegimes $query): array  // @return list<TaxRegime>
// Returns TaxRegimeRepositoryInterface::findAllActive()
```

`GetEffectiveRateForUI/GetEffectiveRateForUI.php` + `GetEffectiveRateForUIHandler.php`:
```php
final readonly class GetEffectiveRateForUI implements QueryInterface {
    public function __construct(
        public readonly TaxCategoryCode $categoryCode,
        public readonly TaxRegimeId $regimeId,
        public readonly FiscalContext $fiscalContext,
    ) {}
}
// Create alongside: GetEffectiveRateForUI/EffectiveRateResult.php
// final readonly class EffectiveRateResult {
//     public function __construct(
//         public readonly string $ratePercent,   // e.g. "5.50"
//         public readonly string $regimeId,      // e.g. "FR"
//     ) {}
// }
//
// Handler __invoke(): EffectiveRateResult
// 1. $currency = CurrencyCode::fromString($this->deriveExpectedCurrency($command->regimeId))
// 2. $netAmount = Money::zero($currency)
// 3. $item = TaxableItem::of($netAmount, $command->categoryCode)
// 4. $application = $this->taxResolver->resolve($item, $command->regimeId, $command->fiscalContext)
// 5. return new EffectiveRateResult(
//        ratePercent: $application->appliedRate()->value()->toPercentString(),
//        regimeId: $command->regimeId->toString(),
//    )
```

---

#### PHASE 3 — Infrastructure Layer

**Task 3.1 — Doctrine Entities (4 files)**
Path: `src/System/Taxation/Infrastructure/Persistence/Doctrine/Entity/`

`TaxRegimeEntity.php`:
```php
#[ORM\Entity]
#[ORM\Table]
class TaxRegimeEntity {
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 16)]
    private string $id;  // 'FR', 'CH'

    #[ORM\Column(type: 'string', length: 128)]
    private string $name;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $active;

    #[ORM\Column(name: 'loaded_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $loadedAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Getters + setters
}
```

`TaxRateEntity.php`:
```php
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_taxation_rates_regime_validity', columns: ['regime_id', 'valid_from', 'valid_to'])]
class TaxRateEntity {
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $id;  // 'fr.standard'

    #[ORM\Column(name: 'regime_id', type: 'string', length: 16)]
    private string $regimeId;

    #[ORM\Column(name: 'value_bp', type: 'integer')]
    private int $valueBp;

    #[ORM\Column(name: 'valid_from', type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(name: 'valid_to', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo;

    #[ORM\Column(name: 'condition_json', type: 'json')]
    private array $conditionJson;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Getters + setters
}
```
**Decision (definitive):** NO `#[ORM\ManyToOne]` on `TaxRateEntity`. Use raw `string $regimeId`
column only. Rationale: `DoctrineTaxRegimeRepository::save()` uses DQL DELETE to batch-remove
all rates before re-inserting — this pattern bypasses Doctrine's identity map and cascade,
making `ManyToOne`/`orphanRemoval` irrelevant and misleading. The FK constraint
(`regime_id → taxation__tax_regimes(id) ON DELETE CASCADE`) is enforced in migration SQL only.

`TaxCategoryEntity.php`:
```php
#[ORM\Entity]
#[ORM\Table]
class TaxCategoryEntity {
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(name: 'display_name', type: 'string', length: 128)]
    private string $displayName;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Getters + setters
}
```

`MentionTemplateEntity.php`:
```php
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_taxation_mentions_regime', columns: ['regime_id'])]
#[ORM\UniqueConstraint(name: 'uniq_taxation_mention_regime_code_locale', columns: ['regime_id', 'code', 'locale'])]
class MentionTemplateEntity {
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;  // Symfony\Component\Uid\Uuid

    #[ORM\Column(name: 'regime_id', type: 'string', length: 16)]
    private string $regimeId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(name: 'condition_json', type: 'json')]
    private array $conditionJson;

    #[ORM\Column(type: 'text')]
    private string $template;

    #[ORM\Column(type: 'string', length: 8)]
    private string $locale;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Getters + setters
}
```

---

**Task 3.2 — Mappers (4 files)**
Path: `src/System/Taxation/Infrastructure/Persistence/Doctrine/Mapper/`

`TaxRateMapper.php` (`final readonly class`) — **do this first, used by TaxRegimeMapper**:
- `toDomain(TaxRateEntity $entity): TaxRate`
  - Deserializes `conditionJson` → `TaxRateCondition` (reconstruct enums via `::from()`, RegionCode array, null clinicLiability)
  - `ValidityPeriod`: `validTo === null` → `openEndedFrom()`, else → `between()`
- `toEntity(TaxRate $rate, string $regimeId): TaxRateEntity` — regimeId passed explicitly (no back-reference in domain)

`TaxRegimeMapper.php` (`final readonly class`):
- `toDomain(TaxRegimeEntity $entity, TaxRateEntity[] $rateEntities): TaxRegime`
  - Calls `TaxRegime::reconstitute(...)` with `array_map(fn($e) => $this->rateMapper->toDomain($e), $rateEntities)`
  - PHPStan: `@param list<TaxRateEntity> $rateEntities`
- `toEntity(TaxRegime $regime): TaxRegimeEntity`

`TaxCategoryMapper.php` (`final readonly class`):
- `toDomain(TaxCategoryEntity): TaxCategory` → `TaxCategory::reconstitute(...)`
- `toEntity(TaxCategory): TaxCategoryEntity`

`MentionTemplateMapper.php` (`final readonly class`):
- `toDomain(MentionTemplateEntity): MentionTemplate` → `MentionTemplate::reconstitute(...)`
  - Deserializes `conditionJson` back to `MentionTemplateCondition` (same enum reconstruction pattern as TaxRateMapper)
- `toEntity(MentionTemplate): MentionTemplateEntity`

**TaxRateCondition JSON serialization format:**
```json
{
  "categories": ["veterinary.act.*", "veterinary.consumable"],
  "regions": [],
  "customer_statuses": ["intracom"],
  "animal_usages": ["companion"],
  "clinic_liability": null
}
```

---

**Task 3.3 — Doctrine Repositories (3 files)**
Path: `src/System/Taxation/Infrastructure/Persistence/Doctrine/Repository/`

`DoctrineTaxRegimeRepository.php`:
```php
final readonly class DoctrineTaxRegimeRepository implements TaxRegimeRepositoryInterface {
    // save():
    //   1. find-or-persist TaxRegimeEntity (standard idempotent pattern)
    //   2. For rates — DQL DELETE (not orphanRemoval):
    //      $em->createQueryBuilder()
    //         ->delete(TaxRateEntity::class, 'r')
    //         ->where('r.regimeId = :id')
    //         ->setParameter('id', $regime->id()->toString())
    //         ->getQuery()->execute();
    //      Reason: orphanRemoval requires loading all existing TaxRateEntities into the identity map
    //      first (N SELECT + N DELETE). DQL DELETE = 1 batch DELETE regardless of rate count.
    //      For FR with 4 rates: negligible; for future regimes with 50+ historical rates: critical.
    //   3. For each TaxRate in $regime->rates(): $em->persist($rateMapper->toEntity($rate, $regime->id()->toString()))
    //   4. $em->flush()
    //
    // findById(): find TaxRegimeEntity + findBy(['regimeId' => id]) TaxRateEntities → mapper->toDomain()
    // findAllActive(): findBy(['active' => true]) + rates for each
    //
    // PHPStan: @return list<TaxRegime> on findAllActive() implementation
}
```

`DoctrineTaxCategoryRepository.php`:
```php
final readonly class DoctrineTaxCategoryRepository implements TaxCategoryRepositoryInterface {
    // Standard find-or-persist save()
    // findByCode(): find by code (PK)
}
```

`DoctrineMentionTemplateRepository.php`:
```php
final readonly class DoctrineMentionTemplateRepository implements MentionTemplateRepositoryInterface {
    // save(): find-or-persist by id (UUID) — standard pattern
    // deleteByRegime(): DQL DELETE WHERE regime_id = :id (same rationale as TaxRateEntity — batch, no identity map load)
    // findActiveByRegime(): findBy(['regimeId' => id, 'active' => true])
}
```

---

**Task 3.4 — DoctrineTaxCategoryRegistry**
Path: `src/System/Taxation/Infrastructure/Registry/DoctrineTaxCategoryRegistry.php`

```php
final readonly class DoctrineTaxCategoryRegistry implements TaxCategoryRegistryInterface {
    public function __construct(private TaxCategoryRepositoryInterface $repo) {}
    public function get(TaxCategoryCode $code): TaxCategory
    // findByCode($code) → throw UnknownTaxCategoryException if null
    public function listActive(): array  // findAll active from repo
    public function has(TaxCategoryCode $code): bool
}
```

---

**Task 3.5 — YAML Loader Infrastructure (2 files)**
Path: `src/System/Taxation/Infrastructure/RegimeLoader/`

`YamlTaxRegimeLoader.php` implements `TaxRegimeLoaderInterface`:
```
1. Locate file via RegimeFileLocator
2. Parse YAML (symfony/yaml)
3. For each rate in rates[]:
   a. Build TaxRateCondition from applies_to.conditions + applies_to.categories
   b. Validate each category pattern via TaxCategoryRegistryInterface:
      - if pattern contains '*': check that at least one active category matches fnmatch()
      - if exact code: check has($code)
      - if no match: throw UnknownCategoryPatternException($regimeId, $pattern)
4. Overlap detection — after building all TaxRate objects:
   For each pair (rateA, rateB) where rateA.appliesTo() equals rateB.appliesTo() (same condition):
     if rateA.validityPeriod() overlaps rateB.validityPeriod():
       throw OverlappingValidityPeriodsException($regimeId, $rateA->id()->toString(), $rateB->id()->toString())
   Two ValidityPeriods overlap when: fromA <= toB && fromB <= toA (or either toX is null = open-ended)
   This catches config errors at deployment time, not at production resolution time.
5. Build list<TaxRate> and list<MentionTemplate>
6. Return TaxRegimeBlueprint
```

`RegimeFileLocator.php`:
```php
final readonly class RegimeFileLocator {
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {}
    // resourcesDir resolved as: $this->projectDir . '/src/System/Taxation/Infrastructure/Resources'
    public function locate(TaxRegimeId $regimeId): string  // full path to regimes/{id.lowercase()}.yaml
    /** @return list<string> */ public function findAll(): array  // glob(regimes/*.yaml)
}
```

---

**Task 3.6 — Console Commands (5 files)**
Path: `src/System/Taxation/Infrastructure/Console/`

`LoadTaxCategoriesCommand.php` — `app:taxation:load-categories`:
```
1. Parse categories.yaml
2. For each category: dispatch RegisterTaxCategory command
3. Output: "Loaded X categories"
```

`LoadTaxRegimeCommand.php` — `app:taxation:load-regime <regimeId>`:
```
1. TaxRegimeId::fromString($input->getArgument('regimeId'))
2. Dispatch LoadTaxRegime command
3. Output: "Loaded regime {id}"
```

`ReloadAllRegimesCommand.php` — `app:taxation:reload-all`:
```
1. Dispatch ReloadAllRegimes command
2. Output: "Reloaded X regimes"
```

`ListRegimesCommand.php` — `app:taxation:list-regimes`:
```
1. Dispatch ListSupportedRegimes query
2. Table output: id | name | country | rates count | status
```

`ResolveTaxCommand.php` — `app:taxation:resolve-test`:
```
Options: --category, --regime, --net (minor units), --currency, --country, --animal-usage, --date
1. Build FiscalContext + TaxableItem
2. Dispatch ResolveTax query
3. Pretty-print TaxApplication
```

---

**Task 3.7 — Resource YAML Files (2 files)**
Path: `src/System/Taxation/Infrastructure/Resources/`

`categories.yaml` — 19 veterinary categories (full list in spec section [13]).

`regimes/fr.yaml` — France regime with 4 rates + 2 legal mentions (full content in spec section [13]).

---

**Task 3.8 — Doctrine + Migration Configuration**

`config/packages/doctrine.yaml` — add after the `Money` block:
```yaml
Taxation:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/System/Taxation/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity'
    alias: Taxation
```

`config/packages/doctrine_migrations.yaml` — add (between Shared and Translation):
```yaml
'DoctrineMigrations\Taxation': '%kernel.project_dir%/migrations/Taxation'
```

`migrations/Taxation/Version<YYYYMMDDHHMMSS>.php` — creates 4 tables:
- `taxation__tax_regimes`
- `taxation__tax_rates` (with FK + index)
- `taxation__tax_categories`
- `taxation__mention_templates` (with unique constraint + index)

---

**Task 3.9 — Foundry Fixtures (4 files)**

`fixtures/System/Taxation/Factory/TaxRegimeEntityFactory.php`
`fixtures/System/Taxation/Factory/TaxRateEntityFactory.php`
`fixtures/System/Taxation/Factory/TaxCategoryEntityFactory.php`

`fixtures/System/Taxation/Story/TaxonomyBootstrapStory.php`:
```
1. Run LoadTaxCategoriesCommand (or dispatch commands directly)
2. Run LoadTaxRegimeCommand for 'FR'
```

**Note:** Factories target `*Entity` classes only (Doctrine layer). Never create
Foundry factories for Domain Aggregates.

---

**Task 3.10 — README**
Path: `src/System/Taxation/README.md`

Sections:
- Ubiquitous Language
- Business Invariants
- Use Cases
- Supported Regimes (FR active; CH Q4 2026; others backlog)
- How to Add a New Country (YAML + load-regime + tests)
- Fixture Examples

---

#### PHASE 4 — Tests

**Task 4.1 — Unit VO Tests**
Path: `tests/Unit/System/Taxation/Domain/ValueObject/`

- `TaxCategoryCodeTest.php` — valid regex, invalid throws, equals
- `TaxRegimeIdTest.php` — valid (FR, FR-DOM, GB), invalid, equals
- `TaxRateIdTest.php` — valid (fr.standard, fr.standard.2014), invalid, equals
- `TaxRateValueTest.php`:
  - ofBasisPoints: 0, 2000, 10000 valid; -1 throws; 10001 throws
  - ofPercentString — 9 dedicated tests:
    - "20.0" → 2000 ✓
    - "5.5" → 550 ✓
    - "2.1" → 210 ✓
    - "0.1" → 10 ✓
    - "0" → 0 ✓
    - "100" → 10000 ✓  (upper valid boundary — 100% rate)
    - "5.555" → throws InvalidTaxRateValueException (regex rejects 3 decimals)
    - "-5" → throws (regex rejects negative)
    - "" → throws (regex rejects empty)
    - "100.01" → throws (bcmath result 10001 > 10000, range check catches it)
  - toPercentString: 2000→"20.00", 550→"5.50", 210→"2.10", 0→"0.00"
  - toBcmathFactor: 2000→"0.2000", 550→"0.0550"
- `RegionCodeTest.php` — valid, invalid, equals
- `ValidityPeriodTest.php`:
  - openEnded: contains past, present; validFrom > date → false
  - closed: contains mid-range; outside → false
  - between with from > to → throws
- `TaxRateConditionTest.php` — `matches()` and `specificityScore()`:
  - Exact category match (+100)
  - Wildcard `veterinary.drug.*` → +20 (2 segments)
  - Wildcard `veterinary.act.*` → +20
  - Empty categories = wildcard
  - Region filter: match +5, no-match → false
  - CustomerTaxStatus filter: match +4
  - AnimalUsage filter: match +3
  - ClinicLiability filter: match +2
  - Multi-dimension: cat+region+animal → +20+5+3 = +28
  - Empty condition = matches everything, score = 0
- `FiscalContextTest.php` — of(), minimal(), all getters, immutability
- `MentionTemplateConditionTest.php` — 8 cases minimum:
  - empty condition → matches any FiscalContext ✓
  - region filter: matching region → true; non-matching → false
  - customerTaxStatus filter: INTRACOM matches INTRACOM; B2C does not
  - animalUsage filter: COMPANION matches; LIVESTOCK does not when COMPANION required
  - clinicLiability: NOT_LIABLE matches NOT_LIABLE; LIABLE does not
  - combined (region + customerStatus): both must match (AND logic)
  - partial match (region matches, customerStatus does not) → false
  - null FiscalContext fields vs non-empty filter → false (condition is unmet)

---

**Task 4.2 — Unit Aggregate Tests**
Path: `tests/Unit/System/Taxation/Domain/`

`TaxCategoryTest.php`:
- `create()` records `TaxCategoryRegistered`, sets fields correctly
- `reconstitute()` restores state without events
- `activate()` / `deactivate()` are idempotent
- `updateDisplay()` updates name/description

`TaxRegimeTest.php`:
- `create()` records `TaxRegimeLoaded`
- `reconstitute()` restores state without events
- `addRate()` records `TaxRateAdded`
- `replaceAllRates()` clears + sets rates; no individual TaxRateAdded events
- `activate()` on empty regime → `EmptyTaxRegimeCannotBeActivatedException`
- `activate()` on non-empty regime → records `TaxRegimeActivated`
- `deactivate()` → records `TaxRegimeDeactivated`
- `findCandidateRates()` returns all matching, no sorting, no exceptions:
  - Two matching rates → both returned
  - Date outside validity → excluded
  - Condition mismatch → excluded

---

**Task 4.3 — Unit Service Tests**

`TaxResolverTest.php` (FR scenarios only):
```
✓ FR consultation (COMPANION) → 20% (fr.standard)
✓ FR drug.companion + COMPANION → 5.5% (fr.super_reduced)
✓ FR drug.livestock + LIVESTOCK → 2.1% (fr.particular)
✓ FR food.therapeutic → 5.5% (fr.super_reduced)
✓ FR INTRACOM → 20% + LegalMention INTRACOM_REVERSE_CHARGE
✓ FR EXPORT → 20% + LegalMention EXPORT_OUT_OF_EU
✗ Unknown regime → UnknownTaxRegimeException
✗ Inactive regime → UnknownTaxRegimeException
✗ Unknown category → UnknownTaxCategoryException
✗ No applicable rate → NoApplicableRateException
✗ Ambiguous rates (2 rates same score) → AmbiguousRateException
    Precise setup: build TaxRegime with two TaxRate both having:
      categoriesMatching: ['veterinary.act.consultation']  (exact match, no wildcard → +100 each)
      regionsMatching: [], customerStatusesMatching: [], animalUsagesMatching: [], clinicLiability: null
    Both match veterinary.act.consultation with score = 100 → AmbiguousRateException
    Assert: exception message contains both rateIds (use regex: '/rate-a.*rate-b|rate-b.*rate-a/')
```
Use real TaxRate objects (no mocks for VOs). Mock: TaxRegimeRepositoryInterface,
TaxCategoryRegistryInterface, MoneyCalculator, RoundingPolicyRegistry, MentionTemplateResolver.

`MentionTemplateResolverTest.php`:
- match INTRACOM condition → generates mention with correct text
- `{{rate}}` placeholder replaced by "20.00"
- `{{regime}}` placeholder replaced by "FR"
- `{{country}}` placeholder replaced by "FR"
- unknown `{{foobar}}` left as-is
- non-matching condition → mention excluded
- inactive template → excluded (findActiveByRegime returns only active)

---

**Task 4.4 — Unit Handler Tests**

`LoadTaxRegimeHandlerTest.php`:
- Happy path: blueprint loaded → regime created → rates replaced → saved → events published
- Idempotent: existing regime → replaceAllRates called (not create)
- `DomainEventPublisher::publish()` called once per aggregate

`RegisterTaxCategoryHandlerTest.php`:
- New category: created + saved + published
- Existing category: updateDisplay called (idempotent)

`ReloadAllRegimesHandlerTest.php`:
- Dispatches LoadTaxRegime for each file found by RegimeFileLocator

`DeactivateTaxRegimeHandlerTest.php`:
- Regime found + deactivated + saved + published
- Unknown regime → UnknownTaxRegimeException

`ResolveTaxHandlerTest.php`:
- Happy path: returns TaxApplication
- Currency mismatch: USD on FR regime → CurrencyMismatchForRegimeException

`GetEffectiveRateForUIHandlerTest.php`:
- Derives EUR for FR regime, calls resolve, returns "5.50" for drug.companion/COMPANION

`YamlTaxRegimeLoaderTest.php`:
- Parses `fr.yaml` correctly: 4 rates, 2 mention templates, all fields asserted
- Unknown category pattern → UnknownCategoryPatternException with regimeId + pattern
- Overlapping validity periods on two rates with same condition → OverlappingValidityPeriodsException
  (setup: inline YAML with two rates sharing identical TaxRateCondition + overlapping validity dates)
- Non-overlapping periods on same condition → no exception (validates the non-overlap path)

---

**Task 4.5 — Integration Tests**
Path: `tests/Integration/System/Taxation/`

`DoctrineTaxRegimeRepositoryTest.php`:
- save → findById: all properties asserted (`self::assertSame()` on every field)
- save twice (idempotent): same state
- findAllActive: returns only active regimes

`DoctrineTaxCategoryRepositoryTest.php`:
- save → findByCode: all properties asserted

`DoctrineMentionTemplateRepositoryTest.php`:
- save → findActiveByRegime: correct filtering

`TaxRateMapperTest.php` (unit test — no DB):
- `testRoundTripSymmetry()`: `toDomain(toEntity($taxRate, 'FR'))` reconstitutes TaxRate with same id, value, validityPeriod, condition
- Test with open-ended ValidityPeriod and closed ValidityPeriod
- Test with all condition dimensions filled vs empty (wildcard)

`TaxRegimeMapperTest.php`:
- `toDomain(toEntity($regime))` reconstitutes equivalent aggregate

`TaxCategoryMapperTest.php`:
- `toDomain(toEntity($category))` reconstitutes equivalent aggregate

`DoctrineTaxCategoryRegistryTest.php`:
- `get()` returns correct category
- `has()` true/false
- `listActive()` excludes inactive

`LoadTaxRegimesCommandTest.php` (end-to-end integration):
1. Load categories first (prerequisite)
2. Load FR regime → assert 4 rates persisted
3. Load FR regime again (idempotent) → same count

`ReloadAllRegimesCommandTest.php`:
- Load categories → reload-all → 1 regime loaded (FR only at MVP)

`ReloadAllRegimesWithoutCategoriesTest.php`:
- reload-all without prior categories → UnknownCategoryPatternException

---

### Acceptance Criteria

**AC-1: TaxCategory Bootstrap**
```
Given categories.yaml exists with 19 veterinary categories
When `app:taxation:load-categories` is executed
Then all 19 TaxCategory records exist in the database as active
And re-running the command produces no duplicate errors (idempotent)
```

**AC-2: FR Regime Load**
```
Given categories are loaded
When `app:taxation:load-regime FR` is executed
Then TaxRegime 'FR' is persisted as active
And 4 TaxRate records exist for regime FR (fr.standard, fr.reduced, fr.super_reduced, fr.particular)
And 2 MentionTemplate records exist for regime FR (INTRACOM_REVERSE_CHARGE, EXPORT_OUT_OF_EU)
```

**AC-3: Tax Resolution — standard rate**
```
Given regime FR is loaded with categories
When ResolveTax is dispatched with:
  netAmount = Money::fromMinorUnits(10000, EUR)
  category = veterinary.act.consultation
  regimeId = FR
  fiscalContext = FiscalContext::minimal(FR, 2026-05-15)
Then TaxApplication.appliedRate.value = 2000 basis points (20%)
And TaxApplication.taxAmount = Money 2000 EUR minor units
And TaxApplication.grossAmount = Money 12000 EUR minor units
```

**AC-4: Tax Resolution — drug with COMPANION**
```
Given regime FR loaded
When ResolveTax with category=veterinary.drug.companion, animalUsage=COMPANION, EUR
Then appliedRate = fr.super_reduced, value = 550 bp (5.5%)
```

**AC-5: Tax Resolution — INTRACOM legal mention**
```
Given regime FR loaded
When ResolveTax with customerTaxStatus=INTRACOM, category=veterinary.act.consultation
Then TaxApplication.legalMentions contains code=INTRACOM_REVERSE_CHARGE
And legalMentions.byCode('INTRACOM_REVERSE_CHARGE').text = 'TVA non applicable, article 262 ter, I du CGI'
```

**AC-6: Currency Mismatch**
```
When ResolveTax dispatched with netAmount in USD and regimeId=FR
Then CurrencyMismatchForRegimeException is thrown
```

**AC-7: Unknown Pattern Rejection**
```
Given a fr.yaml with a category pattern 'veterinary.unknown.*'
When app:taxation:load-regime FR is executed
Then UnknownCategoryPatternException is thrown with regimeId=FR and pattern='veterinary.unknown.*'
```

**AC-8: Empty Regime Activation**
```
Given a TaxRegime with no rates
When activate() is called
Then EmptyTaxRegimeCannotBeActivatedException is thrown
```

**AC-9: Table Names (auto-generated)**
```
After migration: tables taxation__tax_regimes, taxation__tax_rates,
taxation__tax_categories, taxation__mention_templates exist
```

---

## Additional Context

### Dependencies

- `System/Money` BC — **already fully implemented** (confirmed via codebase scan).
  `MoneyCalculator`, `RoundingPolicyRegistry`, `Money::zero()`, `RoundingPolicyId::ACCOUNTING` are all available.
- `Shared\Domain\ValueObject\CountryCode` — exists at `src/Shared/Domain/ValueObject/CountryCode.php`
- `Shared\Domain\ValueObject\CurrencyCode` — exists at `src/Shared/Domain/ValueObject/CurrencyCode.php`
- `Shared\Domain\Identifier\AbstractUuidId` — exists; `->toString()` exposed
- `symfony/yaml` — must be available (already used by Translation BC, confirm in composer.json)
- `ext-bcmath` — already required by System/Money

No Clinic BC changes needed at MVP. `Clinic::taxRegimeId` and `Clinic::isLiableForTax`
are a separate future story. `FiscalContext` carries both fields as inputs so
Taxation is self-contained.

**Services / autowiring:** The project uses Symfony autowiring. Two explicit interface
bindings are needed in `config/services.yaml` (or a dedicated `config/packages/taxation.yaml`):
```yaml
App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface:
    '@App\System\Taxation\Infrastructure\Registry\DoctrineTaxCategoryRegistry'

App\System\Taxation\Application\Port\TaxRegimeLoaderInterface:
    '@App\System\Taxation\Infrastructure\RegimeLoader\YamlTaxRegimeLoader'
```
All other services (`TaxResolver`, repositories, handlers) are autowired by concrete
class type — no explicit binding needed. `MoneyCalculator` and `RoundingPolicyRegistry`
from `System/Money` are already registered as services and will be autowired.

### Testing Strategy

**Unit tests** (`tests/Unit/System/Taxation/`):
- All domain VOs, aggregates, and services tested in isolation
- Handlers tested with `createMock()` for all interfaces
- No Symfony container, no database
- Self-contained: use real VO instances, never mock VOs

**Integration tests** (`tests/Integration/System/Taxation/`):
- `KernelTestCase` + DAMA auto-rollback (no manual tearDown)
- Foundry v2 factories (`PersistentProxyObjectFactory`) for seeding entities
- Assert every property after reconstitution (not just non-null)
- `self::assertSame()` everywhere

**Coverage target**: 100% line coverage on all files in `src/System/Taxation/`.

### Notes

- `TaxRate` lives in `Domain/Entity/TaxRate.php` — it has identity (`TaxRateId`) but
  no independent lifecycle outside its parent `TaxRegime`. Its Doctrine counterpart is `TaxRateEntity`.
- `ch.yaml` wildcard `['*']` pattern for NOT_LIABLE: not needed at MVP since CH
  is out of scope. If added later, ensure `TaxRateCondition::matches()` handles
  the literal `'*'` as a universal wildcard (distinct from `fnmatch('*', ...)` which
  already does this).
- `RegimeFileLocator` is injected with `'%kernel.project_dir%/src/System/Taxation/Infrastructure/Resources'`
  via services.yaml or autowiring with a `#[Autowire]` attribute.
- The `source` field of `AppliedTaxRate` is set to `'yaml.'.$winner->id()->toString()`
  (e.g. `'yaml.fr.standard'`). This is a traceability string, not a lookup key.
- **Duplication `TaxRateCondition` / `MentionTemplateCondition`**: the two classes share ~80%
  of their matching logic (regions, customerStatuses, animalUsages, clinicLiability). This
  duplication is **intentional and assumed at MVP**. Refactoring into a shared `ConditionMatcher`
  is justified only when one of these conditions is met: (1) a new matching dimension (e.g.
  `saleChannel`) is added and must be propagated to both; (2) 3+ identical bugs are found and
  fixed in both classes in the same 6-month period; (3) a 3rd condition-bearing class is added.
  Document this decision in `src/System/Taxation/README.md` under "Architecture Decisions".
