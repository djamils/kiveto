<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Application\Port\VaccinationRecordsInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Animal's ListAnimalPreventiveCare (latest first), vaccinations only. */
final readonly class QueryBusVaccinationRecords implements VaccinationRecordsInterface
{
    private const string VACCINATION = 'vaccination';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function vaccinationsOf(string $clinicId, string $animalId): array
    {
        $records = $this->queryBus->ask(new ListAnimalPreventiveCare($clinicId, $animalId));

        if (!\is_array($records)) {
            return [];
        }

        $vaccinations = [];

        foreach ($records as $record) {
            \assert($record instanceof PreventiveCareRecordView);

            if (self::VACCINATION === $record->kind) {
                $vaccinations[] = new VaccinationRecord($record->id, $record->label, $record->administeredOn, $record->batchNumber, $record->nextDueOn);
            }
        }

        return $vaccinations;
    }
}
