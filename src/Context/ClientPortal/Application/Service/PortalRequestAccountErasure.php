<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Command\DeletePortalAccount\AccountErasureStepInterface;
use App\Context\ClientPortal\Application\Port\ErasedOwner;

/** Slice 6's step of the account deletion: the account's requests closed and their free text erased. */
final readonly class PortalRequestAccountErasure implements AccountErasureStepInterface
{
    public function __construct(private PortalRequestCloser $closer)
    {
    }

    public function erase(string $userId, ErasedOwner $owner): void
    {
        $this->closer->eraseAccount($userId);
    }
}
