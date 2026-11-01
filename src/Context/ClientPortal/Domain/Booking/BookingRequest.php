<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

/**
 * A new client's first appointment request through the clinic directory
 * (S7d): the owner account has no link to the clinic yet, so Scheduling
 * holds the slot with a pending appointment without owner nor animal, and
 * the clinic resolves it from « Demandes Portal » « Nouveau client ».
 *
 * The requester (Portal profile names, account phone) and the new pet are
 * snapshots taken at request time: staff match them against their files,
 * the decline SMS goes to that phone; account deletion erases them.
 * Each step moves only from `pending`, so a late or replayed event changes
 * nothing.
 */
final class BookingRequest
{
    private BookingRequestId $id;
    private string $userId;
    private string $clinicId;
    private string $listingId;
    private string $appointmentId;
    private string $motifLabel;
    private \DateTimeImmutable $startAt;
    private ?string $newPetName;
    private ?string $newPetSpecies;
    private ?string $requesterFirstName;
    private ?string $requesterLastName;
    private ?string $requesterPhone;
    private BookingRequestStatus $status;
    private ?DeclineReason $declineReason;
    private ?string $clientId;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $resolvedAt;

    private function __construct()
    {
    }

    /**
     * @param string $newPetSpecies  dog|cat|nac|other
     * @param string $requesterPhone the account phone (E.164)
     */
    public static function request(
        BookingRequestId $id,
        string $userId,
        string $clinicId,
        string $listingId,
        string $appointmentId,
        string $motifLabel,
        \DateTimeImmutable $startAt,
        string $newPetName,
        string $newPetSpecies,
        string $requesterFirstName,
        string $requesterLastName,
        string $requesterPhone,
        \DateTimeImmutable $now,
    ): self {
        $request                     = new self();
        $request->id                 = $id;
        $request->userId             = $userId;
        $request->clinicId           = $clinicId;
        $request->listingId          = $listingId;
        $request->appointmentId      = $appointmentId;
        $request->motifLabel         = $motifLabel;
        $request->startAt            = $startAt;
        $request->newPetName         = $newPetName;
        $request->newPetSpecies      = $newPetSpecies;
        $request->requesterFirstName = $requesterFirstName;
        $request->requesterLastName  = $requesterLastName;
        $request->requesterPhone     = $requesterPhone;
        $request->status             = BookingRequestStatus::PENDING;
        $request->declineReason      = null;
        $request->clientId           = null;
        $request->createdAt          = $now;
        $request->resolvedAt         = null;

        return $request;
    }

    public static function reconstitute(
        BookingRequestId $id,
        string $userId,
        string $clinicId,
        string $listingId,
        string $appointmentId,
        string $motifLabel,
        \DateTimeImmutable $startAt,
        ?string $newPetName,
        ?string $newPetSpecies,
        ?string $requesterFirstName,
        ?string $requesterLastName,
        ?string $requesterPhone,
        BookingRequestStatus $status,
        ?DeclineReason $declineReason,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $resolvedAt,
    ): self {
        $request                     = new self();
        $request->id                 = $id;
        $request->userId             = $userId;
        $request->clinicId           = $clinicId;
        $request->listingId          = $listingId;
        $request->appointmentId      = $appointmentId;
        $request->motifLabel         = $motifLabel;
        $request->startAt            = $startAt;
        $request->newPetName         = $newPetName;
        $request->newPetSpecies      = $newPetSpecies;
        $request->requesterFirstName = $requesterFirstName;
        $request->requesterLastName  = $requesterLastName;
        $request->requesterPhone     = $requesterPhone;
        $request->status             = $status;
        $request->declineReason      = $declineReason;
        $request->clientId           = $clientId;
        $request->createdAt          = $createdAt;
        $request->resolvedAt         = $resolvedAt;

        return $request;
    }

    /**
     * The clinic confirmed the appointment, for that client file (null when
     * it was confirmed at the desk without one).
     *
     * @return bool false when the request was no longer pending (nothing changed)
     */
    public function confirm(?string $clientId, \DateTimeImmutable $now): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status     = BookingRequestStatus::CONFIRMED;
        $this->clientId   = $clientId ?? $this->clientId;
        $this->resolvedAt = $now;

        return true;
    }

    /** @return bool false when the request was no longer pending (nothing changed) */
    public function decline(DeclineReason $reason, \DateTimeImmutable $now): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status        = BookingRequestStatus::DECLINED;
        $this->declineReason = $reason;
        $this->resolvedAt    = $now;

        return true;
    }

    /**
     * Another request of the account at the clinic was confirmed for that
     * client file: this one will be confirmed for the same file (AC 268).
     */
    public function attachClient(string $clientId): void
    {
        if ($this->isPending()) {
            $this->clientId = $clientId;
        }
    }

    public function isPending(): bool
    {
        return BookingRequestStatus::PENDING === $this->status;
    }

    public function id(): BookingRequestId
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function listingId(): string
    {
        return $this->listingId;
    }

    public function appointmentId(): string
    {
        return $this->appointmentId;
    }

    public function motifLabel(): string
    {
        return $this->motifLabel;
    }

    /** UTC. */
    public function startAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    /** Null once the account is deleted. */
    public function newPetName(): ?string
    {
        return $this->newPetName;
    }

    /** dog|cat|nac|other; null once the account is deleted. */
    public function newPetSpecies(): ?string
    {
        return $this->newPetSpecies;
    }

    public function requesterFirstName(): ?string
    {
        return $this->requesterFirstName;
    }

    public function requesterLastName(): ?string
    {
        return $this->requesterLastName;
    }

    /** E.164; null once the account is deleted. */
    public function requesterPhone(): ?string
    {
        return $this->requesterPhone;
    }

    public function status(): BookingRequestStatus
    {
        return $this->status;
    }

    public function declineReason(): ?DeclineReason
    {
        return $this->declineReason;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
