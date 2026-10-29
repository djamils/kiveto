<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

/**
 * One row per (user, clinic, client) — unique key `uniq_client_link`.
 * Dates are written in UTC.
 */
final readonly class DbalClientLinkRepository implements ClientLinkRepositoryInterface
{
    private const string TABLE     = 'client_portal__client_links';
    private const string CONFIRMED = ClientLinkStatus::CONFIRMED->value;

    public function __construct(private Connection $connection)
    {
    }

    public function save(ClientLink $link): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, clinic_id, client_id, status, linked_at, revoked_at)'
            . ' VALUES (:id, :user, :clinic, :client, :status, :linked, :revoked)'
            . ' AS new ON DUPLICATE KEY UPDATE status = new.status, linked_at = new.linked_at, revoked_at = new.revoked_at',
            [
                'id'      => SqlValues::binary($link->id()->toString()),
                'user'    => SqlValues::binary($link->userId()),
                'clinic'  => SqlValues::binary($link->clinicId()),
                'client'  => SqlValues::binary($link->clientId()),
                'status'  => $link->status()->value,
                'linked'  => SqlValues::nullableUtc($link->linkedAt()),
                'revoked' => SqlValues::nullableUtc($link->revokedAt()),
            ],
        );
    }

    public function findForUpdate(string $userId, string $clinicId, string $clientId): ?ClientLink
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user AND clinic_id = :clinic AND client_id = :client FOR UPDATE',
            ['user' => SqlValues::binary($userId), 'clinic' => SqlValues::binary($clinicId), 'client' => SqlValues::binary($clientId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findByClient(string $clinicId, string $clientId): array
    {
        return $this->list(
            'clinic_id = :clinic AND client_id = :client ORDER BY id',
            ['clinic' => SqlValues::binary($clinicId), 'client' => SqlValues::binary($clientId)],
        );
    }

    public function findConfirmedByUser(string $userId): array
    {
        return $this->list(
            'user_id = :user AND status = :confirmed ORDER BY linked_at ASC, id ASC',
            ['user' => SqlValues::binary($userId), 'confirmed' => self::CONFIRMED],
        );
    }

    public function hasConfirmed(string $userId, string $clinicId): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE user_id = :user AND clinic_id = :clinic AND status = :confirmed LIMIT 1',
            ['user' => SqlValues::binary($userId), 'clinic' => SqlValues::binary($clinicId), 'confirmed' => self::CONFIRMED],
        );
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<ClientLink>
     */
    private function list(string $where, array $parameters): array
    {
        return array_map(
            self::hydrate(...),
            $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): ClientLink
    {
        return ClientLink::reconstitute(
            id: ClientLinkId::fromString(RowAccessor::uuid($row, 'id')),
            userId: RowAccessor::uuid($row, 'user_id'),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::uuid($row, 'client_id'),
            status: ClientLinkStatus::from(RowAccessor::string($row, 'status')),
            linkedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'linked_at')),
            revokedAt: SqlValues::nullableDate(RowAccessor::nullableString($row, 'revoked_at')),
        );
    }
}
