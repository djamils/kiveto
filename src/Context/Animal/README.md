# Animal Bounded Context

Le Bounded Context **Animal** gère les patients animaux dans le système multi-clinique Kiveto. Il implémente le modèle Write (CQRS) et Read pour la gestion complète des animaux, leurs identifications, et leurs liens de propriété avec les clients.

## Responsabilités

- **Gestion des animaux** : Création, modification, archivage des patients animaux
- **Identité animale** : Nom, espèce, race, sexe, statut reproductif, couleur, photo
- **Identification** : Puce électronique, tatouage, passeport, registres (LOF/LOOF), SIRE
- **Cycle de vie** : Statut de vie (vivant/décédé/disparu) avec dates
- **Transfert** : Statut de cession (vendu/donné) avec dates
- **Ownerships** : Gestion des propriétaires primaires et secondaires
- **Contact auxiliaire** : Contact local non-propriétaire optionnel
- **Scoping multi-clinique** : Toutes les opérations sont scopées par `ClinicId`
- **Integration Events** : Consommation d'événements cross-BC (archivage client)

## Ubiquitous Language

### Entités et Statuts
- **Animal** : Patient animal de la clinique, scopé par clinique
- **AnimalStatus** : `ACTIVE` | `ARCHIVED`
- **Species** : `DOG` | `CAT` | `NAC` | `OTHER`
- **Sex** : `MALE` | `FEMALE` | `UNKNOWN`
- **ReproductiveStatus** : `INTACT` | `NEUTERED` | `UNKNOWN`

### Identification
- **Identification** : Ensemble des numéros d'identification (puce, tatouage, passeport, registres)
- **MicrochipNumber** : Numéro de transpondeur (unique par clinique)
- **TattooNumber** : Numéro de tatouage
- **PassportNumber** : Numéro de passeport
- **RegistryType** : `NONE` | `LOF` | `LOOF` | `OTHER`
- **RegistryNumber** : Numéro d'inscription au registre (null si NONE)
- **SIRENumber** : Numéro SIRE

### Cycle de vie et Transfert
- **LifeCycle** : Cycle de vie de l'animal
- **LifeStatus** : `ALIVE` | `DECEASED` | `MISSING`
- **DeceasedAt** : Date de décès (si DECEASED)
- **MissingSince** : Date de disparition (si MISSING)
- **Transfer** : Information de cession
- **TransferStatus** : `NONE` | `SOLD` | `GIVEN`
- **SoldAt** : Date de vente (si SOLD)
- **GivenAt** : Date de don (si GIVEN)

### Propriété
- **Ownership** : Lien entre un animal et un client propriétaire
- **OwnershipRole** : `PRIMARY` | `SECONDARY`
- **OwnershipStatus** : `ACTIVE` | `ENDED`
- **AuxiliaryContact** : Contact local non-propriétaire (optionnel)

## Architecture

```
src/Animal/
├── Domain/
│   ├── Animal.php                              # Aggregate root
│   ├── Event/
│   │   ├── AnimalCreated.php                   # Domain event
│   │   └── AnimalArchived.php                  # Domain event
│   ├── Exception/
│   │   ├── AnimalNotFound.php
│   │   ├── AnimalClinicMismatch.php
│   │   ├── AnimalAlreadyArchived.php
│   │   ├── AnimalArchivedCannotBeModified.php
│   │   ├── AnimalMustHavePrimaryOwner.php
│   │   ├── DuplicateActiveOwner.php
│   │   ├── PrimaryOwnerConflict.php
│   │   ├── OwnershipNotFound.php
│   │   ├── InvalidLifeStatus.php
│   │   ├── InvalidTransferStatus.php
│   │   └── MicrochipAlreadyUsed.php
│   ├── Enum/
│   │   ├── AnimalStatus.php
│   │   ├── Species.php
│   │   ├── Sex.php
│   │   ├── ReproductiveStatus.php
│   │   ├── RegistryType.php
│   │   ├── LifeStatus.php
│   │   ├── TransferStatus.php
│   │   ├── OwnershipRole.php
│   │   └── OwnershipStatus.php
│   ├── ValueObject/
│   │   ├── AnimalId.php
│   │   ├── Identification.php
│   │   ├── LifeCycle.php
│   │   ├── Transfer.php
│   │   ├── AuxiliaryContact.php
│   │   └── Ownership.php
│   └── Port/
│       ├── AnimalRepositoryInterface.php       # Write repository
│       └── AnimalReadRepositoryInterface.php   # Read repository
├── Application/
│   ├── Command/
│   │   ├── CreateAnimal/
│   │   │   ├── CreateAnimal.php
│   │   │   └── CreateAnimalHandler.php
│   │   ├── UpdateAnimalIdentity/
│   │   │   ├── UpdateAnimalIdentity.php
│   │   │   └── UpdateAnimalIdentityHandler.php
│   │   ├── UpdateAnimalLifeCycle/
│   │   │   ├── UpdateAnimalLifeCycle.php
│   │   │   └── UpdateAnimalLifeCycleHandler.php
│   │   ├── UpdateAnimalTransfer/
│   │   │   ├── UpdateAnimalTransfer.php
│   │   │   └── UpdateAnimalTransferHandler.php
│   │   ├── ReplaceAnimalOwners/
│   │   │   ├── ReplaceAnimalOwners.php
│   │   │   └── ReplaceAnimalOwnersHandler.php
│   │   └── ArchiveAnimal/
│   │       ├── ArchiveAnimal.php
│   │       └── ArchiveAnimalHandler.php
│   └── Query/
│       ├── GetAnimalById/
│       │   ├── GetAnimalById.php
│       │   ├── GetAnimalByIdHandler.php
│       │   ├── AnimalView.php
│       │   ├── IdentificationDto.php
│       │   ├── LifeCycleDto.php
│       │   ├── TransferDto.php
│       │   ├── AuxiliaryContactDto.php
│       │   └── OwnershipDto.php
│       └── SearchAnimals/
│           ├── SearchAnimals.php
│           ├── SearchAnimalsHandler.php
│           ├── SearchAnimalsCriteria.php
│           └── AnimalListItemView.php
└── Infrastructure/
    ├── Persistence/
    │   └── Doctrine/
    │       ├── Entity/
    │       │   ├── AnimalEntity.php
    │       │   └── OwnershipEntity.php
    │       ├── AnimalMapper.php
    │       ├── DoctrineAnimalRepository.php
    │       └── DoctrineAnimalReadRepository.php
    └── Messaging/
        └── Consumer/
            └── ClientArchivedIntegrationEventConsumer.php
```

## Règles métier (invariants)

### Animal
1. Un animal appartient à **une seule clinique** (`ClinicId` obligatoire)
2. Un animal `ARCHIVED` ne peut plus être modifié
3. Un animal `ACTIVE` doit avoir **exactement 1 propriétaire PRIMARY actif**
4. Un animal peut avoir **0..n propriétaires SECONDARY actifs**
5. Pas de doublon de propriétaire actif (même `clientId`)

### Identification
1. `MicrochipNumber` est **unique par clinique** (si renseignée)
2. `RegistryType=NONE` ⇒ `RegistryNumber=null`
3. `RegistryType≠NONE` ⇒ `RegistryNumber` peut être renseigné

### Cycle de vie
1. `LifeStatus=ALIVE` ⇒ `DeceasedAt=null` et `MissingSince=null`
2. `LifeStatus=DECEASED` ⇒ `DeceasedAt≠null` et `MissingSince=null`
3. `LifeStatus=MISSING` ⇒ `MissingSince≠null` et `DeceasedAt=null`

### Transfert
1. `TransferStatus=NONE` ⇒ `SoldAt=null` et `GivenAt=null`
2. `TransferStatus=SOLD` ⇒ `SoldAt≠null` et `GivenAt=null`
3. `TransferStatus=GIVEN` ⇒ `GivenAt≠null` et `SoldAt=null`

### AuxiliaryContact
1. Si présent, les 3 champs sont obligatoires : `firstName`, `lastName`, `phoneNumber`

### Scoping
- Toutes les opérations sont scopées par `ClinicId`
- Les repositories write et read appliquent systématiquement ce filtre

## Commands et Queries

### Commands (Write Model)

#### CreateAnimal
Crée un nouvel animal avec identité, identification, et propriétaires.

**Input:**
- `clinicId: string`
- `name: string`
- `species: string` (DOG|CAT|NAC|OTHER)
- `sex: string` (MALE|FEMALE|UNKNOWN)
- `reproductiveStatus: string` (INTACT|NEUTERED|UNKNOWN)
- `isMixedBreed: bool`
- `breedName: ?string`
- `birthDate: ?string` (ISO 8601)
- `color: ?string`
- `photoUrl: ?string`
- `microchipNumber: ?string`
- `tattooNumber: ?string`
- `passportNumber: ?string`
- `registryType: string` (NONE|LOF|LOOF|OTHER)
- `registryNumber: ?string`
- `sireNumber: ?string`
- `lifeStatus: string` (ALIVE|DECEASED|MISSING, default: ALIVE)
- `deceasedAt: ?string` (ISO 8601)
- `missingSince: ?string` (ISO 8601)
- `transferStatus: string` (NONE|SOLD|GIVEN, default: NONE)
- `soldAt: ?string` (ISO 8601)
- `givenAt: ?string` (ISO 8601)
- `auxiliaryContactFirstName: ?string`
- `auxiliaryContactLastName: ?string`
- `auxiliaryContactPhoneNumber: ?string`
- `primaryOwnerClientId: string`
- `secondaryOwnerClientIds: string[]`

**Output:** `animalId: string`

**Invariants validés:**
- Exactement 1 propriétaire PRIMARY
- Pas de doublons de propriétaires
- Unicité du `microchipNumber` (si renseigné)
- Cohérence `LifeCycle` et `Transfer`

---

#### UpdateAnimalIdentity
Met à jour l'identité et l'identification d'un animal.

**Input:**
- `clinicId: string`
- `animalId: string`
- `name: string`
- `species: string`
- `sex: string`
- `reproductiveStatus: string`
- `isMixedBreed: bool`
- `breedName: ?string`
- `birthDate: ?string`
- `color: ?string`
- `photoUrl: ?string`
- `microchipNumber: ?string`
- `tattooNumber: ?string`
- `passportNumber: ?string`
- `registryType: string`
- `registryNumber: ?string`
- `sireNumber: ?string`
- `auxiliaryContactFirstName: ?string`
- `auxiliaryContactLastName: ?string`
- `auxiliaryContactPhoneNumber: ?string`

**Exceptions:**
- `AnimalNotFound`
- `AnimalClinicMismatch`
- `AnimalArchivedCannotBeModified`
- `MicrochipAlreadyUsed`

---

#### UpdateAnimalLifeCycle
Met à jour le cycle de vie de l'animal.

**Input:**
- `clinicId: string`
- `animalId: string`
- `lifeStatus: string`
- `deceasedAt: ?string`
- `missingSince: ?string`

**Exceptions:**
- `AnimalNotFound`
- `AnimalClinicMismatch`
- `AnimalArchivedCannotBeModified`
- `InvalidLifeStatus`

---

#### UpdateAnimalTransfer
Met à jour le statut de cession de l'animal.

**Input:**
- `clinicId: string`
- `animalId: string`
- `transferStatus: string`
- `soldAt: ?string`
- `givenAt: ?string`

**Exceptions:**
- `AnimalNotFound`
- `AnimalClinicMismatch`
- `AnimalArchivedCannotBeModified`
- `InvalidTransferStatus`

---

#### ReplaceAnimalOwners
Remplace **tous** les propriétaires de l'animal.

**Input:**
- `clinicId: string`
- `animalId: string`
- `primaryOwnerClientId: string`
- `secondaryOwnerClientIds: string[]`

**Comportement:**
1. Termine tous les ownerships actifs
2. Crée les nouveaux ownerships (1 PRIMARY + n SECONDARY)

**Exceptions:**
- `AnimalNotFound`
- `AnimalClinicMismatch`
- `AnimalArchivedCannotBeModified`
- `DuplicateActiveOwner`
- `PrimaryOwnerConflict`

---

#### ArchiveAnimal
Archive un animal.

**Input:**
- `clinicId: string`
- `animalId: string`

**Exceptions:**
- `AnimalNotFound`
- `AnimalClinicMismatch`
- `AnimalAlreadyArchived`

---

### Queries (Read Model)

#### GetAnimalById
Retourne les détails complets d'un animal.

**Input:**
- `clinicId: string`
- `animalId: string`

**Output:** `AnimalView | null`

**AnimalView:**
```php
{
    id: string,
    clinicId: string,
    name: string,
    species: string,
    sex: string,
    reproductiveStatus: string,
    isMixedBreed: bool,
    breedName: ?string,
    birthDate: ?string (ISO 8601),
    color: ?string,
    photoUrl: ?string,
    identification: IdentificationDto,
    lifeCycle: LifeCycleDto,
    transfer: TransferDto,
    auxiliaryContact: ?AuxiliaryContactDto,
    ownerships: OwnershipDto[],
    status: string,
    createdAt: string (ISO 8601),
    updatedAt: string (ISO 8601)
}
```

---

#### SearchAnimals
Recherche et pagine les animaux d'une clinique.

**Input:**
- `clinicId: string`
- `searchTerm: ?string` (recherche sur nom)
- `status: ?string` (filtre par AnimalStatus)
- `species: ?string` (filtre par Species)
- `lifeStatus: ?string` (filtre par LifeStatus)
- `ownerClientId: ?string` (filtre par propriétaire)
- `page: int` (défaut: 1)
- `limit: int` (défaut: 20, max: 100)

**Output:**
```php
{
    items: AnimalListItemView[],
    total: int
}
```

**AnimalListItemView:**
```php
{
    id: string,
    name: string,
    species: string,
    sex: string,
    breedName: ?string,
    status: string,
    lifeStatus: string,
    primaryOwnerClientId: string,
    createdAt: string (ISO 8601)
}
```

---

## Integration Events

### ClientArchivedIntegrationEvent (Consommateur)
Événement cross-BC consommé lorsqu'un client est archivé dans le BC Client.

**Comportement:**
1. Charge tous les animaux ayant ce client comme propriétaire actif
2. Pour chaque animal :
   - Si owner **SECONDARY** : termine l'ownership
   - Si owner **PRIMARY** :
     - Si existe un SECONDARY actif : promouvoir le plus ancien en PRIMARY
     - Sinon : archiver l'animal (car invariant : ACTIVE ⇒ 1 PRIMARY)

**Idempotence:** Rejouer l'event ne crée pas d'incohérence

**Transport:** Async via `shared__messenger_messages` (Doctrine)

---

## Modèle de données (Doctrine)

### Table `animal__animal`
| Colonne                       | Type                   | Contraintes          |
|-------------------------------|------------------------|----------------------|
| id                            | UUID (string)          | NOT NULL, PK         |
| clinic_id                     | UUID (string)          | NOT NULL, INDEX      |
| name                          | VARCHAR(255)           | NOT NULL             |
| species                       | ENUM (Species)         | NOT NULL, INDEX      |
| sex                           | ENUM (Sex)             | NOT NULL             |
| reproductive_status           | ENUM                   | NOT NULL             |
| is_mixed_breed                | BOOLEAN                | NOT NULL             |
| breed_name                    | VARCHAR(255)           | NULL                 |
| birth_date                    | DATE                   | NULL                 |
| color                         | VARCHAR(255)           | NULL                 |
| photo_url                     | VARCHAR(500)           | NULL                 |
| microchip_number              | VARCHAR(50)            | NULL, INDEX (unique) |
| tattoo_number                 | VARCHAR(50)            | NULL                 |
| passport_number               | VARCHAR(50)            | NULL                 |
| registry_type                 | ENUM (RegistryType)    | NOT NULL             |
| registry_number               | VARCHAR(50)            | NULL                 |
| sire_number                   | VARCHAR(50)            | NULL                 |
| life_status                   | ENUM (LifeStatus)      | NOT NULL, INDEX      |
| deceased_at                   | DATETIME_IMMUTABLE     | NULL                 |
| missing_since                 | DATETIME_IMMUTABLE     | NULL                 |
| transfer_status               | ENUM (TransferStatus)  | NOT NULL             |
| sold_at                       | DATETIME_IMMUTABLE     | NULL                 |
| given_at                      | DATETIME_IMMUTABLE     | NULL                 |
| auxiliary_contact_first_name  | VARCHAR(255)           | NULL                 |
| auxiliary_contact_last_name   | VARCHAR(255)           | NULL                 |
| auxiliary_contact_phone_number| VARCHAR(50)            | NULL                 |
| status                        | ENUM (AnimalStatus)    | NOT NULL, INDEX      |
| created_at                    | DATETIME_IMMUTABLE     | NOT NULL, INDEX      |
| updated_at                    | DATETIME_IMMUTABLE     | NOT NULL             |

### Table `animal__ownership`
| Colonne       | Type                   | Contraintes            |
|---------------|------------------------|------------------------|
| id            | INT (PK, auto)         | NOT NULL               |
| animal_id     | UUID (string)          | NOT NULL, INDEX, FK    |
| client_id     | UUID (string)          | NOT NULL, INDEX        |
| role          | ENUM (OwnershipRole)   | NOT NULL               |
| status        | ENUM (OwnershipStatus) | NOT NULL, INDEX        |
| started_at    | DATETIME_IMMUTABLE     | NOT NULL               |
| ended_at      | DATETIME_IMMUTABLE     | NULL                   |

**Note:** Cascade delete sur `animal_id` (orphanRemoval=true)

---

## Notes techniques

### Validation applicative vs Invariants
- **Validation applicative** : Format, longueur, présence de champs → dans les handlers
- **Invariants métier** : Règles business critiques → garantis dans le Domain (Aggregate)

### Mapping Domain ↔ Infrastructure
- **AnimalMapper** : Gère la conversion bidirectionnelle entre `Animal` (aggregate) et `AnimalEntity` + `OwnershipEntity[]`
- Les ownerships sont stockés dans une table séparée avec relation 1-N

### Write vs Read repositories
- **Write** : `AnimalRepositoryInterface` → charge l'aggregate complet pour modification
  - `get()` : throw `AnimalNotFound` si introuvable
  - `find()` : retourne `null` si introuvable
- **Read** : `AnimalReadRepositoryInterface` → projections optimisées (DTOs) pour les queries
  - `findById()` : retourne `?AnimalView`

### Transaction boundary
- Une commande = une transaction
- Les domain events sont publiés **après** `flush()` via `EventBusInterface`

---

## Évolution future (hors scope MVP)

- Historique médical (consultations, vaccins, traitements)
- Gestion des images (upload, multiple photos)
- Calcul automatique de l'âge
- Validation avancée des numéros (format puce ISO, SIRE)
- Export des données animal (PDF, fiche)
- Gestion des décès avec motif/cause
- Notifications aux propriétaires (rappels vaccins, etc.)

---

## Changelog

### 2026-01-24 - Initial Release

**Ajouts** :
- ✨ BC Animal complet avec gestion des patients animaux
- ✨ Gestion des identifications multiples (puce, tatouage, registres)
- ✨ Cycle de vie et transfert
- ✨ Ownerships PRIMARY/SECONDARY
- ✨ Consumer `ClientArchivedIntegrationEvent`
- ✨ 6 Commands et 2 Queries
- ✨ Respect conventions projet (sans suffixe Command/Query, interfaces avec suffixe Interface)

**Compatibilité** : ✅ Première version
