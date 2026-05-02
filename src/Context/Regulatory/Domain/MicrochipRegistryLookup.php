<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

use App\Context\Regulatory\Domain\Event\MicrochipRegistryLookupFailed;
use App\Context\Regulatory\Domain\Event\MicrochipRegistryLookupFound;
use App\Context\Regulatory\Domain\Event\MicrochipRegistryLookupInitiated;
use App\Context\Regulatory\Domain\Event\MicrochipRegistryLookupNotFound;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupId;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Audit trail for microchip registry lookups (V1: manual entry, no external API call).
 */
final class MicrochipRegistryLookup extends AggregateRoot
{
    private function __construct(
        private readonly MicrochipRegistryLookupId $id,
        private readonly string $chipNumber,
        private readonly string $clinicId,
        private MicrochipRegistryLookupStatus $status,
        private ?string $icadAnimalData,
        private ?string $errorMessage,
        private int $version,
        private readonly \DateTimeImmutable $initiatedAt,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Initiates a new microchip registry lookup audit record.
     */
    public static function initiate(
        MicrochipRegistryLookupId $id,
        string $chipNumber,
        string $clinicId,
        \DateTimeImmutable $now,
    ): self {
        $lookup = new self(
            id: $id,
            chipNumber: $chipNumber,
            clinicId: $clinicId,
            status: MicrochipRegistryLookupStatus::Pending,
            icadAnimalData: null,
            errorMessage: null,
            version: 1,
            initiatedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );

        $lookup->recordDomainEvent(new MicrochipRegistryLookupInitiated(
            lookupId: $id->value(),
            chipNumber: $chipNumber,
            clinicId: $clinicId,
            initiatedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $lookup;
    }

    /**
     * Records that the chip was found in the microchip registry.
     */
    public function recordFound(string $icadAnimalData, \DateTimeImmutable $now): void
    {
        $this->status         = MicrochipRegistryLookupStatus::FoundInICad;
        $this->icadAnimalData = $icadAnimalData;
        $this->updatedAt      = $now;

        $this->recordDomainEvent(new MicrochipRegistryLookupFound(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
            icadAnimalData: $icadAnimalData,
        ));
    }

    /**
     * Records that the chip was not found in the microchip registry.
     */
    public function recordNotFound(\DateTimeImmutable $now): void
    {
        $this->status    = MicrochipRegistryLookupStatus::NotFoundInICad;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new MicrochipRegistryLookupNotFound(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
        ));
    }

    /**
     * Records that the microchip registry lookup failed due to a technical error.
     */
    public function recordFailed(string $errorMessage, \DateTimeImmutable $now): void
    {
        $this->status       = MicrochipRegistryLookupStatus::LookupFailed;
        $this->errorMessage = $errorMessage;
        $this->updatedAt    = $now;

        $this->recordDomainEvent(new MicrochipRegistryLookupFailed(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
            errorMessage: $errorMessage,
        ));
    }

    /**
     * Reconstitutes the aggregate from persistence without firing domain events.
     */
    public static function reconstituteFromPersistence(
        MicrochipRegistryLookupId $id,
        string $chipNumber,
        string $clinicId,
        MicrochipRegistryLookupStatus $status,
        ?string $icadAnimalData,
        ?string $errorMessage,
        int $version,
        \DateTimeImmutable $initiatedAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            chipNumber: $chipNumber,
            clinicId: $clinicId,
            status: $status,
            icadAnimalData: $icadAnimalData,
            errorMessage: $errorMessage,
            version: $version,
            initiatedAt: $initiatedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): MicrochipRegistryLookupId
    {
        return $this->id;
    }

    public function chipNumber(): string
    {
        return $this->chipNumber;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function status(): MicrochipRegistryLookupStatus
    {
        return $this->status;
    }

    public function icadAnimalData(): ?string
    {
        return $this->icadAnimalData;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function initiatedAt(): \DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
