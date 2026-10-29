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
 * The account is created before the clinic's membership, in a transaction
 * of its own: when the onboarding is then refused, a PENDING account without
 * a link stays behind — harmless, the next invitation of that address
 * reuses it.
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

        if (null === $existing) {
            $user = User::invite(UserId::fromString($this->uuids->generate()), $email, $this->clock->now(), UserType::CLINIC);

            if ($this->users->addIfEmailFree($user)) {
                $this->domainEventPublisher->publish($user);

                return new InvitedUserRegistration($user->id()->toString(), false);
            }

            // Two administrators invited the same address at once: the other
            // invitation created the account, this one joins it.
            $existing = $this->users->findByEmailForUpdate($email) ?? throw new \LogicException(\sprintf('The account of "%s" vanished while being invited.', $email));
        }

        if (UserType::CLINIC !== $existing->type()) {
            throw new EmailUsedByAnotherAccountException();
        }

        return match ($existing->status()) {
            UserStatus::DISABLED => throw new AccountDisabledException(),
            UserStatus::ACTIVE   => new InvitedUserRegistration($existing->id()->toString(), true),
            UserStatus::PENDING  => new InvitedUserRegistration($existing->id()->toString(), false),
        };
    }
}
