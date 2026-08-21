<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\AddMedicalAlert;

use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\MedicalAlert;
use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddMedicalAlertHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddMedicalAlert $command): void
    {
        $animal = $this->repository->get(
            ClinicId::fromString($command->clinicId),
            AnimalId::fromString($command->animalId),
        );

        $kind = MedicalAlertKind::tryFrom($command->kind);

        if (null === $kind) {
            throw new \InvalidArgumentException('Unknown medical alert kind');
        }

        $animal->addMedicalAlert(
            MedicalAlert::create($kind, $command->label, $command->note),
            $this->clock->now(),
        );

        $this->repository->save($animal);
    }
}
