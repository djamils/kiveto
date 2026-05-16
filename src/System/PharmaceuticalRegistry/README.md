# System/PharmaceuticalRegistry

## Purpose

Global regulatory drug reference — tenant-free, shared across all clinics.
Ingests ANMV France XML, computes diffs, applies changes event-by-event.

## Key Design Decisions

### JurisdictionCode vs Shared\CountryCode
`JurisdictionCode` is BC-internal (`^[A-Z]{2,3}$`). It accepts `"EU"` (European Union), which is not a country.
`Shared\CountryCode` is strictly ISO 3166-1 alpha-2. Use `Shared\CountryCode` when you need a real country; use `JurisdictionCode` for regulatory jurisdiction (FR, EU, CH, UK, etc.).

### TargetSpeciesCode vs Animal\Species
`TargetSpeciesCode` maps to 2327+ ANMV regulatory species codes (slug format). `Animal\Domain\ValueObject\Species` (DOG/CAT/NAC/OTHER) is too coarse. Cross-BC alignment is done in `Catalog/Infrastructure/Adapter/PharmaceuticalRegistry/`.

### AuthorizationEntity vs MarketingAuthorization
Doctrine entity is named `AuthorizationEntity` → table: `pharmaceutical_registry__authorizations` (short, readable). Domain aggregate is `MarketingAuthorization` throughout.

### MarketingAuthorizationBlueprint
ACL seam between ANMV infrastructure parsing and universal domain logic. `AnmvCodeMapper` (Infrastructure) produces it; `MarketingAuthorization::create()` and `::updateFromImport()` consume it. Lives in `Domain/` because the aggregate's public API depends on it.

### Dual event publishing
- Domain events (`AbstractDomainEvent`, intra-BC, sync): recorded via `recordDomainEvent()`, published via `DomainEventPublisher`.
- Integration events (`AbstractIntegrationEvent`, cross-BC, async): recorded via `recordIntegrationEvent()` on `MarketingAuthorization`, pulled via `pullIntegrationEvents()`, published via `IntegrationEventPublisher`.

## Adding a new jurisdiction source

1. Create `Infrastructure/ImportSources/{Country}/` directory
2. Implement `RegistryImporterInterface` and `BlueprintBuilderInterface`
3. Register in the service container

## Running the initial bootstrap

```bash
bin/console app:pharmaceutical-registry:bootstrap \
  --file=path/to/anmv.xml \
  --dictionary=path/to/dict.xml \
  --batch=500
```

## Running subsequent imports

```bash
bin/console app:pharmaceutical-registry:full-import-cycle \
  --source=ANMV \
  --file=path/to/anmv.xml \
  --dictionary=path/to/dict.xml
```

## Deferred items

See `_bmad-output/implementation-artifacts/tech-spec-system-pharmaceutical-registry-bc.md` for:
- HTTP downloader (AnmvDownloadService, RegistryDownloaderInterface)
- Symfony Scheduler (WeeklyAnmvImportScheduleProvider)
- Snapshot cleanup command
