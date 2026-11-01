<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ConfirmClientLink;

use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Each link is read `FOR UPDATE`; a link no longer pending (confirmed,
 * rejected by a colleague, revoked with its client) is left as it is.
 * `ClientLinkConfirmed` releases the documents waiting for the file.
 */
#[AsMessageHandler]
final readonly class ConfirmClientLinkHandler
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    public function __invoke(ConfirmClientLink $command): int
    {
        $now       = $this->clock->now();
        $confirmed = 0;

        foreach (array_unique($command->clientIds) as $clientId) {
            $link = $this->links->findForUpdate($command->userId, $command->clinicId, $clientId);

            if (null === $link || !$link->isPending()) {
                continue;
            }

            $link->confirmPending($now);
            $this->links->save($link);
            $this->eventPublisher->publish($link);
            ++$confirmed;
        }

        return $confirmed;
    }
}
