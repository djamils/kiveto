<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251227013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_type discriminator column and backfill existing users (default CLINIC)';
    }

    public function up(Schema $schema): void
    {
        // Add discriminator column
        $this->addSql("ALTER TABLE `user` ADD user_type VARCHAR(32) NOT NULL DEFAULT 'CLINIC'");
        // Backfill existing rows
        $this->addSql("UPDATE `user` SET user_type = 'CLINIC' WHERE user_type = 'CLINIC'");
        // Optional index to scope lookups by type+email
        $this->addSql('CREATE INDEX user_user_type_email_idx ON `user` (user_type, email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_user_type_email_idx ON `user`');
        $this->addSql('ALTER TABLE `user` DROP COLUMN user_type');
    }
}

