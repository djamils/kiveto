<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetLatestInvitation;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetLatestInvitationHandler
{
    public function __construct(
        private AccountTokenRepositoryInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetLatestInvitation $query): ?InvitationView
    {
        $token = $this->tokens->latestUnusedInvitation($query->userId);

        if (null === $token) {
            return null;
        }

        \assert(null !== $token->clinicId());

        return new InvitationView(
            clinicId: $token->clinicId(),
            sentAt: $token->createdAt(),
            expiresAt: $token->expiresAt(),
            expired: !$token->isUsable($this->clock->now()),
        );
    }
}
