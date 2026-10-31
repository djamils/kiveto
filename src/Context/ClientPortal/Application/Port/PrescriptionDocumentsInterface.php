<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The prescriptions the Portal publishes (Prescription context). */
interface PrescriptionDocumentsInterface
{
    /**
     * The prescription as the Portal publishes it, or null when it is not
     * publishable: unknown, not issued, or without owner client.
     */
    public function publication(string $clinicId, string $prescriptionId): ?PrescriptionPublication;

    /** Renders the ordonnance PDF under the given key (no database write). */
    public function renderFile(string $clinicId, string $prescriptionId, string $storageKey): void;

    /**
     * Live state of each prescription, keyed by id; a missing id is unknown.
     *
     * @param list<string> $prescriptionIds
     *
     * @return array<string, PrescriptionPortalState>
     */
    public function states(string $clinicId, array $prescriptionIds): array;

    /** The lines of an issued prescription, or null. */
    public function content(string $clinicId, string $prescriptionId): ?PrescriptionContent;

    /**
     * @return list<PublishableSource>
     */
    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array;
}
