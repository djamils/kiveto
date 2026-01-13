# AccessControl Bounded Context

Le Bounded Context **AccessControl** gère l'accès des utilisateurs (staff) aux cliniques dans Kiveto.

## Responsabilités

- Gérer les **memberships** : lier un User (IdentityAccess) à une Clinic (BC Clinic) avec un rôle tenant-scoped
- Gérer les **rôles staff** : VETERINARY, ASSISTANT_VETERINARY, CLINIC_ADMIN
- Gérer le **type d'engagement** : EMPLOYEE vs CONTRACTOR
- Gérer la **période de validité** : validFrom/validUntil (surtout utile pour les contractors)
- Activer/désactiver les memberships
- Fournir la liste des cliniques accessibles pour un utilisateur donné

## Architecture

```
src/AccessControl/
├── Domain/
│   ├── ClinicMembership.php (Aggregate)
│   ├── Event/
│   │   ├── ClinicMembershipCreated.php
│   │   ├── ClinicMembershipDisabled.php
│   │   ├── ClinicMembershipEnabled.php
│   │   ├── ClinicMembershipRoleChanged.php
│   │   ├── ClinicMembershipEngagementChanged.php
│   │   └── ClinicMembershipValidityChanged.php
│   ├── Repository/
│   │   └── ClinicMembershipRepositoryInterface.php
│   └── ValueObject/
│       ├── MembershipId.php
│       ├── ClinicId.php (local VO, autonomous)
│       ├── UserId.php (local VO, autonomous)
│       ├── ClinicMemberRole.php (enum)
│       ├── ClinicMembershipStatus.php (enum)
│       └── ClinicMembershipEngagement.php (enum)
├── Application/
│   ├── Command/
│   │   ├── CreateClinicMembership/
│   │   ├── DisableClinicMembership/
│   │   ├── EnableClinicMembership/
│   │   ├── ChangeClinicMembershipRole/
│   │   ├── ChangeClinicMembershipEngagement/
│   │   └── ChangeClinicMembershipValidityWindow/
│   ├── Query/
│   │   ├── ListClinicsForUser/ (liste des cliniques accessibles pour un user)
│   │   ├── GetUserMembershipInClinic/ (récupère la membership d'un user dans une clinic)
│   │   └── ListAllMemberships/ (admin: liste toutes les memberships)
│   ├── Port/
│   │   ├── ClinicMembershipReadRepositoryInterface.php
│   │   └── MembershipAdminRepositoryInterface.php
│   └── Exception/
│       └── ClinicMembershipAlreadyExistsException.php
└── Infrastructure/
    └── Persistence/
        └── Doctrine/
            ├── Entity/
            │   └── ClinicMembershipEntity.php
            ├── Mapper/
            │   └── ClinicMembershipMapper.php
            └── Repository/
                ├── DoctrineClinicMembershipRepository.php (write)
                ├── DoctrineClinicMembershipReadRepository.php (read optimisé)
                └── DoctrineMembershipAdminRepository.php (backoffice)
```

## Ubiquitous Language

### Enum `ClinicMemberRole`
- `CLINIC_ADMIN` : Administrateur de la clinique
- `VETERINARY` : Vétérinaire
- `ASSISTANT_VETERINARY` : Assistant vétérinaire

**Important** : Les rôles sont en majuscules sans diminutifs (VETERINARY, pas VET).

### Enum `ClinicMembershipStatus`
- `ACTIVE` : Membership active
- `DISABLED` : Membership désactivée (temporairement ou définitivement)

### Enum `ClinicMembershipEngagement`
- `EMPLOYEE` : Employé (CDI/permanente)
- `CONTRACTOR` : Prestataire externe (freelance, intérim, CDD)

**Important** : "Freelance" n'est PAS un rôle, c'est un engagement (CONTRACTOR).

## Aggregate ClinicMembership

### Propriétés

- `MembershipId` : Identifiant unique
- `ClinicId` : Référence à la clinique (VO local, autonome)
- `UserId` : Référence à l'utilisateur (VO local, autonome)
- `role` : Rôle du user dans la clinique (ClinicMemberRole)
- `engagement` : Type d'engagement (ClinicMembershipEngagement)
- `status` : Statut (ClinicMembershipStatus)
- `validFrom` : Date de début de validité (non-null, default = createdAt)
- `validUntil` : Date de fin de validité (nullable, recommandé pour CONTRACTOR)
- `createdAt` : Date de création

### DDD Autonomy

**Important** : Le Domain AccessControl est 100% autonome. Il ne dépend d'AUCUN autre BC au niveau Domain.

- `ClinicId` et `UserId` sont des **Value Objects locaux** au BC AccessControl
- Ils encapsulent un UUID (string) mais sont définis dans `App\AccessControl\Domain\ValueObject\`
- Les intégrations avec Clinic BC et IdentityAccess BC se font au niveau Application (anti-corruption layer)

### Invariants

1. **Unicité** : Un user ne peut avoir qu'une seule membership par clinique
   - Contrainte UNIQUE (clinic_id, user_id) en BDD
2. **Window de validité** : `validFrom <= validUntil` si validUntil non null
3. **Membership effective** : Une membership est considérée "effective" si :
   - status = ACTIVE
   - validFrom <= now
   - (validUntil is null OR now <= validUntil)

### Méthodes métier

- `disable()` : Désactiver la membership
- `enable()` : Réactiver la membership
- `changeRole(ClinicMemberRole)` : Changer le rôle
- `changeEngagement(ClinicMembershipEngagement)` : Changer le type d'engagement
- `changeValidityWindow(validFrom, validUntil)` : Changer la fenêtre de validité
- `isEffectiveAt(DateTimeImmutable $now)` : Vérifier si la membership est effective à une date donnée

## Use Cases (MVP)

### Commands

1. **AddUserToClinic** : Créer une nouvelle membership
   - Vérifie que la clinic existe (BC Clinic)
   - Vérifie que le user existe (BC IdentityAccess)
   - Vérifie l'unicité (clinic_id, user_id)
   - Valide la window de validité

2. **DisableClinicMembership** : Désactiver une membership

3. **EnableClinicMembership** : Réactiver une membership

4. **ChangeClinicMembershipRole** : Changer le rôle d'un member

5. **ChangeClinicMembershipEngagement** : Changer le type d'engagement

6. **ChangeClinicMembershipValidity** : Modifier la période de validité

### Queries

1. **ListClinicsForUser** : Liste les cliniques accessibles pour un user
   - Filtre sur status = ACTIVE + window de validité
   - Utilisé pour le flow "Select Clinic" dans l'app clinic.kiveto.com

2. **GetUserMembershipInClinic** : Récupère la membership d'un user dans une clinic
   - Inclut le flag `isEffectiveNow`

3. **ListAllMemberships** : Liste toutes les memberships (backoffice admin)
   - Filtres : clinicId, userId, status, role, engagement

## Infrastructure

### Base de données

**Table** : `access_control__clinic_memberships`

```sql
CREATE TABLE access_control__clinic_memberships (
  id BINARY(16) NOT NULL,
  clinic_id BINARY(16) NOT NULL,
  user_id BINARY(16) NOT NULL,
  role VARCHAR(40) NOT NULL,
  engagement VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL,
  valid_from_utc DATETIME(6) NOT NULL,
  valid_until_utc DATETIME(6) DEFAULT NULL,
  created_at_utc DATETIME(6) NOT NULL,
  UNIQUE INDEX uniq_clinic_user (clinic_id, user_id),
  INDEX idx_user_id (user_id),
  INDEX idx_clinic_id (clinic_id),
  INDEX idx_status (status),
  PRIMARY KEY (id)
);
```

**Migrations** : `migrations/AccessControl/Version20260111224021.php`

### Repositories

- **DoctrineClinicMembershipRepository** : Write repository (persiste l'aggregate)
- **DoctrineClinicMembershipReadRepository** : Read repository optimisé (query DBAL)
- **DoctrineMembershipAdminRepository** : Repository admin (backoffice)

## Intégration

### Backoffice (backoffice.kiveto.com)

**Controller** : `src/Presentation/Backoffice/Controller/ClinicMembershipController.php`

**Routes** :
- `GET /clinic-memberships` : Liste toutes les memberships (avec filtres)
- `GET /clinic-memberships/new` : Formulaire de création
- `POST /clinic-memberships/create` : Créer une membership
- `POST /clinic-memberships/{id}/disable` : Désactiver
- `POST /clinic-memberships/{id}/enable` : Activer
- `POST /clinic-memberships/{id}/role` : Changer le rôle
- `POST /clinic-memberships/{id}/engagement` : Changer l'engagement
- `POST /clinic-memberships/{id}/validity` : Changer la période de validité

### Clinic App (clinic.kiveto.com)

**Flow post-login** :

1. Query `ListClinicsForUser(userId)` (ne remonte que les memberships effectives)
2. Si 0 clinic : afficher erreur "No active clinic access"
3. Si 1 clinic : auto-sélection + redirect dashboard
4. Si >1 clinics : afficher page "Select clinic"

**Controller** : `src/Presentation/Clinic/Controller/SelectClinicController.php`

**Routes** :
- `GET /select-clinic` : Affiche la liste des cliniques accessibles
- `POST /select-clinic` : Enregistre la clinique sélectionnée en session
- `GET /dashboard` : Dashboard (redirige vers /select-clinic si pas de clinic sélectionnée)

**Session** : Service `SelectedClinicContext` (stocke le clinicId actuel en session)

## Tests

### Tests unitaires Domain

`tests/Unit/AccessControl/Domain/ClinicMembershipTest.php`

- Création avec validation de la window
- disable/enable
- changeRole/changeEngagement/changeValidity
- isEffectiveAt(now) avec différents scénarios

### Tests unitaires Application

`tests/Unit/AccessControl/Application/Command/AddUserToClinic/AddUserToClinicHandlerTest.php`

- Création réussie
- Erreurs : clinic inexistante, user inexistant, duplicate membership

### Exécution

```bash
php bin/phpunit tests/Unit/AccessControl/
```

## Fixtures

**Factory** : `fixtures/AccessControl/Factory/ClinicMembershipEntityFactory.php`

**Story** : `fixtures/AccessControl/Story/ClinicMembershipDataStory.php`

Crée des users (vet, assistant, admin, contractor) et les assigne aux cliniques de test.

```bash
php bin/console doctrine:fixtures:load --group=clinic-access
```

## Règles métier importantes

1. **Pas de relations Doctrine cross-BC** : Uniquement des UUID (string)
2. **Rôles sans diminutifs** : VETERINARY (pas VET), ASSISTANT_VETERINARY (pas ASS_VET)
3. **Freelance = engagement CONTRACTOR**, pas un rôle
4. **Dates en UTC** : Toujours utiliser `DateTimeImmutable` avec ClockInterface
5. **Unicité** : Un user ne peut pas avoir 2 memberships sur la même clinique
6. **Membership effective** : status ACTIVE + validFrom <= now + (validUntil null ou now <= validUntil)
7. **EMPLOYEE** : Généralement validUntilUtc = null (pas de limite)
8. **CONTRACTOR** : Généralement validUntilUtc non null (mission limitée dans le temps)

## Évolutions futures (hors MVP)

- Gestion des invitations (envoi email + acceptation)
- Permissions fines par rôle (ACL/RBAC)
- Multi-tenant group admin (gérer plusieurs cliniques d'un groupe)
- Historique des changements de membership
- Notifications lors des changements de rôle/statut

## Dépendances cross-BC

- **BC Clinic** : ClinicId, pour vérifier l'existence de la clinique
- **BC IdentityAccess** : UserId, pour vérifier l'existence du user
- **BC Shared** : ClockInterface, UuidGeneratorInterface, AggregateRoot, DomainEvents

## Documentation complémentaire

Voir `docs/architecture/bounded-contexts.md` pour plus de détails sur l'architecture DDD/CQRS.
