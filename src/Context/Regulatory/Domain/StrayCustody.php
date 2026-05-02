<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain;

use App\Context\Regulatory\Domain\Event\StrayCustodyBegun;
use App\Context\Regulatory\Domain\Event\StrayCustodyCancelledOwnerFound;
use App\Context\Regulatory\Domain\Event\StrayCustodyClosedHandedToAuthority;
use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyId;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Tracks the mandatory holding period for an unidentified stray animal before
 * possible handover to the competent authority. Deadline is delegated to the
 * jurisdiction policy (`RegulatoryPolicyInterface::getStrayCustodyDeadline`).
 */
final class StrayCustody extends AggregateRoot
{
    private function __construct(
        private readonly StrayCustodyId $id,
        private readonly string $admissionId,
        private readonly string $patientId,
        private readonly string $clinicId,
        private StrayCustodyStatus $status,
        private readonly \DateTimeImmutable $deadline,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Begins a stray custody period.
     * Deadline is determined by the jurisdiction policy.
     */
    public static function begin(
        StrayCustodyId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        \DateTimeImmutable $admissionOpenedAt,
        RegulatoryPolicyInterface $policy,
        \DateTimeImmutable $now,
    ): self {
        $deadline = $policy->getStrayCustodyDeadline($admissionOpenedAt);

        $custody = new self(
            id: $id,
            admissionId: $admissionId,
            patientId: $patientId,
            clinicId: $clinicId,
            status: StrayCustodyStatus::Active,
            deadline: $deadline,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $custody->recordDomainEvent(new StrayCustodyBegun(
            custodyId: $id->value(),
            admissionId: $admissionId,
            clinicId: $clinicId,
            deadline: $deadline->format(\DateTimeInterface::ATOM),
        ));

        return $custody;
    }

    /**
     * Cancels the custody because the owner was found.
     * Guard: status must be Active.
     */
    public function cancelOwnerFound(\DateTimeImmutable $now): void
    {
        if (StrayCustodyStatus::Active !== $this->status) {
            throw new \DomainException(\sprintf(
                'Cannot cancel StrayCustody "%s": current status is "%s", expected "%s".',
                $this->id->value(),
                $this->status->value,
                StrayCustodyStatus::Active->value,
            ));
        }

        $this->status    = StrayCustodyStatus::CancelledOwnerFound;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new StrayCustodyCancelledOwnerFound(
            custodyId: $this->id->value(),
            admissionId: $this->admissionId,
            cancelledAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Closes the custody after the animal has been handed to the authority.
     * Guard: status must be Active.
     */
    public function closeHandedToAuthority(\DateTimeImmutable $now): void
    {
        if (StrayCustodyStatus::Active !== $this->status) {
            throw new \DomainException(\sprintf(
                'Cannot close StrayCustody "%s": current status is "%s", expected "%s".',
                $this->id->value(),
                $this->status->value,
                StrayCustodyStatus::Active->value,
            ));
        }

        $this->status    = StrayCustodyStatus::ClosedHandedToAuthority;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new StrayCustodyClosedHandedToAuthority(
            custodyId: $this->id->value(),
            admissionId: $this->admissionId,
            closedAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Reconstitutes the aggregate from persistence without firing domain events.
     */
    public static function reconstituteFromPersistence(
        StrayCustodyId $id,
        string $admissionId,
        string $patientId,
        string $clinicId,
        StrayCustodyStatus $status,
        \DateTimeImmutable $deadline,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            admissionId: $admissionId,
            patientId: $patientId,
            clinicId: $clinicId,
            status: $status,
            deadline: $deadline,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): StrayCustodyId
    {
        return $this->id;
    }

    public function admissionId(): string
    {
        return $this->admissionId;
    }

    public function patientId(): string
    {
        return $this->patientId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function status(): StrayCustodyStatus
    {
        return $this->status;
    }

    public function deadline(): \DateTimeImmutable
    {
        return $this->deadline;
    }

    public function version(): int
    {
        return $this->version;
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
