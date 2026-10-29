<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkRevoked;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * An owner account (PORTAL user) reaching one client file of one clinic.
 * One link per (user, clinic, client): a revoked link is reactivated, never
 * duplicated. Only a confirmed link gives access to the clinic's data.
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

    public static function reconstitute(
        ClientLinkId $id,
        string $userId,
        string $clinicId,
        string $clientId,
        ClientLinkStatus $status,
        ?\DateTimeImmutable $linkedAt,
        ?\DateTimeImmutable $revokedAt,
    ): self {
        $link            = new self();
        $link->id        = $id;
        $link->userId    = $userId;
        $link->clinicId  = $clinicId;
        $link->clientId  = $clientId;
        $link->status    = $status;
        $link->linkedAt  = $linkedAt;
        $link->revokedAt = $revokedAt;

        return $link;
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
