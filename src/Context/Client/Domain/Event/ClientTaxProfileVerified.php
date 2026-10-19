<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * The answers of the company registry and VIES were stored on the client.
 */
readonly class ClientTaxProfileVerified extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clientId,
        private string $clinicId,
        private string $companyOutcome,
        private ?string $registryName,
        private ?string $companyCheckedAt,
        private string $vatOutcome,
        private ?string $vatCheckedAt,
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
            'companyOutcome'   => $this->companyOutcome,
            'registryName'     => $this->registryName,
            'companyCheckedAt' => $this->companyCheckedAt,
            'vatOutcome'       => $this->vatOutcome,
            'vatCheckedAt'     => $this->vatCheckedAt,
        ];
    }
}
