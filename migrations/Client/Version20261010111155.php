<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010111155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client__client_communications (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              channel VARCHAR(20) NOT NULL,
              direction VARCHAR(20) NOT NULL,
              subject VARCHAR(160) NOT NULL,
              body LONGTEXT DEFAULT NULL,
              author_name VARCHAR(255) NOT NULL,
              occurred_at DATETIME NOT NULL,
              INDEX idx_client_communication_client_occurred (
                clinic_id, client_id, occurred_at
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE client__client_notes (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              client_id BINARY(16) NOT NULL,
              kind VARCHAR(20) NOT NULL,
              content LONGTEXT NOT NULL,
              author_name VARCHAR(255) NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_client_note_client_created (clinic_id, client_id, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              client__clients
            ADD
              authorized_person JSON DEFAULT NULL,
            ADD
              communication_preferences JSON DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client__client_communications');
        $this->addSql('DROP TABLE client__client_notes');
        $this->addSql('ALTER TABLE client__clients DROP authorized_person, DROP communication_preferences');
    }
}
