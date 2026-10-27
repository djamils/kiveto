<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPasswordReset;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestPasswordResetHandler
{
    private const string LABEL_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private UserRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private AccountLinkBuilderInterface $links,
        private NotificationRequesterInterface $notifications,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        $found = $this->users->findByEmailAndType(mb_strtolower(mb_trim($command->email)), UserType::CLINIC);
        // Locked: two requests at once leave one live link, not two.
        $user = null !== $found ? $this->users->findByIdForUpdate($found->id()) : null;

        if (null === $user || UserStatus::ACTIVE !== $user->status()) {
            return;
        }

        $now    = $this->clock->now();
        $userId = $user->id()->toString();
        $this->tokens->invalidatePasswordResets($userId, $now);

        $secret = AccountToken::newSecret();
        $token  = AccountToken::issue(AccountTokenId::fromString($this->uuids->generate()), $userId, TokenPurpose::PASSWORD_RESET, null, $secret, $now);
        $this->tokens->save($token);

        $this->notifications->requestPasswordReset(new PasswordResetRequest(
            tokenId: $token->id()->toString(),
            userId: $userId,
            email: $user->email(),
            resetUrl: $this->links->passwordResetUrl($secret),
            requestedAtLabel: $now->setTimezone(new \DateTimeZone(self::LABEL_TIME_ZONE))->format('d/m/Y \à H:i'),
        ));
    }
}
