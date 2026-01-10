<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110012338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__clinic_groups (
              id BINARY(16) NOT NULL,
              name VARCHAR(255) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__clinics (
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic__clinic_groups');
        $this->addSql('DROP TABLE clinic__clinics');
    }
}
