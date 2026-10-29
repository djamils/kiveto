<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClientPortalStatus;

use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A confirmed link wins (active). Otherwise the pending invitation shows
 * (invited / expired) — unless it predates the withdrawal of the access, in
 * which case the withdrawal shows (revoked), so that « Réinviter » turns the
 * sheet into « Invité le … ».
 */
#[AsMessageHandler]
final readonly class GetClientPortalStatusHandler
{
    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClientLinkRepositoryInterface $links,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetClientPortalStatus $query): ClientPortalStatus
    {
        $revokedAt = null;

        foreach ($this->links->findByClient($query->clinicId, $query->clientId) as $link) {
            if ($link->isConfirmed()) {
                return new ClientPortalStatus(ClientPortalStatus::ACTIVE, linkedAt: $link->linkedAt());
            }

            $linkRevokedAt = $link->revokedAt();

            if (null !== $linkRevokedAt && (null === $revokedAt || $linkRevokedAt > $revokedAt)) {
                $revokedAt = $linkRevokedAt;
            }
        }

        $invitation = $this->invitations->findLatestForClient($query->clinicId, $query->clientId);

        if (null !== $invitation && PortalInvitationStatus::PENDING === $invitation->status() && (null === $revokedAt || $invitation->sentAt() >= $revokedAt)) {
            return new ClientPortalStatus(
                $invitation->isExpired($this->clock->now()) ? ClientPortalStatus::EXPIRED : ClientPortalStatus::INVITED,
                sentAt: $invitation->sentAt(),
                expiresAt: $invitation->expiresAt(),
            );
        }

        return null !== $revokedAt
            ? new ClientPortalStatus(ClientPortalStatus::REVOKED, revokedAt: $revokedAt)
            : new ClientPortalStatus(ClientPortalStatus::NOT_INVITED);
    }
}
