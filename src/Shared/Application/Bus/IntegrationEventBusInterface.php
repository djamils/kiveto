<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Bus for publishing integration events (cross-bounded contexts).
 */
interface IntegrationEventBusInterface
{
    /**
     * Publish one or many integration events.
     *
     * @param list<object> $stamps Optional Messenger stamps (e.g. MessageMetadataStamp)
     */
    public function publish(array $stamps, object ...$events): void;
}
