<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226232304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__users (
              id CHAR(36) NOT NULL,
              email VARCHAR(180) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              created_at DATETIME NOT NULL,
              status VARCHAR(20) NOT NULL,
              email_verified_at DATETIME DEFAULT NULL,
              user_type VARCHAR(255) NOT NULL,
              UNIQUE INDEX UNIQ_770DC570E7927C74 (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE identity_access__users');
    }
}
