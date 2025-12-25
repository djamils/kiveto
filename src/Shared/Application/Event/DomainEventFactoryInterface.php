<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;

interface DomainEventFactoryInterface
{
    /**
     * Creates a domain event with generated eventId + occurredAt.
     *
     * Convention:
     * - Event constructors MUST accept named parameters "eventId" and "occurredAt".
     * - Payload arguments are passed as an array.
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
