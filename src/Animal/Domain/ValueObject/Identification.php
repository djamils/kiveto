<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

use App\Animal\Domain\Exception\InvalidIdentificationException;

final readonly class Identification
{
    public function __construct(
        public ?string $microchipNumber,
        public ?string $tattooNumber,
        public ?string $passportNumber,
        public RegistryType $registryType,
        public ?string $registryNumber,
        public ?string $sireNumber,
    ) {
    }

    public static function createEmpty(): self
    {
        return new self(
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: RegistryType::NONE,
            registryNumber: null,
            sireNumber: null,
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
            sireNumber: $this->sireNumber,
        );
    }

    public function ensureConsistency(): void
    {
        if (RegistryType::NONE === $this->registryType && null !== $this->registryNumber) {
            throw new InvalidIdentificationException('RegistryNumber must be null when RegistryType is NONE.');
        }
    }
}
