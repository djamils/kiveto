<?php

declare(strict_types=1);

namespace DoctrineMigrations\Admission;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426114354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE admission__admissions (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              patient_id BINARY(16) NOT NULL,
              is_patient_identified_at_opening TINYINT NOT NULL,
              intake_channel VARCHAR(32) NOT NULL,
              triage_level VARCHAR(32) NOT NULL,
              presenter_name VARCHAR(255) DEFAULT NULL,
              presenter_phone VARCHAR(32) DEFAULT NULL,
              presenter_role VARCHAR(32) DEFAULT NULL,
              location_status_value VARCHAR(32) NOT NULL,
              location_status_entered_at DATETIME NOT NULL,
              status VARCHAR(16) NOT NULL,
              closure_reason VARCHAR(32) DEFAULT NULL,
              appointment_id BINARY(16) DEFAULT NULL,
              physical_description LONGTEXT DEFAULT NULL,
              triage_notes LONGTEXT DEFAULT NULL,
              version INT DEFAULT 1 NOT NULL,
              opened_at DATETIME NOT NULL,
              closed_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_admission_clinic_status (clinic_id, status),
              INDEX idx_admission_clinic_patient (clinic_id, patient_id),
              INDEX idx_admission_waiting (
                clinic_id, status, location_status_value
              ),
              UNIQUE INDEX uniq_admission_appointment (appointment_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admission__admissions');
    }
}
