<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger\Probe;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Keeps the order in which the external worker handled the probe commands.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final class ProbeExternalApiCommandHandler
{
    /** @var list<string> */
    private array $handled = [];

    public function __invoke(ProbeExternalApiCommand $command): void
    {
        $this->handled[] = $command->marker;
    }

    /**
     * @return list<string>
     */
    public function handled(): array
    {
        return $this->handled;
    }
}
