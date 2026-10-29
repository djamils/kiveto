<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;
use App\System\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;

/**
 * Mints the pairs of the Portal API: a fresh family at sign-in, then the
 * successor (or the one grace re-issue) of a presented refresh token.
 */
final readonly class PortalSessionIssuer
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private AccessTokenIssuerInterface $accessTokens,
        private OpaqueTokenHasherInterface $tokenHasher,
        private UuidGeneratorInterface $uuids,
    ) {
    }

    public function open(string $userId, \DateTimeImmutable $now): PortalSessionTokens
    {
        $secret = $this->tokenHasher->newSecret();
        $token  = RefreshToken::issue($this->newId(), RefreshTokenFamilyId::fromString($this->uuids->generate()), $userId, $this->tokenHasher->hash($secret), $now);
        $this->refreshTokens->save($token);

        return $this->pair($token, $secret);
    }

    public function rotate(RefreshToken $presented, \DateTimeImmutable $now): PortalSessionTokens
    {
        $secret    = $this->tokenHasher->newSecret();
        $successor = $presented->rotate($this->newId(), $this->tokenHasher->hash($secret), $now);

        return $this->store($presented, $successor, $secret);
    }

    public function reissue(RefreshToken $presented, \DateTimeImmutable $now): PortalSessionTokens
    {
        $secret    = $this->tokenHasher->newSecret();
        $successor = $presented->reissue($this->newId(), $this->tokenHasher->hash($secret), $now);

        return $this->store($presented, $successor, $secret);
    }

    private function store(RefreshToken $presented, RefreshToken $successor, string $secret): PortalSessionTokens
    {
        $this->refreshTokens->save($successor);
        $this->refreshTokens->save($presented);

        return $this->pair($successor, $secret);
    }

    private function pair(RefreshToken $token, string $secret): PortalSessionTokens
    {
        $access = $this->accessTokens->issue($token->userId());

        return new PortalSessionTokens($access->token, $access->expiresAt, $secret, $token->expiresAt());
    }

    private function newId(): RefreshTokenId
    {
        return RefreshTokenId::fromString($this->uuids->generate());
    }
}
