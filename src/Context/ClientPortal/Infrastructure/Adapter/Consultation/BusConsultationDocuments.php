<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Consultation;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\Consultation\Application\Command\RenderConsultationReportFile\RenderConsultationReportFile;
use App\Context\Consultation\Application\Query\GetConsultationShareability\ConsultationShareability;
use App\Context\Consultation\Application\Query\GetConsultationShareability\GetConsultationShareability;
use App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission\ListConsultationIdsForAdmission;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Consultation's share check, its report rendering and the consultations of a visit. */
final readonly class BusConsultationDocuments implements ConsultationDocumentsInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function shareability(string $clinicId, string $consultationId): ?ReportShareability
    {
        $check = $this->queryBus->ask(new GetConsultationShareability($clinicId, $consultationId));

        if (!$check instanceof ConsultationShareability) {
            return null;
        }

        return new ReportShareability(
            $check->reason,
            $check->patientId,
            $check->animalId,
            $check->primaryOwnerClientId,
            $check->startedAtUtc,
            $check->closedAtUtc,
            $check->practitionerName,
        );
    }

    public function renderReport(string $clinicId, string $consultationId, string $storageKey): void
    {
        $this->commandBus->dispatch(new RenderConsultationReportFile($clinicId, $consultationId, $storageKey));
    }

    public function consultationsOfAdmission(string $clinicId, string $admissionId): array
    {
        $ids = $this->queryBus->ask(new ListConsultationIdsForAdmission($clinicId, $admissionId));
        \assert(\is_array($ids));

        return array_values(array_filter($ids, \is_string(...)));
    }
}
