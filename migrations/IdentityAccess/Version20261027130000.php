<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: single-use account tokens (invitation, password reset).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__account_tokens (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              purpose VARCHAR(20) NOT NULL,
              clinic_id BINARY(16) DEFAULT NULL,
              token_hash CHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_account_token_hash (token_hash),
              INDEX idx_account_token_user (user_id, purpose, clinic_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_access__account_tokens');
    }
}
