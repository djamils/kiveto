<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Domain event marker interface.
 * Domain events are internal to a bounded context.
 */
interface DomainEventInterface extends EventInterface
{
}
