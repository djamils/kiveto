<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shared;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lock table shared across containers (DoctrineDbalStore default columns)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE shared__lock_keys (
              key_id VARCHAR(64) NOT NULL,
              key_token VARCHAR(44) NOT NULL,
              key_expiration INT UNSIGNED NOT NULL,
              PRIMARY KEY (key_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shared__lock_keys');
    }
}
