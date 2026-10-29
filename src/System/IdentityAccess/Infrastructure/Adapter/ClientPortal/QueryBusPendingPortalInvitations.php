<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Adapter\ClientPortal;

use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\ListPendingInvitationsForPhone;
use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\PendingInvitationSummary;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitation;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitationsInterface;

final readonly class QueryBusPendingPortalInvitations implements PendingPortalInvitationsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function listForPhone(PhoneNumber $phone): array
    {
        $summaries = $this->queryBus->ask(new ListPendingInvitationsForPhone($phone->toString()));
        \assert(\is_array($summaries));

        $invitations = [];
        foreach ($summaries as $summary) {
            \assert($summary instanceof PendingInvitationSummary);
            $invitations[] = new PendingPortalInvitation($summary->invitationId, $summary->clinicName, $summary->city);
        }

        return $invitations;
    }
}
