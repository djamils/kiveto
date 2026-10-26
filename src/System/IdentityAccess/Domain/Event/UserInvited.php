<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** A clinic user was created PENDING, waiting to choose a password. */
final readonly class UserInvited extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'identity-access';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'email'  => $this->email,
        ];
    }
}
