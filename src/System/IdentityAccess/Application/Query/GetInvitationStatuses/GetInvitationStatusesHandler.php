<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetInvitationStatuses;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetInvitationStatusesHandler
{
    public function __construct(
        private UserReadRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, string> userId → PENDING_VALID | PENDING_EXPIRED | ACTIVE
     */
    public function __invoke(GetInvitationStatuses $query): array
    {
        if ([] === $query->userIds) {
            return [];
        }

        $statuses = $this->users->statuses(array_values(array_unique($query->userIds)));
        $pending  = array_keys(array_filter($statuses, static fn (string $status): bool => UserStatus::PENDING->value === $status));
        $invited  = $this->tokens->usersWithUsableInvitation($query->clinicId, array_map(strval(...), $pending), $this->clock->now());
        $answer   = [];

        foreach ($statuses as $userId => $status) {
            $userId = (string) $userId;

            if (UserStatus::ACTIVE->value === $status) {
                $answer[$userId] = GetInvitationStatuses::ACTIVE;
            } elseif (UserStatus::PENDING->value === $status) {
                $answer[$userId] = \in_array($userId, $invited, true) ? GetInvitationStatuses::PENDING_VALID : GetInvitationStatuses::PENDING_EXPIRED;
            }
        }

        return $answer;
    }
}
