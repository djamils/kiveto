<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification tables: outbound messages, unmatched delivery events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__outbound_messages (
              id BINARY(16) NOT NULL,
              clinic_id BINARY(16) DEFAULT NULL,
              client_id BINARY(16) DEFAULT NULL,
              purpose VARCHAR(32) NOT NULL,
              channel VARCHAR(8) NOT NULL,
              criticality VARCHAR(16) NOT NULL,
              recipient_address VARCHAR(320) DEFAULT NULL,
              recipient_name VARCHAR(255) DEFAULT NULL,
              sender JSON DEFAULT NULL,
              subject_type VARCHAR(40) NOT NULL,
              subject_id VARCHAR(64) NOT NULL,
              idempotency_key VARCHAR(190) NOT NULL,
              content JSON DEFAULT NULL,
              segments INT DEFAULT 0 NOT NULL,
              attachments JSON NOT NULL,
              status VARCHAR(16) NOT NULL,
              external_message_id VARCHAR(128) DEFAULT NULL,
              status_history JSON NOT NULL,
              complained_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              sent_at DATETIME DEFAULT NULL,
              status_changed_at DATETIME NOT NULL,
              content_purged_at DATETIME DEFAULT NULL,
              version INT DEFAULT 1 NOT NULL,
              UNIQUE INDEX uniq_notif_idempotency (idempotency_key),
              INDEX idx_notif_external (channel, external_message_id),
              INDEX idx_notif_clinic_sent (clinic_id, channel, sent_at),
              INDEX idx_notif_clinic_status (clinic_id, channel, status),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification__unmatched_delivery_events (
              id BINARY(16) NOT NULL,
              channel VARCHAR(8) NOT NULL,
              external_id VARCHAR(128) NOT NULL,
              payload JSON NOT NULL,
              received_at DATETIME NOT NULL,
              INDEX idx_notif_unmatched_external (channel, external_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification__unmatched_delivery_events');
        $this->addSql('DROP TABLE notification__outbound_messages');
    }
}
