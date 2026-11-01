<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Prescription;

use App\Context\ClientPortal\Application\Port\PrescriptionContent;
use App\Context\ClientPortal\Application\Port\PrescriptionContentLine;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionPortalState;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Application\Port\PublishableSource;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFile;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionLineView;
use App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions\ListPortalPublishablePrescriptions;
use App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions\PortalPublishablePrescription;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\ListPrescriptionPortalSummaries;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\PrescriptionPortalSummary;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Prescription's ordonnance queries and its PDF rendering under a given key. */
final readonly class BusPrescriptionDocuments implements PrescriptionDocumentsInterface
{
    private const string ISSUED = 'ISSUED';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function publication(string $clinicId, string $prescriptionId): ?PrescriptionPublication
    {
        $prescription = $this->issued($clinicId, $prescriptionId);

        if (null === $prescription?->issuedAt || null === $prescription->ownerClientId || null === $prescription->number) {
            return null;
        }

        return new PrescriptionPublication(
            $prescription->id,
            $prescription->ownerClientId,
            $prescription->number,
            $prescription->issuedAt,
            $prescription->consultationId,
            $prescription->patientAnimalId,
            $prescription->prescriberName,
            $prescription->prescriberId,
        );
    }

    public function renderFile(string $clinicId, string $prescriptionId, string $storageKey): void
    {
        $this->commandBus->dispatch(new RenderPrescriptionFile($clinicId, $prescriptionId, $storageKey));
    }

    public function states(string $clinicId, array $prescriptionIds): array
    {
        if ([] === $prescriptionIds) {
            return [];
        }

        /** @var list<PrescriptionPortalSummary> $summaries */
        $summaries = $this->queryBus->ask(new ListPrescriptionPortalSummaries($clinicId, $prescriptionIds));
        $states    = [];

        foreach ($summaries as $summary) {
            $states[$summary->id] = new PrescriptionPortalState(self::ISSUED === $summary->status, $summary->validUntil, $summary->renewableByOwner);
        }

        return $states;
    }

    public function content(string $clinicId, string $prescriptionId): ?PrescriptionContent
    {
        $prescription = $this->issued($clinicId, $prescriptionId);

        if (null === $prescription?->issuedAt) {
            return null;
        }

        return new PrescriptionContent(
            $prescription->id,
            $prescription->issuedAt,
            $prescription->prescriberName,
            $prescription->patientAnimalId,
            $prescription->patientName,
            array_map(static fn (PrescriptionLineView $line): PrescriptionContentLine => new PrescriptionContentLine($line->label, $line->quantity, $line->posology), $prescription->lines),
        );
    }

    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array
    {
        /** @var list<PortalPublishablePrescription> $prescriptions */
        $prescriptions = $this->queryBus->ask(new ListPortalPublishablePrescriptions($issuedFrom, $afterId, $limit));

        return array_map(static fn (PortalPublishablePrescription $prescription): PublishableSource => new PublishableSource($prescription->prescriptionId, $prescription->clinicId), $prescriptions);
    }

    /** The prescription when it is issued; null when unknown, malformed or in any other status. */
    private function issued(string $clinicId, string $prescriptionId): ?PrescriptionDetails
    {
        try {
            $prescription = $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $prescriptionId));
        } catch (\DomainException) {
            return null;
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $prescription instanceof PrescriptionDetails && self::ISSUED === $prescription->status ? $prescription : null;
    }
}
