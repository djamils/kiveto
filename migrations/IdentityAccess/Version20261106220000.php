<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: the role of a member of the Kiveto team in the console (admin, support).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE identity_access__backoffice_profiles ADD role VARCHAR(16) DEFAULT 'admin' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__backoffice_profiles DROP role');
    }
}
