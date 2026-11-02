<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification: Portal account of the owner messages, dispatch claim and erasure pending on outbound messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification__outbound_messages ADD account_id BINARY(16) DEFAULT NULL, ADD dispatch_claimed_at DATETIME DEFAULT NULL, ADD erasure_pending TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_notif_account ON notification__outbound_messages (account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_notif_account ON notification__outbound_messages');
        $this->addSql('ALTER TABLE notification__outbound_messages DROP account_id, DROP dispatch_claimed_at, DROP erasure_pending');
    }
}
