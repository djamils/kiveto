<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\UpdateAnimalTransfer;

use App\Context\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
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
            throw new AnimalClinicMismatchException(
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
