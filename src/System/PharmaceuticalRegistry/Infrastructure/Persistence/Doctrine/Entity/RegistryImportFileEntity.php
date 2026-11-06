<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Schema of the journal of the imports' source files; read and written in
 * SQL by DbalRegistryImportFileLog.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pharmaceutical_registry__import_files')]
#[ORM\Index(name: 'idx_pharma_import_files_snapshot', columns: ['snapshot_id'])]
#[ORM\Index(name: 'idx_pharma_import_files_hash', columns: ['source', 'kind', 'sha256'])]
class RegistryImportFileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'binary', length: 16)]
        public string $id,
        #[ORM\Column(name: 'snapshot_id', type: 'binary', length: 16)]
        public string $snapshotId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $source,
        #[ORM\Column(type: 'string', length: 8)]
        public string $kind,
        #[ORM\Column(name: 'file_name', type: 'string', length: 64)]
        public string $fileName,
        #[ORM\Column(type: 'string', length: 64, options: ['fixed' => true])]
        public string $sha256,
        #[ORM\Column(name: 'size_bytes', type: 'bigint')]
        public string $sizeBytes,
        #[ORM\Column(name: 'stored_path', type: 'string', length: 255)]
        public string $storedPath,
        #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $receivedAt,
        #[ORM\Column(name: 'imported_by', type: 'string', length: 160, nullable: true)]
        public ?string $importedBy,
        #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $recordedAt,
    ) {
    }
}
