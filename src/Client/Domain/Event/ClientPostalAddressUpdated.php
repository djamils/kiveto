<?php

declare(strict_types=1);

namespace App\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientPostalAddressUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    /**
     * @param array{
     *     streetLine1: string,
     *     streetLine2: string|null,
     *     postalCode: string|null,
     *     city: string,
     *     region: string|null,
     *     countryCode: string
     * }|null $postalAddress
     */
    public function __construct(
        private string $clientId,
        private string $clinicId,
        private ?array $postalAddress,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'      => $this->clientId,
            'clinicId'      => $this->clinicId,
            'postalAddress' => $this->postalAddress,
        ];
    }
}
