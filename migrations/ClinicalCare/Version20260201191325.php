<?php

declare(strict_types=1);

namespace DoctrineMigrations\ClinicalCare;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201191325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE clinical_care__clinical_notes (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              note_type VARCHAR(30) NOT NULL,
              content LONGTEXT NOT NULL,
              created_at_utc DATETIME NOT NULL,
              created_by_user_id VARBINARY(16) NOT NULL,
              INDEX idx_consultation_created (consultation_id, created_at_utc),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE clinical_care__consultations (
              id VARBINARY(16) NOT NULL,
              clinic_id VARBINARY(16) NOT NULL,
              appointment_id VARBINARY(16) DEFAULT NULL,
              waiting_room_entry_id VARBINARY(16) DEFAULT NULL,
              owner_id VARBINARY(16) DEFAULT NULL,
              animal_id VARBINARY(16) DEFAULT NULL,
              practitioner_user_id VARBINARY(16) NOT NULL,
              status VARCHAR(20) NOT NULL,
              chief_complaint LONGTEXT DEFAULT NULL,
              summary LONGTEXT DEFAULT NULL,
              weight_kg NUMERIC(6, 3) DEFAULT NULL,
              temperature_c NUMERIC(4, 2) DEFAULT NULL,
              started_at_utc DATETIME NOT NULL,
              closed_at_utc DATETIME DEFAULT NULL,
              created_at_utc DATETIME NOT NULL,
              updated_at_utc DATETIME NOT NULL,
              INDEX idx_clinic_started (clinic_id, started_at_utc),
              INDEX idx_animal (animal_id),
              INDEX idx_waiting_entry (waiting_room_entry_id),
              INDEX idx_status (status),
              UNIQUE INDEX unique_appointment (appointment_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE clinical_care__performed_acts (
              id VARBINARY(16) NOT NULL,
              consultation_id VARBINARY(16) NOT NULL,
              label VARCHAR(255) NOT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              performed_at_utc DATETIME NOT NULL,
              created_at_utc DATETIME NOT NULL,
              created_by_user_id VARBINARY(16) NOT NULL,
              INDEX idx_consultation_performed (
                consultation_id, performed_at_utc
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE clinical_care__clinical_notes');
        $this->addSql('DROP TABLE clinical_care__consultations');
        $this->addSql('DROP TABLE clinical_care__performed_acts');
    }
}
