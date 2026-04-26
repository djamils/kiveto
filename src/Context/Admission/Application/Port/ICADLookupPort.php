<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface ICADLookupPort
{
    /**
     * Dispatches an OpenICADLookup command to the Regulatory BC to trigger an audit-trailed chip search.
     */
    public function initiateChipLookup(string $chipNumber, string $clinicId): void;
}
