<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Jwt;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\System\IdentityAccess\Application\Port\IssuedAccessToken;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * lexik signs the token: `sub` = the owner's id (the identifier of a PORTAL
 * SecurityUser), `typ` = portal, lifetime = lexik's token_ttl.
 */
final readonly class LexikAccessTokenIssuer implements AccessTokenIssuerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $tokens,
        private ClockInterface $clock,
        #[Autowire('%lexik_jwt_authentication.token_ttl%')]
        private int $ttl,
    ) {
    }

    public function issue(string $userId): IssuedAccessToken
    {
        $expiresAt = $this->clock->now()->modify(\sprintf('+%d seconds', $this->ttl));
        $token     = $this->tokens->createFromPayload(new SecurityUser($userId, null, UserType::PORTAL), ['typ' => 'portal']);

        return new IssuedAccessToken($token, $expiresAt);
    }
}
