<?php

declare(strict_types=1);

namespace DoctrineMigrations\Inventory;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Inventory BC tables: stock_items, lots, stock_movements, stock_alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory__stock_items (
                id                    BINARY(16)   NOT NULL,
                clinic_id             BINARY(16)   NOT NULL,
                article_id            BINARY(16)   NOT NULL,
                total_on_hand_amount  VARCHAR(32)  NOT NULL,
                total_on_hand_unit    VARCHAR(16)  NOT NULL,
                reserved_amount       VARCHAR(32)  NOT NULL DEFAULT '0.000000',
                threshold_amount      VARCHAR(32)  NOT NULL,
                threshold_unit        VARCHAR(16)  NOT NULL,
                threshold_type        VARCHAR(16)  NOT NULL,
                track_stock           TINYINT(1)   NOT NULL DEFAULT 1,
                status                VARCHAR(16)  NOT NULL DEFAULT 'ACTIVE',
                version               INT          NOT NULL DEFAULT 1,
                created_at            DATETIME     NOT NULL,
                updated_at            DATETIME     NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_si_clinic_article (clinic_id, article_id),
                INDEX idx_si_clinic_status (clinic_id, status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory__lots (
                id              BINARY(16)   NOT NULL,
                stock_item_id   BINARY(16)   NOT NULL,
                lot_number      VARCHAR(64)  NOT NULL,
                quantity_amount VARCHAR(32)  NOT NULL,
                quantity_unit   VARCHAR(16)  NOT NULL,
                expiry_date     DATE         NOT NULL,
                received_at     DATETIME     NULL,
                source_supplier BINARY(16)   NULL,
                status          VARCHAR(16)  NOT NULL DEFAULT 'ACTIVE',
                created_at      DATETIME     NOT NULL,
                updated_at      DATETIME     NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_lot_stock_item_number (stock_item_id, lot_number),
                INDEX idx_lot_stock_item (stock_item_id),
                INDEX idx_lots_active_expiry (status, expiry_date),
                INDEX idx_lot_stock_status (stock_item_id, status),
                CONSTRAINT fk_lot_stock_item FOREIGN KEY (stock_item_id) REFERENCES inventory__stock_items (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory__stock_movements (
                id              BINARY(16)   NOT NULL,
                clinic_id       BINARY(16)   NOT NULL,
                article_id      BINARY(16)   NOT NULL,
                lot_id          BINARY(16)   NULL,
                type            VARCHAR(16)  NOT NULL,
                reason          VARCHAR(64)  NOT NULL,
                quantity_amount VARCHAR(32)  NOT NULL,
                quantity_unit   VARCHAR(16)  NOT NULL,
                occurred_at     DATETIME     NOT NULL,
                reference       VARCHAR(128) NULL,
                performed_by    BINARY(16)   NULL,
                note            TEXT         NULL,
                created_at      DATETIME     NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_sm_clinic_article (clinic_id, article_id),
                INDEX idx_sm_clinic_occurred (clinic_id, occurred_at),
                INDEX idx_sm_lot (lot_id),
                INDEX idx_sm_reason (reason),
                INDEX idx_sm_reference (reference)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory__stock_alerts (
                id                    BINARY(16)   NOT NULL,
                clinic_id             BINARY(16)   NOT NULL,
                stock_item_id         BINARY(16)   NOT NULL,
                article_id            BINARY(16)   NOT NULL,
                article_name          VARCHAR(255) NOT NULL,
                type                  VARCHAR(32)  NOT NULL,
                severity              VARCHAR(16)  NOT NULL,
                current_level_amount  VARCHAR(32)  NULL,
                current_level_unit    VARCHAR(16)  NULL,
                earliest_expiry       DATE         NULL,
                detected_at           DATETIME     NOT NULL,
                resolved_at           DATETIME     NULL,
                PRIMARY KEY (id),
                INDEX idx_alert_clinic_resolved (clinic_id, resolved_at),
                INDEX idx_alert_severity (severity),
                INDEX idx_alert_stock_item_type (stock_item_id, type),
                CONSTRAINT fk_alert_stock_item FOREIGN KEY (stock_item_id) REFERENCES inventory__stock_items (id) ON DELETE CASCADE,
                CONSTRAINT chk_current_level CHECK (
                    (current_level_amount IS NULL AND current_level_unit IS NULL)
                    OR (current_level_amount IS NOT NULL AND current_level_unit IS NOT NULL)
                )
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS inventory__stock_alerts');
        $this->addSql('DROP TABLE IF EXISTS inventory__lots');
        $this->addSql('DROP TABLE IF EXISTS inventory__stock_movements');
        $this->addSql('DROP TABLE IF EXISTS inventory__stock_items');
    }
}
