<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

use App\Context\Regulatory\Domain\Event\ICADLookupFailed;
use App\Context\Regulatory\Domain\Event\ICADLookupFound;
use App\Context\Regulatory\Domain\Event\ICADLookupInitiated;
use App\Context\Regulatory\Domain\Event\ICADLookupNotFound;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupId;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Audit trail for I-CAD chip lookups (V1: manual entry, no external API call).
 */
final class ICADLookup extends AggregateRoot
{
    private function __construct(
        private readonly ICADLookupId $id,
        private readonly string $chipNumber,
        private readonly string $clinicId,
        private ICADLookupStatus $status,
        private ?string $icadAnimalData,
        private ?string $errorMessage,
        private int $version,
        private readonly \DateTimeImmutable $initiatedAt,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Initiates a new I-CAD chip lookup audit record.
     */
    public static function initiate(
        ICADLookupId $id,
        string $chipNumber,
        string $clinicId,
        \DateTimeImmutable $now,
    ): self {
        $lookup = new self(
            id: $id,
            chipNumber: $chipNumber,
            clinicId: $clinicId,
            status: ICADLookupStatus::Pending,
            icadAnimalData: null,
            errorMessage: null,
            version: 1,
            initiatedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );

        $lookup->recordDomainEvent(new ICADLookupInitiated(
            lookupId: $id->value(),
            chipNumber: $chipNumber,
            clinicId: $clinicId,
            initiatedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $lookup;
    }

    /**
     * Records that the chip was found in I-CAD.
     */
    public function recordFound(string $icadAnimalData, \DateTimeImmutable $now): void
    {
        $this->status         = ICADLookupStatus::FoundInICad;
        $this->icadAnimalData = $icadAnimalData;
        $this->updatedAt      = $now;

        $this->recordDomainEvent(new ICADLookupFound(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
            icadAnimalData: $icadAnimalData,
        ));
    }

    /**
     * Records that the chip was not found in I-CAD.
     */
    public function recordNotFound(\DateTimeImmutable $now): void
    {
        $this->status    = ICADLookupStatus::NotFoundInICad;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new ICADLookupNotFound(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
        ));
    }

    /**
     * Records that the I-CAD lookup failed due to a technical error.
     */
    public function recordFailed(string $errorMessage, \DateTimeImmutable $now): void
    {
        $this->status       = ICADLookupStatus::LookupFailed;
        $this->errorMessage = $errorMessage;
        $this->updatedAt    = $now;

        $this->recordDomainEvent(new ICADLookupFailed(
            lookupId: $this->id->value(),
            chipNumber: $this->chipNumber,
            errorMessage: $errorMessage,
        ));
    }

    /**
     * Reconstitutes the aggregate from persistence without firing domain events.
     */
    public static function reconstituteFromPersistence(
        ICADLookupId $id,
        string $chipNumber,
        string $clinicId,
        ICADLookupStatus $status,
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

    public function id(): ICADLookupId
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

    public function status(): ICADLookupStatus
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
