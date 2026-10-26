<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Token;

/**
 * A single-use link sent by e-mail (invitation, password reset). Only the
 * sha256 of its secret is kept: whoever reads the table cannot use a link.
 * An invitation is scoped to (clinic, user) — two clinics inviting the same
 * person never cancel each other.
 */
final class AccountToken
{
    private function __construct(
        private readonly AccountTokenId $id,
        private readonly string $userId,
        private readonly TokenPurpose $purpose,
        private readonly ?string $clinicId,
        private readonly string $tokenHash,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $usedAt,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /** A fresh secret: 32 random bytes, base64url without padding. */
    public static function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public static function issue(
        AccountTokenId $id,
        string $userId,
        TokenPurpose $purpose,
        ?string $clinicId,
        string $secret,
        \DateTimeImmutable $createdAt,
    ): self {
        if (TokenPurpose::INVITATION === $purpose && null === $clinicId) {
            throw new \InvalidArgumentException('An invitation belongs to a clinic.');
        }

        return new self(
            $id,
            $userId,
            $purpose,
            TokenPurpose::INVITATION === $purpose ? $clinicId : null,
            self::hash($secret),
            $createdAt->add($purpose->lifetime()),
            null,
            $createdAt,
        );
    }

    public static function reconstitute(
        AccountTokenId $id,
        string $userId,
        TokenPurpose $purpose,
        ?string $clinicId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $usedAt,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $purpose, $clinicId, $tokenHash, $expiresAt, $usedAt, $createdAt);
    }

    /** Unused and not expired. */
    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $now < $this->expiresAt;
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->usedAt ??= $at;
    }

    public function id(): AccountTokenId
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function purpose(): TokenPurpose
    {
        return $this->purpose;
    }

    public function clinicId(): ?string
    {
        return $this->clinicId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
