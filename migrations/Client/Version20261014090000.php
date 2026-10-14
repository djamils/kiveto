<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client civility (Mme / M.), shown before the name on the client sheet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE client__clients ADD civility VARCHAR(20) DEFAULT 'unspecified' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client__clients DROP civility');
    }
}
