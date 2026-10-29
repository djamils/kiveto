<?php

declare(strict_types=1);

namespace DoctrineMigrations\Inventory;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory: the lots already announced « Péremption proche » in the bell.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory__expiry_notifications (
              lot_id BINARY(16) NOT NULL,
              notified_at DATETIME NOT NULL,
              PRIMARY KEY (lot_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory__expiry_notifications');
    }
}
