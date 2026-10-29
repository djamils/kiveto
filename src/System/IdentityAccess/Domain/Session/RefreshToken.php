<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Session;

/**
 * An opaque refresh token of the Portal API; only its digest is stored. Each
 * use replaces it by a successor of the same family. Presented again within
 * 30 s while its successor is unused, it yields one extra pair (a lost
 * response); any other replay is treated as theft and revokes the family.
 * A token not used for 90 days expires.
 */
final class RefreshToken
{
    private const string LIFETIME     = 'P90D';
    private const string GRACE_WINDOW = 'PT30S';

    private RefreshTokenId $id;
    private RefreshTokenFamilyId $familyId;
    private string $userId;
    private string $tokenHash;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastUsedAt;
    private \DateTimeImmutable $expiresAt;
    private ?\DateTimeImmutable $rotatedAt;
    private ?RefreshTokenId $rotatedToId;
    private ?RefreshTokenId $graceSuccessorId;
    private ?\DateTimeImmutable $revokedAt;

    private function __construct()
    {
    }

    public static function issue(
        RefreshTokenId $id,
        RefreshTokenFamilyId $familyId,
        string $userId,
        string $tokenHash,
        \DateTimeImmutable $now,
    ): self {
        $token                   = new self();
        $token->id               = $id;
        $token->familyId         = $familyId;
        $token->userId           = $userId;
        $token->tokenHash        = $tokenHash;
        $token->createdAt        = $now;
        $token->lastUsedAt       = null;
        $token->expiresAt        = $now->add(new \DateInterval(self::LIFETIME));
        $token->rotatedAt        = null;
        $token->rotatedToId      = null;
        $token->graceSuccessorId = null;
        $token->revokedAt        = null;

        return $token;
    }

    public static function reconstitute(
        RefreshTokenId $id,
        RefreshTokenFamilyId $familyId,
        string $userId,
        string $tokenHash,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $lastUsedAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $rotatedAt,
        ?RefreshTokenId $rotatedToId,
        ?RefreshTokenId $graceSuccessorId,
        ?\DateTimeImmutable $revokedAt,
    ): self {
        $token                   = new self();
        $token->id               = $id;
        $token->familyId         = $familyId;
        $token->userId           = $userId;
        $token->tokenHash        = $tokenHash;
        $token->createdAt        = $createdAt;
        $token->lastUsedAt       = $lastUsedAt;
        $token->expiresAt        = $expiresAt;
        $token->rotatedAt        = $rotatedAt;
        $token->rotatedToId      = $rotatedToId;
        $token->graceSuccessorId = $graceSuccessorId;
        $token->revokedAt        = $revokedAt;

        return $token;
    }

    /** The successor of this token, in the same family. */
    public function rotate(RefreshTokenId $successorId, string $successorHash, \DateTimeImmutable $now): self
    {
        if (null !== $this->rotatedAt) {
            throw new \LogicException('A refresh token is rotated once.');
        }

        $this->rotatedAt   = $now;
        $this->rotatedToId = $successorId;
        $this->lastUsedAt  = $now;

        return self::issue($successorId, $this->familyId, $this->userId, $successorHash, $now);
    }

    /**
     * The one extra pair of a replay within the grace window; the caller has
     * checked canBeReissued().
     */
    public function reissue(RefreshTokenId $successorId, string $successorHash, \DateTimeImmutable $now): self
    {
        if (null !== $this->graceSuccessorId) {
            throw new \LogicException('A refresh token is re-issued once.');
        }

        $this->graceSuccessorId = $successorId;
        $this->lastUsedAt       = $now;

        return self::issue($successorId, $this->familyId, $this->userId, $successorHash, $now);
    }

    /**
     * Rotated less than 30 s ago, never re-issued, and its successor still
     * unused: the client most probably lost the response.
     */
    public function canBeReissued(\DateTimeImmutable $now, bool $successorUsed): bool
    {
        return null !== $this->rotatedAt
            && $now < $this->rotatedAt->add(new \DateInterval(self::GRACE_WINDOW))
            && null === $this->graceSuccessorId
            && !$successorUsed;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isRotated(): bool
    {
        return null !== $this->rotatedAt;
    }

    /** Presented at least once (rotated or re-issued). */
    public function isUsed(): bool
    {
        return null !== $this->lastUsedAt;
    }

    public function id(): RefreshTokenId
    {
        return $this->id;
    }

    public function familyId(): RefreshTokenFamilyId
    {
        return $this->familyId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function rotatedAt(): ?\DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function rotatedToId(): ?RefreshTokenId
    {
        return $this->rotatedToId;
    }

    public function graceSuccessorId(): ?RefreshTokenId
    {
        return $this->graceSuccessorId;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
