<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * The SIREN or the VAT number of the client changed (or was first given):
 * the previous verification no longer applies and a new one is requested.
 */
readonly class ClientTaxIdentifiersChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clientId,
        private string $clinicId,
        private string $taxStatus,
        private ?string $previousSiren,
        private ?string $siren,
        private ?string $previousVatNumber,
        private ?string $vatNumber,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'          => $this->clientId,
            'clinicId'          => $this->clinicId,
            'taxStatus'         => $this->taxStatus,
            'previousSiren'     => $this->previousSiren,
            'siren'             => $this->siren,
            'previousVatNumber' => $this->previousVatNumber,
            'vatNumber'         => $this->vatNumber,
        ];
    }
}
