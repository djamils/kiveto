# Services Configuration - Correction

## ✅ Correction effectuée

Comme pour tous les autres Bounded Contexts, les services Scheduling sont maintenant déclarés **directement dans `config/services.yaml`** et non dans un fichier séparé.

---

## 🔧 Changements appliqués

### ❌ Supprimé
- `config/services/scheduling.yaml` (fichier supprimé)

### ✅ Ajouté
Services Scheduling dans `config/services.yaml` (lignes ~213-240) :

```yaml
# ============================================================================
# BOUNDED CONTEXT: SCHEDULING
# ============================================================================

App\Scheduling\Domain\Repository\AppointmentRepositoryInterface:
    class: App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository

App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface:
    class: App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineWaitingRoomEntryRepository

App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface:
    class: App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineWaitingRoomReadRepository

App\Scheduling\Application\Port\MembershipEligibilityCheckerInterface:
    class: App\Scheduling\Infrastructure\Adapter\AccessControl\DbalMembershipEligibilityChecker

App\Scheduling\Application\Port\AppointmentConflictCheckerInterface:
    class: App\Scheduling\Infrastructure\Adapter\DbalAppointmentConflictChecker

App\Scheduling\Application\Port\OwnerExistenceCheckerInterface:
    class: App\Scheduling\Infrastructure\Adapter\Client\DbalOwnerExistenceChecker

App\Scheduling\Application\Port\AnimalExistenceCheckerInterface:
    class: App\Scheduling\Infrastructure\Adapter\Animal\DbalAnimalExistenceChecker

App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper: ~
App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\WaitingRoomEntryMapper: ~
```

---

## 📋 Ce qui est déclaré (et pourquoi)

### ✅ Déclarations nécessaires (alias d'interfaces)

1. **Repositories (Domain)** : Interfaces → Implémentations Doctrine
   - `AppointmentRepositoryInterface` → `DoctrineAppointmentRepository`
   - `WaitingRoomEntryRepositoryInterface` → `DoctrineWaitingRoomEntryRepository`

2. **Ports (Application)** : Interfaces → Adapters
   - `WaitingRoomReadRepositoryInterface` → `DoctrineWaitingRoomReadRepository`
   - `MembershipEligibilityCheckerInterface` → `DbalMembershipEligibilityChecker`
   - `AppointmentConflictCheckerInterface` → `DbalAppointmentConflictChecker`
   - `OwnerExistenceCheckerInterface` → `DbalOwnerExistenceChecker`
   - `AnimalExistenceCheckerInterface` → `DbalAnimalExistenceChecker`

3. **Mappers** : Utilisés dans les repositories
   - `AppointmentMapper`
   - `WaitingRoomEntryMapper`

### ⚙️ Auto-découverts (pas besoin de déclarer)

Grâce à `autowire: true` et `autoconfigure: true` dans `_defaults` :

1. **Command Handlers** : Auto-enregistrés via `#[AsMessageHandler]`
   - `ScheduleAppointmentHandler`
   - `CancelAppointmentHandler`
   - Etc. (14 handlers)

2. **Query Handlers** : Auto-enregistrés via `#[AsMessageHandler]`
   - `GetAgendaForClinicDayHandler`
   - `ListWaitingRoomHandler`
   - Etc. (6 handlers)

3. **Controllers** : Auto-enregistrés via `tags: ['controller.service_arguments']`
   - Tous les controllers dans `App\Presentation\Clinic\Controller\`

4. **Entities Doctrine** : Auto-découvertes via mapping dans `doctrine.yaml`

---

## 🔍 Comparaison avec les autres BCs

Cette configuration est **identique au pattern** utilisé pour :

### Client BC
```yaml
# BOUNDED CONTEXT: CLIENT
App\Client\Domain\Repository\ClientRepositoryInterface:
    class: App\Client\Infrastructure\Persistence\Doctrine\Repository\DoctrineClientRepository

App\Client\Application\Port\ClientReadRepositoryInterface:
    class: App\Client\Infrastructure\Persistence\Doctrine\Repository\DoctrineClientReadRepository

App\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper: ~
```

### Animal BC
```yaml
# BOUNDED CONTEXT: ANIMAL
App\Animal\Domain\Port\AnimalRepositoryInterface:
    class: App\Animal\Infrastructure\Persistence\Doctrine\DoctrineAnimalRepository

App\Animal\Domain\Port\AnimalReadRepositoryInterface:
    class: App\Animal\Infrastructure\Persistence\Doctrine\DoctrineAnimalReadRepository

App\Animal\Infrastructure\Persistence\Doctrine\AnimalMapper: ~
```

### Clinic BC
```yaml
# BOUNDED CONTEXT: CLINIC
App\Clinic\Domain\Repository\ClinicRepositoryInterface:
    class: App\Clinic\Infrastructure\Persistence\Doctrine\Repository\DoctrineClinicRepository

App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface:
    class: App\Clinic\Infrastructure\Persistence\Doctrine\Repository\DoctrineClinicGroupRepository

# ...
```

✅ **Scheduling suit maintenant exactement le même pattern !**

---

## ✅ Vérification

```bash
# Vérifier que les services sont bien enregistrés
php bin/console debug:container Scheduling

# Devrait lister tous les services Scheduling

# Vérifier les alias d'interfaces
php bin/console debug:container \
  App\\Scheduling\\Domain\\Repository\\AppointmentRepositoryInterface

# Devrait afficher : 
# alias for "App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository"
```

---

## 🎯 Pourquoi cette approche ?

### Avantages

1. **Cohérence** : Même pattern pour tous les BCs
2. **Centralisation** : Tout dans `services.yaml`, facile à trouver
3. **Simplicité** : Pas besoin de fichier séparé pour quelques lignes
4. **Convention Symfony** : Fichiers `config/services/*.yaml` généralement pour packages tiers

### Inconvénient évité

Avoir un fichier séparé `config/services/scheduling.yaml` pour seulement ~10 lignes de config serait de la sur-architecture. 

Les autres BCs (Client, Animal, Clinic, etc.) n'ont pas de fichier séparé, donc Scheduling non plus.

---

## 📚 Documentation mise à jour

Les documents suivants ont été corrigés :
- ✅ `CONFIG_UPDATE.md` - Mention du fichier services.yaml
- ✅ `DEPLOYMENT_GUIDE.md` - Section configuration corrigée

---

## 🎉 Configuration finale

Le module Scheduling utilise maintenant la **configuration standard** du projet :

```
config/
├── packages/
│   ├── doctrine.yaml              ✅ Mapping Scheduling ajouté
│   └── doctrine_migrations.yaml   ✅ Path migrations ajouté
└── services.yaml                   ✅ Services Scheduling ajoutés (pas de fichier séparé)
```

**Cohérent, simple, maintenable !** 👍

---

*Document de correction - 1er février 2026*
