<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RemoveMedicalAlert;

use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveMedicalAlertHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemoveMedicalAlert $command): void
    {
        $animal = $this->repository->get(
            ClinicId::fromString($command->clinicId),
            AnimalId::fromString($command->animalId),
        );

        $animal->removeMedicalAlert($command->alertId, $this->clock->now());

        $this->repository->save($animal);
    }
}
