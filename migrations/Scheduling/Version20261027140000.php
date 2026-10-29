<?php

declare(strict_types=1);

namespace DoctrineMigrations\Scheduling;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scheduling: what each appointment\'s client was told, its reminder, and the reminder delay of each clinic.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__appointment_notifications (
              appointment_id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              confirmed_at DATETIME DEFAULT NULL,
              cancellation_notified_at DATETIME DEFAULT NULL,
              silenced TINYINT(1) DEFAULT 0 NOT NULL,
              notified_starts_at_utc DATETIME DEFAULT NULL,
              notified_duration INT DEFAULT NULL,
              notified_practitioner_user_id BINARY(16) DEFAULT NULL,
              notification_counter INT DEFAULT 0 NOT NULL,
              reminder_due_at_utc DATETIME DEFAULT NULL,
              reminder_requested_at DATETIME DEFAULT NULL,
              INDEX idx_sched_notif_clinic (clinic_id),
              INDEX idx_sched_notif_reminder_due (reminder_due_at_utc),
              PRIMARY KEY (appointment_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduling__clinic_settings (
              clinic_id BINARY(16) NOT NULL,
              reminder_delay VARCHAR(16) DEFAULT 'H24' NOT NULL,
              PRIMARY KEY (clinic_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduling__appointment_notifications');
        $this->addSql('DROP TABLE scheduling__clinic_settings');
    }
}
