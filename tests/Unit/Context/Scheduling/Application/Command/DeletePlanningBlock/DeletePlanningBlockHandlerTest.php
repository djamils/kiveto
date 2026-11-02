<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\DeletePlanningBlock;

use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
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
use App\Tests\Unit\Context\Scheduling\Support\FakeOccupyingAppointments;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeletePlanningBlockHandlerTest extends TestCase
{
    private const string STAFF = '33333333-3333-3333-3333-333333333333';

    private PlanningBlockRepositoryInterface&MockObject $repository;
    private FakeOccupyingAppointments $appointments;
    private ClinicTimezoneResolverInterface&MockObject $timezoneResolver;
    private DeletePlanningBlockHandler $handler;
    private RecordingDayLock $dayLock;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->appointments     = new FakeOccupyingAppointments();
        $this->timezoneResolver = $this->createMock(ClinicTimezoneResolverInterface::class);

        $this->handler = new DeletePlanningBlockHandler(
            repository: $this->repository,
            appointmentsReader: $this->appointments,
            timezoneResolver: $this->timezoneResolver,
            dayLock: $this->dayLock = new RecordingDayLock(),
            clock: new MovableClock(new \DateTimeImmutable('2026-04-01 08:00', new \DateTimeZone('UTC'))),
        );
    }

    public function testHappyPath(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn($this->sampleBlock());
        $this->timezoneResolver->expects(self::once())->method('resolveTimezone')
            ->willReturn(new \DateTimeZone('Europe/Paris'))
        ;
        $this->repository->expects(self::once())->method('delete');

        ($this->handler)(new DeletePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        ));

        self::assertSame(['33333333-3333-3333-3333-333333333333|2026-04-21'], $this->dayLock->locked);
        // The one-off block's own window (08:00–12:00 Paris), read under the lock
        self::assertSame(['33333333-3333-3333-3333-333333333333|2026-04-21 06:00|2026-04-21 10:00|current'], $this->appointments->reads);
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
    public function testBlockOfAnotherClinicIsReportedAsNotFound(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn($this->sampleBlock());
        $this->repository->expects(self::never())->method('delete');

        $this->expectException(PlanningBlockNotFoundException::class);

        try {
            ($this->handler)(new DeletePlanningBlock(
                blockId: '11111111-1111-1111-1111-111111111111',
                clinicId: '99999999-9999-9999-9999-999999999999',
            ));
        } finally {
            self::assertSame([], $this->appointments->reads);
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockWithAppointmentsThrows(): void
    {
        $this->expectException(CannotDeletePlanningBlockWithAppointments::class);
        $this->repository->method('findById')->willReturn($this->sampleBlock());
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->appointments->add(self::STAFF, '2026-04-21 07:00');

        ($this->handler)(new DeletePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        ));
    }

    /**
     * A weekly block anchored on Tuesday 21 April 2026, 08:00–12:00 Paris;
     * today is 1 April 2026.
     *
     * @return iterable<string, array{?string, string}>
     */
    public static function provideAnAppointmentInAnyFutureOccurrenceOfARecurringBlockRefusesTheDeletionCases(): iterable
    {
        yield 'the fifth occurrence, not the first one' => [null, '2026-05-19 07:00'];
        yield 'beyond the 60 locked days of an open-ended block' => [null, '2026-11-03 09:00'];
        yield 'the last occurrence before until' => ['2026-06-30', '2026-06-30 09:30'];
        yield 'straddling the end of an occurrence' => [null, '2026-05-26 09:45'];
    }

    #[DataProvider('provideAnAppointmentInAnyFutureOccurrenceOfARecurringBlockRefusesTheDeletionCases')]
    #[AllowMockObjectsWithoutExpectations]
    public function testAnAppointmentInAnyFutureOccurrenceOfARecurringBlockRefusesTheDeletion(?string $until, string $startsAtUtc): void
    {
        $this->repository->method('findById')->willReturn($this->sampleBlock(RecurrenceRule::weekly($until)));
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->repository->expects(self::never())->method('delete');
        $this->appointments->add(self::STAFF, $startsAtUtc);

        $this->expectException(CannotDeletePlanningBlockWithAppointments::class);

        ($this->handler)(new DeletePlanningBlock('11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222'));
    }

    /**
     * @return iterable<string, array{RecurrenceRule, string, string}>
     */
    public static function provideAnAppointmentOutsideTheFutureOccurrencesLetsTheBlockGoCases(): iterable
    {
        yield 'a Wednesday of a weekly block' => [RecurrenceRule::weekly(), '2026-04-21', '2026-05-20 07:00'];
        yield 'after the until' => [RecurrenceRule::weekly('2026-06-30'), '2026-04-21', '2026-07-07 07:00'];
        yield 'a past occurrence' => [RecurrenceRule::weekly(), '2026-03-03', '2026-03-24 08:00'];
        yield 'after the window of an occurrence' => [RecurrenceRule::weekdays(), '2026-04-21', '2026-05-19 10:00'];
        yield 'another practitioner' => [RecurrenceRule::weekly(), '2026-04-21', 'other'];
    }

    #[DataProvider('provideAnAppointmentOutsideTheFutureOccurrencesLetsTheBlockGoCases')]
    #[AllowMockObjectsWithoutExpectations]
    public function testAnAppointmentOutsideTheFutureOccurrencesLetsTheBlockGo(RecurrenceRule $rule, string $anchor, string $startsAtUtc): void
    {
        $this->repository->method('findById')->willReturn($this->sampleBlock($rule, $anchor));
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->repository->expects(self::once())->method('delete');

        if ('other' === $startsAtUtc) {
            $this->appointments->add('44444444-4444-4444-4444-444444444444', '2026-04-21 07:00');
        } else {
            $this->appointments->add(self::STAFF, $startsAtUtc);
        }

        ($this->handler)(new DeletePlanningBlock('11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222'));
    }

    private function sampleBlock(?RecurrenceRule $rule = null, string $anchor = '2026-04-21'): PlanningBlock
    {
        return PlanningBlock::reconstitute(
            id: PlanningBlockId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            staffUserId: UserId::fromString(self::STAFF),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange($anchor, '08:00', '12:00'),
            capacityPerHour: 3,
            rule: $rule ?? RecurrenceRule::none(),
            note: null,
        );
    }
}
