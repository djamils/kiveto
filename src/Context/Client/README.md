# Client Bounded Context

Le Bounded Context **Client** gère les clients / propriétaires d'animaux dans le système multi-clinique Kiveto. Il implémente le modèle Write (CQRS) et Read pour la gestion complète des clients et de leurs moyens de contact.

## Responsabilités

- **Gestion des clients** : Création, modification, archivage des clients
- **Identité client** : Prénom, nom
- **Moyens de contact** : Gestion des téléphones et emails avec labels et primaires
- **Scoping multi-clinique** : Toutes les opérations sont scopées par `ClinicId`
- **Integration Events** : Publication d'événements cross-BC (ex: archivage client)

## Ubiquitous Language

- **Client** : Propriétaire / client facturable et contactable, scopé par clinique
- **ClientStatus** : `ACTIVE` | `ARCHIVED`
- **ContactMethod** : Moyen de contact (phone ou email) avec label, valeur et indicateur primary
- **ContactMethodType** : `PHONE` | `EMAIL`
- **ContactLabel** : `MOBILE` | `HOME` | `WORK` | `OTHER`
- **ClientIdentity** : Prénom + Nom du client
- **PostalAddress** : Adresse postale internationale (unique par client, optionnelle)

## Architecture

```
src/Client/
├── Domain/
│   ├── Client.php                              # Aggregate root
│   ├── Event/
│   │   ├── ClientCreated.php                   # Domain event
│   │   ├── ClientArchived.php                  # Domain event
│   │   ├── ClientIdentityUpdated.php           # Domain event
│   │   ├── ClientContactMethodsReplaced.php    # Domain event
│   │   └── ClientArchivedIntegrationEvent.php  # Integration event (cross-BC)
│   ├── Exception/
│   │   ├── ClientNotFoundException.php
│   │   ├── ClientClinicMismatchException.php
│   │   ├── ClientAlreadyArchivedException.php
│   │   ├── ClientArchivedCannotBeModifiedException.php
│   │   ├── ClientMustHaveAtLeastOneContactMethodException.php
│   │   ├── PrimaryContactMethodConflictException.php
│   │   └── DuplicateContactMethodException.php
│   └── ValueObject/
│       ├── ClientId.php
│       ├── ClientStatus.php
│       ├── ClientIdentity.php
│       ├── ContactMethod.php
│       ├── ContactMethodType.php
│       ├── ContactLabel.php
│       ├── EmailAddress.php
│       └── PhoneNumber.php
├── Application/
│   ├── Command/
│   │   ├── CreateClient/
│   │   │   ├── CreateClient.php
│   │   │   ├── CreateClientHandler.php
│   │   │   └── ContactMethodDto.php
│   │   ├── UpdateClientIdentity/
│   │   │   ├── UpdateClientIdentity.php
│   │   │   └── UpdateClientIdentityHandler.php
│   │   ├── ReplaceClientContactMethods/
│   │   │   ├── ReplaceClientContactMethods.php
│   │   │   ├── ReplaceClientContactMethodsHandler.php
│   │   │   └── ContactMethodDto.php
│   │   └── ArchiveClient/
│   │       ├── ArchiveClient.php
│   │       └── ArchiveClientHandler.php
│   ├── Query/
│   │   ├── GetClientById/
│   │   │   ├── GetClientById.php
│   │   │   ├── GetClientByIdHandler.php
│   │   │   ├── ClientView.php
│   │   │   └── ContactMethodDto.php
│   │   └── SearchClients/
│   │       ├── SearchClients.php
│   │       ├── SearchClientsHandler.php
│   │       ├── SearchClientsCriteria.php
│   │       └── ClientListItemView.php
│   └── Port/
│       ├── ClientRepositoryInterface.php        # Write repository
│       └── ClientReadRepositoryInterface.php    # Read repository
└── Infrastructure/
    └── Persistence/
        └── Doctrine/
            ├── Entity/
            │   ├── ClientEntity.php
            │   └── ContactMethodEntity.php
            ├── Mapper/
            │   └── ClientMapper.php
            └── Repository/
                ├── DoctrineClientRepository.php
                └── DoctrineClientReadRepository.php
```

## Règles métier (invariants)

### Client
1. Un client appartient à **une seule clinique** (`ClinicId` obligatoire)
2. Un client `ARCHIVED` ne peut plus être modifié
3. Un client doit avoir **au moins 1 ContactMethod** (PHONE ou EMAIL)

### ContactMethods
1. **Au plus 1 primary PHONE** autorisé par client
2. **Au plus 1 primary EMAIL** autorisé par client
3. Interdiction de supprimer le dernier ContactMethod
4. Interdiction des doublons stricts (même type + même valeur)

### Scoping
- Toutes les opérations sont scopées par `ClinicId`
- Les repositories write et read appliquent systématiquement ce filtre

## Commands et Queries

### Commands (Write Model)

#### CreateClient
Crée un nouveau client avec identité et moyens de contact.

**Input:**
- `clinicId: string`
- `firstName: string`
- `lastName: string`
- `contactMethods: ContactMethodDto[]` (au moins 1)

**Output:** `clientId: string`

**Invariants validés:**
- Au moins 1 contact method
- Au plus 1 primary phone
- Au plus 1 primary email
- Pas de doublons

---

#### UpdateClientIdentity
Met à jour l'identité (prénom/nom) d'un client.

**Input:**
- `clinicId: string`
- `clientId: string`
- `firstName: string`
- `lastName: string`

**Exceptions:**
- `ClientNotFoundException`
- `ClientClinicMismatchException`
- `ClientArchivedCannotBeModifiedException`

---

#### UpdateClientPostalAddress ✨ NOUVEAU
Met à jour ou supprime l'adresse postale d'un client.

**Input:**
- `clinicId: string`
- `clientId: string`
- `postalAddress: PostalAddressDto | null`

**PostalAddressDto:**
- `streetLine1: string` (required)
- `city: string` (required)
- `countryCode: string` (required, ISO 3166-1 alpha-2, ex: "FR")
- `streetLine2: string | null`
- `postalCode: string | null`
- `region: string | null`

**Comportement:**
- Si `postalAddress = null` : supprime l'adresse
- Sinon : met à jour l'adresse (crée ou remplace)

**Exceptions:**
- `ClientNotFoundException`
- `ClientClinicMismatchException`
- `ClientArchivedCannotBeModifiedException`
- `InvalidArgumentException` (validation PostalAddress)

---

#### ReplaceClientContactMethods
Remplace **toute** la collection de moyens de contact (MVP simple).

**Input:**
- `clinicId: string`
- `clientId: string`
- `contactMethods: ContactMethodDto[]` (au moins 1)

**Invariants validés:**
- Au moins 1 contact method
- Au plus 1 primary phone
- Au plus 1 primary email
- Pas de doublons

**Exceptions:**
- `ClientNotFoundException`
- `ClientClinicMismatchException`
- `ClientArchivedCannotBeModifiedException`
- `ClientMustHaveAtLeastOneContactMethodException`
- `PrimaryContactMethodConflictException`
- `DuplicateContactMethodException`

---

#### ArchiveClient
Archive un client et publie un **Integration Event** pour notifier les autres BC.

**Input:**
- `clinicId: string`
- `clientId: string`

**Output:** `void`

**Comportement:**
1. Charge le Client aggregate
2. Vérifie qu'il appartient à `clinicId`
3. Archive le client (status = `ARCHIVED`)
4. Enregistre un **domain event** `ClientArchived`
5. Publie un **integration event** `ClientArchivedIntegrationEvent`

**Exceptions:**
- `ClientNotFoundException`
- `ClientClinicMismatchException`
- `ClientAlreadyArchivedException`

**Note importante:**
- Le BC `Animal` (futur) consommera cet integration event pour gérer les ownerships
- Pas de résolution d'ownership dans ce handler (principe de séparation des BC)

---

### Queries (Read Model)

#### GetClientById
Retourne les détails complets d'un client (incluant tous ses contact methods).

**Input:**
- `clinicId: string`
- `clientId: string`

**Output:** `ClientView | null`

**ClientView:**
```php
{
    id: string,
    clinicId: string,
    firstName: string,
    lastName: string,
    status: string,
    contactMethods: ContactMethodDto[],
    postalAddress: PostalAddressDto | null,
    createdAt: string (ISO 8601),
    updatedAt: string (ISO 8601)
}
```

**PostalAddressDto:**
```php
{
    streetLine1: string,
    city: string,
    countryCode: string,
    streetLine2: string | null,
    postalCode: string | null,
    region: string | null
}
```

---

#### SearchClients
Recherche et pagine les clients d'une clinique.

**Input:**
- `clinicId: string`
- `searchTerm: ?string` (recherche sur firstName/lastName)
- `status: ?string` (filtre par status)
- `page: int` (défaut: 1)
- `limit: int` (défaut: 20, max: 100)

**Output:**
```php
{
    items: ClientListItemView[],
    total: int
}
```

**ClientListItemView:**
```php
{
    id: string,
    firstName: string,
    lastName: string,
    status: string,
    primaryPhone: ?string,
    primaryEmail: ?string,
    createdAt: string (ISO 8601)
}
```

**Note:**
- `primaryPhone` / `primaryEmail` : retourne le contact marqué `isPrimary=true`, sinon le premier trouvé

---

## Integration Events

### ClientArchivedIntegrationEvent
Événement cross-BC publié lorsqu'un client est archivé.

**Format:**
- **Event name:** `client.client.archived.v1`
- **Bounded Context:** `client`
- **Version:** 1

**Payload:**
```php
{
    clientId: string,
    clinicId: string
}
```

**Consommateurs attendus:**
- **Animal BC** (futur) : Résoudre les ownerships (archiver les animaux orphelins ou marquer comme sans propriétaire)

**Transport:** Async via `shared__messenger_messages` (Doctrine)

---

## Modèle de données (Doctrine)

### Table `client__client`
| Colonne       | Type                | Contraintes          |
|---------------|---------------------|----------------------|
| id            | UUID (PK)           | NOT NULL             |
| clinic_id     | UUID                | NOT NULL, INDEX      |
| first_name    | VARCHAR(255)        | NOT NULL             |
| last_name     | VARCHAR(255)        | NOT NULL             |
| status        | ENUM (ClientStatus) | NOT NULL, INDEX      |
| postal_address_street_line_1 | VARCHAR(255) | NULL              |
| postal_address_street_line_2 | VARCHAR(255) | NULL              |
| postal_address_postal_code   | VARCHAR(20)  | NULL              |
| postal_address_city          | VARCHAR(255) | NULL              |
| postal_address_region        | VARCHAR(255) | NULL              |
| postal_address_country_code  | VARCHAR(2)   | NULL              |
| created_at    | DATETIME_IMMUTABLE  | NOT NULL, INDEX      |
| updated_at    | DATETIME_IMMUTABLE  | NOT NULL             |

### Table `client__contact_method`
| Colonne       | Type                     | Contraintes          |
|---------------|--------------------------|----------------------|
| id            | UUID (PK)                | NOT NULL             |
| client_id     | UUID                     | NOT NULL, INDEX, FK  |
| type          | ENUM (ContactMethodType) | NOT NULL, INDEX      |
| label         | ENUM (ContactLabel)      | NOT NULL             |
| value         | VARCHAR(255)             | NOT NULL             |
| is_primary    | BOOLEAN                  | NOT NULL, DEFAULT 0  |

**Note:** La cascade delete des contact_methods est gérée explicitement dans le repository (DELETE puis INSERT).

---

## Exemples d'utilisation

### Créer un client avec 2 téléphones

```php
$command = new CreateClient(
    clinicId: '01942f6a-...',
    firstName: 'Jean',
    lastName: 'Dupont',
    contactMethods: [
        new ContactMethodDto(
            type: 'phone',
            label: 'mobile',
            value: '+33612345678',
            isPrimary: true
        ),
        new ContactMethodDto(
            type: 'phone',
            label: 'home',
            value: '+33145678901',
            isPrimary: false
        ),
    ]
);

$clientId = $commandBus->dispatch($command);
```

### Archiver un client

```php
$command = new ArchiveClient(
    clinicId: '01942f6a-...',
    clientId: '01942f80-...'
);

$commandBus->dispatch($command);

// => ClientArchived domain event enregistré
// => ClientArchivedIntegrationEvent publié async
```

### Rechercher des clients actifs

```php
$query = new SearchClients(
    clinicId: '01942f6a-...',
    searchTerm: 'Dupont',
    status: 'active',
    page: 1,
    limit: 20
);

$result = $queryBus->ask($query);
// => ['items' => [...], 'total' => 42]
```

---

## Notes techniques

### Validation applicative vs Invariants
- **Validation applicative** : Format, longueur, présence de champs → dans les handlers
- **Invariants métier** : Règles business critiques → garantis dans le Domain (Aggregate)

### Mapping Domain ↔ Infrastructure
- **ClientMapper** : Gère la conversion bidirectionnelle entre `Client` (aggregate) et `ClientEntity` + `ContactMethodEntity[]`
- Les contact methods sont stockés dans une table séparée avec relation 1-N

### Write vs Read repositories
- **Write** : `ClientRepositoryInterface` → charge l'aggregate complet pour modification
  - `get()` : throw `ClientNotFoundException` si introuvable
  - `find()` : retourne `null` si introuvable
- **Read** : `ClientReadRepositoryInterface` → projections optimisées (DTOs) pour les queries
  - `findById()` : retourne `?ClientView`

### Transaction boundary
- Une commande = une transaction
- Les domain events sont publiés **après** `flush()` via `DomainEventPublisher`
- Les integration events sont publiés **après** les domain events

---

## Évolution future (hors scope MVP)

- Preferences : `locale`, `preferredChannel` (EMAIL|SMS|PHONE|NONE)
- Consents : `marketingOptIn`, `smsOptIn`, `emailOptIn`
- Unicité email par clinic (contrainte DB + validation domain)
- **Adresses postales multiples** (billing + shipping séparées)
- Notes / commentaires sur le client
- Historique des modifications (event sourcing partiel)

---

## Changelog

### 2026-01-17 - Patch : Adresse Postale + Scalabilité

**Ajouts** :
- ✨ `PostalAddress` ValueObject dans Shared (international-friendly)
- ✨ Command `UpdateClientPostalAddress` (met à jour ou supprime l'adresse)
- ✨ Event `ClientPostalAddressUpdated`
- ✨ Colonnes `postal_address_*` dans `ClientEntity` (embedded inline)
- ✨ PostalAddressDto dans `ClientView`

**Améliorations** :
- ♻️ `EmailAddress` et `PhoneNumber` déplacés vers Shared (réutilisabilité)
- ♻️ Tous les imports mis à jour

**Documentation** :
- 📝 `PATCH_CLIENT_POSTAL_ADDRESS.md` (détails complets du patch)

**Compatibilité** : ✅ Rétrocompatible (pas de breaking change)
