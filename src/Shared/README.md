# Shared Bounded Context

Le Bounded Context **Shared** fournit les abstractions et composants partagés par tous les autres Bounded Contexts de Kiveto.

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

namespace App\IdentityAccess\Domain\Event;

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
            $userId,
            $email
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
- Les arguments du payload sont passés avant

```php
// ✅ Correct
$event = $domainEventFactory->create(
    UserRegistered::class,
    $userId,        // payload arg 1
    $email          // payload arg 2
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
