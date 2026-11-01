<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeletePortalAccount;

use App\Context\ClientPortal\Application\Port\ErasedOwner;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point of DeletePortalAccount: what a later feature must close
 * or erase when an owner account is deleted (slice 6: its requests closed
 * with `account_deleted` and their free text erased; slice 7: its pending
 * booking requests declined without message). Every implementation is run,
 * in the deletion's transaction, after the links and invitations are
 * revoked and before the profile and the account are anonymised. It must
 * be idempotent: the deletion may be retried after any step.
 */
#[AutoconfigureTag]
interface AccountErasureStepInterface
{
    public function erase(string $userId, ErasedOwner $owner): void;
}
