<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClinicAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260111103514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE clinic_access__clinic_memberships (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              role VARCHAR(40) NOT NULL,
              engagement VARCHAR(20) NOT NULL,
              status VARCHAR(20) NOT NULL,
              valid_from_utc DATETIME NOT NULL,
              valid_until_utc DATETIME DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              INDEX idx_user_id (user_id),
              INDEX idx_clinic_id (clinic_id),
              INDEX idx_status (status),
              UNIQUE INDEX uniq_clinic_user (clinic_id, user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE clinic_access__clinic_memberships');
    }
}
