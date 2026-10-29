<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RematchDeliveryEvents;

use App\System\Notification\Application\Command\ReplayDeliveryEvents\ReplayDeliveryEvents;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Only selects: the kept events of each provider id now recorded are
 * replayed by their own `async` job (ReplayDeliveryEvents), so that one
 * failure never rolls back the others. A job queued twice (the next tick
 * runs before the worker) finds nothing left to replay.
 */
#[AsMessageHandler]
final readonly class RematchDeliveryEventsHandler
{
    private const int BATCH = 500;

    public function __construct(
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @return int how many provider ids were queued for a replay
     */
    public function __invoke(RematchDeliveryEvents $command): int
    {
        $ids = [];

        foreach ($this->unmatched->findMatchable(self::BATCH) as $kept) {
            $ids[$kept->channel->value . ' ' . $kept->externalId] = new ReplayDeliveryEvents($kept->channel->value, $kept->externalId);
        }

        foreach ($ids as $replay) {
            $this->commandBus->dispatch($replay);
        }

        return \count($ids);
    }
}
