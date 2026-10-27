<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Query\GetAdmissionVisit\AdmissionVisitView;
use App\Context\Admission\Application\Query\GetAdmissionVisit\GetAdmissionVisit;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Billing\Application\Port\VisitDirectoryInterface;
use App\Context\Billing\Application\Port\VisitSummary;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The visit an invoice charges, hop by hop through each context's public
 * query: the admission (Admission), its patient's animal (Patient), the
 * animal's name (Animal — an unknown animal, refused with a LogicException,
 * reads as no name, like a patient never identified).
 */
final readonly class QueryBusVisitDirectory implements VisitDirectoryInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function visitOf(string $clinicId, string $admissionId): ?VisitSummary
    {
        $admission = $this->queryBus->ask(new GetAdmissionVisit($clinicId, $admissionId));

        if (!$admission instanceof AdmissionVisitView) {
            return null;
        }

        return new VisitSummary($this->animalName($clinicId, $admission->patientId), $admission->openedAt);
    }

    private function animalName(string $clinicId, string $patientId): ?string
    {
        $link = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));

        if (!$link instanceof PatientAnimalLinkDto || null === $link->animalId) {
            return null;
        }

        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $link->animalId));
        } catch (\LogicException) {
            return null;
        }

        $name = $animal instanceof AnimalView ? mb_trim($animal->name) : '';

        return '' !== $name ? $name : null;
    }
}
