<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: owners\' data exports (slice 5).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__data_exports (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              requested_at DATETIME NOT NULL,
              ready_at DATETIME DEFAULT NULL,
              expires_at DATETIME DEFAULT NULL,
              failed_at DATETIME DEFAULT NULL,
              INDEX idx_data_export_user (user_id, requested_at),
              INDEX idx_data_export_expiry (status, expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__data_exports');
    }
}
