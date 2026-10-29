<?php

declare(strict_types=1);

namespace DoctrineMigrations\Billing;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invoice dispatch log (sends and reminders) and the document PDFs stored at their first send.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE billing__invoice_dispatches (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              invoice_id BINARY(16) NOT NULL,
              kind VARCHAR(16) NOT NULL,
              channels JSON NOT NULL,
              notification_ids JSON NOT NULL,
              author_user_id BINARY(16) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_billing_dispatch_invoice (invoice_id, kind),
              INDEX idx_billing_dispatch_clinic_kind (clinic_id, kind, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE billing__sent_document_files (
              document_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              document_kind VARCHAR(16) NOT NULL,
              storage_key VARCHAR(255) NOT NULL,
              filename VARCHAR(255) NOT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (document_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing__invoice_dispatches');
        $this->addSql('DROP TABLE billing__sent_document_files');
    }
}
