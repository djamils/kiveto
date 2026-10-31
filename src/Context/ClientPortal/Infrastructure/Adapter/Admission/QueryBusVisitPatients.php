<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Query\GetAdmissionVisit\AdmissionVisitView;
use App\Context\Admission\Application\Query\GetAdmissionVisit\GetAdmissionVisit;
use App\Context\ClientPortal\Application\Port\VisitPatientsInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;

/** Admission's GetAdmissionVisit, then Patient's GetPatientAnimalLink. */
final readonly class QueryBusVisitPatients implements VisitPatientsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function patientOfAdmission(string $clinicId, string $admissionId): ?string
    {
        try {
            $visit = $this->queryBus->ask(new GetAdmissionVisit($clinicId, $admissionId));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $visit instanceof AdmissionVisitView ? $visit->patientId : null;
    }

    public function animalOfPatient(string $clinicId, string $patientId): ?string
    {
        try {
            $link = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $link instanceof PatientAnimalLinkDto ? $link->animalId : null;
    }
}
