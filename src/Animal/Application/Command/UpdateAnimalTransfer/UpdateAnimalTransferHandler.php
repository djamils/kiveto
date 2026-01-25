<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalTransfer;

use App\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Domain\ValueObject\TransferStatus;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// CommandHandlerInterface removed - Symfony handles it via AsMessageHandler

#[AsMessageHandler]
final readonly class UpdateAnimalTransferHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateAnimalTransfer $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString($command->animalId);
        $now      = $this->clock->now();

        $animal = $this->repository->get($clinicId, $animalId);

        if (!$animal->clinicId()->equals($clinicId)) {
            throw AnimalClinicMismatchException::create(
                $command->animalId,
                $command->clinicId,
                $animal->clinicId()->toString()
            );
        }

        $transfer = new Transfer(
            transferStatus: TransferStatus::from($command->transferStatus),
            soldAt: $command->soldAt ? new \DateTimeImmutable($command->soldAt) : null,
            givenAt: $command->givenAt ? new \DateTimeImmutable($command->givenAt) : null,
        );

        $animal->updateTransfer($transfer, $now);

        $this->repository->save($animal);
        $this->eventBus->publish([], ...$animal->pullDomainEvents());
    }
}
