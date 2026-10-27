<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC. Only `used_at` ever changes after the insert.
 */
final readonly class DbalAccountTokenRepository implements AccountTokenRepositoryInterface
{
    private const string TABLE = 'identity_access__account_tokens';

    public function __construct(private Connection $connection)
    {
    }

    public function save(AccountToken $token): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, purpose, clinic_id, token_hash, expires_at, used_at, created_at)'
            . ' VALUES (:id, :user, :purpose, :clinic, :hash, :expires, :used, :created)'
            . ' AS new ON DUPLICATE KEY UPDATE used_at = new.used_at',
            [
                'id'      => self::binary($token->id()->toString()),
                'user'    => self::binary($token->userId()),
                'purpose' => $token->purpose()->value,
                'clinic'  => null !== $token->clinicId() ? self::binary($token->clinicId()) : null,
                'hash'    => $token->tokenHash(),
                'expires' => self::utc($token->expiresAt()),
                'used'    => null !== $token->usedAt() ? self::utc($token->usedAt()) : null,
                'created' => self::utc($token->createdAt()),
            ],
        );
    }

    public function findByHashForUpdate(string $tokenHash): ?AccountToken
    {
        return $this->find($tokenHash, ' FOR UPDATE');
    }

    public function findByHash(string $tokenHash): ?AccountToken
    {
        return $this->find($tokenHash, '');
    }

    public function invalidateInvitations(string $userId, string $clinicId, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET used_at = :at WHERE user_id = :user AND purpose = :purpose AND clinic_id = :clinic AND used_at IS NULL',
            ['at' => self::utc($at), 'user' => self::binary($userId), 'purpose' => TokenPurpose::INVITATION->value, 'clinic' => self::binary($clinicId)],
        );
    }

    public function invalidatePasswordResets(string $userId, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET used_at = :at WHERE user_id = :user AND purpose = :purpose AND used_at IS NULL',
            ['at' => self::utc($at), 'user' => self::binary($userId), 'purpose' => TokenPurpose::PASSWORD_RESET->value],
        );
    }

    public function usersWithUsableInvitation(string $clinicId, array $userIds, \DateTimeImmutable $now): array
    {
        if ([] === $userIds) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT user_id FROM ' . self::TABLE
            . ' WHERE clinic_id = :clinic AND purpose = :purpose AND user_id IN (:users) AND used_at IS NULL AND expires_at > :now',
            [
                'clinic'  => self::binary($clinicId),
                'purpose' => TokenPurpose::INVITATION->value,
                'users'   => array_map(self::binary(...), $userIds),
                'now'     => self::utc($now),
            ],
            ['users' => ArrayParameterType::BINARY],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
    }

    private function find(string $tokenHash, string $lock): ?AccountToken
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE token_hash = :hash' . $lock,
            ['hash' => $tokenHash],
        );

        if (false === $row) {
            return null;
        }

        $usedAt = RowAccessor::nullableString($row, 'used_at');

        return AccountToken::reconstitute(
            id: AccountTokenId::fromString(RowAccessor::uuid($row, 'id')),
            userId: RowAccessor::uuid($row, 'user_id'),
            purpose: TokenPurpose::from(RowAccessor::string($row, 'purpose')),
            clinicId: RowAccessor::nullableUuid($row, 'clinic_id'),
            tokenHash: RowAccessor::string($row, 'token_hash'),
            expiresAt: self::date(RowAccessor::string($row, 'expires_at')),
            usedAt: null !== $usedAt ? self::date($usedAt) : null,
            createdAt: self::date(RowAccessor::string($row, 'created_at')),
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

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
