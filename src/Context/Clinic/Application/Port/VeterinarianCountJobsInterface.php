<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Queues the count of today's active veterinarians of one clinic as a job of
 * its own (worker), so that the nightly run of every clinic does not hold one
 * transaction, and one failing clinic does not hold back the others.
 */
interface VeterinarianCountJobsInterface
{
    public function countLater(string $clinicId): void;
}
