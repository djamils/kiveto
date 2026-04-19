<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419135302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CreatePlanningBlocksTable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__planning_blocks (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              practitioner_user_id BINARY(16) NOT NULL,
              type VARCHAR(50) NOT NULL,
              date VARCHAR(10) NOT NULL,
              start_time VARCHAR(5) NOT NULL,
              end_time VARCHAR(5) NOT NULL,
              capacity_per_hour INT NOT NULL,
              recurrence_rule JSON NOT NULL,
              note VARCHAR(500) DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              updated_at_utc DATETIME NOT NULL,
              INDEX idx_pb_clinic_date (clinic_id, date),
              INDEX idx_pb_clinic_pract (
                clinic_id, practitioner_user_id,
                date
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduling__planning_blocks');
    }
}
