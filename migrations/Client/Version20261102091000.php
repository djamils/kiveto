<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client: index contact methods by type and value, for the cross-clinic mobile lookup of the Portal link suggestions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_contact_method_type_value ON client__contact_methods (type, value, client_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_contact_method_type_value ON client__contact_methods');
    }
}
