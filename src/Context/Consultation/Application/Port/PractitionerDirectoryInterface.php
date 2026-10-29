<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The veterinarian who signs a consultation, read from the clinic's staff;
 * null when they are not (or no longer) one of its veterinarians.
 */
interface PractitionerDirectoryInterface
{
    public function find(string $clinicId, string $userId): ?ReportPractitioner;
}
