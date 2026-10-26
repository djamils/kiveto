<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\DispatchOutboundMessage;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Placeholder until the transports are wired (Task 2.7 of the notification
 * spec): refuses to run, so that a queued message fails visibly (failure
 * transport) instead of being consumed and lost.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class DispatchOutboundMessageHandler
{
    public function __invoke(DispatchOutboundMessage $command): void
    {
        throw new \LogicException(\sprintf('Message %s cannot be dispatched yet: the notification transports are not wired.', $command->messageId));
    }
}
