<?php

declare(strict_types=1);

namespace App\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientContactMethodsReplaced extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    /**
     * @param array<int, array{type: string, label: string, value: string, isPrimary: bool}> $contactMethods
     */
    public function __construct(
        private string $clientId,
        private string $clinicId,
        private array $contactMethods,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'       => $this->clientId,
            'clinicId'       => $this->clinicId,
            'contactMethods' => $this->contactMethods,
        ];
    }
}
