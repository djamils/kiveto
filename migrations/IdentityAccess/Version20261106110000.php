<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: second factor and trusted devices of the console, signed-in sessions, back-office profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__second_factors (
              user_id BINARY(16) NOT NULL,
              secret_ciphertext VARCHAR(255) NOT NULL,
              enabled_at DATETIME NOT NULL,
              last_used_step BIGINT DEFAULT NULL,
              backup_codes JSON NOT NULL,
              PRIMARY KEY (user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__trusted_devices (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              label VARCHAR(120) NOT NULL,
              created_at DATETIME NOT NULL,
              expires_at DATETIME NOT NULL,
              revoked_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_trusted_device_token (token_hash),
              INDEX idx_trusted_device_user (user_id, expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__user_sessions (
              id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              session_hash CHAR(64) NOT NULL,
              user_agent VARCHAR(255) DEFAULT NULL,
              ip_address VARCHAR(45) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              last_seen_at DATETIME NOT NULL,
              ended_at DATETIME DEFAULT NULL,
              revoked_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_user_session_hash (session_hash),
              INDEX idx_user_session_user (user_id, last_seen_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__backoffice_profiles (
              user_id BINARY(16) NOT NULL,
              display_name VARCHAR(80) NOT NULL,
              time_zone VARCHAR(64) NOT NULL,
              notify_registry_failure TINYINT(1) NOT NULL,
              notify_invitation_expiry TINYINT(1) NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_access__backoffice_profiles');
        $this->addSql('DROP TABLE identity_access__user_sessions');
        $this->addSql('DROP TABLE identity_access__trusted_devices');
        $this->addSql('DROP TABLE identity_access__second_factors');
    }
}
