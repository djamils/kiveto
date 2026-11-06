<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryDiffLine;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use Doctrine\DBAL\Connection;

final readonly class DbalRegistryImportReadRepository implements RegistryImportReadRepositoryInterface
{
    private const string IMPORT_SELECT = 'SELECT BIN_TO_UUID(s.id) AS id, s.status, s.downloaded_at, s.applied_at, s.error_message,'
        . " (SELECT COUNT(*) FROM pharmaceutical_registry__snapshot_entries e WHERE e.snapshot_id = s.id AND e.diff_kind = 'CREATE') AS created,"
        . " (SELECT COUNT(*) FROM pharmaceutical_registry__snapshot_entries e WHERE e.snapshot_id = s.id AND e.diff_kind = 'UPDATE') AS updated,"
        . " (SELECT COUNT(*) FROM pharmaceutical_registry__snapshot_entries e WHERE e.snapshot_id = s.id AND e.diff_kind = 'WITHDRAW') AS withdrawn"
        . ' FROM pharmaceutical_registry__snapshots s';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function imports(string $source, int $limit): array
    {
        return array_map(self::view(...), $this->connection->fetchAllAssociative(
            self::IMPORT_SELECT . ' WHERE s.source = ? ORDER BY s.downloaded_at DESC, s.id DESC LIMIT ' . max(1, $limit),
            [$source],
        ));
    }

    public function import(string $id): ?RegistryImportView
    {
        $row = $this->connection->fetchAssociative(self::IMPORT_SELECT . ' WHERE s.id = UUID_TO_BIN(?)', [$id]);

        return false === $row ? null : self::view($row);
    }

    public function diff(string $id, ?string $kind, int $offset, int $limit): array
    {
        [$where, $parameters] = self::where($id, $kind);

        return array_map(self::line(...), $this->connection->fetchAllAssociative(
            'SELECT authority_identifier, diff_kind, raw_dto, changes FROM pharmaceutical_registry__snapshot_entries WHERE ' . $where
            . " ORDER BY FIELD(diff_kind, 'WITHDRAW', 'UPDATE', 'CREATE'), authority_identifier LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $parameters,
        ));
    }

    public function countDiff(string $id, ?string $kind): int
    {
        [$where, $parameters] = self::where($id, $kind);

        return RowAccessor::int(['n' => $this->connection->fetchOne('SELECT COUNT(*) FROM pharmaceutical_registry__snapshot_entries WHERE ' . $where, $parameters)], 'n');
    }

    public function authorizationCount(): int
    {
        return RowAccessor::int(['n' => $this->connection->fetchOne('SELECT COUNT(*) FROM pharmaceutical_registry__authorizations')], 'n');
    }

    /**
     * @return array{string, list<string>}
     */
    private static function where(string $id, ?string $kind): array
    {
        return null !== $kind
            ? ['snapshot_id = UUID_TO_BIN(?) AND diff_kind = ?', [$id, $kind]]
            : ['snapshot_id = UUID_TO_BIN(?) AND diff_kind IS NOT NULL', [$id]];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function view(array $row): RegistryImportView
    {
        $appliedAt = RowAccessor::nullableString($row, 'applied_at');

        return new RegistryImportView(
            id: RowAccessor::string($row, 'id'),
            status: RowAccessor::string($row, 'status'),
            downloadedAt: new \DateTimeImmutable(RowAccessor::string($row, 'downloaded_at'), new \DateTimeZone('UTC')),
            appliedAt: null !== $appliedAt ? new \DateTimeImmutable($appliedAt, new \DateTimeZone('UTC')) : null,
            errorMessage: RowAccessor::nullableString($row, 'error_message'),
            created: RowAccessor::int($row, 'created'),
            updated: RowAccessor::int($row, 'updated'),
            withdrawn: RowAccessor::int($row, 'withdrawn'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function line(array $row): RegistryDiffLine
    {
        $dto     = self::json(RowAccessor::nullableString($row, 'raw_dto'));
        $changes = self::json(RowAccessor::nullableString($row, 'changes'));
        $fields  = [];
        foreach ($changes as $key => $value) {
            $fields[] = \is_string($key) ? $key : (\is_string($value) ? $value : '');
        }

        return new RegistryDiffLine(
            identifier: RowAccessor::string($row, 'authority_identifier'),
            kind: RowAccessor::string($row, 'diff_kind'),
            name: \is_string($dto['commercialName'] ?? null) ? $dto['commercialName'] : null,
            laboratory: \is_string($dto['holderLaboratoryLabel'] ?? null) ? $dto['holderLaboratoryLabel'] : null,
            changedFields: array_values(array_filter($fields, static fn (string $f): bool => '' !== $f)),
        );
    }

    /**
     * @return array<mixed>
     */
    private static function json(?string $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
