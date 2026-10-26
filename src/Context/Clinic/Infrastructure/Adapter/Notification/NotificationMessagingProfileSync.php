<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\Notification;

use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;

/**
 * Hands Notification its read-only copy of what it needs about a clinic,
 * through Notification's public commands.
 */
final readonly class NotificationMessagingProfileSync implements MessagingProfileSyncInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function pushVeterinarianCount(string $clinicId, string $day, int $count): void
    {
        $this->commandBus->dispatch(new RecordVeterinarianCount($clinicId, $day, $count));
    }
}
