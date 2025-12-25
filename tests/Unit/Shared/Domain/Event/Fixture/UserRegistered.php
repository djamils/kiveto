<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final class UserRegistered extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test-bc';
    protected const VERSION         = 1;

    public function aggregateId(): string
    {
        return 'user-123';
    }

    public function payload(): array
    {
        return [
            'email' => 'test@example.com',
        ];
    }
}

