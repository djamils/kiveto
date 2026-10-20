<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transmission status of professional documents, projected from ElectronicInvoicing, and the neutral document frozen at issue.';
    }

    public function up(Schema $schema): void
    {
        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql(<<<SQL
                ALTER TABLE {$table}
                  ADD transmission_state VARCHAR(20) DEFAULT NULL,
                  ADD transmission_document JSON DEFAULT NULL,
                  ADD transmission_block_reason VARCHAR(40) DEFAULT NULL,
                  ADD transmission_status_code SMALLINT DEFAULT NULL,
                  ADD transmission_status_label VARCHAR(64) DEFAULT NULL,
                  ADD transmission_reason_code VARCHAR(40) DEFAULT NULL,
                  ADD transmission_reason VARCHAR(512) DEFAULT NULL,
                  ADD transmission_requires_action TINYINT(1) NOT NULL DEFAULT 0,
                  ADD transmission_sequence INT DEFAULT NULL,
                  ADD transmission_updated_at DATETIME DEFAULT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['billing__invoices', 'billing__credit_notes'] as $table) {
            $this->addSql(<<<SQL
                ALTER TABLE {$table}
                  DROP transmission_state, DROP transmission_document, DROP transmission_block_reason, DROP transmission_status_code,
                  DROP transmission_status_label, DROP transmission_reason_code, DROP transmission_reason,
                  DROP transmission_requires_action, DROP transmission_sequence, DROP transmission_updated_at
            SQL);
        }
    }
}
