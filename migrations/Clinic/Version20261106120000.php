<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clinic: the clinics\' accounts of the console (plan, pilot end, parallel software, internal note, onboarding, suspension).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic__clinic_accounts (
              clinic_id BINARY(16) NOT NULL,
              plan VARCHAR(16) NOT NULL,
              pilot_ends_on DATE DEFAULT NULL,
              parallel_software VARCHAR(16) DEFAULT NULL,
              internal_note LONGTEXT NOT NULL,
              onboarded_at DATETIME DEFAULT NULL,
              suspension_reason VARCHAR(24) DEFAULT NULL,
              suspension_comment VARCHAR(500) DEFAULT NULL,
              suspended_at DATETIME DEFAULT NULL,
              suspended_by_id BINARY(16) DEFAULT NULL,
              suspended_by_name VARCHAR(160) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinic__clinic_accounts');
    }
}
