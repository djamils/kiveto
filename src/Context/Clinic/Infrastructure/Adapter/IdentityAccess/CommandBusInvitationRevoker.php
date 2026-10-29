<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\InvitationRevokerInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RevokeInvitation\RevokeInvitation;

final readonly class CommandBusInvitationRevoker implements InvitationRevokerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function revoke(string $userId, string $clinicId): void
    {
        $this->commandBus->dispatch(new RevokeInvitation($userId, $clinicId));
    }
}
