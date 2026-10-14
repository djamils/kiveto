<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalPlanningBlockFinderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcd0f1';
    private const string USER_ID   = '01234567-89ab-cdef-0123-456789abc0f1';

    private PlanningBlockFinderInterface $finder;
    private PlanningBlockRepositoryInterface $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        $finder = self::getContainer()->get(PlanningBlockFinderInterface::class);
        \assert($finder instanceof PlanningBlockFinderInterface);
        $this->finder = $finder;

        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);
        $this->blocks = $blocks;

        ClinicEntityFactory::new()->withId(self::CLINIC_ID)->create(['timeZone' => 'Europe/Paris']);
    }

    public function testFindsTheWeeklyBlockCoveringTheSlotWithItsLengthAndBookings(): void
    {
        $this->block('01960000-0000-7000-8000-0000000b0001', PlanningBlockType::CONSULTATION, '2026-04-06', '08:00', '12:00', RecurrenceRule::weekly());

        // 2026-04-13 09:00 Paris = 07:00 UTC, inside the block of that Monday
        AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('2026-04-13 07:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        $block = $this->finder->findActiveBlockFor(ClinicId::fromString(self::CLINIC_ID), UserId::fromString(self::USER_ID), '2026-04-13', '10:00', '10:30');

        self::assertNotNull($block);
        self::assertSame(PlanningBlockType::CONSULTATION, $block->type);
        self::assertTrue($block->acceptsAppointments);
        self::assertSame(240, $block->durationMinutes);
        self::assertSame(1, $block->currentAppointmentCount);
        self::assertSame(4, $block->capacityPerHour);
    }

    public function testIgnoresBlocksThatDoNotCoverTheSlotOrTheDay(): void
    {
        $this->block('01960000-0000-7000-8000-0000000b0002', PlanningBlockType::LEAVE, '2026-04-06', '08:00', '12:00', RecurrenceRule::none());

        $clinic = ClinicId::fromString(self::CLINIC_ID);
        $user   = UserId::fromString(self::USER_ID);

        self::assertNull($this->finder->findActiveBlockFor($clinic, $user, '2026-04-13', '09:00', '09:30'));
        self::assertNull($this->finder->findActiveBlockFor($clinic, $user, '2026-04-06', '11:45', '12:15'));
        self::assertNotNull($this->finder->findActiveBlockFor($clinic, $user, '2026-04-06', '08:00', '08:30'));
    }

    private function block(string $id, PlanningBlockType $type, string $date, string $start, string $end, RecurrenceRule $rule): void
    {
        $this->blocks->save(PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            staffUserId: UserId::fromString(self::USER_ID),
            type: $type,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: PlanningBlockType::LEAVE === $type ? 0 : 4,
            rule: $rule,
            note: null,
        ));
    }
}
