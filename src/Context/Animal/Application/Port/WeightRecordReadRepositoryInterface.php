<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Port;

use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;

interface WeightRecordReadRepositoryInterface
{
    /**
     * The weighings of one animal, newest first.
     *
     * @return list<WeightRecordView>
     */
    public function listForAnimal(ClinicId $clinicId, AnimalId $animalId): array;
}
