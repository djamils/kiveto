<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final class FactoryEvent extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test';
    protected const VERSION         = 1;

    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        string $eventId,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'email'  => $this->email,
        ];
    }
}

