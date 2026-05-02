<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\UpdateAnimalLifeCycle;

use App\Context\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler

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
            throw new AnimalClinicMismatchException(
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
