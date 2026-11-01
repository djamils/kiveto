<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClientPortal;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102091100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ClientPortal: when a pending client link was requested (accepted link suggestion), and the clinic\'s pending links by status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_portal__client_links ADD requested_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_client_link_clinic_status ON client_portal__client_links (clinic_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_client_link_clinic_status ON client_portal__client_links');
        $this->addSql('ALTER TABLE client_portal__client_links DROP requested_at');
    }
}
