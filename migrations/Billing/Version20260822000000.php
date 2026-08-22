<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the Billing bounded context: invoices, their frozen lines, the
 * payments received against them, and the per-clinic numbering sequence.
 */
final class Version20260822000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Billing tables: invoices, invoice lines, payments and number sequences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE billing__invoices (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                number VARCHAR(20) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                admission_id BINARY(16) DEFAULT NULL,
                payer_client_id BINARY(16) DEFAULT NULL,
                payer_name VARCHAR(255) NOT NULL,
                payer_address_lines JSON NOT NULL,
                currency VARCHAR(3) NOT NULL,
                discount_kind VARCHAR(16) DEFAULT NULL,
                discount_value VARCHAR(20) DEFAULT NULL,
                discount_reason VARCHAR(255) DEFAULT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                issued_at DATETIME DEFAULT NULL,
                paid_at DATETIME DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                cancellation_reason VARCHAR(255) DEFAULT NULL,
                INDEX idx_invoice_clinic_status (clinic_id, status),
                INDEX idx_invoice_clinic_issued (clinic_id, issued_at),
                INDEX idx_invoice_admission (admission_id),
                INDEX idx_invoice_payer (clinic_id, payer_client_id),
                UNIQUE INDEX uniq_invoice_number (clinic_id, number),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__invoice_lines (
                id BINARY(16) NOT NULL,
                invoice_id BINARY(16) NOT NULL,
                label VARCHAR(255) NOT NULL,
                code VARCHAR(40) DEFAULT NULL,
                quantity NUMERIC(10, 2) NOT NULL,
                unit_price_minor_units INT NOT NULL,
                net_minor_units INT NOT NULL,
                tax_minor_units INT NOT NULL,
                gross_minor_units INT NOT NULL,
                tax_rate_percent NUMERIC(7, 4) NOT NULL,
                tax_category_code VARCHAR(40) DEFAULT NULL,
                source VARCHAR(20) NOT NULL,
                source_reference VARCHAR(64) DEFAULT NULL,
                position SMALLINT NOT NULL,
                INDEX idx_invoice_line_invoice (invoice_id, position),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__payments (
                id BINARY(16) NOT NULL,
                invoice_id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                method VARCHAR(20) NOT NULL,
                amount_minor_units INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                received_at DATETIME NOT NULL,
                reference VARCHAR(120) DEFAULT NULL,
                recorded_by_user_id BINARY(16) DEFAULT NULL,
                position SMALLINT NOT NULL,
                INDEX idx_payment_invoice (invoice_id),
                INDEX idx_payment_clinic_received (clinic_id, received_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__number_sequences (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                year SMALLINT NOT NULL,
                `last_value` INT NOT NULL,
                UNIQUE INDEX uniq_number_sequence (clinic_id, year),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE billing__invoice_lines ADD CONSTRAINT fk_invoice_line_invoice
            FOREIGN KEY (invoice_id) REFERENCES billing__invoices (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE billing__payments ADD CONSTRAINT fk_payment_invoice
            FOREIGN KEY (invoice_id) REFERENCES billing__invoices (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing__payments DROP FOREIGN KEY fk_payment_invoice');
        $this->addSql('ALTER TABLE billing__invoice_lines DROP FOREIGN KEY fk_invoice_line_invoice');
        $this->addSql('DROP TABLE billing__payments');
        $this->addSql('DROP TABLE billing__invoice_lines');
        $this->addSql('DROP TABLE billing__number_sequences');
        $this->addSql('DROP TABLE billing__invoices');
    }
}
