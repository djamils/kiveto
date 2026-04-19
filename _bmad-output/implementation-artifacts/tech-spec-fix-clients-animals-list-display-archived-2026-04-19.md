---
title: 'Fix display data in clients & animals list'
slug: 'fix-clients-animals-list-display'
created: '2026-04-19'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7', 'Doctrine ORM', 'Doctrine DBAL', 'Twig', 'PHP 8.3']
files_to_modify:
  - src/Context/Animal/Application/Port/AnimalReadRepositoryInterface.php
  - src/Context/Animal/Application/Query/SearchAnimals/AnimalListItemView.php
  - src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php
  - src/Context/Client/Application/Port/ClientReadRepositoryInterface.php
  - src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DoctrineClientReadRepository.php
  - src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php
  - templates/clinic/clients/list/_tab_clients.html.twig
  - templates/clinic/clients/list/_tab_animals.html.twig
files_to_create:
  - src/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIds.php
  - src/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIdsHandler.php
  - src/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIds.php
  - src/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIdsHandler.php
  - tests/Unit/Context/Animal/Application/Query/SearchAnimals/AnimalListItemViewTest.php
  - tests/Unit/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIdsHandlerTest.php
  - tests/Unit/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIdsHandlerTest.php
code_patterns:
  - 'Port interface method → handler → repository impl (mandatory chain for every new query)'
  - 'DBAL raw SQL with Connection::PARAM_STR_ARRAY for IN clauses on binary UUID columns'
  - 'DoctrineClientReadRepository uses $em->getConnection() + raw SQL — match this pattern'
  - 'DoctrineAnimalReadRepository uses EntityManager + QueryBuilder — switch to DBAL for GROUP BY bulk'
  - 'UUID binary: Uuid::fromString()->toBinary() for params; BIN_TO_UUID(col) in SELECT'
  - 'QueryBus return type is mixed — always guard with is_int / instanceof checks'
  - 'Presentation layer dispatches queries from multiple BCs — already established pattern'
test_patterns:
  - 'Unit tests mock the port interface, verify handler delegates correctly'
  - 'ClientListItemViewTest.php is the model for AnimalListItemViewTest'
  - 'SearchClientsHandlerTest.php is the model for the two new handler tests'
---

# Tech-Spec: Fix display data in clients & animals list

**Created:** 2026-04-19

## Overview

### Problem Statement

The clients & animals list page (`/clients`) shows `—` placeholders in three columns that should display real data:

1. **Clients tab → "Animaux" column** — `_tab_clients.html.twig:65` hardcoded `—`; no animal count data in `ClientListItemView`.
2. **Animals tab → "Âge" column** — `_tab_animals.html.twig:65` hardcoded `—`; `AnimalListItemView.birthDate` available as `'Y-m-d'` but unused.
3. **Animals tab → "Propriétaire" column** — `_tab_animals.html.twig:66` hardcoded `—`; `AnimalListItemView.primaryOwnerClientId` available (UUID string) but name lookup missing.

Both templates carry comments acknowledging these as "pending enrichment spec" items.

### Solution

- **Âge**: Add `ageLabel(?\DateTimeImmutable $now = null): ?string` to `AnimalListItemView` — returns a French human-readable string (`"< 1 j"`, `"12 j"`, `"8 mois"`, `"3 ans"`) or `null` when `birthDate` is null.
- **Animaux count**: Add a new bulk query `CountAnimalsPerClientIds` in the Animal BC — one `GROUP BY` query for the whole page, returns `array<string, int>` (clientId → count). No N+1.
- **Propriétaire**: Add a new bulk query `GetClientNamesByIds` in the Client BC — one query for all owner IDs on the page, returns `array<string, string>` (clientId → fullName). No N+1.

### Scope

**In Scope:**
- `AnimalListItemView::ageLabel()` + unit test
- New Animal BC query: `CountAnimalsPerClientIds` (query + handler + port method + repo impl + test)
- New Client BC query: `GetClientNamesByIds` (query + handler + port method + repo impl + test)
- `ListClientsController` dispatch of both new queries
- `_tab_clients.html.twig` render animal count
- `_tab_animals.html.twig` render age and owner name

**Out of Scope:**
- "Dernier RDV" column (Scheduling BC — separate spec)
- `CLAUDE.md` directive (separate commit, not polluting this fix branch)

---

## Context for Development

### Architecture Rules to Respect

- **BC boundary**: Animal BC must never import Client BC internals. `CountAnimalsPerClientIds` accepts `list<string>` clientId strings (not `ClientId` VOs) — UUIDs as strings are not BC-specific.
- **Port → Handler → Repository chain**: every new query needs a port interface method, a handler, and a repository implementation. Skip none.
- **Presentation cross-BC**: `ListClientsController` already mixes Animal and Client BC queries. Adding `CountAnimalsPerClientIds` and `GetClientNamesByIds` dispatches is consistent.

### UUID / DBAL Patterns (confirmed from existing code)

Binary UUID in params:
```php
Uuid::fromString($someId)->toBinary()
// stored as BINARY(16) in MySQL
```

Reading UUID from result:
```php
BIN_TO_UUID(col) AS someId   // in SELECT clause
```

IN clause with array of binary UUIDs via DBAL:
```php
use Doctrine\DBAL\Connection;

$binaryIds = array_map(fn(string $id) => Uuid::fromString($id)->toBinary(), $ids);
$rows = $conn->fetchAllAssociative(
    'SELECT BIN_TO_UUID(col) as id, ... WHERE col IN (:ids)',
    ['ids' => $binaryIds],
    ['ids' => Connection::PARAM_STR_ARRAY],
);
```

### Table Names (confirmed from `BoundedContextPrefixNamingStrategy`)

| Entity class | Table name |
|---|---|
| `AnimalEntity` | `animal__animals` |
| `OwnershipEntity` | `animal__ownerships` |
| `ClientEntity` | `client__clients` |

### `GetClientById` Confirmed Signature

```php
// src/Context/Client/Application/Query/GetClientById/GetClientById.php
public function __construct(
    public string $clinicId,
    public string $clientId,
)
```
(Not used in this spec — replaced by bulk `GetClientNamesByIds`.)

### Files to Reference

| File | Purpose |
|---|---|
| `src/Context/Animal/Application/Query/SearchAnimals/AnimalListItemView.php` | Add `ageLabel()` |
| `src/Context/Client/Application/Query/SearchClients/ClientListItemView.php` | Model: computed method pattern |
| `src/Context/Animal/Application/Port/AnimalReadRepositoryInterface.php` | Add `countByPrimaryOwnerClientIds()` |
| `src/Context/Client/Application/Port/ClientReadRepositoryInterface.php` | Add `findFullNamesByIds()` |
| `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php` | Add DBAL bulk impl |
| `src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DoctrineClientReadRepository.php` | Model: DBAL raw SQL pattern; add bulk impl |
| `src/Context/Animal/Application/Query/CountAnimals/CountAnimals.php` | Model: existing Animal query |
| `src/Context/Animal/Application/Query/CountAnimals/CountAnimalsHandler.php` | Model: handler structure |
| `src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php` | Add both bulk dispatch calls |
| `templates/clinic/clients/list/_tab_clients.html.twig` | Fix line 65 |
| `templates/clinic/clients/list/_tab_animals.html.twig` | Fix lines 65–66 |
| `tests/Unit/Context/Client/Application/Query/SearchClients/ClientListItemViewTest.php` | Model: DTO test |
| `tests/Unit/Context/Animal/Application/Query/SearchAnimals/SearchAnimalsHandlerTest.php` | Model: handler test |

---

## Implementation Plan

Execute tasks in dependency order.

---

### Task 1 — Add `ageLabel()` to `AnimalListItemView`

**File:** `src/Context/Animal/Application/Query/SearchAnimals/AnimalListItemView.php`

Add method after the constructor. The `$now` parameter enables deterministic testing:

```php
public function ageLabel(?\DateTimeImmutable $now = null): ?string
{
    if (null === $this->birthDate) {
        return null;
    }

    $birth = new \DateTimeImmutable($this->birthDate);
    $now   = $now ?? new \DateTimeImmutable();
    $diff  = $now->diff($birth);

    if ($diff->days === 0) {
        return '< 1 j';
    }

    if ($diff->y >= 2) {
        return $diff->y . ' ans';
    }

    $months = $diff->y * 12 + $diff->m;
    if ($months >= 1) {
        return $months . ' mois';
    }

    return $diff->days . ' j';
}
```

**Age label rules (French):**

| Condition | Output |
|---|---|
| `birthDate` is null | `null` |
| born today (0 days) | `"< 1 j"` |
| < 1 month | `"N j"` (N = days) |
| 1 month to < 2 years | `"N mois"` (N = total months) |
| ≥ 2 years | `"N ans"` |

---

### Task 2 — Unit test `AnimalListItemViewTest`

**File (new):** `tests/Unit/Context/Animal/Application/Query/SearchAnimals/AnimalListItemViewTest.php`

Model: `ClientListItemViewTest.php`. Create a `final class AnimalListItemViewTest extends TestCase`.

Helper factory method for brevity:
```php
private function makeView(?string $birthDate): AnimalListItemView
{
    return new AnimalListItemView(
        id: '01234567-89ab-cdef-0123-456789abcdef',
        name: 'Rex',
        species: 'dog',
        sex: 'male',
        breedName: null,
        birthDate: $birthDate,
        color: null,
        microchipNumber: null,
        status: 'active',
        lifeStatus: 'alive',
        primaryOwnerClientId: null,
        createdAt: '2024-01-01T10:00:00+00:00',
    );
}
```

Test cases (all pass a fixed `$now` to `ageLabel()`):

| Test name | birthDate | $now | Expected |
|---|---|---|---|
| `testAgeLabelNullWhenNoBirthDate` | null | any | null |
| `testAgeLabelLessThanOneDay` | same day as $now | same day | `"< 1 j"` |
| `testAgeLabelInDays` | 10 days before $now | — | `"10 j"` |
| `testAgeLabelInMonths` | 8 months before $now | — | `"8 mois"` |
| `testAgeLabelExactly1YearIsMonths` | 12 months before $now | — | `"12 mois"` |
| `testAgeLabelExactly2YearsIsAns` | 2 years before $now | — | `"2 ans"` |
| `testAgeLabelInYears` | 3 years before $now | — | `"3 ans"` |

Use a fixed `$now = new \DateTimeImmutable('2026-04-19')` and compute birthDate as `$now->modify('-X days/months/years')->format('Y-m-d')`.

---

### Task 3 — New query: `CountAnimalsPerClientIds`

#### 3a — Query object

**File (new):** `src/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIds.php`

```php
<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\CountAnimalsPerClientIds;

use App\Shared\Application\Bus\QueryInterface;

final readonly class CountAnimalsPerClientIds implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
```

#### 3b — Port interface method

**File:** `src/Context/Animal/Application/Port/AnimalReadRepositoryInterface.php`

Add method:
```php
/**
 * @param list<string> $clientIds UUID strings
 * @return array<string, int> clientId => active primary-owned animal count
 */
public function countByPrimaryOwnerClientIds(ClinicId $clinicId, array $clientIds): array;
```

#### 3c — Handler

**File (new):** `src/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIdsHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\CountAnimalsPerClientIds;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountAnimalsPerClientIdsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function __invoke(CountAnimalsPerClientIds $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->readRepository->countByPrimaryOwnerClientIds(
            ClinicId::fromString($query->clinicId),
            $query->clientIds,
        );
    }
}
```

#### 3d — Repository implementation

**File:** `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php`

Add method. Uses DBAL connection for the GROUP BY query:

```php
public function countByPrimaryOwnerClientIds(ClinicId $clinicId, array $clientIds): array
{
    if ([] === $clientIds) {
        return [];
    }

    $conn          = $this->entityManager->getConnection();
    $clinicBinary  = Uuid::fromString($clinicId->toString())->toBinary();
    $binaryIds     = array_map(
        static fn (string $id) => Uuid::fromString($id)->toBinary(),
        $clientIds,
    );

    $sql = '
        SELECT BIN_TO_UUID(o.client_id) AS clientId, COUNT(DISTINCT a.id) AS animalCount
        FROM animal__ownerships o
        JOIN animal__animals a ON a.id = o.animal_id
        WHERE a.clinic_id = :clinicId
          AND o.client_id IN (:clientIds)
          AND o.role = :role
          AND o.status = :status
        GROUP BY o.client_id
    ';

    $rows = $conn->fetchAllAssociative(
        $sql,
        [
            'clinicId'  => $clinicBinary,
            'clientIds' => $binaryIds,
            'role'      => 'primary',
            'status'    => 'active',
        ],
        [
            'clientIds' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
        ],
    );

    $result = [];
    foreach ($rows as $row) {
        \assert(\is_string($row['clientId']));
        \assert(is_numeric($row['animalCount']));
        $result[$row['clientId']] = (int) $row['animalCount'];
    }

    return $result;
}
```

**Note:** Add required `use` imports: `\Doctrine\DBAL\Connection`, `Symfony\Component\Uid\Uuid`.

#### 3e — Handler unit test

**File (new):** `tests/Unit/Context/Animal/Application/Query/CountAnimalsPerClientIds/CountAnimalsPerClientIdsHandlerTest.php`

Model: `SearchAnimalsHandlerTest.php`. Cover:
- `testReturnsEmptyArrayWhenClientIdsIsEmpty` — empty `clientIds` → handler returns `[]` without calling repository
- `testDelegatesSearchToRepository` — non-empty `clientIds` → repository `countByPrimaryOwnerClientIds()` called once with correct `ClinicId` and `clientIds`; handler returns repository result

---

### Task 4 — New query: `GetClientNamesByIds`

#### 4a — Query object

**File (new):** `src/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIds.php`

```php
<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNamesByIds;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClientNamesByIds implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
```

#### 4b — Port interface method

**File:** `src/Context/Client/Application/Port/ClientReadRepositoryInterface.php`

Add method:
```php
/**
 * @param list<string> $clientIds UUID strings
 * @return array<string, string> clientId => fullName
 */
public function findFullNamesByIds(ClinicId $clinicId, array $clientIds): array;
```

#### 4c — Handler

**File (new):** `src/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIdsHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNamesByIds;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientNamesByIdsHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(GetClientNamesByIds $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->clientReadRepository->findFullNamesByIds(
            ClinicId::fromString($query->clinicId),
            $query->clientIds,
        );
    }
}
```

#### 4d — Repository implementation

**File:** `src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DoctrineClientReadRepository.php`

Add method using the existing DBAL pattern:

```php
public function findFullNamesByIds(ClinicId $clinicId, array $clientIds): array
{
    if ([] === $clientIds) {
        return [];
    }

    $conn         = $this->em->getConnection();
    $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();
    $binaryIds    = array_map(
        static fn (string $id) => Uuid::fromString($id)->toBinary(),
        $clientIds,
    );

    $sql = '
        SELECT BIN_TO_UUID(c.id) AS id, c.first_name AS firstName, c.last_name AS lastName
        FROM client__clients c
        WHERE c.clinic_id = :clinicId
          AND c.id IN (:clientIds)
    ';

    $rows = $conn->fetchAllAssociative(
        $sql,
        [
            'clinicId'  => $clinicBinary,
            'clientIds' => $binaryIds,
        ],
        [
            'clientIds' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
        ],
    );

    $result = [];
    foreach ($rows as $row) {
        \assert(\is_string($row['id']));
        \assert(\is_string($row['firstName']));
        \assert(\is_string($row['lastName']));
        $result[$row['id']] = trim($row['firstName'] . ' ' . $row['lastName']);
    }

    return $result;
}
```

#### 4e — Handler unit test

**File (new):** `tests/Unit/Context/Client/Application/Query/GetClientNamesByIds/GetClientNamesByIdsHandlerTest.php`

Model: `SearchClientsHandlerTest.php`. Cover:
- `testReturnsEmptyArrayWhenClientIdsIsEmpty` — empty `clientIds` → returns `[]` without calling repository
- `testDelegatesSearchToRepository` — non-empty `clientIds` → `findFullNamesByIds()` called once with correct args; handler returns repository result

---

### Task 5 — Update `ListClientsController`

**File:** `src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php`

Add imports:
```php
use App\Context\Animal\Application\Query\CountAnimalsPerClientIds\CountAnimalsPerClientIds;
use App\Context\Client\Application\Query\GetClientNamesByIds\GetClientNamesByIds;
```

**Clients tab branch** — after `$clientsView = ...` and `$clientCount = ...`, add:

```php
$rawCounts = $this->queryBus->ask(new CountAnimalsPerClientIds(
    clinicId: $currentClinicId->toString(),
    clientIds: array_map(
        static fn (mixed $c): string => \assert($c instanceof ClientListItemView) ?: $c->id,
        $clientsView['items'],
    ),
));
$animalCountsByClientId = \is_array($rawCounts) ? $rawCounts : [];
```

**Simpler alternative** (avoids PHPStan mixed-type complexity — preferred):
```php
$clientPageIds = [];
foreach ($clientsView['items'] as $item) {
    if ($item instanceof ClientListItemView) {
        $clientPageIds[] = $item->id;
    }
}

$rawCounts = $this->queryBus->ask(new CountAnimalsPerClientIds(
    clinicId: $currentClinicId->toString(),
    clientIds: $clientPageIds,
));
$animalCountsByClientId = \is_array($rawCounts) ? $rawCounts : [];
```

Add `use App\Context\Client\Application\Query\SearchClients\ClientListItemView;` if not already imported.

**Animals tab branch** — after `$animalsView = ...` and `$animalCount = ...`, add:

```php
$ownerIds = [];
foreach ($animalsView['items'] as $item) {
    if ($item instanceof AnimalListItemView && null !== $item->primaryOwnerClientId) {
        $ownerIds[] = $item->primaryOwnerClientId;
    }
}
$ownerIds = array_values(array_unique($ownerIds));

$rawNames = $this->queryBus->ask(new GetClientNamesByIds(
    clinicId: $currentClinicId->toString(),
    clientIds: $ownerIds,
));
$ownerNamesByClientId = \is_array($rawNames) ? $rawNames : [];
```

Add `use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;` if not already imported.

**`render()` call** — add both maps to template vars:
```php
'animalCountsByClientId' => $animalCountsByClientId,
'ownerNamesByClientId'   => $ownerNamesByClientId,
```

Both variables must be initialized to `[]` at the top of `__invoke()` (alongside `$clientsView`, `$animalsView`), so the render call is always valid regardless of which tab is active.

---

### Task 6 — Update `_tab_clients.html.twig`

**File:** `templates/clinic/clients/list/_tab_clients.html.twig`

Replace lines 64–65:
```twig
{# Cross-BC fallback — pending follow-up enrichment spec. #}
<td class="col-animals">—</td>
```
With:
```twig
<td class="col-animals">{{ animalCountsByClientId[client.id] is defined ? animalCountsByClientId[client.id] : '—' }}</td>
```

---

### Task 7 — Update `_tab_animals.html.twig`

**File:** `templates/clinic/clients/list/_tab_animals.html.twig`

Replace lines 65–66:
```twig
<td>—</td>
<td>—</td>
```
With:
```twig
<td>{{ animal.ageLabel() ?? '—' }}</td>
<td>{{ ownerNamesByClientId[animal.primaryOwnerClientId] is defined ? ownerNamesByClientId[animal.primaryOwnerClientId] : '—' }}</td>
```

Remove (or update) the comment on lines 25–26: `{# Cross-BC columns rendered with "—" — pending enrichment spec. #}`.

---

### Acceptance Criteria

**AC1 — Clients tab, animal count**
- Given a client with 2 active primary-owned animals, When the clients tab is displayed, Then "Animaux" shows `2`
- Given a client with no animals, Then "Animaux" shows `0`
- Given clients tab with 20 clients, Then only 1 query is dispatched for all animal counts (not 20)

**AC2 — Animals tab, age**
- Given `birthDate = today`, Then "Âge" shows `"< 1 j"`
- Given `birthDate = 10 days ago`, Then "Âge" shows `"10 j"`
- Given `birthDate = 8 months ago`, Then "Âge" shows `"8 mois"`
- Given `birthDate = 1 year ago` (exactly), Then "Âge" shows `"12 mois"`
- Given `birthDate = 2 years ago`, Then "Âge" shows `"2 ans"`
- Given `birthDate = 3 years ago`, Then "Âge" shows `"3 ans"`
- Given no `birthDate`, Then "Âge" shows `—`

**AC3 — Animals tab, owner**
- Given an animal with primary active owner "Marie Dupont", Then "Propriétaire" shows `"Marie Dupont"`
- Given an animal with no primary owner, Then "Propriétaire" shows `—`
- Given animals tab with 20 animals sharing 5 distinct owners, Then only 1 query is dispatched for all owner names (not 20)

**AC4 — No regression**
- `make ci` passes (cs-fixer, phpcs, phpstan level:max, all tests green)
- Animal BC coverage at 100% (new test covers `ageLabel()` and `CountAnimalsPerClientIds` handler)
- Client BC coverage at 100% (new test covers `GetClientNamesByIds` handler)

---

## Additional Context

### Dependencies

- `AnimalListItemView.birthDate` is formatted `'Y-m-d'` — `new \DateTimeImmutable($this->birthDate)` is safe.
- `AnimalListItemView.primaryOwnerClientId` is a plain UUID string — no VO needed.
- `OwnershipStatus` and `OwnershipRole` values confirmed as `'active'` / `'primary'` strings in the raw SQL (from `DoctrineAnimalReadRepository` existing code).
- `QueryInterface` lives at `App\Shared\Application\Bus\QueryInterface` (confirmed from existing query classes).

### Testing Strategy

- Unit tests use mocked port interfaces — no DB needed.
- Handler tests verify delegation: correct VO construction, correct method called, result passed through unchanged.
- `ageLabel()` tests use fixed `$now` via the injectable parameter — no date drift.
- `make ci` after each task to catch PHPStan violations early.

### Notes

- Template uses `ownerNamesByClientId[animal.primaryOwnerClientId]` (keyed by clientId, not animalId). This is correct because `primaryOwnerClientId` on each animal is the key used in the map.
- `animalCountsByClientId` will not contain entries for clients with 0 animals (they appear in no GROUP BY row). The template `is defined` check handles this correctly — falls back to `—`. Per AC1, a client with 0 animals should show `0`, not `—`. **Fix**: in the controller, after getting `$rawCounts`, fill missing keys: `foreach ($clientPageIds as $id) { $animalCountsByClientId[$id] ??= 0; }`.
- Branch name: `fix/clients-animals-list-display`
