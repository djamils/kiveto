<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Service;

use App\Shared\Application\Event\DomainEventPublisher;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;

/**
 * Applies kept provider events to the message that now carries their
 * provider id (status precedence included) and drops them. An event whose
 * id is still unknown stays kept.
 */
final readonly class DeliveryEventReplayer
{
    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @param list<UnmatchedDeliveryEvent> $events
     *
     * @return int how many events found their message
     */
    public function replay(array $events): int
    {
        $matched = 0;

        foreach ($events as $kept) {
            $message = $this->messages->findByExternalId($kept->channel, $kept->externalId);

            if (null === $message) {
                continue;
            }

            if ($message->recordProviderEvent($kept->event)) {
                $this->messages->save($message);
                $this->domainEventPublisher->publish($message);
            }

            $this->unmatched->delete($kept->id);
            ++$matched;
        }

        return $matched;
    }
}
