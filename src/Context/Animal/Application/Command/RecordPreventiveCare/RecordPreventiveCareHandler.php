<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RecordPreventiveCare;

use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\Repository\PreventiveCareRecordRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordPreventiveCareHandler
{
    public function __construct(
        private AnimalRepositoryInterface $animals,
        private PreventiveCareRecordRepositoryInterface $preventiveCareRecords,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordPreventiveCare $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $animalId = AnimalId::fromString($command->animalId);

        // Throws when the animal does not belong to the clinic.
        $this->animals->get($clinicId, $animalId);

        $recordId = PreventiveCareRecordId::fromString($this->uuidGenerator->generate());

        $record = PreventiveCareRecord::record(
            id: $recordId,
            clinicId: $clinicId,
            animalId: $animalId,
            kind: PreventiveCareKind::from($command->kind),
            label: $command->label,
            administeredOn: $this->date($command->administeredOn),
            batchNumber: $command->batchNumber,
            nextDueOn: null === $command->nextDueOn ? null : $this->date($command->nextDueOn),
            authorName: $command->authorName,
            recordedAt: $this->clock->now(),
        );

        $this->preventiveCareRecords->save($record);
        $this->domainEventPublisher->publish($record);

        return $recordId->toString();
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date) {
            throw new \InvalidArgumentException('Preventive care dates must be Y-m-d dates.');
        }

        return $date;
    }
}
