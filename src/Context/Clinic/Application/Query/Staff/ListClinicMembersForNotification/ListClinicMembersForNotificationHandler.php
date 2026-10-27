<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification;

use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicMembersForNotificationHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $memberships,
        private UserAccountStatusInterface $accounts,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string> user ids
     */
    public function __invoke(ListClinicMembersForNotification $query): array
    {
        $members = $this->memberships->findEffectiveUserIds(
            ClinicId::fromString($query->clinicId),
            $this->clock->now(),
            null !== $query->role ? ClinicMemberRole::from($query->role) : null,
            $query->administratorsOnly,
        );

        // An invited member who has not activated their account cannot open the bell.
        return [] === $members ? [] : $this->accounts->activeUserIds($members);
    }
}
