<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Prescription;

use App\Context\Consultation\Application\Port\PrescriptionDocumentsInterface;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\PrescriptionFile;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFile;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Prescription's public query (the ordonnances of a consultation) and
 * command (render one under a given key).
 */
final readonly class CommandBusPrescriptionDocuments implements PrescriptionDocumentsInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function printable(string $clinicId, string $consultationId): array
    {
        $rows      = $this->queryBus->ask(new ListPrescriptionsForConsultation(clinicId: $clinicId, consultationId: $consultationId));
        $printable = [];

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if ($row instanceof ConsultationPrescriptionRow && $row->isPrintable() && null !== $row->number) {
                $printable[$row->id] = $row->number;
            }
        }

        return $printable;
    }

    public function store(string $clinicId, string $prescriptionId, string $storageKey): StoredDocument
    {
        $file = $this->commandBus->dispatch(new RenderPrescriptionFile($clinicId, $prescriptionId, $storageKey));
        \assert($file instanceof PrescriptionFile);

        return new StoredDocument($file->storageKey, $file->filename);
    }
}
