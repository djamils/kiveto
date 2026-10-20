<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\EventSubscriber;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions\ReleasePendingTransmissions;
use App\System\ElectronicInvoicing\Domain\Event\PlatformConnectionActivated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A connection that becomes active releases what its organisation waited to send.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnPlatformConnectionActivatedRelease
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PlatformConnectionActivated $event): void
    {
        $this->commandBus->dispatch(new ReleasePendingTransmissions($event->organisationId));
    }
}
