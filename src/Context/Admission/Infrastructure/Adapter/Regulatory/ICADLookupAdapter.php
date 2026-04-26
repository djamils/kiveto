<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Regulatory;

use App\Context\Admission\Application\Port\ICADLookupPort;

/**
 * Stub adapter for the Regulatory BC ICAD lookup.
 * Will dispatch an OpenICADLookup command once the Regulatory BC is built.
 */
final readonly class ICADLookupAdapter implements ICADLookupPort
{
    public function initiateChipLookup(string $chipNumber, string $clinicId): void
    {
        // TODO: dispatch OpenICADLookup command to Regulatory BC (Commit E)
    }
}
