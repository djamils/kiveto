<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailNotVerifiedException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InactiveUserException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\WrongContextException;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\UserStatus;

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

        $expectedType = $query->context->toUserType();
        if ($user->type() !== $expectedType) {
            throw new WrongContextException($query->context, $user->type());
        }

        if (UserStatus::ACTIVE !== $user->status()) {
            throw new InactiveUserException($user->status());
        }

        if (LoginContext::PORTAL === $query->context && null === $user->emailVerifiedAt()) {
            throw new EmailNotVerifiedException();
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

