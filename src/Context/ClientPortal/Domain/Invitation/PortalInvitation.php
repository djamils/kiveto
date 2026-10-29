<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Invitation;

use App\Context\ClientPortal\Domain\Event\PortalInvitationAccepted;
use App\Context\ClientPortal\Domain\Event\PortalInvitationSent;
use App\Context\ClientPortal\Domain\Exception\ClientNotInvitableException;
use App\Context\ClientPortal\Domain\Exception\InvitationAlreadyUsedException;
use App\Context\ClientPortal\Domain\Exception\InvitationExpiredException;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\PhoneNumber;

/**
 * A clinic's invitation of one of its clients to the Portal. It owns its
 * link token (only the digest is kept) and is bound to the client's mobile:
 * the phone that must be proven to accept it. It lives 7 days; a resend
 * keeps the same id with a new token and a new expiry.
 */
final class PortalInvitation extends AggregateRoot
{
    private const string LIFETIME = 'P7D';

    private PortalInvitationId $id;
    private string $clinicId;
    private string $clientId;
    private PhoneNumber $phone;
    private ?string $email;
    private InvitationChannel $channel;
    private string $tokenHash;
    private PortalInvitationStatus $status;
    private \DateTimeImmutable $sentAt;
    private \DateTimeImmutable $expiresAt;
    private ?\DateTimeImmutable $acceptedAt;
    private ?string $acceptedByUserId;
    private ?\DateTimeImmutable $revokedAt;

    private function __construct()
    {
    }

    /**
     * @throws ClientNotInvitableException e-mail channel without e-mail
     */
    public static function send(
        PortalInvitationId $id,
        string $clinicId,
        string $clientId,
        PhoneNumber $phone,
        ?string $email,
        InvitationChannel $channel,
        string $tokenHash,
        \DateTimeImmutable $now,
    ): self {
        self::assertReachable($channel, $email);

        $invitation                   = new self();
        $invitation->id               = $id;
        $invitation->clinicId         = $clinicId;
        $invitation->clientId         = $clientId;
        $invitation->phone            = $phone;
        $invitation->email            = $email;
        $invitation->channel          = $channel;
        $invitation->tokenHash        = $tokenHash;
        $invitation->status           = PortalInvitationStatus::PENDING;
        $invitation->sentAt           = $now;
        $invitation->expiresAt        = $now->add(new \DateInterval(self::LIFETIME));
        $invitation->acceptedAt       = null;
        $invitation->acceptedByUserId = null;
        $invitation->revokedAt        = null;

        $invitation->recordSent(false);

        return $invitation;
    }

    public static function reconstitute(
        PortalInvitationId $id,
        string $clinicId,
        string $clientId,
        PhoneNumber $phone,
        ?string $email,
        InvitationChannel $channel,
        string $tokenHash,
        PortalInvitationStatus $status,
        \DateTimeImmutable $sentAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $acceptedAt,
        ?string $acceptedByUserId,
        ?\DateTimeImmutable $revokedAt,
    ): self {
        $invitation                   = new self();
        $invitation->id               = $id;
        $invitation->clinicId         = $clinicId;
        $invitation->clientId         = $clientId;
        $invitation->phone            = $phone;
        $invitation->email            = $email;
        $invitation->channel          = $channel;
        $invitation->tokenHash        = $tokenHash;
        $invitation->status           = $status;
        $invitation->sentAt           = $sentAt;
        $invitation->expiresAt        = $expiresAt;
        $invitation->acceptedAt       = $acceptedAt;
        $invitation->acceptedByUserId = $acceptedByUserId;
        $invitation->revokedAt        = $revokedAt;

        return $invitation;
    }

    /**
     * « Renvoyer l'invitation »: same invitation, new token (the old one is
     * dead), new 7-day expiry, sent to the client's current addresses. Codes
     * and grants bound to the invitation id stay valid.
     *
     * @throws InvitationAlreadyUsedException accepted or revoked
     * @throws ClientNotInvitableException    e-mail channel without e-mail
     */
    public function reissue(
        string $newTokenHash,
        InvitationChannel $channel,
        PhoneNumber $phone,
        ?string $email,
        \DateTimeImmutable $now,
    ): void {
        if (PortalInvitationStatus::PENDING !== $this->status) {
            throw new InvitationAlreadyUsedException($this->id->toString());
        }

        self::assertReachable($channel, $email);

        $this->tokenHash = $newTokenHash;
        $this->channel   = $channel;
        $this->phone     = $phone;
        $this->email     = $email;
        $this->sentAt    = $now;
        $this->expiresAt = $now->add(new \DateInterval(self::LIFETIME));

        $this->recordSent(true);
    }

    /**
     * @throws InvitationAlreadyUsedException accepted or revoked
     * @throws InvitationExpiredException     pending but past its expiry
     */
    public function accept(string $userId, \DateTimeImmutable $now): void
    {
        if (PortalInvitationStatus::PENDING !== $this->status) {
            throw new InvitationAlreadyUsedException($this->id->toString());
        }

        if ($this->isExpired($now)) {
            throw new InvitationExpiredException($this->id->toString());
        }

        $this->status           = PortalInvitationStatus::ACCEPTED;
        $this->acceptedAt       = $now;
        $this->acceptedByUserId = $userId;

        $this->recordDomainEvent(new PortalInvitationAccepted(
            invitationId: $this->id->toString(),
            clinicId: $this->clinicId,
            clientId: $this->clientId,
            userId: $userId,
        ));
    }

    /**
     * Withdraws a pending invitation (expired or not); its token, codes and
     * grants can no longer accept it.
     *
     * @throws InvitationAlreadyUsedException accepted or revoked
     */
    public function revoke(\DateTimeImmutable $now): void
    {
        if (PortalInvitationStatus::PENDING !== $this->status) {
            throw new InvitationAlreadyUsedException($this->id->toString());
        }

        $this->status    = PortalInvitationStatus::REVOKED;
        $this->revokedAt = $now;
    }

    /** Pending and past its expiry (accepted or revoked ones are not « expired »). */
    public function isExpired(\DateTimeImmutable $now): bool
    {
        return PortalInvitationStatus::PENDING === $this->status && $now >= $this->expiresAt;
    }

    /** Pending and not expired: it can still be accepted. */
    public function isOpen(\DateTimeImmutable $now): bool
    {
        return PortalInvitationStatus::PENDING === $this->status && $now < $this->expiresAt;
    }

    public function id(): PortalInvitationId
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

    public function phone(): PhoneNumber
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function channel(): InvitationChannel
    {
        return $this->channel;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function status(): PortalInvitationStatus
    {
        return $this->status;
    }

    public function sentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function acceptedByUserId(): ?string
    {
        return $this->acceptedByUserId;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    private static function assertReachable(InvitationChannel $channel, ?string $email): void
    {
        if (InvitationChannel::EMAIL === $channel && (null === $email || '' === mb_trim($email))) {
            throw new ClientNotInvitableException(ClientNotInvitableException::NO_EMAIL);
        }
    }

    private function recordSent(bool $reissued): void
    {
        $this->recordDomainEvent(new PortalInvitationSent(
            invitationId: $this->id->toString(),
            clinicId: $this->clinicId,
            clientId: $this->clientId,
            channel: $this->channel->value,
            reissued: $reissued,
            expiresAt: $this->expiresAt->format(\DATE_ATOM),
        ));
    }
}
