<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502194100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointments ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE scheduling__planning_blocks ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__appointments DROP version');
        $this->addSql('ALTER TABLE scheduling__planning_blocks DROP version');
    }
}
