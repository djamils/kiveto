<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Messaging;

use App\Context\Billing\Application\Command\SendInvoiceEmail\SendInvoiceEmail;
use App\Context\Billing\Application\Port\DispatchJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The commands are routed to `async` (messenger.yaml) and dispatched on the
 * raw command bus — the application command bus expects a handled result.
 * The Doctrine transport writes them in the current transaction (outbox):
 * no e-mail is queued for a dispatch log that is rolled back.
 */
final readonly class MessengerDispatchJobs implements DispatchJobsInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function sendInvoiceEmail(string $dispatchId): void
    {
        $this->commandBus->dispatch(new SendInvoiceEmail($dispatchId));
    }
}
