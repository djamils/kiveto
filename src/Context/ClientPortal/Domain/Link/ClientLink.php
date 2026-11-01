<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkRevoked;
use App\Context\ClientPortal\Domain\Exception\ClientLinkNotPendingException;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * An owner account (PORTAL user) reaching one client file of one clinic.
 * One link per (user, clinic, client): a revoked link is reactivated, never
 * duplicated. Only a confirmed link gives access to the clinic's data; a
 * `pending` link (an accepted link suggestion) waits for the clinic to
 * confirm or reject it.
 */
final class ClientLink extends AggregateRoot
{
    private ClientLinkId $id;
    private string $userId;
    private string $clinicId;
    private string $clientId;
    private ClientLinkStatus $status;
    private ?\DateTimeImmutable $linkedAt;
    private ?\DateTimeImmutable $revokedAt;
    private ?\DateTimeImmutable $requestedAt = null;

    private function __construct()
    {
    }

    /** A new link, confirmed at once (an accepted invitation). */
    public static function confirm(
        ClientLinkId $id,
        string $userId,
        string $clinicId,
        string $clientId,
        \DateTimeImmutable $now,
    ): self {
        $link            = new self();
        $link->id        = $id;
        $link->userId    = $userId;
        $link->clinicId  = $clinicId;
        $link->clientId  = $clientId;
        $link->status    = ClientLinkStatus::CONFIRMED;
        $link->linkedAt  = $now;
        $link->revokedAt = null;

        $link->recordConfirmed();

        return $link;
    }

    /**
     * A link the owner asked for by accepting a link suggestion: pending,
     * without access nor link date, until the clinic confirms it.
     */
    public static function requestFromSuggestion(
        ClientLinkId $id,
        string $userId,
        string $clinicId,
        string $clientId,
        \DateTimeImmutable $now,
    ): self {
        $link              = new self();
        $link->id          = $id;
        $link->userId      = $userId;
        $link->clinicId    = $clinicId;
        $link->clientId    = $clientId;
        $link->status      = ClientLinkStatus::PENDING;
        $link->linkedAt    = null;
        $link->revokedAt   = null;
        $link->requestedAt = $now;

        return $link;
    }

    public static function reconstitute(
        ClientLinkId $id,
        string $userId,
        string $clinicId,
        string $clientId,
        ClientLinkStatus $status,
        ?\DateTimeImmutable $linkedAt,
        ?\DateTimeImmutable $revokedAt,
        ?\DateTimeImmutable $requestedAt = null,
    ): self {
        $link              = new self();
        $link->id          = $id;
        $link->userId      = $userId;
        $link->clinicId    = $clinicId;
        $link->clientId    = $clientId;
        $link->status      = $status;
        $link->linkedAt    = $linkedAt;
        $link->revokedAt   = $revokedAt;
        $link->requestedAt = $requestedAt;

        return $link;
    }

    /**
     * The clinic confirms a pending link: access from now on. A link
     * already confirmed is left as it is; a revoked one (rejected or
     * withdrawn meanwhile) cannot be confirmed this way.
     */
    public function confirmPending(\DateTimeImmutable $now): void
    {
        if (ClientLinkStatus::CONFIRMED === $this->status) {
            return;
        }

        if (ClientLinkStatus::PENDING !== $this->status) {
            throw new ClientLinkNotPendingException($this->id->toString(), $this->status->value);
        }

        $this->status   = ClientLinkStatus::CONFIRMED;
        $this->linkedAt = $now;

        $this->recordConfirmed();
    }

    /**
     * The clinic rejects a pending link (`link_rejected`): it is revoked and
     * kept, so the suggestion never comes back. Consents are not touched
     * (D5). A link already revoked is left as it is; a confirmed one is not
     * rejected (its access is withdrawn by archiving the client).
     */
    public function reject(\DateTimeImmutable $now): void
    {
        if (ClientLinkStatus::REVOKED === $this->status) {
            return;
        }

        if (ClientLinkStatus::PENDING !== $this->status) {
            throw new ClientLinkNotPendingException($this->id->toString(), $this->status->value);
        }

        $this->status    = ClientLinkStatus::REVOKED;
        $this->revokedAt = $now;

        $this->recordDomainEvent(new ClientLinkRevoked(
            linkId: $this->id->toString(),
            userId: $this->userId,
            clinicId: $this->clinicId,
            clientId: $this->clientId,
            reason: ClientLinkRevoked::REASON_REJECTED,
        ));
    }

    /**
     * Confirms a pending or revoked link again, with a new `linkedAt`. A link
     * already confirmed is left as it is.
     */
    public function reactivate(\DateTimeImmutable $now): void
    {
        if (ClientLinkStatus::CONFIRMED === $this->status) {
            return;
        }

        $this->status    = ClientLinkStatus::CONFIRMED;
        $this->linkedAt  = $now;
        $this->revokedAt = null;

        $this->recordConfirmed();
    }

    /** Withdraws the access; a link already revoked is left as it is. */
    public function revoke(\DateTimeImmutable $now): void
    {
        if (ClientLinkStatus::REVOKED === $this->status) {
            return;
        }

        $this->status    = ClientLinkStatus::REVOKED;
        $this->revokedAt = $now;

        $this->recordDomainEvent(new ClientLinkRevoked(
            linkId: $this->id->toString(),
            userId: $this->userId,
            clinicId: $this->clinicId,
            clientId: $this->clientId,
        ));
    }

    public function isConfirmed(): bool
    {
        return ClientLinkStatus::CONFIRMED === $this->status;
    }

    public function isPending(): bool
    {
        return ClientLinkStatus::PENDING === $this->status;
    }

    public function id(): ClientLinkId
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

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function status(): ClientLinkStatus
    {
        return $this->status;
    }

    public function linkedAt(): ?\DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** When the owner accepted the link suggestion; null for a link made otherwise. */
    public function requestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    private function recordConfirmed(): void
    {
        $this->recordDomainEvent(new ClientLinkConfirmed(
            linkId: $this->id->toString(),
            userId: $this->userId,
            clinicId: $this->clinicId,
            clientId: $this->clientId,
        ));
    }
}
