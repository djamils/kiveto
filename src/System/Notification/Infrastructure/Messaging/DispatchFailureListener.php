<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Messaging;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * When a dispatch has failed for the last time (retries exhausted, or a
 * failure Messenger does not retry), the message is recorded FAILED — it
 * would otherwise stay « En cours » in the journals forever.
 */
#[AsEventListener]
final readonly class DispatchFailureListener
{
    public const string RETRIES_EXHAUSTED = 'Échec après plusieurs tentatives';

    public function __construct(
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $command = $event->getEnvelope()->getMessage();

        if (!$command instanceof DispatchOutboundMessage || $event->willRetry()) {
            return;
        }

        $this->commandBus->dispatch(new RecordDispatchOutcome($command->messageId, null, self::RETRIES_EXHAUSTED, $this->clock->now()->format(\DateTimeInterface::ATOM)));
    }
}
