<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

final readonly class TransactionalityProbeIntegrationEvent extends AbstractIntegrationEvent
{
    public function aggregateId(): string
    {
        return 'probe';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }
}
