<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface EventBusInterface
{
    /**
     * Publish one or many events.
     *
     * @param list<object> $stamps Optional Messenger stamps (e.g. MessageMetadataStamp)
     */
    public function publish(array $stamps, object ...$events): void;
}
