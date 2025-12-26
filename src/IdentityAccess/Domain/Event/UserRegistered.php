<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class UserRegistered extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'identity-access';
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
