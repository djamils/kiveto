<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\ArchiveAnimal;

use App\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ArchiveAnimalHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveAnimal $command): void
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

        $animal->archive($now);

        $this->repository->save($animal);
        $this->eventBus->publish([], ...$animal->pullDomainEvents());
    }
}
