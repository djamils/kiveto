<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\RebuildClinicListings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Refreshes the projection of every clinic Clinic knows (`clinic-listing:rebuild`,
 * run at deploy and by the dev dataset) → int, the number of clinics projected.
 */
final readonly class RebuildClinicListings implements CommandInterface
{
}
