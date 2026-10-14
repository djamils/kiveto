<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facturation register: credit notes (their own numbering series and what
 * they cancel out of each invoice), cheque remittances and cash closings.
 */
final class Version20261014200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing: credit notes, credit note numbering series, cheque deposits and cash closings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE billing__invoices
              ADD credited_minor_units INT DEFAULT 0 NOT NULL,
              ADD refunded_minor_units INT DEFAULT 0 NOT NULL
            SQL);

        $this->addSql('ALTER TABLE billing__number_sequences DROP INDEX uniq_number_sequence');
        $this->addSql("ALTER TABLE billing__number_sequences ADD series VARCHAR(16) DEFAULT 'INVOICE' NOT NULL AFTER clinic_id");
        $this->addSql('CREATE UNIQUE INDEX uniq_number_sequence ON billing__number_sequences (clinic_id, series, year)');

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__credit_notes (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                number VARCHAR(20) NOT NULL,
                invoice_id BINARY(16) NOT NULL,
                invoice_number VARCHAR(20) NOT NULL,
                payer_client_id BINARY(16) DEFAULT NULL,
                payer_name VARCHAR(255) NOT NULL,
                payer_address_lines JSON NOT NULL,
                line_items JSON NOT NULL,
                reason VARCHAR(255) NOT NULL,
                refund_method VARCHAR(20) DEFAULT NULL,
                refunded_minor_units INT NOT NULL,
                total_net_minor_units INT NOT NULL,
                total_tax_minor_units INT NOT NULL,
                total_gross_minor_units INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                issued_at DATETIME NOT NULL,
                issued_by_user_id BINARY(16) DEFAULT NULL,
                INDEX idx_credit_note_clinic_issued (clinic_id, issued_at),
                INDEX idx_credit_note_invoice (invoice_id),
                UNIQUE INDEX uniq_credit_note_number (clinic_id, number),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__cheque_deposits (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                total_minor_units INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                deposited_at DATETIME NOT NULL,
                deposited_by_user_id BINARY(16) DEFAULT NULL,
                INDEX idx_cheque_deposit_clinic (clinic_id, deposited_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__cheque_deposit_payments (
                payment_id BINARY(16) NOT NULL,
                deposit_id BINARY(16) NOT NULL,
                INDEX idx_cheque_deposit_payment_deposit (deposit_id),
                PRIMARY KEY (payment_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE billing__cash_closings (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                number INT NOT NULL,
                business_date VARCHAR(10) NOT NULL,
                expected JSON NOT NULL,
                counted_cash_minor_units INT DEFAULT NULL,
                counted_cheques_minor_units INT DEFAULT NULL,
                comment VARCHAR(255) DEFAULT NULL,
                currency VARCHAR(3) NOT NULL,
                closed_at DATETIME NOT NULL,
                closed_by_user_id BINARY(16) DEFAULT NULL,
                UNIQUE INDEX uniq_cash_closing_day (clinic_id, business_date),
                UNIQUE INDEX uniq_cash_closing_number (clinic_id, number),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing__cash_closings');
        $this->addSql('DROP TABLE billing__cheque_deposit_payments');
        $this->addSql('DROP TABLE billing__cheque_deposits');
        $this->addSql('DROP TABLE billing__credit_notes');
        $this->addSql('DROP INDEX uniq_number_sequence ON billing__number_sequences');
        $this->addSql('ALTER TABLE billing__number_sequences DROP series');
        $this->addSql('CREATE UNIQUE INDEX uniq_number_sequence ON billing__number_sequences (clinic_id, year)');
        $this->addSql('ALTER TABLE billing__invoices DROP credited_minor_units, DROP refunded_minor_units');
    }
}
