<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;

interface DomainEventFactoryInterface
{
    /**
     * Creates a domain event (pure event, no metadata).
     *
     * @template T of DomainEventInterface
     *
     * @param class-string<T> $eventClass
     * @param list<mixed>     $args
     *
     * @return T
     */
    public function create(string $eventClass, array $args): DomainEventInterface;
}
