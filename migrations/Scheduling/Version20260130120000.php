<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Scheduling BC tables: appointments and waiting_room_entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__appointments (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              owner_id BINARY(16) DEFAULT NULL,
              animal_id BINARY(16) DEFAULT NULL,
              practitioner_user_id BINARY(16) DEFAULT NULL,
              starts_at_utc DATETIME NOT NULL,
              duration_minutes INT NOT NULL,
              status VARCHAR(20) NOT NULL,
              reason LONGTEXT DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              service_started_at_utc DATETIME DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              updated_at_utc DATETIME NOT NULL,
              INDEX idx_clinic_starts (clinic_id, starts_at_utc),
              INDEX idx_clinic_practitioner_starts (clinic_id, practitioner_user_id, starts_at_utc),
              INDEX idx_status (status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__waiting_room_entries (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              origin VARCHAR(20) NOT NULL,
              arrival_mode VARCHAR(20) NOT NULL,
              linked_appointment_id BINARY(16) DEFAULT NULL,
              owner_id BINARY(16) DEFAULT NULL,
              animal_id BINARY(16) DEFAULT NULL,
              found_animal_description LONGTEXT DEFAULT NULL,
              priority INT NOT NULL DEFAULT 0,
              triage_notes LONGTEXT DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              arrived_at_utc DATETIME NOT NULL,
              called_at_utc DATETIME DEFAULT NULL,
              service_started_at_utc DATETIME DEFAULT NULL,
              closed_at_utc DATETIME DEFAULT NULL,
              called_by_user_id BINARY(16) DEFAULT NULL,
              service_started_by_user_id BINARY(16) DEFAULT NULL,
              closed_by_user_id BINARY(16) DEFAULT NULL,
              INDEX idx_clinic_status (clinic_id, status),
              INDEX idx_linked_appointment (linked_appointment_id),
              UNIQUE INDEX uniq_linked_appointment (linked_appointment_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduling__waiting_room_entries');
        $this->addSql('DROP TABLE scheduling__appointments');
    }
}
