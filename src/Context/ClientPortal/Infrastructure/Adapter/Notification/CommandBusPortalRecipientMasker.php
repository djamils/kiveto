<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Notification;

use App\Context\ClientPortal\Application\Port\PortalRecipientMaskerInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipient;

/** Notification's MaskPortalRecipient, in the caller's transaction. */
final readonly class CommandBusPortalRecipientMasker implements PortalRecipientMaskerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function mask(string $phone, ?string $email): void
    {
        $this->commandBus->dispatch(new MaskPortalRecipient($phone, $email));
    }
}
