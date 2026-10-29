<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Shared\Application\Bus\EventBusInterface;

/**
 * Keeps the domain events a handler published.
 */
final class CapturingEventBus implements EventBusInterface
{
    /** @var list<object> */
    public array $events = [];

    public function publish(array $stamps, object ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }
}
