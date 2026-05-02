---
title: 'Global Search — Provider-based architecture with read models'
slug: 'global-search-provider-read-models'
created: '2026-04-26'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'PHP 8.5 / Symfony 7.4'
  - 'Doctrine ORM ^3.5 + DBAL (BINARY(16) UUID, prefix LIKE on search_* columns)'
  - 'Symfony Messenger (messenger.bus.event synchronous, doctrine_transaction on command bus)'
  - 'Stimulus 3.2 (new global-search controller, preserve existing autocomplete controllers)'
  - 'Turbo Frame for live result rendering'
  - 'MySQL utf8mb4_0900_ai_ci, B-tree composite indexes (clinic_id, search_*)'
files_to_modify:
  - 'src/Context/Animal/Domain/Animal.php — emit AnimalIdentityChanged + AnimalOwnersReplaced'
  - 'src/Context/Animal/Domain/Event/ — add AnimalIdentityChanged.php, AnimalOwnersReplaced.php'
  - 'src/Shared/Search/ — CREATE (new namespace)'
  - 'src/Context/Animal/Application/Search/ — CREATE'
  - 'src/Context/Animal/Infrastructure/Search/ — CREATE'
  - 'src/Context/Client/Application/Search/ — CREATE'
  - 'src/Context/Client/Infrastructure/Search/ — CREATE'
  - 'src/Presentation/Clinic/Controller/Search/ — CREATE (SearchController)'
  - 'assets/controllers/global_search_controller.js — CREATE (live search, replaces search_center for live mode)'
  - 'templates/components/layout/search_modal.html.twig — BLOCKED: DO NOT modify until separate wiring ticket created by Djamil'
  - 'config/services.yaml — tag providers with vetsaas.search_provider, bind rate limiter on SearchController'
code_patterns:
  - 'SearchProviderInterface: supports(SearchQuery), search(SearchQuery): SearchBucket, resultType(): string, isExternal(): bool'
  - 'SearchEngine: inject iterable<SearchProviderInterface> $providers, list<string> $bucketOrder — MAX_HITS_PER_PROVIDER=20 const, 800ms timeout only for isExternal()==true'
  - 'SearchQuery: immutable, withLimit(int): self returns clone — repository reads $query->limit directly (no double convention)'
  - 'SearchHit: id(UUID), resourceId(UUID), title, subtitle, context, metadata[source, species, etc.]'
  - 'Provider detects chip (15 digits) → = on search_chip; phone E.164 → = on search_phone; else prefix LIKE'
  - 'Projection handlers: On{EventName}.php → single dependency: the *SearchIndexWriter — Client event data via $event->payload()[key] (all props are private)'
  - 'Writer methods: upsert(DTO), updateName(), updateChip(), updateOwner(), updateOwnerName(), markArchived(), delete() — writer calls $em->flush(); updateOwnerName() uses DBAL executeStatement (no flush); chip empty-string treated as null'
  - 'DBAL UUID pattern: Uuid::fromString($id)->toBinary() for params; Uuid::fromBinary($row["id"])->toString() for reads — NO custom type, always manual'
  - 'ContactMethodType::PHONE->value = "phone" (confirmed backed string enum) — filter contactMethods where type === "phone" AND isPrimary === true'
  - 'normalizeText: iconv TRANSLIT first (then lowercase) + replace hyphens/apostrophes with spaces + trim — iconv returns false on bad UTF-8, must handle'
  - 'Limit fetch +1 for hasMore, zero COUNT() SQL, hard cap SearchEngine::MAX_HITS_PER_PROVIDER=20 (const defined on SearchEngine, not on providers)'
  - 'vetsaas:search:pick CustomEvent dispatched on $this->element (controller root); pickerId in detail; listener on #search-modal with once:true + pickerId guard'
  - 'DomainEventPublisher: called INSIDE __invoke(), after repo->save(), before return — guaranteed inside doctrine_transaction boundary'
test_patterns:
  - 'Unit: 1 test per projection handler (mock writer, assert correct method called with correct data)'
  - 'Unit: AnimalSearchProvider with mock repo — chip/phone exact, text prefix, isExecutable guard'
  - 'Unit: AnimalSearchIndexWriter (upsert inserts then updates, delete removes, flush called)'
  - 'Unit: SearchTermNormalizer — full table (FR/ES/DE accents, uppercase, hyphens, apostrophes, chips, E.164)'
  - 'Unit: Animal aggregate — AnimalIdentityChanged emitted only on actual change'
  - 'Unit: Animal aggregate — AnimalOwnersReplaced emitted by replaceOwners()'
  - 'Integration: DoctrineAnimalSearchRepository — search returns hits, IDOR (different clinic returns empty)'
---

# Tech-Spec: Global Search — Provider-based architecture with read models

**Created:** 2026-04-26

---

## Overview

### Problem Statement

VetApp has no live global search. The existing `search_modal.html.twig` is a UI shell with hardcoded static results and client-side filtering only (`search_center_controller.js` does zero API calls). Existing `SearchClients`/`SearchAnimals` queries hit aggregate tables directly with contains LIKE — not indexable, no unified endpoint, no extensibility path. The waiting room flow (urgence/check-in modals) and the future "rattacher à un propriétaire" need a reliable, fast picker component backed by real live data.

### Solution

Provider-based search engine with dedicated read-model tables per bounded context. Each BC owns its `*_search_index` table, populated synchronously via domain event projection handlers in the same Doctrine transaction. A unified `GET /search` Turbo Frame endpoint aggregates buckets from all registered providers. The existing `search_modal.html.twig` shell is wired to the new live backend. A single Stimulus controller (`global-search`) handles both **navigate** mode (Cmd+K global) and **pick** mode (from modals), with `pickerId` scoping to prevent cross-modal interference.

### Scope

**In Scope — V1:**
- `AnimalIdentityChanged` + `AnimalOwnersReplaced` events added to Animal aggregate
- `Shared/Search/` contracts: `SearchProviderInterface` (with `isExternal()`), `SearchEngine`, `SearchQuery`, `SearchBucket`, `SearchHit`, `SearchTermNormalizer`
- Animal search: `animal_search_index` table + projection handlers + `AnimalSearchProvider`
- Client search: `client_search_index` table + projection handlers + `ClientSearchProvider`
- Unified `GET /search?q=&type=&mode=&pickerId=` controller with strict mode validation
- `global-search` Stimulus controller: debounce 250ms, AbortController, keyboard ↑↓ Enter, Escape, modes navigate/pick, `vetsaas:search:pick` CustomEvent with pickerId
- Wire `search_modal.html.twig` to live backend (structure validated by Djamil before Task 7.2)
- Bucket order: YAML param `vetsaas.search.bucket_order: ['animal', 'client']`
- Hard cap: `SearchEngine::MAX_HITS_PER_PROVIDER = 20`

**Out of Scope — V1 (explicit YAGNI):**
- Public IDs `ANI-XXXX-XXXX` — deferred V2, belongs in aggregate not projection
- I-CAD external provider
- Consultation/Document/Appointment search buckets
- Full search page with pagination and totalCount
- Meilisearch/OpenSearch
- Outbox/async projection
- FULLTEXT MATCH AGAINST
- Deprecation of existing `SearchClients`/`SearchAnimals` + their API controllers (audit first, separate PR)
- Repository flush refactor (17 existing repos call `$em->flush()` — not in scope)

---

## Context for Development

### Codebase Patterns (from audit)

**Existing infrastructure — do not break:**
- `search_modal.html.twig` — UI shell with modal-overlay pattern, filter pills (Clients, Animaux, Consultations, Documents, RDV), Récents section, multi-column layout. **DO NOT modify structure until Djamil validates final wired version.**
- `search_center_controller.js` — client-side DOM filter of hardcoded HTML, kept for Récents section. `global-search` controller handles live API.
- `modal.js` handles `data-modal-open="search-modal"` click → opens modal. Cmd+K not yet bound.
- `doctrine_transaction` middleware on `messenger.bus.command` + shared EntityManager = same-UoW guaranteed for command + projection handlers in one transaction.
- **F1 VERIFIED**: `DomainEventPublisher::publish()` (actually `$this->eventBus->publish([], ...$aggregate->pullDomainEvents())`) is called **INSIDE `__invoke()`**, after `$repo->save()`, before `return`. This means projection handlers always run within the `doctrine_transaction` boundary. Pattern confirmed on `UpdateAnimalIdentityHandler`, `CreateClientHandler`, and all other audited handlers. Any future handler that calls `publish()` OUTSIDE `__invoke()` (e.g., from a kernel listener) would break this guarantee — document this invariant as a code review checklist item.
- Existing `client_search_autocomplete_controller.js` + `animal_search_autocomplete_controller.js` + their `/api/clinic/*/search` endpoints → **kept untouched**, they serve inline form autocomplete.

**Flush strategy (decided after audit — 17 repos call flush):**
The `AnimalSearchIndexWriter` and `ClientSearchIndexWriter` call `$em->flush()` themselves at the end of each write method. This is the isolated fallback chosen because refactoring all 17 existing repositories is out of scope. The writers are the ONLY new services that call flush. Domain event handlers must NOT call flush independently — they call the writer method, which flushes.

**Transactional proof requirement:** The `AnimalSearchIndexWriterTest` must include a rollback test — if an exception is thrown after `upsert()` but before the command transaction commits, both the aggregate write and the search index write must roll back.

**Animal BC new events:**
- **NEW** `AnimalIdentityChanged` — emitted from `Animal::updateIdentity()` when any of `microchipNumber`, `tattooNumber`, `passportNumber` actually changes. Carries NEW values. Conditional: only if at least one field differs.
- **NEW** `AnimalOwnersReplaced` — emitted from `Animal::replaceOwners()` unconditionally (ownership change is always a business fact). Carries `primaryOwnerClientId` + `secondaryOwnerClientIds[]`.

**Client BC existing events — access pattern (CRITICAL — F4 VERIFIED):**
ALL Client BC events have **private** constructor properties. There are NO public getters. The ONLY way to read data from these events in handlers is via `$event->payload()` which returns an array. **Never use `$event->firstName` — it will throw a fatal Error.**

Exact payload() keys for each event:
- `ClientCreated::payload()` → `['clientId', 'clinicId', 'firstName', 'lastName', 'contactMethods']` where `contactMethods` is `list<array{type: string, label: string, value: string, isPrimary: bool}>`
- `ClientIdentityUpdated::payload()` → `['clientId', 'clinicId', 'firstName', 'lastName']`
- `ClientContactMethodsReplaced::payload()` → `['clientId', 'clinicId', 'contactMethods']`
- `ClientArchived::payload()` → check file for exact keys (clientId confirmed)
- `ClientUnarchived::payload()` → check file for exact keys

Handler access pattern:
```php
$payload = $event->payload();
$firstName = $payload['firstName'];
$lastName  = $payload['lastName'];
$clientId  = $payload['clientId'];
$clinicId  = $payload['clinicId'];
```

- Cross-context: Animal BC's `OnClientIdentityUpdated` handler → updates `search_owner_name` in `animal_search_index` for all that client's animals

**SearchTermNormalizer — normalizeText() algorithm:**
```
1. iconv('UTF-8', 'ASCII//TRANSLIT', $term)  → strip accents (é→e, ü→u, ß→ss)
2. strtolower()                               → lowercase
3. preg_replace('/[-\']+/', ' ', $result)     → hyphens + apostrophes → spaces
4. preg_replace('/\s+/', ' ', $result)        → collapse multiple spaces
5. trim()                                     → strip leading/trailing whitespace
```

Result examples:
- `"RÉMI"` → `"remi"`, `"Rémi"` → `"remi"`, `"rémi"` → `"remi"`
- `"Jean-Pierre"` → `"jean pierre"`
- `"O'Brien"` → `"o brien"`
- `"Saint-Étienne"` → `"saint etienne"`
- `"  Marie    Curie  "` → `"marie curie"`
- `"Müller"` → `"muller"` (DE), `"García"` → `"garcia"` (ES)
- Note on `ß`: `iconv TRANSLIT` converts `ß` → `ss` on most systems; test this explicitly

**Search columns pattern:**
- `search_name` = `normalizeText(firstName . ' ' . lastName)` or `normalizeText(animalName)`
- `search_phone` = E.164 normalized: strip non-digits, prefix `+33` for 10-digit French numbers starting with `0`
- `search_chip` = raw 15-digit string stored as-is (no normalization)
- Composite index: `(clinic_id, search_name)` — clinic_id always first (IDOR + cardinality)

**DBAL UUID convention (F8 — confirmed mandatory):**
ALL DBAL queries against BINARY(16) UUID columns MUST convert manually. No Doctrine custom type handles this automatically.
```php
// CORRECT — parameters
$clinicBinary = Uuid::fromString($clinicId)->toBinary();
$conn->fetchAssociative('SELECT ... WHERE clinic_id = :clinicId', ['clinicId' => $clinicBinary]);

// CORRECT — reading results
$animalId = Uuid::fromBinary($row['id'])->toString();

// WRONG — passing raw UUID string to BINARY(16) column — silently returns 0 rows
$conn->fetchAssociative('SELECT ... WHERE id = :id', ['id' => $uuidString]); // DO NOT DO THIS
```
This pattern is used consistently in `DoctrineAnimalReadRepository` and `DoctrineClientReadRepository` — follow it exactly.

**Provider detection logic:**
```php
$term = $query->normalizedTerm(); // lowercased + accent-stripped
if ($normalizer->isChipNumber($query->term)) {
    // check ORIGINAL term (not normalized) — raw 15 digits
    // :chip = $query->term (raw), :clinicId = Uuid::fromString($query->clinicId)->toBinary()
    WHERE clinic_id = :clinicId AND search_chip = :chip
} elseif ($normalizer->isPhone($query->term)) {
    // :phone = $normalizer->normalizePhone($query->term)
    WHERE clinic_id = :clinicId AND search_phone = :phone
} else {
    // :prefix = $term . '%', :clinicId = toBinary()
    WHERE clinic_id = :clinicId AND search_name LIKE :prefix
}
```

**Fetch +1 pattern (F9 — single calling convention):**
`SearchQuery::withLimit(int): self` returns an immutable clone. The repository reads `$query->limit` directly.
`SearchEngine::MAX_HITS_PER_PROVIDER = 20` is defined on `SearchEngine` (not on providers). The engine passes a modified query to the provider via `$query->withLimit(self::MAX_HITS_PER_PROVIDER + 1)`.
```php
// In SearchEngine::searchAll():
$capped = $query->withLimit(self::MAX_HITS_PER_PROVIDER + 1);
$bucket = $provider->search($capped);
$hasMore = count($bucket->hits) > self::MAX_HITS_PER_PROVIDER;
$hits = array_slice($bucket->hits, 0, self::MAX_HITS_PER_PROVIDER);

// In DoctrineAnimalSearchRepository::findByQuery(SearchQuery $query):
$limit = $query->limit; // already set to MAX+1 by engine
// SELECT ... LIMIT :limit  (use $query->limit directly)
```

**New events are SYNCHRONOUS — messenger.yaml note:**
`AnimalIdentityChanged` and `AnimalOwnersReplaced` are dispatched synchronously on `messenger.bus.event`. No routing in `messenger.yaml` needed. No async transport. Outbox pattern is V2. This must be explicit to prevent a dev adding async routing by mistake.

**pickerId scoping for mode=pick:**
When the `global-search` controller is in `mode=pick`, it accepts a `pickerIdValue` Stimulus value (a unique ID generated by the calling component). This value is passed as `?pickerId=` query param to the search endpoint and embedded in all result buttons as `data-picker-id`. When a hit is picked, the CustomEvent detail includes `pickerId`. The calling component listens on its own element with a guard:
```js
document.getElementById('search-modal').addEventListener('vetsaas:search:pick', (e) => {
  if (e.detail.pickerId !== this.myPickerId) return; // ignore picks for other callers
  // prefill form
}, { once: true });
```
This prevents two simultaneous pickers from interfering.

### Files to Reference

| File | Purpose |
|------|---------|
| `src/Context/Animal/Domain/Animal.php` | Add AnimalIdentityChanged + AnimalOwnersReplaced emission |
| `src/Context/Animal/Domain/Event/AnimalCreated.php` | Pattern for new event classes (BOUNDED_CONTEXT, VERSION, payload(), aggregateId()) |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | recordDomainEvent(), pullDomainEvents() |
| `src/Shared/Domain/Event/AbstractDomainEvent.php` | Base for new events |
| `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php` | DBAL query pattern for DoctrineAnimalSearchRepository |
| `src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DoctrineClientReadRepository.php` | DBAL JOIN pattern (search_phone replaces the JOIN in the new index) |
| `src/Presentation/Clinic/Controller/Api/Clinic/Clients/SearchClientsApiController.php` | Rate limiter binding pattern to replicate on SearchController |
| `templates/components/layout/search_modal.html.twig` | **DO NOT MODIFY** until structure validated |
| `assets/controllers/client_search_autocomplete_controller.js` | Debounce + ARIA + AbortController pattern → replicate in global-search |
| `config/packages/messenger.yaml` | Verify no routing added for new domain events |
| `config/services.yaml` | Tag providers, bind rate limiter |

### Technical Decisions (locked — post party-mode review)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Read model isolation | Dedicated `*_search_index` tables, CREATE only | Zero impact on aggregate tables |
| Flush strategy | Writer calls `$em->flush()` (17 repos not refactored) | Isolated; refactor of 17 repos out of scope |
| Search behavior | Prefix LIKE on `search_*` columns | Indexable B-tree, covers 90% real usage |
| Hyphen/apostrophe normalization | Replace with spaces in `search_*` only | `Jean-Pierre` → `jean pierre`; display col untouched |
| Public IDs | YAGNI V2 — resourceId = UUID | Belongs in aggregate, not projection |
| `isExternal()` on provider | Added to interface, default false | 800ms timeout only for external (I-CAD ready) |
| Timeout | Local = no timeout, external = 800ms | Trust MySQL + our indexes |
| Timeout on local | No timeout guard for local providers | MySQL query with indexed prefix LIKE ≤ 5ms expected |
| Writer methods | `upsert()`, `updateName()`, `updateChip()`, `updateOwnerName()`, `markArchived()`, `delete()` | One method = one intention; no ambiguous partial upserts |
| ownerName nullable | `search_owner_name VARCHAR(255) NULL` | Animals may exist without linked owner |
| New events sync | `AnimalIdentityChanged`, `AnimalOwnersReplaced` dispatch on `messenger.bus.event`, no async routing | Same-UoW, outbox V2 |
| MAX_HITS_PER_PROVIDER | 20 hardcoded constant in `SearchEngine` | YAGNI config; +1 fetch for hasMore |
| Mode validation | SearchController validates `mode ∈ {navigate, pick}` → 400 otherwise | No silent fallback |
| pickerId | Caller-generated UUID in Stimulus value, carried in CustomEvent detail | Prevents cross-picker interference |
| Existing search endpoints | Keep untouched in V1 | Audit first, deprecation in separate PR |
| Cmd+K | Bound in `global-search` controller `connect()` | No modal.js change needed |

---

## Implementation Plan

> **⚠️ MANDATORY PHASE ORDER — read before starting**
>
> ```
> Phase 1 (Domain events)
>   → Phase 2 (Shared contracts)
>     → Phase 3 + Phase 4 in parallel (Animal + Client BC projection + index)
>       → Phase 5 + Phase 6 in parallel (HTTP controller + Stimulus controller)
>         → Phase 7 (modal wiring — BLOCKED, see Task 7.2)
>           → Phase 8 (tests)
> ```
> A fresh dev agent **MUST** complete phases in this order. Starting Phase 5 without Phase 2 will break imports. Starting Phase 3 without Phase 1 means handlers listen to non-existent events.

### Phase 1 — Animal aggregate: 2 new domain events

**Task 1.1** `src/Context/Animal/Domain/Event/AnimalIdentityChanged.php`
- `extends AbstractDomainEvent`, `BOUNDED_CONTEXT = 'animal'`, `VERSION = 1`
- Constructor: `string $animalId, string $clinicId, ?string $microchipNumber, ?string $tattooNumber, ?string $passportNumber`
- Carries NEW values (not diff) — handler does not reload aggregate
- `aggregateId()` → `$this->animalId`
- `payload()` → all 5 fields

**Task 1.2** `src/Context/Animal/Domain/Event/AnimalOwnersReplaced.php`
- Constructor: `string $animalId, string $clinicId, string $primaryOwnerClientId, list<string> $secondaryOwnerClientIds`

**Task 1.3** Normalize `Identification` VO + emit `AnimalIdentityChanged` from `Animal::updateIdentity()`

**Step A — Identification VO** (`src/Context/Animal/Domain/ValueObject/Identification.php`):
The `Identification` constructor has **6 parameters** (confirmed audit):
```php
public function __construct(
    public ?string $microchipNumber,  // normalize '' → null
    public ?string $tattooNumber,     // normalize '' → null
    public ?string $passportNumber,   // normalize '' → null
    public RegistryType $registryType,
    public ?string $registryNumber,   // normalize '' → null (F26 — uniform rule)
    public ?string $sireNumber,       // normalize '' → null (F26 — uniform rule)
)
```
Add `$this->field = $field === '' ? null : $field` normalization for all 5 optional string fields (F26 decision: uniform rule for all optional strings in this VO).

**Step B — updateIdentity()** insertion point (F18 — exact location):
In `Animal::updateIdentity()`, the existing code assigns at a block starting with `$this->name = $name;` (approx. line 249 of the current file). Insert the `AnimalIdentityChanged` emission BETWEEN the `$identification->ensureConsistency()` call and the `$this->name = $name` assignment line:
```php
// AFTER: $identification->ensureConsistency();
// INSERT HERE:
if ($this->identification->microchipNumber !== $identification->microchipNumber
    || $this->identification->tattooNumber !== $identification->tattooNumber
    || $this->identification->passportNumber !== $identification->passportNumber) {
    $this->recordDomainEvent(new AnimalIdentityChanged(
        animalId: $this->id->toString(),
        clinicId: $this->clinicId->toString(),
        microchipNumber: $identification->microchipNumber,
        tattooNumber: $identification->tattooNumber,
        passportNumber: $identification->passportNumber,
    ));
}
// THEN: $this->name = $name; (existing code continues)
```
Crucially, the event uses `$identification->microchipNumber` (the NEW value from the parameter), NOT `$this->identification->microchipNumber` (old value). The assignment `$this->identification = $identification` must happen AFTER this block.

**Task 1.4** Emit `AnimalOwnersReplaced` from `Animal::replaceOwners()` (F25 decision)
- Use `$primaryOwnerClientId` and `$secondaryOwnerClientIds` **directly from the method parameters** — NOT extracted from `$this->ownerships` after mutation (that would require a re-filter of the array, error-prone).
- Record event AFTER `$this->ownerships = ...` is updated but using the original parameters.
```php
$this->recordDomainEvent(new AnimalOwnersReplaced(
    animalId: $this->id->toString(),
    clinicId: $this->clinicId->toString(),
    primaryOwnerClientId: $primaryOwnerClientId,
    secondaryOwnerClientIds: $secondaryOwnerClientIds,
));
```

**Task 1.5** Tests for new events + Identification normalization
- `tests/Unit/Context/Animal/Domain/AnimalTest.php`: add 3 tests:
  - `testUpdateIdentityEmitsAnimalIdentityChangedWhenChipChanges()`
  - `testUpdateIdentityDoesNotEmitAnimalIdentityChangedWhenNothingChanges()`
  - `testReplaceOwnersEmitsAnimalOwnersReplaced()`
- `tests/Unit/Context/Animal/Domain/ValueObject/IdentificationTest.php`: add tests (F2 — correct 6-param constructor):
  - `testEmptyStringChipNormalizedToNull()` — `new Identification(microchipNumber: '', tattooNumber: null, passportNumber: null, registryType: RegistryType::NONE, registryNumber: null, sireNumber: null)` → `microchipNumber === null`
  - `testAllOptionalStringsNormalizedToNull()` — all 5 optional fields with `''` → all null (F26)

### Phase 2 — Shared/Search contracts

**Task 2.1** `src/Shared/Search/Query/SearchQuery.php`
```php
final readonly class SearchQuery {
    public function __construct(
        public string $term,
        public string $clinicId,
        public string $userId,
        public int $limit = 10,
    ) {}
    public function normalizedTerm(): string { /* SearchTermNormalizer::normalizeText */ }
    public function isExecutable(): bool { return mb_strlen(trim($this->term)) >= 2; }
    public function withLimit(int $limit): self { return new self($this->term, $this->clinicId, $this->userId, $limit); }
}
```

**Task 2.2** `src/Shared/Search/Result/SearchHit.php`
```php
final readonly class SearchHit {
    public function __construct(
        public string $id,           // internal UUID
        public string $resourceId,   // UUID for URL generation via Twig path()
        public string $title,
        public ?string $subtitle,
        public ?string $context,     // e.g. owner name for an animal hit
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
```

**Task 2.3** `src/Shared/Search/Result/SearchBucket.php`
```php
final readonly class SearchBucket {
    public function __construct(
        public string $type,
        /** @var list<SearchHit> */
        public array $hits,
        public bool $hasMore,
    ) {}
}
```

**Task 2.4** `src/Shared/Search/SearchProviderInterface.php`
```php
interface SearchProviderInterface {
    public function supports(SearchQuery $query): bool;
    public function search(SearchQuery $query): SearchBucket;
    public function resultType(): string;
    public function isExternal(): bool; // default false for local MySQL providers
}
```

**Task 2.5** `src/Shared/Search/Normalization/SearchTermNormalizer.php`
```php
final class SearchTermNormalizer {
    /**
     * Normalize for storage/search: iconv TRANSLIT first (removes accents preserving case),
     * then strtolower, then hyphens/apostrophes → spaces, then collapse whitespace, then trim.
     * F16/F17: correct order is iconv THEN lowercase.
     * F17: iconv() may return false on invalid UTF-8 — throw \InvalidArgumentException in that case.
     */
    public function normalizeText(string $term): string;

    /**
     * Normalize a phone number to E.164 format.
     * Strips non-digit chars; prefixes +33 for 10-digit FR numbers starting with 0.
     * Returns null if the result has fewer than 7 digits (not a phone).
     */
    public function normalizePhone(string $term): ?string;

    /** Returns true if $term matches /^\d{15}$/ — checks ORIGINAL (not normalized) term. */
    public function isChipNumber(string $term): bool;

    /** Returns true if $term looks like a phone (≥7 digits after stripping separators). */
    public function isPhone(string $term): bool;

    /** Returns true if mb_strlen(trim($term)) >= 2. */
    public function isExecutable(string $term): bool;
}
```

**Task 2.6** `src/Shared/Search/SearchEngine.php` (F20 — PHPStan max compatible)
```php
final class SearchEngine {
    public const int MAX_HITS_PER_PROVIDER = 20;
    private const int EXTERNAL_TIMEOUT_MS = 800;

    /**
     * @param iterable<SearchProviderInterface> $providers
     * @param list<string> $bucketOrder
     */
    public function __construct(
        private iterable $providers,
        private array $bucketOrder,    // @var list<string>
    ) {}

    /** @return list<SearchBucket> — ordered by $bucketOrder, empty buckets excluded */
    public function searchAll(SearchQuery $query): array;
    public function searchOne(SearchQuery $query, string $type): ?SearchBucket;
}
```
- `searchAll()` passes `$query->withLimit(self::MAX_HITS_PER_PROVIDER + 1)` to each provider (F9 — single convention)
- Provider receives a `SearchQuery` with `limit = MAX+1`; DoctrineRepository reads `$query->limit` directly
- For `isExternal()=true`: best-effort timeout via microtime check (PHP has no native per-call preemptive timeout in V1)
- For local providers: no timeout. Trust MySQL + indexes.
- Results ordered by `$bucketOrder`; providers not in bucketOrder appended at end
- PHPDoc annotations `@var list<string>` on `$bucketOrder` needed for PHPStan max

### Phase 3 — Animal search index + projection

**Task 3.1** `src/Context/Animal/Infrastructure/Search/Index/AnimalSearchIndex.php`
```php
#[ORM\Entity]
#[ORM\Table(name: 'animal_search_index')]
#[ORM\Index(name: 'idx_animal_srch_name', columns: ['clinic_id', 'search_name'])]
#[ORM\Index(name: 'idx_animal_srch_chip', columns: ['clinic_id', 'search_chip'])]
class AnimalSearchIndex {
    #[ORM\Id, ORM\Column(type: UuidType::NAME)] private Uuid $id;          // = animal UUID
    #[ORM\Column(type: UuidType::NAME)] private Uuid $clinicId;
    #[ORM\Column(length: 255)] private string $animalName;                  // display name
    #[ORM\Column(length: 255)] private string $searchName;                  // normalized
    #[ORM\Column(length: 64, nullable: true)] private ?string $searchChip;  // raw 15 digits
    #[ORM\Column(length: 50)] private string $species;
    #[ORM\Column(length: 255, nullable: true)] private ?string $breedName;
    #[ORM\Column(length: 255, nullable: true)] private ?string $searchOwnerName;  // nullable — animal may have no owner
    #[ORM\Column(type: UuidType::NAME, nullable: true)] private ?Uuid $primaryOwnerClientId;
    #[ORM\Column(length: 20)] private string $status;                       // 'active' | 'archived'
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $updatedAt;
    // getters + setters
}
```

**Task 3.2** Doctrine migration: `CREATE TABLE animal_search_index (...)` — no ALTER on any existing table

**Task 3.3** `AnimalSearchIndexData` DTO
```php
final readonly class AnimalSearchIndexData {
    public function __construct(
        public string $animalId, public string $clinicId,
        public string $animalName, public string $species, public ?string $breedName,
        public ?string $chipNumber,
        public ?string $ownerName,          // nullable: animal without owner → null stored
        public ?string $primaryOwnerClientId,
        public string $status,
    ) {}
}
```

**Task 3.4** `AnimalSearchIndexWriter` (F3/F13 — aligned method list, flush rules explicit)
```php
final readonly class AnimalSearchIndexWriter {
    // upsert: ORM find+update or persist new; normalize searchName via SearchTermNormalizer; $em->flush()
    public function upsert(AnimalSearchIndexData $data): void;

    // updateName: ORM load by animalId+clinicId, set animalName + searchName (normalized), $em->flush()
    public function updateName(string $animalId, string $clinicId, string $newName): void;

    // updateChip: ORM load by animalId+clinicId, set searchChip ($chip ?: null defense-in-depth), $em->flush()
    public function updateChip(string $animalId, string $clinicId, ?string $chip): void;

    // updateOwner: ORM load by animalId+clinicId, set primaryOwnerClientId + searchOwnerName, $em->flush()
    // Used by OnAnimalOwnersReplaced — single animal update
    public function updateOwner(string $animalId, string $clinicId, ?string $ownerClientId, ?string $ownerName): void;

    // updateOwnerName: DBAL bulk UPDATE — NO $em->flush() (DBAL executeStatement bypasses ORM)
    // Used by OnClientIdentityUpdated (cross-context) — updates ALL animals for a given client
    // SQL: UPDATE animal_search_index SET search_owner_name = :name
    //       WHERE primary_owner_client_id = :clientBinary AND clinic_id = :clinicBinary
    public function updateOwnerName(string $clientId, string $clinicId, string $newOwnerName): void;

    // markArchived: ORM load by animalId+clinicId, set status='archived', $em->flush()
    // (NOT delete — keeps the row for audit; search queries filter status != 'archived')
    public function markArchived(string $animalId, string $clinicId): void;

    // delete: ORM remove by animalId+clinicId, $em->flush() — reserved for hard delete scenarios
    public function delete(string $animalId, string $clinicId): void;
}
```
**Flush rules (F13 resolved):**
- Methods 1–4, 6, 7: ORM operations → call `$em->flush()` at end of each method
- Method 5 `updateOwnerName()`: DBAL `executeStatement` → NO `$em->flush()` (already sent to DB directly)
- `testFlushCalledOnEveryWriteMethod()` in Task 8.4 EXCLUDES `updateOwnerName()` — test explicitly asserts `flush` NOT called for that method
- `upsert()` uses ORM find+update or persist (not DBAL INSERT OR REPLACE — maintains Doctrine identity map)

**Task 3.5** `OnAnimalCreated.php` — listens `AnimalCreated`, calls `writer->upsert()`
- `#[AsMessageHandler(bus: 'messenger.bus.event')]`
- `AnimalCreated::payload()` keys: `animalId`, `clinicId`, `name`, `primaryOwnerClientId` — NO species/breed/chip
- Access via `$payload = $event->payload(); $animalId = $payload['animalId']; ...`
- **F8 DBAL reads** (required — use `->toBinary()` for ALL UUID params):
  ```php
  $animalBinary = Uuid::fromString($payload['animalId'])->toBinary();
  $row = $conn->fetchAssociative(
      'SELECT species, breed_name, microchip_number FROM animal__animals WHERE id = :id',
      ['id' => $animalBinary]
  );
  $ownerBinary = $payload['primaryOwnerClientId']
      ? Uuid::fromString($payload['primaryOwnerClientId'])->toBinary()
      : null;
  $ownerRow = $ownerBinary ? $conn->fetchAssociative(
      "SELECT CONCAT(first_name, ' ', last_name) as name FROM client__clients WHERE id = :id",
      ['id' => $ownerBinary]
  ) : null;
  $ownerName = $ownerRow ? $ownerRow['name'] : null; // null if no owner (archived or missing)
  ```
- Call `writer->upsert(new AnimalSearchIndexData(...))` with all fields

**Task 3.6** `OnAnimalArchived.php` — listens `AnimalArchived`, calls `writer->markArchived(animalId, clinicId)`
- `$payload = $event->payload(); $animalId = $payload['animalId']; $clinicId = $payload['clinicId'];`
- Sets `status = 'archived'` in index; animal no longer returned in live search

**Task 3.7** `OnAnimalNameChanged.php` — listens `AnimalNameChanged`, calls `writer->updateName(animalId, clinicId, newName)`
- `$payload = $event->payload(); $newName = $payload['newName'];` — event carries newName directly; no DBAL read needed

**Task 3.8** `OnAnimalIdentityChanged.php` — listens `AnimalIdentityChanged`, calls `writer->updateChip(animalId, clinicId, microchipNumber)`
- `$payload = $event->payload(); $chip = $payload['microchipNumber'];` (may be null if chip removed)
- Only chip is indexed in V1 (tattoo/passport not searchable by pattern)
- Writer applies defense-in-depth: `$chip ?: null`

**Task 3.9** `OnAnimalOwnersReplaced.php` — listens `AnimalOwnersReplaced`, calls `writer->updateOwner(animalId, clinicId, ownerClientId, ownerName)`
- `$payload = $event->payload(); $primaryOwnerId = $payload['primaryOwnerClientId'];`
- **F8 DBAL read** for owner name:
  ```php
  $ownerBinary = Uuid::fromString($primaryOwnerId)->toBinary();
  $ownerRow = $conn->fetchAssociative(
      "SELECT CONCAT(first_name, ' ', last_name) as name FROM client__clients WHERE id = :id",
      ['id' => $ownerBinary]
  );
  $ownerName = $ownerRow ? $ownerRow['name'] : null;
  ```
- Archived owner is still found — store name regardless (no status filter on this read)
- Call `writer->updateOwner($animalId, $clinicId, $primaryOwnerId, $ownerName)` (defined in Task 3.4)

**Task 3.10** `OnClientIdentityUpdated.php` — listens `ClientIdentityUpdated` (cross-context, in Animal BC), calls `writer->updateOwnerName(clientId, clinicId, newName)`
- `#[AsMessageHandler(bus: 'messenger.bus.event')]`
- **F4 — use payload()**: `$payload = $event->payload(); $firstName = $payload['firstName']; $lastName = $payload['lastName']; $clientId = $payload['clientId']; $clinicId = $payload['clinicId'];`
- Build: `$newName = $firstName . ' ' . $lastName;`
- `writer->updateOwnerName($clientId, $clinicId, $newName)`
- This does DBAL bulk UPDATE with `Uuid::fromString($clientId)->toBinary()` and `Uuid::fromString($clinicId)->toBinary()` — NO `$em->flush()` (DBAL executeStatement only)

### Phase 4 — Client search index + projection

**Task 4.1** `ClientSearchIndex` entity (`client_search_index` table)
```
Columns: id (UUID=client UUID), clinicId (UUID), firstName (VARCHAR 255), lastName (VARCHAR 255),
         searchName (VARCHAR 255 = normalizeText(firstName . ' ' . lastName)),
         searchPhone (VARCHAR 32 NULL = E.164 primary phone),
         primaryEmail (VARCHAR 255 NULL),
         status ('active'|'archived'),
         updatedAt (datetime_immutable)
Indexes: (clinic_id, search_name), (clinic_id, search_phone)
```

**Task 4.2** Migration + `ClientSearchIndexData` DTO + `ClientSearchIndexWriter`
- Writer methods: `upsert(ClientSearchIndexData)`, `updateContactMethods(clientId, clinicId, ?phone, ?email)`, `markArchived()`, `markActive()`
- All writer methods call `$em->flush()`
- `upsert()` normalizes searchName via `SearchTermNormalizer::normalizeText`

**Task 4.2** Migration + `ClientSearchIndexWriter` (F11 — resolved, methods listed below):
```php
final readonly class ClientSearchIndexWriter {
    // upsert: ORM find+update or persist; normalizes searchName; $em->flush()
    public function upsert(ClientSearchIndexData $data): void;
    // updateName: ORM load, set firstName/lastName/searchName, $em->flush()
    public function updateName(string $clientId, string $clinicId, string $firstName, string $lastName): void;
    // updateContactMethods: ORM load, set searchPhone + primaryEmail from new contact methods, $em->flush()
    public function updateContactMethods(string $clientId, string $clinicId, ?string $phone, ?string $email): void;
    // markArchived: ORM load, set status='archived', $em->flush()
    public function markArchived(string $clientId, string $clinicId): void;
    // markActive: ORM load, set status='active', $em->flush()
    public function markActive(string $clientId, string $clinicId): void;
}
```

**Task 4.3** `OnClientCreated.php` — listens `ClientCreated`, calls `writer->upsert()`
- `$payload = $event->payload();`
- Extract primary phone: `array_filter($payload['contactMethods'], fn($m) => $m['type'] === 'phone' && $m['isPrimary'] === true)`
- `ContactMethodType::PHONE->value === 'phone'` (confirmed)
- Call `writer->upsert(new ClientSearchIndexData(...))`

**Task 4.4** `OnClientIdentityUpdated.php` — listens `ClientIdentityUpdated`, calls `writer->updateName()`
- **F4 + F11 resolved**: `$payload = $event->payload(); $firstName = $payload['firstName']; $lastName = $payload['lastName'];`
- Call `writer->updateName($clientId, $clinicId, $firstName, $lastName)` — preserves phone/email (no upsert needed)

**Task 4.5** `OnClientContactMethodsReplaced.php` — listens `ClientContactMethodsReplaced`, calls `writer->updateContactMethods()`
- `$payload = $event->payload();`
- Extract primary phone/email from `$payload['contactMethods']` (same pattern as Task 4.3)
- Call `writer->updateContactMethods($clientId, $clinicId, $phone, $email)`

**Task 4.6** `OnClientArchived.php` — listens `ClientArchived`, calls `writer->markArchived($clientId, $clinicId)`
- Access via `$payload = $event->payload(); $clientId = $payload['clientId'];`

**Task 4.7** `OnClientUnarchived.php` — listens `ClientUnarchived`, calls `writer->markActive($clientId, $clinicId)` (F12 — test added in Task 8.3)

### Phase 5 — Search providers

**Task 5.1** `AnimalSearchRepositoryInterface` (in `Application/Search/`)
```php
interface AnimalSearchRepositoryInterface {
    // F9 — single calling convention: $query->limit is already set to MAX+1 by SearchEngine
    /** @return list<AnimalSearchRow> */
    public function findByQuery(SearchQuery $query): array;
}
```

**Task 5.2** `AnimalSearchRow` DTO (in `Application/Search/`)

**Task 5.3** `AnimalSearchProvider`
- `resultType()` → `'animal'`, `isExternal()` → `false`
- `supports()` → `$normalizer->isExecutable($query->term)` (use injected SearchTermNormalizer)
- `search()` (F22 — `SearchEngine::MAX_HITS_PER_PROVIDER` not accessible here; engine handles capping via `withLimit`):
  - `$query->limit` already = `MAX_HITS_PER_PROVIDER + 1` (set by SearchEngine before calling provider)
  - Detect on ORIGINAL `$query->term`: chip → exact match, phone → E.164 exact, else → prefix LIKE on `$query->normalizedTerm()`
  - Call `$this->repo->findByQuery($query)` — repo reads `$query->limit` directly
  - `$hasMore = count($rows) > ($query->limit - 1)` (since limit was MAX+1, more than MAX means hasMore)
  - `$hits = array_slice($rows, 0, $query->limit - 1)`
  - Build `SearchHit[]`: `title=name`, `subtitle="species · breed"`, `context=ownerName ?? 'Sans propriétaire'`, `metadata=['status'=>..., 'species'=>...]`
  - Return `SearchBucket` with hasMore

**Task 5.4** `DoctrineAnimalSearchRepository` (in `Infrastructure/Search/`)
- DBAL on `animal_search_index`
- All queries include `AND status = 'active'` (archived hidden from live search)
- IDOR: `AND clinic_id = :clinicId` always present

**Task 5.5 — 5.8** Same pattern for Client BC: `ClientSearchRow`, `ClientSearchProvider`, `ClientSearchRepositoryInterface`, `DoctrineClientSearchRepository`

### Phase 6 — SearchEngine wiring + endpoint

**Task 6.1** `config/services.yaml` additions (F5 — use `$apiClinicSearchLimiter`, the global bind name):
```yaml
App\Shared\Search\SearchEngine:
    arguments:
        $providers: !tagged_iterator vetsaas.search_provider
        $bucketOrder: '%vetsaas.search.bucket_order%'

App\Context\Animal\Application\Search\AnimalSearchProvider:
    tags: ['vetsaas.search_provider']

App\Context\Client\Application\Search\ClientSearchProvider:
    tags: ['vetsaas.search_provider']
# No per-class bind needed for SearchController — global _defaults.bind already provides:
#   $apiClinicSearchLimiter: '@limiter.api_clinic_search'
# SearchController constructor MUST use the name $apiClinicSearchLimiter to match the global bind.
```

**Task 6.2** Add `vetsaas.search.bucket_order` parameter (F6 — in `config/services.yaml`, NOT `framework.yaml`):
```yaml
# In config/services.yaml, under the existing `parameters:` key at the top of the file:
parameters:
    vetsaas.search.bucket_order: ['animal', 'client']
    vetsaas.search.bucket_icons:
        animal: 'paw-print'
        client: 'user'
    vetsaas.search.route_map:
        animal: 'clinic_animal_view'
        client: 'clinic_clients_view'
```

**Task 6.3** `src/Presentation/Clinic/Controller/Search/SearchController.php`
```php
#[Route('/search', name: 'clinic_search', methods: ['GET'])]
final class SearchController extends AbstractController {
    public function __construct(
        private SearchEngine $searchEngine,
        private CurrentClinicContextInterface $currentClinicContext,
        private SearchTermNormalizer $normalizer,
        private RateLimiterFactoryInterface $apiClinicSearchLimiter, // F5: matches global bind name
        /** @var array<string,string> */
        #[Autowire(param: 'vetsaas.search.bucket_icons')] private array $bucketIcons,   // F14
        /** @var array<string,string> */
        #[Autowire(param: 'vetsaas.search.route_map')] private array $routeMap,          // F14
    ) {}

    public function __invoke(Request $request): Response {
        // 1. Rate limit check ($this->apiClinicSearchLimiter->create($user->id())->consume(1))
        // 2. Extract mode from query; validate mode ∈ {'navigate', 'pick'} → 400 if invalid (F28: absent = 'navigate')
        // 3. Extract q (string), type (?string), pickerId (?string)
        // 4. Build SearchQuery with currentClinicId + currentUserId
        // 5. If !$normalizer->isExecutable($q) → render empty _results partial (200)
        // 6. $query->withLimit() is handled by SearchEngine internally
        // 7. searchAll() or searchOne($type) depending on $type param
        // 8. render('clinic/search/_results.html.twig', [
        //        'buckets' => $buckets, 'mode' => $mode, 'pickerId' => $pickerId,
        //        'bucketIcons' => $this->bucketIcons, 'routeMap' => $this->routeMap
        //    ])
    }
}
```

**Task 6.4** `templates/clinic/search/_results.html.twig` (F19 — secure JSON encoding)
```twig
<turbo-frame id="search-results">
{% if buckets is empty %}
  {# empty state #}
{% else %}
  {% for bucket in buckets %}
    <div class="search-result-section" data-bucket-type="{{ bucket.type }}">
      <div class="search-result-section-head">
        <i data-lucide="{{ bucketIcons[bucket.type] ?? 'search' }}"></i>
        <span>{{ ('search.bucket.' ~ bucket.type)|trans }}</span>
      </div>
      {% for hit in bucket.hits %}
        {% if mode == 'pick' %}
          <button type="button" class="search-result"
                  data-action="click->global-search#pick"
                  data-hit="{{ hit|json_encode|e('html_attr') }}"
                  data-picker-id="{{ pickerId }}">
            {# F19: |e('html_attr') is REQUIRED — prevents JSON double-quotes from breaking the attribute #}
            <span class="search-result-icon">...</span>
            <div class="search-result-body">
              <p class="search-result-title">{{ hit.title }}</p>
              {% if hit.subtitle %}<p class="search-result-sub">{{ hit.subtitle }}</p>{% endif %}
              {% if hit.context %}<p class="search-result-sub">{{ hit.context }}</p>{% endif %}
            </div>
          </button>
        {% else %}
          <a href="{{ path(routeMap[bucket.type], {id: hit.resourceId}) }}" class="search-result">
            {# routeMap is passed from controller — 'animal' → 'clinic_animal_view', etc. #}
            ...
          </a>
        {% endif %}
      {% endfor %}
      {% if bucket.hasMore %}
        <div class="search-result-more">+ d'autres résultats</div>
      {% endif %}
    </div>
  {% endfor %}
{% endif %}
</turbo-frame>
```

### Phase 7 — Stimulus `global-search` controller + modal wiring

**Task 7.1** `assets/controllers/global_search_controller.js`
```js
export default class extends Controller {
  static targets = ['input', 'results', 'liveRegion'];
  static values  = {
    url:      { type: String },
    mode:     { type: String, default: 'navigate' },
    pickerId: { type: String, default: '' },
    minChars: { type: Number, default: 2 },
    debounce: { type: Number, default: 250 },
  };

  _debounceTimer = null;
  _abortController = null;

  connect() {
    this._onKeydown = this._handleKeydown.bind(this);
    document.addEventListener('keydown', this._onKeydown);
  }
  disconnect() {
    document.removeEventListener('keydown', this._onKeydown);
    this._abortController?.abort();
  }

  search() { /* debounce → fetch with AbortController */ }
  pick(event) {
    const hit = JSON.parse(event.currentTarget.dataset.hit);
    const pickerId = event.currentTarget.dataset.pickerId;
    this.element.dispatchEvent(new CustomEvent('vetsaas:search:pick', {
      detail: { hit, pickerId },
      bubbles: true,
    }));
  }
  _handleKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      // F23: use modal.js mechanism (data-modal-open attribute trigger), not direct classList manipulation
      // modal.js opens the modal when a click is dispatched on an element with data-modal-open="search-modal"
      // OR: call the same open function that modal.js uses. Investigate modal.js open API before implementing.
      // Safe fallback: dispatch a synthetic click on the sidebar search trigger button:
      document.querySelector('[data-modal-open="search-modal"]')?.click();
      this.hasInputTarget && this.inputTarget.focus();
    }
    if (e.key === 'Escape') { /* close modal via modal.js close mechanism */ }
    if (e.key === 'ArrowDown') { /* focus next .search-result */ }
    if (e.key === 'ArrowUp') { /* focus prev .search-result */ }
  }
  // fetch: builds URL with q + type + mode + pickerId; uses AbortController; updates results turbo-frame
  // F15: CustomEvent dispatched on this.element (controller root = #search-modal or its child)
  // The event bubbles: true ensures it reaches #search-modal for listener scoping
}
```

**Task 7.2 — Wire `search_modal.html.twig` to backend [BLOCKED — DO NOT IMPLEMENT IN THIS PR]**

> **STATUS: BLOCKED**
> The existing `search_modal.html.twig` structure must be reviewed and validated by Djamil before backend wiring. A separate ticket will be created after validation.
>
> **Until then:**
> - The `/search` endpoint exists and returns valid HTML (Phase 5/6 complete)
> - The `global-search` Stimulus controller exists and works (Phase 6 complete)
> - But they are NOT yet connected to the existing modal shell
>
> **A fresh dev agent MUST skip this task and report completion of all other tasks.** Djamil will create the wiring ticket separately.

When the wiring ticket is created, the implementation will:
- Replace `data-controller="search-center"` → `data-controller="global-search"`
- Add `data-global-search-url-value="{{ path('clinic_search') }}"`
- The `<turbo-frame id="search-results">` replaces the static suggestions section
- Keep filter pills → `data-action="click->global-search#filterType"`
- Keep static Récents section → shown only when input is empty
- `search-center` controller removed from this element (keep the JS file for other potential uses)

**Task 7.3** Translations (`translations/messages.fr.yaml`):
```yaml
search.bucket.animal: 'Animaux'
search.bucket.client: 'Clients'
```

### Phase 8 — Tests

**Task 8.1** `tests/Unit/Shared/Search/Normalization/SearchTermNormalizerTest.php`
Full case table:
```php
// Accents FR
['rémi', 'remi'], ['RÉMI', 'remi'], ['Rémi', 'remi'], ['RéMi', 'remi'],
['é è ê ë', 'e e e e'], ['à â', 'a a'], ['ô', 'o'], ['ù ü', 'u u'], ['ç', 'c'],
// Accents ES
['García', 'garcia'], ['José', 'jose'], ['Ñoño', 'nono'],
// Accents DE
['Müller', 'muller'], ['Öffner', 'offner'],
// ß special case — iconv TRANSLIT: 'ß' → 'ss'
['Straße', 'strasse'],
// Hyphens and apostrophes
['Jean-Pierre', 'jean pierre'], ["O'Brien", 'o brien'], ['Saint-Étienne', 'saint etienne'],
// Multiple spaces
['  Marie    Curie  ', 'marie curie'],
// isChipNumber
['250269802120045', true], // 15 digits → true
['25026980212004', false], // 14 digits → false
['2502698021200456', false], // 16 digits → false
['25026980212004X', false], // contains letter → false
['000000000000000', true], // all zeros valid pattern
// isPhone
['+33612345678', true], ['06 12 34 56 78', true], ['0612345678', true],
['123', false], // too short
// normalizePhone E.164
['0612345678', '+33612345678'], ['06.12.34.56.78', '+33612345678'],
['+33612345678', '+33612345678'],
// isExecutable
['ab', true], ['a', false], ['  a  ', false],
```

**Task 8.2** `tests/Unit/Context/Animal/Domain/AnimalTest.php` (extend):
- `testUpdateIdentityEmitsAnimalIdentityChangedWhenChipChanges()`
- `testUpdateIdentityDoesNotEmitAnimalIdentityChangedWhenNothingChanges()`
- `testReplaceOwnersEmitsAnimalOwnersReplaced()`

**Task 8.3** Projection handler tests (1 each, mock writer) (F12 — complete list):
- `OnAnimalCreatedTest` — verify `upsert()` called with correct AnimalSearchIndexData
- `OnAnimalArchivedTest` — verify `markArchived()` called with correct ids
- `OnAnimalNameChangedTest` — verify `updateName()` called; reads from `$event->payload()['newName']`
- `OnAnimalIdentityChangedTest` — verify `updateChip()` called with `payload()['microchipNumber']`
- `OnAnimalOwnersReplacedTest` — verify `updateOwner()` called; DBAL lookup mocked
- `OnClientIdentityUpdatedTest` (cross-context — in Animal BC) — verify `updateOwnerName()` via `payload()['firstName']`
- `OnClientCreatedTest` — verify `upsert()` with phone extraction from contactMethods
- `OnClientIdentityUpdatedTest` (in Client BC) — verify `updateName()` via `payload()`
- `OnClientContactMethodsReplacedTest` — verify `updateContactMethods()` with correct phone/email
- `OnClientArchivedTest` — verify `markArchived()`
- `OnClientUnarchivedTest` — verify `markActive()` (F12 — previously missing)

**Task 8.4** `tests/Unit/Context/Animal/Infrastructure/Search/AnimalSearchIndexWriterTest.php` (F27):
- `testUpsertInsertsWhenNotExists()`
- `testUpsertUpdatesWhenExists()`
- `testMarkArchivedSetsStatusAndFlushes()` — verifies flush called
- `testFlushCalledForOrmMethods()` — flush IS called for: `upsert`, `updateName`, `updateChip`, `updateOwner`, `markArchived`, `delete`
- `testFlushNotCalledForUpdateOwnerName()` — flush is NOT called for `updateOwnerName()` (DBAL path); F27 resolved

**Task 8.5** `tests/Unit/Context/Animal/Application/Search/AnimalSearchProviderTest.php` (F24 — phone test added):
- `testChipDetectionUsesExactMatch()`
- `testPhoneDetectionUsesE164ExactMatch()` — F24: tests `WHERE search_phone = :e164` path
- `testTextTermUsesPrefixLike()`
- `testIsExecutableGuardReturnsEmptyBucket()` — use 1-char term e.g. `"m"`, NOT `"ma"` (F30)
- `testHasMoreReturnedWhenLimitPlusOneFound()`
- `testIsExternalReturnsFalse()`

**Task 8.6** `tests/Integration/Context/Animal/Infrastructure/Search/DoctrineAnimalSearchRepositoryTest.php`:
- `testSearchByNameReturnsHits()`
- `testSearchByChipReturnsExactMatch()`
- `testSearchByPhoneReturnsExactMatch()` — ensures BINARY UUID conversion works
- `testSearchRespectsClinicIdIsolation()` — IDOR test: same name different clinic = empty
- `testArchivedAnimalsNotReturned()`
- `testSearchByNameNormalizesAccents()` — search "remi" matches row with searchName "remi"

**Task 8.7** `tests/Integration/Context/Animal/Infrastructure/Search/AnimalSearchIndexWriterTransactionTest.php` (F7):
- `testRollbackOnExceptionAfterUpsert()` — KernelTestCase + real transaction:
  1. Begin explicit transaction
  2. Create animal via command (aggregat write + projection write both committed)
  3. Confirm row exists in both `animal__animals` AND `animal_search_index`
  4. Run another command that throws after projection upsert
  5. Rollback is triggered by exception propagation
  6. Assert the animal from step 4's partial work is NOT in `animal_search_index`
  This test proves the transactional guarantee that is the architectural foundation of the sync write model.

---

## Acceptance Criteria

### Animal domain events
- **Given** `Animal::updateIdentity()` called with chip `250269802120045` when current chip is `null` → **Then** `AnimalIdentityChanged` emitted carrying the new chip value
- **Given** `Animal::updateIdentity()` called with chip `X` when current chip is already `X` → **Then** `AnimalIdentityChanged` NOT emitted (no unnecessary event)
- **Given** `Animal::replaceOwners()` called → **Then** `AnimalOwnersReplaced` emitted with correct primaryOwnerClientId

### SearchTermNormalizer
- **Given** term `"RÉMI"` → `normalizeText()` returns `"remi"`
- **Given** term `"Jean-Pierre"` → `normalizeText()` returns `"jean pierre"`
- **Given** term `"O'Brien"` → `normalizeText()` returns `"o brien"`
- **Given** term `"250269802120045"` → `isChipNumber()` returns `true`
- **Given** term `"25026980212004"` (14 digits) → `isChipNumber()` returns `false`

### Identification VO normalization
- **Given** `new Identification(microchipNumber: '', tattooNumber: null, passportNumber: null, registryType: RegistryType::NONE, registryNumber: null, sireNumber: null)` → **Then** `$identification->microchipNumber === null` (F2 — correct 6-param constructor)
- **Given** `Animal::updateIdentity()` called with chip `''` when current chip is `null` → **Then** `AnimalIdentityChanged` NOT emitted (both normalize to null, no change)
- **Given** animal created with empty string chip → **Then** indexed with `search_chip = null`; not searchable by chip until a real chip is assigned

### Projection consistency
- **Given** `AnimalCreated` event → **When** `OnAnimalCreated` runs → **Then** `animal_search_index` row exists with `search_name` = accent-stripped lowercase name
- **Given** `ClientIdentityUpdated` event for client C → **When** `OnClientIdentityUpdated` (Animal BC handler) runs → **Then** `search_owner_name` updated for ALL animals of client C in that clinic
- **Given** `AnimalArchived` event → **Then** `status = 'archived'` in index; live search returns empty for that animal
- **Given** animal without linked owner → **Then** indexed with `search_owner_name = null`; searchable by name/chip
- **Given** `AnimalOwnersReplaced` where the new owner is archived → **When** `OnAnimalOwnersReplaced` runs → **Then** owner's name still stored in `search_owner_name` (archived status does not prevent name storage)

### Search accuracy
- **Given** term `"remi"` → **Then** provider returns animals/clients whose `search_name` starts with `"remi"`
- **Given** term `"RÉMI"` → **Then** provider normalizes to `"remi"` and returns matching rows (uppercase + accent stripped)
- **Given** term `"250269802120045"` (15 digits) → **Then** provider does exact `search_chip =` match, not LIKE
- **Given** term `"+33612345678"` (E.164 phone) → **Then** provider does exact `search_phone =` match
- **Given** term `"m"` (1 char) → **Then** `isExecutable()` = false; empty bucket returned WITHOUT DB query (F30)
- **Given** term `"ma"` (2 chars) → **Then** `isExecutable()` = true; prefix LIKE search proceeds
- **Given** MAX_HITS_PER_PROVIDER = 20 and 21 matching rows → **Then** `hasMore = true`, exactly 20 hits returned

### IDOR safety
- **Given** search query for clinic A → **Then** zero results from clinic B regardless of term match

### SearchController validation
- **Given** `GET /search?q=foo&mode=invalid` → **Then** HTTP 400 Bad Request
- **Given** `GET /search?q=f` (1 char) → **Then** 200 with empty results partial (no DB query)
- **Given** `GET /search?q=foo` (no `mode` param) → **Then** defaults to `navigate` mode; 200 OK (F28)

### Known limitations (F10 / F29 — documented, ticket required)
- **Given** a primary owner is archived and `resolveOwnershipForArchivedClient()` promotes a secondary owner → **Then** the search index is NOT updated in this PR (no `AnimalOwnersReplaced` event emitted from that code path). The animal will continue showing the archived owner's name in search results until the next `AnimalOwnersReplaced` event. **Fix tracked in a separate ticket.**
- **Given** `AnimalArchived` is emitted from `resolveOwnershipForArchivedClient()` → **Then** that path is handled by `OnAnimalArchived` → animal is correctly marked archived in search index. ✅

### Mode pick + pickerId
- **Given** `global-search` in mode `pick` with `pickerId=picker-abc123` → **When** user selects a result → **Then** `vetsaas:search:pick` CustomEvent dispatched on `.search-modal` element with `detail.pickerId = 'picker-abc123'`
- **Given** two pickers open simultaneously with different pickerIds → **Then** each picker receives only its own selections

### Cmd+K
- **Given** Cmd+K (Mac) or Ctrl+K (Win/Linux) pressed anywhere in VetApp → **Then** `search-modal` opens and search input is focused

---

## Additional Context

### Dependencies
- `SearchTermNormalizer` requires `iconv` PHP extension (present by default in PHP 8.x)
- No new Composer packages required
- Projection handlers have a single dependency each: the `*SearchIndexWriter`
- `SearchEngine` composed of tagged providers via Symfony DI

### Testing Strategy
- `SearchTermNormalizer`: pure unit test with extensive case table (FR/ES/DE accents, hyphens, chips, phones)
- Projection handlers: unit tests with mocked writer (verify correct method + args)
- `AnimalSearchIndexWriter`: unit test verifying flush is called + upsert inserts vs updates
- Providers: unit tests with mocked repository (chip path, text path, isExecutable guard)
- Doctrine repositories: integration tests (IDOR, chip exact, text prefix, archived hidden)

### Notes

#### ⚠️ Critical — read before implementing

- **Task 7.2 BLOCKED**: `search_modal.html.twig` wiring must NOT be implemented in this PR. Separate ticket required.
- **F4 — Client event data is PRIVATE**: ALL Client BC events (`ClientCreated`, `ClientIdentityUpdated`, etc.) have private constructor properties. Access data ONLY via `$event->payload()['key']`. Direct `$event->firstName` causes fatal `Error`. This applies to ALL handlers in Tasks 3.10, 4.3, 4.4, 4.5, 4.6, 4.7.
- **F8 — DBAL UUID conversion is MANDATORY**: Pass `Uuid::fromString($id)->toBinary()` for ALL UUID params in DBAL queries against `BINARY(16)` columns. Read results with `Uuid::fromBinary($row['id'])->toString()`. Passing a raw UUID string causes 0 rows returned with no error.
- **F5 — Rate limiter param name**: `SearchController` constructor MUST use `$apiClinicSearchLimiter` to match the global bind name. `$searchLimiter` will NOT be auto-wired.
- **F6 — Parameters in services.yaml**: `vetsaas.search.bucket_order`, `vetsaas.search.bucket_icons`, `vetsaas.search.route_map` belong in `config/services.yaml` under the `parameters:` key. NOT in `framework.yaml`.
- **`AnimalCreated` species gap (confirmed)**: `AnimalCreated` only carries 4 fields. `OnAnimalCreated` MUST DBAL-read `animal__animals` for species/breed/chip. See Task 3.5.
- **`Identification` VO — 6 params**: constructor is `__construct(?string $microchipNumber, ?string $tattooNumber, ?string $passportNumber, RegistryType $registryType, ?string $registryNumber, ?string $sireNumber)`. Use named args. See Task 1.3.
- **Writer flush rules**: ORM methods call `$em->flush()`. `updateOwnerName()` (DBAL bulk) does NOT call flush. Test `testFlushNotCalledForUpdateOwnerName()` enforces this.
- **`DomainEventPublisher::publish()` must be inside `__invoke()`**: confirmed pattern in all existing handlers. Any projection handler called from outside the command handler boundary breaks transactional consistency.

#### General — FYI

- **Existing `SearchClients`/`SearchAnimals`**: not deprecated in this PR. Audit + deprecation in a separate PR.
- **`SearchEngine` external timeout**: PHP has no native preemptive per-call timeout. V1: best-effort via try/catch + microtime. Real preemptive timeout requires Fiber — V2.
- **Archived owner name stored anyway**: DBAL reads on `client__clients` have no status filter — archived clients are found and their name stored. This is intentional; the archived badge belongs in the Client UI, not in search index.
- **`updateOwnerName()` identity map**: DBAL executeStatement bypasses ORM identity map. Stale cached entities possible after this call. Acceptable — projection handlers don't re-read the index in the same transaction.
- **Known limitation F10/F29**: `resolveOwnershipForArchivedClient()` does not emit `AnimalOwnersReplaced` → search index not updated on ownership promotion via that path. Tracked in separate ticket. See ACs "Known limitations" section.
- **F23 Cmd+K**: dispatch synthetic click on `[data-modal-open="search-modal"]` to use `modal.js` open mechanism. Do NOT manipulate `hidden` class directly — that bypasses modal.js lifecycle.
- **Future testing standard**: handlers dealing with serialized enum payloads should have a round-trip serialize/deserialize test. Not required in this PR.
