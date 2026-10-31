<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: current vaccination booklet file of each animal.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_portal__booklet_files (
              animal_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              storage_key VARCHAR(255) NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (animal_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_portal__booklet_files');
    }
}
