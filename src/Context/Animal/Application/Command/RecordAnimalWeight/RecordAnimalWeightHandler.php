<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RecordAnimalWeight;

use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\Repository\WeightRecordRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Context\Animal\Domain\WeightRecord;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordAnimalWeightHandler
{
    public function __construct(
        private AnimalRepositoryInterface $animals,
        private WeightRecordRepositoryInterface $weightRecords,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordAnimalWeight $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString($command->animalId);

        // Throws when the animal does not belong to the clinic.
        $this->animals->get($clinicId, $animalId);

        $measuredOn = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->measuredOn);

        if (false === $measuredOn) {
            throw new \InvalidArgumentException('The weighing date must be a Y-m-d date.');
        }

        $recordId = WeightRecordId::fromString($this->uuidGenerator->generate());

        $record = WeightRecord::record(
            id: $recordId,
            clinicId: $clinicId,
            animalId: $animalId,
            weightGrams: $command->weightGrams,
            bodyConditionScore: $command->bodyConditionScore,
            measuredOn: $measuredOn,
            authorName: $command->authorName,
            recordedAt: $this->clock->now(),
        );

        $this->weightRecords->save($record);
        $this->domainEventPublisher->publish($record);

        return $recordId->toString();
    }
}
