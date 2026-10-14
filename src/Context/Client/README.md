# Client Bounded Context

Le Bounded Context **Client** gère les clients / propriétaires d'animaux dans le système multi-clinique Kiveto. Il implémente le modèle Write (CQRS) et Read pour la gestion complète des clients et de leurs moyens de contact.

## Responsabilités

- **Gestion des clients** : Création, modification, archivage des clients
- **Identité client** : Civilité, prénom, nom
- **Moyens de contact** : Gestion des téléphones et emails avec labels et primaires
- **Scoping multi-clinique** : Toutes les opérations sont scopées par `ClinicId`
- **Integration Events** : Publication d'événements cross-BC (ex: archivage client)

## Ubiquitous Language

- **Client** : Propriétaire / client facturable et contactable, scopé par clinique
- **ClientStatus** : `ACTIVE` | `ARCHIVED`
- **ContactMethod** : Moyen de contact (phone ou email) avec label, valeur et indicateur primary
- **ContactMethodType** : `PHONE` | `EMAIL`
- **ContactLabel** : `MOBILE` | `HOME` | `WORK` | `OTHER`
- **ClientIdentity** : Prénom + Nom du client, avec sa civilité (`Civility` : `MADAM` | `MISTER` | `UNSPECIFIED`, « Mme » / « M. » devant le nom)
- **PostalAddress** : Adresse postale internationale (unique par client, optionnelle)
- **AuthorizedPerson** : Personne autorisée à déposer et récupérer les animaux du dossier (une seule par client, optionnelle) — civilité, prénom, nom, lien (`AuthorizedPersonRelationship`), téléphone, date de désignation
- **ContactChannel** : `SMS` | `EMAIL` | `PHONE` | `MAIL` ; seuls `SMS` et `EMAIL` sont automatisables
- **Consent** : Accord du client pour un type d'envoi (`ConsentKind`) sur un ou plusieurs canaux automatisables, daté de son recueil
- **ConsentKind** : `APPOINTMENT_REMINDERS` | `VACCINATION_REMINDERS` | `CLINICAL_DOCUMENTS` | `BILLING` | `MARKETING`
- **CommunicationPreferences** : Canal préféré pour les échanges ponctuels + liste des consentements actifs
- **ClientNote** : Note interne sur un client (`INFORMATION` ou `ATTENTION`), jamais visible du client, jamais modifiée
- **ClientCommunication** : Échange avec le client (appel, SMS, e-mail, courrier) consigné par l'équipe — une entrée de journal, pas un envoi

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

### AuthorizedPerson
1. Au plus **une** personne autorisée par client ; prénom et nom obligatoires
2. Redésigner la **même personne** (même nom, casse ignorée) conserve la date de désignation d'origine ; désigner quelqu'un d'autre repart de la date du jour
3. `null` retire l'autorisation

### Consents et préférences
1. Un consentement **existe ou n'existe pas** : le retirer le supprime, il n'y a pas d'état « refusé »
2. Un consentement porte **au moins un canal**, et uniquement des canaux automatisables (`SMS`, `EMAIL`)
3. Au plus un consentement par `ConsentKind`
4. Réviser les préférences **conserve la date de recueil** d'un consentement tant qu'il ne couvre aucun canal nouveau ; accepter un canal supplémentaire est un nouveau consentement, daté du jour

### ClientNote et ClientCommunication
1. Agrégats distincts du Client, en **ajout seul** (ni modification ni suppression)
2. Rattachés à un client **de la clinique** : le handler charge le client avant d'écrire
3. Contenu d'une note : non vide, 2 000 caractères max ; objet d'un échange : non vide, 160 caractères max ; corps : 4 000 caractères max
4. L'auteur est un **nom figé à l'écriture** (pas une référence utilisateur)
5. Restent possibles sur un client archivé (ils ne modifient pas la fiche)

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

#### ChangeClientAuthorizedPerson
Désigne, remplace ou retire la personne autorisée.

**Input:**
- `clinicId: string`
- `clientId: string`
- `authorizedPerson: ?AuthorizedPersonDto` (`civility`, `firstName`, `lastName`, `relationship`, `?phoneNumber`) — `null` retire l'autorisation

**Output:** `void` — event `ClientAuthorizedPersonChanged`

**Exceptions:** `ClientNotFoundException`, `ClientArchivedCannotBeModifiedException`, `\ValueError` (civilité ou lien inconnu), `\InvalidArgumentException` (nom vide, téléphone invalide)

---

#### UpdateClientCommunicationPreferences
Enregistre le canal préféré et l'état complet des consentements.

**Input:**
- `clinicId: string`
- `clientId: string`
- `preferredChannel: ?string`
- `channelsByConsentKind: array<string, list<string>>` — un type absent ou sans canal est retiré

**Output:** `void` — event `ClientCommunicationPreferencesUpdated`

**Exceptions:** `ClientNotFoundException`, `ClientArchivedCannotBeModifiedException`, `\ValueError` (type ou canal inconnu), `\InvalidArgumentException` (canal non automatisable)

---

#### AddClientNote
Ajoute une note interne.

**Input:** `clinicId`, `clientId`, `kind` (`information` | `attention`), `content`, `authorName`

**Output:** `string` (id de la note) — event `ClientNoteAdded`

---

#### LogClientCommunication
Consigne un échange au journal du client. N'envoie rien.

**Input:** `clinicId`, `clientId`, `channel`, `direction` (`inbound` | `outbound`), `subject`, `?body`, `authorName`

**Output:** `string` (id de l'entrée) — event `ClientCommunicationLogged`

---

### Queries (Read Model)

#### ListClientNotes / ListClientCommunications
Notes internes et échanges consignés d'un client, du plus récent au plus ancien.

**Input:** `clinicId`, `clientId`

**Output:** `list<ClientNoteView>` (`id`, `kind`, `content`, `authorName`, `createdAt`) / `list<ClientCommunicationView>` (`id`, `channel`, `direction`, `subject`, `body`, `authorName`, `occurredAt`)

`ClientView` (GetClientById) expose en plus `authorizedPerson`, `preferredChannel` et `consents` (consentements actifs uniquement).

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

- Preferences : `locale`
- Historique horodaté des consentements (aujourd'hui seule la date de recueil du consentement actif est conservée)
- Envoi réel de messages depuis le journal des communications
- Type de client (professionnel, éleveur, association) et civilité du client
- Unicité email par clinic (contrainte DB + validation domain)
- **Adresses postales multiples** (billing + shipping séparées)
- Historique des modifications (event sourcing partiel)

---

## Changelog

### 2026-10-10 - Fiche client : personne autorisée, consentements, notes, journal

**Ajouts** :
- ✨ `AuthorizedPerson` et `CommunicationPreferences` sur l'agrégat `Client` (colonnes JSON `authorized_person` et `communication_preferences`)
- ✨ Commands `ChangeClientAuthorizedPerson` et `UpdateClientCommunicationPreferences`
- ✨ Agrégats `ClientNote` (table `client__client_notes`) et `ClientCommunication` (table `client__client_communications`), commands `AddClientNote` / `LogClientCommunication`, queries `ListClientNotes` / `ListClientCommunications`

**Corrections** :
- 🐛 `DoctrineClientRepository::save()` ne persistait pas l'adresse postale lors de la mise à jour d'un client existant : `UpdateClientPostalAddress` était sans effet en base

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
