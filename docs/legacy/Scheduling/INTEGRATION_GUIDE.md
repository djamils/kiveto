# Implémentation du BC Scheduling - Guide d'Intégration

## Vue d'Ensemble

Le Bounded Context **Scheduling** a été implémenté avec succès selon l'architecture DDD/CQRS/Hexagonal du projet Kiveto.

## Structure Créée

```
src/Scheduling/
├── Domain/
│   ├── Appointment.php (Aggregate)
│   ├── WaitingRoomEntry.php (Aggregate)
│   ├── Event/ (15 événements)
│   ├── Repository/ (2 interfaces)
│   └── ValueObject/ (11 value objects)
├── Application/
│   ├── Command/ (2 commands + handlers créés, 13 autres à implémenter)
│   ├── Port/ (5 interfaces anti-corruption)
│   └── Exception/ (1 exception)
└── Infrastructure/
    ├── Persistence/Doctrine/
    │   ├── Entity/ (2 entities)
    │   ├── Mapper/ (2 mappers)
    │   └── Repository/ (3 repositories)
    └── Adapter/ (4 adapters DBAL)

tests/Unit/Scheduling/
├── Domain/ (3 test suites)
└── Application/ (1 test suite)

migrations/Scheduling/
└── Version20260130120000.php

fixtures/Scheduling/
├── AppointmentFactory.php
├── WaitingRoomEntryFactory.php
└── Story/SchedulingStory.php

config/services/scheduling.yaml
```

## Commandes Implémentées (MVP)

✅ **ScheduleAppointment** - Planifier un rendez-vous avec vérifications d'éligibilité et overlaps
✅ **CreateWaitingRoomEntryFromAppointment** - Check-in d'un rendez-vous

### Commandes à Implémenter (même pattern)

Pour compléter le MVP selon les spécifications, vous devez créer ces commandes supplémentaires :

1. **RescheduleAppointment** - Modifier date/heure d'un RDV
2. **ChangeAppointmentPractitionerAssignee** - Réassigner un praticien
3. **UnassignAppointmentPractitionerAssignee** - Retirer l'assignation
4. **CancelAppointment** - Annuler un RDV (+ fermer waiting entry si existe)
5. **MarkAppointmentNoShow** - Marquer no-show
6. **CompleteAppointment** - Marquer terminé
7. **CreateWaitingRoomWalkInEntry** - Entrée sans RDV (urgence)
8. **UpdateWaitingRoomTriage** - Modifier priorité/notes/arrivalMode
9. **CallNextWaitingRoomEntry** - WAITING -> CALLED
10. **StartServiceForWaitingRoomEntry** - -> IN_SERVICE
11. **CloseWaitingRoomEntry** - -> CLOSED
12. **LinkWaitingRoomEntryToOwnerAndAnimal** - Associer propriétaire/animal
13. **StartServiceForAppointment** - Sync appointment/waiting (policy)

**Pattern à suivre :** Voir `ScheduleAppointmentHandler` comme template.

## Queries à Implémenter

Pour l'affichage, créer ces queries optimisées DBAL :

1. **GetAgendaForClinicDay** - Liste RDV d'une journée
2. **GetAgendaForClinicWeek** - Vue semaine
3. **GetAppointmentDetails** - Détails d'un RDV
4. **ListWaitingRoom** - File d'attente triée (EMERGENCY first, puis priority, puis arrivedAt)
5. **GetWaitingRoomEntryDetails** - Détails entry
6. **ListEligiblePractitionerAssigneesForClinic** - Liste praticiens éligibles

**Pattern :** Créer des read repositories DBAL dans `Infrastructure/Persistence/Doctrine/Repository/` (voir `DoctrineWaitingRoomReadRepository` comme exemple).

## Intégration Requise

### 1. Services YAML

Le fichier `config/services/scheduling.yaml` doit être importé dans `config/services.yaml` :

```yaml
imports:
  # ... autres imports
  - { resource: 'services/scheduling.yaml' }
```

### 2. Migration Base de Données

Exécuter la migration :

```bash
php bin/console doctrine:migrations:migrate --configuration=migrations/Scheduling
```

Ou selon votre configuration :

```bash
make migrate  # si défini dans Makefile
```

### 3. Vérification des Tables Externes

Les adapters anti-corruption font référence aux tables :
- `access_control__clinic_memberships`
- `client__owners`
- `animal__animals`

Assurez-vous que ces BCs sont migrés avant Scheduling.

### 4. Doctrine Configuration

Vérifier que Doctrine scanne le namespace `App\Scheduling\Infrastructure\Persistence\Doctrine\Entity`.

Si nécessaire, ajouter dans `config/packages/doctrine.yaml` :

```yaml
doctrine:
  orm:
    mappings:
      Scheduling:
        is_bundle: false
        type: attribute
        dir: '%kernel.project_dir%/src/Scheduling/Infrastructure/Persistence/Doctrine/Entity'
        prefix: 'App\Scheduling\Infrastructure\Persistence\Doctrine\Entity'
```

## Tests

### Exécuter les Tests Unitaires

```bash
php bin/phpunit tests/Unit/Scheduling/
```

### Coverage

Les tests couvrent :
- ✅ Domain : Aggregates (Appointment, WaitingRoomEntry) + TimeSlot
- ✅ Application : ScheduleAppointmentHandler (success + failures)

Pour une couverture complète, ajouter des tests pour chaque handler créé.

## Policies d'Orchestration (Future - ClinicalCare BC)

Le code Domain est prêt pour l'intégration avec ClinicalCare. Les hooks sont :

- **Events** : `AppointmentServiceStarted`, `WaitingRoomEntryServiceStarted`
- **Commands préparées** : `StartServiceForAppointment`, `StartServiceForWaitingRoomEntry`

Lorsque ClinicalCare sera implémenté, créer un **EventSubscriber** qui :
- Écoute `ConsultationStarted` (ClinicalCare)
- Déclenche `StartServiceForAppointment` ou `StartServiceForWaitingRoomEntry` si nécessaire

## Optimisations Futures

1. **Caching** : Mettre en cache les listes de praticiens éligibles
2. **Read Models** : Créer des projections dénormalisées pour l'agenda (ex: vue par praticien)
3. **Event Sourcing** (optionnel) : Conserver l'historique complet des changements de RDV
4. **Notifications** : Écouter les événements pour envoyer des rappels SMS/email

## Points d'Attention

### Concurrence

Les overlaps sont vérifiés en lecture (pas de lock). Pour haute concurrence :
- Ajouter un lock optimiste (version field) sur `AppointmentEntity`
- Ou utiliser un lock pessimiste : `SELECT ... FOR UPDATE` dans le conflict checker

### Performance

Les queries DBAL sont optimisées avec indexes. Pour grandes cliniques (>10k RDV/an) :
- Partitionner par année
- Archiver les RDV anciens (status terminal + date < 1 an)

### Unicité Waiting Room Entry

La contrainte `uniq_linked_appointment` empêche les duplicatas. En prod, handle l'exception Doctrine et retourner une erreur utilisateur claire.

## Documentation Supplémentaire

- **Domain Model** : Voir `src/Scheduling/README.md`
- **Ubiquitous Language** : Défini dans le README du BC
- **Architecture Globale** : `docs/README_architecture_vet_saas.md`

## Prochaines Étapes Recommandées

1. ✅ Intégrer les services YAML
2. ✅ Exécuter la migration
3. 🔄 Implémenter les 13 commandes restantes (2-3h de travail)
4. 🔄 Implémenter les 6 queries (2h)
5. 🔄 Créer les controllers Symfony (backoffice + clinic app)
6. 🔄 Intégrer dans l'UI (agenda + waiting room widgets)
7. ✅ Exécuter les tests

## Support

En cas de questions sur l'implémentation :
- Les patterns sont cohérents avec AccessControl BC
- Tous les handlers suivent la même structure
- Les tests fournissent des exemples d'usage

**Le BC Scheduling est prêt à être intégré et étendu ! 🚀**
