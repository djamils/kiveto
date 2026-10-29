<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: one-time code challenges (with onboarding grants) and refresh token families of the Portal API.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__otp_challenges (
              id BINARY(16) NOT NULL,
              phone VARCHAR(16) NOT NULL,
              purpose VARCHAR(20) NOT NULL,
              invitation_ref VARCHAR(36) DEFAULT NULL,
              code_hash CHAR(64) NOT NULL,
              attempts SMALLINT NOT NULL,
              send_count SMALLINT NOT NULL,
              expires_at DATETIME NOT NULL,
              resend_available_at DATETIME NOT NULL,
              last_channel VARCHAR(10) NOT NULL,
              sms_suppressed TINYINT(1) NOT NULL,
              consumed_at DATETIME DEFAULT NULL,
              onboarding_token_hash CHAR(64) DEFAULT NULL,
              onboarding_expires_at DATETIME DEFAULT NULL,
              onboarding_revoked_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_otp_onboarding_hash (onboarding_token_hash),
              INDEX idx_otp_phone (phone),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__refresh_tokens (
              id BINARY(16) NOT NULL,
              family_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              created_at DATETIME NOT NULL,
              last_used_at DATETIME DEFAULT NULL,
              expires_at DATETIME NOT NULL,
              rotated_at DATETIME DEFAULT NULL,
              rotated_to_id BINARY(16) DEFAULT NULL,
              grace_successor_id BINARY(16) DEFAULT NULL,
              revoked_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_refresh_token_hash (token_hash),
              INDEX idx_refresh_token_family (family_id),
              INDEX idx_refresh_token_user (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_access__refresh_tokens');
        $this->addSql('DROP TABLE identity_access__otp_challenges');
    }
}
