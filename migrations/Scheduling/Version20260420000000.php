<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RenamePlanningBlockPractitionerUserIdToStaffUserId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__planning_blocks RENAME COLUMN practitioner_user_id TO staff_user_id');
        $this->addSql('DROP INDEX idx_pb_clinic_pract ON scheduling__planning_blocks');
        $this->addSql(
            'CREATE INDEX idx_pb_clinic_staff ON scheduling__planning_blocks (clinic_id, staff_user_id, date)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__planning_blocks RENAME COLUMN staff_user_id TO practitioner_user_id');
        $this->addSql('DROP INDEX idx_pb_clinic_staff ON scheduling__planning_blocks');
        $this->addSql(
            'CREATE INDEX idx_pb_clinic_pract ON scheduling__planning_blocks (clinic_id, practitioner_user_id, date)',
        );
    }
}
