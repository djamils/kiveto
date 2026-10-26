<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordDispatchOutcome;

use App\Shared\Application\Event\DomainEventPublisher;
use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Marks the message SENT with the provider id, or FAILED with the reason.
 * Only a QUEUED message moves: a replayed outcome changes nothing.
 *
 * Once the provider id is known, the provider events that arrived before it
 * (kept as unmatched) are applied and dropped.
 */
#[AsMessageHandler]
final readonly class RecordDispatchOutcomeHandler
{
    public const string DEFAULT_ERROR = "Échec de l'envoi";

    public function __construct(
        private OutboundMessageRepositoryInterface $messages,
        private UnmatchedDeliveryEventRepositoryInterface $unmatched,
        private DeliveryEventReplayer $replayer,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RecordDispatchOutcome $command): void
    {
        $message    = $this->messages->get(OutboundMessageId::fromString($command->messageId));
        $occurredAt = new \DateTimeImmutable($command->occurredAt);
        $externalId = $command->externalId;

        $changed = null !== $externalId
            ? $message->markSent($externalId, $occurredAt)
            : $message->markFailed($command->error ?? self::DEFAULT_ERROR, $occurredAt);

        if (!$changed) {
            return;
        }

        $this->messages->save($message);
        $this->domainEventPublisher->publish($message);

        if (null !== $message->externalMessageId()) {
            $this->replayer->replay($this->unmatched->findFor($message->channel(), $message->externalMessageId()));
        }
    }
}
