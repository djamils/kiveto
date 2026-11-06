<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: the support sessions of the console.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__support_sessions (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              admin_id BINARY(16) NOT NULL,
              admin_name VARCHAR(160) NOT NULL,
              reason VARCHAR(500) NOT NULL,
              mode VARCHAR(8) NOT NULL,
              started_at DATETIME NOT NULL,
              expires_at DATETIME NOT NULL,
              handoff_hash VARCHAR(64) NOT NULL,
              claimed_at DATETIME DEFAULT NULL,
              ended_at DATETIME DEFAULT NULL,
              pages INT NOT NULL,
              writes INT NOT NULL,
              UNIQUE INDEX uniq_support_session_handoff (handoff_hash),
              INDEX idx_support_session_clinic (clinic_id, started_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_access__support_sessions');
    }
}
