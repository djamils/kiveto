---
title: 'Scheduling — PlanningBlock Aggregate'
slug: 'scheduling-planning-block'
created: '2026-04-19'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7', 'Doctrine ORM', 'Doctrine DBAL', 'PHP 8.3', 'Twig', 'Stimulus/Turbo']
files_to_modify:
  - src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php
  - src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandler.php
  - src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/AppointmentItem.php
  - src/Presentation/Clinic/Controller/Scheduling/Planning/PlanningController.php
  - templates/clinic/scheduling/planning/index.html.twig
  - assets/js/pages/scheduling/planning.js
  - tests/Unit/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandlerTest.php
code_patterns:
  - 'Private constructor + factory methods (create/reconstitute) — mandatory for every aggregate'
  - 'Ports in Application/Port/ with {Service}Interface naming'
  - 'Domain repository interfaces in Domain/Repository/'
  - 'Domain events extend AbstractDomainEvent, BOUNDED_CONTEXT=scheduling, VERSION=1'
  - 'Doctrine entity + Mapper(toDomain/toEntity/updateEntity) + Repository pattern'
  - 'DBAL raw SQL with UUID binary (toBinary/toRfc4122) for read-heavy queries'
  - 'Enum with capability methods (acceptsAppointments, hasCapacityLimit) + completeness test snapshot'
  - 'Wall-time local storage (date YYYY-MM-DD + start_time HH:MM + end_time HH:MM), UTC conversion at read time'
  - 'RecurrenceExpander injected in both query AND write-path adapters — single source of truth for expansion logic'
  - 'Overlap logic: existing.start < new.end AND existing.end > new.start (strict, no containment)'
  - 'Appointment counter uses temporal overlap SQL — no FK on Appointment v1'
test_patterns:
  - 'Mock all ports with createMock(), expects(self::once()) for delegation'
  - 'Domain aggregate tests: factory methods, recordedDomainEvents(), pullDomainEvents()'
  - 'Capability completeness test: explicit expectation table per enum case (snapshot pattern)'
  - 'Handler tests: happy path + each validation failure path'
  - 'RecurrenceExpander: NONE/DAILY/WEEKLY/WEEKDAYS + edge cases (rangeEnd < baseDate, rangeStart > until, WEEKLY non-Monday base, range=single day)'
  - 'Overlap tests: exact-same slot, overlap-start, overlap-end, containment, adjacent (must NOT match)'
---

# Tech-Spec: Scheduling — PlanningBlock Aggregate

**Created:** 2026-04-19

## Overview

### Problem Statement

La page `/scheduling/planning` affiche une grille de blocs (consultation, chirurgie, congé…) avec des données mock hardcodées dans `planning.js`. L'agrégat `PlanningBlock` n'existe pas dans le Scheduling BC — les blocs ne sont ni persistés, ni validés, ni consommés par `ScheduleAppointment` pour filtrer les créneaux disponibles.

### Solution

Créer `PlanningBlock` comme nouvel agrégat dans le Scheduling BC (aux côtés de `Appointment` et `WaitingRoomEntry`), avec domain + persistence + ports `PlanningBlockFinderInterface` / `PlanningBlockOverlapCheckerInterface` / `ClinicTimezoneResolverInterface` / `PlanningBlockAppointmentCounterInterface`, service de domaine `RecurrenceExpander` (seule source de vérité pour l'expansion), intégration dans `ScheduleAppointment`, puis wirer `PlanningController` avec les vraies données.

### Scope

**In Scope:**
- Agrégat `PlanningBlock` (invariants de recouvrement, types d'activité, capacité, récurrence)
- Commands : Create / Update / Delete PlanningBlock
- Query : `ListPlanningBlocksForClinicDateRange`
- Doctrine entity + repository + mapper
- 4 ports Application + 4 adapters DBAL
- Service de domaine `RecurrenceExpander` (DAILY / WEEKLY / WEEKDAYS)
- Intégration dans `ScheduleAppointment` (valider bloc, capacité, type d'activité)
- `AppointmentItem.isOrphaned` calculé en lecture via LEFT JOIN
- Wire `PlanningController` : vraies data via `data-*` attributes Twig, popup create/edit/delete bloc, mini-calendar synchro
- `planning.js` : remplacer `VETS` + `blocks` mock par données serveur
- Fixtures couvrant les états UI

**Out of Scope:**
- Drag-to-select pour création de bloc (WIP UI séparé)
- Self-service vétérinaire : v1 manager uniquement
- Notifications staff sur changement de planning
- Export ICS / sync calendrier externe
- Templates de planning applicables en masse
- WaitingRoom / Consultation BC
- Multi-ressources (un bloc = un praticien v1)
- Gestion d'équipe / backup
- Mutation de `RecurrenceRule` sur bloc existant (v1 : delete + recreate)
- Blocs spanning midnight (v1 : un bloc = un jour calendaire)
- Patterns de récurrence avancés (BYDAY, BYMONTHDAY, RRULE complète)

---

## Context for Development

### Toutes les décisions tranchées

#### Contrainte hybride PlanningBlock → ScheduleAppointment
- **Existence du bloc : SOFT** — si aucun bloc configuré, RDV autorisé
- **Capacité si bloc existe : HARD** — RDV refusé si `countActive >= floor(capacity * slotDurationHours)`
- **Compatibilité type : HARD** — RDV refusé si `PlanningBlockType::acceptsAppointments() === false`

#### Deux règles temporelles distinctes

**Overlap bloc/bloc** (conflit entre deux blocs du même praticien) :
```
existing.start_time < new.end_time  AND  existing.end_time > new.start_time
```
Adjacent (end = next.start) n'est **pas** un overlap. Utilisé par `PlanningBlockOverlapCheckerInterface`.

**Containment slot/bloc** (un RDV doit tenir entièrement dans un bloc) :
```
block.start_time <= slot.start_time  AND  block.end_time >= slot.end_time
```
Comparaison en UTC (voir décision isOrphaned). Utilisé par `DbalPlanningBlockFinder` et le calcul isOrphaned.

#### Seule source de vérité pour la récurrence
`RecurrenceExpander` est injecté dans TOUS les composants qui ont besoin de savoir si un bloc est actif à une date donnée : `ListPlanningBlocksForClinicDateRangeHandler`, `DbalPlanningBlockFinder`, `DbalPlanningBlockAppointmentCounter`. Toute expansion de récurrence passe par ce service.

#### Port overlap checker
`Application/Port/PlanningBlockOverlapCheckerInterface`
```php
public function hasOverlap(
    ClinicId $clinicId,
    UserId $practitionerId,
    string $date,       // YYYY-MM-DD
    string $startTime,  // HH:MM
    string $endTime,    // HH:MM
    ?PlanningBlockId $excludeId = null,
): bool;
```
SQL overlap strict : `start_time < :endTime AND end_time > :startTime`

#### Port finder (write-path validation) — avec expansion récurrence
`Application/Port/PlanningBlockFinderInterface`
```php
public function findActiveBlockFor(
    ClinicId $clinicId,
    UserId $practitionerId,
    string $localDate,      // YYYY-MM-DD (déjà converti de UTC par le handler appelant)
    string $localStartTime, // HH:MM
    string $localEndTime,   // HH:MM
): ?PlanningBlockReadModel;
```
L'adapter DBAL implémentant ce port :
1. Charge les blocs candidats : `date <= :localDate AND (JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :localDate)`
2. Pour chaque candidat, appelle `RecurrenceExpander::expand($rule, $row['date'], new \DateTimeImmutable($localDate), new \DateTimeImmutable($localDate))`
3. Si `$localDate` ∈ occurrences ET `$row['start_time'] <= $localStartTime AND $row['end_time'] >= $localEndTime`, retourne le ReadModel
4. Compte les RDV dans cette fenêtre via `PlanningBlockAppointmentCounterInterface`

`PlanningBlockReadModel` (readonly class) : `id`, `type` (PlanningBlockType), `capacityPerHour` (int), `currentAppointmentCount` (int), `acceptsAppointments` (bool).
Champ nommé `capacityPerHour` pour lever l'ambiguïté avec la capacité-par-créneau (calculée dans le handler).

#### Port timezone resolver
`Application/Port/ClinicTimezoneResolverInterface`
```php
public function resolveTimezone(ClinicId $clinicId): \DateTimeZone;
```
DBAL : `SELECT time_zone FROM clinic__clinics WHERE id = :id`
**Prérequis :** grep `TimezoneResolver\|resolveTimezone` dans `src/` avant de créer ce port.
Adapter `DbalClinicTimezoneResolver` : wrapper `new \DateTimeZone($tzString)` dans un try/catch ; si exception, lancer `InvalidClinicTimezoneStored` (`Domain/Exception/`, extends `\RuntimeException`) avec le message `"Invalid timezone stored for clinic {clinicId}: {value}"`.

#### Port counter (overlap-based, sans FK)
`Application/Port/PlanningBlockAppointmentCounterInterface`
```php
public function countActiveInWindow(
    ClinicId $clinicId,
    UserId $practitionerId,
    \DateTimeImmutable $windowStartUtc,
    \DateTimeImmutable $windowEndUtc,
): int;
```
SQL :
```sql
SELECT COUNT(*) as cnt
FROM scheduling__appointments
WHERE clinic_id = :clinicId
  AND practitioner_user_id = :practitionerId
  AND status = 'PLANNED'
  AND starts_at_utc < :windowEndUtc
  AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :windowStartUtc
```
Pas de FK `planning_block_id` sur Appointment v1. Appartenance déduite par chevauchement temporel.

#### Invariants de mutation UpdatePlanningBlock (vérifiés dans le HANDLER, pas l'agrégat)
Les checks D.1–D.5 sont effectués par `UpdatePlanningBlockHandler` AVANT d'appeler `$block->update()`. L'agrégat `update()` ne reçoit que les nouvelles valeurs et émet `PlanningBlockUpdated` sans re-vérifier.
- D.1 `CannotChangePlanningBlockTypeWithActiveAppointments`
- D.2 `CannotShrinkPlanningBlockBelowExistingAppointments`
- D.3 `CannotReduceCapacityBelowExistingAppointmentCount`
- D.4 `CannotModifyRecurrenceRuleOnExistingBlock` — s'applique à **tout sous-champ** de `RecurrenceRule` (freq ET until). Comparaison : `$newRule->freq() !== $block->recurrenceRule()->freq() || $newRule->until() !== $block->recurrenceRule()->until()`. Un manager qui veut raccourcir la récurrence supprime et recrée.
- D.5 changement praticien avec RDV → même exception que D.2

#### Exceptions domaine — toutes dans `Domain/Exception/`
Toutes étendent `\DomainException` :
- `CannotCreateOverlappingPlanningBlock`
- `CannotChangePlanningBlockTypeWithActiveAppointments`
- `CannotShrinkPlanningBlockBelowExistingAppointments`
- `CannotReduceCapacityBelowExistingAppointmentCount`
- `CannotModifyRecurrenceRuleOnExistingBlock`
- `CannotDeletePlanningBlockWithAppointments`
- `InvalidPlanningBlockTimeRange`
- `UnsupportedRecurrencePattern`
- `PlanningBlockNotFoundException`

#### PlanningBlockType capability matrix
| Type         | `acceptsAppointments()` | `hasCapacityLimit()` |
|---|---|---|
| CONSULTATION | true  | true  |
| CHIRURGIE    | true  | true  |
| BILAN        | true  | true  |
| URGENCE      | true  | true  |
| GARDE        | true  | true  |
| CONGE        | false | false |
| FORMATION    | false | false |
| ADMIN        | false | false |

#### Validations TimeRange
- Format heure : `([01][0-9]|2[0-3]):[0-5][0-9]` (heures 00–23, minutes 00–59)
- Format date : `\d{4}-\d{2}-\d{2}`
- `start_time >= end_time` (comparaison lexicographique) → `InvalidPlanningBlockTimeRange`
- Durée < 15 min → `InvalidPlanningBlockTimeRange`
- Note : le span midnight est implicitement rejeté par `start >= end` (ex : '23:00' > '01:00')

#### RecurrenceRule — validation until
- `until` doit être `>= baseDate` (validé dans `PlanningBlock::create/update`)
- `until` non fourni = pas de fin
- Format YYYY-MM-DD

#### Normalisation data-planning-blocks (JS)
`PlanningController` sérialise les blocs avec les clés attendues par `planning.js` :
```php
array_map(fn(PlanningBlockView $b) => [
    'id'         => $b->id,
    'vet'        => $b->practitionerUserId,
    'date'       => $b->date,
    'start'      => $b->startTime,
    'end'        => $b->endTime,
    'type'       => $b->type,
    'capacity'   => $b->capacityPerHour,
    'note'       => $b->note ?? '',
    'recurrence' => $b->recurrenceFreq,
], $planningBlocks)
```

#### Hard delete / pas de status
Pas de `status`, pas de `deletedAt`. Delete protégé par `CannotDeletePlanningBlockWithAppointments`.

#### Race condition (DETTE D-R1)
Snapshot non-verrouillé pour `currentAppointmentCount`. Surbooking de 1-2 max possible. Solution v2 : advisory lock ou SELECT ... FOR UPDATE.

#### Stockage wall-time local
- `date` VARCHAR(10), `start_time` VARCHAR(5), `end_time` VARCHAR(5)
- Timezone clinique : `clinic__clinics.time_zone`
- Table : `scheduling__planning_blocks`

### Patterns codebase confirmés

| Pattern | Localisation |
|---|---|
| Agrégat | `Domain/Appointment.php`, `Domain/WaitingRoomEntry.php` |
| Domain repository | `Domain/Repository/AppointmentRepositoryInterface.php` |
| Port + DBAL adapter | `Application/Port/AppointmentConflictCheckerInterface.php` + `Infrastructure/Adapter/DbalAppointmentConflictChecker.php` |
| Événement domaine | `Domain/Event/AppointmentScheduled.php` |
| Doctrine entity + mapper | `AppointmentEntity.php` + `AppointmentMapper.php` |
| Query DBAL direct | `GetAgendaForClinicDateRangeHandler.php` |
| Enum capabilities | `MembershipRole::canHoldVeterinaryCredentials()` (Clinic BC) |
| Test handler | `ScheduleAppointmentHandlerTest.php` |
| Test agrégat | `AppointmentTest.php` |

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Scheduling/Domain/Appointment.php` | Modèle agrégat |
| `src/Context/Scheduling/Domain/ValueObject/TimeSlot.php` | VO UTC — référence ; TimeRange est l'équivalent local |
| `src/Context/Scheduling/Application/Port/AppointmentConflictCheckerInterface.php` | Modèle port + naming |
| `src/Context/Scheduling/Infrastructure/Adapter/DbalAppointmentConflictChecker.php` | Modèle DBAL + overlap SQL |
| `src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php` | Point d'intégration |
| `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandler.php` | Modèle query DBAL + LEFT JOIN |
| `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/AppointmentItem.php` | DTO à enrichir |
| `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php` | Modèle wiring contrôleur |
| `assets/js/pages/scheduling/planning.js` | JS à dé-mocker |
| `templates/clinic/scheduling/planning/index.html.twig` | Template à wirer |

---

## Implementation Plan

Exécuter dans l'ordre des dépendances.

---

### Task 1 — Value Object `PlanningBlockId`

**File (new):** `src/Context/Scheduling/Domain/ValueObject/PlanningBlockId.php`

Étend `AbstractUuidId` — pattern identique à `AppointmentId` :
```php
final class PlanningBlockId extends AbstractUuidId
{
    public static function fromString(string $value): self { return new self($value); }
}
```

---

### Task 2 — Enum `PlanningBlockType` avec capabilities

**File (new):** `src/Context/Scheduling/Domain/ValueObject/PlanningBlockType.php`

```php
enum PlanningBlockType: string
{
    case CONSULTATION = 'consultation';
    case CHIRURGIE    = 'chirurgie';
    case BILAN        = 'bilan';
    case URGENCE      = 'urgence';
    case GARDE        = 'garde';
    case CONGE        = 'conge';
    case FORMATION    = 'formation';
    case ADMIN        = 'admin';

    public function acceptsAppointments(): bool
    {
        return match ($this) {
            self::CONGE, self::FORMATION, self::ADMIN => false,
            default => true,
        };
    }

    public function hasCapacityLimit(): bool
    {
        return match ($this) {
            self::CONGE, self::FORMATION, self::ADMIN => false,
            default => true,
        };
    }
}
```

Mapping Doctrine dans `PlanningBlockEntity` : `#[ORM\Column(type: 'string', length: 50, enumType: PlanningBlockType::class)]`

**File (new):** `tests/Unit/Context/Scheduling/Domain/ValueObject/PlanningBlockTypeCapabilityCompletenessTest.php`

```php
#[\PHPUnit\Framework\Attributes\DataProvider('capabilityProvider')]
public function testCapabilityMatrix(PlanningBlockType $type, bool $acceptsAppointments, bool $hasCapacityLimit): void
{
    self::assertSame($acceptsAppointments, $type->acceptsAppointments());
    self::assertSame($hasCapacityLimit, $type->hasCapacityLimit());
}

public static function capabilityProvider(): iterable
{
    yield 'CONSULTATION' => [PlanningBlockType::CONSULTATION, true,  true];
    yield 'CHIRURGIE'    => [PlanningBlockType::CHIRURGIE,    true,  true];
    yield 'BILAN'        => [PlanningBlockType::BILAN,        true,  true];
    yield 'URGENCE'      => [PlanningBlockType::URGENCE,      true,  true];
    yield 'GARDE'        => [PlanningBlockType::GARDE,        true,  true];
    yield 'CONGE'        => [PlanningBlockType::CONGE,        false, false];
    yield 'FORMATION'    => [PlanningBlockType::FORMATION,    false, false];
    yield 'ADMIN'        => [PlanningBlockType::ADMIN,        false, false];
}

public function testAllCasesAreCovered(): void
{
    $covered = array_column(iterator_to_array(self::capabilityProvider()), 0);
    self::assertCount(\count(PlanningBlockType::cases()), $covered);
}
```

---

### Task 3 — Value Object `TimeRange` (wall-time local)

**File (new):** `src/Context/Scheduling/Domain/ValueObject/TimeRange.php`

```php
final readonly class TimeRange
{
    public function __construct(
        private string $date,      // YYYY-MM-DD
        private string $startTime, // HH:MM
        private string $endTime,   // HH:MM
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (1 !== preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/', $this->date)) {
            throw new InvalidPlanningBlockTimeRange('Invalid date format: ' . $this->date);
        }
        // Validates HH in [00–23] and MM in [00–59]. Also implicitly rejects midnight-spanning
        // blocks because '23:xx' > '01:xx' lexicographically, which the start >= end check catches.
        $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (1 !== preg_match($timePattern, $this->startTime) ||
            1 !== preg_match($timePattern, $this->endTime)) {
            throw new InvalidPlanningBlockTimeRange(
                'Invalid time format (expected HH:MM with H∈[00–23], M∈[00–59]).'
            );
        }
        if ($this->startTime >= $this->endTime) {
            throw new InvalidPlanningBlockTimeRange('start_time must be strictly before end_time.');
        }
        if ($this->durationMinutes() < 15) {
            throw new InvalidPlanningBlockTimeRange('Minimum block duration is 15 minutes.');
        }
    }

    public function date(): string      { return $this->date; }
    public function startTime(): string { return $this->startTime; }
    public function endTime(): string   { return $this->endTime; }

    public function durationMinutes(): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->startTime));
        [$eh, $em] = array_map('intval', explode(':', $this->endTime));
        return ($eh * 60 + $em) - ($sh * 60 + $sm);
    }

    /** Convert to UTC DateTimeImmutable using the given timezone. */
    public function toUtcStart(\DateTimeZone $tz): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($this->date . ' ' . $this->startTime, $tz))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public function toUtcEnd(\DateTimeZone $tz): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($this->date . ' ' . $this->endTime, $tz))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public function equals(self $other): bool
    {
        return $this->date === $other->date
            && $this->startTime === $other->startTime
            && $this->endTime === $other->endTime;
    }
}
```

**File (new):** `tests/Unit/Context/Scheduling/Domain/ValueObject/TimeRangeTest.php`

Couvrir : construction valide, `start >= end` → exception, durée < 15 min → exception, heure invalide (25:00, 23:61) → exception, format invalide → exception, `toUtcStart/End` avec timezone, `durationMinutes()`.

---

### Task 4 — Value Object `RecurrenceRule` (JSON-serializable)

**File (new):** `src/Context/Scheduling/Domain/ValueObject/RecurrenceRule.php`

```php
final readonly class RecurrenceRule
{
    public const string NONE     = 'NONE';
    public const string DAILY    = 'DAILY';
    public const string WEEKLY   = 'WEEKLY';
    public const string WEEKDAYS = 'WEEKDAYS';

    private const array VALID_FREQS = [self::NONE, self::DAILY, self::WEEKLY, self::WEEKDAYS];

    private function __construct(
        private string $freq,
        private ?string $until = null, // YYYY-MM-DD inclusive, null = no end
    ) {
        if (!\in_array($freq, self::VALID_FREQS, true)) {
            throw new UnsupportedRecurrencePattern($freq);
        }
    }

    public static function none(): self                          { return new self(self::NONE); }
    public static function daily(?string $until = null): self    { return new self(self::DAILY, $until); }
    public static function weekly(?string $until = null): self   { return new self(self::WEEKLY, $until); }
    public static function weekdays(?string $until = null): self { return new self(self::WEEKDAYS, $until); }

    public function freq(): string   { return $this->freq; }
    public function until(): ?string { return $this->until; }
    public function isRecurring(): bool { return self::NONE !== $this->freq; }

    public function toJson(): string
    {
        return json_encode(['freq' => $this->freq, 'until' => $this->until], \JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Corrupt RecurrenceRule JSON in database: ' . $e->getMessage(), 0, $e);
        }
        if (!isset($data['freq']) || !\is_string($data['freq'])) {
            throw new \RuntimeException('Corrupt RecurrenceRule JSON: missing or invalid "freq" key.');
        }
        return new self($data['freq'], isset($data['until']) && \is_string($data['until']) ? $data['until'] : null);
    }
}
```

**File (new):** `tests/Unit/Context/Scheduling/Domain/ValueObject/RecurrenceRuleTest.php`

Couvrir : toutes les factories, roundtrip JSON, `until=null` vs until défini, freq invalide → `UnsupportedRecurrencePattern`, JSON malformé → RuntimeException, JSON sans clé `freq` → RuntimeException.

---

### Task 5 — Service de domaine `RecurrenceExpander`

**File (new):** `src/Context/Scheduling/Domain/Service/RecurrenceExpander.php`

```php
final class RecurrenceExpander
{
    /**
     * Returns dates (YYYY-MM-DD) in [$rangeStart, $rangeEnd] matching the rule anchored on $baseDate.
     * $rangeStart and $rangeEnd are treated as date boundaries (time component ignored).
     *
     * @param string $baseDate format YYYY-MM-DD attendu — validation à la charge du caller
     * @return list<string>
     */
    public function expand(
        RecurrenceRule $rule,
        string $baseDate,              // YYYY-MM-DD, validated format expected by caller
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): array {
        $rangeStartStr = $rangeStart->format('Y-m-d');
        $rangeEndStr   = $rangeEnd->format('Y-m-d');

        if ($rangeStartStr > $rangeEndStr) {
            return [];
        }

        if (!$rule->isRecurring()) {
            if ($baseDate >= $rangeStartStr && $baseDate <= $rangeEndStr) {
                return [$baseDate];
            }
            return [];
        }

        $until        = $rule->until() ?? $rangeEndStr;
        $effectiveEnd = $until < $rangeEndStr ? $until : $rangeEndStr;
        $effectiveStart = $baseDate > $rangeStartStr ? $baseDate : $rangeStartStr;

        // If entire range is after until, nothing to expand
        if ($effectiveStart > $effectiveEnd) {
            return [];
        }

        $baseDow = (int)(new \DateTimeImmutable($baseDate))->format('N'); // 1=Mon…7=Sun

        $dates  = [];
        $cursor = new \DateTimeImmutable($effectiveStart);
        $end    = new \DateTimeImmutable($effectiveEnd);

        while ($cursor->format('Y-m-d') <= $end->format('Y-m-d')) {
            $dow = (int) $cursor->format('N');
            $matches = match ($rule->freq()) {
                RecurrenceRule::DAILY    => true,
                RecurrenceRule::WEEKLY   => $dow === $baseDow,
                RecurrenceRule::WEEKDAYS => $dow >= 1 && $dow <= 5,
                default                 => false,
            };
            if ($matches) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }
}
```

**File (new):** `tests/Unit/Context/Scheduling/Domain/Service/RecurrenceExpanderTest.php`

Couvrir :
- NONE dans range → [baseDate]
- NONE hors range → []
- DAILY sur 7 jours → 7 occurrences
- WEEKLY ancré lundi → 4 occurrences sur 4 semaines
- WEEKLY ancré mercredi → occurrences chaque mercredi (pas lundi)
- WEEKDAYS → exclut S/D
- `until` borne la récurrence
- `rangeEnd < baseDate` → []
- `rangeStart > until` → []
- range = 1 jour = baseDate → [baseDate]
- `until = baseDate` → [baseDate]

---

### Task 6 — Exceptions domaine

**Files (new) :** `src/Context/Scheduling/Domain/Exception/` (9 fichiers)

Toutes étendent `\DomainException` sauf `UnsupportedRecurrencePattern` et `PlanningBlockNotFoundException` qui étendent `\RuntimeException` :

```php
// Extends \DomainException — invariants métier
final class CannotCreateOverlappingPlanningBlock extends \DomainException {}
final class CannotChangePlanningBlockTypeWithActiveAppointments extends \DomainException {}
final class CannotShrinkPlanningBlockBelowExistingAppointments extends \DomainException {}
final class CannotReduceCapacityBelowExistingAppointmentCount extends \DomainException {}
final class CannotModifyRecurrenceRuleOnExistingBlock extends \DomainException {}
final class CannotDeletePlanningBlockWithAppointments extends \DomainException {}
final class InvalidPlanningBlockTimeRange extends \DomainException {}

// Extends \RuntimeException — erreurs de données / invariants techniques
final class UnsupportedRecurrencePattern extends \RuntimeException {}
final class PlanningBlockNotFoundException extends \RuntimeException {}
final class InvalidClinicTimezoneStored extends \RuntimeException {}
```

---

### Task 7 — Événements domaine `PlanningBlock`

**Files (new) :**
- `src/Context/Scheduling/Domain/Event/PlanningBlockCreated.php`
- `src/Context/Scheduling/Domain/Event/PlanningBlockUpdated.php`
- `src/Context/Scheduling/Domain/Event/PlanningBlockDeleted.php`

Pattern identique à `AppointmentScheduled` — `BOUNDED_CONTEXT = 'scheduling'`, `VERSION = 1`, readonly, `aggregateId()` retourne `$planningBlockId`.

`PlanningBlockCreated` payload : `planningBlockId`, `clinicId`, `practitionerUserId`, `type`, `date`, `startTime`, `endTime`, `capacityPerHour`, `recurrenceFreq`, `recurrenceUntil`, `note`.

`PlanningBlockUpdated` payload : mêmes champs (état après mise à jour).

`PlanningBlockDeleted` payload : `planningBlockId`, `clinicId`.

---

### Task 8 — Interface repository domaine

**File (new):** `src/Context/Scheduling/Domain/Repository/PlanningBlockRepositoryInterface.php`

```php
interface PlanningBlockRepositoryInterface
{
    public function save(PlanningBlock $block): void;
    public function findById(PlanningBlockId $id): ?PlanningBlock;
    public function delete(PlanningBlock $block): void;
}
```

---

### Task 9 — Agrégat `PlanningBlock`

**File (new):** `src/Context/Scheduling/Domain/PlanningBlock.php`

Constructeur privé. Factory methods :

```php
public static function create(
    PlanningBlockId $id,
    ClinicId $clinicId,
    UserId $practitionerId,
    PlanningBlockType $type,
    TimeRange $timeRange,
    int $capacityPerHour,
    RecurrenceRule $rule,
    ?string $note,
): self
```
Émet `PlanningBlockCreated`.

```php
public static function reconstitute(...same params...): self
```
Hydratation depuis persistence — aucun événement.

```php
public function update(
    PlanningBlockType $newType,
    TimeRange $newRange,
    int $newCapacity,
    RecurrenceRule $newRule,
    ?string $newNote,
): void
```
Met à jour les champs et émet `PlanningBlockUpdated`. **N'effectue aucune vérification sur les RDV** — les invariants D.1–D.5 sont vérifiés DANS le handler avant l'appel.

```php
public function delete(): void
```
Émet `PlanningBlockDeleted`. **N'effectue aucune vérification** — `CannotDeletePlanningBlockWithAppointments` est levée DANS le handler avant l'appel.

Getters : `id()`, `clinicId()`, `practitionerId()`, `type()`, `timeRange()`, `capacityPerHour()`, `recurrenceRule()`, `note()`.

**File (new):** `tests/Unit/Context/Scheduling/Domain/PlanningBlockTest.php`

Modèle : `AppointmentTest.php`. Helper `createSampleBlock()`. Couvrir :
- `create()` → `PlanningBlockCreated` émis
- `reconstitute()` → aucun événement
- `update()` → `PlanningBlockUpdated` émis, nouvelles valeurs accessibles
- `delete()` → `PlanningBlockDeleted` émis

---

### Task 10 — Ports Application

**Prérequis :** Grep `TimezoneResolver\|resolveTimezone` dans `src/` avant création de `ClinicTimezoneResolverInterface`. Si un port équivalent existe, l'utiliser.

**Files (new) :**

`src/Context/Scheduling/Application/Port/PlanningBlockOverlapCheckerInterface.php`
```php
interface PlanningBlockOverlapCheckerInterface
{
    public function hasOverlap(
        ClinicId $clinicId,
        UserId $practitionerId,
        string $date,
        string $startTime,
        string $endTime,
        ?PlanningBlockId $excludeId = null,
    ): bool;
}
```

`src/Context/Scheduling/Application/Port/PlanningBlockFinderInterface.php`
```php
interface PlanningBlockFinderInterface
{
    public function findActiveBlockFor(
        ClinicId $clinicId,
        UserId $practitionerId,
        string $localDate,
        string $localStartTime,
        string $localEndTime,
    ): ?PlanningBlockReadModel;
}
```

`src/Context/Scheduling/Application/Port/PlanningBlockReadModel.php` (readonly class) :
```php
final readonly class PlanningBlockReadModel
{
    public function __construct(
        public string $id,
        public PlanningBlockType $type,
        public int $capacityPerHour,        // raw capacity/h from DB
        public int $currentAppointmentCount, // snapshot, see D-R1
        public bool $acceptsAppointments,
    ) {}
}
```

`src/Context/Scheduling/Application/Port/ClinicTimezoneResolverInterface.php`
```php
interface ClinicTimezoneResolverInterface
{
    public function resolveTimezone(ClinicId $clinicId): \DateTimeZone;
}
```

`src/Context/Scheduling/Application/Port/PlanningBlockAppointmentCounterInterface.php`
```php
interface PlanningBlockAppointmentCounterInterface
{
    /** Count PLANNED appointments overlapping the UTC window for the given practitioner. */
    public function countActiveInWindow(
        ClinicId $clinicId,
        UserId $practitionerId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
    ): int;
}
```

---

### Task 11 — Command Create PlanningBlock

**Files (new) :**
- `src/Context/Scheduling/Application/Command/CreatePlanningBlock/CreatePlanningBlock.php`
```php
final readonly class CreatePlanningBlock implements CommandInterface
{
    public function __construct(
        public string  $clinicId,
        public string  $practitionerUserId,
        public string  $type,             // PlanningBlockType value
        public string  $date,             // YYYY-MM-DD
        public string  $startTime,        // HH:MM
        public string  $endTime,          // HH:MM
        public int     $capacityPerHour,
        public string  $recurrenceFreq,   // NONE|DAILY|WEEKLY|WEEKDAYS
        public ?string $recurrenceUntil,  // YYYY-MM-DD or null
        public ?string $note,
    ) {}
}
```

- `src/Context/Scheduling/Application/Command/CreatePlanningBlock/CreatePlanningBlockHandler.php`

Injections : `PlanningBlockRepositoryInterface`, `PlanningBlockOverlapCheckerInterface`, `UuidGeneratorInterface`.

Logique :
1. Construire VOs : `PlanningBlockId`, `ClinicId`, `UserId`, `PlanningBlockType::from($command->type)`, `TimeRange`, `RecurrenceRule`
2. `overlapChecker->hasOverlap(...)` → lève `CannotCreateOverlappingPlanningBlock`
3. `PlanningBlock::create(...)` → `repository->save($block)`
4. Retourner `$block->id()->toString()`

**File (new):** `tests/Unit/Context/Scheduling/Application/Command/CreatePlanningBlock/CreatePlanningBlockHandlerTest.php`

Couvrir : happy path, overlap → exception, TimeRange invalide → exception propagée (save non appelé).

---

### Task 12 — Command Update PlanningBlock

**Files (new) :**
- `src/Context/Scheduling/Application/Command/UpdatePlanningBlock/UpdatePlanningBlock.php`

Mêmes champs que `CreatePlanningBlock` + `blockId: string`.

- `src/Context/Scheduling/Application/Command/UpdatePlanningBlock/UpdatePlanningBlockHandler.php`

Injections : `PlanningBlockRepositoryInterface`, `PlanningBlockOverlapCheckerInterface`, `PlanningBlockAppointmentCounterInterface`, `ClinicTimezoneResolverInterface`.

Logique :
1. `repository->findById(...)` → lève `PlanningBlockNotFoundException` si null
2. Construire nouveau `TimeRange`, `PlanningBlockType`, `RecurrenceRule`
3. `overlapChecker->hasOverlap(..., excludeId: $block->id())`
4. Résoudre timezone : `$tz = $timezoneResolver->resolveTimezone($clinicId)`
5. Convertir la NOUVELLE fenêtre en UTC via `TimeRange::toUtcStart/End($tz)`
6. `$count = $counter->countActiveInWindow($clinicId, $practitionerId, $startUtc, $endUtc)`
7. Vérifier invariants D.1–D.5 :
   - D.1 : si `$newType !== $block->type() && $count > 0` → `CannotChangePlanningBlockTypeWithActiveAppointments`
   - D.2/D.5 : si fenêtre réduite ou praticien changé avec `$count > 0` → `CannotShrinkPlanningBlockBelowExistingAppointments`
   - D.3 : si `$newCapacity * $slotDurationH < $count` → `CannotReduceCapacityBelowExistingAppointmentCount`
   - D.4 : si `$newRule->freq() !== $block->recurrenceRule()->freq()` → `CannotModifyRecurrenceRuleOnExistingBlock`
8. `$block->update($newType, $newRange, $newCapacity, $newRule, $newNote)`
9. `repository->save($block)`

**File (new):** `tests/Unit/Context/Scheduling/Application/Command/UpdatePlanningBlock/UpdatePlanningBlockHandlerTest.php`

Couvrir : happy path, bloc introuvable, overlap hors lui-même, chaque refus D.1–D.5.

---

### Task 13 — Command Delete PlanningBlock

**Files (new) :**
- `src/Context/Scheduling/Application/Command/DeletePlanningBlock/DeletePlanningBlock.php`

Champs : `blockId: string`, `clinicId: string`.

- `src/Context/Scheduling/Application/Command/DeletePlanningBlock/DeletePlanningBlockHandler.php`

Injections : `PlanningBlockRepositoryInterface`, `PlanningBlockAppointmentCounterInterface`, `ClinicTimezoneResolverInterface`.

Logique :
1. `repository->findById(...)` → lève `PlanningBlockNotFoundException`
2. Résoudre timezone, convertir `timeRange` → UTC
3. `$counter->countActiveInWindow(...) > 0` → lève `CannotDeletePlanningBlockWithAppointments`
4. `$block->delete()` → `repository->delete($block)`

**File (new):** `tests/Unit/Context/Scheduling/Application/Command/DeletePlanningBlock/DeletePlanningBlockHandlerTest.php`

Couvrir : happy path, bloc introuvable, bloc avec RDV → exception.

---

### Task 14 — Query `ListPlanningBlocksForClinicDateRange`

**Files (new) :**
- `src/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/ListPlanningBlocksForClinicDateRange.php`

Champs : `clinicId: string`, `fromDate: string` (YYYY-MM-DD), `toDate: string` (YYYY-MM-DD).
**Interprétation des dates :** `fromDate` et `toDate` sont en **local time de la clinique** (wall time), cohérent avec le stockage `date` des blocs. Aucune conversion UTC nécessaire dans ce handler — la comparaison est directement en YYYY-MM-DD string.

SQL de chargement optimisé (filtre baseDate + until) :
```sql
SELECT *, BIN_TO_UUID(id) as id_str,
         BIN_TO_UUID(practitioner_user_id) as pract_str
FROM scheduling__planning_blocks
WHERE clinic_id = :clinicId
  AND date <= :toDate
  AND (
      JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL
      OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :fromDate
  )
```
Évite de charger les blocs expirés (until < fromDate) et les blocs futurs (date > toDate).

UUID : utiliser `$row['id_str']` et `$row['pract_str']` (déjà RFC4122 lowercase via `LOWER(BIN_TO_UUID(...))`). Pattern cohérent avec `GetAgendaForClinicDateRangeHandler` existant.

- `src/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/PlanningBlockView.php`

```php
final readonly class PlanningBlockView
{
    public function __construct(
        public string  $id,
        public string  $clinicId,
        public string  $practitionerUserId,
        public string  $type,
        public string  $date,            // YYYY-MM-DD (occurrence date, not base date)
        public string  $startTime,       // HH:MM
        public string  $endTime,         // HH:MM
        public int     $capacityPerHour,
        public string  $recurrenceFreq,
        public ?string $recurrenceUntil,
        public ?string $note,
        public bool    $acceptsAppointments,
        public bool    $hasCapacityLimit,
    ) {}
}
```

- `src/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/ListPlanningBlocksForClinicDateRangeHandler.php`

Injections : `Connection` (DBAL), `RecurrenceExpander`.

Logique :
1. DBAL : `SELECT * FROM scheduling__planning_blocks WHERE clinic_id = :clinicId`
2. Pour chaque ligne, `RecurrenceRule::fromJson($row['recurrence_rule'])`
3. `$occurrences = $expander->expand($rule, $row['date'], \DateTimeImmutable::createFromFormat('Y-m-d', $query->fromDate), \DateTimeImmutable::createFromFormat('Y-m-d', $query->toDate))`
4. Pour chaque occurrence, créer `PlanningBlockView` avec la **date d'occurrence** (pas la date de base)
5. Retourner `list<PlanningBlockView>` triée par `date ASC, start_time ASC, practitioner_user_id ASC`

**File (new):** `tests/Unit/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/ListPlanningBlocksForClinicDateRangeHandlerTest.php`

Couvrir : aucun bloc, NONE dans range, NONE hors range, WEEKLY produit N occurrences, tri correct.

---

### Task 15 — Infrastructure : Doctrine Entity + Mapper + Repository

**File (new):** `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/PlanningBlockEntity.php`

```
Table : scheduling__planning_blocks
Colonnes :
  id                   BINARY(16) PK  (UuidType)
  clinic_id            BINARY(16) not null
  practitioner_user_id BINARY(16) not null
  type                 VARCHAR(50) not null  [enumType: PlanningBlockType::class]
  date                 VARCHAR(10) not null  (YYYY-MM-DD)
  start_time           VARCHAR(5)  not null  (HH:MM)
  end_time             VARCHAR(5)  not null  (HH:MM)
  capacity_per_hour    INT not null
  recurrence_rule      JSON not null         (default {"freq":"NONE","until":null})
  note                 VARCHAR(500) nullable
  created_at_utc       DATETIME_IMMUTABLE not null
  updated_at_utc       DATETIME_IMMUTABLE not null

Index :
  idx_pb_clinic_date    (clinic_id, date)
  idx_pb_clinic_pract   (clinic_id, practitioner_user_id, date)
```

**File (new):** `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Mapper/PlanningBlockMapper.php`

`toDomain(PlanningBlockEntity): PlanningBlock` via `PlanningBlock::reconstitute()`
`toEntity(PlanningBlock): PlanningBlockEntity`
`updateEntity(PlanningBlock, PlanningBlockEntity): void`

**File (new):** `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Repository/DoctrinePlanningBlockRepository.php`

Pattern identique à `DoctrineAppointmentRepository`.

**Migration :**
```bash
make console CMD="doctrine:migrations:diff"
```
Nommer : `CreatePlanningBlocksTable`.

---

### Task 16 — Infrastructure : Adapters DBAL

**File (new):** `src/Context/Scheduling/Infrastructure/Adapter/DbalPlanningBlockOverlapChecker.php`

Implémente `PlanningBlockOverlapCheckerInterface`.
```sql
SELECT COUNT(*) as cnt
FROM scheduling__planning_blocks
WHERE clinic_id = :clinicId
  AND practitioner_user_id = :practitionerUserId
  AND date = :date
  AND start_time < :endTime
  AND end_time > :startTime
  [AND id != :excludeId]
```
Tests overlap obligatoires (unit avec mock ou integration) :
- Même créneau exact → overlap
- Overlap début (nouveau commence avant fin existant) → overlap
- Overlap fin (nouveau finit après début existant) → overlap
- Containment (nouveau dans existant) → overlap
- Adjacent (nouveau.start = existant.end) → **pas** overlap

**File (new):** `src/Context/Scheduling/Infrastructure/Adapter/DbalPlanningBlockFinder.php`

Implémente `PlanningBlockFinderInterface`.
Injections : `Connection`, `RecurrenceExpander`, `PlanningBlockAppointmentCounterInterface`, `ClinicTimezoneResolverInterface`.

Algorithme :
```php
// 1. Load candidate blocks (base date <= localDate AND until not expired)
$sql = 'SELECT * FROM scheduling__planning_blocks
        WHERE clinic_id = :clinicId
          AND practitioner_user_id = :practitionerUserId
          AND date <= :localDate
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, \'$.until\')) IS NULL
            OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, \'$.until\')) >= :localDate
          )';

// 2. For each candidate, check if localDate is an occurrence
foreach ($rows as $row) {
    $rule = RecurrenceRule::fromJson($row['recurrence_rule']);
    $occurrences = $this->expander->expand(
        $rule, $row['date'],
        new \DateTimeImmutable($localDate),
        new \DateTimeImmutable($localDate),
    );
    if ([] === $occurrences) continue;

    // 3. Check time containment: block covers [localStartTime, localEndTime]
    if ($row['start_time'] <= $localStartTime && $row['end_time'] >= $localEndTime) {
        // 4. Count active appointments in this window
        $clinicTz = $this->timezoneResolver->resolveTimezone($clinicId);
        $startUtc = (new \DateTimeImmutable($localDate . ' ' . $row['start_time'], $clinicTz))
            ->setTimezone(new \DateTimeZone('UTC'));
        $endUtc   = (new \DateTimeImmutable($localDate . ' ' . $row['end_time'],   $clinicTz))
            ->setTimezone(new \DateTimeZone('UTC'));
        $count    = $this->counter->countActiveInWindow($clinicId, $practitionerId, $startUtc, $endUtc);

        return new PlanningBlockReadModel(
            id: Uuid::fromBinary($row['id'])->toRfc4122(),
            type: PlanningBlockType::from($row['type']),
            capacityPerHour: (int) $row['capacity_per_hour'],
            currentAppointmentCount: $count,
            acceptsAppointments: PlanningBlockType::from($row['type'])->acceptsAppointments(),
        );
    }
}
return null;
```
UUID binaire : utiliser `Uuid::fromBinary($row['id'])->toRfc4122()` (import `Symfony\Component\Uid\Uuid`). Pattern cohérent avec la codebase existante.

**File (new):** `src/Context/Scheduling/Infrastructure/Adapter/DbalClinicTimezoneResolver.php`

```sql
SELECT time_zone FROM clinic__clinics WHERE id = :clinicId
```
Lance `\RuntimeException('Clinic not found')` si absent.

**File (new):** `src/Context/Scheduling/Infrastructure/Adapter/DbalPlanningBlockAppointmentCounter.php`

Implémente `PlanningBlockAppointmentCounterInterface`.
```sql
SELECT COUNT(*) as cnt
FROM scheduling__appointments
WHERE clinic_id = :clinicId
  AND practitioner_user_id = :practitionerUserId
  AND status = 'PLANNED'
  AND starts_at_utc < :windowEndUtc
  AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :windowStartUtc
```
Paramètres bindés :
- `clinicId` → `Uuid::fromString($clinicId->toString())->toBinary()`
- `practitionerUserId` → `Uuid::fromString($practitionerId->toString())->toBinary()`
- `windowStartUtc` → `$windowStartUtc->format('Y-m-d H:i:s')`
- `windowEndUtc`   → `$windowEndUtc->format('Y-m-d H:i:s')`

---

### Task 17 — Intégration dans `ScheduleAppointmentHandler`

**File:** `src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php`

Nouvelles injections :
- `PlanningBlockFinderInterface $planningBlockFinder`
- `ClinicTimezoneResolverInterface $timezoneResolver`

Après la vérification de conflit existante :
```php
$clinicTz    = $this->timezoneResolver->resolveTimezone($clinicId);
$startsLocal = $timeSlot->startsAtUtc()->setTimezone($clinicTz);
$endsLocal   = $timeSlot->endsAtUtc()->setTimezone($clinicTz);

$block = $this->planningBlockFinder->findActiveBlockFor(
    $clinicId,
    $practitionerUserId,
    $startsLocal->format('Y-m-d'),
    $startsLocal->format('H:i'),
    $endsLocal->format('H:i'),
);

if (null !== $block) {
    if (!$block->acceptsAppointments) {
        throw new \DomainException(
            'Cannot schedule appointment in a ' . $block->type->value . ' block.'
        );
    }
    $slotDurationHours = $timeSlot->durationMinutes() / 60;
    $capacityForSlot   = (int) floor($block->capacityPerHour * $slotDurationHours);
    if ($capacityForSlot > 0 && $block->currentAppointmentCount >= $capacityForSlot) {
        throw new \DomainException('Planning block capacity reached.');
    }
}
// null = aucun bloc configuré (SOFT constraint) — RDV autorisé
```

**File:** `tests/Unit/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandlerTest.php`

Ajouter : bloc `acceptsAppointments=false` → exception, bloc capacité pleine → exception, bloc capacité disponible → succès, aucun bloc (null) → succès.

---

### Task 18 — Enrichir `AppointmentItem.isOrphaned`

**File:** `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/AppointmentItem.php`

Ajouter `public readonly bool $isOrphaned` dans le constructeur (après les champs existants, valeur par défaut `false`).

Ajouter une méthode immutable :
```php
public function withIsOrphaned(bool $isOrphaned): self
{
    return new self(
        $this->id, $this->clinicId, $this->ownerId, $this->animalId,
        $this->practitionerUserId, $this->startsAtUtc, $this->durationMinutes,
        $this->status, $this->reason, $this->notes,
        $this->ownerLabel, $this->ownerPhone, $this->animalLabel,
        $this->animalSpecies, $this->practitionerLabel,
        $isOrphaned,
    );
}
```
Adapter la liste des paramètres aux champs réels de `AppointmentItem`.

**File:** `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandler.php`

**Pas de LEFT JOIN SQL** — aucune logique de récurrence en SQL.

Injections complètes du handler (modifier le constructeur) :
```php
public function __construct(
    private readonly Connection $connection,
    private readonly ClinicTimezoneResolverInterface $timezoneResolver,
    private readonly RecurrenceExpander $expander,
) {}
```

Logique **après** la requête SQL principale qui hydrate `$appointments` :

```php
// 1. Résoudre timezone
$clinicTz = $this->timezoneResolver->resolveTimezone(ClinicId::fromString($query->clinicId));

// 2. Charger blocs candidats pour la fenêtre (filtre sur baseDate + until)
$blockRows = $this->connection->fetchAllAssociative(
    'SELECT *, LOWER(BIN_TO_UUID(id)) as id_str,
             LOWER(BIN_TO_UUID(practitioner_user_id)) as pract_str
     FROM scheduling__planning_blocks
     WHERE clinic_id = :clinicId
       AND date <= :toDate
       AND (
           JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, \'$.until\')) IS NULL
           OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, \'$.until\')) >= :fromDate
       )',
    [
        'clinicId' => Uuid::fromString($query->clinicId)->toBinary(),
        'toDate'   => $query->toUtc->setTimezone($clinicTz)->format('Y-m-d'),
        'fromDate' => $query->fromUtc->setTimezone($clinicTz)->format('Y-m-d'),
    ],
);

// 3. Construire un index UTC des fenêtres de blocs actifs :
//    entry = [practitionerUserId, utcStart, utcEnd, PlanningBlockType]
$blockWindows = [];
foreach ($blockRows as $row) {
    $rule        = RecurrenceRule::fromJson($row['recurrence_rule']);
    // setTimezone() ne change pas le moment UTC, juste l'affichage — format('Y-m-d') retourne la date locale
    $rangeStart  = $query->fromUtc->setTimezone($clinicTz);
    $rangeEnd    = $query->toUtc->setTimezone($clinicTz);
    $occurrences = $this->expander->expand($rule, $row['date'], $rangeStart, $rangeEnd);
    $type        = PlanningBlockType::from($row['type']);

    foreach ($occurrences as $occDate) {
        $blockUtcStart = (new \DateTimeImmutable($occDate . ' ' . $row['start_time'], $clinicTz))
            ->setTimezone(new \DateTimeZone('UTC'));
        $blockUtcEnd   = (new \DateTimeImmutable($occDate . ' ' . $row['end_time'],   $clinicTz))
            ->setTimezone(new \DateTimeZone('UTC'));
        $blockWindows[] = [
            'vet'   => $row['pract_str'],   // RFC4122 lowercase
            'start' => $blockUtcStart,
            'end'   => $blockUtcEnd,
            'type'  => $type,
        ];
    }
}

// 4. Calculer isOrphaned en UTC pour chaque appointment (containment strict)
// PHP supporte les opérateurs < <= > >= sur DateTimeImmutable (comparaison chronologique)
$enriched = [];
foreach ($appointments as $item) {
    $apptStart = new \DateTimeImmutable($item->startsAtUtc);
    $apptEnd   = $apptStart->modify('+' . $item->durationMinutes . ' minutes');

    $isOrphaned = true;
    foreach ($blockWindows as $w) {
        /** @var array{vet: string, start: \DateTimeImmutable, end: \DateTimeImmutable, type: PlanningBlockType} $w */
        if ($w['vet'] === $item->practitionerUserId
            && $w['type']->acceptsAppointments()
            && $w['start'] <= $apptStart   // block.utcStart <= appt.utcStart (containment)
            && $w['end']   >= $apptEnd     // block.utcEnd   >= appt.utcEnd
        ) {
            $isOrphaned = false;
            break;
        }
    }
    $enriched[] = $item->withIsOrphaned($isOrphaned);
}
// $enriched remplace $appointments dans le return du handler
```

`isOrphaned` est un snapshot — peut devenir stale si un bloc est modifié sans rechargement. Non bloquant v1.

**Dette D-R2 :** occurrences hebdomadaires qui tombent sur un changement DST (2×/an en France) sont décalées d'1h en UTC. Edge case, solution v2.

---

### Task 19 — Contrôleurs CRUD Presentation

**Files (new) :**
- `src/Presentation/Clinic/Controller/Scheduling/Planning/CreatePlanningBlockController.php`
- `src/Presentation/Clinic/Controller/Scheduling/Planning/UpdatePlanningBlockController.php`
- `src/Presentation/Clinic/Controller/Scheduling/Planning/DeletePlanningBlockController.php`

Routes :
```
POST   /scheduling/planning/blocks        clinic_planning_block_create
PUT    /scheduling/planning/blocks/{id}   clinic_planning_block_update
DELETE /scheduling/planning/blocks/{id}   clinic_planning_block_delete
```

Un `__invoke()` par contrôleur. `AbstractController` + `CommandBusInterface`.

`Create` → `JsonResponse(['id' => $id], 200)`
`Update` → `JsonResponse(null, 204)`
`Delete` → `JsonResponse(null, 204)`

`DomainException` → `JsonResponse(['error' => $e->getMessage()], 422)`
`PlanningBlockNotFoundException` → `JsonResponse(['error' => 'Not found'], 404)`

---

### Task 20 — Wire `PlanningController`

**File:** `src/Presentation/Clinic/Controller/Scheduling/Planning/PlanningController.php`

Ajouter injections : `ClockInterface`, `QueryBusInterface` (si pas déjà présent).

Logique (calquée sur `AgendaController`) :
1. Résoudre `$currentClinicId`, `$clinic` (GetClinic — déjà présent)
2. `$veterinarians` via `ListClinicVeterinarians`
3. `$practitionersByUserId` map
4. `$selectedDate` depuis param `date` ou today (ClockInterface + clinicTz)
5. Dispatcher `ListPlanningBlocksForClinicDateRange` pour la semaine courante
6. Normaliser les blocs pour le JS (voir décision F21) :
```php
$planningBlocksJs = array_map(fn(PlanningBlockView $b) => [
    'id'         => $b->id,
    'vet'        => $b->practitionerUserId,
    'date'       => $b->date,
    'start'      => $b->startTime,
    'end'        => $b->endTime,
    'type'       => $b->type,
    'capacity'   => $b->capacityPerHour,
    'note'       => $b->note ?? '',
    'recurrence' => $b->recurrenceFreq,
], $planningBlocks);
```
7. Générer liens nav (prev/next/today avec param `date`)
8. `render()` avec : `veterinarians`, `practitionersByUserId`, `selectedDate`, `clinicTimezone`, `currentUserId`, `planningBlocksJs`, liens nav

---

### Task 21 — Wire template et `planning.js`

**File:** `templates/clinic/scheduling/planning/index.html.twig`

Aside :
```twig
{{ include('clinic/scheduling/_scheduling_aside.html.twig', {
  page: 'planning',
  selected_date: selectedDate|date('Y-m-d'),
  week_highlight: false,
  veterinarians: veterinarians,
  currentUserId: currentUserId,
}) }}
```

Attributs `data-*` sur `<div class="scheduling-shell">` :
```twig
data-planning-blocks="{{ planningBlocksJs|json_encode }}"
data-vets="{{ practitionersByUserId|json_encode }}"
data-clinic-id="{{ currentClinicId }}"
data-clinic-tz="{{ clinicTimezone }}"
data-today="{{ selectedDate|date('Y-m-d') }}"
data-create-url="{{ path('clinic_planning_block_create') }}"
data-update-url-template="{{ path('clinic_planning_block_update', {id: '__ID__'}) }}"
data-delete-url-template="{{ path('clinic_planning_block_delete', {id: '__ID__'}) }}"
```

Popup vet `<select>` :
```twig
{{ include('components/ui/select.html.twig', {
  name: 'vet',
  id: 'popup-vet',
  options: veterinarians|map(v => {value: v.userId, label: v.displayName ?? v.userId}),
}) }}
```

**File:** `assets/js/pages/scheduling/planning.js`

Remplacer la section `DATA` :
```js
const _shell     = document.querySelector('.scheduling-shell');
const VETS       = JSON.parse(_shell?.dataset.vets ?? '{}');
const CLINIC_ID  = _shell?.dataset.clinicId ?? '';
const CLINIC_TZ  = _shell?.dataset.clinicTz ?? 'UTC';
const CREATE_URL = _shell?.dataset.createUrl ?? '';
const UPDATE_URL_TPL = _shell?.dataset.updateUrlTemplate ?? '';
const DELETE_URL_TPL = _shell?.dataset.deleteUrlTemplate ?? '';

// Keys already match JS internals: {id, vet, date, start, end, type, capacity, note, recurrence}
let blocks   = JSON.parse(_shell?.dataset.planningBlocks ?? '[]');
let nextId   = blocks.length ? Math.max(...blocks.map(b => typeof b.id === 'number' ? b.id : 0)) + 1 : 1;
```

Remplacer `today`/`currentDate` hardcodés :
```js
const _todayStr = _shell?.dataset.today ?? new Date().toISOString().slice(0, 10);
let today       = new Date(_todayStr + 'T00:00:00');
let currentDate = new Date(_todayStr + 'T00:00:00');
```

Remplacer `saveBlock()` et `deleteBlock()` par `fetch` :
```js
async function saveBlock() {
    const payload = { /* champs du popup */ };
    const url     = editingBlockId
        ? UPDATE_URL_TPL.replace('__ID__', editingBlockId)
        : CREATE_URL;
    const method  = editingBlockId ? 'PUT' : 'POST';
    const res     = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if (!res.ok) { showToast('Erreur : ' + (await res.json()).error); return; }
    // Reload page or update local `blocks` array
    location.reload();
}
async function deleteBlock() {
    const res = await fetch(DELETE_URL_TPL.replace('__ID__', editingBlockId), { method: 'DELETE' });
    if (!res.ok) { showToast('Erreur : ' + (await res.json()).error); return; }
    location.reload();
}
```

Note drag-to-select : la logique `dragState` reste dans le fichier sans modification. Préfixer le bloc avec `// drag-to-select: v2 — not wired`.

---

### Task 22 — Fixtures

**Prérequis :**
1. Grep `ClinicDataset\|StaffProfileDataset` dans `src/` et `fixtures/` pour trouver le chemin exact des datasets existants — aligner `PlanningBlockDataset` sur ce pattern.
2. Vérifier que `ClinicDataset::CLINIC_PARIS_ID` et `StaffProfileDataset::VET_ROUSSEAU_USER_ID` / `VET_MARTIN_USER_ID` / `VET_DUPONT_USER_ID` existent.

**File (new) :** `{chemin-trouvé-au-grep}/PlanningBlockDataset.php` — vraisemblablement `fixtures/Dataset/PlanningBlockDataset.php` ou `src/DataFixtures/Dataset/PlanningBlockDataset.php`. Vérifier avant de créer.

```php
final class PlanningBlockDataset
{
    // UUIDs fixes pour reproductibilité des tests
    public const string BLOCK_ROUSSEAU_CONSULT_WEEKLY_ID   = '01000000-0000-0000-0000-000000000001';
    public const string BLOCK_ROUSSEAU_CONGE_MARCH_25_ID   = '01000000-0000-0000-0000-000000000002';
    public const string BLOCK_MARTIN_CONSULT_WEEKDAYS_ID   = '01000000-0000-0000-0000-000000000003';
    public const string BLOCK_DUPONT_CHIRURGIE_MONDAY_ID   = '01000000-0000-0000-0000-000000000004';
    public const string BLOCK_DUPONT_AT_CAPACITY_ID        = '01000000-0000-0000-0000-000000000005';
}
```

**File :** `src/DataFixtures/SchedulingPlanningBlockFixtures.php`

Blocs avec les champs exacts :

| Constante | Praticien | Type | date | start | end | cap | recurrence | until |
|---|---|---|---|---|---|---|---|---|
| `BLOCK_ROUSSEAU_CONSULT_WEEKLY_ID` | VET_ROUSSEAU | CONSULTATION | 2026-04-21 | 08:00 | 12:00 | 3 | WEEKLY | null |
| `BLOCK_ROUSSEAU_CONGE_MARCH_25_ID` | VET_ROUSSEAU | CONGE | 2026-03-25 | 00:00 | 23:59 | 0 | NONE | null |
| `BLOCK_MARTIN_CONSULT_WEEKDAYS_ID` | VET_MARTIN | CONSULTATION | 2026-04-21 | 08:00 | 18:00 | 4 | WEEKDAYS | null |
| `BLOCK_DUPONT_CHIRURGIE_MONDAY_ID` | VET_DUPONT | CHIRURGIE | 2026-04-21 | 13:00 | 17:00 | 1 | WEEKLY | null |
| `BLOCK_DUPONT_AT_CAPACITY_ID` | VET_DUPONT | CONSULTATION | 2026-03-24 | 09:00 | 10:00 | 1 | NONE | null |

`BLOCK_DUPONT_AT_CAPACITY_ID` doit avoir 1 RDV PLANNED existant dans la fenêtre 09:00–10:00 le 2026-03-24 (créé dans les fixtures Appointment existantes ou dans cette fixture en DBAL direct).

---

## Acceptance Criteria

**AC1 — Création d'un bloc**
- Given la clinique `ClinicDataset::CLINIC_PARIS_ID`, When le manager crée un bloc CONSULTATION pour `StaffProfileDataset::VET_ROUSSEAU_USER_ID`, date=2026-05-05, 08:00–12:00, cap=3, recurrence=WEEKLY, Then `POST /scheduling/planning/blocks` retourne 200 avec un UUID, et `ListPlanningBlocksForClinicDateRange(2026-05-05 → 2026-05-12)` retourne 2 occurrences (05/05 et 12/05).
- Given `PlanningBlockDataset::BLOCK_ROUSSEAU_CONSULT_WEEKLY` (lundi 08:00–12:00), When on tente de créer un second bloc pour Rousseau, date=2026-04-21 (lundi), 10:00–14:00, Then `POST` retourne 422 avec `CannotCreateOverlappingPlanningBlock`.

**AC2 — Mise à jour d'un bloc**
- Given `PlanningBlockDataset::BLOCK_DUPONT_CHIRURGIE_MONDAY` (0 RDV), When on change le type en BILAN, Then `PUT` retourne 204.
- Given `PlanningBlockDataset::BLOCK_DUPONT_AT_CAPACITY` (1 RDV PLANNED), When on tente de changer le type en CONGE, Then `PUT` retourne 422 `CannotChangePlanningBlockTypeWithActiveAppointments`.
- Given `PlanningBlockDataset::BLOCK_DUPONT_AT_CAPACITY` (cap=1, 1 RDV), When on réduit capacity à 0, Then `PUT` retourne 422 `CannotReduceCapacityBelowExistingAppointmentCount`.
- Given `PlanningBlockDataset::BLOCK_ROUSSEAU_CONSULT_WEEKLY` (WEEKLY), When on tente de changer recurrence=DAILY, Then `PUT` retourne 422 `CannotModifyRecurrenceRuleOnExistingBlock`.

**AC3 — Suppression d'un bloc**
- Given `PlanningBlockDataset::BLOCK_DUPONT_CHIRURGIE_MONDAY` (0 RDV), When `DELETE`, Then 204 et `ListPlanningBlocksForClinicDateRange` ne retourne plus ce bloc.
- Given `PlanningBlockDataset::BLOCK_DUPONT_AT_CAPACITY` (1 RDV PLANNED), When `DELETE`, Then 422 `CannotDeletePlanningBlockWithAppointments`.

**AC4 — ScheduleAppointment respecte les blocs**
- Given `PlanningBlockDataset::BLOCK_ROUSSEAU_CONGE_MARCH_25` (CONGE 2026-03-25), When `ScheduleAppointment` pour Rousseau le 2026-03-25, Then DomainException contenant "conge".
- Given `PlanningBlockDataset::BLOCK_DUPONT_AT_CAPACITY` (CONSULTATION cap=1, 1h, 1 RDV existant dans la même heure), When `ScheduleAppointment` pour Dupont dans cette heure, Then DomainException "capacity reached".
- Given aucun bloc configuré pour `StaffProfileDataset::VET_MARTIN_USER_ID` le 2026-05-01, When `ScheduleAppointment` pour Martin, Then RDV créé (SOFT constraint).

**AC5 — isOrphaned dans l'agenda**
- Given un RDV PLANNED pour Rousseau à 09:00 le 2026-03-25 (avant que le congé soit créé), When `BLOCK_ROUSSEAU_CONGE_MARCH_25` est créé puis `GetAgendaForClinicDateRange(2026-03-25)` est appelé, Then `AppointmentItem.isOrphaned = true` pour ce RDV.
- Given un RDV PLANNED pour Rousseau à 10:00 le 2026-04-21 dans `BLOCK_ROUSSEAU_CONSULT_WEEKLY`, When `GetAgendaForClinicDateRange(2026-04-21)` est appelé, Then `AppointmentItem.isOrphaned = false`.

**AC6 — Récurrence**
- Given `BLOCK_ROUSSEAU_CONSULT_WEEKLY` (ancré lundi 2026-04-21), When `ListPlanningBlocksForClinicDateRange(2026-04-21 → 2026-05-12)`, Then 4 occurrences retournées exactement aux dates 2026-04-21, 2026-04-28, 2026-05-05, 2026-05-12.
- Given un bloc WEEKDAYS ancré lundi 2026-04-21 avec until=2026-04-25, When `ListPlanningBlocksForClinicDateRange(2026-04-21 → 2026-04-25)`, Then 5 occurrences : lun 21, mar 22, mer 23, jeu 24, ven 25.

**AC7 — UI Planning wirée**
- Given `/scheduling/planning`, When chargée, Then `data-vets` contient les praticiens de `CLINIC_PARIS_ID` (pas les mocks hardcodés).
- Given un bloc créé via le popup, When la page est rechargée, Then le bloc apparaît dans `data-planning-blocks` avec les clés `{id, vet, date, start, end, type, capacity, note, recurrence}`.
- `make ci` green (cs-fixer + phpcs + phpstan level:max + tests Scheduling BC à 100%).

---

## Additional Context

### Dependencies

- `UuidGeneratorInterface` (Shared BC)
- `ClockInterface` (Shared BC)
- `CommandBusInterface` / `QueryBusInterface` (Shared BC)
- Pas de librairie tierce pour `RecurrenceExpander`

### Testing Strategy

- **Unit tests** : VOs (TimeRange, RecurrenceRule), agrégat, `RecurrenceExpander`, enum snapshot, handlers (mocks)
- **Overlap tests** : 5 scénarios par adapter (même créneau, overlap début, overlap fin, containment, adjacent)
- **Pas de tests d'intégration pour les adapters DBAL** (cohérent avec la stratégie projet)
- **Test manuel** : créer un bloc CONGE, tenter un RDV → doit échouer ; créer un bloc WEEKLY, recharger → occurrences visibles

### Notes

- **D-R1 (Dette race condition)** : snapshot `currentAppointmentCount` non-verrouillé. Surbooking 1-2 RDV max possible. Solution v2 : advisory lock MySQL.
- **D-R2 (Dette DST)** : occurrences hebdomadaires tombant sur un changement DST (2×/an en France) sont décalées d'1h en UTC. Edge case. Solution v2 : stocker les blocs en UTC absolu ou appliquer une correction DST à l'expansion.
- **`InvalidClinicTimezoneStored`** : nouvelle exception `Domain/Exception/InvalidClinicTimezoneStored extends \RuntimeException` lancée par `DbalClinicTimezoneResolver` si la timezone en DB est invalide. À ajouter à la liste des exceptions Task 6.
- **`ClinicTimezoneResolverInterface`** : grep `TimezoneResolver\|resolveTimezone` avant création (prérequis Task 10).
- **drag-to-select v2** : logique `dragState` dans `planning.js` préservée sans modification, commentée `// drag-to-select: v2`.
- **`isOrphaned` stale** : valeur snapshot (cohérente au moment de la query), peut devenir obsolète si un bloc est modifié sans rechargement. Non bloquant v1.
- **Exceptions** : `InvalidPlanningBlockTimeRange` et `UnsupportedRecurrencePattern` sont utilisées dans `TimeRange` et `RecurrenceRule` — créer `src/Context/Scheduling/Domain/Exception/` avant `Task 3` et `Task 4`.
