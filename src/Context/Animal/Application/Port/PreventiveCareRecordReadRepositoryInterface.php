<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Port;

use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;

interface PreventiveCareRecordReadRepositoryInterface
{
    /**
     * The preventive acts of one animal, newest first.
     *
     * @return list<PreventiveCareRecordView>
     */
    public function listForAnimal(ClinicId $clinicId, AnimalId $animalId): array;
}
