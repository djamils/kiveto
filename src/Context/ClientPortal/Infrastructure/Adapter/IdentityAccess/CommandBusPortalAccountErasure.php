<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess;

use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Port\PortalAccountErasureInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\AnonymisePortalUser\AnonymisePortalUser;
use App\System\IdentityAccess\Application\Command\DisablePortalUser\DisablePortalUser;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgedPortalIdentity;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgePortalPhoneTraces;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\VerifyPortalActionOtp;

/**
 * IdentityAccess commands, dispatched inside the caller's transaction (the
 * command bus nests).
 */
final readonly class CommandBusPortalAccountErasure implements PortalAccountErasureInterface
{
    private const string PURPOSE = 'account_deletion';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function verifyDeletionCode(string $userId, string $challengeId, string $code): DeletionCodeCheck
    {
        $result = $this->commandBus->dispatch(new VerifyPortalActionOtp($userId, $challengeId, $code, self::PURPOSE));
        \assert($result instanceof ActionOtpResult);

        return DeletionCodeCheck::from($result->value);
    }

    public function disable(string $userId): void
    {
        $this->commandBus->dispatch(new DisablePortalUser($userId));
    }

    public function purgeTraces(string $userId): ErasedOwner
    {
        $purged = $this->commandBus->dispatch(new PurgePortalPhoneTraces($userId));
        \assert($purged instanceof PurgedPortalIdentity);

        return new ErasedOwner($purged->phone, $purged->email);
    }

    public function anonymise(string $userId): void
    {
        $this->commandBus->dispatch(new AnonymisePortalUser($userId));
    }
}
