<?php

declare(strict_types=1);

namespace App\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientIdentityUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clientId,
        private string $clinicId,
        private string $firstName,
        private string $lastName,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'  => $this->clientId,
            'clinicId'  => $this->clinicId,
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
        ];
    }
}
