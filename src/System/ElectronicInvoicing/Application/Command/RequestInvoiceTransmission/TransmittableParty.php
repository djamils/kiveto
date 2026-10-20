<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * The seller (BG-4) or the buyer (BG-7) of a document.
 */
final readonly class TransmittableParty
{
    public function __construct(
        public string $name,
        public string $siren,
        public ?string $vatNumber,
        public TransmittableAddress $address,
        public TransmittableElectronicAddress $electronicAddress,
    ) {
    }

    /**
     * @return array{name: string, siren: string, vatNumber: ?string, address: array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string}, electronicAddress: array{scheme: string, identifier: string}}
     */
    public function toArray(): array
    {
        return [
            'name'              => $this->name,
            'siren'             => $this->siren,
            'vatNumber'         => $this->vatNumber,
            'address'           => $this->address->toArray(),
            'electronicAddress' => $this->electronicAddress->toArray(),
        ];
    }

    /**
     * @param array{name: string, siren: string, vatNumber: ?string, address: array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string}, electronicAddress: array{scheme: string, identifier: string}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['siren'],
            $data['vatNumber'],
            TransmittableAddress::fromArray($data['address']),
            TransmittableElectronicAddress::fromArray($data['electronicAddress']),
        );
    }
}
