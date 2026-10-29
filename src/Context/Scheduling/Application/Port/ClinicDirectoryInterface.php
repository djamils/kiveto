<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * What the client messages of an appointment need from Clinic: the clinic
 * (time zone for every label and due time, name and address for the
 * calendar invitation) and the printed name of a member (« Dr Claire
 * Rousseau », D15).
 */
interface ClinicDirectoryInterface
{
    /**
     * @throws \DomainException when the clinic does not exist
     */
    public function clinic(string $clinicId): ClinicCard;

    /** Null when the member has no staff profile in that clinic. */
    public function printedName(string $clinicId, string $userId): ?string;
}
