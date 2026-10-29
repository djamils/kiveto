<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Security;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\PortalAccount;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads the owner of an access token by its `sub`: only an active owner
 * (PORTAL) account is accepted — a staff id, a disabled account or anything
 * else fails like an invalid token. The API is stateless: nothing is
 * refreshed from a session.
 *
 * @implements UserProviderInterface<PortalApiUser>
 */
final readonly class PortalApiUserProvider implements UserProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $account = $this->queryBus->ask(new GetPortalAccount($identifier));

        if (!$account instanceof PortalAccount) {
            throw new UserNotFoundException('Unknown or inactive owner account.');
        }

        return new PortalApiUser($account->userId);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('The Portal API is stateless.');
    }

    public function supportsClass(string $class): bool
    {
        return PortalApiUser::class === $class;
    }
}
