<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client tax profile: VAT status, legal name, SIREN and French VAT number.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE client__clients
              ADD tax_status VARCHAR(20) DEFAULT 'individual' NOT NULL,
              ADD legal_name VARCHAR(255) DEFAULT NULL,
              ADD siren CHAR(9) DEFAULT NULL,
              ADD vat_number VARCHAR(13) DEFAULT NULL
        SQL);
        $this->addSql('CREATE INDEX idx_client_clinic_siren ON client__clients (clinic_id, siren)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_client_clinic_siren ON client__clients');
        $this->addSql('ALTER TABLE client__clients DROP tax_status, DROP legal_name, DROP siren, DROP vat_number');
    }
}
