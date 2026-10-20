<?php

declare(strict_types=1);

namespace DoctrineMigrations\ElectronicInvoicing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ElectronicInvoicing: flows of the local platform simulator.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE electronic_invoicing__simulated_flows (
              flow_id VARCHAR(64) NOT NULL,
              organisation_id VARCHAR(9) NOT NULL,
              tracking_id VARCHAR(64) NOT NULL,
              sha256 VARCHAR(64) NOT NULL,
              document_number VARCHAR(64) NOT NULL,
              type_code VARCHAR(3) NOT NULL,
              issue_date DATE DEFAULT NULL,
              seller_siren VARCHAR(9) NOT NULL,
              buyer_siren VARCHAR(9) DEFAULT NULL,
              scenario VARCHAR(20) NOT NULL,
              file LONGTEXT NOT NULL,
              created_at DATETIME NOT NULL,
              statuses JSON NOT NULL,
              next_step SMALLINT DEFAULT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_ei_simulated_flow_organisation (organisation_id),
              INDEX idx_ei_simulated_flow_document (seller_siren, document_number),
              UNIQUE INDEX uniq_ei_simulated_flow_deposit (tracking_id, sha256),
              PRIMARY KEY (flow_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE electronic_invoicing__simulated_flows');
    }
}
