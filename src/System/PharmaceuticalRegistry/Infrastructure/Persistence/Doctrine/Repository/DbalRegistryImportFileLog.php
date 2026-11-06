<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalRegistryImportFileLog implements RegistryImportFileLogInterface
{
    public const string TABLE = 'pharmaceutical_registry__import_files';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function record(string $snapshotId, string $source, array $files): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($files as $file) {
            $this->connection->insert(self::TABLE, [
                'id'          => Uuid::v7()->toBinary(),
                'snapshot_id' => Uuid::fromString($snapshotId)->toBinary(),
                'source'      => $source,
                'kind'        => $file->kind,
                'file_name'   => $file->name,
                'sha256'      => $file->sha256,
                'size_bytes'  => $file->size,
                'stored_path' => $file->storedPath,
                'received_at' => self::utc($file->receivedAt),
                'imported_by' => $file->importedBy,
                'recorded_at' => self::utc($now),
            ]);
        }
    }

    public function filesOf(array $snapshotIds): array
    {
        if ([] === $snapshotIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(snapshot_id) AS snapshot_id, kind, file_name, sha256, size_bytes, stored_path, received_at, imported_by FROM ' . self::TABLE
            . ' WHERE snapshot_id IN (:ids) ORDER BY recorded_at, kind',
            ['ids' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $snapshotIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $files = [];
        foreach ($rows as $row) {
            $files[RowAccessor::string($row, 'snapshot_id')][] = new RegistryImportFile(
                kind: RowAccessor::string($row, 'kind'),
                name: RowAccessor::string($row, 'file_name'),
                sha256: RowAccessor::string($row, 'sha256'),
                size: RowAccessor::int($row, 'size_bytes'),
                storedPath: RowAccessor::string($row, 'stored_path'),
                receivedAt: new \DateTimeImmutable(RowAccessor::string($row, 'received_at'), new \DateTimeZone('UTC')),
                importedBy: RowAccessor::nullableString($row, 'imported_by'),
            );
        }

        return $files;
    }

    public function lastAppliedRegistryHash(string $source): ?string
    {
        $hash = $this->connection->fetchOne(
            'SELECT f.sha256 FROM ' . self::TABLE . ' f JOIN pharmaceutical_registry__snapshots s ON s.id = f.snapshot_id'
            . " WHERE f.source = ? AND f.kind = ? AND s.status = 'APPLIED' ORDER BY s.downloaded_at DESC, f.recorded_at DESC LIMIT 1",
            [$source, RegistryImportFile::REGISTRY],
        );

        return \is_string($hash) ? $hash : null;
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
