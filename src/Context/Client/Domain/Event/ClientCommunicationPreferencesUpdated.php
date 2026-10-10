<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientCommunicationPreferencesUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    /**
     * @param list<array{kind: string, channels: list<string>, grantedAt: string}> $consents
     */
    public function __construct(
        private string $clientId,
        private string $clinicId,
        private ?string $preferredChannel,
        private array $consents,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'         => $this->clientId,
            'clinicId'         => $this->clinicId,
            'preferredChannel' => $this->preferredChannel,
            'consents'         => $this->consents,
        ];
    }
}
