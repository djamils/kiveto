<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426114330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduling__waiting_room_entries');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              scheduling__appointments
            ADD
              linked_admission_id BINARY(16) DEFAULT NULL,
            DROP
              owner_id,
            DROP
              animal_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__waiting_room_entries (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              origin VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              arrival_mode VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
              linked_appointment_id BINARY(16) DEFAULT NULL,
              owner_id BINARY(16) DEFAULT NULL,
              animal_id BINARY(16) DEFAULT NULL,
              found_animal_description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`,
              priority INT DEFAULT 0 NOT NULL,
              triage_notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`,
              status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`,
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
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              scheduling__appointments
            ADD
              animal_id BINARY(16) DEFAULT NULL,
            CHANGE
              linked_admission_id owner_id BINARY(16) DEFAULT NULL
        SQL);
    }
}
