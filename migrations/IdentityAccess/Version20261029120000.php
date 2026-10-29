<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: owner (PORTAL) accounts by phone — e-mail and phone unique per account type, per-phone claim mutex.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__users DROP INDEX UNIQ_770DC570E7927C74');
        $this->addSql(<<<'SQL'
            ALTER TABLE identity_access__users
              MODIFY email VARCHAR(180) DEFAULT NULL,
              ADD phone VARCHAR(16) DEFAULT NULL AFTER email,
              ADD phone_verified_at DATETIME DEFAULT NULL AFTER email_verified_at
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_type_email ON identity_access__users (type, email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_type_phone ON identity_access__users (type, phone)');
        // Owner accounts sign in with their phone from now on: the legacy
        // e-mail ones have none and can no longer be used.
        $this->addSql("UPDATE identity_access__users SET status = 'DISABLED' WHERE type = 'PORTAL' AND phone IS NULL");

        $this->addSql(<<<'SQL'
            CREATE TABLE identity_access__portal_phone_locks (
              phone VARCHAR(16) NOT NULL,
              PRIMARY KEY (phone)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    /**
     * Fails loudly when an address is now shared by accounts of different
     * types (or several owner accounts have no e-mail): the global unique
     * key cannot be restored then.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_access__portal_phone_locks');
        $this->addSql('DROP INDEX uniq_user_type_phone ON identity_access__users');
        $this->addSql('DROP INDEX uniq_user_type_email ON identity_access__users');
        $this->addSql('ALTER TABLE identity_access__users DROP phone, DROP phone_verified_at, MODIFY email VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_770DC570E7927C74 ON identity_access__users (email)');
    }
}
