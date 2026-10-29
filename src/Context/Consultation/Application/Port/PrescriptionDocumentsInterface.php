<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The ordonnances of a consultation, read and rendered by the Prescription
 * context.
 */
interface PrescriptionDocumentsInterface
{
    /**
     * The signed, uncancelled ordonnances of the consultation.
     *
     * @return array<string, string> prescription id => number (« ORD-2026-000391 »)
     */
    public function printable(string $clinicId, string $consultationId): array;

    /**
     * Renders the ordonnance and stores it under the given key (a retry
     * overwrites the same file).
     *
     * @throws \DomainException when the prescription is unknown to this clinic
     */
    public function store(string $clinicId, string $prescriptionId, string $storageKey): StoredDocument;
}
