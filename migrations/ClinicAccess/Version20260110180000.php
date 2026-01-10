<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClinicAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create clinic_access__memberships table for ClinicAccess BC';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic_access__memberships (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              role VARCHAR(40) NOT NULL,
              engagement VARCHAR(20) NOT NULL,
              status VARCHAR(20) NOT NULL,
              valid_from_utc DATETIME(6) NOT NULL,
              valid_until_utc DATETIME(6) DEFAULT NULL,
              created_at_utc DATETIME(6) NOT NULL,
              UNIQUE INDEX uniq_clinic_user (clinic_id, user_id),
              INDEX idx_user_id (user_id),
              INDEX idx_clinic_id (clinic_id),
              INDEX idx_status (status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic_access__memberships');
    }
}
