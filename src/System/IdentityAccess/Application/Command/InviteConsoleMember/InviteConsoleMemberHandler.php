<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\InviteConsoleMember;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class InviteConsoleMemberHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AccountTokenRepositoryInterface $tokens,
        private BackofficeProfileStoreInterface $profiles,
        private OpaqueTokenHasherInterface $tokenHasher,
        private AccountLinkBuilderInterface $links,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @throws \DomainException no address, an unknown role, or an account of the console already in use
     */
    public function __invoke(InviteConsoleMember $command): ConsoleInvitation
    {
        $email = mb_strtolower(mb_trim($command->email));
        $role  = ConsoleRole::tryFrom($command->role) ?? throw new \DomainException('Choisis un rôle : administrateur ou support.');

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('Adresse e-mail invalide.');
        }

        $now  = $this->clock->now();
        $user = $this->users->findByEmailAndTypeForUpdate($email, UserType::BACKOFFICE);

        if (null === $user) {
            $user = User::invite(UserId::fromString($this->uuids->generate()), $email, $now, UserType::BACKOFFICE);
            $this->users->save($user);
            $this->domainEventPublisher->publish($user);
        } elseif (UserStatus::PENDING !== $user->status()) {
            throw new \DomainException(UserStatus::DISABLED === $user->status()
                ? 'Ce membre est désactivé : réactive-le depuis l’équipe.'
                : 'Cette adresse a déjà un compte console.');
        }

        $userId = $user->id()->toString();
        $name   = mb_trim($command->displayName);
        $this->profiles->save($userId, '' !== $name ? mb_substr($name, 0, 80) : BackofficeProfileView::nameFromEmail($email), BackofficeProfileView::DEFAULT_TIME_ZONE, true, true, $now);
        $this->profiles->saveRole($userId, $role, $now);

        // One live link at a time.
        $this->tokens->invalidateConsoleInvitations($userId, $now);
        $secret = $this->tokenHasher->newSecret();
        $token  = AccountToken::issue(AccountTokenId::fromString($this->uuids->generate()), $userId, TokenPurpose::CONSOLE_INVITATION, null, $this->tokenHasher->hash($secret), $now);
        $this->tokens->save($token);

        return new ConsoleInvitation($userId, $email, $this->links->consoleInvitationUrl($secret), $token->expiresAt());
    }
}
