<?php

declare(strict_types=1);

namespace DoctrineMigrations\ElectronicInvoicing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ElectronicInvoicing tables: platform connections, outbound transmissions, lifecycle events, unprocessed flows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE electronic_invoicing__lifecycle_events (
              id BINARY(16) NOT NULL,
              transmission_id BINARY(16) NOT NULL,
              status_code SMALLINT NOT NULL,
              reason_code VARCHAR(40) DEFAULT NULL,
              reason_text VARCHAR(512) DEFAULT NULL,
              source_flow_id VARCHAR(64) NOT NULL,
              occurred_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_ei_lifecycle_event (
                transmission_id, status_code, source_flow_id
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE electronic_invoicing__outbound_transmissions (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              kind VARCHAR(16) NOT NULL,
              tracking_id VARCHAR(64) NOT NULL,
              document_number VARCHAR(40) NOT NULL,
              document_date DATE NOT NULL,
              seller_siren VARCHAR(9) NOT NULL,
              buyer_siren VARCHAR(9) DEFAULT NULL,
              preceding_document_number VARCHAR(40) DEFAULT NULL,
              payload JSON NOT NULL,
              connection_id BINARY(16) DEFAULT NULL,
              state VARCHAR(16) NOT NULL,
              block_reason VARCHAR(40) DEFAULT NULL,
              failure_detail LONGTEXT DEFAULT NULL,
              document_id VARCHAR(255) DEFAULT NULL,
              document_sha256 VARCHAR(64) DEFAULT NULL,
              electronic_address_scheme VARCHAR(8) DEFAULT NULL,
              electronic_address_identifier VARCHAR(64) DEFAULT NULL,
              flow_id VARCHAR(64) DEFAULT NULL,
              submission_requested_at DATETIME DEFAULT NULL,
              submission_attempts INT NOT NULL,
              submitted_at DATETIME DEFAULT NULL,
              internal_because SMALLINT DEFAULT NULL,
              sequence INT NOT NULL,
              version INT DEFAULT 1 NOT NULL,
              prepared_at DATETIME NOT NULL,
              INDEX idx_ei_transmission_schedule (
                seller_siren, state, submission_requested_at
              ),
              UNIQUE INDEX uniq_ei_transmission_tracking (tracking_id),
              UNIQUE INDEX uniq_ei_transmission_document (
                seller_siren, kind, document_number
              ),
              UNIQUE INDEX uniq_ei_transmission_flow (flow_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE electronic_invoicing__platform_connections (
              id BINARY(16) NOT NULL,
              organisation_id VARCHAR(9) NOT NULL,
              platform VARCHAR(32) NOT NULL,
              state VARCHAR(16) NOT NULL,
              rejection_reason VARCHAR(255) DEFAULT NULL,
              cursor_updated_after DATETIME NOT NULL,
              cursor_last_flow_id VARCHAR(64) NOT NULL,
              opened_at DATETIME NOT NULL,
              verified_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_ei_connection_organisation (organisation_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE electronic_invoicing__unprocessed_flows (
              id BINARY(16) NOT NULL,
              organisation_id VARCHAR(9) NOT NULL,
              flow_id VARCHAR(64) NOT NULL,
              reason VARCHAR(16) NOT NULL,
              error LONGTEXT DEFAULT NULL,
              content LONGTEXT DEFAULT NULL,
              attempts INT NOT NULL,
              last_seen_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_ei_unprocessed_flow (organisation_id, flow_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE electronic_invoicing__lifecycle_events');
        $this->addSql('DROP TABLE electronic_invoicing__outbound_transmissions');
        $this->addSql('DROP TABLE electronic_invoicing__platform_connections');
        $this->addSql('DROP TABLE electronic_invoicing__unprocessed_flows');
    }
}
