<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RejectClientLink;

use App\Context\ClientPortal\Application\Service\LinkDecisionRecorder;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\LinkDecisionOutcome;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Each link is read `FOR UPDATE`; a link no longer pending is left as it
 * is. The files rejected are recorded as one decision.
 */
#[AsMessageHandler]
final readonly class RejectClientLinkHandler
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private LinkDecisionRecorder $decisions,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    public function __invoke(RejectClientLink $command): int
    {
        $now      = $this->clock->now();
        $rejected = [];

        foreach (array_unique($command->clientIds) as $clientId) {
            $link = $this->links->findForUpdate($command->userId, $command->clinicId, $clientId);

            if (null === $link || !$link->isPending()) {
                continue;
            }

            $link->reject($now);
            $this->links->save($link);
            $this->eventPublisher->publish($link);
            $rejected[] = $clientId;
        }

        $this->decisions->record($command->clinicId, $command->userId, LinkDecisionOutcome::REJECTED, $rejected, $now, $command->actorUserId, $command->actorName);

        return \count($rejected);
    }
}
