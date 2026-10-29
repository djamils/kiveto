<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** An owner account was created from a proven mobile phone. */
readonly class PortalUserRegistered extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'identity-access';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $userId,
        private string $phone,
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
            'phone'  => $this->phone,
        ];
    }
}
