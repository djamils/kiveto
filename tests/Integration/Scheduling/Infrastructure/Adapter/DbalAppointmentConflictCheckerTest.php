<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Adapter;

use App\Fixtures\Scheduling\Factory\AppointmentEntityFactory;
use App\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalAppointmentConflictCheckerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string USER_ID   = '01234567-89ab-cdef-0123-456789abcdef';

    private AppointmentConflictCheckerInterface $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $checker = self::getContainer()->get(AppointmentConflictCheckerInterface::class);
        \assert($checker instanceof AppointmentConflictCheckerInterface);
        $this->checker = $checker;
    }

    public function testReturnsTrueWhenSlotOverlapsExistingAppointment(): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        // 09:15 - 09:45 overlaps with the 09:00 - 09:30 existing slot.
        $candidate = new TimeSlot(new \DateTimeImmutable('2026-04-10 09:15:00'), 30);

        self::assertTrue($this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            $candidate,
        ));
    }

    public function testReturnsFalseWhenSlotsAreAdjacent(): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        // 09:30 - 10:00 starts exactly when the previous slot ends.
        $candidate = new TimeSlot(new \DateTimeImmutable('2026-04-10 09:30:00'), 30);

        self::assertFalse($this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            $candidate,
        ));
    }

    public function testIgnoresAppointmentsForOtherPractitioners(): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId('99999999-9999-9999-9999-999999999999')
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        $candidate = new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30);

        self::assertFalse($this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            $candidate,
        ));
    }

    public function testIgnoresCancelledAppointments(): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::CANCELLED)
            ->create()
        ;

        $candidate = new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30);

        self::assertFalse($this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            $candidate,
        ));
    }

    public function testExcludesProvidedAppointmentId(): void
    {
        $existingId = '01234567-89ab-cdef-0123-456789abcdef';

        AppointmentEntityFactory::new()
            ->withId($existingId)
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        // Same slot, but we exclude the existing appointment from the conflict check
        // (typical reschedule scenario).
        $candidate = new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30);

        self::assertFalse($this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            $candidate,
            AppointmentId::fromString($existingId),
        ));
    }
}
