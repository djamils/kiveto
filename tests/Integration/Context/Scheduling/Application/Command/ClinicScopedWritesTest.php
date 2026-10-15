<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Command;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Application\Command\MarkAppointmentNoShow\MarkAppointmentNoShow;
use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlock;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A planning block or an appointment is only writable from the clinic that owns
 * it: another clinic addressing it by id is told it does not exist, and the
 * record is left untouched.
 */
final class ClinicScopedWritesTest extends KernelTestCase
{
    use Factories;

    private const string OWNER_CLINIC = '01960000-0000-7000-8000-00000000c0a1';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c0b2';
    private const string USER_ID      = '01960000-0000-7000-8000-00000000c0c3';
    private const string BLOCK_ID     = '01960000-0000-7000-8000-00000000c0d4';

    private CommandBusInterface $commandBus;
    private PlanningBlockRepositoryInterface $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);
        $this->blocks = $blocks;

        ClinicEntityFactory::new()->withId(self::OWNER_CLINIC)->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER_CLINIC)->create(['timeZone' => 'Europe/Paris']);

        $this->blocks->save(PlanningBlock::create(
            id: PlanningBlockId::fromString(self::BLOCK_ID),
            clinicId: ClinicId::fromString(self::OWNER_CLINIC),
            staffUserId: UserId::fromString(self::USER_ID),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange('2030-03-04', '08:00', '12:00'),
            capacityPerHour: 4,
            rule: RecurrenceRule::none(),
            note: null,
        ));
    }

    public function testAnotherClinicCannotUpdateTheBlock(): void
    {
        try {
            $this->commandBus->dispatch($this->update(self::OTHER_CLINIC));
            self::fail('The update of another clinic\'s block must be refused.');
        } catch (PlanningBlockNotFoundException) {
        }

        $block = $this->reloadBlock();
        self::assertNotNull($block);
        self::assertSame(PlanningBlockType::CONSULTATION, $block->type());
        self::assertSame(4, $block->capacityPerHour());
        self::assertNull($block->note());
    }

    public function testTheOwningClinicStillUpdatesTheBlock(): void
    {
        $this->commandBus->dispatch($this->update(self::OWNER_CLINIC));

        $block = $this->reloadBlock();
        self::assertNotNull($block);
        self::assertSame(2, $block->capacityPerHour());
        self::assertSame('Pris par une autre clinique ?', $block->note());
    }

    public function testAnotherClinicCannotDeleteTheBlock(): void
    {
        try {
            $this->commandBus->dispatch(new DeletePlanningBlock(self::BLOCK_ID, self::OTHER_CLINIC));
            self::fail('The deletion of another clinic\'s block must be refused.');
        } catch (PlanningBlockNotFoundException) {
        }

        self::assertNotNull($this->reloadBlock());

        $this->commandBus->dispatch(new DeletePlanningBlock(self::BLOCK_ID, self::OWNER_CLINIC));

        self::assertNull($this->reloadBlock());
    }

    public function testAnotherClinicCannotCancelOrMarkAnAppointmentAsNoShow(): void
    {
        $upcoming = '01960000-0000-7000-8000-00000000c0e5';
        $started  = '01960000-0000-7000-8000-00000000c0f6';

        AppointmentEntityFactory::new()->withId($upcoming)->withClinicId(self::OWNER_CLINIC)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('+2 days'))
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;
        AppointmentEntityFactory::new()->withId($started)->withClinicId(self::OWNER_CLINIC)
            ->withPractitionerUserId(self::USER_ID)
            ->startingAt(new \DateTimeImmutable('-2 hours'))
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        foreach ([new CancelAppointment($upcoming, self::OTHER_CLINIC), new MarkAppointmentNoShow($started, self::OTHER_CLINIC)] as $command) {
            try {
                $this->commandBus->dispatch($command);
                self::fail('A write on another clinic\'s appointment must be refused.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('does not exist', $e->getMessage());
            }
        }

        self::assertSame(AppointmentStatus::PLANNED, $this->appointmentStatus($upcoming));
        self::assertSame(AppointmentStatus::PLANNED, $this->appointmentStatus($started));
    }

    private function update(string $clinicId): UpdatePlanningBlock
    {
        return new UpdatePlanningBlock(
            blockId: self::BLOCK_ID,
            clinicId: $clinicId,
            staffUserId: self::USER_ID,
            type: 'consultation',
            date: '2030-03-04',
            startTime: '08:00',
            endTime: '12:00',
            capacityPerHour: 2,
            recurrenceFreq: 'NONE',
            recurrenceUntil: null,
            note: 'Pris par une autre clinique ?',
        );
    }

    private function reloadBlock(): ?PlanningBlock
    {
        $this->entityManager()->clear();

        return $this->blocks->findById(PlanningBlockId::fromString(self::BLOCK_ID));
    }

    private function appointmentStatus(string $id): AppointmentStatus
    {
        $this->entityManager()->clear();

        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);

        $appointment = $appointments->findById(AppointmentId::fromString($id));
        self::assertNotNull($appointment);

        return $appointment->status();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }
}
