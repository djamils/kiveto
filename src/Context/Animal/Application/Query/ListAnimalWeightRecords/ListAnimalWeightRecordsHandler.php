<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalWeightRecords;

use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAnimalWeightRecordsHandler
{
    public function __construct(
        private WeightRecordReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<WeightRecordView>
     */
    public function __invoke(ListAnimalWeightRecords $query): array
    {
        return $this->readRepository->listForAnimal(
            ClinicId::fromString($query->clinicId),
            AnimalId::fromString($query->animalId),
        );
    }
}
