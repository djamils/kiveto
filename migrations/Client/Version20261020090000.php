<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client tax profile verification: registry and VIES outcomes, check dates and pending request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE client__clients
              ADD tax_company_check_outcome VARCHAR(20) DEFAULT NULL,
              ADD tax_company_registry_name VARCHAR(255) DEFAULT NULL,
              ADD tax_company_checked_at DATETIME DEFAULT NULL,
              ADD tax_vat_check_outcome VARCHAR(20) DEFAULT NULL,
              ADD tax_vat_checked_at DATETIME DEFAULT NULL,
              ADD tax_verification_requested_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql('CREATE INDEX idx_client_tax_checked ON client__clients (tax_company_checked_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_client_tax_checked ON client__clients');
        $this->addSql(<<<'SQL'
            ALTER TABLE client__clients
              DROP tax_company_check_outcome,
              DROP tax_company_registry_name,
              DROP tax_company_checked_at,
              DROP tax_vat_check_outcome,
              DROP tax_vat_checked_at,
              DROP tax_verification_requested_at
        SQL);
    }
}
