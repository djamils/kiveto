# Clinic Bounded Context

Le Bounded Context **Clinic** gère les informations relatives aux cliniques vétérinaires (tenants) et aux groupes de cliniques.

## Responsabilités

- **Clinic (Tenant/Office)**: Gestion des cliniques individuelles avec leurs paramètres globaux
- **ClinicGroup**: Structure optionnelle pour regrouper plusieurs cliniques
- Paramètres: slug unique, timezone, locale, currency (optionnel), status, identité (name, address/contact optionnel)

**Ce BC ne gère PAS** :
- Agendas et rendez-vous
- Clients et animaux
- Dossiers médicaux
- Finance
- Accès VET/ASV (géré par un futur BC ClinicMembership/ClinicAccess)

## Architecture

```
src/Clinic/
├── Domain/
│   ├── Clinic.php (Aggregate Root)
│   ├── ClinicGroup.php (Aggregate Root)
│   ├── Event/
│   │   ├── ClinicCreated.php
│   │   ├── ClinicRenamed.php
│   │   ├── ClinicSlugChanged.php
│   │   ├── ClinicTimeZoneChanged.php
│   │   ├── ClinicLocaleChanged.php
│   │   ├── ClinicSuspended.php
│   │   ├── ClinicActivated.php
│   │   ├── ClinicClosed.php
│   │   ├── ClinicGroupCreated.php
│   │   ├── ClinicGroupRenamed.php
│   │   ├── ClinicGroupSuspended.php
│   │   └── ClinicGroupActivated.php
│   ├── ValueObject/
│   │   ├── ClinicId.php
│   │   ├── ClinicGroupId.php
│   │   ├── ClinicSlug.php
│   │   ├── TimeZone.php
│   │   ├── LocaleCode.php
│   │   ├── ClinicStatus.php (enum: active, suspended, closed)
│   │   └── ClinicGroupStatus.php (enum: active, suspended)
│   └── Repository/
│       ├── ClinicRepositoryInterface.php
│       └── ClinicGroupRepositoryInterface.php
├── Application/
│   ├── Command/
│   │   ├── CreateClinic/
│   │   ├── RenameClinic/
│   │   ├── ChangeClinicSlug/
│   │   ├── ChangeClinicTimeZone/
│   │   ├── ChangeClinicLocale/
│   │   ├── ChangeClinicStatus/
│   │   ├── CreateClinicGroup/
│   │   ├── RenameClinicGroup/
│   │   ├── SuspendClinicGroup/
│   │   └── ActivateClinicGroup/
│   ├── Query/
│   │   ├── GetClinic/
│   │   ├── ListClinics/
│   │   ├── GetClinicGroup/
│   │   └── ListClinicGroups/
│   ├── Port/
│   │   ├── ClinicReadRepositoryInterface.php
│   │   └── ClinicGroupReadRepositoryInterface.php
│   └── Exception/
│       └── DuplicateClinicSlugException.php
└── Infrastructure/
    └── Persistence/
        └── Doctrine/
            ├── Entity/
            │   ├── ClinicEntity.php
            │   └── ClinicGroupEntity.php
            ├── Mapper/
            │   ├── ClinicMapper.php
            │   └── ClinicGroupMapper.php
            └── Repository/
                ├── DoctrineClinicRepository.php
                ├── DoctrineClinicGroupRepository.php
                ├── DoctrineClinicReadRepository.php
                └── DoctrineClinicGroupReadRepository.php
```

## Concepts clés

### 1. Clinic (Aggregate)

Représente une clinique vétérinaire individuelle (tenant).

#### Propriétés

- `ClinicId`: Identifiant unique (UUIDv7)
- `clinicGroupId`: ID du groupe (optionnel, nullable)
- `slug`: Slug unique globalement (format: `[a-z0-9-]+`)
- `name`: Nom de la clinique
- `status`: Statut (active, suspended, closed)
- `timeZone`: Timezone IANA (ex: Europe/Paris)
- `locale`: Locale code (ex: fr, en_US)
- `currency`: Code devise (optionnel MVP)
- `createdAt`: Date de création (UTC)
- `updatedAt`: Date de dernière modification (UTC)

#### Invariants

- Le slug doit être unique globalement
- Le slug doit respecter le format `[a-z0-9-]+`
- Le timezone doit être valide (IANA)
- Le name ne peut pas être vide
- Une clinique fermée (closed) ne peut pas être réactivée

#### Comportements

```php
// Créer une clinique
$clinic = Clinic::create(
    id: $clinicId,
    name: 'Clinique Vétérinaire de Paris',
    slug: ClinicSlug::fromString('clinic-paris'),
    timeZone: TimeZone::fromString('Europe/Paris'),
    locale: LocaleCode::fromString('fr'),
    createdAt: $clock->now(),
    clinicGroupId: $groupId, // optionnel
);

// Modifier les paramètres
$clinic->rename('Nouveau nom', $clock->now());
$clinic->changeSlug(ClinicSlug::fromString('new-slug'), $clock->now());
$clinic->changeTimeZone(TimeZone::fromString('America/New_York'), $clock->now());
$clinic->changeLocale(LocaleCode::fromString('en_US'), $clock->now());

// Gérer le statut
$clinic->suspend($clock->now());
$clinic->activate($clock->now());
$clinic->close($clock->now());
```

### 2. ClinicGroup (Aggregate)

Structure optionnelle pour regrouper plusieurs cliniques.

#### Propriétés

- `ClinicGroupId`: Identifiant unique (UUIDv7)
- `name`: Nom du groupe
- `status`: Statut (active, suspended)
- `createdAt`: Date de création (UTC)

#### Invariants

- Le name ne peut pas être vide

#### Comportements

```php
// Créer un groupe
$group = ClinicGroup::create(
    id: $groupId,
    name: 'Réseau VetoFrance',
    createdAt: $clock->now(),
);

// Modifier
$group->rename('Nouveau nom');
$group->suspend();
$group->activate();
```

### 3. Value Objects

#### ClinicSlug

Slug unique pour identifier une clinique dans les URLs.

```php
$slug = ClinicSlug::fromString('my-clinic'); // Format: [a-z0-9-]+
```

**Contraintes**:
- Unique globalement
- Format: lettres minuscules, chiffres, tirets uniquement
- Non vide

#### TimeZone

Timezone IANA valide.

```php
$tz = TimeZone::fromString('Europe/Paris');
$tz = TimeZone::fromString('America/New_York');
$tz = TimeZone::fromString('UTC');
```

**Contraintes**:
- Doit être un timezone IANA valide

#### LocaleCode

Code locale (ISO 639-1 + optionnel ISO 3166-1).

```php
$locale = LocaleCode::fromString('fr');
$locale = LocaleCode::fromString('en_US');
```

**Contraintes**:
- Format: `[a-z]{2}` ou `[a-z]{2}_[A-Z]{2}`

### 4. Domain Events

Tous les événements suivent le pattern `clinic.<aggregate>.<action>.v1`.

#### Événements Clinic

- `ClinicCreated`: Clinique créée
- `ClinicRenamed`: Nom modifié
- `ClinicSlugChanged`: Slug modifié
- `ClinicTimeZoneChanged`: Timezone modifié
- `ClinicLocaleChanged`: Locale modifié
- `ClinicSuspended`: Clinique suspendue
- `ClinicActivated`: Clinique réactivée
- `ClinicClosed`: Clinique fermée définitivement

#### Événements ClinicGroup

- `ClinicGroupCreated`: Groupe créé
- `ClinicGroupRenamed`: Nom modifié
- `ClinicGroupSuspended`: Groupe suspendu
- `ClinicGroupActivated`: Groupe réactivé

## Usage

### Créer une clinique via Command

```php
use App\Clinic\Application\Command\CreateClinic\CreateClinic;

$command = new CreateClinic(
    name: 'Clinique Vétérinaire de Paris',
    slug: 'clinic-paris',
    timeZone: 'Europe/Paris',
    locale: 'fr',
    clinicGroupId: null, // optionnel
);

$clinicId = $commandBus->dispatch($command);
```

### Récupérer une clinique via Query

```php
use App\Clinic\Application\Query\GetClinic\GetClinic;

$query = new GetClinic($clinicId);

/** @var ClinicDto|null $clinic */
$clinic = $queryBus->ask($query);
```

### Lister les cliniques avec filtres

```php
use App\Clinic\Application\Query\ListClinics\ListClinics;

$query = new ListClinics(
    status: ClinicStatus::ACTIVE,
    clinicGroupId: $groupId,
    search: 'paris',
);

/** @var ClinicsCollection $collection */
$collection = $queryBus->ask($query);
```

## Backoffice

L'interface d'administration est accessible via `backoffice.kiveto.com`.

### Routes principales

#### Cliniques

- `GET /clinics` - Liste des cliniques
- `GET /clinics/new` - Formulaire de création
- `POST /clinics/create` - Créer une clinique
- `GET /clinics/{id}/edit` - Formulaire d'édition
- `POST /clinics/{id}/update` - Mettre à jour une clinique

#### Groupes

- `GET /clinic-groups` - Liste des groupes
- `POST /clinic-groups/create` - Créer un groupe
- `POST /clinic-groups/{id}/rename` - Renommer un groupe
- `POST /clinic-groups/{id}/suspend` - Suspendre un groupe
- `POST /clinic-groups/{id}/activate` - Activer un groupe

### Accès

L'accès au backoffice nécessite un compte utilisateur de type `BACKOFFICE`.

## Base de données

### Tables

#### `clinic__groups`

```sql
CREATE TABLE clinic__groups (
  id VARBINARY(16) NOT NULL,
  name VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
);
```

#### `clinic__clinics`

```sql
CREATE TABLE clinic__clinics (
  id VARBINARY(16) NOT NULL,
  clinic_group_id VARBINARY(16) DEFAULT NULL,
  slug VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL,
  time_zone VARCHAR(64) NOT NULL,
  locale VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE INDEX UNIQ_CLINIC_SLUG (slug),
  INDEX IDX_CLINIC_GROUP_ID (clinic_group_id),
  INDEX IDX_CLINIC_STATUS (status),
  PRIMARY KEY (id)
);
```

### Migrations

Les migrations sont dans `migrations/Clinic/`.

```bash
# Générer une migration
make clinic-migrations

# Appliquer les migrations
make migrate-db
```

## Fixtures

Des fixtures de développement sont disponibles via Foundry.

```bash
# Charger les fixtures
make load-fixtures
```

**Fixtures créées**:
- 1 clinique indépendante: "Clinique Vétérinaire de Paris" (slug: `clinic-paris`)
- 1 groupe: "Réseau VetoFrance"
- 1 clinique rattachée au groupe: "Clinique Vétérinaire de Lyon" (slug: `clinic-lyon`)
- 3 cliniques aléatoires supplémentaires

## Tests

### Tests unitaires Domain

```bash
php bin/phpunit tests/Unit/Clinic/Domain/
```

**Couverture**:
- Validation des ValueObjects (ClinicSlug, TimeZone, LocaleCode)
- Invariants des Aggregates
- Enregistrement des Domain Events
- Transitions de statut

### Exécuter tous les tests

```bash
make test
```

## Intégration avec d'autres BC

Les autres Bounded Contexts peuvent référencer une clinique via son `ClinicId` (UUID).

**Important**: Aucune relation Doctrine cross-BC. Les références se font uniquement par UUID.

```php
// Dans un autre BC
private string $clinicId; // stocke l'UUID en string
```

## Règles métier

1. **Slug unique**: Le slug doit être unique globalement. La tentative de création/modification avec un slug existant lève `DuplicateClinicSlugException`.

2. **Statuts**:
   - Une clinique `active` peut être suspendue ou fermée
   - Une clinique `suspended` peut être réactivée ou fermée
   - Une clinique `closed` ne peut **plus** être réactivée (terminal)

3. **Timezone**: Toutes les dates sont stockées en UTC dans la base de données. Le timezone de la clinique est utilisé pour l'affichage dans les interfaces.

4. **Groupe optionnel**: Une clinique peut exister indépendamment sans groupe. L'association à un groupe ne peut pas être modifiée après la création (pour l'instant).

## À venir

Fonctionnalités futures (hors scope MVP):
- Gestion de la devise (currency)
- Adresse et contact de la clinique
- Modification de l'association groupe après création
- Soft delete avec archivage
- BC ClinicMembership pour gérer les accès VET/ASV
