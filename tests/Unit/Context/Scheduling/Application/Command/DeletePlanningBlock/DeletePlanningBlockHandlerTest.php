<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\DeletePlanningBlock;

use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Domain\Exception\CannotDeletePlanningBlockWithAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeletePlanningBlockHandlerTest extends TestCase
{
    private PlanningBlockRepositoryInterface&MockObject $repository;
    private PlanningBlockAppointmentCounterInterface&MockObject $counter;
    private ClinicTimezoneResolverInterface&MockObject $timezoneResolver;
    private DeletePlanningBlockHandler $handler;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->counter          = $this->createMock(PlanningBlockAppointmentCounterInterface::class);
        $this->timezoneResolver = $this->createMock(ClinicTimezoneResolverInterface::class);

        $this->handler = new DeletePlanningBlockHandler(
            repository: $this->repository,
            appointmentCounter: $this->counter,
            timezoneResolver: $this->timezoneResolver,
        );
    }

    public function testHappyPath(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn($this->sampleBlock());
        $this->timezoneResolver->expects(self::once())->method('resolveTimezone')
            ->willReturn(new \DateTimeZone('Europe/Paris'))
        ;
        $this->counter->expects(self::once())->method('countActiveInWindow')->willReturn(0);
        $this->repository->expects(self::once())->method('delete');

        ($this->handler)(new DeletePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockNotFoundThrows(): void
    {
        $this->expectException(PlanningBlockNotFoundException::class);
        $this->repository->method('findById')->willReturn(null);

        ($this->handler)(new DeletePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockWithAppointmentsThrows(): void
    {
        $this->expectException(CannotDeletePlanningBlockWithAppointments::class);
        $this->repository->method('findById')->willReturn($this->sampleBlock());
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->counter->method('countActiveInWindow')->willReturn(2);

        ($this->handler)(new DeletePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        ));
    }

    private function sampleBlock(): PlanningBlock
    {
        return PlanningBlock::reconstitute(
            id: PlanningBlockId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            practitionerId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange('2026-04-21', '08:00', '12:00'),
            capacityPerHour: 3,
            rule: RecurrenceRule::none(),
            note: null,
        );
    }
}
