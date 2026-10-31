<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: publication registry of the documents shown to owners, and their read marks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__published_documents (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) DEFAULT NULL,
              type VARCHAR(16) NOT NULL,
              source_ref BINARY(16) NOT NULL,
              consultation_id BINARY(16) DEFAULT NULL,
              patient_id BINARY(16) DEFAULT NULL,
              pet_ids JSON NOT NULL,
              title VARCHAR(255) NOT NULL,
              issued_at DATETIME NOT NULL,
              practitioner_name VARCHAR(255) DEFAULT NULL,
              storage_key VARCHAR(255) DEFAULT NULL,
              file_version INT NOT NULL,
              pending_file_version INT DEFAULT NULL,
              render_attempts INT NOT NULL,
              status VARCHAR(16) NOT NULL,
              published_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_published_document_source (clinic_id, type, source_ref),
              INDEX idx_published_document_client (clinic_id, client_id, status, issued_at, id),
              INDEX idx_published_document_patient (clinic_id, patient_id),
              INDEX idx_published_document_pending (status, updated_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__document_reads (
              document_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              read_at DATETIME NOT NULL,
              PRIMARY KEY (document_id, user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__document_reads');
        $this->addSql('DROP TABLE client_portal__published_documents');
    }
}
