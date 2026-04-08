# Commands à Implémenter - Scheduling BC

Ce document liste les commandes restantes à implémenter pour compléter le MVP du BC Scheduling.

## Pattern de Base

Chaque commande suit ce pattern (voir `ScheduleAppointmentHandler` comme référence) :

1. **Command DTO** (readonly class implémentant `CommandInterface`)
2. **Handler** (avec `#[AsMessageHandler]`)
   - Injection des dépendances (repositories, ports, clock, etc.)
   - Validation des préconditions (existence, éligibilité, etc.)
   - Chargement de l'aggregate
   - Appel de la méthode domain
   - Sauvegarde

---

## 1. RescheduleAppointment

**Fichiers :**
- `Application/Command/RescheduleAppointment/RescheduleAppointment.php`
- `Application/Command/RescheduleAppointment/RescheduleAppointmentHandler.php`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
    public \DateTimeImmutable $newStartsAtUtc,
    public int $newDurationMinutes,
) {}
```

**Handler Logic :**
1. Load appointment by ID (throw if not found)
2. Create new TimeSlot
3. If practitioner assigned: check eligibility + overlaps (exclude current appointmentId)
4. Call `$appointment->reschedule($newTimeSlot)`
5. Save

---

## 2. ChangeAppointmentPractitionerAssignee

**Fichiers :**
- `Application/Command/ChangeAppointmentPractitionerAssignee/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
    public string $newPractitionerUserId,
) {}
```

**Handler Logic :**
1. Load appointment
2. Check new practitioner eligibility
3. Check overlaps for new practitioner
4. Create PractitionerAssignee VO
5. Call `$appointment->changePractitionerAssignee($newAssignee)`
6. Save

---

## 3. UnassignAppointmentPractitionerAssignee

**Fichiers :**
- `Application/Command/UnassignAppointmentPractitionerAssignee/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
) {}
```

**Handler Logic :**
1. Load appointment
2. Call `$appointment->unassignPractitioner()`
3. Save

---

## 4. CancelAppointment

**Fichiers :**
- `Application/Command/CancelAppointment/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
) {}
```

**Handler Logic :**
1. Load appointment
2. Call `$appointment->cancel()`
3. Save
4. **(Policy)** Find active waiting entry for this appointment and close it

Pour la policy :
- Injecter `WaitingRoomEntryRepositoryInterface` + `WaitingRoomReadRepositoryInterface`
- Après save, chercher l'entry active
- Si trouvée : `$entry->close($clock->now(), null)` + save

---

## 5. MarkAppointmentNoShow

**Fichiers :**
- `Application/Command/MarkAppointmentNoShow/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
) {}
```

**Handler Logic :**
1. Load appointment
2. Call `$appointment->markNoShow()`
3. Save
4. **(Policy)** Close waiting entry si existe

---

## 6. CompleteAppointment

**Fichiers :**
- `Application/Command/CompleteAppointment/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
) {}
```

**Handler Logic :**
1. Load appointment
2. Call `$appointment->complete()`
3. Save

---

## 7. CreateWaitingRoomWalkInEntry

**Fichiers :**
- `Application/Command/CreateWaitingRoomWalkInEntry/...`

**DTO :**
```php
public function __construct(
    public string $clinicId,
    public ?string $ownerId,
    public ?string $animalId,
    public ?string $foundAnimalDescription,
    public string $arrivalMode, // STANDARD | EMERGENCY
    public int $priority = 0,
    public ?string $triageNotes = null,
) {}
```

**Handler Logic :**
1. Validate owner/animal existence if provided
2. Create WalkInEntry
3. Save

---

## 8. UpdateWaitingRoomTriage

**Fichiers :**
- `Application/Command/UpdateWaitingRoomTriage/...`

**DTO :**
```php
public function __construct(
    public string $waitingRoomEntryId,
    public int $priority,
    public ?string $triageNotes,
    public string $arrivalMode,
) {}
```

**Handler Logic :**
1. Load entry
2. Call `$entry->updateTriage($priority, $triageNotes, WaitingRoomArrivalMode::from($arrivalMode))`
3. Save

---

## 9. CallNextWaitingRoomEntry

**Fichiers :**
- `Application/Command/CallNextWaitingRoomEntry/...`

**DTO :**
```php
public function __construct(
    public string $waitingRoomEntryId,
    public ?string $calledByUserId = null,
) {}
```

**Handler Logic :**
1. Load entry
2. Call `$entry->call($clock->now(), UserId or null)`
3. Save

---

## 10. StartServiceForWaitingRoomEntry

**Fichiers :**
- `Application/Command/StartServiceForWaitingRoomEntry/...`

**DTO :**
```php
public function __construct(
    public string $waitingRoomEntryId,
    public ?string $serviceStartedByUserId = null,
) {}
```

**Handler Logic :**
1. Load entry
2. Call `$entry->startService($clock->now(), UserId or null)`
3. Save

---

## 11. CloseWaitingRoomEntry

**Fichiers :**
- `Application/Command/CloseWaitingRoomEntry/...`

**DTO :**
```php
public function __construct(
    public string $waitingRoomEntryId,
    public ?string $closedByUserId = null,
) {}
```

**Handler Logic :**
1. Load entry
2. Call `$entry->close($clock->now(), UserId or null)`
3. Save

---

## 12. LinkWaitingRoomEntryToOwnerAndAnimal

**Fichiers :**
- `Application/Command/LinkWaitingRoomEntryToOwnerAndAnimal/...`

**DTO :**
```php
public function __construct(
    public string $waitingRoomEntryId,
    public ?string $ownerId,
    public ?string $animalId,
) {}
```

**Handler Logic :**
1. Load entry
2. Validate owner/animal existence if provided
3. Call `$entry->linkToOwnerAndAnimal(OwnerId, AnimalId)`
4. Save

---

## 13. StartServiceForAppointment

**Fichiers :**
- `Application/Command/StartServiceForAppointment/...`

**DTO :**
```php
public function __construct(
    public string $appointmentId,
    public ?string $serviceStartedByUserId = null,
) {}
```

**Handler Logic (Policy/Orchestration) :**
1. Load appointment
2. If `$appointment->serviceStartedAt()` is null:
   - Call `$appointment->startService($clock->now())`
   - Save appointment
3. Find waiting entry for this appointment
4. If found and not IN_SERVICE:
   - Call `$entry->startService($clock->now(), UserId)`
   - Save entry
5. If not found and arrivalMode != EMERGENCY:
   - Create waiting entry from appointment + start service

**Note :** Cette commande est complexe car elle synchronise Appointment ↔ WaitingRoomEntry. C'est le point d'intégration avec ClinicalCare BC.

---

## Tests à Créer

Pour chaque handler, créer un test unitaire similaire à `ScheduleAppointmentHandlerTest` :
- Test success case
- Test validation failures (entity not found, business rule violations)
- Mock tous les ports

---

## Estimation

- **Commandes simples (3-11)** : ~15-20 min chacune = ~2h
- **Commandes avec policy (4-6, 13)** : ~30 min chacune = ~2h
- **Total** : ~4h de développement + 2h de tests = **6h**

Prioriser dans cet ordre pour MVP minimal :
1. CancelAppointment (avec policy close waiting)
2. CreateWaitingRoomWalkInEntry (urgences)
3. StartServiceForWaitingRoomEntry (flow principal)
4. CloseWaitingRoomEntry (fin de consultation)
5. Les autres selon besoins métier
