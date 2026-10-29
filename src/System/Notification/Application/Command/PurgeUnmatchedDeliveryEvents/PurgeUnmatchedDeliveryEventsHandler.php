<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeUnmatchedDeliveryEventsHandler
{
    public const int KEPT_HOURS = 24;

    public function __construct(
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DeliveryEventReplayer $replayer,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int how many events were dropped unmatched
     */
    public function __invoke(PurgeUnmatchedDeliveryEvents $command): int
    {
        $before = $this->clock->now()->modify(\sprintf('-%d hours', self::KEPT_HOURS));
        $this->replayer->replay($this->unmatched->findReceivedBefore($before));

        $dropped = 0;
        foreach ($this->unmatched->findReceivedBefore($before) as $event) {
            $this->logger->warning('Delivery event dropped: no message ever carried its provider id.', [
                'channel'    => $event->channel->value,
                'externalId' => $event->externalId,
                'kind'       => $event->event->kind(),
                'receivedAt' => $event->receivedAt->format(\DateTimeInterface::ATOM),
            ]);
            $this->unmatched->delete($event->id);
            ++$dropped;
        }

        return $dropped;
    }
}
