# Guide Rapide - Module Scheduling

## 🚀 Démarrage Rapide

### 1. Migrations

```bash
php bin/console doctrine:migrations:migrate --em=scheduling
```

### 2. Fixtures (Optionnel - Dev uniquement)

```bash
php bin/console doctrine:fixtures:load --group=scheduling --append
```

### 3. Accès UI

```
http://clinic.kiveto.local/scheduling/dashboard
```

---

## 💡 Cas d'Usage Fréquents

### Créer un RDV depuis le Code

```php
use App\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;

$command = new ScheduleAppointment(
    clinicId: '01234567-89ab-cdef-0123-456789abcdef',
    ownerId: 'owner-uuid',
    animalId: 'animal-uuid',
    practitionerUserId: 'user-uuid',
    startsAtUtc: new \DateTimeImmutable('2026-02-15 14:00:00'),
    durationMinutes: 30,
    reason: 'Consultation',
    notes: 'Première visite'
);

$appointmentId = $commandBus->dispatch($command);
```

### Check-in un RDV

```php
use App\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment\CreateWaitingRoomEntryFromAppointment;

$command = new CreateWaitingRoomEntryFromAppointment(
    appointmentId: 'appointment-uuid',
    arrivalMode: 'STANDARD', // ou 'EMERGENCY'
    priority: 0
);

$entryId = $commandBus->dispatch($command);
```

### Créer une Urgence Walk-in

```php
use App\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry\CreateWaitingRoomWalkInEntry;

$command = new CreateWaitingRoomWalkInEntry(
    clinicId: 'clinic-uuid',
    ownerId: null, // Animal inconnu
    animalId: null,
    foundAnimalDescription: 'Chat noir, blessure patte avant',
    arrivalMode: 'EMERGENCY',
    priority: 10,
    triageNotes: 'Saignement abondant, état critique'
);

$entryId = $commandBus->dispatch($command);
```

### Démarrer un Service

```php
use App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry;

$command = new StartServiceForWaitingRoomEntry(
    waitingRoomEntryId: 'entry-uuid',
    serviceStartedByUserId: $currentUser->getId()
);

$commandBus->dispatch($command);
```

### Fermer une Entrée

```php
use App\Scheduling\Application\Command\CloseWaitingRoomEntry\CloseWaitingRoomEntry;

$command = new CloseWaitingRoomEntry(
    waitingRoomEntryId: 'entry-uuid',
    closedByUserId: $currentUser->getId()
);

$commandBus->dispatch($command);
```

### Récupérer l'Agenda du Jour

```php
use App\Scheduling\Application\Query\GetAgendaForClinicDay\GetAgendaForClinicDay;

$query = new GetAgendaForClinicDay(
    clinicId: 'clinic-uuid',
    date: new \DateTimeImmutable('2026-02-15'),
    practitionerUserId: null // Tous les praticiens
);

$appointments = $queryBus->ask($query);
// Array<AppointmentItem>
```

### Récupérer la File d'Attente

```php
use App\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;

$query = new ListWaitingRoom(
    clinicId: 'clinic-uuid'
);

$entries = $queryBus->ask($query);
// Array<WaitingRoomEntryItem> (triés par priority DESC, arrivedAt ASC)
```

---

## 🔒 Permissions & Rôles

### Recommandations

| Action | Rôle Minimum | Notes |
|--------|-------------|-------|
| Voir agenda | ASSISTANT_VETERINARY | Lecture seule OK |
| Créer RDV | ASSISTANT_VETERINARY | Secrétariat |
| Check-in | ASSISTANT_VETERINARY | Accueil |
| Créer walk-in | ASSISTANT_VETERINARY | Urgences |
| Démarrer service | VETERINARY | Praticiens uniquement |
| Fermer entrée | VETERINARY | Praticiens uniquement |
| Modifier RDV | CLINIC_ADMIN | Admin ou propriétaire |
| Annuler RDV | CLINIC_ADMIN | Admin ou propriétaire |

### Exemple dans Controller

```php
#[Route('/scheduling/start-service/{id}', methods: ['POST'])]
#[IsGranted('ROLE_VETERINARY')]
public function startService(string $id): Response
{
    // ...
}
```

---

## 🧪 Tests

### Run Tests Unitaires

```bash
# Tous les tests Scheduling
php bin/phpunit tests/Unit/Scheduling/

# Domain uniquement
php bin/phpunit tests/Unit/Scheduling/Domain/

# Application uniquement
php bin/phpunit tests/Unit/Scheduling/Application/
```

### Coverage

```bash
XDEBUG_MODE=coverage php bin/phpunit tests/Unit/Scheduling/ --coverage-html var/coverage-scheduling
```

---

## 🐛 Debugging

### Enable Query Log (Dev)

```yaml
# config/packages/dev/doctrine.yaml
doctrine:
    dbal:
        logging: true
        profiling: true
```

### Check Migrations Status

```bash
php bin/console doctrine:migrations:status --em=scheduling
```

### Dump Service Config

```bash
php bin/console debug:container --tag=scheduling
```

---

## 📈 Monitoring

### Métriques Recommandées

- Nombre de RDV créés par jour
- Taux de no-show
- Durée moyenne en waiting room
- Pics d'affluence (heatmap)

### Logs Critiques

```php
// Dans handlers
$this->logger->info('Appointment scheduled', [
    'appointment_id' => $appointmentId,
    'clinic_id' => $command->clinicId,
    'practitioner_id' => $command->practitionerUserId,
]);
```

---

## 🔗 Intégration ClinicalCare BC (Future)

### Event Subscriber Exemple

```php
namespace App\Scheduling\Infrastructure\EventSubscriber;

use App\Scheduling\Domain\Event\WaitingRoomEntryServiceStarted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StartConsultationOnServiceStartedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            WaitingRoomEntryServiceStarted::class => 'onServiceStarted',
        ];
    }

    public function onServiceStarted(WaitingRoomEntryServiceStarted $event): void
    {
        // Dispatch command vers ClinicalCare BC
        // $this->commandBus->dispatch(new StartConsultation(...));
    }
}
```

---

## 📞 Support

- **Docs** : `/src/Scheduling/*.md`
- **Issues** : GitHub Issues
- **Slack** : #scheduling-module

---

*Guide mis à jour le 1er février 2026*
