<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

/**
 * The platform permanently refused a deposit (a 4xx other than throttling):
 * retrying the same file would be refused again.
 */
final class PlatformRefusedFlowException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(\sprintf('The platform refused the flow: %s', $reason));
    }
}
