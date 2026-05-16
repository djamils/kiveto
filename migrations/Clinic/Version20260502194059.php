<?php

declare(strict_types=1);

namespace DoctrineMigrations\Clinic;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502194059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE clinic__staff_profiles DROP FOREIGN KEY `fk_staff_profile_membership`');
        $this->addSql('DROP INDEX idx_staff_profile_membership_id ON clinic__staff_profiles');
        $this->addSql('ALTER TABLE clinic__staff_profiles CHANGE agenda_color agenda_color VARCHAR(7) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic__clinics DROP version');
        $this->addSql('ALTER TABLE clinic__staff_profiles CHANGE agenda_color agenda_color CHAR(7) NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              clinic__staff_profiles
            ADD
              CONSTRAINT `fk_staff_profile_membership`
              FOREIGN KEY (membership_id) REFERENCES clinic__clinic_memberships (id) ON
            UPDATE
              NO ACTION
        SQL);
        $this->addSql('CREATE INDEX idx_staff_profile_membership_id ON clinic__staff_profiles (membership_id)');
    }
}
