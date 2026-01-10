<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110004807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__entities (
              id BINARY(16) NOT NULL,
              clinic_group_id BINARY(16) DEFAULT NULL,
              slug VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              status VARCHAR(20) NOT NULL,
              time_zone VARCHAR(64) NOT NULL,
              locale VARCHAR(10) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_clinic_group_id (clinic_group_id),
              INDEX idx_clinic_status (status),
              UNIQUE INDEX uniq_clinic_slug (slug),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__groups (
              id BINARY(16) NOT NULL,
              name VARCHAR(255) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE clinic__entities');
        $this->addSql('DROP TABLE clinic__groups');
    }
}
