# Shared Bounded Context

Le Bounded Context **Shared** fournit les abstractions et composants partagés par tous les autres Bounded Contexts de Kiveto.

## Event dispatch policy

`Shared/` may host buses, publishers and abstract event classes
(`AbstractDomainEvent`, `EventBus`, `DomainEventPublisher`, …), but
contains no concrete business `DomainEvent` and never `dispatch()`es
directly. Dispatch always originates from an aggregate inside a Bounded
Context.

## Transactional messaging (implicit outbox)

An integration event is never lost between the business commit and the
queue, without an outbox table or a relay. The guarantee holds because:

1. the `async` transport is `doctrine://default`, i.e. the **same MySQL
   connection** as the business data (table `shared__messenger_messages`);
2. the command bus wraps every handler in `doctrine_transaction`;
3. domain events are dispatched **synchronously** (`messenger.bus.event`,
   routed to `sync`), including the domain → integration bridges
   (`*IntegrationBridge`), so the `INSERT` into the messenger table happens
   inside the command transaction. A failure anywhere in the handler —
   even after `flush` — rolls back the business rows and the message
   together.

Rules:

- Never use `DispatchAfterCurrentBusStamp` / `dispatch_after_current_bus`.
- Domain events stay routed to `sync`; only `IntegrationEventInterface`
  goes to `async`. Do not route a domain event with `#[AsMessage]`.
- Every Doctrine transport keeps `auto_setup: false` and the
  `shared__messenger_messages` table (`failed` included, told apart by
  `queue_name`): runtime DDL would implicitly commit an open MySQL
  transaction. Tables are created by migrations only.

Guards:

| Guarantee | Test |
|-----------|------|
| Business rows and message commit/roll back together | `tests/Integration/System/Messenger/CommandBusOutboxAtomicityTest.php` |
| Doctrine transports: business connection, messenger table, no auto-setup | `tests/Integration/System/Messenger/DoctrineTransportsConfigurationTest.php` |
| Failure transport stores in the messenger table (`queue_name = failed`) | `tests/Integration/System/Messenger/FailureTransportTest.php` |
| Domain events → `sync`, integration events → `async` (compiled senders locator) | `tests/Integration/System/Messenger/MessengerRoutingGuardTest.php` |
| Every `*Handler` carries `#[AsMessageHandler]` (or the external-API bus one) | `tests/Integration/Shared/MessengerHandlersRegistrationTest.php` |
| No dispatch-after-bus stamp, bridges on `messenger.bus.event` | `tests/Integration/Shared/TransactionalMessagingConventionTest.php` |

Known gap (tech debt): handlers consumed from
`messenger.bus.integration_event` run **without** a transaction — each
`flush` commits on its own.

Worker: `make worker-start` / `make worker-stop` / `make worker-logs`
run the `messenger-worker` compose service (profile `workers`), which
consumes the `async` transport. On first start it may process a backlog
of events never consumed in dev.

## Scheduled tasks

`symfony/scheduler` runs the `default` schedule
(`Infrastructure/Scheduler/DefaultScheduleProvider.php`), consumed by the
`scheduler-worker` compose service (`messenger:consume scheduler_default`,
started by `make worker-start`; `make scheduler-debug` lists the next runs).

- **Lock in the database.** The schedule holds a lock from
  `shared.lock.factory` (`DoctrineDbalStore` on the default connection,
  table `shared__lock_keys`, created by a `migrations/Shared` migration).
  Several scheduler workers (rolling deploys) never run the same tick
  twice; a `flock` store would be per container. The framework lock
  (`LOCK_DSN`) is disabled on purpose.
- **Not stateful.** Missed runs are not replayed. Every scheduled task
  must be idempotent and catch up by itself (e.g. a period-closing task
  closes every ended, unclosed period; a polling task resumes from its
  own cursor).
- Recurring messages are dispatched on the default (command) bus.

## External APIs

Every call to a third-party HTTP service follows one convention
(`Application/ExternalApi/`, `Infrastructure/ExternalApi/`).

- **One scoped client per service** (`framework.http_client.scoped_clients`):
  base URL, `timeout: 2` (idle, also bounds a stalled connect),
  `max_duration: 5`, `User-Agent` + `Accept: application/json`, no
  `retry_failed` — Symfony never retries on our behalf.
- **`RetryAfterAwareClient`** wraps that client, one instance per service:
  429 → `ExternalServiceUnavailable::rateLimited()` (`Retry-After` parsed),
  503 / other 5xx → `failed()`, transport error or timeout → `unreachable()`;
  any 2xx / 4xx is handed back as an `ExternalApiResponse` for the adapter to
  interpret. It never retries.
- **`ExternalServiceUnavailable` is an outage, never an empty result.** A port
  returns "nothing found" only when the service said so. `isThrottled()` is
  true for our local budget being spent (`throttled()`) or a remote 429
  (`rateLimited()`) — "try again in a moment"; everything else (5xx,
  timeout, `refused()` for an API-level outage code) means the service is
  down.
- **Fixture adapter + env var.** Each port has an HTTP adapter and a fixture
  adapter, picked by an env var through a service locator used as factory
  (unknown value → failure on first use). Tests, CI and dev run on the
  fixtures by default.
- **Recorded JSON fixtures** under `tests/Fixtures/<service>/` feed the HTTP
  adapters' tests (`MockHttpClient`); a contract test runs on both adapters.
- **No network in tests.** In the test environment the HTTP transport of every
  client fails on any call its test did not mock (`UnmockedHttpCallGuard`).
- **User-Agent contact checked at use.** `ExternalApiUserAgent::withContact()`
  (`EXTERNAL_API_USER_AGENT`) is called when a real adapter is built: without
  an e-mail or URL it throws `\LogicException`; `cache:clear`, migrations and
  fixture mode never depend on it.
- **Logs.** One record per call on the `external_api` channel (service,
  method, path without query string, status, `duration_ms`, outcome
  `ok|rate_limited|failed|unreachable`), never bodies, headers or query
  parameters. Symfony's own `http_client` channel logs full URLs: it is
  excluded from every handler.
- **Never a network call inside a database transaction.** Every background
  external call is an `ExternalApiCommandInterface` sent through
  `ExternalApiCommandBusInterface` to `async_external` (or
  `async_external_batch` with a `TransportNamesStamp` for batch sends). The
  row is written in the caller's transaction (implicit outbox); the
  `external-worker` handles it on `messenger.bus.external_api`, which has no
  `doctrine_transaction`, then a short transactional command on the command
  bus records the result. `CommandBusInterface` refuses such a command. The
  only synchronous external call allowed is a read-only lookup answering a
  user in a web request, outside any transaction.

Not yet migrated: `GeoplateformeAddressDirectory` (Client) and
`GotenbergPdfRenderer` (Shared) still use the default client.

Guards:

| Guarantee | Test |
|-----------|------|
| 429 / 5xx / timeout mapping, no retry, log content | `tests/Unit/Shared/Infrastructure/ExternalApi/RetryAfterAwareClientTest.php` |
| `external_api` channel, `http_client` excluded in dev and prod | `tests/Integration/Shared/Infrastructure/ExternalApi/LoggingConfigurationTest.php` |
| No unmocked HTTP call in tests | `tests/Integration/Shared/Infrastructure/ExternalApi/NetworkGuardTest.php` |
| External commands queued in the caller's transaction, interactive before batch | `tests/Integration/Shared/Infrastructure/Bus/ExternalApiCommandOutboxTest.php` |
| External commands → `async_external` only | `tests/Integration/System/Messenger/MessengerRoutingGuardTest.php` |
| `messenger.bus.external_api` handlers ↔ external commands | `tests/Integration/System/Messenger/ExternalApiBusGuardTest.php` |
| `external-worker` consumes `async_external async_external_batch`, `messenger-worker` neither | `tests/Integration/Shared/Infrastructure/Console/ComposeWorkersTest.php` |

Workers (`make worker-start`): `messenger-worker` (`async`),
`external-worker` (`async_external`, then `async_external_batch`),
`scheduler-worker` (schedules).

## Responsabilités

- **Domain Events**: Infrastructure pour les événements de domaine (enregistrement, métadonnées, versionning)
- **Aggregate Root**: Classe de base pour les agrégats DDD
- **Time abstraction**: Gestion déterministe du temps (ClockInterface)
- **UUID Generation**: Génération d'identifiants UUIDv7
- **Domain Event Factory**: Création d'événements avec métadonnées auto-générées
- **Bus abstractions**: Interfaces pour Command/Query/Event buses
- **Context management**: Gestion du contexte clinique et utilisateur

## Architecture

```
src/Shared/
├── Domain/
│   ├── Aggregate/
│   │   └── AggregateRoot.php
│   ├── Event/
│   │   ├── DomainEventInterface.php
│   │   └── AbstractDomainEvent.php
│   ├── Identifier/
│   │   └── UuidGeneratorInterface.php
│   └── Time/
│       └── ClockInterface.php
├── Application/
│   └── Event/
│       ├── DomainEventFactoryInterface.php
│       └── DomainEventFactory.php
└── Infrastructure/
    ├── Identifier/
    │   └── SymfonyUuidV7Generator.php
    └── Time/
        └── SystemClock.php
```

## Concepts clés

### 1. Domain Events

Les événements de domaine représentent des faits qui se sont produits dans le passé.

#### Format du type d'événement

```
<bounded-context>.<aggregate>.<action>.v<version>
```

Exemple: `identity-access.user.registered.v1`

#### Règles de versionning

- **Garder la même version** pour les changements rétro-compatibles (ajout d'un champ optionnel)
- **Incrémenter VERSION** uniquement pour les breaking changes (renommage/suppression/changement de format)

#### Convention de constructeur

Tous les événements DOIVENT suivre ce pattern :

```php
public function __construct(
    // ... arguments du payload (positionnels)
    string $eventId,              // ← paramètre nommé (requis)
    \DateTimeImmutable $occurredAt, // ← paramètre nommé (requis)
) {
    parent::__construct($eventId, $occurredAt);
}
```

#### Exemple d'événement

```php
<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final class UserRegistered extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'identity-access';
    protected const VERSION = 1;

    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        string $eventId,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'email' => $this->email,
        ];
    }
}
```

#### Création d'événements

**TOUJOURS** créer les événements via `DomainEventFactoryInterface` :

```php
use App\Shared\Application\Event\DomainEventFactoryInterface;

final class RegisterUserHandler
{
    public function __construct(
        private readonly DomainEventFactoryInterface $domainEventFactory,
    ) {}

    public function __invoke(RegisterUserCommand $command): void
    {
        // ... logique métier ...

        $event = $this->domainEventFactory->create(
            UserRegistered::class,
            [$userId, $email]
        );

        $user->recordDomainEvent($event);
    }
}
```

La factory génère automatiquement :
- `eventId` : UUIDv7
- `occurredAt` : timestamp du Clock

### 2. Aggregate Root

Les agrégats étendent `AggregateRoot` pour enregistrer des événements de domaine.

```php
use App\Shared\Domain\Aggregate\AggregateRoot;

final class User extends AggregateRoot
{
    public function register(string $email): void
    {
        // Logique métier...

        $event = new UserRegistered($this->id, $email);
        $this->recordDomainEvent($event);
    }
}
```

**Publication des événements** (couche Application) :

```php
// 1. Persister l'agrégat
$repository->save($user);

// 2. Récupérer et publier les événements
$events = $user->pullDomainEvents();
foreach ($events as $event) {
    $eventBus->publish($event);
}
```

### 3. Clock Interface

**JAMAIS** appeler `new DateTimeImmutable()` directement dans le code domaine/application quand "maintenant" compte.

**TOUJOURS** injecter `ClockInterface` :

```php
use App\Shared\Domain\Time\ClockInterface;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(CreateInvoiceCommand $command): void
    {
        $invoice = new Invoice(
            id: $command->id,
            createdAt: $this->clock->now(), // ✅ Déterministe
        );
    }
}
```

**En production** : `SystemClock` (temps réel)

**En tests** : `FrozenClock` (temps figé)

```php
use App\Tests\Shared\Time\FrozenClock;

final class CreateInvoiceHandlerTest extends TestCase
{
    public function test_creates_invoice_with_current_date(): void
    {
        $fixedTime = new \DateTimeImmutable('2025-12-25 10:00:00');
        $clock = new FrozenClock($fixedTime);

        $handler = new CreateInvoiceHandler($clock);

        // Le test est déterministe, pas de flakiness
        $invoice = $handler(...);

        self::assertSame('2025-12-25 10:00:00', $invoice->createdAt()->format('Y-m-d H:i:s'));
    }
}
```

### 4. UUID Generator

Génération d'identifiants UUIDv7 (triables par timestamp).

```php
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

final class CreateClinicHandler
{
    public function __construct(
        private readonly UuidGeneratorInterface $uuidGenerator,
    ) {}

    public function __invoke(CreateClinicCommand $command): void
    {
        $clinicId = $this->uuidGenerator->generate();

        $clinic = new Clinic($clinicId, $command->name);
    }
}
```

### 5. Domain Event Factory

Crée des événements avec métadonnées auto-générées.

**Pourquoi ?**
- Évite la duplication de code (`eventId`, `occurredAt`)
- Garantit la cohérence des métadonnées
- Simplifie les tests (mockable)

**Convention** :
- Les événements DOIVENT avoir `eventId` et `occurredAt` comme paramètres nommés
- Les arguments du payload sont passés dans un tableau

```php
// ✅ Correct
$event = $domainEventFactory->create(
    UserRegistered::class,
    [$userId, $email]  // tableau d'arguments positionnels
    // eventId + occurredAt sont générés automatiquement
);

// ❌ Incorrect (ne pas créer manuellement)
$event = new UserRegistered(
    $userId,
    $email,
    Uuid::v7()->toString(), // ❌ duplication
    new \DateTimeImmutable() // ❌ non déterministe en tests
);
```

## Tests

Les tests unitaires sont dans `tests/Unit/Shared/` :

- `Domain/Event/DomainEventTest.php` : Validation du format `type()` et versionning
- `Domain/Aggregate/AggregateRootTest.php` : Enregistrement et récupération d'événements
- `Application/Event/DomainEventFactoryTest.php` : Création d'événements avec métadonnées
- `Time/ClockTest.php` : SystemClock vs FrozenClock
- `Infrastructure/Identifier/SymfonyUuidV7GeneratorTest.php` : Génération d'UUIDs

Exécuter les tests :

```bash
php bin/phpunit tests/Unit/Shared/
```

## Configuration Symfony

Les services sont déclarés dans `config/services.yaml` :

```yaml
services:
    # Clock - SystemClock en production
    App\Shared\Domain\Time\ClockInterface:
        class: App\Shared\Infrastructure\Time\SystemClock

    # UUID Generator - Symfony UUIDv7
    App\Shared\Domain\Identifier\UuidGeneratorInterface:
        class: App\Shared\Infrastructure\Identifier\SymfonyUuidV7Generator

    # Domain Event Factory
    App\Shared\Application\Event\DomainEventFactoryInterface:
        class: App\Shared\Application\Event\DomainEventFactory
```

**Override en tests** (dans `config/services_test.yaml` si nécessaire) :

```yaml
services:
    App\Shared\Domain\Time\ClockInterface:
        class: App\Tests\Shared\Time\FrozenClock
        arguments:
            $now: '@=new \\DateTimeImmutable("2025-01-01 12:00:00")'
```

## Documentation complémentaire

Voir `docs/architecture/domain-events.md` pour plus de détails sur l'architecture des événements de domaine.

## Règles importantes

1. **JAMAIS** créer des événements manuellement → utiliser `DomainEventFactoryInterface`
2. **JAMAIS** appeler `new DateTimeImmutable()` dans le domaine/application → injecter `ClockInterface`
3. **TOUJOURS** définir `BOUNDED_CONTEXT` et `VERSION` dans vos événements
4. **TOUJOURS** incrémenter `VERSION` pour les breaking changes du payload
5. **TOUJOURS** appeler `pullDomainEvents()` après la persistance et publier les événements
