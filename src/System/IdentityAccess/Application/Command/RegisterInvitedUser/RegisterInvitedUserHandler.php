<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterInvitedUser;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Exception\AccountDisabledException;
use App\System\IdentityAccess\Domain\Exception\EmailUsedByAnotherAccountException;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Unknown address → a PENDING clinic account; a PENDING one (invited by
 * another clinic, or before) is reused; an ACTIVE clinic account simply
 * joins. A portal or back-office account, or a disabled one, is refused.
 */
#[AsMessageHandler]
final readonly class RegisterInvitedUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RegisterInvitedUser $command): InvitedUserRegistration
    {
        $email    = mb_strtolower(mb_trim($command->email));
        $existing = $this->users->findByEmail($email);

        if (null !== $existing) {
            if (UserType::CLINIC !== $existing->type()) {
                throw new EmailUsedByAnotherAccountException();
            }

            return match ($existing->status()) {
                UserStatus::DISABLED => throw new AccountDisabledException(),
                UserStatus::ACTIVE   => new InvitedUserRegistration($existing->id()->toString(), true),
                UserStatus::PENDING  => new InvitedUserRegistration($existing->id()->toString(), false),
            };
        }

        $user = User::invite(UserId::fromString($this->uuids->generate()), $email, $this->clock->now(), UserType::CLINIC);
        $this->users->save($user);
        $this->domainEventPublisher->publish($user);

        return new InvitedUserRegistration($user->id()->toString(), false);
    }
}
