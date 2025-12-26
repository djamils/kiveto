<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class FactoryEvent extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'test';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $userId,
        private string $email,
    ) {
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
