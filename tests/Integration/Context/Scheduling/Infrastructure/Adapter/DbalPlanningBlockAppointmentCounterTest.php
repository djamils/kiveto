<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The block capacity counts what occupies a slot: planned bookings and
 * pending online requests, never a cancelled or declined one.
 */
final class DbalPlanningBlockAppointmentCounterTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000c0c01';
    private const string USER_ID   = '01960000-0000-7000-8000-0000000c0d01';

    public function testCountsPlannedAndPendingOnly(): void
    {
        $this->booking('09:00')->withStatus(AppointmentStatus::PLANNED)->create();
        $this->booking('09:30')->pending(new \DateTimeImmutable('2026-04-10 07:00:00'))->create();
        $this->booking('10:00')->declined()->create();
        $this->booking('10:30')->cancelledBy(CancellationActor::CLINIC)->create();
        $this->booking('11:00')->withStatus(AppointmentStatus::COMPLETED)->create();

        $counter = self::getContainer()->get(PlanningBlockAppointmentCounterInterface::class);
        \assert($counter instanceof PlanningBlockAppointmentCounterInterface);

        self::assertSame(2, $counter->countActiveInWindow(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 08:00:00'),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
        ));
    }

    public function testTheLockedCountIsTheSameCountAndCanLeaveTheMovedBookingOut(): void
    {
        $this->booking('09:00')->withId('01960000-0000-7000-8000-0000000c0e01')->withStatus(AppointmentStatus::PLANNED)->create();
        $this->booking('09:30')->pending(new \DateTimeImmutable('2026-04-10 07:00:00'))->create();
        $this->booking('10:00')->declined()->create();

        $counter = self::getContainer()->get(PlanningBlockAppointmentCounterInterface::class);
        \assert($counter instanceof PlanningBlockAppointmentCounterInterface);

        $clinic = ClinicId::fromString(self::CLINIC_ID);
        $user   = UserId::fromString(self::USER_ID);
        $from   = new \DateTimeImmutable('2026-04-10 08:00:00');
        $to     = new \DateTimeImmutable('2026-04-10 12:00:00');

        self::assertSame(2, $counter->countOccupyingInWindowLocked($clinic, $user, $from, $to));
        self::assertSame(1, $counter->countOccupyingInWindowLocked($clinic, $user, $from, $to, AppointmentId::fromString('01960000-0000-7000-8000-0000000c0e01')));
    }

    private function booking(string $time): AppointmentEntityFactory
    {
        return AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-10 ' . $time . ':00'), 30)
        ;
    }
}
