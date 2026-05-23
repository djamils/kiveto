---
title: 'Context/Catalog — Core Commercial Catalog BC'
slug: 'context-catalog-bc'
created: '2026-05-23'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'PHP 8.5 + Symfony 7.4 + Doctrine ORM 3.5 + MySQL/MariaDB'
  - 'Zenstruck Foundry v2 (PersistentProxyObjectFactory)'
  - 'Symfony Messenger (CommandBus / QueryBus / IntegrationEventBus)'
  - 'ext-bcmath (Money arithmetic)'
  - 'symfony/yaml (starter catalog YAML parsing)'
files_to_modify:
  - 'config/packages/doctrine.yaml'
  - 'config/packages/doctrine_migrations.yaml'
  - 'config/packages/messenger.yaml'
code_patterns:
  - 'AggregateRoot extends App\Shared\Domain\Aggregate\AggregateRoot; create() records events, reconstitute() does NOT'
  - 'Domain events recorded via recordDomainEvent(), pulled via pullDomainEvents()'
  - 'Doctrine Entity separated from Domain; bridged by final readonly *Mapper'
  - 'BoundedContextPrefixNamingStrategy: App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ → catalog__{plural}'
  - 'CommandInterface / QueryInterface with #[AsMessageHandler] handlers'
  - 'DomainEventPublisher::publish($aggregate) after save — NOT EventBusInterface directly'
  - 'Integration events (cross-BC) published via IntegrationEventPublisher (separate injected service)'
  - 'UUIDs as BINARY(16) via UuidType::NAME, AbstractUuidId->toString() NEVER ->value()'
  - 'UUIDv7 via UuidGeneratorInterface, clock via ClockInterface'
  - 'TenantId = ClinicId local per BC (App\Context\Catalog\Domain\ValueObject\ClinicId extends AbstractUuidId)'
  - 'VO primitive accessors: ->toString() for string-backed'
  - 'Write repositories in Domain\Repository\, read/ports in Application\Port\'
  - 'Handler invariant order: load → mutate → save → DomainEventPublisher::publish()'
  - 'flush() in repository ONLY, never in handler'
  - 'Money stored as BIGINT minor units + CHAR(3) currency; no float ever'
  - 'PriceRuleCondition and PriceAdjustment stored as JSON columns in Doctrine'
  - 'Console commands named app:catalog:*'
  - 'Migrations namespace DoctrineMigrations\Catalog in migrations/Catalog/'
  - 'All Catalog domain events: final readonly class FooEvent extends AbstractDomainEvent — note final is intentional. Existing Clinic events omit final but Catalog enforces it. Separate debt task: align Clinic events.'
test_patterns:
  - 'Unit: PHPUnit, createMock() for interfaces, zero framework in domain tests'
  - 'Integration: KernelTestCase + Foundry PersistentProxyObjectFactory + real DB'
  - '100% line coverage on Domain + Application (excl. Presentation)'
  - 'Aggregate tests: instantiate directly, assert state + pullDomainEvents()'
  - 'Mapper tests: symmetry toDomain(toEntity($aggregate)) reconstitutes equivalent aggregate'
  - 'Repository integration tests: assert every property of reconstituted aggregate'
  - 'self::assertSame() everywhere — never assertEquals()'
  - 'VO matching in mock expectations: self::callback(fn($vo) => $vo->toString() === expected)'
---

# Tech-Spec: Context/Catalog — Core Commercial Catalog BC

**Created:** 2026-05-23

---

## Overview

### Problem Statement

A veterinary clinic has no unified catalog of what it sells and how it prices it. Without Catalog, billing is impossible (nothing to invoice), Inventory has nothing to reference (no articles to stock), and each new clinic starts from zero with no way to bootstrap their commercial offering. The current codebase has no concept of "what does a clinic sell and at what price."

### Solution

Deliver two sequential items:

1. **`chore/shared-unit-of-measure`** (prerequisite PR): add `Shared/UnitOfMeasure` (VO + YAML registry + exceptions) to `src/Shared/`.
2. **`feature/context-catalog-bc`** (main PR): implement `src/Context/Catalog/` — a tenant-scoped Bounded Context defining the 3 catalog item natures (Act, Article, Package), a contextual pricing system (PriceList + PriceRule + PriceResolver), and a cold-start mechanism (ApplyStarterCatalog command + fr/companion.yaml stub).

Act = veterinary procedure (knowledge, not a physical object). Article = physical item (drug, consumable, food, equipment). Package = billable assembly of Acts and Articles. Pricing resolves a contextual NET price given a PriceList and a PricingContext (urgency, species, size, discount).

The BC references PharmaceuticalRegistry for drug data via Port/Adapter only (no direct dependency). Drug flags on Article are denormalized for UI filtering performance and kept in sync via PharmaceuticalRegistry integration events.

### Scope

**In Scope:**

**PR 1 — `chore/shared-unit-of-measure`:**
- `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitOfMeasure.php` (VO, regex `^[A-Z][A-Z_]{0,16}$`)
- `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitCategory.php` (enum COUNT | MASS | VOLUME | TIME | OTHER)
- `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitDefinition.php` (resolved VO: code + category + base_factor)
- `src/Shared/Domain/UnitOfMeasure/Service/UnitOfMeasureRegistry.php` (interface)
- `src/Shared/Domain/UnitOfMeasure/Exception/InvalidUnitOfMeasureException.php`
- `src/Shared/Domain/UnitOfMeasure/Exception/UnknownUnitOfMeasureException.php`
- `src/Shared/Infrastructure/UnitOfMeasure/Registry/YamlUnitOfMeasureRegistry.php` (YAML-backed impl)
- `src/Shared/Infrastructure/UnitOfMeasure/Resources/units.yaml` (BOX, TABLET, CAPSULE, VIAL, SACHET, AMPULE, UNIT, DOSE, ML, L, G, KG)
- Unit tests for VO + registry

**PR 2 — `feature/context-catalog-bc`:**
- Domain: Act, Article, Package, Pricing (PriceList) aggregates + all VOs, events, exceptions, repository interfaces
- Domain service: `PriceCalculator` (pure calculation, no I/O)
- Application: 26 commands + 8 queries + 3 ports + 1 event handler + `PriceResolver` (Application Service)
- Infrastructure: 7 Doctrine entities + 6 mappers + 4 Doctrine repositories + PharmaceuticalRegistry adapter + console command
- Resources: `starter-catalogs/fr/companion.yaml` (stub: ~5 acts, ~5 drug articles, ~3 non-drug articles)
- Config: `doctrine.yaml`, `doctrine_migrations.yaml`, `messenger.yaml` (integration event routing)
- Migration: `migrations/Catalog/Version<timestamp>.php` (7 tables + all indexes)
- Foundry factories (6) + Stories (CompanionClinicCatalogStory, EmptyClinicStory)
- Unit tests (100%) + Integration tests (100%)
- `src/Context/Catalog/README.md`

**Out of Scope:**
- Inventory, Procurement, Billing
- Sub-Package support (Package in Package)
- DiscountPolicy as separate aggregate (modelled as PriceRule type DISCOUNT)
- Multi-currency within a single clinic
- Legacy system imports (Vetup, dr.veto)
- Temporal pricing (validity periods on PriceList items)
- AI price suggestions
- Real vet-validated fr/companion.yaml content (stub only for MVP)
- RuralClinicCatalogStory (deferred)

---

## Context for Development

### Codebase Patterns

**Tenant isolation** — `ClinicId` is the tenant key. Each BC defines its own local `ClinicId extends AbstractUuidId` with `fromString(string): self` factory. Catalog defines `App\Context\Catalog\Domain\ValueObject\ClinicId`. No shared `TenantId` VO.

**Money** — `App\Shared\Money\Domain\ValueObject\Money` stores amounts as integer minor units. Factory: `Money::fromMinorUnits(int, CurrencyCode)`. All arithmetic via `MoneyCalculator` (inject; never call `bcmath` directly in domain). Currency coherence check (clinic currency vs Money currency) happens in the **handler**, not in the aggregate.

**Doctrine entity naming** — `BoundedContextPrefixNamingStrategy` derives prefix from namespace. `App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity` → table `catalog__acts`. **Do NOT add `#[ORM\Table(name: ...)]`** — the strategy handles it.

**UUIDs in Doctrine** — use `Symfony\Bridge\Doctrine\Types\UuidType::NAME` (`'uuid'`). TenantId (`ClinicId`) stored as `BINARY(16)`.

**Integration events** — PharmaceuticalRegistry cross-BC events implement `AbstractIntegrationEvent` (routed via `messenger.bus.integration_event`). Catalog's `SyncCatalogOnPharmaceuticalChange` subscribes to the **real** event class names with distinct behaviors per event type:
- `PrescriptionRequirementChanged` → `updatePrescriptionFlags()` on matching Articles
- `ControlledSubstanceClassificationChanged` → `updatePrescriptionFlags()` on matching Articles
- `MarketingAuthorizationStatusChanged` → `updatePrescriptionFlags()` only if AMM still marketable
- `MarketingAuthorizationWithdrawn` → `archive()` on matching Articles (regulatory obligation — withdrawn drug must not be sold)

**PriceRule persistence** — `PriceRuleCondition` and `PriceAdjustment` are VOs that serialize to/from JSON. Stored as `JSON` columns in `PriceRuleEntity` (`condition_json`, `adjustment_json`). The Mapper handles the serialization/deserialization.

**Starter catalog idempotence** — `ApplyStarterCatalogHandler` checks if an act/article with the same `code` already exists for the tenant before creating. If it does → skip. No error.

**PharmaceuticalRegistry adapter** — `HealthRegistryPharmaceuticalRefAdapter` (named as per architecture doc) dispatches `GetMarketingAuthorizationByGtin` and `GetMarketingAuthorizationDetail` queries from `App\System\PharmaceuticalRegistry\Application\Query\*` via `QueryBusInterface` and maps results to `App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\PharmaceuticalRef` DTO.

**Migrations** — namespace `DoctrineMigrations\Catalog`, folder `migrations/Catalog/`. Add to `doctrine_migrations.yaml`:
```yaml
'DoctrineMigrations\Catalog': '%kernel.project_dir%/migrations/Catalog'
```

**Messenger routing** — integration event handler tagged via `#[AsMessageHandler(bus: 'messenger.bus.integration_event')]` on `SyncCatalogOnPharmaceuticalChange`. No routing change needed in `messenger.yaml` — `IntegrationEventInterface: async` is already configured globally and PharmaceuticalRegistry events implement it.

**Domain events are `final readonly class`** — `AbstractDomainEvent` and `AbstractIntegrationEvent` are both `abstract readonly class`. All Catalog domain events must be `final readonly class FooEvent extends AbstractDomainEvent`.

**Domain events are `final readonly class`** — All Catalog domain events must be `final readonly class FooEvent extends AbstractDomainEvent`. The `final` modifier is intentional. Existing Clinic BC events currently omit `final` — this is a separate debt task (DEBT-1) to align them. Do not follow the Clinic pattern here; enforce `final` on all new Catalog events.

**Doctrine save() pattern (confirmed)** — `$repo->find($uuid)` → if null: `$em->persist(new Entity); $em->flush()`; if exists: update entity properties then `$em->flush()`. Never `persist` an existing entity. This prevents Doctrine identity map conflicts. Note: use `Uuid::fromString($id->toString())` — NOT `->value()` — when converting domain IDs to Doctrine UUIDs.

**ClinicInfoProviderInterface** — `Application/Port/ClinicInfoProviderInterface.php` provides `countryCode(ClinicId): CountryCode` and `currencyCode(ClinicId): CurrencyCode`. Used by `ApplyStarterCatalogHandler` (country lookup) and by currency coherence validation in any handler that receives a `Money` parameter. Implemented by an adapter that dispatches to Clinic BC via `QueryBusInterface` — no direct Clinic domain import.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Money/Domain/ValueObject/Money.php` | Money VO API (`fromMinorUnits`, `zero`) |
| `src/Shared/Money/Domain/Service/MoneyCalculator.php` | Arithmetic: `add`, `subtract`, `multiply`, `allocate` |
| `src/Shared/Money/Domain/Service/RoundingPolicyRegistry.php` | `commercial()` rounding for pricing |
| `src/Shared/Domain/Identifier/AbstractUuidId.php` | Base for all ID VOs (`toString()`) |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | Base aggregate (`recordDomainEvent`, `pullDomainEvents`) |
| `src/Shared/Domain/Event/AbstractDomainEvent.php` | Base for intra-BC domain events |
| `src/Shared/Domain/Event/AbstractIntegrationEvent.php` | Base for cross-BC integration events |
| `src/Shared/Application/Event/DomainEventPublisher.php` | Publish after save |
| `src/Shared/Application/Event/IntegrationEventPublisher.php` | Publish cross-BC events |
| `src/System/Taxation/Domain/ValueObject/TaxCategoryCode.php` | Reference for import + validation pattern |
| `src/System/PharmaceuticalRegistry/Application/Query/` | Queries to dispatch from adapter |
| `src/System/PharmaceuticalRegistry/Domain/Event/` | Real integration event class names to subscribe |
| `src/Context/Scheduling/Domain/ValueObject/ClinicId.php` | Template for local ClinicId VO |
| `src/Context/Clinic/Domain/Clinic.php` | Named constructor + aggregate pattern reference |
| `config/packages/doctrine.yaml` | Add Catalog mapping block |
| `config/packages/doctrine_migrations.yaml` | Add Catalog migrations path |
| `config/packages/messenger.yaml` | Add integration event routing if needed |
| `src/_bmad-output/implementation-artifacts/tech-spec-system-pharmaceutical-registry-bc.md` | PharmaceuticalRegistry API surface reference |
| `src/System/PharmaceuticalRegistry/Application/Query/GetMarketingAuthorizationByGtin/GetMarketingAuthorizationByGtinHandler.php` | Returns `MarketingAuthorizationDetailView` (not `PharmaceuticalRefView`) — note `presentations: array<mixed>` |
| `src/System/PharmaceuticalRegistry/Application/ReadModel/PharmaceuticalRefView.php` | Fields: id, commercialName, holderLaboratory, status, atcVetCode, permanentIdentifier, presentations[], activeSubstanceLabels[], pharmaceuticalForm, lastImportSource, lastImportedAt |
| `src/System/PharmaceuticalRegistry/Domain/Event/PrescriptionRequirementChanged.php` | Fields: presentationId, marketingAuthorizationId, newClass (PrescriptionClass code string) |
| `src/System/PharmaceuticalRegistry/Domain/Event/ControlledSubstanceClassificationChanged.php` | Fields: marketingAuthorizationId, newClass (?string — null = no longer controlled) |
| `src/System/PharmaceuticalRegistry/Domain/Event/MarketingAuthorizationWithdrawn.php` | Fields: marketingAuthorizationId, effectiveDate (string) |
| `src/System/PharmaceuticalRegistry/Domain/Event/MarketingAuthorizationStatusChanged.php` | Fields: marketingAuthorizationId, previousStatus, newStatus (strings) |
| `src/Context/Clinic/Application/Query/Clinic/GetClinic/GetClinicHandler.php` | Returns `ClinicDto` with countryCode, currencyCode as strings — use for ClinicInfoAdapter |
| `src/Shared/Money/Domain/Service/MoneyCalculator.php` | `allocate(Money, list<int> $ratios): list<Money>` — last element receives rounding remainder |
| `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/DoctrineStrayCustodyRepository.php` | Reference save() pattern: find → update or persist(new) → flush. WARNING: This file uses `->value()` on IDs — do NOT copy that pattern. Catalog uses `->toString()` exclusively. |
| `fixtures/Context/Clinic/Factory/ClinicGroupEntityFactory.php` | Foundry v2 `PersistentProxyObjectFactory` boilerplate |
| `src/System/Taxation/Infrastructure/Persistence/Doctrine/Mapper/MentionTemplateMapper.php` | JSON column ↔ VO mapping pattern reference |

### Technical Decisions

1. **No inheritance across Act/Article/Package** — 3 distinct aggregates, no shared base class. `CatalogItemRef` VO provides polymorphic reference (enum type + UUID string).

2. **DrugProperties invariant enforced via named constructors** — `Article::createDrug()` always provides `DrugProperties`; `Article::createNonDrug()` never does. The invariant `(kind === DRUG) ↔ (drugProperties !== null)` is unviolable at construction time.

3. **PrescriptionClass copied in Catalog domain** — autonomous enum `App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass` (NONE, RX, RX_CONTROLLED, RX_NARCOTIC). Does NOT import from PharmaceuticalRegistry. Parallel definition by design.

4. **Pricing stores NET only** — ADR-008. `PriceListItem::netPrice` is NET HT. No TVA, no TTC in Pricing. Tax resolution is Taxation's job at billing time.

5. **PriceCalculator (domain) / PriceResolver (application) split** — `Domain/Pricing/Service/PriceCalculator.php` is a pure domain service: receives `Money $basePrice`, `list<PriceRule> $matchedRules` (pre-sorted), `RoundingPolicy`, returns `Money $netAmount` with applied rules. No repository injected — zero I/O. `Application/Service/PriceResolver.php` orchestrates: loads PriceList + Act/Article via repos, filters matching rules, **sorts by `PriceRuleType::applicationOrder()` (ascending)**, then delegates arithmetic to `PriceCalculator`. Returns `ResolvedPrice`.

6. **Deterministic PriceRule application order** — rules applied sequentially (result of previous feeds next). Order fixed by enum method `PriceRuleType::applicationOrder(): int`:
   - `SPECIES_ADJUSTMENT` → 10
   - `SIZE_ADJUSTMENT` → 20
   - `URGENCY_COEFFICIENT` → 30
   - `DISCOUNT` → 40
   
   Rationale: species/size adjustments are structural (clinic-specific pricing), urgency is a situational multiplier, discounts are the final reduction applied on the already-adjusted price.

7. **Package ACTIVE-item validation at handler level** — `Package::addComponent()` does NOT validate that the referenced Act/Article is ACTIVE (would require cross-aggregate load). `AddPackageComponentHandler` loads the Act or Article, asserts `status === ACTIVE`, throws `ArchivedItemInPackageException` if not. Archiving an Act/Article already in a Package does NOT cascade — the Package remains structurally valid; UI should surface a warning (future).

8. **PriceResolver uses `RoundingPolicyRegistry::commercial()`** — applies commercial rounding after each rule application (not just at the end) to avoid intermediate float drift.

9. **Package::FIXED_PRICE allocation** — uses `MoneyCalculator::allocate()` to split the fixed price proportionally by component weights. Sum of allocated parts === fixedPrice (no rounding leakage). The `allocate()` call lives in `GetPackageDetail` query handler (to compute per-component breakdown for display) and in future Billing when splitting a Package invoice line. The Package aggregate itself does NOT call `allocate()` — it only stores `fixedPrice` and component weights. The domain exposes `components(): list<PackageComponent>` and `fixedPrice(): ?Money`; consumers derive the allocation.

10. **JSON persistence for PriceRuleCondition + PriceAdjustment** — avoids a complex relational schema for rule conditions. Mapper handles serialize/deserialize. PHPStan requires explicit types on the JSON array shape.

11. **UnitOfMeasure validation at handler level** — the domain VO `UnitOfMeasure` validates format only (regex). Existence validation (is the code known to the registry?) happens in the **handler** via `UnitOfMeasureRegistry`. Same pattern as `TaxCategoryCode` validation via `TaxCategoryRegistry`.

12. **StarterCatalog idempotence** — handler checks `ActRepositoryInterface::existsByCode(ActCode, ClinicId)` and `ArticleRepositoryInterface::existsByCode(ArticleCode, ClinicId)` before creating. No upsert.

13. **Gtin duplication** — both PharmaceuticalRegistry and Catalog define `Gtin` VO. They are independent (BC isolation). Catalog's `Gtin` does NOT import PharmaceuticalRegistry's `Gtin`.

14. **CreateDrugArticle rejects non-marketable AMM** — `CreateDrugArticleHandler` calls `PharmaceuticalRefProviderInterface::findById()` when `authorizationRef` is provided. If the returned `PharmaceuticalRef` indicates the AMM is withdrawn or not marketable, the handler throws `AuthorizationNotMarketableException` (Article is NOT created). If `authorizationRef` is `null` (magistral preparation), the handler skips the lookup entirely — the vet manually sets the drug flags.

15. **ClinicInfoProviderInterface replaces direct Clinic import** — any handler needing `countryCode` or `currencyCode` of the active clinic uses `Application/Port/ClinicInfoProviderInterface`. Implemented by `ClinicInfoAdapter` (Infrastructure/Adapter/Clinic/) dispatching a Clinic BC query. This includes `CreateActHandler`, `CreateDrugArticleHandler`, `CreateNonDrugArticleHandler`, etc. for currency coherence checks. When the Money parameter's currency differs from `ClinicInfoProviderInterface::getCurrencyCode(clinicId)`, throw `ClinicCurrencyMismatchException` (defined in `Domain/Exception/` — reused across Act, Article, and PriceList handlers within the Catalog BC).

---

## Implementation Plan

### Tasks

Ordered by dependency. Each task is independently committable.

---

**PR 1: `chore/shared-unit-of-measure`**

**T-UOM-1**: `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitCategory.php`
- `enum UnitCategory: string { case COUNT = 'COUNT'; case MASS = 'MASS'; case VOLUME = 'VOLUME'; case TIME = 'TIME'; case OTHER = 'OTHER'; }`

**T-UOM-2**: `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitOfMeasure.php`
- `final class`, private constructor, factory `fromString(string): self`
- Validates regex `^[A-Z][A-Z_]{0,16}$`; throws `InvalidUnitOfMeasureException`
- `toString(): string`, `equals(self): bool`

**T-UOM-3**: `src/Shared/Domain/UnitOfMeasure/ValueObject/UnitDefinition.php`
- `final readonly class UnitDefinition(public UnitOfMeasure $code, public UnitCategory $category, public ?string $baseFactor)`

**T-UOM-4**: `src/Shared/Domain/UnitOfMeasure/Exception/InvalidUnitOfMeasureException.php` + `UnknownUnitOfMeasureException.php`

**T-UOM-5**: `src/Shared/Domain/UnitOfMeasure/Service/UnitOfMeasureRegistry.php`
```php
interface UnitOfMeasureRegistry {
    public function resolve(UnitOfMeasure $unit): UnitDefinition; // throws UnknownUnitOfMeasureException
    public function has(UnitOfMeasure $unit): bool;
    /** @return list<UnitDefinition> */
    public function all(): array;
}
```

**T-UOM-6**: `src/Shared/Infrastructure/UnitOfMeasure/Resources/units.yaml` — units as listed in architecture (BOX, TABLET, CAPSULE, VIAL, SACHET, AMPULE, UNIT, DOSE, ML, L, G, KG)

**T-UOM-7**: `src/Shared/Infrastructure/UnitOfMeasure/Registry/YamlUnitOfMeasureRegistry.php`
- Loads units.yaml at boot; stores as `array<string, UnitDefinition>` in memory
- Wire as service tagged for `UnitOfMeasureRegistry` interface

**T-UOM-8**: Unit tests — `UnitOfMeasureTest`, `YamlUnitOfMeasureRegistryTest` (100% coverage)

---

**PR 2: `feature/context-catalog-bc`**

**GROUP A — Shared VOs & Config**

**T-A1**: `src/Context/Catalog/Domain/ValueObject/ClinicId.php`
- `final class ClinicId extends AbstractUuidId { public static function fromString(string): self }`

**T-A2**: `src/Context/Catalog/Domain/Shared/ValueObject/CatalogItemType.php`
- `enum CatalogItemType: string { case ACT = 'ACT'; case ARTICLE = 'ARTICLE'; }`

**T-A3**: `src/Context/Catalog/Domain/Shared/ValueObject/CatalogItemRef.php`
- `final class`, private constructor
- `static toAct(ActId): self`, `static toArticle(ArticleId): self`
- `type(): CatalogItemType`, `id(): string`, `isAct(): bool`, `isArticle(): bool`, `equals(self): bool`

**T-A4**: Config — add Catalog mapping to `config/packages/doctrine.yaml`:
```yaml
Catalog:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/Context/Catalog/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity'
    alias: Catalog
```

**T-A5**: Config — add to `config/packages/doctrine_migrations.yaml`:
```yaml
'DoctrineMigrations\Catalog': '%kernel.project_dir%/migrations/Catalog'
```

**T-A6**: Wire new interfaces in `config/services.yaml`:
```yaml
App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry: '@App\Shared\Infrastructure\UnitOfMeasure\Registry\YamlUnitOfMeasureRegistry'
App\Context\Catalog\Application\Port\PharmaceuticalRefProviderInterface: '@App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\HealthRegistryPharmaceuticalRefAdapter'
App\Context\Catalog\Application\Port\ClinicInfoProviderInterface: '@App\Context\Catalog\Infrastructure\Adapter\Clinic\ClinicInfoAdapter'
```
Check existing analogous entries (e.g., TaxCategoryRegistry binding pattern) in `config/services.yaml` to match the format.

---

**GROUP B — Act aggregate (full stack)**

**T-B1**: Act VOs — `ActId`, `ActName` (max 150, non-empty), `ActCode` (regex `^[A-Z0-9_-]{2,20}$`), `ActDescription` (max 1000, null if empty), `ActCategory` (enum), `ActDuration` (factory `ofMinutes(int>0): self`), `ActStatus` (enum ACTIVE|ARCHIVED)

**T-B2**: Act events — `ActCreated`, `ActRenamed`, `ActBasePriceChanged`, `ActTaxCategoryChanged`, `ActArchived`, `ActRestored` — all extend `AbstractDomainEvent`, carry scalar properties only

**T-B3**: Act exceptions — `ActNotFoundException`, `DuplicateActCodeException`, `InvalidActNameException`, `ArchivedActCannotBeModifiedException`, `ClinicCurrencyMismatchException`

**T-B4**: `src/Context/Catalog/Domain/Act/Act.php`
- `create(ActId, ClinicId, ActName, ActCode, ?ActDescription, ActCategory, TaxCategoryCode, Money, ActDuration, bool $requiresAnesthesia, \DateTimeImmutable): self` → records `ActCreated`
- `reconstitute(...)`: no event
- `rename(ActName $name, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ActRenamed`; throws `ArchivedActCannotBeModifiedException` if ARCHIVED
- `changeBasePrice(Money $price, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ActBasePriceChanged`; throws if ARCHIVED
- `changeTaxCategory(TaxCategoryCode $code, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ActTaxCategoryChanged`; throws if ARCHIVED
- `archive(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ActArchived` (idempotent — no-op if already ARCHIVED)
- `restore(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ActRestored` (idempotent — no-op if already ACTIVE)
- Handlers inject `ClockInterface` and pass `$this->clock->now()` as the `$updatedAt` argument
- Getters: `id()`, `clinicId()`, `name()`, `code()`, `description()`, `category()`, `taxCategory()`, `basePrice()`, `estimatedDuration()`, `status()`, `requiresAnesthesia()`, `createdAt()`, `updatedAt()`

**T-B5**: `src/Context/Catalog/Domain/Act/Repository/ActRepositoryInterface.php`
- `save(Act): void`
- `findById(ActId, ClinicId): ?Act`
- `findByCode(ActCode, ClinicId): ?Act`
- `existsByCode(ActCode, ClinicId): bool`

**T-B6**: `ActEntity.php` — Doctrine entity with all columns per schema. No `#[ORM\Table(name:...)]`. UUIDs via `UuidType::NAME`. Enums as `string`.

**T-B7**: `ActMapper.php` — `final readonly class`. `toEntity(Act, ?ActEntity): ActEntity`; `toDomain(ActEntity): Act`

**T-B8**: `DoctrineActRepository.php` — implements `ActRepositoryInterface`. Pattern: `find()` first → update fields if exists → `persist(new)` if null → `flush()`.

**T-B9**: Application commands:
- `CreateAct` / `CreateActHandler` — validates `TaxCategoryCode` via `TaxCategoryRegistry`; validates currency vs clinic; saves; publishes
- `RenameAct` / `RenameActHandler`
- `ChangeActBasePrice` / `ChangeActBasePriceHandler`
- `ChangeActTaxCategory` / `ChangeActTaxCategoryHandler`
- `ArchiveAct` / `ArchiveActHandler`
- `RestoreAct` / `RestoreActHandler`

**T-B10**: Query `GetActDetail` / `GetActDetailHandler` — returns `ActDetailView` DTO

**T-B11**: Unit tests — Act aggregate (all invariants + events), ActCode regex exhaustive, all handlers (mocked repos)

**T-B12**: Integration tests — `DoctrineActRepository` symmetry + TenantId scoping, `ActMapper` symmetry

---

**GROUP C — Article aggregate (full stack)**

**T-C1**: Article VOs — `ArticleId`, `ArticleName`, `ArticleCode`, `ArticleKind` (enum DRUG|CONSUMABLE|FOOD|EQUIPMENT), `Gtin` (regex `^\d{8,14}$`, no Luhn), `ArticleStatus` (enum ACTIVE|ARCHIVED)

**T-C2**: `MarketingAuthorizationRef.php` — wraps a UUID string; `toString()`, `equals(self): bool`

**T-C3**: `PrescriptionClass.php` — enum (NONE|RX|RX_CONTROLLED|RX_NARCOTIC) — Catalog-local, NOT imported from PharmaceuticalRegistry

**T-C4**: `DrugProperties.php` — `final class` (not readonly — to allow `updatePrescriptionFlags` mutation)
- Properties: `?MarketingAuthorizationRef $authorizationRef`, `bool $requiresPrescription`, `?PrescriptionClass $prescriptionClass`, `bool $isControlledSubstance`
- Factory: `create(?MarketingAuthorizationRef, bool, ?PrescriptionClass, bool): self`
- `authorizationRef(): ?MarketingAuthorizationRef`, other getters
- `withUpdatedFlags(bool $requiresPrescription, ?PrescriptionClass, bool $isControlledSubstance): self` (returns new instance — immutable update)

**T-C5**: Article events — `ArticleCreated`, `ArticleRenamed`, `ArticleBasePriceChanged`, `ArticlePrescriptionFlagsUpdated`, `ArticleTaxCategoryChanged`, `ArticleArchived`, `ArticleRestored`

**T-C6**: Article exceptions — `ArticleNotFoundException`, `DuplicateArticleCodeException`, `DuplicateGtinException`, `InvalidDrugPropertiesException`, `ArchivedArticleCannotBeModifiedException`, `AuthorizationNotMarketableException`, `RegulatoryRestoreForbiddenException`, `ClinicCurrencyMismatchException`

**T-C7**: `src/Context/Catalog/Domain/Article/Article.php`
- `createDrug(ArticleId, ClinicId, ArticleName, ArticleCode, ?Gtin, TaxCategoryCode, Money, UnitOfMeasure, DrugProperties, bool $trackStock, \DateTimeImmutable): self`
  - Asserts `$drugProperties !== null`, `kind = DRUG`
  - Records `ArticleCreated`
- `createNonDrug(ArticleId, ClinicId, ArticleName, ArticleCode, ArticleKind $kind, ?Gtin, TaxCategoryCode, Money, UnitOfMeasure, bool $trackStock, \DateTimeImmutable): self`
  - Asserts `$kind !== DRUG`; throws `InvalidDrugPropertiesException` if `$kind === DRUG`
  - Records `ArticleCreated`
- `reconstitute(...)`: no event
- `rename(ArticleName $name, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ArticleRenamed`; throws if ARCHIVED
- `changeBasePrice(Money $price, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ArticleBasePriceChanged`; throws if ARCHIVED
- `changeTaxCategory(TaxCategoryCode $code, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ArticleTaxCategoryChanged`; throws if ARCHIVED
- `updatePrescriptionFlags(bool $requiresPrescription, ?PrescriptionClass $class, bool $isControlledSubstance, \DateTimeImmutable $updatedAt)`:
  - **Idempotent**: only mutates + records `ArticlePrescriptionFlagsUpdated` if flags actually changed; sets `$this->updatedAt = $updatedAt` when mutated
  - Asserts `kind === DRUG` (else throw `InvalidDrugPropertiesException`)
- `archive(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `ArticleArchived` (idempotent)
- `restore(\DateTimeImmutable $updatedAt)`:
  - If `kind !== DRUG` → restore unconditionally → emits `ArticleRestored`
  - If `kind === DRUG` and `authorizationRef !== null`: handler checks AMM status via `PharmaceuticalRefProviderInterface::findById()` BEFORE calling `restore()` on the aggregate; if AMM status is WITHDRAWN → handler throws `RegulatoryRestoreForbiddenException` (NOT thrown inside aggregate); if AMM is marketable → restore → emits `ArticleRestored`
  - If `kind === DRUG` and `authorizationRef === null` (magistrale) → restore unconditionally → emits `ArticleRestored`
  - Idempotent: no-op if already ACTIVE
- Handlers inject `ClockInterface` and pass `$this->clock->now()` as the `$updatedAt` argument
- Getters: `id()`, `clinicId()`, `name()`, `code()`, `kind()`, `gtin()`, `taxCategory()`, `basePrice()`, `unitOfMeasure()`, `drugProperties(): ?DrugProperties`, `trackStock()`, `status()`, `createdAt()`, `updatedAt()`
  - Note: `drugProperties(): ?DrugProperties` — returns the full DrugProperties VO (or null if not DRUG kind); required by `SyncCatalogOnPharmaceuticalChange` to read current flag values before calling `updatePrescriptionFlags()`

**T-C8**: `ArticleRepositoryInterface` — `save()`, `findById()`, `findByCode()`, `findByGtin()`, `existsByCode()`, `existsByGtin()`

**T-C8b**: `src/Context/Catalog/Application/Port/ArticleReadRepositoryInterface.php` (read port):
- `findAllByAuthorizationRef(MarketingAuthorizationRef $ref): list<Article>` — cross-tenant scan for the event handler (no ClinicId filter; MarketingAuthorization is a global/tenant-free registry)
- `search(string $term, ClinicId $clinicId, int $limit): list<Article>` (and other read-side methods as needed)

**T-C9**: `ArticleEntity.php` — Doctrine entity per schema (drug columns nullable)

**T-C10**: `ArticleMapper.php` — handles DrugProperties serialization (drug_auth_ref, drug_requires_rx, drug_prescription_class, drug_is_controlled columns)

**T-C11**: `DoctrineArticleRepository.php`

**T-C12a**: Port — `src/Context/Catalog/Application/Port/PharmaceuticalRefProviderInterface.php`:
```php
interface PharmaceuticalRefProviderInterface {
    /**
     * Returns light results: drug flag fields (requiresPrescription, isControlledSubstance) are null.
     * Sufficient for autocomplete UI only.
     * @return list<PharmaceuticalRef>
     */
    public function searchByName(string $term, int $limit = 20): array;
    /** Returns full results with drug flags populated — required at Article creation time. */
    public function findByGtin(Gtin $gtin): ?PharmaceuticalRef;
    /** Returns full results with drug flags populated — required at Article creation time. */
    public function findById(MarketingAuthorizationRef $ref): ?PharmaceuticalRef;
}
```

**T-C12b**: Port — `src/Context/Catalog/Application/Port/ClinicInfoProviderInterface.php`:
```php
interface ClinicInfoProviderInterface {
    public function getCountryCode(ClinicId $clinicId): CountryCode;
    public function getCurrencyCode(ClinicId $clinicId): CurrencyCode;
}
```
Implemented by `ClinicInfoAdapter` (Infrastructure/Adapter/Clinic/) dispatching to Clinic BC via `QueryBusInterface`. Used by: `ApplyStarterCatalogHandler` (countryCode), all price-accepting handlers (currencyCode coherence check).

**T-C13**: `src/Context/Catalog/Infrastructure/Adapter/HealthRegistry/PharmaceuticalRef.php` — DTO (final readonly class), Catalog-defined:
- Properties: `string $id` (UUID), `string $commercialName`, `?string $gtin`, `?bool $requiresPrescription`, `?string $prescriptionClass`, `?bool $isControlledSubstance`
- Note: `searchByName()` returns `null` for drug flag fields (`requiresPrescription`, `isControlledSubstance`) — sufficient for autocomplete UI only. `findByGtin()` and `findById()` return fully populated fields (required at Article creation time).

**T-C14**: `HealthRegistryPharmaceuticalRefAdapter.php` — implements `PharmaceuticalRefProviderInterface`; dispatches `GetMarketingAuthorizationByGtin`, `GetMarketingAuthorizationDetail`, `SearchMarketingAuthorizations` queries from `App\System\PharmaceuticalRegistry\Application\Query\*` via `QueryBusInterface`; maps results to `PharmaceuticalRef`
- `gtin` field of `PharmaceuticalRef`: populated from `PharmaceuticalRefView` if a `gtin` field is added by HEALTHREGISTRY-FIX-1; otherwise set to `null`

**T-C15**: Application commands:
- `CreateDrugArticle` / `CreateDrugArticleHandler`:
  - If `authorizationRef` provided: calls `PharmaceuticalRefProviderInterface::findById()`; throws `AuthorizationNotMarketableException` if AMM `status === 'WITHDRAWN'` or not found; maps `controlledSubstanceClass !== null → isControlledSubstance=true`
  - The adapter calls `findByGtin(gtin)` or `findById(authorizationRef)` which (after HEALTHREGISTRY-FIX-1) will return `controlledSubstanceClass` and allow derivation of `isControlledSubstance`. `requiresPrescription` and `prescriptionClass` come from the detail view's `controlledSubstanceClass` field — until HEALTHREGISTRY-FIX-1 lands, these default to `null` (nullable flags per F5 decision)
  - If `authorizationRef` is `null` (magistral): skips lookup entirely; vet-supplied flags used as-is
  - Validates UnitOfMeasure via `UnitOfMeasureRegistry`
  - Validates currency coherence via `ClinicInfoProviderInterface::currencyCode()`
- `CreateNonDrugArticle` / `CreateNonDrugArticleHandler` — refuses `kind = DRUG`
- `RenameArticle` / `RenameArticleHandler`
- `ChangeArticleBasePrice` / `ChangeArticleBasePriceHandler`
- `ChangeArticleTaxCategory` / `ChangeArticleTaxCategoryHandler`
- `ArchiveArticle` / `ArchiveArticleHandler`
- `RestoreArticle` / `RestoreArticleHandler`:
  - For DRUG articles with `authorizationRef !== null`: calls `PharmaceuticalRefProviderInterface::findById()` before calling `restore()` on aggregate; throws `RegulatoryRestoreForbiddenException` if AMM is WITHDRAWN
  - For magistrale (authorizationRef=null) and non-drug: calls `restore()` unconditionally

**T-C16**: `SyncCatalogOnPharmaceuticalChange.php`

Handles **3** PharmaceuticalRegistry integration events (NOT 4 — `MarketingAuthorizationStatusChanged` is excluded at MVP). Uses `#[AsMessageHandler(bus: 'messenger.bus.integration_event')]` on **each public method separately**:

```php
#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onPrescriptionRequirementChanged')]
public function onPrescriptionRequirementChanged(PrescriptionRequirementChanged $event): void

#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onControlledSubstanceClassificationChanged')]
public function onControlledSubstanceClassificationChanged(ControlledSubstanceClassificationChanged $event): void

#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onMarketingAuthorizationWithdrawn')]
public function onMarketingAuthorizationWithdrawn(MarketingAuthorizationWithdrawn $event): void
```

Injects `ArticleReadRepositoryInterface` (not `ArticleRepositoryInterface`) for the cross-tenant lookup.

**Event payloads (confirmed from source):**

| Event | Relevant fields | Handler behavior |
|-------|-----------------|-----------------|
| `PrescriptionRequirementChanged` | `marketingAuthorizationId`, `newClass: string` (PrescriptionClass code) | Wrap `PrescriptionClass::from($newClass)` in try/catch for `\ValueError` — if unknown class code (enum divergence), log warning and skip (do NOT throw); otherwise: `requiresPrescription = ($newClass !== 'NONE')`, `prescriptionClass = PrescriptionClass::from($newClass)` → `updatePrescriptionFlags(...)` |
| `ControlledSubstanceClassificationChanged` | `marketingAuthorizationId`, `newClass: ?string` | `isControlledSubstance = (null !== $newClass)` → `updatePrescriptionFlags(...)` keeping other flags unchanged |
| `MarketingAuthorizationWithdrawn` | `marketingAuthorizationId`, `effectiveDate: string` | `article->archive()` — regulatory obligation; idempotent |

**Note**: `MarketingAuthorizationStatusChanged` is NOT handled at MVP. Status transitions (SUSPENDED, REINSTATED, etc.) do not trigger automatic catalog changes. Revisit if business need emerges.

**Repository used**: `ArticleReadRepositoryInterface::findAllByAuthorizationRef(MarketingAuthorizationRef): list<Article>` (cross-tenant — no ClinicId filter, because PharmaceuticalRegistry is global/tenant-free).

**T-C17**: Query `GetArticleDetail`, `SearchPharmaceuticalReferential`, and `SearchCatalogItems`:

**SearchCatalogItems** — FULLTEXT cross-3-tables search:
- Return type: `CatalogSearchResultCollection` — `final readonly class` with `list<CatalogSearchResultItem> $items` and `int $total`
- `CatalogSearchResultItem` — `final readonly class`: `string $id`, `string $type` (ACT|ARTICLE|PACKAGE), `string $name`, `string $code`, `?string $gtin`, `string $status`
- Implementation: 3 separate DBAL queries (Acts FULLTEXT, Articles FULLTEXT + gtin LIKE, Packages name/code LIKE), results merged and sorted by relevance in PHP, then limited
- FULLTEXT for Acts and Articles on `name` column; Packages use `LIKE %term%` on `name` and `code` (Packages have no FULLTEXT index in migration)
- Lives in `Application/Port/CatalogSearchRepositoryInterface` (read port); implementation in `Infrastructure/Persistence/Doctrine/DoctrineCatalogSearchRepository`
- Uses `Doctrine\DBAL\Connection` directly (not ORM) for flexibility

**T-C18**: Unit + Integration tests — full coverage Article, DrugProperties invariants, EventHandler idempotence, Adapter (with mocked QueryBus)

---

**GROUP D — Pricing (PriceList)**

**T-D1**: Pricing VOs — `PriceListId`, `PriceListName`, `PriceListItemId`, `PriceRuleId`, `PriceListStatus` (enum ACTIVE|ARCHIVED), `AnimalSizeCategory` (enum XS|S|M|L|XL), `PriceRuleType` (enum with `applicationOrder(): int` method):
```php
enum PriceRuleType: string {
    case SPECIES_ADJUSTMENT   = 'SPECIES_ADJUSTMENT';   // applicationOrder: 10
    case SIZE_ADJUSTMENT      = 'SIZE_ADJUSTMENT';      // applicationOrder: 20
    case URGENCY_COEFFICIENT  = 'URGENCY_COEFFICIENT';  // applicationOrder: 30
    case DISCOUNT             = 'DISCOUNT';             // applicationOrder: 40

    public function applicationOrder(): int {
        return match($this) {
            self::SPECIES_ADJUSTMENT  => 10,
            self::SIZE_ADJUSTMENT     => 20,
            self::URGENCY_COEFFICIENT => 30,
            self::DISCOUNT            => 40,
        };
    }
}
```

**T-D2**: `PriceAdjustment.php` — `final class`:
- **`AdjustmentType` MUST be a separate top-level file**: `src/Context/Catalog/Domain/Pricing/ValueObject/AdjustmentType.php`
- It is a `backed enum: string`: `enum AdjustmentType: string { case COEFFICIENT = 'COEFFICIENT'; case FIXED_AMOUNT_DISCOUNT = 'FIXED_AMOUNT_DISCOUNT'; }`
- **NOT nested inside PriceAdjustment class** — PHP does not support nested enums (parse error)
- Properties: `AdjustmentType $type`, `string $decimalValue` (for COEFFICIENT), `?Money $money` (for FIXED_AMOUNT_DISCOUNT)
- Factories: `coefficient(string $decimalValue): self`, `fixedAmountDiscount(Money): self`
- Serialization (with PHPStan array shape annotations):
  - `/** @return array{type: string, value: string} */ public function toArray(): array`
  - `/** @param array{type: string, value: string} $data */ public static function fromArray(array $data): self`

**T-D3**: `PriceRuleCondition.php` — `final class`:
- Properties: `bool $appliesToAllItems`, `list<CatalogItemRef> $itemRefs`, `?bool $isUrgency`, `?AnimalSizeCategory $animalSize`, `?string $speciesGroup`, `?string $discountCode`
- `matches(PricingContext $context, CatalogItemRef $itemRef): bool`
  - If `$this->discountCode !== null`, condition only matches when `$context->discountCode === $this->discountCode`
- Serialization (with PHPStan array shape annotations):
  - `/** @return array{appliesToAllItems: bool, itemRefs: list<array{type: string, id: string}>, isUrgency: bool|null, animalSize: string|null, speciesGroup: string|null, discountCode: string|null} */ public function toArray(): array`
  - `/** @param array{appliesToAllItems: bool, itemRefs: list<array{type: string, id: string}>, isUrgency: bool|null, animalSize: string|null, speciesGroup: string|null, discountCode: string|null} $data */ public static function fromArray(array $data): self`

**T-D4**: `PricingContext.php` — `final readonly class`: `PriceListId`, `bool $isUrgency`, `?AnimalSizeCategory`, `?string $speciesGroup`, `?string $discountCode`, `\DateTimeImmutable $pricingDate`

**T-D5**: `AppliedPriceRule.php` — `final readonly class`: `PriceRuleId`, `PriceRuleType`, `PriceAdjustment`

**T-D6**: `ResolvedPrice.php` — `final readonly class`: `Money $netAmount`, `Money $baseAmount`, `list<AppliedPriceRule> $appliedRules`, `PriceListId $sourcePriceList`, `?PriceListItemId $sourcePriceListItem`, `\DateTimeImmutable $resolvedAt`

**T-D7**: `PriceListItem.php` — child entity:
- `PriceListItemId`, `CatalogItemRef`, `Money $netPrice`, `?TaxCategoryCode $taxCategoryOverride`
- Invariant: `netPrice.minorUnits >= 0`

**T-D8**: `PriceRule.php` — child entity:
- `PriceRuleId`, `PriceRuleType`, `PriceRuleCondition`, `PriceAdjustment`

**T-D9**: Pricing events — `PriceListCreated`, `PriceListItemAdded`, `PriceListItemUpdated`, `PriceListItemRemoved`, `PriceRuleAdded`, `PriceRuleRemoved`

**T-D10**: Pricing exceptions — `PriceListNotFoundException`, `NoPriceFoundForItemException`, `InvalidPriceAdjustmentException`, `DefaultPriceListAlreadyExistsException`

**T-D11**: `PriceList.php` — aggregate:
- `create(PriceListId, ClinicId, PriceListName, bool $isDefault, \DateTimeImmutable): self` → `PriceListCreated`
- `addItem(PriceListItem $item, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PriceListItemAdded`
- `updateItem(PriceListItemId $id, Money $price, ?TaxCategoryCode $tax, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PriceListItemUpdated`
- `removeItem(PriceListItemId $id, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PriceListItemRemoved`
- `addRule(PriceRule $rule, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PriceRuleAdded`
- `removeRule(PriceRuleId $id, \DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PriceRuleRemoved`
- `markAsDefault(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`
- `unmarkAsDefault(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`
- Invariant: name non-empty
- Handlers inject `ClockInterface` and pass `$this->clock->now()` as the `$updatedAt` argument

**T-D12**: `PriceListRepositoryInterface` — `save()`, `findById()`, `findDefaultForClinic()`, `hasDefaultForClinic()`

**T-D13a**: `src/Context/Catalog/Domain/Pricing/Service/PriceCalculator.php` — pure domain service, zero I/O:
```php
final class PriceCalculator {
    public function __construct(private MoneyCalculator $calc) {}

    /**
     * @param list<PriceRule> $sortedRules already filtered (matching) and sorted by applicationOrder()
     * @return array{netAmount: Money, appliedRules: list<AppliedPriceRule>}
     */
    public function calculate(Money $basePrice, array $sortedRules, RoundingPolicy $rounding): array;
}
```
- Applies each rule sequentially: result of rule N is input of rule N+1
- Rounds after each rule via `$rounding`
- Returns `netAmount` + list of `AppliedPriceRule` for traceability

**T-D13b**: `src/Context/Catalog/Application/Service/PriceResolver.php` — Application Service:

Injected dependencies: `PriceListRepositoryInterface`, `ActRepositoryInterface`, `ArticleRepositoryInterface`, `PriceCalculator`, `RoundingPolicyRegistry`, `ClockInterface`

```
resolve(CatalogItemRef $itemRef, PricingContext $context): ResolvedPrice
  1. Load PriceList via PriceListRepositoryInterface → PriceListNotFoundException if absent
  2. Find PriceListItem for itemRef in PriceList
     - If found → basePrice = PriceListItem::netPrice; sourcePriceListItem = its ID
     - If not found → extract $clinicId = $priceList->clinicId() from the loaded PriceList aggregate
       → load Act or Article via ActRepositoryInterface::findById($actId, $clinicId) or ArticleRepositoryInterface::findById($articleId, $clinicId)
       → basePrice = Act::basePrice() or Article::basePrice(); sourcePriceListItem = null
  3. If no price found → NoPriceFoundForItemException
  4. Filter PriceList::rules where condition.matches(context, itemRef)
  5. Sort filtered rules by PriceRuleType::applicationOrder() ascending
  6. Delegate: PriceCalculator::calculate(basePrice, sortedRules, RoundingPolicyRegistry::commercial())
  7. Build and return ResolvedPrice (netAmount, baseAmount, appliedRules, sourcePriceList, sourcePriceListItem, resolvedAt: $this->clock->now())
```

**T-D14**: Doctrine entities: `PriceListEntity`, `PriceListItemEntity` (FK to price_list), `PriceRuleEntity` (FK to price_list, condition_json JSON, adjustment_json JSON)

**T-D15**: Mappers: `PriceListMapper`, `PriceListItemMapper`, `PriceRuleMapper` (handles JSON ↔ VO for condition + adjustment)

**T-D16**: `DoctrinePriceListRepository.php`

**T-D17**: Application commands: `CreatePriceList`, `AddPriceListItem`, `UpdatePriceListItem`, `RemovePriceListItem`, `AddPriceRule`, `RemovePriceRule` — with handlers

**T-D18**: Queries: `GetPriceList`, `ListPriceLists`, `ResolvePrice` (calls `PriceResolver`)

**T-D19**: Unit + Integration tests — PriceResolver scenarios (full matrix per section [11]), PriceRuleCondition.matches, allocate invariants, all handlers

> **MoneyCalculator.allocate() behavior (confirmed from source)**: remainder goes to the LAST element. For `allocate(Money(10000, EUR), [1, 1, 1])` → `[Money(3333), Money(3333), Money(3334)]`. Tests must assert against the LAST element receiving the remainder, not the first.

---

**GROUP E — Package aggregate**

**T-E1**: Package VOs — `PackageId`, `PackageName`, `PackageCode`, `PackageComponentId`, `PackagePricingMode` (enum FIXED_PRICE|SUM_OF_COMPONENTS), `Quantity` (factory `of(int>0): self`), `PackageStatus` (enum ACTIVE|ARCHIVED)

**T-E2**: `PackageComponent.php` — child entity: `PackageComponentId`, `CatalogItemRef`, `Quantity`, `int $weight`

**T-E3**: Package events — `PackageCreated`, `PackageComponentAdded`, `PackageComponentRemoved`, `PackageFixedPriceChanged`, `PackageArchived`, `PackageRestored`

**T-E4**: Package exceptions — `PackageNotFoundException`, `EmptyPackageException`, `ArchivedItemInPackageException`, `InvalidPackagePricingModeException`, `DuplicatePackageCodeException`

**T-E5**: `Package.php` — aggregate:
- `create(PackageId, ClinicId, PackageName, PackageCode, TaxCategoryCode, PackagePricingMode, ?Money $fixedPrice, \DateTimeImmutable): self`
  - Invariant: `FIXED_PRICE ↔ fixedPrice !== null`
  - Records `PackageCreated`
- `addComponent(CatalogItemRef, Quantity, int $weight)` → `PackageComponentAdded`
  - Validates: `itemRef` points to ACTIVE Act/Article (injected check at handler level, not here)
- `removeComponent(PackageComponentId)` → `PackageComponentRemoved`
- `changeFixedPrice(Money)` → `PackageFixedPriceChanged` (only if `pricingMode === FIXED_PRICE`; throws `InvalidPackagePricingModeException` otherwise)
- `archive()` → `PackageArchived` (idempotent)
- `restore(\DateTimeImmutable $updatedAt)` → sets `$this->updatedAt = $updatedAt`; emits `PackageRestored` (idempotent — no conditions, restore unconditionally)

**T-E6**: `PackageRepositoryInterface`

**T-E7**: `PackageEntity`, `PackageComponentEntity` (FK to package ON DELETE CASCADE)

**T-E8**: `PackageMapper` — handles `PackageComponent[]` collection

**T-E9**: `DoctrinePackageRepository`

**T-E10**: Application commands: `CreatePackage`, `AddPackageComponent`, `RemovePackageComponent`, `ChangePackageFixedPrice`, `ArchivePackage`, `RestorePackage` — with handlers

**T-E11**: Query `GetPackageDetail`

**T-E12**: Unit + Integration tests — Package invariants, FIXED_PRICE ↔ fixedPrice, allocate on 3-component Package

---

**GROUP F — ApplyStarterCatalog**

**T-F1**: `StarterCatalogNotAvailableException.php`

**T-F2**: `src/Context/Catalog/Infrastructure/Resources/starter-catalogs/fr/companion.yaml` — stub:
  - 5 acts (CONS_STD, CONS_VACC, STERIL_FEMELLE, RADIO_THORAX, HOSPIT_J)
  - 5 drug articles (with placeholder GTIN, authorizationRef skipped if not in HealthRegistry)
  - 3 non-drug articles (seringues, compresses, alimentation thérapeutique)
  - 1 default price list with suggested prices

YAML schema:
```yaml
# Schema: fr/companion.yaml
name: string                    # display name
default_price_list:
  name: string
  is_default: true

acts:
  - code: string               # ActCode regex ^[A-Z0-9_-]{2,20}$
    name: string               # ActName
    category: string           # ActCategory enum value
    tax_category: string       # TaxCategoryCode
    estimated_duration_minutes: int
    suggested_price_minor: int  # in currency minor units
    requires_anesthesia: bool

articles_drug:
  - gtin: string               # optional — used to lookup AMM via PharmaceuticalRegistry
    code: string               # ArticleCode
    name_override: string|null # null = use PharmaceuticalRegistry commercialName if available
    tax_category: string
    unit_of_measure: string    # UnitOfMeasure code
    suggested_price_minor: int
    track_stock: bool
    # Drug flags: populated from PharmaceuticalRegistry via gtin lookup
    # If lookup fails or GTIN not found → article created with null flags (magistrale-like)

articles_non_drug:
  - code: string
    name: string
    kind: string               # ArticleKind: CONSUMABLE | FOOD | EQUIPMENT
    tax_category: string
    unit_of_measure: string
    suggested_price_minor: int
    track_stock: bool
```

**T-F3**: `ApplyStarterCatalog` command + `ApplyStarterCatalogHandler`:

The handler injects `CommandBusInterface`, `ClockInterface`, `ClinicInfoProviderInterface`, `PharmaceuticalRefProviderInterface`, `ActRepositoryInterface`, `ArticleRepositoryInterface`, `PriceListRepositoryInterface`, `LoggerInterface`.

  1. Resolve `countryCode` via `ClinicInfoProviderInterface::getCountryCode(clinicId)`
  2. Resolve path `starter-catalogs/{countryCode_lower}/{type}.yaml`
  3. If absent → `StarterCatalogNotAvailableException`
  4. Parse YAML
  5. For each act in YAML: call `existsByCode(code, clinicId)` on `ActRepositoryInterface` — if exists, add to `$report->skipped`; otherwise dispatch `CreateAct` command via `CommandBusInterface`; catch exceptions → add to `$report->failed`, continue
  6. For each drug article in YAML: call `existsByCode(code, clinicId)` on `ArticleRepositoryInterface` — if exists, skip; otherwise optionally call `PharmaceuticalRefProviderInterface::findByGtin(gtin)` if gtin present; dispatch `CreateDrugArticle` command; catch → `$report->failed`, continue
  7. For each non-drug article in YAML: check `existsByCode()` → skip if exists; dispatch `CreateNonDrugArticle`; catch → `$report->failed`, continue
  8. Create default PriceList if `hasDefaultForClinic() === false` — dispatch `CreatePriceList` command
  9. Log completion via `LoggerInterface`: `$logger->info('Starter catalog applied', ['clinicId' => ..., 'type' => ..., 'actsCreated' => ..., 'articlesCreated' => ...])`
  10. Return `StarterCatalogApplicationReport`

**Idempotence**: handled by `existsByCode()` checks before each dispatch — skip silently if item already exists.
**Error handling**: partial application is acceptable — no rollback. Failed items are collected in the report and logged.
**No `StarterCatalogApplied` domain event** — replaced by structured logging.

New DTO: `Application/Service/StarterCatalogApplicationReport.php` — `final readonly class` with `list<string> $created`, `list<string> $skipped`, `list<string> $failed` (by code).

**T-F4**: `ApplyStarterCatalogCommand.php` (console, `app:catalog:apply-starter`) — options: `--clinic=<uuid>`, `--type=companion`

**T-F5**: Unit + Integration tests — FR+companion applies; CH+companion throws; 2× consecutive = idempotent

---

**GROUP G — Migration**

**T-G1**: `migrations/Catalog/Version<timestamp>.php`
- Creates 7 tables with all indexes per schema in section [9]
- NB: MySQL partial `WHERE` indexes (e.g. `WHERE gtin IS NOT NULL`) are NOT supported natively in Doctrine migrations — write raw SQL for those
- FULLTEXT indexes on `ft_act_name`, `ft_article_name` added via raw SQL (Doctrine doesn't generate FULLTEXT)

---

**GROUP H — Fixtures**

**T-H1**: `ActEntityFactory`, `ArticleEntityFactory`, `PackageEntityFactory`, `PriceListEntityFactory`, `PriceListItemEntityFactory`, `PriceRuleEntityFactory` — all extend `PersistentProxyObjectFactory`

**T-H2**: `CompanionClinicCatalogStory` — ~30 acts + ~50 drug articles + ~20 non-drug articles; 1 default PriceList + 1 non-default; demonstrates ACTIVE + ARCHIVED items

**T-H3**: `EmptyClinicStory` — clinic with empty catalog (no acts, no articles, no PriceList)

---

**GROUP I — README**

**T-I1**: `src/Context/Catalog/README.md` — Ubiquitous Language, business invariants, use cases, fixture examples

---

**GROUP INFRA — CI & Infrastructure**

**T-INFRA-1**: Confirm Docker MySQL version supports FULLTEXT on InnoDB. The project has no `.github/workflows` — CI is Docker-local only. Run `docker compose exec db mysql --version` to verify MySQL 8.0+ is used. InnoDB FULLTEXT is supported from MySQL 5.6+ but `MATCH ... AGAINST` with `IN BOOLEAN MODE` on `catalog__acts.name` and `catalog__articles.name` requires the FULLTEXT indexes to be created in the migration. Confirm migration creates them via raw SQL (not Doctrine schema tool which doesn't generate FULLTEXT). Add this check to PR description.

---

### Acceptance Criteria

**AC-1 — Act: ARCHIVED invariant**
- Given an ARCHIVED Act
- When `rename()`, `changeBasePrice()`, `changeTaxCategory()` are called
- Then `ArchivedActCannotBeModifiedException` is thrown and no event is recorded

**AC-2 — Act: archive/restore idempotence**
- Given an ACTIVE Act
- When `archive()` is called twice consecutively
- Then only one `ActArchived` event is emitted (second call is no-op)

**AC-3 — Article: Drug invariant**
- Given `Article::createNonDrug()` called with `ArticleKind::DRUG`
- Then `InvalidDrugPropertiesException` is thrown
- And the Article is not persisted

**AC-4 — Article: DrugProperties idempotence**
- Given an Article with `requiresPrescription=true, prescriptionClass=RX, isControlledSubstance=false`
- When `updatePrescriptionFlags(true, RX, false)` is called (same values)
- Then NO `ArticlePrescriptionFlagsUpdated` event is recorded

**AC-5 — Pricing: PriceListItem override**
- Given a PriceList with a PriceListItem for Act X at netPrice=5000 (50 €)
- When `PriceResolver::resolve(ActRef, context)` is called
- Then `ResolvedPrice::netAmount` = 5000 and `sourcePriceListItem` is set

**AC-6 — Pricing: Fallback to basePrice**
- Given a PriceList with NO PriceListItem for Act X, and Act X has basePrice=4000
- When `PriceResolver::resolve(ActRef, context)` is called
- Then `ResolvedPrice::netAmount` = 4000 and `sourcePriceListItem` is null

**AC-7 — Pricing: URGENCY_COEFFICIENT rule**
- Given a PriceRule of type URGENCY_COEFFICIENT with coefficient=1.5 and condition `isUrgency=true`
- And PricingContext with `isUrgency=true`
- When `PriceResolver::resolve()` is called
- Then netAmount = baseAmount × 1.5 (rounded via commercial rounding)

**AC-8 — Package: FIXED_PRICE ↔ fixedPrice invariant**
- Given `Package::create()` with `pricingMode=FIXED_PRICE` and `fixedPrice=null`
- Then `InvalidPackagePricingModeException` is thrown

**AC-9 — Package: FIXED_PRICE allocate correctness**
- Given a Package with FIXED_PRICE=10000 (100 €) and 3 components with weights [1,1,1]
- When `MoneyCalculator::allocate()` is called
- Then parts = [3333, 3333, 3334] (remainder goes to LAST element) and sum(parts) = 10000

**AC-10 — Tenant isolation**
- Given ClinicA has Act with code=CONS_STD and ClinicB has no acts
- When `DoctrineActRepository::findByCode(CONS_STD, ClinicB.id)` is called
- Then null is returned (no cross-tenant leak)

**AC-11 — StarterCatalog idempotence**
- Given `ApplyStarterCatalog` has been run for ClinicA (companion, FR)
- When it is run again for the same ClinicA
- Then no new Acts/Articles/PriceLists are created (all skipped)

**AC-12 — StarterCatalog unavailable country**
- Given a Clinic with countryCode=CH
- When `ApplyStarterCatalog` is run with type=companion
- Then `StarterCatalogNotAvailableException` is thrown

**AC-13 — EventHandler: SyncDrugFlags**
- Given Article A has `authorizationRef=UUID-X` and `requiresPrescription=false`
- When `PrescriptionRequirementChanged` event for UUID-X is received with `requiresPrescription=true`
- Then Article A's `requiresPrescription` is updated to true and `ArticlePrescriptionFlagsUpdated` event is recorded

**AC-14 — Table naming**
- Given Doctrine entity `App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity`
- When Doctrine resolves the table name
- Then the table is named `catalog__acts`

**AC-15 — Mapper symmetry**
- Given an Act aggregate created via `Act::create()`
- When `ActMapper::toEntity($act)` then `ActMapper::toDomain($entity)` are called
- Then `toDomain(toEntity($act))` reconstitutes an aggregate with identical values on all properties

**AC-16 — PriceList: isDefault uniqueness**
- Given ClinicA already has a PriceList with `isDefault=true`
- When `CreatePriceListHandler` is called with `isDefault=true` for the same ClinicA
- Then `DefaultPriceListAlreadyExistsException` is thrown and no new PriceList is persisted

**AC-17 — Article: magistral preparation (authorizationRef=null)**
- Given `CreateDrugArticle` command with `authorizationRef=null`, `requiresPrescription=true`, `prescriptionClass=RX`
- When `CreateDrugArticleHandler` processes the command
- Then `PharmaceuticalRefProviderInterface::findById()` is NOT called
- And an Article of kind DRUG is created with `drugProperties.authorizationRef === null` and the manually-supplied flags

**AC-18 — Package: ArchivedItem via Act**
- Given Act X has `status=ARCHIVED`
- When `AddPackageComponentHandler` is called to add Act X to a Package
- Then `ArchivedItemInPackageException` is thrown
- And no `PackageComponentAdded` event is recorded

**AC-19 — Package: ArchivedItem via Article**
- Given Article Y has `status=ARCHIVED`
- When `AddPackageComponentHandler` is called to add Article Y to a Package
- Then `ArchivedItemInPackageException` is thrown
- And no `PackageComponentAdded` event is recorded

**AC-20 — Package: archiving an Act does not cascade to Package**
- Given Act Z is a component of Package P (both ACTIVE)
- When `ArchiveActHandler` archives Act Z
- Then Package P remains ACTIVE and its components list still includes Act Z
- And no `PackageArchived` event is recorded

**AC-21 — Gtin: invalid formats rejected**
- Given `Gtin::fromString()` called with '1234567' (7 digits, too short)
- Then an exception is thrown
- Given `Gtin::fromString()` called with 'ABCD1234567' (contains letters)
- Then an exception is thrown
- Given `Gtin::fromString()` called with '' (empty)
- Then an exception is thrown
- Given `Gtin::fromString()` called with '12345678' (8 digits — minimum valid)
- Then a valid `Gtin` is returned

**AC-22 — EventHandler: MarketingAuthorizationWithdrawn archives Articles**
- Given Article A and Article B both have `authorizationRef=UUID-X` and `status=ACTIVE`
- When `MarketingAuthorizationWithdrawn` event for UUID-X is received
- Then both Article A and Article B have `status=ARCHIVED`
- And `ArticleArchived` event is recorded for each

**AC-23 — PriceRule application order is deterministic**
- Given a PriceList with rules: DISCOUNT(-500), URGENCY_COEFFICIENT(×1.5), SPECIES_ADJUSTMENT(×0.9) all matching context
- When `PriceResolver::resolve()` is called with basePrice=10000
- Then rules are applied in order: SPECIES_ADJUSTMENT first (10000×0.9=9000), then URGENCY_COEFFICIENT (9000×1.5=13500), then DISCOUNT (13500-500=13000)
- And netAmount = 13000 (not dependent on order of rules in collection)

---

## Additional Context

### Blocking Prerequisites

**HEALTHREGISTRY-FIX-1 (must be resolved before feature/context-catalog-bc):**
`GetMarketingAuthorizationByGtinHandler` and `GetMarketingAuthorizationDetailHandler` currently hardcode `controlledSubstanceClass: null` in their response construction. This is a bug in `src/System/PharmaceuticalRegistry/Application/Query/`. Until fixed:
- `HealthRegistryPharmaceuticalRefAdapter::findByGtin()` and `findById()` will always return `isControlledSubstance=false`
- This silently breaks controlled-substance flagging for all Drug Articles

This must be fixed as a separate commit/PR on the `feature/system-pharmaceutical-registry-bc` branch before Catalog is implemented.

Similarly, `PharmaceuticalRefView` has no `gtin` field — fix needed there too so `PharmaceuticalRef.gtin` can be populated from detail queries.

### Dependencies

**Hard dependencies (must exist before implementation):**
- `src/Shared/Money/` — already delivered ✅
- `src/System/Taxation/Domain/ValueObject/TaxCategoryCode.php` — already delivered ✅
- `src/System/PharmaceuticalRegistry/Application/Query/GetMarketingAuthorizationByGtin.php` + `GetMarketingAuthorizationDetail.php` + `SearchMarketingAuthorizations.php` — must be implemented as part of PharmaceuticalRegistry BC (feature/system-pharmaceutical-registry-bc branch)
- `src/System/PharmaceuticalRegistry/Domain/Event/PrescriptionRequirementChanged.php` + `MarketingAuthorizationWithdrawn.php` + `MarketingAuthorizationStatusChanged.php` + `ControlledSubstanceClassificationChanged.php` — must implement `AbstractIntegrationEvent`
- `src/Shared/Domain/UnitOfMeasure/` — delivered in PR 1 (chore/shared-unit-of-measure)

**Soft dependencies (referenced by ID only, no direct PHP import):**
- `Clinic` aggregate — Catalog reads `countryCode` and `currencyCode` via `ClinicInfoProviderInterface` (Application/Port), implemented by an adapter dispatching to Clinic BC via `QueryBusInterface`

### Testing Strategy

- **Unit tests** in `tests/Unit/Context/Catalog/` mirroring source structure
- **Integration tests** in `tests/Integration/Context/Catalog/`
- Foundry factories target Doctrine Entities only — no factory for Domain Aggregates
- `DAMA\DoctrineTestBundle` auto-rollback — no manual `tearDown()` cleanup
- PharmaceuticalRegistry adapter tested with mocked `QueryBusInterface`
- PriceResolver: inject real `MoneyCalculator` + `RoundingPolicyRegistry` in unit tests (no mocking of pure domain services)
- All FULLTEXT search integration tests require MySQL (not SQLite) — use the project's Docker MySQL
- Unit tests for `PriceCalculator` use `InMemoryCurrencyRegistry` test double (to be created at `tests/Stub/InMemoryCurrencyRegistry.php`) instead of mocking `MoneyCalculator` directly. `MoneyCalculator` is injected as-is (it is a pure service once `CurrencyRegistry` is provided). `YamlCurrencyRegistry` is NOT used in unit tests — only in integration tests.

### Notes

- **Implementation order is locked**: UnitOfMeasure → Act → Article → Pricing → Package → StarterCatalog (see section [14] of architecture)
- **`PresentationGtinChanged` event from PharmaceuticalRegistry**: NOT handled by Catalog. If a GTIN changes in PharmaceuticalRegistry, Catalog's `Article.gtin` is a user-entered value independent of the registry — no sync needed for GTIN changes.
- **Currency coherence**: validated at handler level by loading the clinic and comparing `Clinic::currencyCode()` with the Money object's currency. The domain aggregate does NOT validate this.
- **`fr/companion.yaml` stub**: uses placeholder GTINs that may not exist in PharmaceuticalRegistry. The handler must gracefully skip `authorizationRef` lookup if the GTIN returns null from the adapter (treat as magistral preparation with `authorizationRef=null`).
- **Doctrine partial indexes**: MySQL does NOT support `WHERE` partial indexes via standard `CREATE UNIQUE INDEX`. The migration must use raw SQL: `CREATE UNIQUE INDEX ... ON ... (gtin) WHERE gtin IS NOT NULL` is PostgreSQL syntax. For MySQL, use application-level unique check in repository instead, or use a generated column trick. Decision: use application-level uniqueness check in `DoctrineArticleRepository::existsByGtin()` rather than DB-level partial unique index.
- **`isDefault` PriceList uniqueness**: `UNIQUE INDEX uniq_pricelist_tenant_default (tenant_id) WHERE is_default = 1` is PostgreSQL syntax. For MySQL: enforce via application-level check `PriceListRepositoryInterface::hasDefaultForClinic()` in handler, not DB constraint.
- **`SyncCatalogOnPharmaceuticalChange` is cross-tenant**: `findAllByAuthorizationRef()` scans ALL clinics' articles — MarketingAuthorization is a global (tenant-free) registry. The handler then saves each Article in its own clinic's transaction. DAMA auto-rollback in tests covers this normally, but integration tests for this handler must be careful to set up Articles for multiple tenants.
- **CI MySQL requirement**: FULLTEXT indexes require MySQL 8.0+. Confirm CI uses MySQL before implementing T-C17 (T-INFRA-1). This is a blocking prerequisite for the FULLTEXT integration tests.

## Delivery Order

1. **HEALTHREGISTRY-FIX-1** (separate commit on `feature/system-pharmaceutical-registry-bc`): Fix `controlledSubstanceClass` and `gtin` hardcoding in `GetMarketingAuthorizationByGtinHandler` and `GetMarketingAuthorizationDetailHandler`. Required before Catalog implementation.
2. **`chore/shared-unit-of-measure`** PR: Shared/UnitOfMeasure (T-UOM-1 to T-UOM-8).
3. **`feature/context-catalog-bc`** PR: Full Catalog BC (all Groups A–I + INFRA).
4. **`content/fr-companion-starter`** PR (deferred): Real `fr/companion.yaml` data co-built with a pilot vet.

## Technical Debt Tasks (separate issues, not this PR)

- **DEBT-1**: Align Clinic BC domain events to use `final readonly class` (currently `readonly class` without `final`).
- **DEBT-2**: Evaluate adding `RestoreArticle` regulatory guard for non-DRUG articles in a future sprint if the UX pain point is confirmed.
