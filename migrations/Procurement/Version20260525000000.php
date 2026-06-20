<?php

declare(strict_types=1);

namespace DoctrineMigrations\Procurement;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Procurement BC tables (12 tables)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__suppliers (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                name VARCHAR(255) NOT NULL,
                code VARCHAR(32) NOT NULL,
                type VARCHAR(32) NOT NULL,
                country_code CHAR(3) NOT NULL,
                default_currency CHAR(3) NOT NULL,
                integration_mode VARCHAR(32) NOT NULL,
                adapter_identifier VARCHAR(64) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
                version INT NOT NULL DEFAULT 1,
                contact_email VARCHAR(255) NULL,
                contact_phone VARCHAR(64) NULL,
                contact_person VARCHAR(255) NULL,
                address_street VARCHAR(255) NULL,
                address_line2 VARCHAR(255) NULL,
                postal_code VARCHAR(16) NULL,
                contact_city VARCHAR(128) NULL,
                address_country_code CHAR(3) NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_supplier_code (code),
                INDEX idx_supplier_status (status),
                FULLTEXT INDEX idx_supplier_name_fulltext (name)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__supplier_accounts (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                customer_code VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
                billing_address_json LONGTEXT NULL,
                delivery_address_json LONGTEXT NULL,
                notes LONGTEXT NULL,
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sa_clinic_supplier (clinic_id, supplier_id),
                INDEX idx_sa_clinic (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__supplier_catalog_entries (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_product_code VARCHAR(128) NOT NULL,
                name VARCHAR(255) NOT NULL,
                gtin VARCHAR(14) NULL,
                catalog_price_minor INT NOT NULL,
                catalog_price_currency CHAR(3) NOT NULL,
                catalog_price_valid_from DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                catalog_price_valid_to DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                packaging_unit VARCHAR(32) NULL,
                packaging_amount VARCHAR(32) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sce_supplier_code (supplier_id, supplier_product_code),
                INDEX idx_sce_supplier (supplier_id),
                INDEX idx_sce_status (status),
                FULLTEXT INDEX idx_sce_name_fulltext (name)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__supplier_pricing (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_catalog_entry_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                amount_minor INT NOT NULL,
                amount_currency CHAR(3) NOT NULL,
                discount_percentage VARCHAR(16) NULL,
                pricing_notes LONGTEXT NULL,
                expires_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                negotiated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sp_clinic_entry (clinic_id, supplier_catalog_entry_id),
                INDEX idx_sp_clinic (clinic_id),
                INDEX idx_sp_entry (supplier_catalog_entry_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__purchase_orders (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_account_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                order_number VARCHAR(32) NOT NULL,
                currency CHAR(3) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
                external_reference_value VARCHAR(255) NULL,
                external_reference_provided_by VARCHAR(128) NULL,
                delivery_address_json LONGTEXT NULL,
                pdf_file_id VARCHAR(255) NULL,
                submitted_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                confirmed_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_po_clinic_number (clinic_id, order_number),
                INDEX idx_po_clinic_status (clinic_id, status),
                INDEX idx_po_supplier (supplier_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__purchase_order_lines (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                purchase_order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                article_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                catalog_entry_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                ordered_amount VARCHAR(32) NOT NULL,
                ordered_unit VARCHAR(32) NOT NULL,
                unit_price_minor INT NOT NULL,
                unit_price_currency CHAR(3) NOT NULL,
                received_amount VARCHAR(32) NOT NULL DEFAULT '0',
                status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
                note LONGTEXT NULL,
                PRIMARY KEY (id),
                INDEX idx_pol_po (purchase_order_id),
                CONSTRAINT fk_pol_po FOREIGN KEY (purchase_order_id) REFERENCES procurement__purchase_orders (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__supplier_receipts (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                purchase_order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                delivery_note_reference VARCHAR(128) NOT NULL,
                match_type VARCHAR(32) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'PENDING_REVIEW',
                validated_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                received_by BINARY(16) NULL COMMENT '(DC2Type:uuid)',
                comment LONGTEXT NULL,
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sr_supplier_dnr (supplier_id, delivery_note_reference),
                INDEX idx_sr_clinic_status (clinic_id, status),
                INDEX idx_sr_po (purchase_order_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__supplier_receipt_lines (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_receipt_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                purchase_order_line_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                article_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                received_amount VARCHAR(32) NOT NULL,
                received_unit VARCHAR(32) NOT NULL,
                lot_number VARCHAR(64) NULL,
                lot_expiry_date DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                lot_manufactured_at DATETIME NULL COMMENT '(DC2Type:datetime_immutable)',
                actual_unit_price_minor INT NULL,
                actual_unit_price_currency CHAR(3) NULL,
                note LONGTEXT NULL,
                PRIMARY KEY (id),
                INDEX idx_srl_receipt (supplier_receipt_id),
                CONSTRAINT fk_srl_receipt FOREIGN KEY (supplier_receipt_id) REFERENCES procurement__supplier_receipts (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__purchase_order_number_sequences (
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                year SMALLINT NOT NULL,
                last_number INT NOT NULL DEFAULT 0,
                PRIMARY KEY (clinic_id, year)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__simulated_orders (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                purchase_order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                external_reference VARCHAR(255) NOT NULL,
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                profile VARCHAR(32) NOT NULL DEFAULT 'DEMO_FAST',
                delivery_delay_seconds INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_so_external_ref (external_reference)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__simulated_deliveries (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                simulated_order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                purchase_order_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                delivery_note_reference VARCHAR(255) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                available_at DATETIME NOT NULL,
                fetched_at DATETIME NULL,
                PRIMARY KEY (id),
                INDEX idx_sd_available (available_at, fetched_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE procurement__unmatched_deliveries (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                clinic_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                supplier_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                delivery_note_reference VARCHAR(128) NOT NULL,
                raw_payload_json LONGTEXT NOT NULL,
                received_at DATETIME NOT NULL,
                resolved_at DATETIME NULL,
                resolved_by BINARY(16) NULL COMMENT '(DC2Type:uuid)',
                PRIMARY KEY (id),
                INDEX idx_unmatched_clinic_resolved (clinic_id, resolved_at),
                UNIQUE KEY uniq_unmatched_supplier_dnr (supplier_id, delivery_note_reference)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS procurement__unmatched_deliveries');
        $this->addSql('DROP TABLE IF EXISTS procurement__simulated_deliveries');
        $this->addSql('DROP TABLE IF EXISTS procurement__simulated_orders');
        $this->addSql('DROP TABLE IF EXISTS procurement__purchase_order_number_sequences');
        $this->addSql('DROP TABLE IF EXISTS procurement__supplier_receipt_lines');
        $this->addSql('DROP TABLE IF EXISTS procurement__supplier_receipts');
        $this->addSql('DROP TABLE IF EXISTS procurement__purchase_order_lines');
        $this->addSql('DROP TABLE IF EXISTS procurement__purchase_orders');
        $this->addSql('DROP TABLE IF EXISTS procurement__supplier_pricing');
        $this->addSql('DROP TABLE IF EXISTS procurement__supplier_catalog_entries');
        $this->addSql('DROP TABLE IF EXISTS procurement__supplier_accounts');
        $this->addSql('DROP TABLE IF EXISTS procurement__suppliers');
    }
}
