<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Buyer tax identity and structured address frozen on invoices and credit notes.';
    }

    public function up(Schema $schema): void
    {
        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql(<<<SQL
                ALTER TABLE {$table}
                  ADD payer_tax_status VARCHAR(20) DEFAULT NULL,
                  ADD payer_legal_name VARCHAR(255) DEFAULT NULL,
                  ADD payer_siren VARCHAR(9) DEFAULT NULL,
                  ADD payer_vat_number VARCHAR(13) DEFAULT NULL,
                  ADD payer_electronic_address_scheme VARCHAR(8) DEFAULT NULL,
                  ADD payer_electronic_address VARCHAR(64) DEFAULT NULL,
                  ADD payer_street JSON DEFAULT NULL,
                  ADD payer_postal_code VARCHAR(20) DEFAULT NULL,
                  ADD payer_city VARCHAR(255) DEFAULT NULL,
                  ADD payer_country_code VARCHAR(2) DEFAULT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql(<<<SQL
                ALTER TABLE {$table}
                  DROP payer_tax_status, DROP payer_legal_name, DROP payer_siren, DROP payer_vat_number,
                  DROP payer_electronic_address_scheme, DROP payer_electronic_address, DROP payer_street,
                  DROP payer_postal_code, DROP payer_city, DROP payer_country_code
            SQL);
        }
    }
}
