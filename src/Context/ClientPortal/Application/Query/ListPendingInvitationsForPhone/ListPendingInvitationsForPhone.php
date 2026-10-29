<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The invitations a phone (E.164) can still accept — pending and not
 * expired, oldest first. Answers list<PendingInvitationSummary>; a malformed
 * phone has none.
 */
final readonly class ListPendingInvitationsForPhone implements QueryInterface
{
    public function __construct(
        public string $phone,
    ) {
    }
}
