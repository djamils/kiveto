<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201105804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
              INDEX idx_clinic_practitioner_starts (
                clinic_id, practitioner_user_id,
                starts_at_utc
              ),
              INDEX idx_status (status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
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
              priority INT DEFAULT 0 NOT NULL,
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
              UNIQUE INDEX uniq_linked_appointment (linked_appointment_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE scheduling__appointments');
        $this->addSql('DROP TABLE scheduling__waiting_room_entries');
    }
}
