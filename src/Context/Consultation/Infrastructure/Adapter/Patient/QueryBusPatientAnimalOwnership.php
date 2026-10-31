<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Consultation\Application\Port\PatientAnimalOwnership;
use App\Context\Consultation\Application\Port\PatientAnimalOwnershipInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * GetPatientAnimalLink (Patient), then GetAnimalById (Animal) for its active
 * primary owner. An animal that cannot be read counts as one without owner.
 */
final readonly class QueryBusPatientAnimalOwnership implements PatientAnimalOwnershipInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function ownershipOf(string $clinicId, string $patientId): PatientAnimalOwnership
    {
        $link     = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));
        $animalId = $link instanceof PatientAnimalLinkDto ? $link->animalId : null;

        if (null === $animalId) {
            return new PatientAnimalOwnership(null, null);
        }

        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\Throwable) {
            $animal = null;
        }

        return new PatientAnimalOwnership(
            $animalId,
            $animal instanceof AnimalView ? $animal->primaryOwnerClientId() : null,
        );
    }
}
