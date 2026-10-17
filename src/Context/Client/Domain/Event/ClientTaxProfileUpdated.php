<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientTaxProfileUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clientId,
        private string $clinicId,
        private string $status,
        private ?string $legalName,
        private ?string $siren,
        private ?string $vatNumber,
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
            'status'    => $this->status,
            'legalName' => $this->legalName,
            'siren'     => $this->siren,
            'vatNumber' => $this->vatNumber,
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
        ];
    }
}
