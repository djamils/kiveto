<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RematchDeliveryEvents;

use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RematchDeliveryEventsHandler
{
    private const int BATCH = 500;

    public function __construct(
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DeliveryEventReplayer $replayer,
    ) {
    }

    /**
     * @return int how many kept events found their message
     */
    public function __invoke(RematchDeliveryEvents $command): int
    {
        return $this->replayer->replay($this->unmatched->findMatchable(self::BATCH));
    }
}
