<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListWeightRecordsForAnimals;

use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The weighings by animal id, newest first as for one animal; an animal
 * never weighed, unknown or of another clinic, is left out.
 */
#[AsMessageHandler]
final readonly class ListWeightRecordsForAnimalsHandler
{
    public function __construct(
        private WeightRecordReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, list<WeightRecordView>>
     */
    public function __invoke(ListWeightRecordsForAnimals $query): array
    {
        $animalIds = array_values(array_unique($query->animalIds));

        if ([] === $animalIds) {
            return [];
        }

        return $this->readRepository->listForAnimals(ClinicId::fromString($query->clinicId), $animalIds);
    }
}
