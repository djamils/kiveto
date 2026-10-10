<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientCommunicationLogged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $communicationId,
        private string $clientId,
        private string $clinicId,
        private string $channel,
        private string $direction,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->communicationId;
    }

    public function payload(): array
    {
        return [
            'communicationId' => $this->communicationId,
            'clientId'        => $this->clientId,
            'clinicId'        => $this->clinicId,
            'channel'         => $this->channel,
            'direction'       => $this->direction,
        ];
    }
}
