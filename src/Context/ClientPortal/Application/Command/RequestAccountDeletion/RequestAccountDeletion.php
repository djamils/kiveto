<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestAccountDeletion;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner confirms the deletion of their account with the SMS code of
 * purpose `account_deletion`. Answers a DeletionCodeCheck: a wrong code
 * changes nothing but its attempt (committed). A right one disables the
 * account and revokes its refresh tokens at once, then queues the erasure
 * (DeletePortalAccount), all in this transaction.
 */
final readonly class RequestAccountDeletion implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $challengeId,
        public string $code,
    ) {
    }
}
