<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SyncPlatformFlows;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * « Read what the platform has for this organisation since last time. ».
 */
final readonly class SyncPlatformFlows implements ExternalApiCommandInterface
{
    public function __construct(public string $organisationId)
    {
    }
}
