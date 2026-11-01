<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * Everything online availability reads, in memory: the clinic's hours, the
 * eligible practitioners, the block occurrences (already expanded) and the
 * occupying appointments. Every read is recorded.
 */
final class FakeAvailabilitySources implements ClinicOpeningHoursInterface, MembershipEligibilityCheckerInterface, PlanningBlockOccurrencesReaderInterface, OccupyingAppointmentsReaderInterface
{
    public ?ClinicOpeningHours $clinic = null;

    /** @var list<string> */
    public array $eligible = [];

    /** @var list<PlanningBlockOccurrence> */
    public array $occurrences = [];

    /** @var list<BusyInterval> */
    public array $appointments = [];

    /** @var list<array{string, string, bool}> */
    public array $blockReads = [];

    /** @var list<array{string, string, ?string, bool}> */
    public array $appointmentReads = [];

    public function of(ClinicId $clinicId): ?ClinicOpeningHours
    {
        return $this->clinic;
    }

    public function isUserEligibleForClinicAt(UserId $userId, ClinicId $clinicId, \DateTimeImmutable $at, array $allowedRoles): bool
    {
        return \in_array($userId->toString(), $this->eligible, true);
    }

    public function listEligiblePractitionerUsersForClinic(ClinicId $clinicId, \DateTimeImmutable $at, array $allowedRoles): array
    {
        return array_map(static fn (string $id): array => ['userId' => $id, 'displayName' => null], $this->eligible);
    }

    public function occurrencesBetween(ClinicId $clinicId, UserId $practitionerUserId, string $fromDate, string $toDate, bool $currentRead = false): array
    {
        $this->blockReads[] = [$fromDate, $toDate, $currentRead];

        return array_values(array_filter(
            $this->occurrences,
            static fn (PlanningBlockOccurrence $occurrence): bool => $occurrence->date >= $fromDate && $occurrence->date <= $toDate,
        ));
    }

    public function occupyingBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        \DateTimeImmutable $fromUtc,
        \DateTimeImmutable $toUtc,
        ?AppointmentId $excludeAppointmentId = null,
        bool $currentRead = false,
    ): array {
        $this->appointmentReads[] = [$fromUtc->format('c'), $toUtc->format('c'), $excludeAppointmentId?->toString(), $currentRead];

        return array_values(array_filter(
            $this->appointments,
            static fn (BusyInterval $busy): bool => $busy->overlaps($fromUtc, $toUtc),
        ));
    }
}
