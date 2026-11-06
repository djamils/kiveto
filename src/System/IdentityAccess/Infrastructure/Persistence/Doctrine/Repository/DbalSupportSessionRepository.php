<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalSupportSessionRepository implements SupportSessionRepositoryInterface
{
    public const string TABLE = 'identity_access__support_sessions';

    private const string COLUMNS = 'BIN_TO_UUID(id) AS id, BIN_TO_UUID(clinic_id) AS clinic_id, BIN_TO_UUID(user_id) AS user_id, BIN_TO_UUID(admin_id) AS admin_id,'
        . ' admin_name, reason, mode, started_at, expires_at, handoff_hash, claimed_at, ended_at, pages, writes';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(SupportSession $session): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, user_id, admin_id, admin_name, reason, mode, started_at, expires_at, handoff_hash, claimed_at, ended_at, pages, writes)'
            . ' VALUES (:id, :clinic, :user, :admin, :admin_name, :reason, :mode, :started_at, :expires_at, :hash, :claimed_at, :ended_at, :pages, :writes) AS new'
            . ' ON DUPLICATE KEY UPDATE claimed_at = new.claimed_at, ended_at = new.ended_at, pages = new.pages, writes = new.writes',
            [
                'id'         => Uuid::fromString($session->id())->toBinary(),
                'clinic'     => Uuid::fromString($session->clinicId())->toBinary(),
                'user'       => Uuid::fromString($session->userId())->toBinary(),
                'admin'      => Uuid::fromString($session->adminId())->toBinary(),
                'admin_name' => $session->adminName(),
                'reason'     => $session->reason(),
                'mode'       => $session->mode()->value,
                'started_at' => self::utc($session->startedAt()),
                'expires_at' => self::utc($session->expiresAt()),
                'hash'       => $session->handoffHash(),
                'claimed_at' => null !== $session->claimedAt() ? self::utc($session->claimedAt()) : null,
                'ended_at'   => null !== $session->endedAt() ? self::utc($session->endedAt()) : null,
                'pages'      => $session->pages(),
                'writes'     => $session->writes(),
            ],
        );
    }

    public function find(string $id): ?SupportSession
    {
        $row = $this->connection->fetchAssociative('SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE . ' WHERE id = UUID_TO_BIN(?)', [$id]);

        return false === $row ? null : self::session($row);
    }

    public function findByHandoffHashForUpdate(string $handoffHash): ?SupportSession
    {
        $row = $this->connection->fetchAssociative('SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE . ' WHERE handoff_hash = ? FOR UPDATE', [$handoffHash]);

        return false === $row ? null : self::session($row);
    }

    public function ofClinic(string $clinicId, int $limit): array
    {
        return array_map(self::session(...), $this->connection->fetchAllAssociative(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE . ' WHERE clinic_id = UUID_TO_BIN(?) AND claimed_at IS NOT NULL ORDER BY started_at DESC, id DESC LIMIT ' . max(1, $limit),
            [$clinicId],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function session(array $row): SupportSession
    {
        $claimedAt = RowAccessor::nullableString($row, 'claimed_at');
        $endedAt   = RowAccessor::nullableString($row, 'ended_at');

        return SupportSession::reconstitute(
            id: RowAccessor::string($row, 'id'),
            clinicId: RowAccessor::string($row, 'clinic_id'),
            userId: RowAccessor::string($row, 'user_id'),
            adminId: RowAccessor::string($row, 'admin_id'),
            adminName: RowAccessor::string($row, 'admin_name'),
            reason: RowAccessor::string($row, 'reason'),
            mode: SupportMode::from(RowAccessor::string($row, 'mode')),
            startedAt: self::date(RowAccessor::string($row, 'started_at')),
            expiresAt: self::date(RowAccessor::string($row, 'expires_at')),
            handoffHash: RowAccessor::string($row, 'handoff_hash'),
            claimedAt: null !== $claimedAt ? self::date($claimedAt) : null,
            endedAt: null !== $endedAt ? self::date($endedAt) : null,
            pages: RowAccessor::int($row, 'pages'),
            writes: RowAccessor::int($row, 'writes'),
        );
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
