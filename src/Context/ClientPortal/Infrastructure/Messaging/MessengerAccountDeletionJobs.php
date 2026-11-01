<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Port\AccountDeletionJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatched on the raw command bus, routed to `async`: the Doctrine
 * transport writes it in the current transaction (outbox).
 */
final readonly class MessengerAccountDeletionJobs implements AccountDeletionJobsInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function queueDeletion(string $userId): void
    {
        $this->commandBus->dispatch(new DeletePortalAccount($userId));
    }
}
