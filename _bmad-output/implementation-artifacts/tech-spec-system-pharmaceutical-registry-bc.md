---
title: 'System/PharmaceuticalRegistry — Global Veterinary Drug Registry'
slug: 'system-pharmaceutical-registry-bc'
created: '2026-05-16'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'PHP 8.5'
  - 'Symfony 7.4'
  - 'Doctrine ORM 3.5'
  - 'MySQL/MariaDB (FULLTEXT index required)'
  - 'symfony/doctrine-messenger (already installed)'
  - 'Zenstruck Foundry v2 (PersistentProxyObjectFactory)'
  - 'XMLReader (streaming, NOT SimpleXML)'
  - 'ext-bcmath (verify present in Docker PHP image)'
files_to_modify:
  - 'config/packages/doctrine.yaml'
  - 'config/packages/doctrine_migrations.yaml'
code_patterns:
  - 'AggregateRoot extends App\Shared\Domain\Aggregate\AggregateRoot with create()/reconstitute() named constructors'
  - 'Domain events recorded via recordDomainEvent(), pulled via pullDomainEvents()'
  - 'Doctrine Entity separated from Domain aggregate, bridged by final readonly *Mapper'
  - 'BoundedContextPrefixNamingStrategy: pharmaceutical_registry__{plural}, NO #[ORM\Table(name:...)]'
  - 'AuthorizationEntity (not MarketingAuthorizationEntity) → table pharmaceutical_registry__authorizations'
  - 'CommandInterface / QueryInterface with #[AsMessageHandler] handlers'
  - 'DomainEventPublisher::publish($aggregate) after save — NOT EventBusInterface directly'
  - 'UUIDs as BINARY(16) via UuidType::NAME, AbstractUuidId->toString() (NOT ->value())'
  - 'UUIDv7 via UuidGeneratorInterface, clock via ClockInterface'
  - 'VO primitive accessors: ->toString() for string-backed, ->value() NEVER'
  - 'IntegrationEventInterface required for cross-BC events (async routing)'
  - 'Events: BOUNDED_CONTEXT = ''pharmaceutical_registry'', VERSION = 1'
  - 'AbstractDomainEvent for internal events, AbstractIntegrationEvent for cross-BC'
  - 'Repositories: Domain\Repository\ for write interfaces, Application\Port\ for read/external ports'
  - 'MarketingAuthorizationBlueprint: final readonly class in Domain/, produced by AnmvCodeMapper (infra), consumed by aggregate (domain)'
  - 'MarketingAuthorizationHashProjection: 2-field DTO (authorityIdentifier, contentHash) for diff calc — never load full aggregates'
  - 'Dual event publishing: DomainEventPublisher for DomainEventInterface (sync, intra-BC); IntegrationEventPublisher for IntegrationEventInterface (async, cross-BC). Handlers emitting cross-BC events must inject BOTH publishers. Check ArchiveClientHandler or any handler that emits IntegrationEvents for the exact injection pattern used in this project.'
test_patterns:
  - 'Unit: PHPUnit, createMock() for interfaces, zero framework/Doctrine in domain tests'
  - 'Integration: KernelTestCase + Foundry PersistentProxyObjectFactory + real DB'
  - '100% line coverage required on all non-Presentation code'
  - 'ContentHash determinism test: same data with permuted keys → same hash; different data → different hash'
  - 'AnmvCodeMapper exhaustive: test every mapped code, throw on unknown'
  - 'IntegrationEventInterface scan test: all 10 cross-BC events implement it'
  - 'Idempotence test: 2 consecutive full import cycles → 0 CREATE/UPDATE/WITHDRAW on 2nd run'
  - 'All ANMV XML tests use static fixture files in tests/fixtures/ — never the real 53 MB file'
---

# Tech-Spec: System/PharmaceuticalRegistry — Global Veterinary Drug Registry

**Created:** 2026-05-16

---

## Overview

### Problem Statement

The veterinary SaaS has no centralised regulatory drug reference. Any BC needing Marketing Authorization data (AMM) — Catalog, Procurement — would have to duplicate it, creating coupling violations and data inconsistencies. The domain language also suffers: the previous BC name "HealthRegistry" was too broad (health covers consultations, records, pathologies) and did not communicate that this is a **drug registry**. The aggregate name "Authorization" collided with RBAC terminology from AccessControl.

### Solution

Implement `src/System/PharmaceuticalRegistry/` — a tenant-free, globally shared regulatory reference BC — that:
1. Ingests ANMV France XML via local file (`--file=PATH`) using event-driven diff strategy (create/update/withdraw — no destructive full reload)
2. Stores a **universal `MarketingAuthorization` model** independent of jurisdiction source
3. Emits precise business events when regulations change (withdrawal, prescription change, withdrawal-period change)
4. Exposes `PharmaceuticalRefView` as the sole cross-BC read model consumed by Catalog
5. Provides autocomplete search by commercial name, GTIN, INN, ATCvet code, or national/EU number
6. Enforces a strict Anti-Corruption Layer per jurisdiction (`ImportSources/{Country}/`)

The Doctrine entity for the aggregate root is deliberately named `AuthorizationEntity` (short, readable table: `pharmaceutical_registry__authorizations`); the domain speaks `MarketingAuthorization` throughout. The `*Mapper` bridges the two.

The ACL boundary materialises through `MarketingAuthorizationBlueprint` — a `final readonly class` in `Domain/` produced by `AnmvCodeMapper` (infra) and consumed by the aggregate (domain). This is the precise seam between ANMV-specific parsing and universal domain logic.

### Scope

**In Scope:**

- Domain: `MarketingAuthorization`, `ActiveSubstance`, `Snapshot` aggregates; `Presentation`, `Composition`, `TargetUsage`, `Summary`, `DiffEntry`, `SnapshotEntry` child entities; `MarketingAuthorizationBlueprint` DTO; `MarketingAuthorizationHashProjection` DTO; 35 value objects / enums (incl. `WithdrawalPeriodUnit`); `RegistryDiffCalculator` service; 3 repository interfaces; 16 domain events; 11 exceptions
- Application: 5 commands (`ImportSnapshot`, `CalculateSnapshotDiff`, `ApplySnapshotDiff`, `RunFullImportCycle`, `ManuallyMarkMarketingAuthorizationWithdrawn`), 10 queries, 3 Application/Port interfaces (no downloader), `PharmaceuticalRefView` read model
- Infrastructure: 9 Doctrine entities + 6 mappers + 5 repositories, ANMV Anti-Corruption Layer France (XML parser, dictionary loader, code mapper — file-based only at MVP), 6 console commands (no HTTP downloader command), `PharmaceuticalRefProjection`; dedicated **bootstrap console command** with batched transactions for the initial full import
- Config changes: `doctrine.yaml`, `doctrine_migrations.yaml`
- Doctrine migration for all 9 tables (FULLTEXT index added manually in migration SQL)
- ANMV species mapping resource file (versioned PHP array, produced from real dictionary XML)
- Foundry v2 fixtures: 4 factories + 3 stories (~10 representative drugs)
- Unit tests (100%) + Integration tests (100%)
- `src/System/PharmaceuticalRegistry/README.md`

**Out of Scope (deferred — not MVP):**

- HTTP download of ANMV file from data.gouv.fr — `AnmvDownloadService`, `RegistryDownloaderInterface`, `AnmvFileLocator` (MVP uses `--file=PATH`)
- Symfony Scheduler / `WeeklyAnmvImportScheduleProvider` — `composer require symfony/scheduler`, `scheduler.yaml` (weekly job activated once download is ready)
- Scoped HTTP client `anmv` in `framework.yaml` (needed only for download)
- `ImportAnmvFromDataGouvCommand` (needed only for HTTP download)
- Foreign sources: EMA UPD, Swissmedic, MHRA, AEMPS, BfArM, FDA-CVM (stubs created, not implemented)
- Magistral preparations (handled by Catalog/Article with `marketingAuthorizationRef=null`)
- Commercial supplier catalogue (Procurement, join by GTIN)
- Pharmacovigilance / adverse event reporting (future BC)
- CE-marked medical devices (future aggregate in same BC)
- Luhn check on GTIN (some old ANMV GTINs fail it; accept as-is)
- Redis cache for `PharmaceuticalRefView` (FULLTEXT SQL sufficient at MVP scale)
- Dedicated outbox pattern (align with existing `doctrine_transaction` middleware stack)
- Snapshot cleanup command (>30 days, deferred)
- US FDA-CVM support

---

## Context for Development

### Codebase Patterns

**Naming Strategy (critical):**
`BoundedContextPrefixNamingStrategy` (`src/Shared/Infrastructure/Persistence/Doctrine/Mapping/`) auto-generates table names from the entity FQCN:
1. Extracts BC name from `App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\`
2. Snake-cases it: `pharmaceutical_registry`
3. Applies inner strategy (UnderscoreNamingStrategy) to class name
4. Strips `_entity` suffix, pluralizes
5. Produces: `pharmaceutical_registry__{plural}` — e.g. `AuthorizationEntity` → `pharmaceutical_registry__authorizations`

**NEVER** add `#[ORM\Table(name: '...')]`. Use bare `#[ORM\Table]` or omit entirely (cf. `ClinicEntity`).

**Domain Event publishing:**
```php
// Handler pattern — always publish AFTER repository save
$this->clinicRepository->save($clinic);
$this->domainEventPublisher->publish($clinic);
```
`DomainEventPublisher` flushes accumulated events to `messenger.bus.event`. `DomainEventInterface` → sync; `IntegrationEventInterface` → async.

**Event class structure:**
```php
final readonly class MarketingAuthorizationWithdrawn extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $marketingAuthorizationId,
        public string $effectiveDate,
    ) {}

    public function aggregateId(): string { return $this->marketingAuthorizationId; }
    public function payload(): array { return ['marketingAuthorizationId' => ..., ...]; }
}
```

**ID pattern:**
```php
// extend AbstractUuidId — exposes toString(), equals()
final class MarketingAuthorizationId extends AbstractUuidId {}
// use fromString() NOT new; toString() NOT value()
$id = MarketingAuthorizationId::fromString($this->uuidGenerator->generate());
```

**Aggregate Root:**
```php
class MarketingAuthorization extends AggregateRoot
{
    // Named constructors only
    public static function create(...): self { /* recordDomainEvent(...) */ }
    public static function reconstitute(...): self { /* no events */ }
}
```

**Doctrine mapper:**
```php
final readonly class MarketingAuthorizationMapper
{
    public function toDomain(AuthorizationEntity $e, array $presentations, ...): MarketingAuthorization
    {
        return MarketingAuthorization::reconstitute(
            id: MarketingAuthorizationId::fromString($e->getId()->toString()),
            ...
        );
    }
    public function toEntity(MarketingAuthorization $ma): AuthorizationEntity { ... }
}
```

**Foundry v2 factory:**
```php
/** @extends PersistentProxyObjectFactory<AuthorizationEntity> */
final class AuthorizationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string { return AuthorizationEntity::class; }
    protected function defaults(): array|callable { return [...]; }
}
```

### Files to Reference

| File | Purpose |
|------|---------|
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php` | Doctrine entity pattern with `#[ORM\Table]`, UuidType |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/ClinicMapper.php` | `final readonly` mapper, `toDomain/toEntity` |
| `src/Context/Clinic/Application/Command/Clinic/CreateClinic/CreateClinicHandler.php` | Handler with `DomainEventPublisher::publish` |
| `src/Shared/Domain/Event/AbstractDomainEvent.php` | Base for internal events |
| `src/Shared/Domain/Event/AbstractIntegrationEvent.php` | Base for cross-BC events |
| `src/Context/Patient/Domain/Event/PatientLinkedToAnimalIntegrationEvent.php` | Concrete IntegrationEvent example |
| `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php` | Table naming logic |
| `src/System/Taxation/Infrastructure/Persistence/Doctrine/Entity/TaxRateEntity.php` | System BC entity example |
| `fixtures/System/Taxation/Factory/TaxRateEntityFactory.php` | Foundry v2 factory pattern |
| `fixtures/System/Translation/Story/RegulatoryTranslationStory.php` | Story pattern |
| `config/packages/doctrine.yaml` | Mapping config to extend |
| `config/packages/doctrine_migrations.yaml` | Migration paths to extend |
| `_bmad-output/implementation-artifacts/tech-spec-system-money-bc.md` | Sister BC for patterns |

### Technical Decisions

1. **`AuthorizationEntity` ≠ `MarketingAuthorizationEntity`** — Short Doctrine entity name for readable table. Domain speaks `MarketingAuthorization`. Bridge: `MarketingAuthorizationMapper`.

2. **`JurisdictionCode` is a BC-internal VO** — `Shared\CountryCode` does not accept `"EU"` (not a country). `JurisdictionCode` regex `^[A-Z]{2,3}$` covers FR, EU, CH, UK, ES, DE, US. Document in README. Adapters in consuming BCs handle mapping to CountryCode when needed.

3. **`TargetSpeciesCode` is a BC-internal VO** — `Animal\Domain\ValueObject\Species` (DOG/CAT/NAC/OTHER) is too coarse for a regulatory reference (2327 ANMV species codes). Cross-BC alignment done in `Catalog/Infrastructure/Adapter/PharmaceuticalRegistry/`.

4. **XMLReader streaming** — ANMV XML is ~53 MB. SimpleXML would OOM. `AnmvXmlParser` uses `XMLReader`, yields DTOs one by one.

5. **No dedicated outbox** — Align with `doctrine_transaction` middleware + `DomainEventPublisher` existing stack. If event volume becomes a bottleneck, that is a `Shared` story, not a `PharmaceuticalRegistry` story.

6. **`ImportSnapshot` does NOT auto-dispatch `CalculateSnapshotDiff`** — Allows isolated use (debug, CLI with `--file=...`). Only `RunFullImportCycle` chains the 3 steps.

7. **`SearchMarketingAuthorizations` does NOT use `SearchProviderInterface`** — That interface is tenant-scoped (requires `clinicId` + `userId`). This is a public global registry; use direct SQL FULLTEXT on `DoctrineMarketingAuthorizationSearchRepository`.

8. **`MarketingAuthorizationBlueprint` lives in `Domain/`** — It is a `final readonly class` with no logic; a pure data structure carrying universally-mapped fields (universal status, universal routes, etc.). It lives in `Domain/` because the aggregate's public API depends on it (`create()`, `updateFromImport()`). It is produced by `AnmvCodeMapper` (Infrastructure) — this is the exact ACL seam. Placing it in `Infrastructure/` would make the domain depend on infra; placing it in `Application/` would add an unnecessary layer. It goes in `Domain/` alongside `RegistryDiffCalculator`.

9. **`MarketingAuthorizationHashProjection` replaces `findAllBySource()` full-load** — Loading 14 000 full `MarketingAuthorization` aggregates (with presentations, compositions, usages) just to compare two fields for the diff is prohibitively expensive. `MarketingAuthorizationHashProjection` is a 2-field `readonly class` (`string $authorityIdentifier`, `ContentHash $contentHash`). `findProjectionsBySource(ImportSource): iterable` fetches only those two columns via a lightweight Doctrine query. `RegistryDiffCalculator` accepts `iterable` of this projection.

10. **Bootstrap vs. weekly batching strategy** — The weekly import applies only a diff (a few dozen products). The initial bootstrap imports all ~14 000 products at once — these cannot share the same transaction strategy. The regular `ApplySnapshotDiffHandler` stays simple (doctrine_transaction middleware handles the transaction). The bootstrap gets a dedicated console command (`BootstrapAnmvImportCommand`) that manages its own transaction-per-batch (500 products), bypasses the middleware transaction, and explicitly calls `em->flush(); em->clear()` per batch. This keeps the hot path (weekly import) clean and the one-time bootstrap manageable.

11. **ANMV species mapping is a versioned resource file** — `AnmvCodeMapper::mapTargetSpecies()` cannot rely on a 2327-entry `match()` expression embedded in PHP (unmaintainable, impossible to review). The mapping is extracted once from the real ANMV dictionary XML into a versioned PHP array file at `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/Resources/anmv-species-mapping.php`, committed to the repo, and loaded once by `AnmvCodeMapper`. Adding a new species code becomes a single-line change in that file.

12. **MVP import is file-based only** — `ImportSnapshot` requires a non-null `$filePath`. HTTP download (`AnmvDownloadService`, `RegistryDownloaderInterface`) is deferred pending a story that also delivers the scheduler and the HTTP client config. The first real ANMV import is done manually: download the XML locally, run `app:pharmaceutical-registry:bootstrap --file=path/to/file.xml --dictionary=path/to/dict.xml`.

13. **`BOUNDED_CONTEXT = 'pharmaceutical_registry'`** — All domain events use this constant.

---

## Implementation Plan

### Tasks

Tasks are ordered strictly by dependency. All Phase 0 config tasks must complete before any PHP code. All Domain tasks before Application. All Application before Infrastructure implementation. Fixtures and tests can be written alongside their subject layers but verified at the end.

---

#### Phase 0 — Infrastructure Bootstrap

- [ ] **T1: Add PharmaceuticalRegistry Doctrine mapping to `config/packages/doctrine.yaml`**
  - File: `config/packages/doctrine.yaml`
  - Action: Add under `doctrine: orm: mappings:`:
    ```yaml
    PharmaceuticalRegistry:
        type: attribute
        is_bundle: false
        dir: '%kernel.project_dir%/src/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Entity'
        prefix: 'App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity'
        alias: PharmaceuticalRegistry
    ```

- [ ] **T2: Add PharmaceuticalRegistry migrations path to `config/packages/doctrine_migrations.yaml`**
  - File: `config/packages/doctrine_migrations.yaml`
  - Action: Insert alphabetically under `migrations_paths:` (after `Patient`, before `Regulatory`):
    ```yaml
    'DoctrineMigrations\PharmaceuticalRegistry': '%kernel.project_dir%/migrations/PharmaceuticalRegistry'
    ```

- [ ] **T3: Create `migrations/PharmaceuticalRegistry/` directory**
  - Action: Create empty directory with `.gitkeep` until migration is generated

- [ ] **T4: Create future-source stub directories**
  - Files: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/Europe/.gitkeep`, `Switzerland/.gitkeep`, `UnitedKingdom/.gitkeep`, `Spain/.gitkeep`, `Germany/.gitkeep`

---

#### Phase 1a — Domain Value Objects

- [ ] **T5: ID value objects**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/MarketingAuthorizationId.php`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ActiveSubstanceId.php`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PresentationId.php`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/SnapshotId.php`
  - Action: Each `final class` extends `App\Shared\Domain\Identifier\AbstractUuidId`. Inherited methods: `toString()`, `fromString()`, `equals()`.

- [ ] **T6: String VOs — names and labels**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/CommercialName.php` — non-empty string, max 255, `private __construct`, `fromString(string): self`, `toString(): string`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/HolderLaboratory.php` — same pattern
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PharmaceuticalForm.php` — string max 128, `fromString/toString`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PresentationDescription.php` — non-empty, max 255
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/UnitCount.php` — string libre max 20 (ANMV mixes int and "1 g"), `fromString/toString`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/Packaging.php` — string libre max 255

- [ ] **T7: Regex-validated VOs**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/JurisdictionCode.php`
      - Regex `^[A-Z]{2,3}$` — accepts FR, EU, CH, UK, ES, DE, US
      - `throw InvalidJurisdictionCodeException` on invalid
      - `toString(): string`, `equals(self $other): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/Gtin.php`
      - Regex `^\d{8,14}$`, `throw InvalidGtinException`
      - `toString(): string`, `equals(self $other): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PermanentIdentifier.php`
      - Regex `^\d{12}$`, `toString(): string`, `equals(self $other): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/AtcVetCode.php`
      - Regex `^Q[A-Z]\d{2}[A-Z\d]{0,4}$`, `throw InvalidAtcVetCodeException`
      - `toString(): string`
      - `mainGroup(): string` → first 2 chars (e.g. `"QD"`)
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/EuPackIdentifier.php`
      - UUID format, `private __construct`, `fromString(string): self`, `toString(): string`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/TargetSpeciesCode.php`
      - Regex `^[a-z][a-z0-9_]{1,32}$`
      - `private __construct`, `fromString(string): self`, `toString(): string`
      - Slug examples: `"dog"`, `"cat"`, `"cattle"`, `"honeybee"`, etc.

- [ ] **T8: `MarketingAuthorizationDate` VO**
  - File: `src/System/PharmaceuticalRegistry/Domain/ValueObject/MarketingAuthorizationDate.php`
  - Wraps `\DateTimeImmutable`, `fromDateTime(\DateTimeImmutable): self`, `toDateTime(): \DateTimeImmutable`, `toString(): string` (ISO 8601 date)

- [ ] **T9: `JurisdictionalIdentifier` VO**
  - File: `src/System/PharmaceuticalRegistry/Domain/ValueObject/JurisdictionalIdentifier.php`
  - Fields: `JurisdictionCode $jurisdictionCode`, `string $authorityCode`, `string $authorityIdentifier`
  - `authorityCode` regex `^[A-Z_]{3,16}$`; `authorityIdentifier` non-empty max 64
  - Factories: `of(JurisdictionCode, string, string): self`, `anmv(string $number): self` (→ JurisdictionCode::fromString('FR'), 'ANMV', $number), `ema(string $number): self` (→ EU, EMA)
  - `equals(self $other): bool`

- [ ] **T10: `WithdrawalPeriod` VO and `WithdrawalPeriodUnit` enum**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/WithdrawalPeriodUnit.php`
      - Standalone file (PHP does not allow nested enums): `enum WithdrawalPeriodUnit: string { case DAY = 'DAY'; case HOUR = 'HOUR'; }`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/WithdrawalPeriod.php`
      - Fields: `int $value` (> 0), `WithdrawalPeriodUnit $unit`
      - Factories: `days(int $value): self`, `hours(int $value): self`
      - `toString(): string` — **technical representation**: `"28:DAY"` / `"12:HOUR"` (invariant, serializable). French rendering (`"28 jours"`) belongs in a Twig filter or presentation service — not in the domain VO.
      - `equals(self $other): bool`

- [ ] **T11: Prescription VOs**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PrescriptionRetention.php` — `int $durationYears`, `ofYears(int): self`, `equals(self): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/JurisdictionalPrescriptionCode.php` — `JurisdictionCode $jurisdictionCode`, `string $code`, `equals(self): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PrescriptionRequirement.php`
      - Fields: `bool $isRequired`, `PrescriptionClass $prescriptionClass`, `?PrescriptionRetention $retention`, `?JurisdictionalPrescriptionCode $jurisdictionalCode`
      - Factories: `none(): self`, `rx(PrescriptionClass, JurisdictionalPrescriptionCode): self`, `rxWithRetention(PrescriptionClass, JurisdictionalPrescriptionCode, int $years): self`, `narcotic(JurisdictionalPrescriptionCode): self` — hardcodes `PrescriptionClass::RX_NARCOTIC` (semantic intent; document in docblock)
      - `equals(self): bool`

- [ ] **T12: `ControlledSubstance` VO and `JurisdictionalControlCode`**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/JurisdictionalControlCode.php` — `JurisdictionCode $jurisdictionCode`, `string $code`, `equals(self): bool`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ControlledSubstance.php` — `ControlledSubstanceClass $class`, `JurisdictionalControlCode $jurisdictionalCode`, `?string $restrictions`

- [ ] **T13: `ContentHash` VO**
  - File: `src/System/PharmaceuticalRegistry/Domain/ValueObject/ContentHash.php`
  - `string $value` (sha-256 hex, 64 chars)
  - Factory `of(array $dto): self`:
    1. `ksortRecursive($dto)` — recursive key sort for determinism
    2. `json_encode($dto, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)`
    3. `hash('sha256', $json)`
  - `equals(self $other): bool`
  - Helper `private function ksortRecursive(array &$arr): void` — must recurse into nested arrays
  - **Contract:** `$dto` must be a pure PHP array of scalars and nested arrays — **no PHP floats, no objects, no resources**. All numeric values must be strings (`"7.2"` not `7.2`). PHP's `json_encode` of a float is platform/locale-dependent in edge cases, breaking hash determinism across environments. `AnmvMedicinalProductDto::toArray()` must cast every numeric field to `(string)` before returning.

- [ ] **T14: Summary VOs**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/SummarySection.php` — `int $titleCode`, `string $titleLabel`, `string $content`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/SummaryLink.php` — `string $url` (max 512, regex `^https?://`), `fromString(string): self`, `toString(): string`

- [ ] **T15: `ImportResult` VO**
  - File: `src/System/PharmaceuticalRegistry/Domain/ValueObject/ImportResult.php`
  - Fields: `int $created`, `int $updated`, `int $withdrawn`, `int $skipped`
  - Factory `of(int, int, int, int): self`

- [ ] **T16: Remaining scalar VOs**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/Concentration.php` — string value (free-form, e.g. "7.2 mg/mL")
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/DosageUnit.php` — string max 32

---

#### Phase 1b — Domain Enums

- [ ] **T17: Status and classification enums**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/MarketingAuthorizationStatus.php`
      ```php
      enum MarketingAuthorizationStatus: string {
          case UNDER_REVIEW = 'UNDER_REVIEW';
          case ACTIVE = 'ACTIVE';
          case EXCEPTIONAL_CIRCUMSTANCES = 'EXCEPTIONAL_CIRCUMSTANCES';
          case UNLIMITED = 'UNLIMITED';
          case WITHDRAWN = 'WITHDRAWN';
          case WITHDRAWN_WITH_DEROGATION = 'WITHDRAWN_WITH_DEROGATION';
          case SUSPENDED = 'SUSPENDED';
          case REFUSED = 'REFUSED';
          case ABANDONED = 'ABANDONED';
          case LAPSED = 'LAPSED';
          public function isMarketable(): bool {
              return match($this) {
                  self::ACTIVE, self::EXCEPTIONAL_CIRCUMSTANCES, self::UNLIMITED => true,
                  default => false,
              };
          }
      }
      ```
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ProductNature.php` — `CHEMICAL`, `IMMUNOLOGICAL`, `HOMEOPATHIC`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/PrescriptionClass.php` — `NONE`, `RX`, `RX_CONTROLLED`, `RX_NARCOTIC`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ControlledSubstanceClass.php` — `NARCOTIC_I`, `NARCOTIC_II`, `PSYCHOTROPIC`, `ANABOLIC`, `OTHER_CONTROLLED`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ImportSource.php` — `ANMV`, `EMA_UPD`, `SWISSMEDIC`, `MHRA`, `AEMPS`, `BFARM`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/ImportStatus.php` — `PENDING`, `RUNNING`, `DIFFED`, `APPLYING`, `APPLIED`, `FAILED`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/DiffKind.php` — `CREATE`, `UPDATE`, `WITHDRAW`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/FoodProductionPurpose.php` — `MUSCLE_SKIN`, `LIVER`, `KIDNEY`, `FAT`, `FAT_WITH_SKIN`, `EGGS`, `HONEY`, `MILK`, `ALL_FOOD_PRODUCTS`, `NOT_APPLICABLE`
    - `src/System/PharmaceuticalRegistry/Domain/ValueObject/AdministrationRoute.php` — all 49 cases: `AURICULAR`, `CUTANEOUS`, `IN_OVO`, `INTRA_ARTICULAR`, `INTRACARDIAC`, `INTRADERMAL`, `INTRAMAMMARY`, `INTRAMUSCULAR`, `INTRANASAL`, `INTRAPERITONEAL`, `INTRARUMINAL`, `INTRAUTERINE`, `INTRAVENOUS`, `ORAL`, `OPHTHALMIC`, `OROMUCOSAL`, `RECTAL`, `SUBCUTANEOUS`, `TOPICAL`, `TRANSDERMAL`, `INHALATION`, `INTRAOSSEOUS`, `INTRATRACHEAL`, `INTRAVAGINAL`, `INTRAVESICAL`, `INTRACORONARY`, `INTRAPERICARDIAL`, `INTRAPLURAL`, `INTRASTERNAL`, `INTRACYSTIC`, `INTRACOLONIC`, `INTRAGASTRIC`, `INTROINTESTINAL`, `INTRALYMPHATIC`, `INTRATUMORAL`, `INTRAOCULAR`, `INTRABURSAL`, `EPIDURAL`, `PERINEURAL`, `PERIODONTAL`, `INTRASINUSAL`, `ENDOCERVICAL`, `ENDOSINUSAL`, `ENDOTRACHEAL`, `ENDOCARDIAL`, `INTRAABDOMINAL`, `INTRAHEPATIC`, `INTRARENAL`, `OTHER` (verify against real AnmvCodeMapper mapping table — add any missing to reach exactly 49)

---

#### Phase 1c — Domain Exceptions

- [ ] **T18: Domain exceptions**
  - All files in `src/System/PharmaceuticalRegistry/Domain/Exception/`:
    - `UnknownMarketingAuthorizationException.php`
    - `UnknownActiveSubstanceException.php`
    - `DuplicateJurisdictionalIdException.php`
    - `InvalidGtinException.php`
    - `InvalidAtcVetCodeException.php`
    - `InvalidJurisdictionCodeException.php`
    - `UnknownImportSourceCodeException.php`
    - `UnknownAnmvCodeException.php` — message MUST include the unknown code
    - `SnapshotNotFoundException.php`
    - `InvalidSnapshotStatusTransitionException.php` — message includes from/to status
    - `SnapshotAlreadyAppliedException.php`
  - Pattern: `final class Foo extends \DomainException {}` (or `\RuntimeException` for infrastructure-triggered ones). Keep consistent with existing exceptions in Taxation BC.

---

#### Phase 1d — Domain Child Entities

- [ ] **T19: `Presentation` child entity**
  - File: `src/System/PharmaceuticalRegistry/Domain/Entity/Presentation.php`
  - Fields: `PresentationId $id`, `PresentationDescription $description`, `?UnitCount $unitCount`, `?Packaging $packaging`, `?Gtin $gtin`, `?EuPackIdentifier $euPackIdentifier`, `PrescriptionRequirement $prescriptionRequirement`
  - `create(PresentationId, PresentationDescription, ...)` factory
  - `equals(self): bool` — compare by `PresentationId`

- [ ] **T20: `Composition` child entity**
  - File: `src/System/PharmaceuticalRegistry/Domain/Entity/Composition.php`
  - Fields: `ActiveSubstanceId $activeSubstanceId`, `?string $quantityValue`, `?string $quantityUnitLabel`, `?string $quantityUnitCode`, `bool $isExcipient`
  - Invariant: if `$quantityValue` present, `bccomp($quantityValue, '0') >= 0` (use bcmath)
  - `of(ActiveSubstanceId, ...)` factory

- [ ] **T21: `TargetUsage` child entity**
  - File: `src/System/PharmaceuticalRegistry/Domain/Entity/TargetUsage.php`
  - Fields: `AdministrationRoute $administrationRoute`, `TargetSpeciesCode $targetSpeciesCode`, `?FoodProductionPurpose $foodProductionPurpose`, `?WithdrawalPeriod $withdrawalPeriod`, `?string $jurisdictionalNote`
  - `of(...)` factory

- [ ] **T22: `Summary` child entity**
  - File: `src/System/PharmaceuticalRegistry/Domain/Entity/Summary.php`
  - Fields: `SummarySection[] $sections`, `?SummaryLink $sourceUrl`, `?\DateTimeImmutable $lastUpdatedAt`
  - `of(SummarySection[], ?SummaryLink, ?\DateTimeImmutable): self` factory

- [ ] **T23: `SnapshotEntry` and `DiffEntry` child entities**
  - Files:
    - `src/System/PharmaceuticalRegistry/Domain/Entity/SnapshotEntry.php` — `string $authorityIdentifier`, `ContentHash $contentHash`, `array $rawDto`
    - `src/System/PharmaceuticalRegistry/Domain/Entity/DiffEntry.php` — `string $authorityIdentifier`, `DiffKind $diffKind`, `?MarketingAuthorizationId $targetUuid`, `?array $changes`

---

#### Phase 1e — Domain Events

- [ ] **T24: Domain-internal events (extend `AbstractDomainEvent`)**
  - Files in `src/System/PharmaceuticalRegistry/Domain/Event/`:
    - `MarketingAuthorizationCreated.php` — payload: `marketingAuthorizationId`, `commercialName`, `source`
    - `TargetSpeciesAdded.php` — payload: `marketingAuthorizationId`, `speciesCode`, `route`
    - `SnapshotStarted.php` — payload: `snapshotId`, `source`
    - `SnapshotDiffed.php` — payload: `snapshotId`, `createCount`, `updateCount`, `withdrawCount`
    - `SnapshotApplied.php` — payload: `snapshotId`, `created`, `updated`, `withdrawn`, `skipped`
    - `SnapshotFailed.php` — payload: `snapshotId`, `errorMessage`
  - All have `BOUNDED_CONTEXT = 'pharmaceutical_registry'`, `VERSION = 1`
  - **`name()` override required** for events whose class name has a multi-word "aggregate" segment: `AbstractEvent::name()` infers the aggregate from everything except the last CamelCase word, producing wrong names for e.g. `SnapshotDiffed` (→ aggregate: `Snapshot`, correct) but unintended ones for complex names. Verify the auto-inferred names are correct; override `name(): string` explicitly for any event where the inferred name would be misleading or collide.

- [ ] **T25: Cross-BC integration events (extend `AbstractIntegrationEvent`)**
  - Files in `src/System/PharmaceuticalRegistry/Domain/Event/`:
    - `MarketingAuthorizationUpdated.php` — payload: `marketingAuthorizationId`, `changedFields[]`
    - `MarketingAuthorizationWithdrawn.php` — payload: `marketingAuthorizationId`, `effectiveDate`
    - `MarketingAuthorizationSuspended.php` — payload: `marketingAuthorizationId`
    - `MarketingAuthorizationStatusChanged.php` — payload: `marketingAuthorizationId`, `previousStatus`, `newStatus`
    - `PresentationAdded.php` — payload: `marketingAuthorizationId`, `presentationId`, `gtin`
    - `PresentationRemoved.php` — payload: `marketingAuthorizationId`, `presentationId`
    - `PresentationGtinChanged.php` — payload: `presentationId`, `previousGtin`, `newGtin`
    - `WithdrawalPeriodChanged.php` — payload: `marketingAuthorizationId`, `targetSpeciesCode`, `route`, `newPeriod`
    - `PrescriptionRequirementChanged.php` — payload: `presentationId`, `marketingAuthorizationId`, `newClass`
    - `ControlledSubstanceClassificationChanged.php` — payload: `marketingAuthorizationId`, `newClass`
  - All have `BOUNDED_CONTEXT = 'pharmaceutical_registry'`, `VERSION = 1`
  - **`name()` override required** for events with complex class names where auto-inference produces a wrong aggregate segment: `MarketingAuthorizationStatusChanged` → inferred aggregate: `MarketingAuthorizationStatus` (wrong); `ControlledSubstanceClassificationChanged` → inferred aggregate: `ControlledSubstanceClassification` (wrong); `PresentationGtinChanged`, `WithdrawalPeriodChanged`, `PrescriptionRequirementChanged` — verify each. Override `name(): string` explicitly for any that infer incorrectly, returning a canonical name like `'pharmaceutical_registry.marketing-authorization.status-changed.v1'`.

---

#### Phase 1f — Domain Repository Interfaces and Projections

- [ ] **T26: Domain write repository interfaces**
  - Files in `src/System/PharmaceuticalRegistry/Domain/Repository/`:
    - `MarketingAuthorizationRepositoryInterface.php`
      ```php
      interface MarketingAuthorizationRepositoryInterface {
          public function save(MarketingAuthorization $ma): void;
          public function findById(MarketingAuthorizationId $id): ?MarketingAuthorization;
          public function findByJurisdictionalId(string $jurisdictionCode, string $authorityCode, string $authorityIdentifier): ?MarketingAuthorization;
          /** @return iterable<MarketingAuthorizationHashProjection> */
          public function findProjectionsBySource(ImportSource $source): iterable;
      }
      ```
    - `ActiveSubstanceRepositoryInterface.php`
      ```php
      interface ActiveSubstanceRepositoryInterface {
          public function save(ActiveSubstance $substance): void;
          public function findByNormalizedLabel(string $normalizedLabel): ?ActiveSubstance;
      }
      ```
    - `SnapshotRepositoryInterface.php`
      ```php
      interface SnapshotRepositoryInterface {
          public function save(Snapshot $snapshot): void;
          public function findById(SnapshotId $id): ?Snapshot;
          public function findRecent(ImportSource $source, int $limit): array;
          /** @return iterable<SnapshotEntry> — streams entries without loading them into the Snapshot aggregate; used by CalculateSnapshotDiffHandler to avoid OOMing on 14k entries */
          public function streamEntriesForDiff(SnapshotId $id): iterable;
      }
      ```

- [ ] **T27: `MarketingAuthorizationHashProjection` DTO**
  - File: `src/System/PharmaceuticalRegistry/Domain/Repository/MarketingAuthorizationHashProjection.php`
  - `final readonly class MarketingAuthorizationHashProjection`
  - Fields: `string $authorityIdentifier`, `ContentHash $contentHash`, `MarketingAuthorizationId $id`
  - `__construct(string $authorityIdentifier, ContentHash $contentHash, MarketingAuthorizationId $id)`
  - This is the only data shape `RegistryDiffCalculator` needs from the current state — never load full aggregates for diff calculation.

---

#### Phase 1g — Domain Aggregates

- [ ] **T28: `ActiveSubstance` aggregate**
  - File: `src/System/PharmaceuticalRegistry/Domain/ActiveSubstance.php`
  - Fields: `ActiveSubstanceId $id`, `string $label`, `string $labelNormalized`, `?string $innCode`, `\DateTimeImmutable $createdAt`, `\DateTimeImmutable $updatedAt`
  - `create(ActiveSubstanceId, string $label, ?string $innCode, \DateTimeImmutable): self` — no event at MVP
  - `reconstitute(...)` — no event
  - `normalizeLabel(string $rawLabel): string` (static) — lowercase + trim + collapse whitespace (`preg_replace('/\s+/', ' ', mb_strtolower(trim($rawLabel)))`)

- [ ] **T29: `MarketingAuthorization` aggregate**
  - File: `src/System/PharmaceuticalRegistry/Domain/MarketingAuthorization.php`
  - Named constructors:
    - `create(MarketingAuthorizationId, CommercialName, HolderLaboratory, MarketingAuthorizationStatus, MarketingAuthorizationDate, ProductNature, PharmaceuticalForm, ?AtcVetCode, ?PermanentIdentifier, ?ControlledSubstance, JurisdictionalIdentifier[] $identifiers, Presentation[] $initialPresentations, Composition[] $compositions, TargetUsage[] $targetUsages, ?Summary $summary, ImportSource, \DateTimeImmutable $now): self`
      → records `MarketingAuthorizationCreated` + one `PresentationAdded` per initial presentation
    - `reconstitute(...)` — no events
  - Methods (all record events per original spec):
    - `addJurisdictionalIdentifier(JurisdictionalIdentifier): void` — throw `DuplicateJurisdictionalIdException`
    - `addPresentation(Presentation, \DateTimeImmutable): void` — refuse if WITHDRAWN, emit `PresentationAdded`
    - `removePresentation(PresentationId, \DateTimeImmutable): void` — emit `PresentationRemoved`
    - `updatePresentation(PresentationId, PresentationDescription, ?Gtin, PrescriptionRequirement, \DateTimeImmutable): void` — emit `PresentationGtinChanged` if GTIN changed
    - `withdraw(\DateTimeImmutable): void` — idempotent, emit `MarketingAuthorizationWithdrawn` + `MarketingAuthorizationStatusChanged`
    - `suspend(\DateTimeImmutable): void` — emit `MarketingAuthorizationStatusChanged` + `MarketingAuthorizationSuspended`
    - `reinstate(\DateTimeImmutable): void` — emit `MarketingAuthorizationStatusChanged`
    - `updateControlledSubstance(?ControlledSubstance, \DateTimeImmutable): void` — emit `ControlledSubstanceClassificationChanged` if changed
    - `updateTargetUsages(TargetUsage[], \DateTimeImmutable): void` — diff; emit `WithdrawalPeriodChanged` / `TargetSpeciesAdded` per delta
    - `updatePrescriptionOn(PresentationId, PrescriptionRequirement, \DateTimeImmutable): void` — emit `PrescriptionRequirementChanged` if different
    - `updateFromImport(MarketingAuthorizationBlueprint $blueprint, ImportSource, \DateTimeImmutable): void` — smart field-level diff, emit only relevant events + `MarketingAuthorizationUpdated` wrapper

- [ ] **T30: `Snapshot` aggregate**
  - File: `src/System/PharmaceuticalRegistry/Domain/Snapshot.php`
  - Named constructors: `create(SnapshotId, ImportSource, \DateTimeImmutable): self` → records `SnapshotStarted`; `reconstitute(...)` → no events
  - Methods:
    - `markAsRunning(\DateTimeImmutable): void` — PENDING → RUNNING, records no event (internal lifecycle step); called by `ImportSnapshotHandler` before delegating to the importer
    - `addEntry(string $authorityIdentifier, ContentHash, array $rawDto): void` — guard: throw if status ≥ DIFFED
    - `markAsDiffed(DiffEntry[] $entries): void` — RUNNING → DIFFED, records `SnapshotDiffed`; throw `InvalidSnapshotStatusTransitionException` if invalid
    - `markAsApplied(ImportResult, \DateTimeImmutable): void` — DIFFED/APPLYING → APPLIED, records `SnapshotApplied`; throw `SnapshotAlreadyAppliedException` if already APPLIED
    - `markAsFailed(string $error, \DateTimeImmutable): void` — any → FAILED, records `SnapshotFailed`

---

#### Phase 1h — Domain Service and Blueprint

- [ ] **T31: `MarketingAuthorizationBlueprint` DTO**
  - File: `src/System/PharmaceuticalRegistry/Domain/MarketingAuthorizationBlueprint.php`
  - `final readonly class MarketingAuthorizationBlueprint` — no methods, no logic; pure data carrier
  - This DTO materialises the ACL boundary: `AnmvCodeMapper` (infra) produces it; `MarketingAuthorization::create()` and `::updateFromImport()` consume it
  - Fields (all using universal domain VOs — no ANMV-specific codes):
    - `CommercialName $commercialName`
    - `HolderLaboratory $holderLaboratory`
    - `MarketingAuthorizationStatus $status`
    - `MarketingAuthorizationDate $authorizationDate`
    - `ProductNature $nature`
    - `PharmaceuticalForm $pharmaceuticalForm`
    - `?AtcVetCode $atcVetCode`
    - `?PermanentIdentifier $permanentIdentifier`
    - `?ControlledSubstance $controlledSubstance`
    - `JurisdictionalIdentifier[] $jurisdictionalIdentifiers`
    - `PresentationBlueprint[] $presentations` — nested `final readonly class PresentationBlueprint` (colocated) carrying `PresentationDescription`, `?Gtin`, `PrescriptionRequirement`, `?UnitCount`, `?Packaging`, `?EuPackIdentifier`
    - `CompositionBlueprint[] $compositions` — nested `final readonly class CompositionBlueprint` carrying `string $activeSubstanceLabel`, `?string $quantityValue`, `?string $quantityUnitLabel`, `?string $quantityUnitCode`, `bool $isExcipient`
    - `TargetUsage[] $targetUsages` — can use the domain entity directly
    - `?Summary $summary`
    - `ImportSource $source`
    - `ContentHash $contentHash`
  - `PresentationBlueprint` → `src/System/PharmaceuticalRegistry/Domain/PresentationBlueprint.php` (own file — PSR-4 + cs-fixer enforce one class per file)
  - `CompositionBlueprint` → `src/System/PharmaceuticalRegistry/Domain/CompositionBlueprint.php` (own file)

- [ ] **T32: `RegistryDiffCalculator` service**
  - File: `src/System/PharmaceuticalRegistry/Domain/Service/RegistryDiffCalculator.php`
  - Pure PHP, no I/O
  - `calculate(iterable $snapshotEntries, iterable $projections): DiffEntry[]`
    - `$snapshotEntries` is `iterable<SnapshotEntry>` — streamed lazily from `SnapshotRepositoryInterface::streamEntriesForDiff()`, NOT from `$snapshot->entries()` (which would OOM)
    - `$projections` is `iterable<MarketingAuthorizationHashProjection>`
  - Algorithm:
    1. Index projections by `authorityIdentifier` → `[identifier => projection]`
    2. For each `$snapshotEntry` (iterated lazily):
       - absent in projections → `DiffEntry(CREATE, targetUuid: null)`
       - present + `$projection->contentHash->equals($snapshotEntry->contentHash())` → skip
       - present + hash different → `DiffEntry(UPDATE, targetUuid: $projection->id)`
       - Track seen identifiers
    3. For each projection whose identifier was never seen in snapshot entries → `DiffEntry(WITHDRAW, targetUuid: $projection->id)`
    4. Return `DiffEntry[]`

---

#### Phase 2a — Application Ports and Read Models

- [ ] **T33: Application ports**
  - Files in `src/System/PharmaceuticalRegistry/Application/Port/`:
    - `RegistryImporterInterface.php` — `stage(Snapshot $snapshot, string $filePath, string $dictionaryPath): void` (both paths required at MVP — no download)
    - `BlueprintBuilderInterface.php` — `buildBlueprint(array $rawDto, ImportSource $source): MarketingAuthorizationBlueprint`
      - Application port that decouples `ApplySnapshotDiffHandler` from `AnmvCodeMapper` (Infrastructure)
      - `AnmvBlueprintBuilder` in `Infrastructure/ImportSources/France/` implements it for `ImportSource::ANMV`
      - The `$rawDto` must be self-contained (contain resolved labels, not raw ANMV IDs) so no dictionary is needed at apply time — the dictionary resolves IDs to labels at parse time (in `AnmvXmlParser`), and rawDto stores the resolved data
    - `PharmaceuticalRefReadRepositoryInterface.php`
      - `findById(MarketingAuthorizationId): ?PharmaceuticalRefView`
      - `findByGtin(Gtin): ?PharmaceuticalRefView`
      - `findByJurisdictionalId(string $jurisdiction, string $authority, string $identifier): ?PharmaceuticalRefView`
    - `MarketingAuthorizationSearchRepositoryInterface.php`
      - `search(string $term, int $limit, array $filters = []): MarketingAuthorizationSearchResult[]`
      - `listBySubstance(ActiveSubstanceId): MarketingAuthorizationSearchResult[]`
      - `listByAtcVet(string $codeOrGroup): MarketingAuthorizationSearchResult[]`
      - `listByTargetSpecies(TargetSpeciesCode): MarketingAuthorizationSearchResult[]`

- [ ] **T34: Application read model**
  - File: `src/System/PharmaceuticalRegistry/Application/ReadModel/PharmaceuticalRefView.php`
  - `readonly class` with fields: `string $id`, `string $commercialName`, `string $holderLaboratory`, `string $status`, `?string $atcVetCode`, `?string $permanentIdentifier`, `array $presentations`, `array $activeSubstanceLabels`, `string $pharmaceuticalForm`, `string $lastImportSource`, `\DateTimeImmutable $lastImportedAt`

---

#### Phase 2b — Application Commands

- [ ] **T35: `ImportSnapshot` command + handler**
  - Files in `src/System/PharmaceuticalRegistry/Application/Command/ImportSnapshot/`:
    - `ImportSnapshot.php` — implements `CommandInterface`; properties: `string $source`, `string $filePath`, `string $dictionaryPath` (both required — MVP is file-based only)
    - `ImportSnapshotHandler.php` — `#[AsMessageHandler]`
      1. Create `SnapshotId` via `UuidGeneratorInterface`
      2. `Snapshot::create(...)`, status PENDING
      3. Persist via `SnapshotRepositoryInterface`
      4. `$snapshot->markAsRunning($now)` — transitions PENDING → RUNNING; persist again
      5. Resolve `RegistryImporterInterface` for the source
      6. Call `$importer->stage($snapshot, $command->filePath, $command->dictionaryPath)`
      7. Does NOT dispatch `CalculateSnapshotDiff` (that's `RunFullImportCycle`'s job)
      8. Returns `string $snapshotId`

- [ ] **T36: `CalculateSnapshotDiff` command + handler**
  - Files in `src/System/PharmaceuticalRegistry/Application/Command/CalculateSnapshotDiff/`:
    - `CalculateSnapshotDiff.php` — `string $snapshotId`
    - `CalculateSnapshotDiffHandler.php` — `#[AsMessageHandler]`
      1. Load `Snapshot` (throw `SnapshotNotFoundException` if absent)
      2. Load `MarketingAuthorizationHashProjection[]` via `findProjectionsBySource($snapshot->source())`
      3. Stream snapshot entries lazily via `SnapshotRepositoryInterface::streamEntriesForDiff($snapshotId)` — do NOT use `$snapshot->entries()` (OOM on 14k entries)
      4. Call `RegistryDiffCalculator::calculate($snapshotEntries, $projections)`
      5. `$snapshot->markAsDiffed($diffEntries)`
      6. Persist snapshot

- [ ] **T37: `ApplySnapshotDiff` command + handler**
  - Files in `src/System/PharmaceuticalRegistry/Application/Command/ApplySnapshotDiff/`:
    - `ApplySnapshotDiff.php` — `string $snapshotId`
    - `ApplySnapshotDiffHandler.php` — `#[AsMessageHandler]` (doctrine_transaction middleware handles transaction — suitable for weekly delta of a few dozen products)
      - Injects: `MarketingAuthorizationRepositoryInterface`, `ActiveSubstanceRepositoryInterface`, `SnapshotRepositoryInterface`, `BlueprintBuilderInterface`, `DomainEventPublisher`, `IntegrationEventPublisher` (check existing pattern from a handler that emits integration events — e.g. `ArchiveClientHandler`)
      1. Load snapshot (for lifecycle management)
      2. `$mutatedAggregates = []`
      3. For each `DiffEntry` (from `$snapshot->diffEntries()`):
         - **CREATE**: `$blueprint = $this->blueprintBuilder->buildBlueprint($entry->rawDto(), $snapshot->source())`. Resolve/create `ActiveSubstance` per `$blueprint->compositions` (lookup by normalizedLabel, create if missing). `MarketingAuthorization::create(...$blueprint...)`. Persist. Add to `$mutatedAggregates`.
         - **UPDATE**: Load `MarketingAuthorization`. `$blueprint = $this->blueprintBuilder->buildBlueprint(...)`. `$ma->updateFromImport($blueprint, ...)`. Persist. Add to `$mutatedAggregates`.
         - **WITHDRAW**: Load `MarketingAuthorization`. `$ma->withdraw($now)`. Persist. Add to `$mutatedAggregates`.
      4. `$snapshot->markAsApplied(ImportResult::of(...))`; persist snapshot
      5. For each in `$mutatedAggregates`:
         - `$this->domainEventPublisher->publish($aggregate)` — dispatches `DomainEventInterface` events
         - `$this->integrationEventPublisher->publish($aggregate)` (or equivalent) — dispatches `IntegrationEventInterface` events async cross-BC
         - **Verify the exact dual-publish pattern against the project's existing integration-event-emitting handlers before implementing**

- [ ] **T38: `RunFullImportCycle` command + service**
  - Files in `src/System/PharmaceuticalRegistry/Application/Command/RunFullImportCycle/`:
    - `RunFullImportCycle.php` — `string $source`, `string $filePath`, `string $dictionaryPath` (data object only, no interface required)
    - `RunFullImportCycleHandler.php` — **NOT `#[AsMessageHandler]`** — plain service, NOT dispatched via the command bus
      - **Why:** dispatching via the command bus would wrap the handler in the `doctrine_transaction` middleware's outer transaction. Any sub-commands dispatched inside would execute as savepoints, and their "commits" would not be visible until the outer transaction commits. This breaks the per-step recovery model (if Apply fails, Import's RUNNING status would be rolled back too).
      - **How:** `FullImportCycleCommand` (console) injects `RunFullImportCycleHandler` directly as a service and calls `->handle(RunFullImportCycle $command): void`
      - Injects: `ImportSnapshotHandler`, `CalculateSnapshotDiffHandler`, `ApplySnapshotDiffHandler` — calls them directly as services (not via bus)
      - Sequential execution: `$importHandler->__invoke(ImportSnapshot(...))` → `$diffHandler->__invoke(CalculateSnapshotDiff($snapshotId))` → `$applyHandler->__invoke(ApplySnapshotDiff($snapshotId))`
      - Each inner `__invoke()` is dispatched via its own handler's `#[AsMessageHandler]` contract, meaning each runs within its own `doctrine_transaction` middleware wrapping — or alternatively, each inner handler manages its own `em->beginTransaction()/commit()` when called directly (verify with the project's middleware chain)
      - On exception: `$snapshot->markAsFailed($e->getMessage())` in a separate try/catch with `em->beginTransaction()/commit()`, rethrow

- [ ] **T39: `ManuallyMarkMarketingAuthorizationWithdrawn` command + handler**
  - Files in `src/System/PharmaceuticalRegistry/Application/Command/ManuallyMarkMarketingAuthorizationWithdrawn/`:
    - `ManuallyMarkMarketingAuthorizationWithdrawn.php` — `string $marketingAuthorizationId`, `string $reason`
    - Handler: load aggregate, `$ma->withdraw($clock->now())`, save, `$domainEventPublisher->publish($ma)`

---

#### Phase 2c — Application Queries

- [ ] **T40: `SearchMarketingAuthorizations` query**
  - Files in `src/System/PharmaceuticalRegistry/Application/Query/SearchMarketingAuthorizations/`:
    - `SearchMarketingAuthorizations.php` — `string $term`, `int $limit = 20`, `array $filters = []`
    - `MarketingAuthorizationSearchResult.php` — `readonly class`: `string $id`, `string $commercialName`, `string $status`, `?string $atcVetCode`, `?string $gtin`, `string $holderLaboratory`
    - `SearchMarketingAuthorizationsHandler.php` — injects `MarketingAuthorizationSearchRepositoryInterface`

- [ ] **T41: `GetMarketingAuthorizationDetail` query**
  - Files in `src/System/PharmaceuticalRegistry/Application/Query/GetMarketingAuthorizationDetail/`:
    - `GetMarketingAuthorizationDetail.php` — `string $marketingAuthorizationId`
    - `MarketingAuthorizationDetailView.php` — `readonly class` with full nested data
    - `GetMarketingAuthorizationDetailHandler.php` — injects `PharmaceuticalRefReadRepositoryInterface`

- [ ] **T42: Remaining queries**
  - Each in their own subfolder under `Application/Query/`:
    - `GetMarketingAuthorizationByGtin/` — `string $gtin` → `?MarketingAuthorizationDetailView`
    - `GetMarketingAuthorizationByJurisdictionalId/` — `string $jurisdictionCode`, `string $authorityCode`, `string $authorityIdentifier` → `?MarketingAuthorizationDetailView`
    - `GetPresentationDetail/` — `string $presentationId` → `?PresentationView`; create `PresentationView.php` colocated
    - `ListMarketingAuthorizationsByActiveSubstance/` — `string $activeSubstanceId` → `MarketingAuthorizationSearchResult[]`
    - `ListMarketingAuthorizationsByAtcVetCode/` — `string $atcVetCode` (partial prefix allowed) → `MarketingAuthorizationSearchResult[]`
    - `ListMarketingAuthorizationsByTargetSpecies/` — `string $targetSpeciesCode` → `MarketingAuthorizationSearchResult[]`
    - `GetWithdrawalPeriodsByMarketingAuthorization/` — `string $marketingAuthorizationId` → `WithdrawalPeriodView[]`; create `WithdrawalPeriodView.php` colocated: `readonly class WithdrawalPeriodView` with fields `string $administrationRoute`, `string $targetSpeciesCode`, `?int $withdrawalPeriodValue`, `?string $withdrawalPeriodUnit`, `?string $foodProductionPurpose`, `?string $jurisdictionalNote` — **not** `TargetUsage[]` (domain entity must not leak through query handlers)
    - `GetImportHistory/` — `?string $source`, `int $limit = 20` → `ImportHistoryView[]`; create `ImportHistoryView.php` colocated

---

#### Phase 3a — Doctrine Entities

- [ ] **T43: Core Doctrine entities**
  - All files in `src/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Entity/`:
  - **`AuthorizationEntity.php`** (→ `pharmaceutical_registry__authorizations`)
    - `#[ORM\Entity]`, `#[ORM\Table]`
    - `#[ORM\Index]` for `status`, `atc_vet_code`, `permanent_identifier`, `last_imported_at`
    - `#[ORM\Id]`, `#[ORM\Column(type: UuidType::NAME)]` for `$id`
    - `VARCHAR(48)` for `$status` (string-backed enum stored as string)
    - `OneToMany` → `JurisdictionEntity[]`, `PresentationEntity[]`, `CompositionEntity[]`, `TargetUsageEntity[]`
    - `OneToOne` → `SummaryEntity` (nullable)
    - All column names snake_case matching schema (spec original section [18])
  - **`JurisdictionEntity.php`** (→ `pharmaceutical_registry__jurisdictions`)
    - `ManyToOne` → `AuthorizationEntity` with `ON DELETE CASCADE`
    - `#[ORM\UniqueConstraint]` on `(jurisdiction_code, authority_code, authority_identifier)`
  - **`PresentationEntity.php`** (→ `pharmaceutical_registry__presentations`)
    - `ManyToOne` → `AuthorizationEntity`
    - `#[ORM\Index]` on `gtin`
    - `prescription_required TINYINT(1) NOT NULL DEFAULT 0` — the `$isRequired` bool from `PrescriptionRequirement` (was missing from original schema)
  - **`ActiveSubstanceEntity.php`** (→ `pharmaceutical_registry__active_substances`)
    - `#[ORM\UniqueConstraint]` on `label_normalized`
  - **`CompositionEntity.php`** (→ `pharmaceutical_registry__compositions`)
    - `ManyToOne` → `AuthorizationEntity`; `ManyToOne` → `ActiveSubstanceEntity` (no CASCADE on substance)
  - **`TargetUsageEntity.php`** (→ `pharmaceutical_registry__target_usages`)
    - `ManyToOne` → `AuthorizationEntity`
    - `#[ORM\Index]` on `target_species_code`
  - **`SummaryEntity.php`** (→ `pharmaceutical_registry__summaries`)
    - `OneToOne` → `AuthorizationEntity` with `#[ORM\JoinColumn(unique: true)]`
    - `sections` column as `json`
  - **`SnapshotEntity.php`** (→ `pharmaceutical_registry__snapshots`)
    - `#[ORM\Index]` on `(source, downloaded_at)`
    - `OneToMany` → `SnapshotEntryEntity[]`
  - **`SnapshotEntryEntity.php`** (→ `pharmaceutical_registry__snapshot_entries`)
    - `ManyToOne` → `SnapshotEntity`
    - `raw_dto`, `changes` as `json`; `content_hash` as `CHAR(64)`
    - `diff_kind`, `target_uuid` nullable
    - `#[ORM\Index]` on `authority_identifier`

---

#### Phase 3b — Doctrine Mappers

- [ ] **T44: Doctrine mappers**
  - All files in `src/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Mapper/` as `final readonly class`:
  - **`MarketingAuthorizationMapper.php`** — `toDomain(AuthorizationEntity, JurisdictionEntity[], PresentationEntity[], CompositionEntity[], TargetUsageEntity[], ?SummaryEntity): MarketingAuthorization`; `toEntity(MarketingAuthorization): AuthorizationEntity`
    - Includes inline mapping of `JurisdictionEntity[]` → `JurisdictionalIdentifier[]`: each row maps `(jurisdiction_code, authority_code, authority_identifier)` → `JurisdictionalIdentifier::of(...)`
  - **`PresentationMapper.php`** — `toDomain(PresentationEntity): Presentation`, `toEntity(Presentation, AuthorizationEntity): PresentationEntity`
    - `PrescriptionRequirement` mapping: `(prescription_required, prescription_class, prescription_retention_years, prescription_juris_code)` → reconstruct using `PrescriptionRequirement::none()` / `rx()` / `rxWithRetention()` / `narcotic()` based on `prescription_class` value
  - **`ActiveSubstanceMapper.php`** — `toDomain(ActiveSubstanceEntity): ActiveSubstance`, `toEntity(ActiveSubstance): ActiveSubstanceEntity`
  - **`CompositionMapper.php`** — `toDomain(CompositionEntity): Composition`, `toEntity(Composition, AuthorizationEntity, ActiveSubstanceEntity): CompositionEntity`
  - **`TargetUsageMapper.php`** — `toDomain(TargetUsageEntity): TargetUsage`, `toEntity(TargetUsage, AuthorizationEntity): TargetUsageEntity`
    - `WithdrawalPeriod` mapping: `(withdrawal_period_value, withdrawal_period_unit)` → `WithdrawalPeriod::days($value)` or `::hours($value)` based on unit string; null if both columns null
  - **`SnapshotMapper.php`** — `toDomain(SnapshotEntity $e): Snapshot` (does NOT load entries — entries are streamed separately via `streamEntriesForDiff()`); `toEntity(Snapshot): SnapshotEntity`
    - `SnapshotEntry` mapping from `SnapshotEntryEntity` (for read-path only): `(authority_identifier, content_hash, raw_dto)` → `SnapshotEntry`; `(diff_kind, target_uuid, changes)` → `DiffEntry` if `diff_kind` is non-null
  - **`SummaryMapper.php`** (6th mapper, implicitly part of `MarketingAuthorizationMapper` or standalone):
    - `SummaryEntity.sections` is stored as JSON array; format: `[{"titleCode": 123, "titleLabel": "Nom du médicament", "content": "..."}]`
    - Deserialization: `json_decode($entity->getSections(), true)` → `array<array{titleCode: int, titleLabel: string, content: string}>` → `SummarySection[]`
    - `SummaryLink` from `source_url` string (nullable)

---

#### Phase 3c — Doctrine Repositories

- [ ] **T45: Doctrine write repositories**
  - Files in `src/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Repository/`:
  - **`DoctrineMarketingAuthorizationRepository.php`** — implements `MarketingAuthorizationRepositoryInterface`
    - `save()`: persist all child entities; `em->persist($entity); em->flush()`
    - `findById()`: fetch `AuthorizationEntity` + eager-load all relations; map via mapper
    - `findByJurisdictionalId()`: DQL join on `JurisdictionEntity`
    - `findProjectionsBySource(ImportSource): iterable` — **lightweight SELECT** of only `(authority_identifier, content_hash, id)` via Doctrine array hydration; maps to `MarketingAuthorizationHashProjection[]`; must NOT load full aggregates
  - **`DoctrineActiveSubstanceRepository.php`** — implements `ActiveSubstanceRepositoryInterface`; `findByNormalizedLabel()`: `WHERE label_normalized = :normalized`
  - **`DoctrineSnapshotRepository.php`** — implements `SnapshotRepositoryInterface`; `findRecent()`: `ORDER BY downloaded_at DESC LIMIT :limit`

- [ ] **T46: Doctrine read repositories**
  - Files in `src/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Repository/`:
  - **`DoctrinePharmaceuticalRefReadRepository.php`** — implements `PharmaceuticalRefReadRepositoryInterface`; returns `PharmaceuticalRefView` from raw Doctrine queries
  - **`DoctrineMarketingAuthorizationSearchRepository.php`** — implements `MarketingAuthorizationSearchRepositoryInterface`
    - `search()`: FULLTEXT on `commercial_name` with `MATCH(...) AGAINST(? IN BOOLEAN MODE)` + LIKE fallback on GTIN; join `active_substances` for INN/DCI
    - `listByAtcVet()`: `WHERE atc_vet_code LIKE :prefix%`
    - `listByTargetSpecies()`: join `target_usages WHERE target_species_code = :code`

---

#### Phase 3d — ANMV Anti-Corruption Layer (France)

- [ ] **T47: ANMV DTOs**
  - Files in `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/Dto/`:
    - `AnmvMedicinalProductDto.php` — all parsed fields from ANMV XML
    - `toArray(): array` must return pure PHP array (scalars + arrays only — required by `ContentHash::of()`); **all numeric fields cast to `(string)`** — no PHP floats allowed
    - `fromArray(array $data): self` — reconstructs DTO from stored rawDto for use by `AnmvBlueprintBuilder::buildBlueprint()`; the dictionary-resolved labels are already in the array (they were resolved at parse time by `AnmvXmlParser`), so no `AnmvDictionaryCache` is needed at apply time
    - `AnmvPresentationDto.php`
    - `AnmvCompositionDto.php`
    - `AnmvVoieAdministrationDto.php`
    - `AnmvDictionaryEntry.php` — type + id + label

- [ ] **T48: `AnmvDictionaryLoader` + `AnmvDictionaryCache`**
  - Files in `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/`:
  - **`AnmvDictionaryCache.php`** — in-memory cache; `getActiveSubstanceLabel(int): string`, `getPrescriptionConditionLabel(int): string`, `getTargetSpeciesLabel(int): string`, `getAdministrationRouteLabel(int): string`, `getWithdrawalUnitLabel(int): string`; throws on unknown key
  - **`AnmvDictionaryLoader.php`** — parses the ~1 MB dictionary XML (SimpleXML acceptable for this small file); returns `AnmvDictionaryCache`

- [ ] **T49: ANMV species mapping resource file** *(prerequisite to T50)*
  - **This task must be completed before `AnmvCodeMapper` is written.**
  - Action:
    1. Download the real ANMV dictionary XML once manually
    2. Extract all `term-esp` entries (2327 entries with their IDs and labels)
    3. Group by animal type, map each to a stable slug (`"dog"`, `"cat"`, `"cattle"`, etc.)
    4. Write the result to `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/Resources/anmv-species-mapping.php` as a plain PHP array: `return [1 => 'dog', 2 => 'cat', 27 => 'cattle', ...];`
    5. Commit this file to the repo — it is a versioned resource, not generated code
  - Adding a new species code in a future ANMV release = one line change in this file
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/Resources/anmv-species-mapping.php`

- [ ] **T50: `AnmvCodeMapper`** *(requires T49)*
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/AnmvCodeMapper.php`
  - Pure stateless service — all mappings via `match()` expressions or loaded arrays
  - Methods (COMPLETE tables, every code explicit; `default => throw UnknownAnmvCodeException`):
    - `mapAuthorizationStatus(int $code): MarketingAuthorizationStatus` — 17 codes
    - `mapProductNature(int $code): ProductNature` — 3 codes
    - `mapAdministrationRoute(int $code): AdministrationRoute` — 49 codes
    - `mapFoodProductionPurpose(?int $code): ?FoodProductionPurpose` — 14 codes (null/10→null)
    - `mapPrescriptionRequirement(int $code): PrescriptionRequirement` — 22 codes
    - `mapTargetSpecies(int $code): TargetSpeciesCode` — loads `anmv-species-mapping.php` once (inject path or load via `require` in constructor); looks up the ID; throws `UnknownAnmvCodeException` if not found
    - `mapWithdrawalPeriod(?string $quantity, ?int $unitCode): ?WithdrawalPeriod` — null→null; parse quantity + unit (days/hours from dico)
    - `buildBlueprint(AnmvMedicinalProductDto $dto): MarketingAuthorizationBlueprint` — assembles the full `MarketingAuthorizationBlueprint` using all map* methods; **no `AnmvDictionaryCache` parameter** — dictionary was already used at parse time; rawDto contains resolved labels
  - Count helpers for test assertions:
    - `getMappedStatusCount(): int` → 17
    - `getMappedRouteCount(): int` → 49
    - `getMappedPrescriptionCount(): int` → 22
    - `getMappedSpeciesCount(): int` → total entries in `anmv-species-mapping.php`

- [ ] **T51: `AnmvXmlParser`**
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/AnmvXmlParser.php`
  - Uses `XMLReader` (NOT SimpleXML) — streaming, one `<medicinal-product>` at a time
  - `parse(string $filePath, AnmvDictionaryCache $dico): \Generator` — yields `AnmvMedicinalProductDto` one by one
  - Computes `ContentHash::of($dto->toArray())` per product and stores on DTO

- [ ] **T52: `AnmvBlueprintBuilder`**
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/AnmvBlueprintBuilder.php`
  - Implements `BlueprintBuilderInterface` for `ImportSource::ANMV`
  - `buildBlueprint(array $rawDto, ImportSource $source): MarketingAuthorizationBlueprint`
    1. `$dto = AnmvMedicinalProductDto::fromArray($rawDto)` — reconstruct DTO from stored data
    2. Delegate to `AnmvCodeMapper::buildBlueprint($dto)` — no dictionary needed (labels already resolved in rawDto)
    3. Return the assembled `MarketingAuthorizationBlueprint`

- [ ] **T53: `AnmvImporter`**
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/AnmvImporter.php`
  - Implements `RegistryImporterInterface` for `ImportSource::ANMV`
  - `stage(Snapshot $snapshot, string $filePath, string $dictionaryPath): void` (both required)
    1. Load `AnmvDictionaryCache` once via `AnmvDictionaryLoader` (needed at parse time to resolve IDs to labels)
    2. Stream-parse via `AnmvXmlParser` (generator) — parser uses dictionary to resolve labels inline, stores resolved labels in DTO
    3. For each DTO: call `$snapshot->addEntry($dto->authorityIdentifier(), $dto->contentHash(), $dto->toArray())` — rawDto now contains resolved labels, not raw ANMV IDs
    4. Persist snapshot (via injected `SnapshotRepositoryInterface`)
  - Does NOT download files — MVP is file-based only

- [ ] **T53: Common import base**
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ImportSources/Common/AbstractRegistryImporter.php`
  - Provides shared utilities: logging, error wrapping
  - No `DownloadedFileStore` at MVP (no download functionality)

---

#### Phase 3e — Console Commands

- [ ] **T54: Console commands**
  - All files in `src/System/PharmaceuticalRegistry/Infrastructure/Console/`:
  - **`ImportAnmvCommand.php`** — `app:pharmaceutical-registry:import-anmv`
    - Options: `--file=PATH` (required), `--dictionary=PATH` (required)
    - Dispatches `ImportSnapshot` only (no diff/apply chaining)
  - **`CalculateDiffCommand.php`** — `app:pharmaceutical-registry:diff`
    - Option: `--snapshot=UUID`; dispatches `CalculateSnapshotDiff`
  - **`ApplyDiffCommand.php`** — `app:pharmaceutical-registry:apply-diff`
    - Option: `--snapshot=UUID`; dispatches `ApplySnapshotDiff`
  - **`FullImportCycleCommand.php`** — `app:pharmaceutical-registry:full-import-cycle`
    - Options: `--source=ANMV`, `--file=PATH`, `--dictionary=PATH`; dispatches `RunFullImportCycle`
  - **`ListSnapshotsCommand.php`** — `app:pharmaceutical-registry:list-snapshots`
    - Options: `--source=ANMV`, `--limit=20`; dispatches `GetImportHistory`, formats as table
  - **`SearchCommand.php`** — `app:pharmaceutical-registry:search`
    - Argument: search term; dispatches `SearchMarketingAuthorizations`

- [ ] **T55: `BootstrapAnmvImportCommand`** — *dedicated bootstrap with batched transactions*
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/Console/BootstrapAnmvImportCommand.php`
  - Command: `app:pharmaceutical-registry:bootstrap`
  - Options: `--file=PATH` (required), `--dictionary=PATH` (required), `--batch=500`
  - Purpose: initial full import of ~14 000 products — cannot use the regular `ApplySnapshotDiffHandler` (one transaction for 14 000+ entities would timeout)
  - Implementation:
    1. Dispatch `ImportSnapshot` + `CalculateSnapshotDiff` normally (these are fast)
    2. For the apply phase: load snapshot diffEntries, iterate in batches of `--batch` entries
    3. Per batch: open a manual Doctrine transaction (`em->beginTransaction()`), apply entries (CREATE/UPDATE/WITHDRAW), `em->flush(); em->commit(); em->clear()`
    4. On batch failure: `em->rollback()`, `markAsFailed()`, stop with error output
    5. After all batches: `$snapshot->markAsApplied(ImportResult)`, persist, publish events
  - This command intentionally bypasses the `doctrine_transaction` Messenger middleware — it manages transactions manually. The handler path (weekly delta) stays clean.

---

#### Phase 3f — Read Model Projection

- [ ] **T56: `PharmaceuticalRefProjection`**
  - File: `src/System/PharmaceuticalRegistry/Infrastructure/ReadModel/PharmaceuticalRefProjection.php`
  - Hydrates `PharmaceuticalRefView` from raw Doctrine results
  - Used by `DoctrinePharmaceuticalRefReadRepository`

---

#### Phase 4 — Doctrine Migration

- [ ] **T57: Generate and complete Doctrine migration**
  - Step 1 — Generate: Inside Docker, run `bin/console doctrine:migrations:diff --namespace='DoctrineMigrations\\PharmaceuticalRegistry'`
  - Step 2 — Review: Verify the generated SQL covers all 9 tables with correct column types, FKs, and indexes per spec section [18]
  - Step 3 — Add FULLTEXT manually: `doctrine:migrations:diff` does NOT generate FULLTEXT indexes (Doctrine ORM has no native FULLTEXT support). Add this line manually to the `up()` method:
    ```php
    $this->addSql('ALTER TABLE pharmaceutical_registry__authorizations ADD FULLTEXT INDEX ft_pharma_authz_name (commercial_name)');
    ```
    And in `down()`:
    ```php
    $this->addSql('ALTER TABLE pharmaceutical_registry__authorizations DROP INDEX ft_pharma_authz_name');
    ```
  - File: `migrations/PharmaceuticalRegistry/Version{timestamp}.php`
  - **Critical:** without this FULLTEXT index, `DoctrineMarketingAuthorizationSearchRepository::search()` will fail silently with a MySQL error on `MATCH(...) AGAINST(...)`.

---

#### Phase 5 — BC README

- [ ] **T58: `src/System/PharmaceuticalRegistry/README.md`**
  - Contents:
    - Purpose of the BC
    - `JurisdictionCode` vs `Shared\CountryCode`: why BC-internal (EU not a country)
    - `TargetSpeciesCode` vs `Animal\Species`: why BC-internal (regulatory granularity)
    - `AuthorizationEntity` vs `MarketingAuthorization`: naming decision for tables
    - `MarketingAuthorizationBlueprint`: role as ACL seam between infra parser and domain aggregate
    - How to add a new jurisdiction source (new `ImportSources/{Country}/` folder)
    - How to run the initial bootstrap: `app:pharmaceutical-registry:bootstrap --file=... --dictionary=...`
    - How to run subsequent imports: `app:pharmaceutical-registry:full-import-cycle --source=ANMV --file=... --dictionary=...`
    - Link to original BMAD spec for deferred items (scheduler, HTTP downloader)

---

#### Phase 6 — Foundry v2 Fixtures

- [ ] **T59: Foundry factories**
  - Files in `fixtures/System/PharmaceuticalRegistry/Factory/`:
  - **`AuthorizationEntityFactory.php`** — `extends PersistentProxyObjectFactory<AuthorizationEntity>`, defaults: status=ACTIVE, commercial_name=faker drug name, holder_laboratory=faker company
  - **`PresentationEntityFactory.php`** — defaults: description, random GTIN
  - **`ActiveSubstanceEntityFactory.php`** — defaults: label, `label_normalized = mb_strtolower(trim($label))`
  - **`SnapshotEntityFactory.php`** — defaults: source=ANMV, status=APPLIED

- [ ] **T60: Foundry stories**
  - Files in `fixtures/System/PharmaceuticalRegistry/Story/`:
  - **`CoreMedicationsStory.php`** — 10 real drugs: Apoquel (oclacitinib, dog, oral, RX_CONTROLLED), Cerenia (maropitant), Frontline (fipronil, no Rx), Convenia (cefovecin, injectable), Vetmedin (pimobendan), Stronghold (selamectin), Synulox (amoxicillin+clavulanate), Cystaid (palmitoylethanolamide), Meloxidyl (meloxicam), Rilexine (cefalexin). Each with ≥1 presentation.
  - **`ControlledSubstancesStory.php`** — 2-3 narcotics with `PrescriptionClass::RX_NARCOTIC`
  - **`LivestockMedicationsStory.php`** — 3 livestock drugs with `WithdrawalPeriod` (e.g., cattle 28 days meat, 7 days milk)

---

#### Phase 7 — Tests

- [ ] **T61: Unit tests — Value Objects**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Domain/ValueObject/`
  - Critical tests:
    - `ContentHashTest::testDeterminism` — same data with permuted keys → identical hash; different values → different hash; 3-level nested array with permuted inner keys → same hash
    - `JurisdictionCodeTest` — accepts FR, EU, CH, UK; rejects "FRANCE", "fr", "EU1"
    - `GtinTest` — accepts 8, 13, 14 digits; rejects 7, 15, non-digits
    - `AtcVetCodeTest` — accepts "QD11AA", "QI07"; rejects "D11", "X11AA"
    - `MarketingAuthorizationStatus::isMarketable()` — true for ACTIVE/EXCEPTIONAL_CIRCUMSTANCES/UNLIMITED; false for rest
    - `WithdrawalPeriodTest` — `days(28)->toString()` = `"28 jours"`; `hours(12)->toString()` = `"12 heures"`

- [ ] **T62: Unit tests — Domain Entities**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Domain/Entity/`
  - `PresentationTest` — invariants, equality by ID
  - `CompositionTest` — quantityValue ≥ 0 invariant (bcmath)
  - `SnapshotEntryTest`, `DiffEntryTest`

- [ ] **T63: Unit tests — Domain Aggregates**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Domain/`
  - **`MarketingAuthorizationTest`**:
    - `create()` with 3 presentations records exactly 4 events: 1 `MarketingAuthorizationCreated` + 3 `PresentationAdded`
    - `addJurisdictionalIdentifier()` throws `DuplicateJurisdictionalIdException`
    - `addPresentation()` refuses if status == WITHDRAWN
    - `withdraw()` idempotent: 2nd call → no-op, no events
    - `updateFromImport()` emits only relevant events per changed field
    - `updateControlledSubstance()` emits event only if changed
  - **`ActiveSubstanceTest`**:
    - `normalizeLabel("MALÉATE D'OCLACITINIB")` → `"maléate d'oclacitinib"`
    - collapse multiple spaces, trim leading/trailing
  - **`SnapshotTest`**:
    - `create()` records `SnapshotStarted`
    - Valid transitions: PENDING→RUNNING→DIFFED→APPLYING→APPLIED
    - Invalid transition throws `InvalidSnapshotStatusTransitionException`
    - `markAsApplied()` twice → `SnapshotAlreadyAppliedException`

- [ ] **T64: Unit tests — `RegistryDiffCalculator`**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Domain/Service/RegistryDiffCalculatorTest.php`
  - Cases: empty+empty→[], new product→CREATE, hash same→skip, hash different→UPDATE, current missing from snapshot→WITHDRAW, mix: 3 CREATE + 2 UPDATE + 1 WITHDRAW

- [ ] **T65: Unit tests — Application Handlers**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Application/`
  - `ImportSnapshotHandlerTest` — mocks `RegistryImporterInterface`, `SnapshotRepositoryInterface`; verify `markAsRunning()` called before `stage()`
  - `CalculateSnapshotDiffHandlerTest` — mocks all dependencies; verifies `streamEntriesForDiff()` called (not `$snapshot->entries()`); verifies `findProjectionsBySource()` called
  - `ApplySnapshotDiffHandlerTest` — verifies `BlueprintBuilderInterface::buildBlueprint()` called per CREATE/UPDATE entry; verifies BOTH domain and integration event publishers called per mutated aggregate
  - `RunFullImportCycleHandlerTest` — **called as plain service (not via bus)**; orchestration chain; on exception: `markAsFailed()` called
  - `ManuallyMarkMarketingAuthorizationWithdrawnHandlerTest` — load aggregate, `withdraw()`, save, both publishers called

- [ ] **T66: Unit tests — Integration Event interface compliance**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Domain/Event/IntegrationEventComplianceTest.php`
  - Scans `src/System/PharmaceuticalRegistry/Domain/Event/` for all PHP classes
  - Verifies these 10 implement `IntegrationEventInterface`: `MarketingAuthorizationUpdated`, `MarketingAuthorizationWithdrawn`, `MarketingAuthorizationSuspended`, `MarketingAuthorizationStatusChanged`, `PresentationAdded`, `PresentationRemoved`, `PresentationGtinChanged`, `WithdrawalPeriodChanged`, `PrescriptionRequirementChanged`, `ControlledSubstanceClassificationChanged`

- [ ] **T67: Unit tests — `AnmvCodeMapper` exhaustive**
  - File: `tests/Unit/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/AnmvCodeMapperTest.php`
  - `testMapAuthorizationStatus`: all 17 codes + unknown throws `UnknownAnmvCodeException` with code in message
  - `testMapProductNature`: 3 codes
  - `testMapAdministrationRoute`: all 49 codes (PHPUnit data provider)
  - `testMapFoodProductionPurpose`: all 14 codes including null
  - `testMapPrescriptionRequirement`: all 22 codes
  - `testMapTargetSpecies`: representative sample (≥20 species IDs from the mapping file)
  - `testGetMappedCountsAreAccurate`: `getMappedRouteCount()===49`, `getMappedStatusCount()===17`, `getMappedPrescriptionCount()===22`

- [ ] **T68: Integration tests — Doctrine mappers**
  - File: `tests/Integration/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Mapper/`
  - Symmetry test: `toDomain(toEntity($aggregate))` produces equivalent aggregate

- [ ] **T69: Integration tests — Doctrine repositories**
  - File: `tests/Integration/System/PharmaceuticalRegistry/Infrastructure/Persistence/Doctrine/Repository/`
  - `DoctrineMarketingAuthorizationRepositoryTest` — save, findById, findByJurisdictionalId, **findProjectionsBySource** (verify only 2 fields loaded, not full aggregate)
  - `DoctrineActiveSubstanceRepositoryTest` — findByNormalizedLabel (case insensitive)
  - `DoctrineSnapshotRepositoryTest` — save, findById, findRecent
  - `DoctrineMarketingAuthorizationSearchRepositoryTest` — FULLTEXT by name, by GTIN, by ATCvet prefix, by species

- [ ] **T70: Integration tests — ANMV import pipeline**
  - File: `tests/Integration/System/PharmaceuticalRegistry/Infrastructure/ImportSources/France/`
  - **All tests use static fixture files** in `tests/fixtures/System/PharmaceuticalRegistry/`:
    - `anmv_sample.xml` — 10–20 product fixture committed to repo
    - `dictionary_sample.xml` — corresponding dictionary fixture
    - **Never use the real 53 MB ANMV file in tests**
  - `AnmvDictionaryLoaderTest` — parses `dictionary_sample.xml`
  - `AnmvXmlParserTest` — parses `anmv_sample.xml`, verifies 10 DTOs yielded with correct fields
  - `AnmvImporterTest` — end-to-end: stage 10 products into a `Snapshot`, verify entries count and contentHash correctness

- [ ] **T71: Integration tests — Full import cycle**
  - File: `tests/Integration/System/PharmaceuticalRegistry/Application/`
  - `FullImportCycleTest`:
    - Import 10 products (fixture file), diff, apply → verify 10 `MarketingAuthorization` in DB with correct presentations
    - Run same import again → `ImportResult{created: 0, updated: 0, withdrawn: 0, skipped: 10}`
  - `ResumeFromDiffedSnapshotTest`:
    - Import 10 products, calculate diff (status=DIFFED persisted), simulate crash (stop before apply)
    - Resume via `ApplySnapshotDiff` command → correct final state
  - `CrossBcEventTest`:
    - Apply 1 UPDATE diff entry with changed prescription → `PrescriptionRequirementChanged` dispatched; verify it implements `IntegrationEventInterface` and reaches `messenger.bus.event` (in-memory transport)

---

### Acceptance Criteria

- [ ] **AC1:** Given the BC code is in place and T1–T4 config tasks complete, when `make ci` runs, then it exits 0 (no missing class references, no config errors).

- [ ] **AC2:** Given `MarketingAuthorizationStatus::ACTIVE`, `EXCEPTIONAL_CIRCUMSTANCES`, `UNLIMITED`, when `isMarketable()` is called, then returns `true`; all other cases return `false`.

- [ ] **AC3:** Given two identical DTO arrays with keys in different order, when `ContentHash::of()` is called on each, then both hashes are identical. Given two DTOs with different values, then hashes are different.

- [ ] **AC4:** Given `JurisdictionCode::fromString('EU')`, when called, then succeeds; given `JurisdictionCode::fromString('france')`, when called, then throws `InvalidJurisdictionCodeException`.

- [ ] **AC5:** Given `MarketingAuthorization::create(...)` with 3 initial presentations, when `pullDomainEvents()` is called, then returns exactly 4 events: 1 `MarketingAuthorizationCreated` + 3 `PresentationAdded`.

- [ ] **AC6:** Given a `MarketingAuthorization` with status `WITHDRAWN`, when `addPresentation()` is called, then throws (refuses).

- [ ] **AC7:** Given a `MarketingAuthorization` already `WITHDRAWN`, when `withdraw()` is called again, then is a no-op (no exception, no new events).

- [ ] **AC8:** Given a `Snapshot` with status `APPLIED`, when `markAsApplied()` is called again, then throws `SnapshotAlreadyAppliedException`.

- [ ] **AC9:** Given a `Snapshot` in `PENDING` status, when `markAsDiffed()` is called (invalid from PENDING — must first go through RUNNING), then throws `InvalidSnapshotStatusTransitionException`.

- [ ] **AC10:** Given `AnmvCodeMapper::mapAdministrationRoute(29)`, when called, then returns `AdministrationRoute::ORAL`; given code `9999`, then throws `UnknownAnmvCodeException` with `"9999"` in the message.

- [ ] **AC11:** Given a full import cycle of 10 ANMV products runs successfully, when run a second time with identical fixture data, then `ImportResult{created: 0, updated: 0, withdrawn: 0, skipped: 10}`.

- [ ] **AC12:** Given `AnmvCodeMapper::getMappedRouteCount()`, when called, then returns `49`; `getMappedStatusCount()` returns `17`; `getMappedPrescriptionCount()` returns `22`.

- [ ] **AC13:** Given `ApplySnapshotDiff` processes one UPDATE diff entry with changed prescription class, when handler completes, then `PrescriptionRequirementChanged` (implementing `IntegrationEventInterface`) is dispatched via the **integration event publisher** (async cross-BC transport) — NOT via the domain event publisher (sync intra-BC bus). Verify via in-memory transport inspection in integration test.

- [ ] **AC14:** Given `SearchMarketingAuthorizations(term: "apoquel")` is dispatched, when the product exists in DB (via fixture), then result contains at least one `MarketingAuthorizationSearchResult` with `commercialName` matching "apoquel" (case-insensitive FULLTEXT).

- [ ] **AC15:** Given all 10 cross-BC events are scanned, when `IntegrationEventComplianceTest` runs, then all 10 implement `IntegrationEventInterface`.

- [ ] **AC16:** Given `ActiveSubstance::normalizeLabel("MALÉATE D'OCLACITINIB")`, when called, then returns `"maléate d'oclacitinib"`.

- [ ] **AC17:** Given `findProjectionsBySource(ImportSource::ANMV)` is called with 14 000 products in DB, when the query executes, then only `authority_identifier`, `content_hash`, and `id` columns are fetched (no join loading of presentations, compositions, etc.) — verified by query log or Doctrine profiler in test.

- [ ] **AC18:** Given `make ci` runs after all tasks complete, then it exits 0 (php-cs-fixer, phpcs, phpstan level:max, tests all green, 100% coverage on BC).

---

## Additional Context

### Dependencies

**External libraries:**
- `ext-bcmath` — verify present in Docker PHP image (used by Composition quantity validation); add to `composer.json` `require` if absent
- `XMLReader` — PHP core extension, always available in PHP 8.5

**Deferred dependencies (not MVP):**
- `symfony/scheduler` — needed for the weekly scheduler; deferred with `WeeklyAnmvImportScheduleProvider`
- Scoped HTTP client `anmv` — needed for download; deferred with `AnmvDownloadService`

**Other BC dependencies (all already implemented):**
- `App\Shared\Domain\Aggregate\AggregateRoot`
- `App\Shared\Domain\Identifier\AbstractUuidId`, `UuidGeneratorInterface`
- `App\Shared\Domain\Time\ClockInterface`
- `App\Shared\Domain\Event\AbstractDomainEvent`, `AbstractIntegrationEvent`
- `App\Shared\Application\Bus\CommandInterface`, `QueryInterface`
- `App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy`
- `DomainEventPublisher` — `publish($aggregate)` after `save()`

**Data dependencies:**
- ANMV XML and dictionary: static fixture files in `tests/fixtures/System/PharmaceuticalRegistry/` (10–20 products); committed to repo; never the real 53 MB file
- ANMV species mapping: `src/.../Resources/anmv-species-mapping.php` — produced once from real dictionary, versioned (T49)

### Testing Strategy

**Unit tests** (`tests/Unit/System/PharmaceuticalRegistry/`):
- Zero framework, zero Doctrine, zero HTTP — pure PHP
- All VOs tested for regex invariants, factory methods, equality
- Aggregates tested for domain events emitted, invariants, idempotence
- `AnmvCodeMapper` tested exhaustively: every mapped code + unknown code exception
- `RegistryDiffCalculator` tested for all 5 algorithm branches

**Integration tests** (`tests/Integration/System/PharmaceuticalRegistry/`):
- Use `KernelTestCase` + Foundry `PersistentProxyObjectFactory`
- Real database transactions via `dama/doctrine-test-bundle` (existing setup)
- ANMV XML parsing uses static fixture files only

**Coverage gate:** PHPUnit `failOnIncomplete`, `failOnRisky`, `failOnWarning`. PHPStan level:max. Every PHP class in the BC must be covered.

### Notes

**High-risk items:**

1. **AnmvCodeMapper species mapping (2327 codes)** — The resource file approach (T49) removes the risk of a 2327-entry `match()` expression in PHP code. The real risk is: are all 2327 IDs correctly mapped before the file is committed? Mitigation: after generating the file, run `AnmvXmlParser` on a local copy of the real ANMV XML and verify zero `UnknownAnmvCodeException` across all 14 000 products.

2. **XMLReader streaming memory** — `AnmvXmlParser` must yield per `<medicinal-product>`, never accumulate. If the generator is not consumed lazily by `AnmvImporter`, memory will blow up. Validate with `memory_get_peak_usage()` in the integration test.

3. **Snapshot entry JSON size** — `raw_dto` can be 5–50 KB per product × 14 000 = potentially 700 MB in `pharmaceutical_registry__snapshot_entries`. `CalculateSnapshotDiffHandler` must NOT load all `SnapshotEntry` objects into memory at once — use `findProjectionsBySource()` (T27) for the current-state side of the diff, and iterate snapshot entries via a paginated or lazy Doctrine query.

4. **FULLTEXT index** — Must be added manually in the migration (T57). After running the migration, verify on the dev DB that `SHOW INDEX FROM pharmaceutical_registry__authorizations` contains a FULLTEXT index on `commercial_name`. MySQL requires InnoDB + `innodb_ft_min_token_size` ≤ 3 for short tokens to match.

5. **Bootstrap batching (T55)** — The `BootstrapAnmvImportCommand` bypasses the Messenger `doctrine_transaction` middleware. It must NOT call `$commandBus->dispatch(ApplySnapshotDiff(...))` — that would re-enter the middleware. Instead it directly injects `EntityManagerInterface` and manages batch transactions. This is intentional and should be documented in the command's docblock.

6. **`ContentHash::of()` contract** — `AnmvMedicinalProductDto::toArray()` must produce a pure PHP array (scalars + nested arrays, no objects, **no floats** — cast all numeric values to string). Float `json_encode` is platform/locale-dependent in edge cases. Enforce with a `ContentHashTest::testFloatCastingIsEnforced` test.

7. **Dual event publisher** — Before implementing `ApplySnapshotDiffHandler`, read the actual project code for a handler that emits `IntegrationEventInterface` events (e.g. `ArchiveClientHandler`, `PatientIdentityResolutionService`). Copy the publisher injection pattern exactly. Do not assume `DomainEventPublisher` routes integration events — it likely does not.

8. **`RunFullImportCycleHandler` is NOT an AsMessageHandler** — It must be called as a plain service from `FullImportCycleCommand`. If someone adds `#[AsMessageHandler]` to it, the nested transaction problem returns and per-step recovery breaks silently.

**Deferred items (activate with a dedicated story):**
- `AnmvDownloadService` + `AnmvFileLocator` + `RegistryDownloaderInterface` — HTTP download from data.gouv.fr
- `WeeklyAnmvImportScheduleProvider` — `composer require symfony/scheduler` + `scheduler.yaml` + `#[AsSchedule]` attribute
- Scoped HTTP client `anmv` in `framework.yaml` (timeout 600s)
- `ImportAnmvFromDataGouvCommand` — CLI trigger for HTTP-based import
- `messenger.yaml` routing of `RunFullImportCycle` to `async` transport (only needed when scheduler dispatches it)
- When activating: update `ImportSnapshot` + `RunFullImportCycle` to accept nullable `$filePath`/`$dictionaryPath`; `AnmvImporter::stage()` falls back to downloader if paths are null
