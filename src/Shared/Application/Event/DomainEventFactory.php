<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class DomainEventFactory implements DomainEventFactoryInterface
{
    public function create(string $eventClass, array $args): DomainEventInterface
    {
        /* @var DomainEventInterface $event */
        return new $eventClass(...$args);
    }
}
