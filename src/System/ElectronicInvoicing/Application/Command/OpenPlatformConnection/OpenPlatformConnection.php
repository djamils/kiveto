<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Opens the connection of an organisation (SIREN) to the configured platform.
 */
final readonly class OpenPlatformConnection implements CommandInterface
{
    public function __construct(public string $organisationId)
    {
    }
}
