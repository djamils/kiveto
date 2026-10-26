<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * Milo's appointments at Clinique Montmartre.
 */
final class Appointments
{
    public const string ID       = '01960000-0000-7000-8000-0000000a0001';
    public const string CLINIC   = '01960000-0000-7000-8000-0000000c0001';
    public const string ROUSSEAU = '01960000-0000-7000-8000-0000000d0001';
    public const string LAMBERT  = '01960000-0000-7000-8000-0000000d0002';
    public const string OWNER    = '01960000-0000-7000-8000-0000000e0001';
    public const string ANIMAL   = '01960000-0000-7000-8000-0000000f0001';

    /**
     * @param string $startsAtUtc e.g. '2026-10-22 07:30' (09:30 in Paris)
     */
    public static function planned(
        string $startsAtUtc = '2026-10-22 07:30',
        int $duration = 30,
        string $practitioner = self::ROUSSEAU,
        ?string $ownerId = self::OWNER,
        ?string $animalId = self::ANIMAL,
        ?string $reason = 'Vaccination',
        string $id = self::ID,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
    ): Appointment {
        return Appointment::reconstitute(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString($practitioner)),
            timeSlot: new TimeSlot(new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')), $duration),
            status: $status,
            reason: $reason,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-13 08:42', new \DateTimeZone('UTC')),
            serviceStartedAt: null,
            ownerId: $ownerId,
            animalId: $animalId,
        );
    }

    public static function slot(string $startsAtUtc, int $duration = 30): TimeSlot
    {
        return new TimeSlot(new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')), $duration);
    }

    public static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
