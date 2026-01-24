<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class Identification
{
    public function __construct(
        public ?string $microchipNumber,
        public ?string $tattooNumber,
        public ?string $passportNumber,
        public \App\Animal\Domain\Enum\RegistryType $registryType,
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
            registryType: \App\Animal\Domain\Enum\RegistryType::NONE,
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
        if (\App\Animal\Domain\Enum\RegistryType::NONE === $this->registryType && null !== $this->registryNumber) {
            throw new \App\Animal\Domain\Exception\InvalidIdentification('RegistryNumber must be null when RegistryType is NONE.');
        }
    }
}
