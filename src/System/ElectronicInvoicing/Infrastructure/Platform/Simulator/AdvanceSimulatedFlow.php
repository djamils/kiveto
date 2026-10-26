<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Emits the next status of a simulated flow. Sent to the `async` transport
 * with a DelayStamp, so the statuses of a demo arrive over time.
 */
final readonly class AdvanceSimulatedFlow implements CommandInterface
{
    public function __construct(public string $flowId)
    {
    }
}
