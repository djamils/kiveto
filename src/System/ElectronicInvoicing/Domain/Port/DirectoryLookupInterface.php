<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

interface DirectoryLookupInterface
{
    /**
     * @throws ExternalServiceUnavailable
     */
    public function lookup(string $siren): DirectoryEntry;
}
