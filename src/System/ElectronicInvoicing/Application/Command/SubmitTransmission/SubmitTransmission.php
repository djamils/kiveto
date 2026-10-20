<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SubmitTransmission;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * « Send this transmission to the platform »: renders and stores its file the
 * first time, then deposits it. Network only; it records through commands.
 */
final readonly class SubmitTransmission implements ExternalApiCommandInterface
{
    public function __construct(public string $transmissionId)
    {
    }
}
