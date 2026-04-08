<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\AuthenticateUser;

use App\System\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\AccountStatusNotAllowedException;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationContextMismatchException;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailVerificationRequiredException;
use App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AuthenticateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHashVerifierInterface $passwordVerifier,
    ) {
    }

    public function __invoke(AuthenticateUserQuery $query): AuthenticatedUser
    {
        $user = $this->userRepository->findByEmail($query->email);
        if (null === $user) {
            throw new InvalidCredentialsException();
        }

        $expectedType = $query->context->allowedUserType();
        if ($user->type() !== $expectedType) {
            throw new AuthenticationContextMismatchException($query->context, $user->type());
        }

        if (UserStatus::ACTIVE !== $user->status()) {
            throw new AccountStatusNotAllowedException($user->status());
        }

        if (AuthenticationContext::PORTAL === $query->context && null === $user->emailVerifiedAt()) {
            throw new EmailVerificationRequiredException();
        }

        if (!$this->passwordVerifier->verify($query->plainPassword, $user->passwordHash())) {
            throw new InvalidCredentialsException();
        }

        return new AuthenticatedUser(
            id: $user->id()->toString(),
            email: $user->email(),
            type: $user->type(),
            roles: ['ROLE_USER'],
        );
    }
}
