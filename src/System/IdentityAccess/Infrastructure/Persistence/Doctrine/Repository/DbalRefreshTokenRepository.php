<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC. A token's identity (family, user, digest,
 * creation, expiry) never changes after the insert.
 */
final readonly class DbalRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private const string TABLE = 'identity_access__refresh_tokens';

    public function __construct(private Connection $connection)
    {
    }

    public function save(RefreshToken $token): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE
            . ' (id, family_id, user_id, token_hash, created_at, last_used_at, expires_at, rotated_at, rotated_to_id, grace_successor_id, revoked_at)'
            . ' VALUES (:id, :family, :user, :hash, :created, :lastUsed, :expires, :rotated, :rotatedTo, :grace, :revoked)'
            . ' AS new ON DUPLICATE KEY UPDATE last_used_at = new.last_used_at, rotated_at = new.rotated_at,'
            . ' rotated_to_id = new.rotated_to_id, grace_successor_id = new.grace_successor_id, revoked_at = new.revoked_at',
            [
                'id'        => self::binary($token->id()->toString()),
                'family'    => self::binary($token->familyId()->toString()),
                'user'      => self::binary($token->userId()),
                'hash'      => $token->tokenHash(),
                'created'   => self::utc($token->createdAt()),
                'lastUsed'  => self::nullableUtc($token->lastUsedAt()),
                'expires'   => self::utc($token->expiresAt()),
                'rotated'   => self::nullableUtc($token->rotatedAt()),
                'rotatedTo' => null !== $token->rotatedToId() ? self::binary($token->rotatedToId()->toString()) : null,
                'grace'     => null !== $token->graceSuccessorId() ? self::binary($token->graceSuccessorId()->toString()) : null,
                'revoked'   => self::nullableUtc($token->revokedAt()),
            ],
        );
    }

    public function findByHashForUpdate(string $tokenHash): ?RefreshToken
    {
        return $this->find('token_hash = :key FOR UPDATE', $tokenHash);
    }

    public function findById(RefreshTokenId $id): ?RefreshToken
    {
        return $this->find('id = :key', self::binary($id->toString()));
    }

    public function revokeFamily(RefreshTokenFamilyId $familyId, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET revoked_at = :at WHERE family_id = :family AND revoked_at IS NULL',
            ['at' => self::utc($at), 'family' => self::binary($familyId->toString())],
        );
    }

    private function find(string $condition, string $key): ?RefreshToken
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $condition, ['key' => $key]);

        if (false === $row) {
            return null;
        }

        $rotatedTo = RowAccessor::nullableUuid($row, 'rotated_to_id');
        $grace     = RowAccessor::nullableUuid($row, 'grace_successor_id');

        return RefreshToken::reconstitute(
            id: RefreshTokenId::fromString(RowAccessor::uuid($row, 'id')),
            familyId: RefreshTokenFamilyId::fromString(RowAccessor::uuid($row, 'family_id')),
            userId: RowAccessor::uuid($row, 'user_id'),
            tokenHash: RowAccessor::string($row, 'token_hash'),
            createdAt: self::date(RowAccessor::string($row, 'created_at')),
            lastUsedAt: self::nullableDate(RowAccessor::nullableString($row, 'last_used_at')),
            expiresAt: self::date(RowAccessor::string($row, 'expires_at')),
            rotatedAt: self::nullableDate(RowAccessor::nullableString($row, 'rotated_at')),
            rotatedToId: null !== $rotatedTo ? RefreshTokenId::fromString($rotatedTo) : null,
            graceSuccessorId: null !== $grace ? RefreshTokenId::fromString($grace) : null,
            revokedAt: self::nullableDate(RowAccessor::nullableString($row, 'revoked_at')),
        );
    }

    private static function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function nullableDate(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::date($value);
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function nullableUtc(?\DateTimeImmutable $at): ?string
    {
        return null === $at ? null : self::utc($at);
    }
}
