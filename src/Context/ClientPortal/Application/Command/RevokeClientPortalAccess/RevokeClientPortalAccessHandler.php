<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess;

use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
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
    }
}
