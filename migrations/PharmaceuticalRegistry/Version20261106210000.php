<?php

declare(strict_types=1);

namespace DoctrineMigrations\PharmaceuticalRegistry;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PharmaceuticalRegistry: the journal of the source files of each import (SHA-256, size, where they are kept, who launched it).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmaceutical_registry__import_files (
              id BINARY(16) NOT NULL,
              snapshot_id BINARY(16) NOT NULL,
              source VARCHAR(16) NOT NULL,
              kind VARCHAR(8) NOT NULL,
              file_name VARCHAR(64) NOT NULL,
              sha256 CHAR(64) NOT NULL,
              size_bytes BIGINT NOT NULL,
              stored_path VARCHAR(255) NOT NULL,
              received_at DATETIME NOT NULL,
              imported_by VARCHAR(160) DEFAULT NULL,
              recorded_at DATETIME NOT NULL,
              INDEX idx_pharma_import_files_snapshot (snapshot_id),
              INDEX idx_pharma_import_files_hash (source, kind, sha256),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pharmaceutical_registry__import_files');
    }
}
