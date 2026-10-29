<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\ReplayDeliveryEvents;

use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReplayDeliveryEventsHandler
{
    public function __construct(
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DeliveryEventReplayer $replayer,
    ) {
    }

    /**
     * @return int how many kept events found their message
     */
    public function __invoke(ReplayDeliveryEvents $command): int
    {
        return $this->replayer->replay($this->unmatched->findFor(Channel::from($command->channel), $command->externalId));
    }
}
