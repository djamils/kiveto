<?php

declare(strict_types=1);

namespace DoctrineMigrations\Animal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502202539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal__animals CHANGE sire_number registry_reference VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal__animals CHANGE registry_reference sire_number VARCHAR(50) DEFAULT NULL');
    }
}
