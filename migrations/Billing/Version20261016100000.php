<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Counter sales remember who they were made to: a client on file, a newcomer
 * whose record was created at the desk, or a client who refused to give their
 * contact details.
 */
final class Version20261016100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing: who a counter sale was made to.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoices ADD counter_sale_payer VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__invoices DROP counter_sale_payer');
    }
}
