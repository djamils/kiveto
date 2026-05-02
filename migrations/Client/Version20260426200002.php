<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426200002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create client__search_entries table for live global search read model';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client__search_entries (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              first_name VARCHAR(255) NOT NULL,
              last_name VARCHAR(255) NOT NULL,
              search_name VARCHAR(255) NOT NULL,
              search_phone VARCHAR(32) DEFAULT NULL,
              primary_email VARCHAR(255) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_client_search_entry_name (clinic_id, search_name),
              INDEX idx_client_search_entry_phone (clinic_id, search_phone),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client__search_entries');
    }
}
