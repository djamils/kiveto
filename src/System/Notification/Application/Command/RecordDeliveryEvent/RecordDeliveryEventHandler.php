<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordDeliveryEvent;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Applies a provider event to the message carrying that provider id, with
 * the status precedence (duplicates and late events change nothing). An id
 * not recorded yet — the webhook beat the dispatch outcome — is kept as an
 * unmatched event, replayed as soon as the id is recorded.
 */
#[AsMessageHandler]
final readonly class RecordDeliveryEventHandler
{
    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecordDeliveryEvent $command): void
    {
        $channel = Channel::from($command->channel);
        $event   = DeliveryEvent::ofKind($command->kind, new \DateTimeImmutable($command->occurredAt), $command->reason);
        $message = $this->messages->findByExternalId($channel, $command->externalId);

        if (null === $message) {
            $this->unmatched->keep(new UnmatchedDeliveryEvent($this->uuids->generate(), $channel, $command->externalId, $event, $this->clock->now()));
            $this->logger->info('Delivery event kept: no message carries that provider id yet.', ['channel' => $channel->value, 'externalId' => $command->externalId, 'kind' => $event->kind()]);

            return;
        }

        if ($message->recordProviderEvent($event)) {
            $this->messages->save($message);
            $this->domainEventPublisher->publish($message);
        }
    }
}
