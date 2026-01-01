<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class Ping extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'test-bc';
    protected const int    VERSION         = 1;

    public function aggregateId(): string
    {
        return 'ping-000';
    }

    public function payload(): array
    {
        return [
            'message' => 'pong',
        ];
    }
}
