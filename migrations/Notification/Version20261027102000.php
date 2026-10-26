<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification: in-app notifications (the bell).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__in_app_notifications (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) NOT NULL,
              user_id BINARY(16) NOT NULL,
              kind VARCHAR(24) NOT NULL,
              title VARCHAR(255) NOT NULL,
              detail VARCHAR(500) NOT NULL,
              route VARCHAR(120) NOT NULL,
              route_params JSON NOT NULL,
              actor_initials VARCHAR(4) DEFAULT NULL,
              idempotency_key VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL,
              read_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_notif_inapp_key_user (idempotency_key, user_id),
              INDEX idx_notif_inapp_user_clinic (user_id, clinic_id, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification__in_app_notifications');
    }
}
