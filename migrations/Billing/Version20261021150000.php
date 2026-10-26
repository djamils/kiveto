<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiscal qualification frozen at issue: processing rule, invoicing framework, payment terms, credited invoice date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE billing__invoices
              ADD processing_rule VARCHAR(3) DEFAULT NULL,
              ADD invoicing_framework VARCHAR(2) DEFAULT NULL,
              ADD due_date DATE DEFAULT NULL,
              ADD payment_terms_note VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE billing__credit_notes
              ADD processing_rule VARCHAR(3) DEFAULT NULL,
              ADD invoicing_framework VARCHAR(2) DEFAULT NULL,
              ADD invoice_issued_at DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoices DROP processing_rule, DROP invoicing_framework, DROP due_date, DROP payment_terms_note');
        $this->addSql('ALTER TABLE billing__credit_notes DROP processing_rule, DROP invoicing_framework, DROP invoice_issued_at');
    }
}
