<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess;

use App\Context\ClientPortal\Application\Service\PortalRequestCloser;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeClientPortalAccessHandler
{
    public function __construct(
        private PortalInvitationRepositoryInterface $invitations,
        private ClientLinkRepositoryInterface $links,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
        private PortalRequestCloser $requests,
    ) {
    }

    public function __invoke(RevokeClientPortalAccess $command): void
    {
        $now = $this->clock->now();

        foreach ($this->invitations->findPendingForClientForUpdate($command->clinicId, $command->clientId) as $invitation) {
            $invitation->revoke($now);
            $this->invitations->save($invitation);
        }

        foreach ($this->links->findByClient($command->clinicId, $command->clientId) as $link) {
            $link->revoke($now);
            $this->links->save($link);
            $this->eventPublisher->publish($link);
        }

        // Every link of the client is gone: the requests its owners left open
        // are closed silently (`link_revoked`), never notified
        $this->requests->close(RequestSubject::CLIENT, $command->clientId, ClosureReason::LINK_REVOKED, $command->clinicId);
    }
}
