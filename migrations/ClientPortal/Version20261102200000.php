<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: when a published document was last shared, apart from its other updates (S2-05); the served files looked up by key (S3-01).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_portal__published_documents ADD shared_at DATETIME DEFAULT NULL');
        // Past rows: their last update, which is what « Partagé le » showed until now.
        $this->addSql('UPDATE client_portal__published_documents SET shared_at = updated_at');
        $this->addSql('ALTER TABLE client_portal__published_documents MODIFY shared_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_published_document_key ON client_portal__published_documents (storage_key)');
        $this->addSql('CREATE INDEX idx_booklet_file_key ON client_portal__booklet_files (storage_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_booklet_file_key ON client_portal__booklet_files');
        $this->addSql('DROP INDEX idx_published_document_key ON client_portal__published_documents');
        $this->addSql('ALTER TABLE client_portal__published_documents DROP shared_at');
    }
}
