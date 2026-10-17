<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017110100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client search entries carry the legal name of structures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client__search_entries ADD legal_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client__search_entries DROP legal_name');
    }
}
