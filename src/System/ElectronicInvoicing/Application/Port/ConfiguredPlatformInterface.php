<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

/**
 * Which approved platform this installation talks to.
 */
interface ConfiguredPlatformInterface
{
    /**
     * The platform code (« simulator »…), or null when electronic invoicing
     * is disabled: no connection can then be opened.
     */
    public function code(): ?string;
}
