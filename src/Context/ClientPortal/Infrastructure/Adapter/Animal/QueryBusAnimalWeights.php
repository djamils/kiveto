<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\ClientPortal\Application\Port\AnimalWeightsInterface;
use App\Context\ClientPortal\Application\Port\RecordedWeight;
use App\Shared\Application\Bus\QueryBusInterface;

/** Animal's ListAnimalWeightRecords, newest measurement first: its first entry. */
final readonly class QueryBusAnimalWeights implements AnimalWeightsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function latestOf(string $clinicId, string $animalId): ?RecordedWeight
    {
        $records = $this->queryBus->ask(new ListAnimalWeightRecords($clinicId, $animalId));
        $latest  = \is_array($records) ? ($records[0] ?? null) : null;

        if (!$latest instanceof WeightRecordView) {
            return null;
        }

        return new RecordedWeight($latest->weightGrams, $latest->measuredOn);
    }
}
