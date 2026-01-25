<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalLifeCycle;

use App\Animal\Domain\Enum\LifeStatus;
use App\Animal\Domain\Exception\AnimalClinicMismatch;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAnimalLifeCycleHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAnimalLifeCycle $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString($command->animalId);
        $now      = $this->clock->now();

        $animal = $this->repository->get($clinicId, $animalId);

        if (!$animal->clinicId()->equals($clinicId)) {
            throw AnimalClinicMismatch::create(
                $command->animalId,
                $command->clinicId,
                $animal->clinicId()->toString()
            );
        }

        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::from($command->lifeStatus),
            deceasedAt: $command->deceasedAt ? new \DateTimeImmutable($command->deceasedAt) : null,
            missingSince: $command->missingSince ? new \DateTimeImmutable($command->missingSince) : null,
        );

        $animal->updateLifeCycle($lifeCycle, $now);

        $this->repository->save($animal);
        $this->eventBus->publish([], ...$animal->pullDomainEvents());
    }
}
