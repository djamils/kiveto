<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Running a clinic is a hat a member wears, not the job they do.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships ADD is_clinic_administrator TINYINT(1) NOT NULL DEFAULT 0');

        // Until now, holding the manager role WAS holding manage_staff. Move that
        // source onto the flag for rows that already exist, so a developer who
        // migrates without reloading fixtures keeps the access they had. No status
        // or window filter: the old right was gated at read time rather than
        // stored, and a manager who is later reactivated must regain it.
        $this->addSql("UPDATE clinic__clinic_memberships SET is_clinic_administrator = 1 WHERE role = 'MANAGER'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinic_memberships DROP is_clinic_administrator');
    }
}
