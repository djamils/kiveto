<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\ClientPortal\Application\Command\BuildDataExport\BuildDataExport;
use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFile;
use App\Context\ClientPortal\Application\Port\DataExportJobsInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * The build renders a PDF (Gotenberg), so it goes to the external API
 * queue; the deletion is dispatched on the raw command bus (routed to
 * `async`) with a 48-hour delay. Both Doctrine transports write in the
 * current transaction (outbox).
 */
final readonly class MessengerDataExportJobs implements DataExportJobsInterface
{
    /** The 48 hours of DataExport::RETENTION. */
    private const int DELETE_DELAY_MS = 172_800_000;

    public function __construct(
        private ExternalApiCommandBusInterface $externalBus,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function queueBuild(string $exportId): void
    {
        $this->externalBus->send(new BuildDataExport($exportId));
    }

    public function deleteFileLater(string $exportId): void
    {
        $this->commandBus->dispatch(new DeleteDataExportFile($exportId), [new DelayStamp(self::DELETE_DELAY_MS)]);
    }
}
