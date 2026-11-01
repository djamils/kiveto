<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\RefreshClinicListing;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Re-reads the clinic from Clinic and rewrites its directory projection
 * (consumer of ClinicProfileChangedIntegrationEvent). An unknown clinic
 * writes nothing.
 */
final readonly class RefreshClinicListing implements CommandInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
