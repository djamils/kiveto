<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalPlanningBlockOccurrencesReaderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '12345678-9abc-def0-1234-56789abcd1f1';
    private const string OTHER_CLINIC_ID = '12345678-9abc-def0-1234-56789abcd1f2';
    private const string VET             = '01234567-89ab-cdef-0123-456789abc1f1';
    private const string OTHER_VET       = '01234567-89ab-cdef-0123-456789abc1f2';

    private PlanningBlockOccurrencesReaderInterface $reader;
    private PlanningBlockRepositoryInterface $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        $reader = self::getContainer()->get(PlanningBlockOccurrencesReaderInterface::class);
        \assert($reader instanceof PlanningBlockOccurrencesReaderInterface);
        $this->reader = $reader;

        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);
        $this->blocks = $blocks;

        ClinicEntityFactory::new()->withId(self::CLINIC_ID)->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER_CLINIC_ID)->create(['timeZone' => 'Europe/Paris']);
    }

    public function testExpandsEveryBlockTypeOfThePractitionerOverTheRange(): void
    {
        // Weekly consultation on Mondays since 6 April, a one-off leave on Monday 13 (AC 150)
        $this->block('01960000-0000-7000-8000-0000000b1001', PlanningBlockType::CONSULTATION, '2026-04-06', '09:00', '12:00', RecurrenceRule::weekly());
        $this->block('01960000-0000-7000-8000-0000000b1002', PlanningBlockType::LEAVE, '2026-04-13', '10:00', '11:00', RecurrenceRule::none());
        // Weekdays break that ended before the range, a daily admin block starting in it
        $this->block('01960000-0000-7000-8000-0000000b1003', PlanningBlockType::BREAK, '2026-03-02', '12:00', '12:30', RecurrenceRule::weekdays('2026-04-10'));
        $this->block('01960000-0000-7000-8000-0000000b1004', PlanningBlockType::ADMIN, '2026-04-14', '17:00', '18:00', RecurrenceRule::daily('2026-04-15'));
        // After the range; another practitioner; another clinic
        $this->block('01960000-0000-7000-8000-0000000b1005', PlanningBlockType::SURGERY, '2026-04-20', '08:00', '12:00', RecurrenceRule::none());
        $this->block('01960000-0000-7000-8000-0000000b1006', PlanningBlockType::CONSULTATION, '2026-04-13', '08:00', '12:00', RecurrenceRule::none(), vet: self::OTHER_VET);
        $this->block('01960000-0000-7000-8000-0000000b1007', PlanningBlockType::CONSULTATION, '2026-04-13', '08:00', '12:00', RecurrenceRule::none(), clinic: self::OTHER_CLINIC_ID);

        $occurrences = $this->reader->occurrencesBetween(ClinicId::fromString(self::CLINIC_ID), UserId::fromString(self::VET), '2026-04-13', '2026-04-19');

        self::assertSame(
            [
                ['2026-04-13', '09:00', '12:00', 'consultation', 4],
                ['2026-04-13', '10:00', '11:00', 'leave', 0],
                ['2026-04-14', '17:00', '18:00', 'admin', 0],
                ['2026-04-15', '17:00', '18:00', 'admin', 0],
            ],
            self::rows($occurrences),
        );
    }

    public function testACurrentReadReturnsTheSameOccurrences(): void
    {
        $this->block('01960000-0000-7000-8000-0000000b1011', PlanningBlockType::HEALTH_CHECK, '2026-03-29', '01:00', '04:00', RecurrenceRule::none());

        $occurrences = $this->reader->occurrencesBetween(ClinicId::fromString(self::CLINIC_ID), UserId::fromString(self::VET), '2026-03-29', '2026-03-29', currentRead: true);

        self::assertSame([['2026-03-29', '01:00', '04:00', 'health_check', 4]], self::rows($occurrences));
    }

    /**
     * @param list<PlanningBlockOccurrence> $occurrences
     *
     * @return list<array{string, string, string, string, int}>
     */
    private static function rows(array $occurrences): array
    {
        return array_map(
            static fn (PlanningBlockOccurrence $o): array => [$o->date, $o->startTime, $o->endTime, $o->type->value, $o->capacityPerHour],
            $occurrences,
        );
    }

    private function block(
        string $id,
        PlanningBlockType $type,
        string $date,
        string $start,
        string $end,
        RecurrenceRule $rule,
        string $vet = self::VET,
        string $clinic = self::CLINIC_ID,
    ): void {
        $this->blocks->save(PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString($clinic),
            staffUserId: UserId::fromString($vet),
            type: $type,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: $type->acceptsAppointments() ? 4 : 0,
            rule: $rule,
            note: null,
        ));
    }
}
