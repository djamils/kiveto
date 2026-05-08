<?php

declare(strict_types=1);

namespace DoctrineMigrations\Regulatory;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502201214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE regulatory__icadlookups');
        $this->addSql('DROP TABLE regulatory__mairie_notifications');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE regulatory__icadlookups (
              id BINARY(16) NOT NULL,
              chip_number VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              clinic_id BINARY(16) NOT NULL,
              status VARCHAR(16) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              icad_animal_data LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`,
              error_message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`,
              version INT DEFAULT 1 NOT NULL,
              initiated_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE regulatory__mairie_notifications (
              id BINARY(16) NOT NULL,
              admission_id BINARY(16) NOT NULL,
              patient_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              status VARCHAR(16) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              deadline DATETIME NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
    }
}
