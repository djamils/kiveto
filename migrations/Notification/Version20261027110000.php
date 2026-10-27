<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification: index for the nightly content purge (unpurged messages by creation date).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_notif_purge ON notification__outbound_messages (content_purged_at, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_notif_purge ON notification__outbound_messages');
    }
}
