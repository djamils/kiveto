<?php

declare(strict_types=1);

namespace DoctrineMigrations\Prescription;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the Prescription bounded context: ordonnances, their frozen lines,
 * the renewals granted on them, and the per-clinic numbering sequence.
 */
final class Version20260822010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Prescription tables: prescriptions, lines, renewals and number sequences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE prescription__prescriptions (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                consultation_id BINARY(16) NOT NULL,
                number VARCHAR(20) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                prescriber_user_id BINARY(16) NOT NULL,
                prescriber_display_name VARCHAR(255) NOT NULL,
                prescriber_registration_number VARCHAR(64) DEFAULT NULL,
                prescriber_professional_title VARCHAR(32) DEFAULT NULL,
                patient_animal_id BINARY(16) DEFAULT NULL,
                patient_name VARCHAR(255) NOT NULL,
                patient_species VARCHAR(32) DEFAULT NULL,
                patient_breed VARCHAR(120) DEFAULT NULL,
                patient_birth_date DATE DEFAULT NULL,
                patient_weight_kg NUMERIC(6, 3) DEFAULT NULL,
                patient_microchip_number VARCHAR(32) DEFAULT NULL,
                owner_client_id BINARY(16) DEFAULT NULL,
                owner_name VARCHAR(255) NOT NULL,
                owner_address_lines JSON NOT NULL,
                issued_at DATETIME DEFAULT NULL,
                valid_until DATETIME DEFAULT NULL,
                validity_span_days SMALLINT DEFAULT NULL,
                renewals_allowed SMALLINT NOT NULL,
                renewals_used SMALLINT NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                cancellation_reason VARCHAR(255) DEFAULT NULL,
                INDEX idx_prescription_clinic_status (clinic_id, status),
                INDEX idx_prescription_clinic_issued (clinic_id, issued_at),
                INDEX idx_prescription_consultation (consultation_id),
                UNIQUE INDEX uniq_prescription_number (clinic_id, number),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE prescription__prescription_lines (
                id BINARY(16) NOT NULL,
                prescription_id BINARY(16) NOT NULL,
                article_id BINARY(16) DEFAULT NULL,
                code VARCHAR(40) DEFAULT NULL,
                label VARCHAR(255) NOT NULL,
                pharmaceutical_form VARCHAR(128) DEFAULT NULL,
                active_substances JSON NOT NULL,
                dose VARCHAR(60) DEFAULT NULL,
                frequency VARCHAR(60) DEFAULT NULL,
                duration_days INT DEFAULT NULL,
                route VARCHAR(60) DEFAULT NULL,
                quantity NUMERIC(10, 2) NOT NULL,
                prescription_class VARCHAR(20) NOT NULL,
                instructions LONGTEXT DEFAULT NULL,
                position SMALLINT NOT NULL,
                INDEX idx_prescription_line_prescription (prescription_id, position),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE prescription__renewals (
                id BINARY(16) NOT NULL,
                prescription_id BINARY(16) NOT NULL,
                renewed_at DATETIME NOT NULL,
                new_valid_until DATETIME NOT NULL,
                renewed_by_user_id BINARY(16) DEFAULT NULL,
                position SMALLINT NOT NULL,
                INDEX idx_renewal_prescription (prescription_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE prescription__number_sequences (
                id BINARY(16) NOT NULL,
                clinic_id BINARY(16) NOT NULL,
                year SMALLINT NOT NULL,
                `last_value` INT NOT NULL,
                UNIQUE INDEX uniq_prescription_sequence (clinic_id, year),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE prescription__prescription_lines ADD CONSTRAINT fk_prescription_line_prescription
            FOREIGN KEY (prescription_id) REFERENCES prescription__prescriptions (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE prescription__renewals ADD CONSTRAINT fk_renewal_prescription
            FOREIGN KEY (prescription_id) REFERENCES prescription__prescriptions (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prescription__renewals DROP FOREIGN KEY fk_renewal_prescription');
        $this->addSql('ALTER TABLE prescription__prescription_lines DROP FOREIGN KEY fk_prescription_line_prescription');
        $this->addSql('DROP TABLE prescription__renewals');
        $this->addSql('DROP TABLE prescription__prescription_lines');
        $this->addSql('DROP TABLE prescription__number_sequences');
        $this->addSql('DROP TABLE prescription__prescriptions');
    }
}
