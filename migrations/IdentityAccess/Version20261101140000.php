<?php

declare(strict_types=1);

namespace DoctrineMigrations\IdentityAccess;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IdentityAccess: owner accounts anonymised after their deletion request (anonymised_at).';
    }

    public function up(Schema $schema): void
    {
        // An anonymised PORTAL account has a NULL phone and is DISABLED: the
        // rule of Version20261029120000 for the phone-less owner rows holds.
        $this->addSql('ALTER TABLE identity_access__users ADD anonymised_at DATETIME DEFAULT NULL AFTER last_login_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_access__users DROP anonymised_at');
    }
}
