<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFile;
use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFile;
use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * The rendering calls Gotenberg, so it goes to the external API queue; the
 * delayed deletion is dispatched on the raw command bus (routed to `async`),
 * which the application command bus would not allow without a handled result.
 * Both Doctrine transports write in the current transaction (outbox).
 */
final readonly class MessengerDocumentFileJobs implements DocumentFileJobsInterface
{
    /** Ten minutes, so that a URL signed before the replacement still serves the old file. */
    private const int DELETE_DELAY_MS = 600_000;

    public function __construct(
        private ExternalApiCommandBusInterface $externalBus,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function storeFile(string $documentId, int $version): void
    {
        $this->externalBus->send(new StorePublishedDocumentFile($documentId, $version));
    }

    public function deleteFileLater(string $storageKey): void
    {
        $this->commandBus->dispatch(new DeleteStoredDocumentFile($storageKey), [new DelayStamp(self::DELETE_DELAY_MS)]);
    }
}
