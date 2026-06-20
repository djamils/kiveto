<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Shared\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;

final readonly class Address
{
    private function __construct(
        public readonly ?string $street,
        public readonly ?string $addressLine2,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?CountryCode $countryCode,
    ) {
    }

    public static function create(?string $street, ?string $addressLine2, ?string $postalCode, ?string $city, ?CountryCode $countryCode): self
    {
        return new self($street, $addressLine2, $postalCode, $city, $countryCode);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'street'       => $this->street,
            'addressLine2' => $this->addressLine2,
            'postalCode'   => $this->postalCode,
            'city'         => $this->city,
            'countryCode'  => $this->countryCode?->toString(),
        ];
    }

    /** @param array<string, string|null> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['street'] ?? null,
            $data['addressLine2'] ?? null,
            $data['postalCode'] ?? null,
            $data['city'] ?? null,
            isset($data['countryCode']) ? CountryCode::fromString($data['countryCode']) : null,
        );
    }
}
