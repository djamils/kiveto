<?php

declare(strict_types=1);

namespace DoctrineMigrations\Patient;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426114348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE patient__patients (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              display_label_kind VARCHAR(16) NOT NULL,
              display_label_value VARCHAR(255) NOT NULL,
              animal_link_id BINARY(16) DEFAULT NULL,
              observed_species VARCHAR(64) DEFAULT NULL,
              observed_color VARCHAR(64) DEFAULT NULL,
              version INT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_patient_clinic_status (clinic_id, status),
              INDEX idx_patient_clinic_animal (clinic_id, animal_link_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE patient__patients');
    }
}
