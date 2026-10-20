<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Sends the transmissions of an organisation that waited for its connection.
 */
final readonly class ReleasePendingTransmissions implements CommandInterface
{
    public function __construct(public string $organisationId)
    {
    }
}
