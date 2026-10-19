<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger\Probe;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Test-only external-call command: proves the `async_external*` outbox and queue priority.
 */
final readonly class ProbeExternalApiCommand implements ExternalApiCommandInterface
{
    public function __construct(public string $marker)
    {
    }
}
