<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

use App\Context\Animal\Domain\Exception\InvalidIdentificationException;

final readonly class Identification
{
    public ?string $microchipNumber;
    public ?string $tattooNumber;
    public ?string $passportNumber;
    public ?string $registryNumber;
    public ?string $registryReference;

    public function __construct(
        ?string $microchipNumber,
        ?string $tattooNumber,
        ?string $passportNumber,
        public RegistryType $registryType,
        ?string $registryNumber,
        ?string $registryReference,
    ) {
        $this->microchipNumber   = '' === $microchipNumber ? null : $microchipNumber;
        $this->tattooNumber      = '' === $tattooNumber ? null : $tattooNumber;
        $this->passportNumber    = '' === $passportNumber ? null : $passportNumber;
        $this->registryNumber    = '' === $registryNumber ? null : $registryNumber;
        $this->registryReference = '' === $registryReference ? null : $registryReference;
    }

    public static function createEmpty(): self
    {
        return new self(
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: RegistryType::NONE,
            registryNumber: null,
            registryReference: null,
        );
    }

    public function withMicrochip(?string $microchipNumber): self
    {
        return new self(
            microchipNumber: $microchipNumber,
            tattooNumber: $this->tattooNumber,
            passportNumber: $this->passportNumber,
            registryType: $this->registryType,
            registryNumber: $this->registryNumber,
            registryReference: $this->registryReference,
        );
    }

    public function ensureConsistency(): void
    {
        if (RegistryType::NONE === $this->registryType && null !== $this->registryNumber) {
            throw new InvalidIdentificationException('RegistryNumber must be null when RegistryType is NONE.');
        }

        if (RegistryType::NONE === $this->registryType && null !== $this->registryReference) {
            throw new InvalidIdentificationException('RegistryReference must be null when RegistryType is NONE.');
        }
    }
}
