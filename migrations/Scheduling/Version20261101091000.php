<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: online booking motifs, their practitioners and the online booking settings of each clinic.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__booking_motifs (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              label VARCHAR(80) NOT NULL,
              description VARCHAR(500) DEFAULT NULL,
              duration_min INT NOT NULL,
              online_enabled TINYINT NOT NULL,
              confirmation_known_client VARCHAR(16) NOT NULL,
              allowed_block_types JSON NOT NULL,
              preventive_care_kind VARCHAR(32) DEFAULT NULL,
              new_clients_allowed TINYINT NOT NULL,
              sort_order INT NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              INDEX idx_sched_motif_clinic (clinic_id, sort_order),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__booking_motif_practitioners (
              user_id BINARY(16) NOT NULL,
              motif_id BINARY(16) NOT NULL,
              INDEX IDX_1A28CCA5D0EEB819 (motif_id),
              INDEX idx_sched_motif_practitioner_user (user_id),
              PRIMARY KEY (motif_id, user_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__online_booking_settings (
              enabled TINYINT NOT NULL,
              grid_step_minutes INT NOT NULL,
              min_lead_time_minutes INT NOT NULL,
              min_lead_time_manual_hours INT NOT NULL,
              horizon_days INT NOT NULL,
              cancellation_window_hours INT NOT NULL,
              new_client_booking_enabled TINYINT NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE scheduling__booking_motif_practitioners ADD CONSTRAINT FK_1A28CCA5D0EEB819 FOREIGN KEY (motif_id) REFERENCES scheduling__booking_motifs (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduling__booking_motif_practitioners DROP FOREIGN KEY FK_1A28CCA5D0EEB819');
        $this->addSql('DROP TABLE scheduling__booking_motif_practitioners');
        $this->addSql('DROP TABLE scheduling__booking_motifs');
        $this->addSql('DROP TABLE scheduling__online_booking_settings');
    }
}
