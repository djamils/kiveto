<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeInvitedUserEmail;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeInvitedUserEmailHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * @throws \InvalidArgumentException a malformed address
     * @throws \DomainException          the address of another clinic account
     */
    public function __invoke(ChangeInvitedUserEmail $command): void
    {
        $email = mb_strtolower(trim($command->email));

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Cette adresse e-mail n’est pas valide.');
        }

        $other = $this->users->findByEmailAndType($email, UserType::CLINIC);

        if (null !== $other && $other->id()->toString() !== $command->userId) {
            throw new \DomainException('Cette adresse est déjà celle d’un autre compte clinique.');
        }

        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \RuntimeException(\sprintf('User "%s" not found.', $command->userId));

        $user->changeInvitedEmail($email);
        $this->users->save($user);
    }
}
