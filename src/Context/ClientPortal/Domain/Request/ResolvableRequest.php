<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException;

/**
 * The life cycle shared by pet change and renewal requests (S6a):
 * `submitted` → `done` | `rejected` (answered by the clinic, the owner then
 * notified) | `closed` (subject gone, never notified). Only a submitted
 * request moves.
 */
trait ResolvableRequest
{
    private PortalRequestId $id;
    private string $clinicId;
    private string $clientId;
    private string $userId;
    private RequestStatus $status;
    private \DateTimeImmutable $submittedAt;
    private \DateTimeImmutable $updatedAt;
    private ?RequestResolution $resolution;
    private ?\DateTimeImmutable $closedAt;
    private ?ClosureReason $closedReason;
    private ?string $notificationMessageId;
    private bool $clientUnreachable;

    /**
     * Closes a submitted request without answer.
     *
     * @return bool false when it was no longer submitted (nothing changed)
     */
    public function close(ClosureReason $reason, \DateTimeImmutable $now): bool
    {
        if (RequestStatus::SUBMITTED !== $this->status) {
            return false;
        }

        $this->status       = RequestStatus::CLOSED;
        $this->closedAt     = $now;
        $this->closedReason = $reason;
        $this->updatedAt    = $now;

        return true;
    }

    /** The owner was told of the answer by that message. */
    public function attachNotification(string $messageId): void
    {
        $this->notificationMessageId = $messageId;
        $this->clientUnreachable     = false;
    }

    /** The answer could not be sent: the client has no address (« client injoignable »). */
    public function markClientUnreachable(): void
    {
        $this->clientUnreachable = true;
    }

    public function isSubmitted(): bool
    {
        return RequestStatus::SUBMITTED === $this->status;
    }

    public function id(): PortalRequestId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function status(): RequestStatus
    {
        return $this->status;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function resolution(): ?RequestResolution
    {
        return $this->resolution;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedReason(): ?ClosureReason
    {
        return $this->closedReason;
    }

    public function notificationMessageId(): ?string
    {
        return $this->notificationMessageId;
    }

    public function isClientUnreachable(): bool
    {
        return $this->clientUnreachable;
    }

    /**
     * @throws PortalRequestAlreadyResolvedException when the request is no longer submitted
     */
    private function applyResolution(RequestResolution $resolution): void
    {
        if (RequestStatus::SUBMITTED !== $this->status) {
            throw new PortalRequestAlreadyResolvedException($this->id->toString(), $this->status->value, $this->resolution?->resolvedByName());
        }

        $this->status     = $resolution->outcome();
        $this->resolution = $resolution;
        $this->updatedAt  = $resolution->resolvedAt();
    }

    private function initialise(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        RequestStatus $status,
        \DateTimeImmutable $submittedAt,
        \DateTimeImmutable $updatedAt,
        ?RequestResolution $resolution,
        ?\DateTimeImmutable $closedAt,
        ?ClosureReason $closedReason,
        ?string $notificationMessageId,
        bool $clientUnreachable,
    ): void {
        $this->id                    = $id;
        $this->clinicId              = $clinicId;
        $this->clientId              = $clientId;
        $this->userId                = $userId;
        $this->status                = $status;
        $this->submittedAt           = $submittedAt;
        $this->updatedAt             = $updatedAt;
        $this->resolution            = $resolution;
        $this->closedAt              = $closedAt;
        $this->closedReason          = $closedReason;
        $this->notificationMessageId = $notificationMessageId;
        $this->clientUnreachable     = $clientUnreachable;
    }
}
