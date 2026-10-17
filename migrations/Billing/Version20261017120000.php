<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Payer « à l\'attention de » (contact person of a structure) on invoices and credit notes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoices ADD payer_attention VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE billing__credit_notes ADD payer_attention VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoices DROP payer_attention');
        $this->addSql('ALTER TABLE billing__credit_notes DROP payer_attention');
    }
}
