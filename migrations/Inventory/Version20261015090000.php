<?php

declare(strict_types=1);

namespace DoctrineMigrations\Inventory;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stock page: where an article is kept, and which way an adjustment went (the
 * quantity of a movement is always positive).
 */
final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory: storage location of a stock item, direction of an adjustment movement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory__stock_items ADD storage_location VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory__stock_movements ADD decrease TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory__stock_items DROP storage_location');
        $this->addSql('ALTER TABLE inventory__stock_movements DROP decrease');
    }
}
