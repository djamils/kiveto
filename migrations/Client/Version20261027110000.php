<?php

declare(strict_types=1);

namespace DoctrineMigrations\Client;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Client journal: automated entries following the delivery status of a Notification message.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE client__client_communications
              ADD origin VARCHAR(20) DEFAULT 'manual' NOT NULL,
              ADD delivery_status VARCHAR(20) DEFAULT NULL,
              ADD notification_message_id BINARY(16) DEFAULT NULL,
              ADD delivery_status_sequence INT DEFAULT NULL,
              ADD status_changed_at DATETIME DEFAULT NULL
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_client_communication_notification ON client__client_communications (notification_message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_client_communication_notification ON client__client_communications');
        $this->addSql(<<<'SQL'
            ALTER TABLE client__client_communications
              DROP origin,
              DROP delivery_status,
              DROP notification_message_id,
              DROP delivery_status_sequence,
              DROP status_changed_at
        SQL);
    }
}
