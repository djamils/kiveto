<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\UpdateAnimalInsurance;

use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Insurance;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAnimalInsuranceHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAnimalInsurance $command): void
    {
        $animal = $this->repository->get(
            ClinicId::fromString($command->clinicId),
            AnimalId::fromString($command->animalId),
        );

        $insurer = null === $command->insurerName ? '' : trim($command->insurerName);

        $animal->updateInsurance(
            '' === $insurer ? null : new Insurance($insurer, $command->policyNumber ?? ''),
            $this->clock->now(),
        );

        $this->repository->save($animal);
    }
}
